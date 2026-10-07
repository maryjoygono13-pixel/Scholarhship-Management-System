<?php
/*
 * CHED enrollment list verification (Data Management → Verify CHED Enrollment List).
 *
 * CHED sends the Registrar a list of students to confirm. This reads that list (Excel or CSV,
 * with or without Student IDs, header row anywhere near the top), looks every student up in the
 * Registrar's database (config/registrar_database.php) and answers, per student:
 *   enrolled      found, and officially enrolled this Academic Year
 *   not_enrolled  found, but not enrolled this Academic Year
 *   not_found     not in the Registrar's records
 *   review        can't be told apart automatically (several possible matches, or the
 *                 Student ID and name point to different people) — check manually
 * Students confirmed as enrolled are marked enrolled in this system (applicants.enrollment_verified)
 * even without an uploaded Certificate of Enrollment. The answers are also written back onto a
 * copy of the CHED list for download.
 */

require_once __DIR__ . '/xlsx_helper.php';
require_once __DIR__ . '/programs_helper.php';
require_once __DIR__ . '/term_helper.php';

const EV_RESULT_LABELS = [
    'enrolled' => 'Enrolled',
    'not_enrolled' => 'Not enrolled',
    'not_found' => 'Not found',
    'review' => 'Check manually',
];

// Where the downloadable result files are kept (not publicly reachable: storage/.htaccess).
function evStorageDir(): string {
    $dir = __DIR__ . '/../storage/verifications';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $guard = __DIR__ . '/../storage/.htaccess';
    if (!is_file($guard)) file_put_contents($guard, "Require all denied\nDeny from all\n");
    return $dir;
}

// Lowercase letters only, single-spaced (accents and punctuation dropped) — for fair name comparison.
function evNorm(string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, ['ñ' => 'n', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
    $s = preg_replace('/[^a-z ]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

// Header text -> the field it holds, or null.
function evHeaderField(string $h): ?string {
    $k = evNorm(str_replace(['_', '-', '.', '/'], ' ', $h));
    $map = [
        'student_id' => ['student id', 'student no', 'student number', 'id number', 'id no', 'school id', 'stud id', 'student id no'],
        'last_name' => ['last name', 'lastname', 'surname', 'family name', 'apelyido'],
        'first_name' => ['first name', 'firstname', 'given name', 'given names', 'pangalan'],
        'middle_name' => ['middle name', 'middlename', 'middle initial', 'mi', 'm i'],
        'extension' => ['extension name', 'ext name', 'name extension', 'suffix', 'ext'],
        'sex' => ['sex', 'gender'],
        'full_name' => ['name', 'full name', 'fullname', 'student name', 'name of student', 'name of grantee', 'grantee name', 'complete name'],
        'birthdate' => ['birthdate', 'birth date', 'date of birth', 'dob', 'birthday'],
        'program' => ['program', 'course', 'degree program', 'program course', 'course program', 'degree'],
        'year_level' => ['year level', 'year', 'yr level', 'level'],
    ];
    foreach ($map as $field => $aliases) {
        if (in_array($k, $aliases, true)) return $field;
    }
    return null;
}

// The header row (searched in the first 20 rows, since CHED lists often start with titles)
// and its column map. Needs a Student ID or a name to work with.
function evFindHeader(array $rows): ?array {
    $best = null;
    foreach (array_slice($rows, 0, 20, true) as $i => $row) {
        $cols = [];
        foreach ($row as $c => $cell) {
            $f = evHeaderField((string)$cell);
            if ($f !== null && !isset($cols[$f])) $cols[$f] = $c;
        }
        $hasName = isset($cols['full_name']) || (isset($cols['last_name']) && isset($cols['first_name']));
        if (!$hasName && !isset($cols['student_id'])) continue;
        if ($best === null || count($cols) > count($best['cols'])) $best = ['index' => $i, 'cols' => $cols];
    }
    return $best;
}

// "DELA CRUZ, JUAN M." or "Juan M. Dela Cruz" -> [last, first, middle].
function evSplitFullName(string $name): array {
    $name = trim(preg_replace('/\s+/', ' ', $name));
    if (strpos($name, ',') !== false) {
        [$last, $rest] = array_map('trim', explode(',', $name, 2));
        $parts = $rest === '' ? [] : explode(' ', $rest);
        $middle = count($parts) > 1 ? array_pop($parts) : '';
        return [$last, implode(' ', $parts), $middle];
    }
    $parts = explode(' ', $name);
    if (count($parts) < 2) return [$name, '', ''];
    $last = array_pop($parts);
    // Common two-word surnames ("Dela Cruz", "De los Santos", "San Jose").
    $prefixes = ['de', 'del', 'dela', 'delos', 'de la', 'de los', 'san', 'santa', 'sta', 'sto', 'van', 'von', 'mac', 'mc'];
    while (count($parts) > 1) {
        $n = count($parts);
        if ($n > 2 && in_array(evNorm($parts[$n - 2] . ' ' . $parts[$n - 1]), $prefixes, true)) {
            $last = $parts[$n - 2] . ' ' . $parts[$n - 1] . ' ' . $last;   // "de los", "de la"
            array_splice($parts, -2);
        } elseif (in_array(evNorm($parts[$n - 1]), $prefixes, true)) {
            $last = array_pop($parts) . ' ' . $last;
        } else {
            break;
        }
    }
    $middle = count($parts) > 1 && strlen(rtrim(end($parts), '.')) <= 2 ? array_pop($parts) : '';
    return [$last, implode(' ', $parts), $middle];
}

// A birthdate cell (text, or an Excel date serial number) -> 'Y-m-d', or '' when unreadable.
function evDate(string $v): string {
    $v = trim($v);
    if ($v === '') return '';
    if (is_numeric($v) && (float)$v > 10000 && (float)$v < 80000) {
        return (new DateTime('1899-12-30'))->modify('+' . (int)$v . ' days')->format('Y-m-d');
    }
    // 10/25/2005 or 10-25-2005: month/day/year as written in the Philippines — or day/month/year
    // when the first number can't be a month.
    if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{2,4})$#', $v, $m)) {
        [$a, $b, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]];
        if ($y < 100) $y += $y > 50 ? 1900 : 2000;
        [$month, $day] = $a > 12 ? [$b, $a] : [$a, $b];
        return checkdate($month, $day, $y) ? sprintf('%04d-%02d-%02d', $y, $month, $day) : '';
    }
    $ts = strtotime($v);
    return $ts ? date('Y-m-d', $ts) : '';
}

