<?php
/*
 * Scholarship Distribution: approved scholars per scholarship TYPE.
 *
 * Two sources count toward a type:
 *   - approved records (a record is created/updated/removed whenever a scholar is approved,
 *     edited or deleted), and
 *   - the Scholars list, for scholarships that add students automatically (the MERIT-BASED
 *     Academic Scholarship): a scholar counts while their GWA still meets its requirement.
 * The list of types is read live from the Scholarships module — nothing here is hardcoded,
 * so a new type appears (with 0) the moment it is created.
 */

require_once __DIR__ . '/grades_helper.php';
require_once __DIR__ . '/gwa_helper.php';

const DISTRIBUTION_UNCLASSIFIED = 'Unclassified';

// Maps a free-text records.scholarship_type ("CMSP", "TES (Tertiary ...)", a
// type name...) onto the canonical scholarship type it belongs to.
function buildScholarshipTypeResolver(PDO $pdo): callable {
    $typeNames = [];   // lower => canonical type name
    $subtypes = [];    // lower sub-type name => type name
    $programs = [];    // lower program name/code/subtype => type name

    foreach ($pdo->query("SELECT name FROM scholarship_types")->fetchAll(PDO::FETCH_COLUMN) as $n) {
        $typeNames[mb_strtolower(trim($n))] = trim($n);
    }
    foreach ($pdo->query("SELECT st.name AS sub, t.name AS type FROM scholarship_subtypes st JOIN scholarship_types t ON t.id = st.type_id")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $subtypes[mb_strtolower(trim($r['sub']))] = trim($r['type']);
    }
    foreach ($pdo->query("SELECT name, code, type, subtype FROM scholarships WHERE TRIM(COALESCE(type, '')) != ''")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $type = trim($r['type']);
        foreach (['name', 'code', 'subtype'] as $col) {
            $key = mb_strtolower(trim((string)$r[$col]));
            if ($key !== '' && !isset($programs[$key])) $programs[$key] = $type;
        }
        // A type only used by a program (not in scholarship_types) is still a type.
        $typeNames[mb_strtolower($type)] = $typeNames[mb_strtolower($type)] ?? $type;
    }

    return function (string $raw) use ($typeNames, $subtypes, $programs): string {
        $key = mb_strtolower(trim($raw));
        if ($key === '') return DISTRIBUTION_UNCLASSIFIED;

        if (isset($typeNames[$key])) return $typeNames[$key];
        if (isset($subtypes[$key])) return $subtypes[$key];
        if (isset($programs[$key])) return $programs[$key];

        // Legacy short form: "CMSP" -> "CMSP (CHED Merit Scholarship Program)".
        foreach ($subtypes as $subName => $typeName) {
            if (strpos($subName, $key . ' (') === 0) return $typeName;
        }
        // "TES (Tertiary ...)" written in full but stored under just its acronym.
        if (preg_match('/^([^(]+)\(/', $key, $m)) {
            $short = trim($m[1]);
            foreach ($subtypes as $subName => $typeName) {
                if (strpos($subName, $short . ' (') === 0) return $typeName;
            }
        }
        return DISTRIBUTION_UNCLASSIFIED;
    };
}

function getScholarshipDistribution(PDO $pdo): array {
    // Every type currently defined in the Scholarships module (plus any type
    // that only exists on a program) starts at 0.
    $counts = [];
    // Read in the Scholarships module's own order, so every type keeps the same place in the chart.
    foreach ($pdo->query("SELECT name FROM scholarship_types ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_COLUMN) as $n) {
        $counts[trim($n)] = [];
    }
    foreach ($pdo->query("SELECT DISTINCT type FROM scholarships WHERE TRIM(COALESCE(type, '')) != ''")->fetchAll(PDO::FETCH_COLUMN) as $n) {
        $counts[trim($n)] = $counts[trim($n)] ?? [];
    }

    $resolve = buildScholarshipTypeResolver($pdo);

    // Approved records only. Each scholar counts once per type, however many
    // semesters they were approved for.
    $rows = $pdo->query("SELECT id, student_id, scholarship_type FROM records WHERE LOWER(TRIM(status)) = 'approved'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $type = $resolve((string)$r['scholarship_type']);
        $scholar = trim((string)$r['student_id']) !== '' ? 's:' . trim($r['student_id']) : 'r:' . $r['id'];
        $counts[$type][$scholar] = true;
    }

    // Scholars on the Scholars list who hold a scholarship (e.g. MERIT-BASED, added automatically).
    // They count while their newest graded semester still meets that scholarship's requirement.
    $scholarRows = $pdo->query("SELECT student_id, gwa, scholarship_type FROM scholars WHERE TRIM(COALESCE(scholarship_type, '')) != ''")->fetchAll(PDO::FETCH_ASSOC);
    if ($scholarRows) {
        $gradeStats = getSemesterGradeStats($pdo, array_column($scholarRows, 'student_id'));
        foreach ($scholarRows as $sc) {
            $sid = trim((string)$sc['student_id']);
            $gwa = (float)$sc['gwa'];   // stored figure, unless imported grades say otherwise
            foreach (['Summer Term', '2nd Semester', '1st Semester'] as $sem) {
                if (isset($gradeStats[$sid][$sem])) { $gwa = $gradeStats[$sid][$sem]['gwa']; break; }
            }
            if ($gwa > resolveGwaRequirement($pdo, (string)$sc['scholarship_type'])) continue;

            $type = $resolve((string)$sc['scholarship_type']);
            $counts[$type]['s:' . $sid] = true;   // same key as a record, so nobody is counted twice per type
        }
    }

    $items = [];
    foreach ($counts as $type => $scholars) {
        $n = count($scholars);
        if ($type === DISTRIBUTION_UNCLASSIFIED && $n === 0) continue;
        $items[] = ['type' => $type, 'count' => $n];
    }

    // No sorting by count: each type stays where it belongs (the Scholarships module's order), so the
    // chart keeps the same layout as the numbers change. Only "Unclassified" is always last.
    usort($items, fn($a, $b) => ($a['type'] === DISTRIBUTION_UNCLASSIFIED) <=> ($b['type'] === DISTRIBUTION_UNCLASSIFIED));

    $totalApproved = array_sum(array_column($items, 'count'));
    $totalTypes = count(array_filter($items, fn($i) => $i['type'] !== DISTRIBUTION_UNCLASSIFIED));
    // Most populated type = highest count (the earlier one in the fixed order wins a tie).
    $top = null;
    foreach ($items as $item) {
        if ($item['count'] > 0 && ($top === null || $item['count'] > $top['count'])) {
            $top = $item;
        }
    }

    return [
        'types' => $items,
        'totalApproved' => $totalApproved,
        'totalTypes' => $totalTypes,
        'mostPopular' => $top,
        'generatedAt' => date('c'),
    ];
}
