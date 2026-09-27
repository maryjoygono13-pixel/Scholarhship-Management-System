<?php
/*
 * Scholarship types are always STORED as their short acronym when they have one, so the same
 * scholarship is never saved two ways ("CMSP" and "CMSP (CHED Merit Scholarship Program)") and
 * filters, records and counts don't split it in two.
 *
 *   "CMSP (CHED Merit Scholarship Program)"  -> "CMSP"     (acronym + full name typed together)
 *   "CHED Merit Scholarship Program"         -> "CMSP"     (full name alone, matched to the sub-type)
 *   "cmsp"                                   -> "CMSP"     (any capitalisation)
 *   "MERIT-BASED Academic Scholarship"       -> unchanged  (a type with no acronym keeps its name)
 *
 * The acronym is the leading ALL-CAPS word of a sub-type written "ACRONYM (Full name)", the
 * convention used for the CHED programs on the Scholarships page.
 */

// Acronym for a value already written "ACRONYM (Full name)", or null if it isn't in that form.
function scholarshipTypeAcronymOf(string $value): ?string {
    if (preg_match('/^([A-Z0-9&\/\-]{2,})\s*\(.*\)\s*$/', trim($value), $m)) {
        return $m[1];
    }
    return null;
}

function normalizeScholarshipType(PDO $pdo, string $raw): string {
    $v = trim(preg_replace('/\s+/', ' ', $raw));
    if ($v === '') return $v;

    // "CMSP (CHED Merit Scholarship Program)" -> "CMSP"
    $acronym = scholarshipTypeAcronymOf($v);
    if ($acronym !== null) return $acronym;

    // "CMSP" in another case, or the full name on its own, -> the acronym of the matching sub-type.
    static $known = null;
    if ($known === null) {
        $known = [];
        $names = $pdo->query("SELECT name FROM scholarship_subtypes")->fetchAll(PDO::FETCH_COLUMN);
        $names = array_merge($names, $pdo->query("SELECT subtype FROM scholarships WHERE TRIM(COALESCE(subtype, '')) != ''")->fetchAll(PDO::FETCH_COLUMN));
        foreach ($names as $name) {
            $acr = scholarshipTypeAcronymOf((string)$name);
            if ($acr === null) continue;
            $known[strtolower($acr)] = $acr;
            if (preg_match('/\((.*)\)\s*$/', (string)$name, $m)) {
                $known[strtolower(trim($m[1]))] = $acr;   // the full description alone
            }
        }
    }
    return $known[strtolower($v)] ?? $v;
}

/*
 * How many applicants currently hold a slot in this scholarship — counted live from the
 * `applicants` table (matched the same way student_apply.php stores it: the program's
 * subtype, or its name when it has none, normalized to the same acronym/type key). This is
 * the source of truth for slot capacity, not a running decrement counter: a counter drifts
 * out of sync the moment an applicant is deleted/rejected without an equal-and-opposite
 * increment somewhere, which is exactly what produced stale "0 slots available" programs
 * that had zero real applicants.
 */
function scholarshipTakenCount(PDO $pdo, string $subtype, string $name): int {
    $type = normalizeScholarshipType($pdo, $subtype !== '' ? $subtype : $name);
    if ($type === '') return 0;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM applicants WHERE LOWER(TRIM(scholarship_type)) = LOWER(TRIM(?))");
    $stmt->execute([$type]);
    return (int)$stmt->fetchColumn();
}

// The live slots_available for one `scholarships` row (0 for unlimited — meaningless there).
function scholarshipSlotsAvailable(PDO $pdo, array $scholarship): int {
    if (!empty($scholarship['unlimited_slots'])) return 0;
    $taken = scholarshipTakenCount($pdo, (string)($scholarship['subtype'] ?? ''), (string)($scholarship['name'] ?? ''));
    return max(0, (int)$scholarship['slots'] - $taken);
}
