<?php
/*
 * The programs the school actually offers. This must match the Program list on the
 * Applicants form (pages/applicants.php); imports use it so a file can't bring in a
 * program that doesn't exist (e.g. "BS Criminology"), which would have no curriculum,
 * no department and nowhere to go on the Scholar Map.
 *
 * canonical program name => other ways it is commonly written (all compared case-insensitively).
 * Department-style short names ("Nursing") are accepted only where they point to one program.
 */
const KNOWN_PROGRAMS = [
    'BS Accountancy' => ['bsa', 'accountancy', 'bachelor of science in accountancy'],
    'BS Business Administration' => ['bsba', 'business administration', 'bachelor of science in business administration'],
    'BS Information Technology' => ['bsit', 'information technology', 'bachelor of science in information technology'],
    'BS Nursing' => ['bsn', 'nursing', 'bachelor of science in nursing'],
    'BA Political Science' => ['bapolsci', 'political science', 'bachelor of arts in political science', 'ab political science'],
    'Bachelor of Elementary Education' => ['beed', 'elementary education', 'bs elementary education'],
    'Bachelor of Secondary Education' => ['bsed', 'secondary education', 'bs secondary education'],
    'BIT Food Preparation and Services Technology' => ['bit fpst', 'bit-fpst', 'fpst', 'food preparation and services technology', 'bachelor in industrial technology major in food preparation and services technology', 'food service'],
];

// How each program is named in the Departments dropdowns, in the order they're listed.
const PROGRAM_DISPLAY_NAMES = [
    'BS Nursing' => 'BS Nursing',
    'BS Information Technology' => 'BS Information Technology',
    'BS Accountancy' => 'BS Accountancy',
    'BS Business Administration' => 'BS Business Administration',
    'BA Political Science' => 'BA PolSci',
    'Bachelor of Elementary Education' => 'B Elementary Education',
    'Bachelor of Secondary Education' => 'B Secondary Education',
    'BIT Food Preparation and Services Technology' => 'BIT Food Preparation and Services Technology',
];

// How each program is written as an acronym (the Program filters on Records and Renewal & Retention).
const PROGRAM_ACRONYMS = [
    'BS Accountancy' => 'BSA',
    'BS Business Administration' => 'BSBA',
    'BS Information Technology' => 'BSIT',
    'BS Nursing' => 'BSN',
    'BA Political Science' => 'BAPolSci',
    'Bachelor of Elementary Education' => 'BEEd',
    'Bachelor of Secondary Education' => 'BSEd',
    'BIT Food Preparation and Services Technology' => 'BIT-FPST',
];

// "BS Nursing · 2nd Year", "Bachelor of Science in Nursing (BSN)", "  bs   nursing " -> comparable form
function normalizeProgramKey(string $raw): string {
    $v = preg_replace('/\s*·.*$/u', '', $raw);          // drop a trailing " · 2nd Year"
    $v = preg_replace('/\s*\([^)]*\)\s*$/', '', $v);    // drop a trailing "(BSN)"
    $v = preg_replace('/\s+/', ' ', trim($v));
    return strtolower($v);
}

/*
 * The acronym of whatever program was written ("BS Nursing · 2nd Year" -> "BSN"). Blank when it
 * isn't one of the programs the school offers (old sample data such as "BS Criminology").
 */
function programAcronym(?string $raw): string {
    $raw = trim((string)$raw);
    if ($raw === '') return '';
    $canonical = resolveProgramName($raw);
    return $canonical !== null ? (PROGRAM_ACRONYMS[$canonical] ?? '') : '';
}

// The canonical program name for whatever was written, or null when it isn't a real program.
function resolveProgramName(string $raw): ?string {
    $key = normalizeProgramKey($raw);
    if ($key === '') return null;

    foreach (KNOWN_PROGRAMS as $canonical => $aliases) {
        if ($key === strtolower($canonical) || in_array($key, $aliases, true)) {
            return $canonical;
        }
    }
    return null;
}

/*
 * Programs that must have a Major (as on the Applicants form), and the majors that exist.
 * A student's subject list is looked up by program + major, so a missing major means
 * "No curriculum reference is available".
 */
const PROGRAM_MAJORS = [
    'BS Business Administration' => ['Human Resource Development Management (HRDM)'],
    'Bachelor of Secondary Education' => ['English'],
];

/*
 * The major to store for this program. Programs without majors get ''. For programs that
 * have them, a recognised major (or its short form, e.g. "HRDM") is used; when the file gives
 * none and the program has just one major, that one is filled in.
 */
function resolveMajor(string $canonicalProgram, string $raw = ''): string {
    $majors = PROGRAM_MAJORS[$canonicalProgram] ?? [];
    if (empty($majors)) return '';

    $key = strtolower(trim(preg_replace('/\s+/', ' ', $raw)));
    if ($key !== '') {
        foreach ($majors as $m) {
            $short = strtolower(preg_replace('/^.*\(([^)]+)\)\s*$/', '$1', $m));
            if ($key === strtolower($m) || $key === $short) return $m;
        }
    }
    return count($majors) === 1 ? $majors[0] : '';
}
