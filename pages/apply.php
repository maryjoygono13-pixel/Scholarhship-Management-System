<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_helper.php';
require_once __DIR__ . '/../includes/locations.php';
require_once __DIR__ . '/../includes/programs_helper.php';
require_once __DIR__ . '/../includes/scholarship_type_helper.php';
require_once __DIR__ . '/../includes/scholarship_criteria_helper.php';
require_once __DIR__ . '/../includes/special_qualification_helper.php';
require_once __DIR__ . '/../includes/apply_guard_helper.php';

$pdo = getDB();
$studentId = trim($_GET['student_id'] ?? '');
$step = $studentId !== '' ? 2 : 1;

// Every active scholarship program, with its live availability — first come, first served.
// Availability is counted fresh from actual applicants (scholarshipSlotsAvailable), not a
// stored decrement counter, so a program never shows "Limit Reached" from stale drift when
// no one has actually applied for it.
$programs = $pdo->query("
    SELECT id, name, code, type, subtype, slots, slots_available, unlimited_slots
    FROM scholarships
    WHERE LOWER(status) = 'active'
    ORDER BY type, name
")->fetchAll(PDO::FETCH_ASSOC);
foreach ($programs as &$p) {
    $p['slots_available'] = scholarshipSlotsAvailable($pdo, $p);
    $p['is_full'] = !$p['unlimited_slots'] && (int)$p['slots'] > 0 && (int)$p['slots_available'] <= 0;
}
unset($p);

// Step 1 lists one option per scholarship TYPE: a type with a single program is that program;
// a type with several (e.g. NEED-BASED) is one option with a dropdown to pick the program.
$programGroups = [];
foreach ($programs as $p) {
    $key = strtolower(trim($p['type']));
    $programGroups[$key] ??= ['type' => $p['type'], 'programs' => []];
    $programGroups[$key]['programs'][] = $p;
}
$programGroups = array_values($programGroups);

// Each program's own required documents (registrar-configured on the Scholarships page),
// keyed by program name — Step 6 swaps its file fields to match whichever program the
// applicant picked in Step 1, instead of the same fixed 3 fields for every program.
$programDocuments = [];
foreach ($programs as $p) {
    $docs = getApplicationDocuments($pdo, (int)$p['id']); // includes the universal COE
    $programDocuments[$p['name']] = array_map(fn($d) => [
        'type' => $d['document_type'],
        'label' => DOCUMENT_TYPES[$d['document_type']] ?? $d['document_type'],
        'required' => (bool)$d['required'],
        'hint' => trim((string)($d['description'] ?? '')),
    ], $docs);
}

// Programs with a Poverty Threshold ask for the applicant's family income. Only a yes/no per
// program is sent to the page — the threshold amount itself stays server-side.
$programNeedsIncome = [];
foreach ($programs as $p) {
    $programNeedsIncome[$p['name']] = scholarshipNeedsFamilyIncome($pdo, (int)$p['id']);
}

// Talent / Community Service / Other Discounts programs ask one extra question (label +
// options), keyed by program name. Programs of any other type are simply left out.
// Programs applied for on an outside site (e.g. CHED's CMSP / COSCHO -> CHED StuFAPs), keyed
// by program name. Picking one and clicking Next sends the applicant there (apply.js).
$programExternalUrl = [];
foreach ($programs as $p) {
    $url = externalApplicationUrl($p);
    if ($url !== null) $programExternalUrl[$p['name']] = $url;
}

$programQualification = [];
foreach ($programs as $p) {
    $cfg = specialQualificationConfig((string)$p['type']);
    if ($cfg !== null) $programQualification[$p['name']] = $cfg;
}
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
            <h2>Application Submitted Successfully! 🎉</h2>
            <p>Thank you for submitting your scholarship application. Your application has been successfully received and is now under review.</p>
            <p>Our Scholarship Office will review your application and submitted documents. We will contact you through the contact information you provided once there is an update regarding your application.</p>
            <p class="ap-success-note">Please make sure that your contact information remains active and accessible.</p>
            <a href="<?= SITE_BASE ?>/apply" class="ap-btn-primary ap-success-btn">Back to Home</a>
        </div>

        <div class="ap-progress-wrap" id="apProgressWrap">
            <p class="ap-step-counter" id="apStepCounter">Step 1 of 6</p>
            <div class="ap-progress-bar"><div class="ap-progress-fill" id="apProgressFill"></div></div>
        </div>

        <form id="apApplyForm" enctype="multipart/form-data">
            <input type="hidden" name="studentId" value="<?= htmlspecialchars($studentId) ?>">
            <!-- Safeguards (includes/apply_guard_helper.php): a signed token tying this form to the
                 Student ID and the time it was opened, and a hidden field only bots fill in. -->
            <input type="hidden" name="formToken" value="<?= htmlspecialchars(applyFormToken($studentId)) ?>">
            <div class="ap-hp" aria-hidden="true">
                <label for="apWebsite">Website</label>
                <input type="text" id="apWebsite" name="<?= APPLY_HONEYPOT_FIELD ?>" tabindex="-1" autocomplete="off">
            </div>

            <div class="ap-step active" data-step="1">
                <div class="ap-section">
                    <h3>Scholarship Program</h3>
                    <p class="ap-note">Slots are first come, first served — a program marked "Limit Reached" is no longer accepting applicants.</p>
                    <div class="ap-programs" id="apPrograms">
                        <?php if (empty($programs)): ?>
                            <p class="ap-empty">No scholarship programs are currently open.</p>
                        <?php endif; ?>
                        <?php foreach ($programGroups as $gi => $group): ?>
                            <?php if (count($group['programs']) === 1): $p = $group['programs'][0]; $full = $p['is_full']; ?>
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
                            <?php else: $allFull = !array_filter($group['programs'], fn($p) => !$p['is_full']); ?>
                                <?php /* Several programs under one type: one option, then pick the program.
                                         The radio's value becomes the chosen program's name (apply.js), so
                                         the submitted `scholarship` is still a specific program. */ ?>
                                <label class="ap-program-option <?= $allFull ? 'ap-program-full' : '' ?>">
                                    <input type="radio" name="scholarship" value="" data-group="<?= $gi ?>" required <?= $allFull ? 'disabled' : '' ?>>
                                    <div class="ap-program-info">
                                        <span class="ap-program-name"><?= htmlspecialchars($group['type']) ?></span>
                                        <span class="ap-program-meta"><?= count($group['programs']) ?> programs — choose yours after selecting</span>
                                    </div>
                                    <span class="ap-program-badge <?= $allFull ? 'ap-badge-full' : 'ap-badge-open' ?>"><?= $allFull ? 'Limit Reached' : 'Open' ?></span>
                                </label>
                                <div class="ap-field ap-group-field" data-group-field="<?= $gi ?>" hidden>
                                    <label for="apGroupSelect<?= $gi ?>"><?= htmlspecialchars($group['type']) ?> Program *</label>
                                    <select id="apGroupSelect<?= $gi ?>" class="ap-group-select" data-group="<?= $gi ?>" disabled>
                                        <option value="">Select</option>
                                        <?php foreach ($group['programs'] as $p): ?>
                                            <option value="<?= htmlspecialchars($p['name']) ?>" <?= $p['is_full'] ? 'disabled' : '' ?>>
                                                <?= htmlspecialchars($p['name']) ?><?= $p['is_full'] ? ' — Limit Reached' : ($p['unlimited_slots'] ? '' : ' — ' . (int)$p['slots_available'] . ' slot' . ((int)$p['slots_available'] === 1 ? '' : 's') . ' left') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <div class="ap-field" id="apQualField" hidden>
                        <label for="apQualSelect" id="apQualLabel">Special Field / Area of Involvement *</label>
                        <select id="apQualSelect" name="specialQualification" disabled></select>
                    </div>
                    <div class="ap-field" id="apQualOtherField" hidden>
                        <label for="apQualOther">Please specify *</label>
                        <input type="text" id="apQualOther" name="specialQualificationOther" maxlength="255" title="Please describe it — this cannot be blank." disabled>
                    </div>
                    <div class="ap-field ap-income-field" id="apIncomeField" hidden>
                        <label for="apFamilyIncome">Total Monthly Family Income (₱) *</label>
                        <input type="number" id="apFamilyIncome" name="familyIncome" min="0" step="0.01" inputmode="decimal" placeholder="e.g. 12000" disabled>
                    </div>
                </div>
                <div class="ap-step-nav">
                    <span></span>
                    <button type="button" class="ap-btn-primary ap-btn-next">Next</button>
                </div>
            </div>

            <div class="ap-step" data-step="2">
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
                    <button type="button" class="ap-btn-secondary ap-btn-back">Back</button>
                    <button type="button" class="ap-btn-primary ap-btn-next">Next</button>
                </div>
            </div>

            <div class="ap-step" data-step="3">
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

            <div class="ap-step" data-step="4">
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

            <div class="ap-step" data-step="5">
                <div class="ap-section">
                    <h3>Academic Information</h3>
                    <div class="ap-grid">
                        <div class="ap-field">
                            <label>Department *</label>
                            <select name="department" required>
                                <option value="">Select department</option>
                                <?php foreach (PROGRAM_DISPLAY_NAMES as $prog => $progName): ?>
                                    <option value="<?= htmlspecialchars($prog) ?>"><?= htmlspecialchars($progName) ?></option>
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

            <div class="ap-step" data-step="6">
                <div class="ap-section">
                    <h3>Required Documents</h3>
                    <p class="ap-note">PNG images only. Documents required depend on the scholarship program you selected.</p>
                    <div class="ap-grid" id="apDocumentsGrid">
                        <!-- Filled in by apply.js based on the Step 1 selection -->
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

<script>
window.APPLY_MAX_FILE_BYTES = <?= APPLY_MAX_FILE_BYTES ?>;
window.APPLY_MAX_TOTAL_BYTES = <?= APPLY_MAX_TOTAL_BYTES ?>;
window.SCHOLARSHIP_DOCUMENTS = <?= json_encode($programDocuments) ?>;
window.SCHOLARSHIP_NEEDS_INCOME = <?= json_encode($programNeedsIncome) ?>;
window.SCHOLARSHIP_QUALIFICATION = <?= json_encode($programQualification, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
window.SCHOLARSHIP_EXTERNAL_URL = <?= json_encode($programExternalUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?>;
</script>
<?php endif; ?>

<script src="<?= SITE_BASE ?>/assets/js/apply.js?v=<?= time() ?>"></script>
</body>
</html>
