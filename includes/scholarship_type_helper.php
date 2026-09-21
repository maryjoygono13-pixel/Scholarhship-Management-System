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
