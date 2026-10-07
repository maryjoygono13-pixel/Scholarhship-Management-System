<?php
/*
 * A student's own scholarship application, submitted from the public Apply page
 * (pages/apply.php). Not a login — a student only ever identifies themselves by typing
 * their Student ID, so this endpoint has no session to check. It only ever creates a new
 * `applicants` row; it never edits an existing one, so there's nothing here an applicant
 * could use to change someone else's record.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db_helper.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_once __DIR__ . '/../includes/name_helper.php';
require_once __DIR__ . '/../includes/gwa_helper.php';
require_once __DIR__ . '/../includes/term_helper.php';
require_once __DIR__ . '/../includes/grades_helper.php';
require_once __DIR__ . '/../includes/renewal_helper.php';
require_once __DIR__ . '/../includes/programs_helper.php';
require_once __DIR__ . '/../includes/scholarship_type_helper.php';
require_once __DIR__ . '/../includes/scholarship_criteria_helper.php';
require_once __DIR__ . '/../includes/locations.php';
require_once __DIR__ . '/../includes/merit_helper.php';
require_once __DIR__ . '/../includes/special_qualification_helper.php';
require_once __DIR__ . '/../includes/apply_guard_helper.php';

header('Content-Type: application/json; charset=utf-8');

function sendJson($data, int $statusCode = 200): void {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}
function sendError(string $message, int $statusCode = 400): void {
    sendJson(['success' => false, 'message' => $message], $statusCode);
}

try {
    $pdo = getDB();

    $studentId = trim($_POST['studentId'] ?? '');
    $firstName = trim($_POST['firstName'] ?? '');
    $middleName = trim($_POST['middleName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $birthdate = trim($_POST['birthdate'] ?? '');
    $municipality = trim($_POST['municipality'] ?? '');
    $barangay = trim($_POST['barangay'] ?? '');
    $motherName = trim($_POST['motherName'] ?? '');
    $motherContact = trim($_POST['motherContact'] ?? '');
    $fatherName = trim($_POST['fatherName'] ?? '');
    $fatherContact = trim($_POST['fatherContact'] ?? '');
    $department = trim($_POST['department'] ?? ''); // stored in applicants.program
    $yearLevel = trim($_POST['yearLevel'] ?? '');
    $scholarshipName = trim($_POST['scholarship'] ?? ''); // a scholarships.name value

    if ($studentId === '') {
        sendError('Your Student ID is missing. Please start over from the Apply page.');
    }

    // ---- Safeguards against spam and floods (includes/apply_guard_helper.php). Cheap checks
    // first, before any file handling or heavy database work. ----
    $clientIp = applyClientIp();
    if (trim((string)($_POST[APPLY_HONEYPOT_FIELD] ?? '')) !== '') {
        sendError('Your application could not be submitted.', 400); // hidden field filled in: a bot
    }
    $wait = applyThrottleWait($pdo, 'apply_attempt', $clientIp, APPLY_ATTEMPTS_PER_IP, APPLY_ATTEMPTS_WINDOW);
    if ($wait > 0) {
        sendError('Too many attempts from this connection. Please wait ' . applyWaitText($wait) . ' and try again.', 429);
    }
    applyThrottleRecord($pdo, 'apply_attempt', $clientIp);
    $tokenError = applyCheckFormToken(trim((string)($_POST['formToken'] ?? '')), $studentId);
    if ($tokenError !== null) {
        sendError($tokenError, 403);
    }
    $wait = applyThrottleWait($pdo, 'apply_submitted_ip', $clientIp, APPLY_SUBMISSIONS_PER_IP, APPLY_SUBMISSIONS_IP_WINDOW);
    if ($wait > 0) {
        sendError('Too many applications have been submitted from this connection. Please try again in ' . applyWaitText($wait) . '.', 429);
    }
    $wait = applyThrottleWait($pdo, 'apply_submitted_student', $studentId, APPLY_SUBMISSIONS_PER_STUDENT, APPLY_SUBMISSIONS_STUDENT_WINDOW);
    if ($wait > 0) {
        sendError('This Student ID has reached the limit of ' . APPLY_SUBMISSIONS_PER_STUDENT . ' applications per day. Please try again in ' . applyWaitText($wait) . '.', 429);
    }
    $sizeError = applyCheckUploadSizes();
    if ($sizeError !== null) {
        sendError($sizeError, 413);
    }
    if (
        $firstName === '' || $lastName === '' || $gender === '' || $phone === '' || $email === '' ||
        $birthdate === '' || $municipality === '' || $barangay === '' ||
        $motherName === '' || $motherContact === '' || $fatherName === '' || $fatherContact === '' ||
        $department === '' || $yearLevel === ''
    ) {
        sendError('Please fill out all required fields.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendError('Please enter a valid email address.');
    }
    if (!preg_match('/^09\d{9}$/', $phone)) {
        sendError('Please enter a valid Philippine mobile number (11 digits, e.g. 09171234567).');
    }
    if ($scholarshipName === '') {
        sendError('Please select a scholarship program.');
    }

    // The chosen program must be a real, currently active one — and not full. Re-checked here
    // (not just in the form) since another applicant could take the last slot in the meantime.
    $prog = $pdo->prepare("SELECT id, name, code, type, subtype, slots, slots_available, unlimited_slots FROM scholarships WHERE name = ? AND LOWER(status) = 'active' LIMIT 1");
    $prog->execute([$scholarshipName]);
    $program = $prog->fetch(PDO::FETCH_ASSOC);
    if (!$program) {
        sendError('That scholarship program is not available. Please choose another.', 404);
    }
    // Applied for on an outside site (e.g. CHED StuFAPs), never through this form.
    $externalUrl = externalApplicationUrl($program);
    if ($externalUrl !== null) {
        sendError('Applications for ' . $program['name'] . ' are submitted through ' . $externalUrl . ', not this form.', 422);
    }

    // A program with a Poverty Threshold needs the applicant's monthly family income. The
    // threshold itself is set by the registrar and never sent from or shown on the form.
    $familyIncome = null;
    if (scholarshipNeedsFamilyIncome($pdo, (int)$program['id'])) {
        $incomeRaw = str_replace([',', '₱', ' '], '', trim((string)($_POST['familyIncome'] ?? '')));
        if ($incomeRaw === '' || !is_numeric($incomeRaw) || (float)$incomeRaw < 0) {
            sendError('Please enter your total monthly family income.');
        }
        $familyIncome = round((float)$incomeRaw, 2);
    }

    // Talent / Community Service / Other Discounts programs ask what the applicant qualifies
    // through. Only the question for THIS program's type is read — anything sent for another
    // type is ignored. Saved as a declaration only; the registrar verifies it from documents.
    $specialQualification = '';
    $specialQualificationOther = '';
    $qualConfig = specialQualificationConfig((string)$program['type']);
    if ($qualConfig !== null) {
        $specialQualification = trim((string)($_POST['specialQualification'] ?? ''));
        if ($specialQualification === '' || !in_array($specialQualification, $qualConfig['options'], true)) {
            sendError('Please select your ' . $qualConfig['label'] . '.');
        }
        if ($specialQualification === SPECIAL_QUALIFICATION_OTHER) {
            $specialQualificationOther = trim(preg_replace('/\s+/', ' ', (string)($_POST['specialQualificationOther'] ?? '')));
            if ($specialQualificationOther === '') {
                sendError('Please specify your ' . $qualConfig['label'] . '.');
            }
            if (mb_strlen($specialQualificationOther) > 255) {
                sendError('Your ' . $qualConfig['label'] . ' description is too long (255 characters max).');
            }
        }
    }

    // The documents this specific program requires (registrar-configured on the Scholarships
    // page) — never the same fixed 3 fields for every program. Every required one is mandatory;
    // an application is never accepted as "complete later".
    $requiredDocuments = array_values(array_filter(getApplicationDocuments($pdo, (int)$program['id']), fn($d) => (bool)$d['required']));
    foreach ($requiredDocuments as $doc) {
        $field = $doc['document_type'];
        $label = DOCUMENT_TYPES[$field] ?? $field;
        if (!isset($_FILES['documents']['error'][$field]) || $_FILES['documents']['error'][$field] === UPLOAD_ERR_NO_FILE) {
            sendError("Please upload your $label.");
        }
    }
    // Counted live from actual applicants, not a stored decrement counter — so this can never
    // drift into showing "full" for a program nobody has actually applied to.
    // Held until this request ends, so a simultaneous applicant can't take the same last slot.
    scholarshipSlotLock($pdo);
    if (scholarshipIsFull($pdo, $program)) {
        sendError('That scholarship program has reached its slot limit and is no longer accepting applicants. Please choose another program.', 409);
    }
    $scholarshipType = normalizeScholarshipType($pdo, $program['subtype'] !== '' ? $program['subtype'] : $program['name']);

    // Can't (re-)apply for a scholarship this Student ID was terminated from.
    if (findScholarshipTermination($pdo, $studentId, $scholarshipType)) {
        sendError(terminationBlockMessage($scholarshipType), 422);
    }

    // Already has a pending/under-review application for this exact program.
    $dupStmt = $pdo->prepare("SELECT id FROM applicants WHERE student_id = ? AND scholarship_type = ? AND LOWER(status) IN ('pending', 'review', 'interview')");
    $dupStmt->execute([$studentId, $scholarshipType]);
    if ($dupStmt->fetch()) {
        sendError('You already have an application for this scholarship that is still being processed.', 409);
    }

    $age = null;
    if ($birthdate !== '') {
        try {
            $age = (new DateTime())->diff(new DateTime($birthdate))->y;
        } catch (Throwable $e) {
            $age = null;
        }
    }

    $town = resolveMunicipality($municipality);
    if ($town !== null) {
        $municipality = $town;
        $address = composeAddress($barangay, $town);
    } else {
        $municipality = $municipality === 'Other' ? 'Other' : $municipality;
        $address = $barangay;
    }
    [$latitude, $longitude] = locationCoordinates($municipality, $barangay, $studentId) ?? [null, null];

    $schoolYear = getActiveSchoolYear($pdo);
    $semester = getActiveSemester($pdo);
    $gwaReq = resolveGwaRequirement($pdo, $scholarshipType, 1.75);

    // Every document this program has configured (required and optional) — PNG only. Field
    // names are documents[<type>], matching pages/apply.php's dynamically-rendered Step 6.
    $allDocuments = getApplicationDocuments($pdo, (int)$program['id']);

    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
        sendError('Unable to create upload directory.', 500);
    }
    $saveUpload = function (string $type) use ($uploadDir): string {
        if (!isset($_FILES['documents']['error'][$type]) || $_FILES['documents']['error'][$type] === UPLOAD_ERR_NO_FILE) {
            return '';
        }
        if ($_FILES['documents']['error'][$type] !== UPLOAD_ERR_OK) {
            return '';
        }
        $label = DOCUMENT_TYPES[$type] ?? $type;
        $tmpName = $_FILES['documents']['tmp_name'][$type];
        $ext = strtolower(pathinfo(basename($_FILES['documents']['name'][$type]), PATHINFO_EXTENSION));
        if ($ext !== 'png') {
            throw new Exception("\"$label\" must be a PNG image.");
        }
        // A real PNG, not just a renamed file with a .png extension.
        $info = @getimagesize($tmpName);
        if ($info === false || $info[2] !== IMAGETYPE_PNG) {
            throw new Exception("\"$label\" does not look like a valid PNG image.");
        }
        $newName = uniqid($type . '_', true) . '.png';
        if (!move_uploaded_file($tmpName, $uploadDir . $newName)) {
            throw new Exception("Failed to upload \"$label\".");
        }
        return $newName;
    };

    $uploadedDocs = []; // document_type => saved filename, for everything actually uploaded
    foreach ($allDocuments as $doc) {
        $filename = $saveUpload($doc['document_type']);
        if ($filename !== '') $uploadedDocs[$doc['document_type']] = $filename;
    }
    // The 3 legacy columns are still populated whenever the matching document type was
    // uploaded, so every existing reader of applicants.transcript_file/coe_file/good_moral_file
    // keeps working unchanged.
    $transcriptFile = $uploadedDocs['transcript'] ?? '';
    $coeFile = $uploadedDocs['coe'] ?? '';
    $goodMoralFile = $uploadedDocs['good_moral'] ?? '';
    // Complete once every document this program actually requires has been uploaded — not a
    // fixed rule about those 3 specific types.
    $docsComplete = 1;
    foreach ($requiredDocuments as $doc) {
        if (empty($uploadedDocs[$doc['document_type']])) { $docsComplete = 0; break; }
    }

    $insert = $pdo->prepare("
        INSERT INTO applicants (
            student_id, first_name, middle_name, last_name, suffix, gender, email, phone, birthdate, age,
            address, municipality, barangay, latitude, longitude, mother_name, mother_contact,
            father_name, father_contact, school, school_year, program, year_level,
            semester, gpa, scholarship_type, status, gwa, gwa_req, failing_grades, units,
            enrolled, docs_complete, transcript_file, coe_file, good_moral_file, family_income,
            special_qualification, special_qualification_other
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, 0.0, ?, 'pending', 0.0, ?, 0, 21,
            1, ?, ?, ?, ?, ?,
            ?, ?
        )
    ");
    $insert->execute([
        $studentId, $firstName, $middleName, $lastName, $suffix, $gender, $email, $phone, $birthdate, $age,
        $address, $municipality, $barangay, $latitude, $longitude, $motherName, $motherContact,
        $fatherName, $fatherContact, 'College of Maasin', $schoolYear, $department, $yearLevel,
        $semester,
        $scholarshipType, $gwaReq,
        $docsComplete, $transcriptFile, $coeFile, $goodMoralFile, $familyIncome,
        $specialQualification, $specialQualificationOther,
    ]);
    $newId = (int)$pdo->lastInsertId();
    // Count this accepted application toward the per-connection and per-Student-ID limits.
    applyThrottleRecord($pdo, 'apply_submitted_ip', $clientIp);
    applyThrottleRecord($pdo, 'apply_submitted_student', $studentId);

    // Every uploaded file (legacy-named or a program-specific one) also gets a row in the
    // generalized documents table — the canonical source Evaluation's Documents tab reads from.
    if (!empty($uploadedDocs)) {
        $docIdByType = [];
        foreach ($allDocuments as $doc) $docIdByType[$doc['document_type']] = (int)$doc['id'] ?: null; // universal docs (id 0) have no program row
        $insertDoc = $pdo->prepare("INSERT INTO applicant_documents (applicant_id, scholarship_document_id, document_type, file_path) VALUES (?, ?, ?, ?)");
        foreach ($uploadedDocs as $docType => $filename) {
            $insertDoc->execute([$newId, $docIdByType[$docType] ?? null, $docType, $filename]);
        }
    }

    // No manual slot decrement needed — this new `applicants` row is itself counted the moment
    // anyone reads scholarshipSlotsAvailable(), so the next applicant sees an up-to-date count
    // with no separate counter that could ever drift from it.

    recalculateApplicantGwa($pdo, $studentId);

    $fullName = trim($firstName . ' ' . $middleName . ' ' . $lastName . ' ' . $suffix);
    logActivity($pdo, 'Applicant Added', 'Applicants', $fullName . ' (Student ID: ' . $studentId . ') applied for ' . $scholarshipName . ' through the public Apply page.', $newId);

    sendJson(['success' => true, 'id' => $newId, 'message' => 'Your application was successfully uploaded and is waiting for evaluation.']);
} catch (Throwable $e) {
    sendError($e->getMessage(), 500);
}
