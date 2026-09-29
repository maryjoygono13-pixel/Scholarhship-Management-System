<?php
/*
 * The registrar-configurable eligibility layer for a scholarship: criteria, required
 * documents, benefits and renewal rules (set up on the Scholarships page's Add/Edit wizard).
 * Every scholarship — including the 4 legacy CHED programs migrated in database/init_db.php —
 * is driven from these tables; nothing here is hardcoded to one specific program, so a new
 * scholarship category never needs a source-code change to define its own eligibility rules.
 */

require_once __DIR__ . '/scholarship_type_helper.php';
require_once __DIR__ . '/programs_helper.php';
require_once __DIR__ . '/renewal_helper.php';

// Every selectable criterion type on the Add Scholarship wizard, and its display label.
const CRITERION_TYPES = [
    'bonafide_cm_student' => 'Bonafide CM Student',
    'filipino_citizen' => 'Filipino Citizen',
    'enrollment_status' => 'Enrollment Status',
    'regular_student' => 'Regular Student',
    'year_level' => 'Year Level',
    'program' => 'Program / Course',
    'gwa' => 'GWA Requirement',
    'cwra' => 'CWRA Requirement',
    'minimum_grade' => 'Minimum Grade',
    'no_failing_grades' => 'No Failing Grades',
    'no_disciplinary_record' => 'No Disciplinary Record',
    'poverty_threshold' => 'Poverty Threshold',
    'leadership_experience' => 'Leadership Experience',
    'community_involvement' => 'Community Involvement',
    'talent_performance' => 'Talent / Performance',
    'awards' => 'Awards',
    'competition_participation' => 'Competition Participation',
    'residency' => 'Residency',
    'existing_scholarship_restriction' => 'Existing Scholarship Restriction',
    'endorsement' => 'Endorsement',
    'custom' => 'Custom Criterion',
];

// Criteria the system can check automatically from data already on file. Everything else
// falls back to a registrar's manual Pass / Fail / Pending mark (applicant_criteria_reviews).
const AUTO_CHECKABLE_CRITERIA = [
    'gwa', 'cwra', 'minimum_grade', 'no_failing_grades',
    'enrollment_status', 'regular_student', 'year_level', 'program',
    'existing_scholarship_restriction', 'poverty_threshold',
];

const DOCUMENT_TYPES = [
    'transcript' => 'Official Transcript of Records (TOR)',
    'coe' => 'Certificate of Enrollment (COE)',
    'good_moral' => 'Certificate of Good Moral Character',
    'certificate_of_indigency' => 'Certificate of Indigency',
    'barangay_certificate' => 'Barangay Certificate / Residency',
    'income_tax_return' => 'Income Tax Return (ITR)',
    'recommendation_letter' => 'Recommendation Letter',
    'endorsement_letter' => 'Endorsement Letter',
    'valid_id' => 'Valid ID',
    'birth_certificate' => 'Birth Certificate (PSA)',
    'certificate_of_award' => 'Certificate of Award / Recognition',
    'proof_of_involvement' => 'Proof of Involvement / Membership',
    'medical_certificate' => 'Medical Certificate',
    'other' => 'Other Document',
];

const BENEFIT_TYPES = [
    'tuition_discount' => 'Tuition Discount (%)',
    'stipend' => 'Monthly Stipend',
    'training_support' => 'Training Support',
    'travel_assistance' => 'Travel Assistance',
    'uniform' => 'Uniform Allowance',
    'housing' => 'Housing Assistance',
    'mentorship' => 'Mentorship Program',
    'other' => 'Other Benefit',
];

const APPLY_SCOPES = [
    'tuition_only' => 'Tuition Fees Only',
    'tuition_plus_fees' => 'Tuition + Miscellaneous Fees',
    'custom' => 'Custom Scope',
];

/*
 * Maps an applicant's stored `scholarship_type` (a normalized acronym or full type name) back
 * to the specific `scholarships.id` row it was applied under, so its own criteria/documents/
 * benefits/renewal rules can be looked up. Same subtype-then-name matching cascade
 * resolveGwaRequirement() already uses (includes/gwa_helper.php), just resolving to the row's
 * id instead of just its GWA.
 */
function resolveScholarshipIdForType(PDO $pdo, string $scholarshipType): ?int {
    $name = trim($scholarshipType);
    if ($name === '') return null;

    $stmt = $pdo->prepare("SELECT id FROM scholarships WHERE LOWER(subtype) = LOWER(?) ORDER BY id DESC LIMIT 1");
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if ($id !== false) return (int)$id;

    $stmt = $pdo->prepare("SELECT id FROM scholarships WHERE LOWER(subtype) LIKE LOWER(?) ORDER BY id DESC LIMIT 1");
    $stmt->execute([$name . ' (%']);
    $id = $stmt->fetchColumn();
    if ($id !== false) return (int)$id;

    $stmt = $pdo->prepare("SELECT id FROM scholarships WHERE LOWER(name) = LOWER(?) OR LOWER(type) = LOWER(?) ORDER BY id DESC LIMIT 1");
    $stmt->execute([$name, $name]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int)$id : null;
}

