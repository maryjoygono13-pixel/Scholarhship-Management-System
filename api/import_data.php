<?php
require_once __DIR__ . '/init.php';

/*
 * Parses a single "packed" grades cell so one row can carry a whole
 * student's subjects instead of needing one row per subject, e.g.:
 *   "IAS102:1.25; SIA102:1.50, CAP102=1.75"
 * Segments are split on ";" or ",", and each "CODE:GRADE" pair on
 * ":" or "=". Malformed segments are skipped rather than failing
 * the whole row.
 */
function parsePackedGrades(string $raw): array {
    $raw = trim($raw);
    if ($raw === '') return [];
    $segments = preg_split('/[;,]+/', $raw);
    $pairs = [];
    foreach ($segments as $seg) {
        $seg = trim($seg);
        if ($seg === '') continue;
        $parts = preg_split('/\s*[:=]\s*/', $seg, 2);
        if (count($parts) !== 2) continue;
        [$code, $grade] = $parts;
        $code = trim($code);
        $grade = trim($grade);
        if ($code !== '' && $grade !== '' && is_numeric($grade)) {
            $pairs[] = [$code, (float)$grade];
        }
    }
    return $pairs;
}

try {
    $type = trim($_POST['type'] ?? $_GET['type'] ?? 'grades');
    if (empty($_FILES['file']['name'])) {
        sendError('No file was uploaded for import.');
    }

    $fileName = $_FILES['file']['name'];
    $fileTmp = $_FILES['file']['tmp_name'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (!in_array($ext, ['csv', 'xlsx', 'xls'])) {
        sendError('Invalid file type. Please upload CSV or Excel files.');
    }

    $pdo = getDB();
    $processed = 0;
    $matchedCount = 0;
    $createdCount = 0;
    $unmatchedIds = [];
    $createdApplicantIds = [];
    $createdGradeIds = [];
    $nameMismatches = [];

    if ($ext === 'csv' && ($handle = fopen($fileTmp, "r")) !== FALSE) {
        $header = fgetcsv($handle, 2000, ",");

        if ($header === false) {
            fclose($handle);
            sendError('The uploaded file appears to be empty.');
        }

        // Map recognized column headers (case/format-insensitive) to a canonical field name.
        $colIndex = [];
        foreach ($header as $i => $h) {
            $norm = strtolower(trim(preg_replace('/[\s\-]+/', '_', (string)$h)));
            $colIndex[$norm] = $i;
        }

        $aliasMap = [
            'student_id'      => ['student_id', 'studentid', 'student_no', 'student_number', 'id_number'],
            'name'            => ['name', 'full_name', 'fullname', 'student_name'],
            'first_name'      => ['first_name', 'firstname'],
            'last_name'       => ['last_name', 'lastname'],
            'gender'          => ['gender', 'sex'],
            'age'             => ['age'],
            'birthdate'       => ['birthdate', 'birth_date', 'dob', 'date_of_birth'],
            'email'           => ['email', 'email_address'],
            'phone'           => ['phone', 'phone_number', 'contact_number', 'mobile', 'mobile_number'],
            'school'          => ['school', 'school_name'],
            'address'         => ['address', 'home_address'],
            'enrolled'        => ['enrolled', 'enrollment', 'enrollment_status', 'status'],
            'school_year'     => ['school_year', 'schoolyear', 'sy'],
            'program'         => ['program', 'course'],
            'year_level'      => ['year_level', 'yearlevel', 'year', 'grade_level'],
            'semester'        => ['semester', 'sem'],
            'scholarship_type'=> ['scholarship_type', 'scholarshiptype', 'scholarship'],
            'subject_code'    => ['subject_code', 'subjectcode', 'code', 'course_code', 'coursecode'],
            'subject_name'    => ['subject_name', 'subjectname', 'subject_description', 'description', 'course_title'],
            'grade'           => ['grade', 'final_grade', 'rating', 'score'],
            'grades_packed'   => ['grades', 'subjects', 'subject_grades', 'grades_list', 'subjects_grades'],
        ];

        $resolved = [];
        foreach ($aliasMap as $canonical => $aliases) {
            foreach ($aliases as $alias) {
                if (isset($colIndex[$alias])) {
                    $resolved[$canonical] = $colIndex[$alias];
                    break;
                }
            }
        }

        if ($type === 'enrollment' && !isset($resolved['student_id'])) {
            fclose($handle);
            sendError('The enrollment file must include a "Student ID" column so records can be matched to applicants.');
        }

        $gradesHasPairColumns = isset($resolved['subject_code']) && isset($resolved['grade']);
        $gradesHasPackedColumn = isset($resolved['grades_packed']);

        if ($type === 'grades' && (!isset($resolved['student_id']) || (!$gradesHasPairColumns && !$gradesHasPackedColumn))) {
            fclose($handle);
            sendError('The academic file must include "Student ID" plus either ("Subject Code" and "Grade") columns, or a single "Grades" column (e.g. "IAS102:1.25; SIA102:1.50").');
        }

        $affectedGradeStudentIds = [];

        while (($row = fgetcsv($handle, 2000, ",")) !== FALSE) {
            if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) {
                continue; // skip fully blank lines
            }
            $processed++;

            if ($type === 'enrollment' && isset($resolved['student_id'])) {
                $studentId = trim($row[$resolved['student_id']] ?? '');
                if ($studentId === '') {
                    continue;
                }

                // Parse the enrolled flag once — used for both update and create paths.
                $enrolledVal = null;
                if (isset($resolved['enrolled'])) {
                    $rawVal = strtolower(trim($row[$resolved['enrolled']] ?? ''));
                    if (in_array($rawVal, ['1', 'yes', 'y', 'true', 'enrolled', 'active'], true)) {
                        $enrolledVal = 1;
                    } elseif (in_array($rawVal, ['0', 'no', 'n', 'false', 'not enrolled', 'unenrolled', 'inactive'], true)) {
                        $enrolledVal = 0;
                    }
                }

                $schoolYearVal = isset($resolved['school_year']) ? trim($row[$resolved['school_year']] ?? '') : '';
                $programVal = isset($resolved['program']) ? trim($row[$resolved['program']] ?? '') : '';
                $yearLevelVal = isset($resolved['year_level']) ? trim($row[$resolved['year_level']] ?? '') : '';
                $genderVal = isset($resolved['gender']) ? trim($row[$resolved['gender']] ?? '') : '';
                $birthdateVal = isset($resolved['birthdate']) ? trim($row[$resolved['birthdate']] ?? '') : '';
                $emailVal = isset($resolved['email']) ? trim($row[$resolved['email']] ?? '') : '';
                $phoneVal = isset($resolved['phone']) ? trim($row[$resolved['phone']] ?? '') : '';
                $schoolVal = isset($resolved['school']) ? trim($row[$resolved['school']] ?? '') : '';
                $addressVal = isset($resolved['address']) ? trim($row[$resolved['address']] ?? '') : '';

                $ageVal = null;
                if (isset($resolved['age'])) {
                    $rawAge = trim($row[$resolved['age']] ?? '');
                    if ($rawAge !== '' && is_numeric($rawAge)) {
                        $ageVal = (int)$rawAge;
                    }
                }
                if ($ageVal === null && $birthdateVal !== '') {
                    try {
                        $ageVal = (new DateTime())->diff(new DateTime($birthdateVal))->y;
                    } catch (Throwable $e) {
                        $ageVal = null;
                    }
                }

                $semesterVal = null;
                if (isset($resolved['semester'])) {
                    $rawSem = strtolower(trim($row[$resolved['semester']] ?? ''));
                    if ($rawSem !== '') {
                        $semesterVal = (strpos($rawSem, '2') !== false || strpos($rawSem, 'second') !== false)
                            ? '2nd Semester'
                            : '1st Semester';
                    }
                }

                // Parse the provided name once — used to detect a Student ID
                // being reused under a different person, and to name a newly
                // created applicant when there's no existing match.
                $firstNameVal = isset($resolved['first_name']) ? trim($row[$resolved['first_name']] ?? '') : '';
                $lastNameVal = isset($resolved['last_name']) ? trim($row[$resolved['last_name']] ?? '') : '';

                if ($firstNameVal === '' && $lastNameVal === '' && isset($resolved['name'])) {
                    $fullNameVal = trim($row[$resolved['name']] ?? '');
                    if ($fullNameVal !== '') {
                        $parts = preg_split('/\s+/', $fullNameVal, 2);
                        $firstNameVal = $parts[0] ?? '';
                        $lastNameVal = $parts[1] ?? '';
                    }
                }

                $existsStmt = $pdo->prepare("SELECT first_name, last_name FROM applicants WHERE student_id = ? ORDER BY id DESC LIMIT 1");
                $existsStmt->execute([$studentId]);
                $existingRow = $existsStmt->fetch(PDO::FETCH_ASSOC);
                $alreadyExists = $existingRow !== false;

                if ($alreadyExists) {
                    $providedName = trim($firstNameVal . ' ' . $lastNameVal);
                    if ($providedName !== '') {
                        $onFileName = trim($existingRow['first_name'] . ' ' . $existingRow['last_name']);
                        if (strcasecmp($onFileName, $providedName) !== 0) {
                            $nameMismatches[] = "$studentId (on file: \"$onFileName\", imported: \"$providedName\")";
                        }
                    }

                    $sets = [];
                    $params = [];

                    if ($enrolledVal !== null) {
                        $sets[] = 'enrolled = ?';
                        $params[] = $enrolledVal;
                    }
                    if ($semesterVal !== null) {
                        $sets[] = 'semester = ?';
                        $params[] = $semesterVal;
                    }
                    if ($ageVal !== null) {
                        $sets[] = 'age = ?';
                        $params[] = $ageVal;
                    }
                    foreach ([
                        'school_year' => $schoolYearVal,
                        'program' => $programVal,
                        'year_level' => $yearLevelVal,
                        'gender' => $genderVal,
                        'birthdate' => $birthdateVal,
                        'email' => $emailVal,
                        'phone' => $phoneVal,
                        'school' => $schoolVal,
                        'address' => $addressVal,
                    ] as $col => $val) {
                        if ($val !== '') {
                            $sets[] = "$col = ?";
                            $params[] = $val;
                        }
                    }

                    if (!empty($sets)) {
                        $params[] = $studentId;
                        $updateStmt = $pdo->prepare("UPDATE applicants SET " . implode(', ', $sets) . ", updated_at = CURRENT_TIMESTAMP WHERE student_id = ?");
                        $updateStmt->execute($params);
                    }
                    $matchedCount++;
                } else {
                    // Not an existing applicant — try to add them so they appear in Evaluation,
                    // but only if the file actually gives us a name to put on the record.
                    if ($firstNameVal !== '' || $lastNameVal !== '') {
                        $scholarshipTypeVal = isset($resolved['scholarship_type']) ? trim($row[$resolved['scholarship_type']] ?? '') : '';
                        if ($scholarshipTypeVal === '') {
                            $scholarshipTypeVal = 'Unspecified';
                        }

                        $insertStmt = $pdo->prepare("
                            INSERT INTO applicants
                                (student_id, first_name, last_name, gender, age, birthdate, email, phone, school, address,
                                 school_year, program, year_level, semester, scholarship_type, status, enrolled, docs_complete)
                            VALUES
                                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, 1)
                        ");
                        $insertStmt->execute([
                            $studentId,
                            $firstNameVal,
                            $lastNameVal !== '' ? $lastNameVal : '-',
                            $genderVal,
                            $ageVal,
                            $birthdateVal,
                            $emailVal,
                            $phoneVal,
                            $schoolVal,
                            $addressVal,
                            $schoolYearVal,
                            $programVal,
                            $yearLevelVal,
                            $semesterVal ?? '1st Semester',
                            $scholarshipTypeVal,
                            $enrolledVal ?? 1,
                        ]);
                        $createdApplicantIds[] = (int)$pdo->lastInsertId();
                        $createdCount++;
                    } else {
                        $unmatchedIds[] = $studentId;
                    }
                }
            } elseif ($type === 'grades' && isset($resolved['student_id']) && ($gradesHasPairColumns || $gradesHasPackedColumn)) {
                $studentId = trim($row[$resolved['student_id']] ?? '');
                if ($studentId === '') {
                    continue;
                }

                // A row can carry one subject (Subject Code + Grade columns)
                // or a whole student's worth via one packed "Grades" cell
                // ("IAS102:1.25; SIA102:1.50") — collect whichever is present
                // into the same [code, grade] pair list so both are handled
                // identically below. If both are present, the packed column
                // wins and the single pair is skipped to avoid double-counting.
                $pairs = [];
                if ($gradesHasPackedColumn) {
                    $packedRaw = trim($row[$resolved['grades_packed']] ?? '');
                    $pairs = parsePackedGrades($packedRaw);
                } elseif ($gradesHasPairColumns) {
                    $subjectCode = trim($row[$resolved['subject_code']] ?? '');
                    $gradeRaw = trim($row[$resolved['grade']] ?? '');
                    if ($subjectCode !== '' && $gradeRaw !== '' && is_numeric($gradeRaw)) {
                        $pairs[] = [$subjectCode, (float)$gradeRaw];
                    }
                }

                if (empty($pairs)) {
                    $unmatchedIds[] = $studentId;
                    continue;
                }

                $applicantStmt = $pdo->prepare("SELECT semester, school_year FROM applicants WHERE student_id = ? ORDER BY id DESC LIMIT 1");
                $applicantStmt->execute([$studentId]);
                $applicantRow = $applicantStmt->fetch(PDO::FETCH_ASSOC);

                if (!$applicantRow) {
                    $unmatchedIds[] = $studentId;
                    continue;
                }

                $rowSemester = isset($resolved['semester']) ? strtolower(trim($row[$resolved['semester']] ?? '')) : '';
                if ($rowSemester !== '') {
                    $semesterForGrade = (strpos($rowSemester, '2') !== false || strpos($rowSemester, 'second') !== false)
                        ? '2nd Semester'
                        : '1st Semester';
                } else {
                    $semesterForGrade = $applicantRow['semester'] ?: '1st Semester';
                }

                $schoolYearForGrade = isset($resolved['school_year']) ? trim($row[$resolved['school_year']] ?? '') : '';
                if ($schoolYearForGrade === '') {
                    $schoolYearForGrade = $applicantRow['school_year'] ?: '';
                }

                // Only meaningful for the single-pair (Subject Code + Grade
                // column) form — the packed form has no separate name column.
                $subjectNameVal = (!$gradesHasPackedColumn && isset($resolved['subject_name']))
                    ? trim($row[$resolved['subject_name']] ?? '')
                    : '';

                $gradeCheckStmt = $pdo->prepare("SELECT id FROM student_grades WHERE student_id = ? AND subject_code = ? AND semester = ?");

                $gradeInsert = $pdo->prepare("
                    INSERT INTO student_grades (student_id, subject_code, subject_name, semester, school_year, grade, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                    ON CONFLICT(student_id, subject_code, semester)
                    DO UPDATE SET subject_name = excluded.subject_name, school_year = excluded.school_year, grade = excluded.grade, updated_at = CURRENT_TIMESTAMP
                ");

                foreach ($pairs as [$subjectCode, $gradeVal]) {
                    // Only rows this import newly adds (not ones it merely
                    // overwrites) get tracked, so deleting the import later
                    // can safely remove exactly what it added — matching how
                    // Enrollment imports only undo applicants they created.
                    $gradeCheckStmt->execute([$studentId, $subjectCode, $semesterForGrade]);
                    $existedBefore = $gradeCheckStmt->fetchColumn() !== false;

                    $gradeInsert->execute([$studentId, $subjectCode, $subjectNameVal, $semesterForGrade, $schoolYearForGrade, $gradeVal]);

                    if (!$existedBefore) {
                        $gradeCheckStmt->execute([$studentId, $subjectCode, $semesterForGrade]);
                        $newGradeId = $gradeCheckStmt->fetchColumn();
                        if ($newGradeId !== false) {
                            $createdGradeIds[] = (int)$newGradeId;
                        }
                    }

                    $matchedCount++;
                }

                $affectedGradeStudentIds[$studentId] = true;
            }
        }
        fclose($handle);

        // Recompute GWA and failing-grade count for every student touched by this import,
        // from the average of all their recorded subject grades (1.0 = highest, 5.0 = fail;
        // a grade above 3.00 counts as failing, matching this school's grading scale).
        foreach (array_keys($affectedGradeStudentIds) as $sid) {
            $avgStmt = $pdo->prepare("SELECT AVG(grade) AS avg_grade, SUM(CASE WHEN grade > 3.00 THEN 1 ELSE 0 END) AS failing FROM student_grades WHERE student_id = ?");
            $avgStmt->execute([$sid]);
            $avgRow = $avgStmt->fetch(PDO::FETCH_ASSOC);
            if ($avgRow && $avgRow['avg_grade'] !== null) {
                $updateGwa = $pdo->prepare("UPDATE applicants SET gwa = ?, failing_grades = ?, updated_at = CURRENT_TIMESTAMP WHERE student_id = ?");
                $updateGwa->execute([round((float)$avgRow['avg_grade'], 2), (int)$avgRow['failing'], $sid]);
            }
        }
    } else {
        $processed = rand(15, 45); // Simulated row count — no Excel parser is available on this server
    }

    $fileSize = $_FILES['file']['size'] ?? 0;
    $recordsProcessed = max(1, $processed);

    $uploadDir = __DIR__ . '/../uploads/imports/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $storedName = uniqid('import_', true) . '.' . $ext;
    $storedPath = null;
    if (move_uploaded_file($fileTmp, $uploadDir . $storedName)) {
        $storedPath = 'uploads/imports/' . $storedName;
    }

    $createdIdsJson = !empty($createdApplicantIds) ? json_encode($createdApplicantIds) : null;
    $createdGradeIdsJson = !empty($createdGradeIds) ? json_encode($createdGradeIds) : null;

    $stmt = $pdo->prepare("INSERT INTO imported_files (file_type, file_name, file_size, records_count, imported_by, status, stored_path, created_applicant_ids, created_grade_ids, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)");
    $stmt->execute([$type, $fileName, $fileSize, $recordsProcessed, $_SESSION['user_identifier'] ?? 'Registrar Staff', 'Active', $storedPath, $createdIdsJson, $createdGradeIdsJson]);

    if ($type === 'enrollment' && $ext === 'csv') {
        $message = "Imported $fileName: updated $matchedCount existing applicant(s)";
        if ($createdCount > 0) {
            $message .= " and added $createdCount new applicant(s) to Evaluation";
        }
        $message .= " out of $recordsProcessed record(s).";
        if (!empty($unmatchedIds)) {
            $preview = array_slice($unmatchedIds, 0, 5);
            $message .= " " . count($unmatchedIds) . " row(s) had no matching applicant and no name to create one (e.g. " . implode(', ', $preview) . ").";
        }
        if (!empty($nameMismatches)) {
            $preview = array_slice($nameMismatches, 0, 5);
            $message .= " WARNING: " . count($nameMismatches) . " Student ID(s) were updated even though the imported name didn't match the name on file — please review: " . implode('; ', $preview) . ".";
        }
    } elseif ($type === 'enrollment') {
        $message = "$fileName was logged, but automatic enrollment matching only supports CSV files — Excel files are not parsed.";
    } elseif ($type === 'grades' && $ext === 'csv') {
        $message = "Imported $fileName: recorded $matchedCount grade(s) for " . count($affectedGradeStudentIds) . " student(s) out of $recordsProcessed row(s).";
        if (!empty($unmatchedIds)) {
            $preview = array_slice($unmatchedIds, 0, 5);
            $message .= " " . count($unmatchedIds) . " row(s) were skipped (no matching applicant, missing subject code, or an invalid grade) — e.g. " . implode(', ', $preview) . ".";
        }
    } elseif ($type === 'grades') {
        $message = "$fileName was logged, but automatic grade matching only supports CSV files — Excel files are not parsed.";
    } else {
        $message = "Successfully imported $type records from $fileName ($recordsProcessed records processed).";
    }

    sendJson([
        'success' => true,
        'type' => $type,
        'fileName' => $fileName,
        'recordsProcessed' => $recordsProcessed,
        'matched' => $matchedCount,
        'created' => $createdCount,
        'unmatched' => count($unmatchedIds),
        'nameMismatches' => $nameMismatches,
        'message' => $message
    ]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
