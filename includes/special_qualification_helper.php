<?php
/*
 * The extra "what qualifies you" question asked on the Apply page for three scholarship
 * types: Talent-Based, Community Service or Leadership, and Other Types of Scholarship and
 * Discounts. Keyed by the program's scholarship TYPE, so every program filed under one of
 * these types (now or added later) asks the same question.
 *
 * The answer is only the applicant's declaration — it is never counted as proof of
 * eligibility. The registrar verifies it against the uploaded supporting documents.
 */

const SPECIAL_QUALIFICATION_OTHER = 'Other';

const SPECIAL_QUALIFICATION_CONFIG = [
    'talent' => [
        'label' => 'Special Field / Area of Involvement',
        'options' => [
            'Varsity / Athlete',
            'Cultural / Dance Troupe Member',
            'Band Member',
            'Anklung Member',
            'Choir Member',
            'Ukulele / Instrumental',
            SPECIAL_QUALIFICATION_OTHER,
        ],
    ],
    'community' => [
        'label' => 'Special Field / Area of Involvement',
        'options' => [
            'Hilltop Publication Editor',
            'Multimedia Staff',
            SPECIAL_QUALIFICATION_OTHER,
        ],
    ],
    'other_discount' => [
        'label' => 'Scholarship / Discount Qualification',
        'options' => [
            'Espina Family — up to 4th Generation',
            'Son/Daughter of Faculty and Staff',
            'Faculty and Staff',
            "UCCP Minister's Child",
            'UCCP Member',
            'Recruit',
            'Brother/Sister',
            'SOLRPISA / ACSCU / AUS Member School',
            SPECIAL_QUALIFICATION_OTHER,
        ],
    ],
];

// Which config (if any) a scholarship type uses. Matched loosely so small wording
// differences ("Other types of Scholarship and Discount" vs "...Discounts") still match.
function specialQualificationKey(string $scholarshipType): ?string {
    $t = strtolower(trim(preg_replace('/\s+/', ' ', $scholarshipType)));
    if ($t === '') return null;
    if (strpos($t, 'talent') !== false) return 'talent';
    if (strpos($t, 'community service') !== false || strpos($t, 'leadership scholarship') !== false) return 'community';
    if (strpos($t, 'other type') !== false && strpos($t, 'discount') !== false) return 'other_discount';
    return null;
}

function specialQualificationConfig(string $scholarshipType): ?array {
    $key = specialQualificationKey($scholarshipType);
    return $key !== null ? SPECIAL_QUALIFICATION_CONFIG[$key] : null;
}

// The saved answer as one readable line, e.g. "Varsity / Athlete" or "Other: Chess Team".
function formatSpecialQualification(?string $value, ?string $other): string {
    $value = trim((string)$value);
    if ($value === '') return '';
    if ($value === SPECIAL_QUALIFICATION_OTHER) {
        $other = trim((string)$other);
        return $other !== '' ? 'Other: ' . $other : 'Other';
    }
    return $value;
}
