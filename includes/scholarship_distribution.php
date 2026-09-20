<?php
/*
 * Scholarship Distribution: approved scholars per scholarship TYPE.
 *
 * The records table is the source of truth (a record is created/updated/removed
 * whenever a scholar is approved, edited or deleted), and the list of types is
 * read live from the Scholarships module — nothing here is hardcoded, so a new
 * type appears (with 0) the moment it is created.
 */

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
    foreach ($pdo->query("SELECT name FROM scholarship_types")->fetchAll(PDO::FETCH_COLUMN) as $n) {
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

    $items = [];
    foreach ($counts as $type => $scholars) {
        $n = count($scholars);
        if ($type === DISTRIBUTION_UNCLASSIFIED && $n === 0) continue;
        $items[] = ['type' => $type, 'count' => $n];
    }

    // Highest to lowest; ties alphabetical so the order is stable.
    usort($items, fn($a, $b) => $b['count'] <=> $a['count'] ?: strcasecmp($a['type'], $b['type']));

    $totalApproved = array_sum(array_column($items, 'count'));
    $totalTypes = count(array_filter($items, fn($i) => $i['type'] !== DISTRIBUTION_UNCLASSIFIED));
    $top = ($items && $items[0]['count'] > 0) ? $items[0] : null;

    return [
        'types' => $items,
        'totalApproved' => $totalApproved,
        'totalTypes' => $totalTypes,
        'mostPopular' => $top,
        'generatedAt' => date('c'),
    ];
}
