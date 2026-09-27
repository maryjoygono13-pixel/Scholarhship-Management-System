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
require_once __DIR__ . '/../includes/locations.php';
require_once __DIR__ . '/../includes/merit_helper.php';

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
    // Every document is mandatory — never accepted as a "complete later" application.
    foreach (['transcript' => 'Transcript of Records', 'coe' => 'Certificate of Enrollment', 'goodMoral' => 'Good Moral Certificate'] as $field => $label) {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            sendError("Please upload your $label.");
        }
    }

    // The chosen program must be a real, currently active one — and not full. Re-checked here
    // (not just in the form) since another applicant could take the last slot in the meantime.
    $prog = $pdo->prepare("SELECT name, type, subtype, slots, slots_available, unlimited_slots FROM scholarships WHERE name = ? AND LOWER(status) = 'active' LIMIT 1");
    $prog->execute([$scholarshipName]);
    $program = $prog->fetch(PDO::FETCH_ASSOC);
    if (!$program) {
        sendError('That scholarship program is not available. Please choose another.', 404);
    }
    // Counted live from actual applicants, not a stored decrement counter — so this can never
    // drift into showing "full" for a program nobody has actually applied to.
    $isFull = !$program['unlimited_slots'] && (int)$program['slots'] > 0 && scholarshipSlotsAvailable($pdo, $program) <= 0;
    if ($isFull) {
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

    // Required documents: PNG only.
    $uploadDir = __DIR__ . '/../uploads/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
        sendError('Unable to create upload directory.', 500);
    }
    $saveUpload = function (string $field) use ($uploadDir): string {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
            return '';
        }
        if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return '';
        }
        $ext = strtolower(pathinfo(basename($_FILES[$field]['name']), PATHINFO_EXTENSION));
        if ($ext !== 'png') {
            throw new Exception("$field must be a PNG image.");
        }
        // A real PNG, not just a renamed file with a .png extension.
        $info = @getimagesize($_FILES[$field]['tmp_name']);
        if ($info === false || $info[2] !== IMAGETYPE_PNG) {
            throw new Exception("$field does not look like a valid PNG image.");
        }
        $newName = uniqid($field . '_', true) . '.png';
        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $uploadDir . $newName)) {
            throw new Exception("Failed to upload $field.");
        }
        return $newName;
    };
    $transcriptFile = $saveUpload('transcript');
    $coeFile = $saveUpload('coe');
    $goodMoralFile = $saveUpload('goodMoral');
    $docsComplete = ($transcriptFile && $coeFile && $goodMoralFile) ? 1 : 0;

    $insert = $pdo->prepare("
        INSERT INTO applicants (
            student_id, first_name, middle_name, last_name, suffix, gender, email, phone, birthdate, age,
            address, municipality, barangay, latitude, longitude, mother_name, mother_contact,
            father_name, father_contact, school, school_year, program, year_level,
            semester, gpa, scholarship_type, status, gwa, gwa_req, failing_grades, units,
            enrolled, docs_complete, transcript_file, coe_file, good_moral_file
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, 0.0, ?, 'pending', 0.0, ?, 0, 21,
            1, ?, ?, ?, ?
        )
    ");
    $insert->execute([
        $studentId, $firstName, $middleName, $lastName, $suffix, $gender, $email, $phone, $birthdate, $age,
        $address, $municipality, $barangay, $latitude, $longitude, $motherName, $motherContact,
        $fatherName, $fatherContact, 'College of Maasin', $schoolYear, $department, $yearLevel,
        $semester,
        $scholarshipType, $gwaReq,
        $docsComplete, $transcriptFile, $coeFile, $goodMoralFile,
    ]);
    $newId = (int)$pdo->lastInsertId();

    // No manual slot decrement needed — this new `applicants` row is itself counted the moment
    // anyone reads scholarshipSlotsAvailable(), so the next applicant sees an up-to-date count
    // with no separate counter that could ever drift from it.

    recalculateApplicantGwa($pdo, $studentId);
    syncMeritScholars($pdo);

    $fullName = trim($firstName . ' ' . $middleName . ' ' . $lastName . ' ' . $suffix);
    logActivity($pdo, 'Applicant Added', 'Applicants', $fullName . ' (Student ID: ' . $studentId . ') applied for ' . $scholarshipName . ' through the public Apply page.', $newId);

    sendJson(['success' => true, 'id' => $newId, 'message' => 'Your application was successfully uploaded and is waiting for evaluation.']);
} catch (Throwable $e) {
    sendError($e->getMessage(), 500);
}
