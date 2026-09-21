<?php
require_once __DIR__ . '/init.php';

/* ============================================================
   COORDINATES HELPER
============================================================ */
function getCoordinates($address)
{
    if (empty($address)) {
        return [10.1333, 124.8333];
    }

    $addrLower = strtolower($address);

    if (strpos($addrLower, 'maasin') !== false) {
        return [10.1333, 124.8333];
    }
    if (strpos($addrLower, 'macrohon') !== false) {
        return [10.0833, 124.9333];
    }
    if (strpos($addrLower, 'batu') !== false || strpos($addrLower, 'bato') !== false) {
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

    return [10.1333, 124.8333];
}

try {
    $pdo = getDB();

    $department = trim(
        $_GET['department'] ??
        $_GET['program'] ??
        ''
    );

    /* =========================================================
       1. GET STUDENT IDS AND RECORD IDS CURRENTLY IN TRASH
    ========================================================= */
    $deletedStudentIds = [];
    $deletedRecordIds = [];

    $stmtDeleted = $pdo->query("
        SELECT item_type, item_id, item_data
        FROM deleted_items
    ");

    $deletedRows = $stmtDeleted->fetchAll(PDO::FETCH_ASSOC);

    foreach ($deletedRows as $deleted) {
        if ($deleted['item_type'] === 'record' && !empty($deleted['item_id'])) {
            $deletedRecordIds[] = (int)$deleted['item_id'];
        }

        if (!empty($deleted['item_data'])) {
            $data = json_decode($deleted['item_data'], true);
            if (is_array($data)) {
                // A trashed record only hides that record; the student stays on the map through their other records.
                if (!empty($data['student_id']) && $deleted['item_type'] !== 'record') {
                    $deletedStudentIds[] = trim((string)$data['student_id']);
                }
                if ($deleted['item_type'] === 'record' && !empty($data['id'])) {
                    $deletedRecordIds[] = (int)$data['id'];
                }
            }
        }
    }

    $deletedStudentIds = array_unique($deletedStudentIds);
    $deletedRecordIds = array_unique($deletedRecordIds);

    // A student ID in the trash only hides the student while nobody with that ID is still live.
    // Otherwise an old trashed applicant/scholar (or one re-imported later) hides the new live student.
    if ($deletedStudentIds) {
        $liveIds = $pdo->query("SELECT student_id FROM applicants WHERE student_id IS NOT NULL AND student_id <> ''")
            ->fetchAll(PDO::FETCH_COLUMN);
        $liveIds = array_map(static fn($v) => trim((string)$v), $liveIds);
        $deletedStudentIds = array_values(array_diff($deletedStudentIds, $liveIds));
    }

    /* =========================================================
       2. FETCH APPROVED AND REJECTED RECORDS
       Every student who was decided on in Records shows on the map; the newest record decides the status
    ========================================================= */
    $stmtRecords = $pdo->query("
        SELECT
            id,
            applicant_id,
            student_id,
            name,
            scholarship_type,
            status,
            semester,
            sy,
            date_evaluated,
            remarks
        FROM records
        WHERE LOWER(TRIM(status)) IN ('approved', 'rejected', 'pending')
        ORDER BY id DESC
    ");

    $approvedRecords = $stmtRecords->fetchAll(PDO::FETCH_ASSOC);


    $list = [];
    $unlocated = [];   // students who have no location yet
    $seenStudents = [];

    foreach ($approvedRecords as $rec) {
        $recordId = (int)$rec['id'];
        $studentId = trim($rec['student_id'] ?? '');

        // Exclude if record or student is in trash/deleted
        if (in_array($recordId, $deletedRecordIds, true)) {
            continue;
        }
        if ($studentId !== '' && in_array($studentId, $deletedStudentIds, true)) {
            continue;
        }

        // Avoid duplicate pins for the same approved student
        if ($studentId !== '' && isset($seenStudents[$studentId])) {
            continue;
        }
        if ($studentId !== '') {
            $seenStudents[$studentId] = true;
        }

        // Match with applicants table to retrieve academic info and address
        $appStmt = $pdo->prepare("
            SELECT * FROM applicants
            WHERE student_id = ? OR id = ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $appStmt->execute([$studentId, $rec['applicant_id'] ?? 0]);
        $app = $appStmt->fetch(PDO::FETCH_ASSOC);

        // Fallback match with scholars table
        $schStmt = $pdo->prepare("
            SELECT * FROM scholars
            WHERE student_id = ?
            LIMIT 1
        ");
        $schStmt->execute([$studentId]);
        $sch = $schStmt->fetch(PDO::FETCH_ASSOC);

        $name = trim($rec['name'] ?? '');
        if (empty($name) && $app) {
            $name = trim(($app['first_name'] ?? '') . ' ' . ($app['last_name'] ?? ''));
        }

        // Determine department
        $prog = $app['program'] ?? $sch['department'] ?? '';
        $dept = 'Information Technology';

        if (stripos($prog, 'nursing') !== false) {
            $dept = 'Nursing';
        } elseif (stripos($prog, 'accountancy') !== false || stripos($prog, 'accounting') !== false) {
            $dept = 'Accountancy';
        } elseif (stripos($prog, 'business') !== false) {
            $dept = 'Business Administration';
        } elseif (stripos($prog, 'food') !== false || stripos($prog, 'service') !== false || stripos($prog, 'fpst') !== false) {
            $dept = 'Food Preparation & Service Technology';
        } elseif (stripos($prog, 'technology') !== false || stripos($prog, 'computer') !== false || stripos($prog, 'bsit') !== false) {
            $dept = 'Information Technology';
        } elseif (stripos($prog, 'political') !== false) {
            $dept = 'Political Science';
        }
        elseif (stripos($prog, 'public administration') !== false) {
            $dept = 'Public Administration';
        }
        elseif (stripos($prog, 'elementary education') !== false) {
            $dept = 'Elementary Education';
        }
        elseif (stripos($prog, 'secondary education') !== false) {
            $dept = 'Secondary Education';
        }elseif (stripos($prog, 'juris') !== false) {
            $dept = 'Juris Doctor';
        }
        elseif (stripos($prog, 'bookkeeping') !== false) {
            $dept = 'Bookkeeping';
        }
        elseif (stripos($prog, 'caregiver') !== false) {
            $dept = 'Caregiver';
        }
        elseif (stripos($prog, 'bread') !== false || stripos($prog, 'pastry') !== false) {
            $dept = 'Bread & Pastry Production';
        }
        elseif (!empty($sch['department'])) {
            $dept = $sch['department'];
        }

        // Department filter
        if (
            !empty($department) &&
            strtolower($department) !== 'all' &&
            strtolower($dept) !== strtolower($department)
        ) {
            continue;
        }

        // Determine coordinates and address
        $lat = !empty($app['latitude']) ? (float)$app['latitude'] : (!empty($sch['latitude']) ? (float)$sch['latitude'] : null);
        $lng = !empty($app['longitude']) ? (float)$app['longitude'] : (!empty($sch['longitude']) ? (float)$sch['longitude'] : null);
        $address = $app['address'] ?? $sch['address'] ?? '';

        // No stored position: work it out from the student's town (and barangay), or from the town
        // inside an older typed address.
        if ($lat === null || $lng === null || $lat == 0 || $lng == 0) {
            $town = trim((string)($app['municipality'] ?? ''));
            $brgy = trim((string)($app['barangay'] ?? ''));
            if ($town === '' && $address !== '') {
                $split = splitAddress($address);
                $town = $split['municipality'];
                if ($brgy === '') $brgy = $split['barangay'];
            }
            $coords = $town !== '' ? locationCoordinates($town, $brgy) : null;
            if ($coords !== null) {
                [$lat, $lng] = $coords;
            } else {
                // Unknown place: no pin (it used to default to Maasin, which piled everyone up there).
                $lat = null;
                $lng = null;
            }
        }

        $gwa = (float)($app['gwa'] ?? $app['gpa'] ?? $sch['gwa'] ?? 1.50);
        $yearLevel = (int)($app['year_level'] ?? $sch['year_level'] ?? 1);

        $list[] = [
            'id' => $recordId,
            'studentId' => $studentId,
            'name' => $name,
            'department' => $dept,
            'yearLevel' => $yearLevel,
            'gwa' => $gwa,
            // 'pending' = approved, awaiting the renewal check (see api/decide.php)
            'status' => strtolower(trim((string)$rec['status'])) === 'rejected' ? 'rejected' : 'approved',
            'scholarshipType' => $rec['scholarship_type'] ?? '',
            'schoolYear' => $rec['sy'] ?? '2025-2026',
            'address' => $address,
            'latitude' => $lat !== null ? (float)$lat : null,
            'longitude' => $lng !== null ? (float)$lng : null,
            'source' => 'record'
        ];
        if ($lat === null || $lng === null) {
            $unlocated[] = ['studentId' => $studentId, 'name' => $name];
        }
    }

    sendJson([
        'success' => true,
        'data' => $list,
        'unlocated' => $unlocated
    ]);

} catch (Exception $e) {

    sendError(
        $e->getMessage(),
        500
    );
}