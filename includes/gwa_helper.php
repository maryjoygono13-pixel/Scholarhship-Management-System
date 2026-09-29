<?php
/*
 * Resolves the GWA an applicant/scholar must meet, from the scholarship
 * Type/Sub-type they belong to (set on the Scholarships page). Looked up
 * live, so editing a sub-type's GWA there applies everywhere immediately.
 */

const DEFAULT_GWA_REQUIREMENT = 1.75;

// Returns the matching requirement, or null when nothing in the taxonomy matches.
function findGwaRequirement(PDO $pdo, string $scholarshipType): ?float {
    $name = trim($scholarshipType);
    if ($name === '') return null;

    // 1. Exact sub-type name, e.g. "CMSP (CHED Merit Scholarship Program)".
    $stmt = $pdo->prepare("SELECT gwa_requirement FROM scholarship_subtypes WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $stmt->execute([$name]);
    $gwa = $stmt->fetchColumn();
    if ($gwa !== false) return (float)$gwa;

    // 2. Legacy short form, e.g. just "CMSP" -> "CMSP (...)".
    $stmt = $pdo->prepare("SELECT gwa_requirement FROM scholarship_subtypes WHERE LOWER(name) LIKE LOWER(?) LIMIT 1");
    $stmt->execute([$name . ' (%']);
    $gwa = $stmt->fetchColumn();
    if ($gwa !== false) return (float)$gwa;

    // 3. A scholarship program added on the Scholarships page under this name/type.
    $stmt = $pdo->prepare("SELECT gwa_requirement FROM scholarships WHERE gwa_requirement > 0 AND (LOWER(name) = LOWER(?) OR LOWER(subtype) = LOWER(?) OR LOWER(type) = LOWER(?)) ORDER BY id DESC LIMIT 1");
    $stmt->execute([$name, $name, $name]);
    $gwa = $stmt->fetchColumn();
    if ($gwa !== false) return (float)$gwa;

    return null;
}

function resolveGwaRequirement(PDO $pdo, string $scholarshipType, ?float $fallback = null): float {
    return findGwaRequirement($pdo, $scholarshipType) ?? ($fallback && $fallback > 0 ? $fallback : DEFAULT_GWA_REQUIREMENT);
}

/*
 * The GWA a scholar must keep to be RENEWED — a scholarship's `scholarship_renewal_rules.min_gwa`
 * overrides its normal requirement when set (a program can ask for a stricter GWA to keep the
 * grant than it did to first receive it); otherwise this is identical to resolveGwaRequirement().
 */
function resolveRenewalGwaRequirement(PDO $pdo, string $scholarshipType, ?float $fallback = null): float {
    if (function_exists('resolveScholarshipIdForType') && function_exists('getScholarshipRenewalRules')) {
        $scholarshipId = resolveScholarshipIdForType($pdo, $scholarshipType);
        if ($scholarshipId) {
            $rules = getScholarshipRenewalRules($pdo, $scholarshipId);
            if ($rules && $rules['min_gwa'] !== null && (float)$rules['min_gwa'] > 0) {
                return (float)$rules['min_gwa'];
            }
        }
    }
    return resolveGwaRequirement($pdo, $scholarshipType, $fallback);
}

// A requirement of 0 means the scholarship has no GWA requirement, so any GWA meets it.
function gwaMeetsRequirement(float $gwa, float $required): bool {
    return $required <= 0 || $gwa <= $required;
}

const STATUS_NON_COMPLIANT = 'non-compliant';

/*
 * "Non-compliant" is an Evaluation-only view: an applicant still being processed whose GWA (once
 * grades are on file) is above what their scholarship requires. It is worked out on the fly and
 * never saved as the applicant's status, so other pages (Applicants, dashboard) keep the real one.
 */
function isGwaRequirementUnmet(PDO $pdo, array $applicant): bool {
    $gwa = (float)($applicant['gwa'] ?? 0);
    if ($gwa <= 0) return false;
    $required = resolveGwaRequirement($pdo, (string)($applicant['scholarship_type'] ?? ''), (float)($applicant['gwa_req'] ?? 0));
    return !gwaMeetsRequirement($gwa, $required);
}
