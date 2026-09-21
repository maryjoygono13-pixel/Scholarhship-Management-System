<?php
require_once __DIR__ . '/init.php';

try {
    $pdo = getDB();

    $statusParam = trim($_GET['status'] ?? '');
    $typeParam = trim($_GET['type'] ?? '');

    /*
    ============================================================
    GET APPLICANTS
    ============================================================
    */

    $query = "
        SELECT *
        FROM applicants
        WHERE LOWER(status) IN ('pending', 'review', 'interview')
    ";

    $params = [];

    /*
    ============================================================
    STATUS FILTER
    ============================================================
    */

    if ($statusParam !== '' && strtolower($statusParam) !== 'all') {

        $statuses = array_map(
            'trim',
            explode(',', $statusParam)
        );

        $placeholders = implode(
            ',',
            array_fill(0, count($statuses), '?')
        );

        $query .= "
            AND LOWER(status) IN ($placeholders)
        ";

        $params = array_merge(
            $params,
            array_map(
                'strtolower',
                $statuses
            )
        );
    }

    /*
    ============================================================
    SCHOLARSHIP TYPE FILTER
    ============================================================
    */

    if (
        $typeParam !== '' &&
        strtolower($typeParam) !== 'all'
    ) {

        $query .= "
            AND scholarship_type = ?
        ";

        $params[] = $typeParam;
    }

    /*
    ============================================================
    ORDER
    ============================================================
    */

    $query .= "
        ORDER BY id DESC
    ";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $rows = $stmt->fetchAll();

    /*
    ============================================================
    FORMAT RESPONSE
    ============================================================
    */

    $data = array_map(function ($r) {

        return [

            /* --------------------------------------------------
               BASIC
            -------------------------------------------------- */

            'id' => (int)$r['id'],

            'studentId' => $r['student_id'],
            'student_id' => $r['student_id'],


            /* --------------------------------------------------
               NAME
            -------------------------------------------------- */

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

            'birthdate' => $r['birthdate'] ?? '',

            'age' => isset($r['age']) && $r['age'] !== ''
                ? (int)$r['age']
                : null,

            'email' => $r['email'] ?? '',

            'phone' => $r['phone'] ?? '',

            'address' => $r['address'] ?? '',
            'municipality' => $r['municipality'] ?? '',
            'barangay' => $r['barangay'] ?? '',


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
               LOCATION
            -------------------------------------------------- */

            'latitude' => $r['latitude'] !== null
                ? (float)$r['latitude']
                : null,

            'longitude' => $r['longitude'] !== null
                ? (float)$r['longitude']
                : null,


            /* --------------------------------------------------
               SCHOLARSHIP
            -------------------------------------------------- */

            'scholarshipType' => $r['scholarship_type'] ?? '',

            'scholarship_type' => $r['scholarship_type'] ?? '',

            'type' => $r['scholarship_type'] ?? '',


            /* --------------------------------------------------
               STATUS
            -------------------------------------------------- */

            'status' => $r['status'] ?? '',


            /* --------------------------------------------------
               EVALUATION DATA
            -------------------------------------------------- */

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

            'createdAt' => $r['created_at'] ?? '',

            'created_at' => $r['created_at'] ?? ''
        ];

    }, $rows);


    /*
    ============================================================
    SEND RESPONSE
    ============================================================
    */

    sendJson([
        'success' => true,
        'data' => $data
    ]);

} catch (Exception $e) {

    sendError(
        $e->getMessage(),
        500
    );
}