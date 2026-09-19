<?php
require_once __DIR__ . '/init.php';

try {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        sendError('Invalid applicant ID.');
    }

    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM applicants WHERE id = ?");
    $stmt->execute([$id]);
    $r = $stmt->fetch();

    if (!$r) {
        sendError('Applicant not found.', 404);
    }

    $data = [
    'id' => (int)$r['id'],

    /* --------------------------------------------------
       STUDENT INFORMATION
    -------------------------------------------------- */

    'studentId' => $r['student_id'],
    'student_id' => $r['student_id'],

    'firstName' => $r['first_name'],
    'first_name' => $r['first_name'],

    'middleName' => $r['middle_name'] ?? '',
    'middle_name' => $r['middle_name'] ?? '',

    'lastName' => $r['last_name'],
    'last_name' => $r['last_name'],

    'name' => trim(
        ($r['first_name'] ?? '') . ' ' .
        ($r['middle_name'] ?? '') . ' ' .
        ($r['last_name'] ?? '')
    ),

    /* --------------------------------------------------
       PERSONAL INFORMATION
    -------------------------------------------------- */

    'gender' => $r['gender'] ?? '',

    'email' => $r['email'] ?? '',

    'phone' => $r['phone'] ?? '',

    'birthdate' => $r['birthdate'] ?? '',

    'age' => isset($r['age']) && $r['age'] !== ''
        ? (int)$r['age']
        : null,

    'address' => $r['address'] ?? '',

    /* --------------------------------------------------
       SCHOOL / ACADEMIC INFORMATION
    -------------------------------------------------- */

    'school' => $r['school'] ?? '',

    'schoolYear' => $r['school_year'] ?? '',
    'school_year' => $r['school_year'] ?? '',

    'program' => $r['program'] ?? '',

    'major' => $r['major'] ?? '',

    'yearLevel' => $r['year_level'] ?? '',
    'year_level' => $r['year_level'] ?? '',

    'semester' => $r['semester'] ?? '1st Semester',

    'gpa' => isset($r['gpa'])
        ? (float)$r['gpa']
        : 0,

    /* --------------------------------------------------
       SCHOLARSHIP
    -------------------------------------------------- */

    'scholarshipType' => $r['scholarship_type'] ?? '',
    'scholarship_type' => $r['scholarship_type'] ?? '',
    'type' => $r['scholarship_type'] ?? '',

    /* --------------------------------------------------
       STATUS / EVALUATION
    -------------------------------------------------- */

    'status' => $r['status'] ?? '',

    'gwa' => isset($r['gwa'])
        ? (float)$r['gwa']
        : 0,

    'gwaReq' => isset($r['gwa_req'])
        ? (float)$r['gwa_req']
        : 0,

    'failingGrades' => isset($r['failing_grades'])
        ? (int)$r['failing_grades']
        : 0,

    'units' => isset($r['units'])
        ? (int)$r['units']
        : 0,

    'enrolled' => !empty($r['enrolled']),

    'docsComplete' => !empty($r['docs_complete']),

    /* --------------------------------------------------
       OTHER
    -------------------------------------------------- */

    'remarks' => $r['remarks'] ?? '',

    'essay' => $r['essay'] ?? '',

    'transcriptFile' => $r['transcript_file'] ?? '',

    'recommendationFile' => $r['recommendation_file'] ?? '',

    'validIdFile' => $r['valid_id_file'] ?? '',

    'createdAt' => $r['created_at'] ?? '',
    'created_at' => $r['created_at'] ?? ''
];

    sendJson(['success' => true, 'data' => $data]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