// Whether two first names refer to the same person ("John Paul" ~ "John", "Ma." ~ "Maria").
function evFirstNamesMatch(string $a, string $b): bool {
    $a = evNorm(preg_replace('/\bma\b\.?/i', 'maria', $a));
    $b = evNorm(preg_replace('/\bma\b\.?/i', 'maria', $b));
    if ($a === '' || $b === '') return false;
    if ($a === $b) return true;
    return strpos($a . ' ', $b . ' ') === 0 || strpos($b . ' ', $a . ' ') === 0;
}

// Middle names agree when either is missing, or their first letters match (CHED often gives only the initial).
function evMiddleMatches(string $fileMiddle, string $regMiddle): bool {
    $f = evNorm($fileMiddle);
    $r = evNorm($regMiddle);
    return $f === '' || $r === '' || $f[0] === $r[0];
}

// Officially enrolled per the Registrar's record (see REGISTRAR_ENROLLED_STATUSES).
function evRegistrarEnrollment(array $stu, string $activeSchoolYear): array {
    $status = trim((string)($stu['enrollment_status'] ?? ''));
    $statusOk = in_array(strtolower($status), array_map('strtolower', REGISTRAR_ENROLLED_STATUSES), true);
    if (!$statusOk) {
        return [false, 'Registrar status: ' . ($status !== '' ? $status : 'not enrolled')];
    }
    $sy = trim((string)($stu['school_year'] ?? ''));
    if (REGISTRAR_CHECK_SCHOOL_YEAR && $sy !== '' && $activeSchoolYear !== '' && $sy !== $activeSchoolYear) {
        return [false, "Last enrolled in $sy, not $activeSchoolYear"];
    }
    return [true, ''];
}

/*
 * Checks every student row of an uploaded CHED list. Returns ['summary' => ..., 'results' => ...,
 * 'verificationId' => ...]. With $markInSystem, enrolled students are marked enrolled in this system.
 */
