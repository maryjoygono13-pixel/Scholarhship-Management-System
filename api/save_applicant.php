<?php
require_once __DIR__ . '/init.php';

/* ============================================================
   CREATE SYSTEM NOTIFICATION
============================================================ */

function createSystemNotification(
    PDO $pdo,
    string $subject,
    string $message,
    string $type = 'approval_status',
    ?string $recipientName = null,
    $recipientId = null
) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications
            (
                type,
                recipient_type,
                recipient_id,
                recipient_name,
                recipient_email,
                subject,
                message,
                deadline,
                status
            )
            VALUES
            (
                ?,
                'system',
                ?,
                ?,
                '',
                ?,
                ?,
                NULL,
                'sent'
            )
        ");

        $stmt->execute([
            $type,
            $recipientId,
            $recipientName ?: 'Scholarship System',
            $subject,
            $message
        ]);
    } catch (Throwable $e) {
        error_log("Failed to create system notification: " . $e->getMessage());
    }
}

    function getCoordinates($address)
    {
        if (empty($address)) {
            return [10.1333, 124.8333];
        }

        $addrLower = strtolower($address);

        // Common locations in Southern Leyte
        if (strpos($addrLower, 'maasin') !== false) {
            return [10.1333, 124.8333];
        }

        if (strpos($addrLower, 'macrohon') !== false) {
            return [10.0833, 124.9333];
        }

        if (
            strpos($addrLower, 'batu') !== false ||
            strpos($addrLower, 'bato') !== false
        ) {
            return [10.3333, 124.7833];
        }

        if (strpos($addrLower, 'hilongos') !== false) {
            return [10.3739, 124.7497];
        }

        if (strpos($addrLower, 'padre burgos') !== false) {
            return [10.0389, 124.9750];
        }

        if (strpos($addrLower, 'sogod') !== false) {
            return [10.3833, 124.9833];
        }

        if (strpos($addrLower, 'malitbog') !== false) {
            return [10.1500, 125.0000];
        }

        if (strpos($addrLower, 'saint bernard') !== false) {
            return [10.3333, 125.1333];
        }

        if (strpos($addrLower, 'liloan') !== false) {
            return [10.1667, 125.1333];
        }

        if (strpos($addrLower, 'bontoc') !== false) {
            return [10.3500, 124.9667];
        }

        /*
        * No known-town match. We used to fall back to a live call to
        * the Nominatim geocoding API here, but that's a synchronous
        * network request on the critical save path — it routinely
        * took 1.7-3.8+ seconds (sometimes longer, since PHP's stream
        * "timeout" option doesn't bound DNS/connect time), and
        * Nominatim's usage policy throttles rapid repeated calls, so
        * adding several applicants in a row made each save slower
        * than the last. That's what made the whole site feel like it
        * froze. Default to the town-hall coordinate instead; precise
        * geocoding for unmatched addresses should happen out-of-band,
        * not while the user is waiting on a save.
        */
        return [10.1333, 124.8333];
    }

    try {
        $pdo = getDB();

        /*
        * ============================================================
        * GET FORM DATA
        * ============================================================
        */

        $id = (int) ($_POST['id'] ?? $_POST['applicantId'] ?? 0);

        $firstName = trim(
            $_POST['firstName'] ??
            $_POST['first_name'] ??
            ''
        );

        $lastName = trim(
            $_POST['lastName'] ??
            $_POST['last_name'] ??
            ''
        );

        $middleName = trim(
            $_POST['middleName'] ??
            $_POST['middle_name'] ??
            ''
        );

        $gender = trim(
            $_POST['gender'] ?? ''
        );

        $studentId = trim(
            $_POST['studentId'] ??
            $_POST['student_id'] ??
            ''
        );

        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $birthdate = trim($_POST['birthdate'] ?? '');

        /*
        * Calculate age from birthdate.
        * Age is not manually trusted from the form.
        */
        $age = null;

        if (!empty($birthdate)) {

            try {

                $birthDateObj =
                    new DateTime($birthdate);

                $today =
                    new DateTime();

                $age =
                    $today->diff($birthDateObj)->y;

            } catch (Throwable $e) {

                $age = null;
            }
        }
        $address = trim($_POST['address'] ?? '');

        $school = trim($_POST['school'] ?? '');

        $schoolYear = trim(
            $_POST['schoolYear'] ??
            $_POST['school_year'] ??
            ''
        );

        $program = trim(
            $_POST['program'] ?? ''
        );

        $major = trim(
            $_POST['major'] ?? ''
        );

        $yearLevel = trim(
            $_POST['yearLevel'] ??
            $_POST['year_level'] ??
            ''
        );

        $semester = trim(
            $_POST['semester'] ?? ''
        );
        if (!in_array($semester, ['1st Semester', '2nd Semester'], true)) {
            $semester = '1st Semester';
        }

        $gpa = (float) ($_POST['gpa'] ?? 0);

        $scholarshipType = trim(
            $_POST['scholarshipType'] ??
            $_POST['scholarship_type'] ??
            'Academic Merit'
        );

        // Comes from the selected scholarship sub-type's own required GWA
        // (see api/scholarship_types.php) — falls back to 1.75 only if the
        // form didn't send one (e.g. an older client).
        $gwaReq = (float) ($_POST['gwaReq'] ?? $_POST['gwa_req'] ?? 1.75);
        if ($gwaReq <= 0) {
            $gwaReq = 1.75;
        }
        // The scholarship's own sub-type requirement always wins over whatever
        // the form sent.
        $gwaReq = resolveGwaRequirement($pdo, $scholarshipType, $gwaReq);

        $essay = trim($_POST['essay'] ?? '');

        $status = trim($_POST['status'] ?? 'pending');

        /*
        * ============================================================
        * VALIDATION
        * ============================================================
        */

            if (
            empty($firstName) ||
            empty($lastName) ||
            empty($studentId) ||
            empty($email) ||
            empty($gender) ||
            empty($birthdate) ||
            empty($schoolYear) ||
            empty($program) ||
            empty($yearLevel)
        ) {
            sendError(
                'Please fill out all required applicant and academic fields.'
            );
        }

        if (empty($scholarshipType)) {
            sendError('Please select a scholarship type.');
        }
        /*
        * ============================================================
        * GET COORDINATES
        * ============================================================
        */

        [$latitude, $longitude] = getCoordinates($address);

        /*
        * ============================================================
        * FILE UPLOADS
        * ============================================================
        */

        $uploadDir = __DIR__ . '/../uploads/';

        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0777, true)) {
                sendError('Unable to create upload directory.', 500);
            }
        }

        $transcriptFile = '';
        $recommendationFile = '';
        $validIdFile = '';

        function saveUploadedFile($fieldName, $uploadDir)
        {
            if (
                !isset($_FILES[$fieldName]) ||
                !isset($_FILES[$fieldName]['name']) ||
                $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE
            ) {
                return '';
            }

            if ($_FILES[$fieldName]['error'] !== UPLOAD_ERR_OK) {
                return '';
            }

            $originalName = basename($_FILES[$fieldName]['name']);

            $extension = strtolower(
                pathinfo($originalName, PATHINFO_EXTENSION)
            );

            $allowedExtensions = [
                'pdf',
                'jpg',
                'jpeg',
                'png',
                'doc',
                'docx'
            ];

            if (!in_array($extension, $allowedExtensions, true)) {
                throw new Exception(
                    "Invalid file type for {$fieldName}."
                );
            }

            /*
            * Give every uploaded file a unique name.
            */
            $newFileName =
                uniqid($fieldName . '_', true) .
                '.' .
                $extension;

            $destination = $uploadDir . $newFileName;

            if (!move_uploaded_file(
                $_FILES[$fieldName]['tmp_name'],
                $destination
            )) {
                throw new Exception(
                    "Failed to upload {$fieldName}."
                );
            }

            return $newFileName;
        }

        /*
        * Support both naming styles.
        */
        $transcriptField =
            isset($_FILES['transcript'])
                ? 'transcript'
                : null;

        $recommendationField =
            isset($_FILES['recommendation'])
                ? 'recommendation'
                : null;

        $validIdField =
            isset($_FILES['validId'])
                ? 'validId'
                : (
                    isset($_FILES['valid_id'])
                        ? 'valid_id'
                        : null
                );

        if ($transcriptField) {
            $transcriptFile =
                saveUploadedFile(
                    $transcriptField,
                    $uploadDir
                );
        }

        if ($recommendationField) {
            $recommendationFile =
                saveUploadedFile(
                    $recommendationField,
                    $uploadDir
                );
        }

        if ($validIdField) {
            $validIdFile =
                saveUploadedFile(
                    $validIdField,
                    $uploadDir
                );
        }

        /*
        * ============================================================
        * UPDATE EXISTING APPLICANT
        * ============================================================
        *
        * This happens ONLY when JavaScript explicitly sends an ID.
        *
        * This is important:
        * We do NOT automatically update an applicant just because
        * the Student ID already exists.
        */

        if ($id > 0) {

            $findStmt = $pdo->prepare("
                SELECT
                    id,
                    student_id,
                    scholarship_type,
                    latitude,
                    longitude
                FROM applicants
                WHERE id = ?
                LIMIT 1
            ");

            $findStmt->execute([$id]);

            $existingApplicant = $findStmt->fetch(PDO::FETCH_ASSOC);

            if (!$existingApplicant) {
                sendError(
                    'The applicant you are trying to update no longer exists.',
                    404
                );
            }

            /*
            * Make sure the Student ID still belongs to the
            * applicant being updated.
            */
            if (
                (string) $existingApplicant['student_id']
                !== (string) $studentId
            ) {
                sendError(
                    'The Student ID does not match the selected applicant.',
                    400
                );
            }

            $updateSql = "
                UPDATE applicants
                SET
                student_id = ?,
                first_name = ?,
                middle_name = ?,
                last_name = ?,
                gender = ?,
                email = ?,
                phone = ?,
                birthdate = ?,
                age = ?,
                address = ?,
                latitude = ?,
                longitude = ?,
                school = ?,
                school_year = ?,
                program = ?,
                major = ?,
                year_level = ?,
                semester = ?,
                gpa = ?,
                scholarship_type = ?,
                gwa_req = ?,
                essay = ?,
                updated_at = CURRENT_TIMESTAMP
            ";

            $updateParams = [
                $studentId,
                $firstName,
                $middleName,
                $lastName,
                $gender,
                $email,
                $phone,
                $birthdate,
                $age,
                $address,
                $latitude,
                $longitude,
                $school,
                $schoolYear,
                $program,
                $major,
                $yearLevel,
                $semester,
                $gpa,
                $scholarshipType,
                $gwaReq,
                $essay
            ];
            /*
            * Only replace file columns when a new file
            * was actually uploaded.
            */
            if ($transcriptFile !== '') {
                $updateSql .= ", transcript_file = ?";
                $updateParams[] = $transcriptFile;
            }

            if ($recommendationFile !== '') {
                $updateSql .= ", recommendation_file = ?";
                $updateParams[] = $recommendationFile;
            }

            if ($validIdFile !== '') {
                $updateSql .= ", valid_id_file = ?";
                $updateParams[] = $validIdFile;
            }

            $updateSql .= " WHERE id = ?";

            $updateParams[] = $id;

            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($updateParams);

            $fullName =
                trim(
                    $firstName . ' ' .
                    $middleName . ' ' .
                    $lastName
                );

            createSystemNotification(
                $pdo,
                'Applicant Updated',
                $fullName .
                ' (Student ID: ' .
                $studentId .
                ') was updated in the scholarship system.',
                'applicant_updated',
                $fullName,
                $id
            );

            logActivity(
                $pdo,
                'Applicant Updated',
                'Applicants',
                $fullName . ' (Student ID: ' . $studentId . ') was updated.',
                $id
            );

            $oldLat = $existingApplicant['latitude'] ?? null;
            $oldLng = $existingApplicant['longitude'] ?? null;
            if ((float)$oldLat !== (float)$latitude || (float)$oldLng !== (float)$longitude) {
                logActivity(
                    $pdo,
                    'Map Location Updated',
                    'Scholar Map',
                    $fullName . ' (Student ID: ' . $studentId . ') location was updated to ' . $address . '.',
                    $id
                );
            }

            sendJson([
                'success' => true,
                'action' => 'updated',
                'id' => $id,
                'message' => 'Applicant updated successfully.'
            ]);
                    }

        /*
        * ============================================================
        * CHECK EXISTING STUDENT ID + SCHOLARSHIP TYPE + SCHOOL YEAR
        * ============================================================
        *
        * IMPORTANT:
        *
        * We only consider it a duplicate when ALL of these match:
        *
        *     student_id
        *     scholarship_type
        *     school_year
        *
        * Same Student ID + DIFFERENT scholarship  = new application (allowed).
        * Same Student ID + DIFFERENT school year  = new application (allowed).
        */

        $duplicateStmt = $pdo->prepare("
            SELECT
                id,
                student_id,
                first_name,
                last_name,
                email,
                scholarship_type,
                school_year,
                status
            FROM applicants
            WHERE student_id = ?
            AND scholarship_type = ?
            AND school_year = ?
            ORDER BY id DESC
            LIMIT 1
        ");

        $duplicateStmt->execute([
            $studentId,
            $scholarshipType,
            $schoolYear
        ]);

        $duplicate = $duplicateStmt->fetch(PDO::FETCH_ASSOC);

        if ($duplicate) {

            /*
            * Tell JavaScript that a matching application already exists.
            *
            * Do NOT insert.
            * Do NOT update.
            *
            * JavaScript will ask the user what to do.
            */

            sendJson([
                'success' => false,
                'duplicate' => true,
                'existingApplicant' => [
                    'id' => (int) $duplicate['id'],
                    'studentId' => $duplicate['student_id'],
                    'firstName' => $duplicate['first_name'],
                    'lastName' => $duplicate['last_name'],
                    'email' => $duplicate['email'],
                    'scholarshipType' => $duplicate['scholarship_type'],
                    'schoolYear' => $duplicate['school_year'],
                    'status' => $duplicate['status']
                ],
                'message' =>
                    'An application with this Student ID already exists for this scholarship and school year.'
            ]);
        }

        /*
        * ============================================================
        * CHECK STUDENT ID IS NOT ALREADY USED BY A DIFFERENT PERSON
        * ============================================================
        *
        * The Student ID is not a unique/primary key in this system —
        * the same ID can legitimately appear on multiple applications
        * (different scholarship, different school year). But it
        * should always belong to the SAME person. If this ID is
        * already on file under a different name, warn instead of
        * silently creating a second identity under the same ID.
        */

        if (empty($_POST['confirmNameMismatch'])) {

            $nameCheckStmt = $pdo->prepare("
                SELECT id, student_id, first_name, last_name, scholarship_type, school_year, status
                FROM applicants
                WHERE student_id = ?
                ORDER BY id DESC
                LIMIT 1
            ");
            $nameCheckStmt->execute([$studentId]);
            $existingById = $nameCheckStmt->fetch(PDO::FETCH_ASSOC);

            if ($existingById) {
                $existingFullName = trim($existingById['first_name'] . ' ' . $existingById['last_name']);
                $newFullName = trim($firstName . ' ' . $lastName);

                if (strcasecmp($existingFullName, $newFullName) !== 0) {
                    sendJson([
                        'success' => false,
                        'nameMismatch' => true,
                        'existingApplicant' => [
                            'id' => (int) $existingById['id'],
                            'studentId' => $existingById['student_id'],
                            'firstName' => $existingById['first_name'],
                            'lastName' => $existingById['last_name'],
                            'scholarshipType' => $existingById['scholarship_type'],
                            'schoolYear' => $existingById['school_year'],
                            'status' => $existingById['status']
                        ],
                        'message' =>
                            'Student ID ' . $studentId . ' is already on file under a different name ("' .
                            $existingFullName . '"). You are entering "' . $newFullName . '" for the same ID.'
                    ]);
                }
            }
        }

        /*
        * ============================================================
        * CREATE NEW APPLICANT
        * ============================================================
        *
        * If we reach this point:
        *
        * - Student ID is new, OR
        * - Student ID exists but scholarship type is different.
        *
        * Therefore, create a new application.
        */

                /*
        * ============================================================
        * CREATE NEW APPLICANT
        * ============================================================
        */

        $insertSql = "
            INSERT INTO applicants (
            student_id,
            first_name,
            middle_name,
            last_name,
            gender,
            email,
            phone,
            birthdate,
            age,
            address,
            latitude,
            longitude,
            school,
            school_year,
            program,
            major,
            year_level,
            semester,
            gpa,
            scholarship_type,
            status,
            gwa,
            gwa_req,
            failing_grades,
            units,
            enrolled,
            docs_complete,
            essay,
            transcript_file,
            recommendation_file,
            valid_id_file
        )
            VALUES (
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?
            )
        ";

        $insertStmt = $pdo->prepare($insertSql);

        $insertParams = [
            $studentId,          // 1
            $firstName,          // 2
            $middleName,         // 3
            $lastName,           // 4
            $gender,             // 5
            $email,              // 6
            $phone,              // 7
            $birthdate,          // 8
            $age,                // 9
            $address,            // 10
            $latitude,           // 11
            $longitude,          // 12
            $school,             // 13
            $schoolYear,         // 14
            $program,            // 15
            $major,              // 16
            $yearLevel,          // 17
            $semester,           // 17b
            $gpa,                // 18
            $scholarshipType,    // 19
            $status,             // 20
            $gpa,                // 21 - gwa
            $gwaReq,             // 22 - gwa_req
            0,                   // 23 - failing_grades
            21,                  // 24 - units
            1,                   // 25 - enrolled
            1,                   // 26 - docs_complete
            $essay,              // 27
            $transcriptFile,     // 28
            $recommendationFile, // 29
            $validIdFile         // 30
        ];

        $insertStmt->execute($insertParams);

        $newId = (int) $pdo->lastInsertId();

        $fullName =
            trim(
                $firstName . ' ' .
                $middleName . ' ' .
                $lastName
            );

        createSystemNotification(
            $pdo,
            'New Applicant Added',
            $fullName .
            ' (Student ID: ' .
            $studentId .
            ') submitted a ' .
            $scholarshipType .
            ' application.',
            'new_applicant',
            $fullName,
            $newId
        );

        logActivity(
            $pdo,
            'Applicant Created',
            'Applicants',
            $fullName . ' (Student ID: ' . $studentId . ') submitted a ' . $scholarshipType . ' application.',
            $newId
        );

        sendJson([
            'success' => true,
            'action' => 'created',
            'id' => $newId,
            'message' => 'Applicant added successfully.'
        ]);

    } catch (Throwable $e) {

        sendError(
            $e->getMessage(),
            500
        );
    }