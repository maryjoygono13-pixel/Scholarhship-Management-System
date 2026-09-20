<?php
require_once __DIR__ . '/init.php';

function restoreApplicantRow(PDO $pdo, array $data): void {
    $stmtRestore = $pdo->prepare("INSERT OR REPLACE INTO applicants (
        id, student_id, first_name, last_name, email, phone, birthdate, address, latitude, longitude,
        school, program, year_level, gpa, scholarship_type, status, gwa, gwa_req, failing_grades,
        units, enrolled, docs_complete, remarks, essay, transcript_file, recommendation_file, valid_id_file,
        created_at, updated_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $stmtRestore->execute([
        $data['id'] ?? null,
        $data['student_id'] ?? '',
        $data['first_name'] ?? '',
        $data['last_name'] ?? '',
        $data['email'] ?? '',
        $data['phone'] ?? '',
        $data['birthdate'] ?? '',
        $data['address'] ?? '',
        $data['latitude'] ?? null,
        $data['longitude'] ?? null,
        $data['school'] ?? '',
        $data['program'] ?? '',
        $data['year_level'] ?? '',
        $data['gpa'] ?? 0,
        $data['scholarship_type'] ?? 'Academic Merit',
        $data['status'] ?? 'pending',
        $data['gwa'] ?? 0,
        $data['gwa_req'] ?? 1.75,
        $data['failing_grades'] ?? 0,
        $data['units'] ?? 21,
        $data['enrolled'] ?? 1,
        $data['docs_complete'] ?? 1,
        $data['remarks'] ?? '',
        $data['essay'] ?? '',
        $data['transcript_file'] ?? '',
        $data['recommendation_file'] ?? '',
        $data['valid_id_file'] ?? '',
        $data['created_at'] ?? date('Y-m-d H:i:s'),
        date('Y-m-d H:i:s')
    ]);
}

try {
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        sendError('Invalid deleted item ID.');
    }

    $pdo = getDB();

    $stmt = $pdo->prepare("SELECT * FROM deleted_items WHERE id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();

    if (!$item) {
        sendError('Deleted item record not found.');
    }

    $type = strtolower($item['item_type']);
    $data = json_decode($item['item_data'], true);

    if (!$data) {
        sendError('Corrupted item data.');
    }

    if ($type === 'applicant') {
        restoreApplicantRow($pdo, $data);
    } else if ($type === 'scholar') {
        $stmtRestore = $pdo->prepare("INSERT OR REPLACE INTO scholars (
            id, student_id, name, department, year_level, gwa, status, school_year, remarks, address, latitude, longitude, created_at, updated_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmtRestore->execute([
            $data['id'] ?? null,
            $data['student_id'] ?? '',
            $data['name'] ?? '',
            $data['department'] ?? 'Information Technology',
            $data['year_level'] ?? 1,
            $data['gwa'] ?? 1.50,
            $data['status'] ?? 'Active',
            $data['school_year'] ?? '2025-2026',
            $data['remarks'] ?? '',
            $data['address'] ?? '',
            $data['latitude'] ?? null,
            $data['longitude'] ?? null,
            $data['created_at'] ?? date('Y-m-d H:i:s'),
            date('Y-m-d H:i:s')
        ]);
    } else if ($type === 'scholarship') {
        $stmtRestore = $pdo->prepare("INSERT OR REPLACE INTO scholarships (
            id, name, code, description, type, gwa_requirement, slots, slots_available, unlimited_slots, coverage, status, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmtRestore->execute([
            $data['id'] ?? null,
            $data['name'] ?? '',
            $data['code'] ?? '',
            $data['description'] ?? '',
            $data['type'] ?? 'Academic Merit',
            $data['gwa_requirement'] ?? 1.50,
            $data['slots'] ?? 50,
            $data['slots_available'] ?? 25,
            $data['unlimited_slots'] ?? 0,
            $data['coverage'] ?? '',
            $data['status'] ?? 'active',
            $data['created_at'] ?? date('Y-m-d H:i:s')
        ]);
    } else if ($type === 'record') {
        $stmtRestore = $pdo->prepare("INSERT OR REPLACE INTO records (
            id, applicant_id, student_id, name, scholarship_type, status, semester, sy, date_evaluated, remarks
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmtRestore->execute([
            $data['id'] ?? null,
            $data['applicant_id'] ?? null,
            $data['student_id'] ?? '',
            $data['name'] ?? '',
            $data['scholarship_type'] ?? 'Academic Merit',
            $data['status'] ?? 'approved',
            $data['semester'] ?? '1st Semester',
            $data['sy'] ?? '2025-2026',
            $data['date_evaluated'] ?? date('Y-m-d'),
            $data['remarks'] ?? ''
        ]);
    } else if ($type === 'notification') {
        $stmtRestore = $pdo->prepare("INSERT OR REPLACE INTO notifications (
            id, type, recipient_type, recipient_id, recipient_name, recipient_email, subject, message, deadline, status, sent_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmtRestore->execute([
            $data['id'] ?? null,
            $data['type'] ?? 'missing_requirements',
            $data['recipient_type'] ?? 'segment',
            $data['recipient_id'] ?? null,
            $data['recipient_name'] ?? '',
            $data['recipient_email'] ?? '',
            $data['subject'] ?? '',
            $data['message'] ?? '',
            $data['deadline'] ?? '',
            $data['status'] ?? 'sent',
            $data['sent_at'] ?? date('Y-m-d H:i:s')
        ]);
    } else if ($type === 'import') {
        $stmtRestore = $pdo->prepare("INSERT OR REPLACE INTO imported_files (
            id, file_type, file_name, file_size, records_count, imported_by, status, stored_path, created_applicant_ids, created_grade_ids, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmtRestore->execute([
            $data['id'] ?? null,
            $data['file_type'] ?? 'grades',
            $data['file_name'] ?? '',
            $data['file_size'] ?? 0,
            $data['records_count'] ?? 0,
            $data['imported_by'] ?? 'Registrar Staff',
            $data['status'] ?? 'Active',
            $data['stored_path'] ?? null,
            $data['created_applicant_ids'] ?? null,
            $data['created_grade_ids'] ?? null,
            $data['created_at'] ?? date('Y-m-d H:i:s')
        ]);

        // Bring back any applicants that were removed alongside this import.
        if (!empty($data['created_applicant_snapshots']) && is_array($data['created_applicant_snapshots'])) {
            foreach ($data['created_applicant_snapshots'] as $applicantSnapshot) {
                if (is_array($applicantSnapshot)) {
                    restoreApplicantRow($pdo, $applicantSnapshot);
                }
            }
        }

        // Bring back any subject grades that were removed alongside this
        // import, then recompute GWA / failing count for whoever they belong to.
        if (!empty($data['created_grade_snapshots']) && is_array($data['created_grade_snapshots'])) {
            $restoreGradeStmt = $pdo->prepare("INSERT OR REPLACE INTO student_grades (
                id, student_id, subject_code, subject_name, semester, school_year, grade, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

            $affectedStudentIds = [];
            foreach ($data['created_grade_snapshots'] as $gradeSnapshot) {
                if (!is_array($gradeSnapshot)) {
                    continue;
                }
                $restoreGradeStmt->execute([
                    $gradeSnapshot['id'] ?? null,
                    $gradeSnapshot['student_id'] ?? '',
                    $gradeSnapshot['subject_code'] ?? '',
                    $gradeSnapshot['subject_name'] ?? '',
                    $gradeSnapshot['semester'] ?? '1st Semester',
                    $gradeSnapshot['school_year'] ?? '',
                    $gradeSnapshot['grade'] ?? 0,
                    $gradeSnapshot['created_at'] ?? date('Y-m-d H:i:s'),
                    date('Y-m-d H:i:s')
                ]);
                if (!empty($gradeSnapshot['student_id'])) {
                    $affectedStudentIds[$gradeSnapshot['student_id']] = true;
                }
            }

            foreach (array_keys($affectedStudentIds) as $sid) {
                recalculateApplicantGwa($pdo, (string)$sid);
            }
        }
    }

    // Delete item from Trash Bin
    $stmtDel = $pdo->prepare("DELETE FROM deleted_items WHERE id = ?");
    $stmtDel->execute([$id]);

    sendJson(['success' => true, 'message' => 'Item restored successfully!']);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