function getScholarshipCriteria(PDO $pdo, int $scholarshipId): array {
    $stmt = $pdo->prepare("SELECT * FROM scholarship_criteria WHERE scholarship_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$scholarshipId]);
    // Skip criteria whose type was retired (e.g. Family Income) so they no longer show or get checked.
    return array_values(array_filter($stmt->fetchAll(PDO::FETCH_ASSOC), fn($c) => isset(CRITERION_TYPES[$c['criterion_type']])));
}

// Whether applicants to this scholarship must declare their family income (it has a Poverty Threshold).
function scholarshipNeedsFamilyIncome(PDO $pdo, int $scholarshipId): bool {
    foreach (getScholarshipCriteria($pdo, $scholarshipId) as $c) {
        if ($c['criterion_type'] === 'poverty_threshold') return true;
    }
    return false;
}

function getScholarshipDocuments(PDO $pdo, int $scholarshipId): array {
    $stmt = $pdo->prepare("SELECT * FROM scholarship_documents WHERE scholarship_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$scholarshipId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getScholarshipBenefits(PDO $pdo, int $scholarshipId): array {
    $stmt = $pdo->prepare("SELECT * FROM scholarship_benefits WHERE scholarship_id = ? ORDER BY sort_order ASC, id ASC");
    $stmt->execute([$scholarshipId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getScholarshipRenewalRules(PDO $pdo, int $scholarshipId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM scholarship_renewal_rules WHERE scholarship_id = ? LIMIT 1");
    $stmt->execute([$scholarshipId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

// The registrar's latest manual mark for this applicant+criterion, or null when none exists yet.
function getManualReview(PDO $pdo, int $applicantId, int $criterionId): ?array {
    if ($applicantId <= 0 || $criterionId <= 0) return null;
    $stmt = $pdo->prepare("SELECT * FROM applicant_criteria_reviews WHERE applicant_id = ? AND scholarship_criteria_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$applicantId, $criterionId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function compareOperator(string $operator, float $actual, float $value, ?float $value2 = null): bool {
    switch ($operator) {
        case 'gt': return $actual > $value;
        case 'gte': return $actual >= $value;
        case 'lt': return $actual < $value;
        case 'eq': return abs($actual - $value) < 0.0001;
        case 'between': return $value2 !== null && $actual >= min($value, $value2) && $actual <= max($value, $value2);
        case 'lte':
        default: return $actual <= $value;
    }
}

/*
 * Evaluates one criterion against one applicant.
 *   status: 'pass' | 'fail' | 'pending' — 'pending' means either no GWA on file yet
 *           (auto-checkable) or no registrar mark yet (manual).
 *   autoChecked: whether the system, not the registrar, produced this status.
 *   actualValue: what was compared, for display (null when not applicable).
 */
function evaluateCriterion(PDO $pdo, array $criterion, array $applicant): array {
    $type = $criterion['criterion_type'];
    $operator = $criterion['operator'] ?: 'lte';
    $value = $criterion['value'];
    $value2 = $criterion['value2'] ?? null;

    if (in_array($type, AUTO_CHECKABLE_CRITERIA, true)) {
        switch ($type) {
            case 'gwa':
            case 'cwra':
            case 'minimum_grade': {
                $gwa = (float)($applicant['gwa'] ?? 0);
                if ($gwa <= 0) {
                    return ['status' => 'pending', 'autoChecked' => true, 'actualValue' => null];
                }
                $pass = compareOperator($operator, $gwa, (float)$value, $value2 !== null && $value2 !== '' ? (float)$value2 : null);
                return ['status' => $pass ? 'pass' : 'fail', 'autoChecked' => true, 'actualValue' => $gwa];
            }
            case 'no_failing_grades': {
                // Zero failing grades is only meaningful once grades actually exist — the
                // same "gwa <= 0 means nothing on file yet" signal the GWA case above uses.
                // Without it, a brand-new applicant with no grades imported yet would read as
                // a clean pass by coincidence, not because their performance was checked.
                if ((float)($applicant['gwa'] ?? 0) <= 0) {
                    return [
                        'status' => 'pending',
                        'autoChecked' => true,
                        'actualValue' => null,
                        'remarks' => 'Grades not uploaded. Unable to verify academic performance.',
                    ];
                }
                $failing = (int)($applicant['failing_grades'] ?? 0);
                return ['status' => $failing === 0 ? 'pass' : 'fail', 'autoChecked' => true, 'actualValue' => $failing];
            }
            case 'enrollment_status':
            case 'regular_student': {
                $enrolled = !empty($applicant['enrolled']);
                return ['status' => $enrolled ? 'pass' : 'fail', 'autoChecked' => true, 'actualValue' => $enrolled ? 1 : 0];
            }
            case 'year_level': {
                $actual = trim((string)($applicant['year_level'] ?? ''));
                if ($actual === '') return ['status' => 'pending', 'autoChecked' => true, 'actualValue' => null];
                $pass = $value !== '' && strtolower($actual) === strtolower(trim((string)$value));
                return ['status' => $pass ? 'pass' : 'fail', 'autoChecked' => true, 'actualValue' => $actual];
            }
            case 'program': {
                $canonical = resolveProgramName((string)($applicant['program'] ?? ''));
                if ($canonical === null) return ['status' => 'pending', 'autoChecked' => true, 'actualValue' => (string)($applicant['program'] ?? '')];
                $pass = $value !== '' && strtolower($canonical) === strtolower(trim((string)$value));
                return ['status' => $pass ? 'pass' : 'fail', 'autoChecked' => true, 'actualValue' => $canonical];
            }
            case 'poverty_threshold': {
                // The registrar sets the threshold; the applicant only declares their income.
                // Eligible when the monthly family income is at or below the threshold.
                $income = $applicant['family_income'] ?? null;
                if ($income === null || $income === '') {
                    return ['status' => 'pending', 'autoChecked' => true, 'actualValue' => null, 'remarks' => 'No family income declared.'];
                }
                $threshold = (float)preg_replace('/[^0-9.]/', '', (string)$value);
                if ($threshold <= 0) {
                    return ['status' => 'pending', 'autoChecked' => true, 'actualValue' => (float)$income, 'remarks' => 'Poverty Threshold amount is not set for this scholarship.'];
                }
                $pass = (float)$income <= $threshold;
                return [
                    'status' => $pass ? 'pass' : 'fail',
                    'autoChecked' => true,
                    'actualValue' => (float)$income,
                    'remarks' => 'Family income ₱' . number_format((float)$income, 2) . ($pass ? ' is within' : ' is above') . ' the ₱' . number_format($threshold, 2) . ' threshold.',
                ];
            }
            case 'existing_scholarship_restriction': {
                $blocked = findScholarshipTermination($pdo, (string)($applicant['student_id'] ?? ''), (string)($applicant['scholarship_type'] ?? ''));
                return ['status' => $blocked ? 'fail' : 'pass', 'autoChecked' => true, 'actualValue' => $blocked ? 'Terminated' : 'None on record'];
            }
        }
    }

    // Not auto-checkable: fall back to the registrar's manual mark, if any.
    $review = getManualReview($pdo, (int)($applicant['id'] ?? 0), (int)$criterion['id']);
    if ($review) {
        return ['status' => $review['status'], 'autoChecked' => false, 'actualValue' => null, 'remarks' => $review['remarks'] ?? ''];
    }
    return ['status' => 'pending', 'autoChecked' => false, 'actualValue' => null, 'remarks' => ''];
}

// Full per-applicant criteria list, each entry the criterion row plus its evaluated result.
function evaluateApplicantCriteria(PDO $pdo, array $applicant): array {
    $scholarshipId = resolveScholarshipIdForType($pdo, (string)($applicant['scholarship_type'] ?? ''));
    if (!$scholarshipId) return [];

    $out = [];
    foreach (getScholarshipCriteria($pdo, $scholarshipId) as $c) {
        $result = evaluateCriterion($pdo, $c, $applicant);
        $out[] = [
            'id' => (int)$c['id'],
            'type' => $c['criterion_type'],
            'label' => $c['label'] !== '' ? $c['label'] : (CRITERION_TYPES[$c['criterion_type']] ?? $c['criterion_type']),
            'operator' => $c['operator'],
            'value' => $c['value'],
            'value2' => $c['value2'],
            'required' => (bool)$c['required'],
            'status' => $result['status'],
            'autoChecked' => $result['autoChecked'],
            'actualValue' => $result['actualValue'],
            'remarks' => $result['remarks'] ?? '',
        ];
    }
    return $out;
}

// Full per-applicant document list: every document this scholarship requires, plus whether
// (and what) the applicant actually submitted.
function evaluateApplicantDocuments(PDO $pdo, array $applicant): array {
    $scholarshipId = resolveScholarshipIdForType($pdo, (string)($applicant['scholarship_type'] ?? ''));
    if (!$scholarshipId) return [];

    $applicantId = (int)($applicant['id'] ?? 0);
    $uploaded = [];
    if ($applicantId > 0) {
        $stmt = $pdo->prepare("SELECT document_type, file_path FROM applicant_documents WHERE applicant_id = ? ORDER BY id DESC");
        $stmt->execute([$applicantId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!isset($uploaded[$row['document_type']])) $uploaded[$row['document_type']] = $row['file_path'];
        }
    }
    // Fallback for applicants that predate applicant_documents (uploaded only via the legacy columns).
    foreach (['transcript' => 'transcript_file', 'coe' => 'coe_file', 'good_moral' => 'good_moral_file'] as $docType => $col) {
        if (empty($uploaded[$docType]) && !empty($applicant[$col])) {
            $uploaded[$docType] = $applicant[$col];
        }
    }

    $out = [];
    foreach (getScholarshipDocuments($pdo, $scholarshipId) as $d) {
        $filename = $uploaded[$d['document_type']] ?? '';
        $out[] = [
            'id' => (int)$d['id'],
            'type' => $d['document_type'],
            'label' => DOCUMENT_TYPES[$d['document_type']] ?? $d['document_type'],
            'description' => $d['description'],
            'required' => (bool)$d['required'],
            'filename' => $filename,
            'submitted' => $filename !== '',
        ];
    }
    return $out;
}