function verifyEnrollmentList(PDO $pdo, PDO $reg, array $rows, string $fileName, string $checkedBy, bool $markInSystem): array {
    $header = evFindHeader($rows);
    if ($header === null) {
        throw new InvalidArgumentException('Couldn\'t find the column headers. The list needs a "Student ID" column, or name columns ("Last Name" and "First Name", or "Name").');
    }
    $cols = $header['cols'];
    $activeSY = getActiveSchoolYear($pdo);
    $T = REGISTRAR_STUDENTS_TABLE;

    $byId = $reg->prepare("SELECT * FROM $T WHERE student_id = ?");
    $byLast = $reg->prepare("SELECT * FROM $T WHERE LOWER(last_name) = LOWER(?)");
    $byLastLike = $reg->prepare("SELECT * FROM $T WHERE LOWER(REPLACE(last_name, ' ', '')) = LOWER(?)");

    $cell = fn(array $row, string $f) => isset($cols[$f]) ? trim((string)($row[$cols[$f]] ?? '')) : '';
    $cellFromRow = $cell;
    $results = [];
    $summary = ['rows' => 0, 'enrolled' => 0, 'not_enrolled' => 0, 'not_found' => 0, 'review' => 0, 'marked' => 0];

    foreach ($rows as $i => $row) {
        if ($i <= $header['index']) continue;
        if (count(array_filter($row, fn($v) => trim((string)$v) !== '')) === 0) continue;

        $sid = $cell($row, 'student_id');
        if (isset($cols['last_name']) || isset($cols['first_name'])) {
            [$last, $first, $middle] = [$cell($row, 'last_name'), $cell($row, 'first_name'), $cell($row, 'middle_name')];
        } else {
            [$last, $first, $middle] = evSplitFullName($cell($row, 'full_name'));
        }
        if ($sid === '' && $last === '' && $first === '') continue; // e.g. a totals/footer line
        $summary['rows']++;
        $nameInFile = trim(preg_replace('/\s+/', ' ', "$first $middle $last"));
        $birth = evDate($cell($row, 'birthdate'));
        $program = resolveProgramName($cell($row, 'program'));

        $match = null;
        $result = 'not_found';
        $remarks = '';

        // 1. By Student ID, when the list has one.
        if ($sid !== '') {
            $byId->execute([$sid]);
            $stu = $byId->fetch(PDO::FETCH_ASSOC);
            if ($stu) {
                $nameOk = ($last === '' && $first === '')
                    || (evNorm($stu['last_name']) === evNorm($last) && evFirstNamesMatch($stu['first_name'], $first));
                if ($nameOk) {
                    $match = $stu;
                } else {
                    $result = 'review';
                    $remarks = 'Student ID belongs to ' . trim($stu['first_name'] . ' ' . $stu['last_name']) . ' in the Registrar\'s records';
                }
            } else {
                $remarks = 'Student ID not in the Registrar\'s records';
            }
        }

        // 2. By name (no Student ID, or it wasn't found).
        if ($match === null && $result !== 'review' && $last !== '' && $first !== '') {
            $byLast->execute([$last]);
            $cands = $byLast->fetchAll(PDO::FETCH_ASSOC);
            if (!$cands) {
                $byLastLike->execute([str_replace(' ', '', $last)]); // "Delacruz" vs "Dela Cruz"
                $cands = $byLastLike->fetchAll(PDO::FETCH_ASSOC);
            }
            $cands = array_values(array_filter($cands, fn($c) => evFirstNamesMatch($c['first_name'], $first) && evMiddleMatches($middle, (string)$c['middle_name'])));
            // Narrow down same-name students by birthdate, then program.
            if (count($cands) > 1 && $birth !== '') {
                $narrow = array_values(array_filter($cands, fn($c) => (string)$c['birthdate'] === $birth));
                if ($narrow) $cands = $narrow;
            }
            if (count($cands) > 1 && $program !== null) {
                $narrow = array_values(array_filter($cands, fn($c) => resolveProgramName((string)$c['program']) === $program));
                if ($narrow) $cands = $narrow;
            }
            if (count($cands) === 1) {
                $match = $cands[0];
                if ($sid !== '') $remarks = 'Matched by name (the Student ID in the list isn\'t on record)';
                elseif ($birth !== '' && (string)$match['birthdate'] !== '' && (string)$match['birthdate'] !== $birth) {
                    $result = 'review';
                    $remarks = 'Name matches ' . $match['student_id'] . ' but the birthdate differs';
                    $match = null;
                }
            } elseif (count($cands) > 1) {
                $result = 'review';
                $remarks = count($cands) . ' students with this name: ' . implode(', ', array_map(fn($c) => $c['student_id'], $cands));
            }
        }

        if ($match !== null) {
            [$isEnrolled, $why] = evRegistrarEnrollment($match, $activeSY);
            $result = $isEnrolled ? 'enrolled' : 'not_enrolled';
            $remarks = trim($remarks . ($why !== '' ? ($remarks !== '' ? '; ' : '') . $why : ''));
        }
        $summary[$result]++;

        $results[] = [
            'row' => $i + 1,
            'rowData' => $row,
            'nameInFile' => $nameInFile,
            'idInFile' => $sid,
            'result' => $result,
            'resultLabel' => EV_RESULT_LABELS[$result],
            'studentId' => $match['student_id'] ?? '',
            'registrarName' => $match ? trim($match['first_name'] . ' ' . ($match['middle_name'] ?? '') . ' ' . $match['last_name']) : '',
            'program' => $match ? trim($match['program'] . (($match['major'] ?? '') !== '' ? ' · ' . $match['major'] : '')) : '',
            'yearLevel' => $match['year_level'] ?? '',
            'remarks' => $remarks,
            'markedInSystem' => false,
            // Personal details for the downloadable file: the Registrar's official record when the
            // student was found (so the file works in "Fetch from Registrar Database"), else the list's.
            'details' => $match
                ? [$match['last_name'], $match['first_name'], (string)($match['middle_name'] ?? ''), (string)($match['gender'] ?? ''), (string)($match['birthdate'] ?? '')]
                : [$last, $first, $middle, $cell($row, 'sex'), $birth !== '' ? $birth : $cell($row, 'birthdate')],
        ];
    }

    // Save the run and its results.
    $pdo->prepare("INSERT INTO enrollment_verifications (file_name, school_year, total_rows, enrolled_count, not_enrolled_count, not_found_count, review_count, checked_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
        ->execute([$fileName, $activeSY, $summary['rows'], $summary['enrolled'], $summary['not_enrolled'], $summary['not_found'], $summary['review'], $checkedBy]);
    $verificationId = (int)$pdo->lastInsertId();
    $source = 'CHED list "' . mb_substr($fileName, 0, 120) . '" (check #' . $verificationId . ')';

    $insertResult = $pdo->prepare("INSERT INTO enrollment_verification_results (verification_id, row_no, name_in_file, student_id, result, remarks) VALUES (?, ?, ?, ?, ?, ?)");
    $markEnrolled = $pdo->prepare("UPDATE applicants SET enrolled = 1, enrollment_verified = 1, enrollment_verified_at = CURRENT_TIMESTAMP, enrollment_verified_source = ?, updated_at = CURRENT_TIMESTAMP WHERE student_id = ?");
    $markNotEnrolled = $pdo->prepare("UPDATE applicants SET enrolled = 0, enrollment_verified = 0, enrollment_verified_at = CURRENT_TIMESTAMP, enrollment_verified_source = ?, updated_at = CURRENT_TIMESTAMP WHERE student_id = ?");
    foreach ($results as &$r) {
        $insertResult->execute([$verificationId, $r['row'], mb_substr($r['nameInFile'], 0, 255), $r['studentId'], $r['result'], mb_substr($r['remarks'], 0, 255)]);
        if ($markInSystem && $r['studentId'] !== '') {
            if ($r['result'] === 'enrolled') {
                $markEnrolled->execute([$source, $r['studentId']]);
                if ($markEnrolled->rowCount() > 0) { $r['markedInSystem'] = true; $summary['marked']++; }
            } elseif ($r['result'] === 'not_enrolled') {
                $markNotEnrolled->execute([$source . ': not enrolled', $r['studentId']]);
            }
        }
    }
    unset($r);

    // The downloadable result: Student ID first, then personal details, then the Registrar's
    // answer — one row per student, header in the first row, so the file can go straight into
    // "Fetch from Registrar Database" (which reads Student ID + Last/First Name).
    $out = [['Student ID', 'Last Name', 'First Name', 'Middle Name', 'Sex', 'Birthdate', 'Program', 'Year Level', 'Registrar Result', 'Remarks']];
    foreach ($results as $r) {
        $out[] = array_merge([$r['studentId']], $r['details'], [$r['program'] !== '' ? $r['program'] : $cellFromRow($r['rowData'], 'program'), $r['yearLevel'] !== '' ? $r['yearLevel'] : $cellFromRow($r['rowData'], 'year_level'), $r['resultLabel'], $r['remarks']]);
    }
    $resultName = 'enrollment_check_' . $verificationId . '_' . date('Ymd_His') . '.xlsx';
    writeXlsx(evStorageDir() . '/' . $resultName, $out, 'Enrollment Check');
    $pdo->prepare("UPDATE enrollment_verifications SET result_path = ?, marked_count = ? WHERE id = ?")->execute([$resultName, $summary['marked'], $verificationId]);

    foreach ($results as &$r) unset($r['rowData']);
    unset($r);
    return ['verificationId' => $verificationId, 'schoolYear' => $activeSY, 'summary' => $summary, 'results' => $results];
}
