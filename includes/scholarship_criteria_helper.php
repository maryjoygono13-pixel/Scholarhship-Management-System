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
    // Need-Based programs: ONE file that is either document (see NEED_BASED_REQUIRED_DOCUMENTS).
    'itr_or_indigency' => 'Income Tax Return (ITR) or Certificate of Indigency',
    'academic_records' => 'Academic Records (Copy of Grades)',
    // Community Service or Leadership programs (see COMMUNITY_SERVICE_REQUIRED_DOCUMENTS).
    'leadership_record' => 'Documented Record of Leadership Role or Community Involvement',
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

// Documents every applicant must submit, whatever the program — on top of the program's own
// list. The Certificate of Enrollment proves the applicant is a bonafide CM student.
const UNIVERSAL_REQUIRED_DOCUMENTS = [
    'coe' => 'Proof that you are a bonafide CM student.',
];

// Every NEED-BASED program also requires these. An ITR *or* a Certificate of Indigency is
// enough, so the two are asked for as one upload instead of two separate required files.
const NEED_BASED_REQUIRED_DOCUMENTS = [
    'academic_records' => 'Copy of your grades / academic records.',
    'itr_or_indigency' => 'Upload EITHER your family\'s Income Tax Return OR a Certificate of Indigency.',
];
// Program-level types folded into the combined "ITR or Certificate of Indigency" upload.
const ITR_OR_INDIGENCY_TYPES = ['income_tax_return', 'certificate_of_indigency'];

// Every Community Service or Leadership program also requires proof of the applicant's
// leadership role or community involvement (e.g. certificate, appointment letter).
const COMMUNITY_SERVICE_REQUIRED_DOCUMENTS = [
    'leadership_record' => 'A documented record of your leadership role or community involvement.',
];

function isCommunityServiceScholarshipType(string $type): bool {
    return stripos($type, 'community service') !== false || stripos($type, 'leadership scholarship') !== false;
}

function isNeedBasedScholarshipType(string $type): bool {
    return stripos($type, 'need-based') !== false || stripos($type, 'need based') !== false;
}

/*
 * The full document list an applicant faces for one program: the universal documents first
 * (always required — even if the program lists one as optional), then the Need-Based ones
 * for Need-Based programs, then the program's own. Built-in documents not in the program's
 * list get id 0 (no scholarship_documents row).
 */
function getApplicationDocuments(PDO $pdo, int $scholarshipId): array {
    $docs = getScholarshipDocuments($pdo, $scholarshipId);

    $required = UNIVERSAL_REQUIRED_DOCUMENTS;
    $typeStmt = $pdo->prepare("SELECT type FROM scholarships WHERE id = ?");
    $typeStmt->execute([$scholarshipId]);
    $programType = (string)$typeStmt->fetchColumn();
    if (isNeedBasedScholarshipType($programType)) {
        $required += NEED_BASED_REQUIRED_DOCUMENTS;
        // Separate ITR / Certificate of Indigency entries are replaced by the combined one.
        $docs = array_filter($docs, fn($d) => !in_array($d['document_type'], ITR_OR_INDIGENCY_TYPES, true));
    }
    if (isCommunityServiceScholarshipType($programType)) {
        $required += COMMUNITY_SERVICE_REQUIRED_DOCUMENTS;
    }

    $builtIn = [];
    foreach ($required as $type => $description) {
        $existing = null;
        foreach ($docs as $i => $d) {
            if ($d['document_type'] === $type) { $existing = $d; unset($docs[$i]); break; }
        }
        $builtIn[] = $existing
            ? array_merge($existing, ['required' => 1])
            : ['id' => 0, 'scholarship_id' => $scholarshipId, 'document_type' => $type, 'description' => $description, 'required' => 1, 'sort_order' => -1];
    }
    return array_merge($builtIn, array_values($docs));
}

// Programs applied for on an outside site instead of this form, keyed by program code/acronym.
// The Apply page sends the applicant there when they pick one; the submit API refuses them.
const EXTERNAL_APPLICATION_PROGRAMS = [
    'CMSP' => 'https://ched.gov.ph/stufaps',     // CHED Merit Scholarship Program
    'COSCHO' => 'https://ched.gov.ph/stufaps',   // Scholarship for Coconut Farmers and Their Families
];

// The outside application URL for a program row (matched on its code, or the acronym that
// starts its name/sub-type, e.g. "CMSP (CHED Merit Scholarship Program)"), or null.
function externalApplicationUrl(array $program): ?string {
    foreach ([$program['code'] ?? '', $program['subtype'] ?? '', $program['name'] ?? ''] as $value) {
        if (preg_match('/^\s*([A-Za-z]+)\b/', (string)$value, $m)) {
            $key = strtoupper($m[1]);
            if (isset(EXTERNAL_APPLICATION_PROGRAMS[$key])) return EXTERNAL_APPLICATION_PROGRAMS[$key];
        }
    }
    return null;
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

// Whether the applicant has a Certificate of Enrollment on file (legacy coe_file, or a "coe"
// upload in applicant_documents).
function applicantHasCoe(PDO $pdo, array $applicant): bool {
    if (trim((string)($applicant['coe_file'] ?? '')) !== '') return true;
    $id = (int)($applicant['id'] ?? 0);
    if ($id <= 0) return false;
    $stmt = $pdo->prepare("SELECT 1 FROM applicant_documents WHERE applicant_id = ? AND document_type = 'coe' AND TRIM(file_path) != '' LIMIT 1");
    $stmt->execute([$id]);
    return (bool)$stmt->fetchColumn();
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
                if (!$enrolled) {
                    return ['status' => 'fail', 'autoChecked' => true, 'actualValue' => 0];
                }
                // Same rule as Evaluation's Enrollment column/tab: enrollment is confirmed by an
                // uploaded Certificate of Enrollment, not by the enrolled flag (every new applicant
                // has it by default).
                // Confirmed in the Registrar's records counts as officially enrolled, even with no COE.
                if (empty($applicant['enrollment_verified']) && !applicantHasCoe($pdo, $applicant)) {
                    return ['status' => 'pending', 'autoChecked' => true, 'actualValue' => null, 'remarks' => 'No Certificate of Enrollment on file.'];
                }
                return ['status' => 'pass', 'autoChecked' => true, 'actualValue' => 1];
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

    // Applicants who uploaded a separate ITR or Certificate of Indigency (before the two were
    // combined) still count as having submitted the combined document.
    if (empty($uploaded['itr_or_indigency'])) {
        foreach (ITR_OR_INDIGENCY_TYPES as $alt) {
            if (!empty($uploaded[$alt])) { $uploaded['itr_or_indigency'] = $uploaded[$alt]; break; }
        }
    }

    $out = [];
    foreach (getApplicationDocuments($pdo, $scholarshipId) as $d) {
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
