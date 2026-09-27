<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_helper.php';
require_once __DIR__ . '/../includes/locations.php';
require_once __DIR__ . '/../includes/programs_helper.php';
require_once __DIR__ . '/../includes/scholarship_type_helper.php';

$pdo = getDB();
$studentId = trim($_GET['student_id'] ?? '');
$step = $studentId !== '' ? 2 : 1;

// Every active scholarship program, with its live availability — first come, first served.
// Availability is counted fresh from actual applicants (scholarshipSlotsAvailable), not a
// stored decrement counter, so a program never shows "Limit Reached" from stale drift when
// no one has actually applied for it.
$programs = $pdo->query("
    SELECT name, type, subtype, slots, slots_available, unlimited_slots
    FROM scholarships
    WHERE LOWER(status) = 'active'
    ORDER BY type, name
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($programs as &$p) {
    $p['slots_available'] = scholarshipSlotsAvailable($pdo, $p);
}
unset($p);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Apply for a Scholarship - Scholarship Portal</title>
<link rel="icon" href="<?= SITE_BASE ?>/assets/img/cmlogoremove.png">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap">
<link rel="stylesheet" href="<?= SITE_BASE ?>/assets/css/apply.css?v=<?= time() ?>">
</head>
<body>

<div class="ap-bg"></div>

<?php if ($step === 1): ?>

<div class="ap-viewport">
    <div class="ap-id-card">
        <div class="ap-logo"><img src="<?= SITE_BASE ?>/assets/img/cmlogoremove.png" alt=""></div>
        <h1>Apply for a Scholarship</h1>
        <p class="ap-sub">Enter your Student ID to begin. No account or password needed.</p>
        <form method="GET" action="<?= SITE_BASE ?>/apply" id="idForm">
            <div class="ap-field">
                <label for="student_id">Student ID</label>
                <input type="text" id="student_id" name="student_id" placeholder="e.g. 20230001" autocomplete="off" required autofocus>
            </div>
            <button type="submit" class="ap-btn-primary" id="idContinueBtn">Continue</button>
        </form>
        <a href="<?= SITE_BASE ?>/login" class="ap-back-link">&larr; Registrar Sign In</a>
    </div>
</div>

<?php else: ?>

<div class="ap-viewport ap-viewport-wide">
    <div class="ap-form-card">

        <div class="ap-form-header">
            <div class="ap-logo"><img src="<?= SITE_BASE ?>/assets/img/cmlogoremove.png" alt=""></div>
            <div>
                <h1>Scholarship Application</h1>
                <p class="ap-sub">Student ID: <strong><?= htmlspecialchars($studentId) ?></strong> &middot; <a href="<?= SITE_BASE ?>/apply">Not you? Start over</a></p>
            </div>
        </div>

        <div id="apFlash" class="ap-flash" hidden></div>
        <div id="apSuccess" class="ap-success" hidden>
            <div class="ap-success-icon">&#10003;</div>
            <h2>Your application was successfully uploaded and is waiting for evaluation.</h2>
            <p>You'll be notified once the registrar's office reviews it.</p>
            <a href="<?= SITE_BASE ?>/login" class="ap-btn-primary ap-success-btn">Return to Main Page</a>
        </div>

        <div class="ap-progress-wrap" id="apProgressWrap">
            <p class="ap-step-counter" id="apStepCounter">Step 1 of 6</p>
            <div class="ap-progress-bar"><div class="ap-progress-fill" id="apProgressFill"></div></div>
        </div>

        <form id="apApplyForm" enctype="multipart/form-data">
            <input type="hidden" name="studentId" value="<?= htmlspecialchars($studentId) ?>">

            <div class="ap-step active" data-step="1">
                <div class="ap-section">
                    <h3>Personal Details</h3>
                    <div class="ap-grid">
                        <div class="ap-field"><label>First Name *</label><input type="text" name="firstName" required></div>
                        <div class="ap-field"><label>Middle Name</label><input type="text" name="middleName"></div>
                        <div class="ap-field"><label>Surname *</label><input type="text" name="lastName" required></div>
                        <div class="ap-field"><label>Suffix</label><input type="text" name="suffix" placeholder="Jr., Sr., III"></div>
                        <div class="ap-field">
                            <label>Gender *</label>
                            <select name="gender" required>
                                <option value="">Select</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="ap-field"><label>Contact Number *</label><input type="tel" name="phone" placeholder="09XXXXXXXXX" pattern="09[0-9]{9}" maxlength="11" inputmode="numeric" title="Philippine mobile number: 11 digits starting with 09" required></div>
                        <div class="ap-field"><label>Email Address *</label><input type="email" name="email" required></div>
                        <div class="ap-field"><label>Date of Birth *</label><input type="date" name="birthdate" required></div>
                    </div>
                </div>
                <div class="ap-step-nav">
                    <span></span>
                    <button type="button" class="ap-btn-primary ap-btn-next">Next</button>
                </div>
            </div>

            <div class="ap-step" data-step="2">
                <div class="ap-section">
                    <h3>Address</h3>
                    <div class="ap-grid">
                        <div class="ap-field">
                            <label>City / Municipality *</label>
                            <select name="municipality" required>
                                <option value="">Select municipality</option>
                                <?php foreach (['Southern Leyte', 'Leyte'] as $province): ?>
                                    <optgroup label="<?= htmlspecialchars($province) ?>">
                                        <?php foreach (MUNICIPALITIES as $townName => $townInfo): if ($townInfo[2] !== $province) continue; ?>
                                            <option value="<?= htmlspecialchars($townName) ?>"><?= htmlspecialchars($townName) ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endforeach; ?>
                                <option value="Other">Other (not listed)</option>
                            </select>
                        </div>
                        <div class="ap-field"><label>Barangay *</label><input type="text" name="barangay" required></div>
                    </div>
                </div>
                <div class="ap-step-nav">
                    <button type="button" class="ap-btn-secondary ap-btn-back">Back</button>
                    <button type="button" class="ap-btn-primary ap-btn-next">Next</button>
                </div>
            </div>

            <div class="ap-step" data-step="3">
                <div class="ap-section">
                    <h3>Parents' Information</h3>
                    <div class="ap-grid">
                        <div class="ap-field"><label>Mother's Maiden Name *</label><input type="text" name="motherName" required></div>
                        <div class="ap-field"><label>Mother's Contact Number *</label><input type="text" name="motherContact" required></div>
                        <div class="ap-field"><label>Father's Name *</label><input type="text" name="fatherName" required></div>
                        <div class="ap-field"><label>Father's Contact Number *</label><input type="text" name="fatherContact" required></div>
                    </div>
                </div>
                <div class="ap-step-nav">
                    <button type="button" class="ap-btn-secondary ap-btn-back">Back</button>
                    <button type="button" class="ap-btn-primary ap-btn-next">Next</button>
                </div>
            </div>

            <div class="ap-step" data-step="4">
                <div class="ap-section">
                    <h3>Academic Information</h3>
                    <div class="ap-grid">
                        <div class="ap-field">
                            <label>Department *</label>
                            <select name="department" required>
                                <option value="">Select department</option>
                                <?php foreach (array_keys(KNOWN_PROGRAMS) as $prog): ?>
                                    <option value="<?= htmlspecialchars($prog) ?>"><?= htmlspecialchars($prog) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ap-field">
                            <label>Year Level *</label>
                            <select name="yearLevel" required>
                                <option value="">Select</option>
                                <?php foreach (['1st Year', '2nd Year', '3rd Year', '4th Year'] as $yl): ?>
                                    <option value="<?= $yl ?>"><?= $yl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="ap-step-nav">
                    <button type="button" class="ap-btn-secondary ap-btn-back">Back</button>
                    <button type="button" class="ap-btn-primary ap-btn-next">Next</button>
                </div>
            </div>

            <div class="ap-step" data-step="5">
                <div class="ap-section">
                    <h3>Scholarship Program</h3>
                    <p class="ap-note">Slots are first come, first served — a program marked "Limit Reached" is no longer accepting applicants.</p>
                    <div class="ap-programs" id="apPrograms">
                        <?php if (empty($programs)): ?>
                            <p class="ap-empty">No scholarship programs are currently open.</p>
                        <?php endif; ?>
                        <?php foreach ($programs as $p): $full = !$p['unlimited_slots'] && (int)$p['slots'] > 0 && (int)$p['slots_available'] <= 0; ?>
                            <label class="ap-program-option <?= $full ? 'ap-program-full' : '' ?>">
                                <input type="radio" name="scholarship" value="<?= htmlspecialchars($p['name']) ?>" required <?= $full ? 'disabled' : '' ?>>
                                <div class="ap-program-info">
                                    <span class="ap-program-name"><?= htmlspecialchars($p['name']) ?></span>
                                    <span class="ap-program-meta"><?= htmlspecialchars($p['type']) ?></span>
                                </div>
                                <?php if ($full): ?>
                                    <span class="ap-program-badge ap-badge-full">Limit Reached</span>
                                <?php elseif ($p['unlimited_slots']): ?>
                                    <span class="ap-program-badge ap-badge-open">Open</span>
                                <?php else: ?>
                                    <span class="ap-program-badge ap-badge-open"><?= (int)$p['slots_available'] ?> slot<?= (int)$p['slots_available'] === 1 ? '' : 's' ?> left</span>
                                <?php endif; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="ap-step-nav">
                    <button type="button" class="ap-btn-secondary ap-btn-back">Back</button>
                    <button type="button" class="ap-btn-primary ap-btn-next">Next</button>
                </div>
            </div>

            <div class="ap-step" data-step="6">
                <div class="ap-section">
                    <h3>Required Documents</h3>
                    <p class="ap-note">PNG images only. All three are required to submit your application.</p>
                    <div class="ap-grid">
                        <div class="ap-field"><label>Transcript of Records *</label><input type="file" name="transcript" accept="image/png" required></div>
                        <div class="ap-field"><label>Certificate of Enrollment *</label><input type="file" name="coe" accept="image/png" required></div>
                        <div class="ap-field"><label>Good Moral Certificate *</label><input type="file" name="goodMoral" accept="image/png" required></div>
                    </div>
                </div>
                <div class="ap-step-nav">
                    <button type="button" class="ap-btn-secondary ap-btn-back">Back</button>
                    <button type="submit" class="ap-btn-primary ap-btn-submit" id="apSubmitBtn">Submit Application</button>
                </div>
            </div>

        </form>

    </div>
</div>

<?php endif; ?>

<script src="<?= SITE_BASE ?>/assets/js/apply.js?v=<?= time() ?>"></script>
</body>
</html>
