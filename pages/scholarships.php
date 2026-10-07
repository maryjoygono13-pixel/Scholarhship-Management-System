<?php
$current_page = 'scholarships';
$page_title = "Scholarships";
$page_css = "scholarships.css";
$page_js = "scholarships.js";

include __DIR__ . '/../includes/header.php';
?>

<div class="page">
    <div class="table-header-toolbar">
        <div class="toolbar">
            <div class="search-wrap">
                <input type="text" placeholder="Search scholarship...">
                <i data-lucide="search"></i>
            </div>

            <div class="select-wrap">
                <select id="filterType">
                    <option value="all">Types</option>
                </select>
                <i data-lucide="chevron-down"></i>
            </div>

            <div class="select-wrap">
                <select id="filterSubtype">
                    <option value="all">Programs</option>
                </select>
                <i data-lucide="chevron-down"></i>
            </div>

        <button class="btn-primary" id="addScholarshipBtn">
            <i data-lucide="plus"></i>
            Add Scholarship
        </button>
        </div>
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table class="scholarships-table">
                <thead>
                    <tr>
                        <th>Scholarship Name</th>
                        <th>Type</th>
                        <th style="white-space:nowrap;">GWA</th>
                        <th style="white-space:nowrap; min-width:120px;">Slots<div style="font-size:10px; font-weight:400; color:#9ca3af;">Applied / Total</div></th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <!-- Dynamic -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add / Edit Scholarship Modal (multi-step wizard) -->
<div class="custom-modal-overlay" id="schFormOverlay">
    <div class="custom-modal-card lg">
        <div class="custom-modal-header">
            <div>
                <h3 id="schFormTitle">Add Scholarship</h3>
                <p>Configure scholarship parameters and requirements.</p>
            </div>
            <button type="button" class="custom-modal-close" id="schFormCloseBtn"><i data-lucide="x"></i></button>
        </div>
        <div class="sch-wizard-progress">
            <p class="sch-step-counter" id="schStepCounter">Step 1 of 6 — Basic Information</p>
            <div class="sch-progress-bar"><div class="sch-progress-fill" id="schProgressFill"></div></div>
        </div>
        <form id="schForm">
            <input type="hidden" id="schId" name="id">
            <div class="custom-modal-body">
                <input type="hidden" id="schName" name="name">
                <input type="hidden" id="schCode" name="code">

                <!-- Step 1: Basic Information -->
                <div class="sch-step active" data-step="1">
                <div class="field">
                    <label style="font-size:13px; font-weight:600; color:#374151;">Category / Type <span style="color:red;">*</span></label>
                    <input type="hidden" id="schType" name="type" required>
                    <div class="pill-picker" id="schTypePicker">
                        <div class="pill-picker-add" id="schTypeAddWrap">
                            <button type="button" class="pill-add-btn" id="schTypeAddBtn" title="Add a new scholarship category" aria-label="Add a new scholarship category">
                                <i data-lucide="plus"></i>
                            </button>
                            <input type="text" class="pill-add-input" id="schTypeAddInput" placeholder="New category name, then Enter" hidden>
                        </div>
                    </div>
                </div>
                <div class="field" id="schSubtypeField" hidden>
                    <label style="font-size:13px; font-weight:600; color:#374151; display:flex; align-items:center; justify-content:space-between;">
                        <span>Program <span id="schSubtypeHint" style="font-weight:400; color:#6b7280;">(pick one or more)</span></span>
                        <button type="button" class="pill-select-all" id="schSubtypeSelectAll">Select all</button>
                    </label>
                    <input type="hidden" id="schSubtype" name="subtype">
                    <div class="pill-picker" id="schSubtypePicker">
                        <div class="pill-picker-add" id="schSubtypeAddWrap">
                            <button type="button" class="pill-add-btn" id="schSubtypeAddBtn" title="Add programs" aria-label="Add programs">
                                <i data-lucide="plus"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="field">
                    <label style="font-size:13px; font-weight:600; color:#374151;">This will be saved as</label>
                    <div class="identity-preview-box">
                        <strong id="schPreviewName">Select a category to continue</strong>
                        <span class="font-mono identity-preview-code" id="schPreviewCode"></span>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label style="font-size:13px; font-weight:600; color:#374151;">Education Level</label>
                        <select id="schEducationLevel" name="education_level" class="sch-input">
                            <option value="Collegiate">Collegiate</option>
                            <option value="Basic Education">Basic Education</option>
                            <option value="Both">Both</option>
                        </select>
                    </div>
                    <div class="field">
                        <label style="font-size:13px; font-weight:600; color:#374151;">School Year</label>
                        <input type="text" id="schSchoolYear" name="school_year" placeholder="e.g. 2025-2026" class="sch-input">
                    </div>
                </div>
                <!-- MERIT-BASED only: editable eligibility rules, saved on the program and used for
                     every Merit decision (see includes/merit_helper.php) -->
                <div class="merit-rules" id="schMeritRules" hidden>
                    <div class="merit-rules-title">MERIT-BASED Eligibility</div>
                    <div class="merit-rules-cols">
                    <div class="merit-rules-group" id="schMeritCollegiate">
                        <span class="merit-rules-level">Collegiate (GWA)</span>
                        <div class="merit-rules-row">
                            <span class="merit-tier merit-tier-full">Full Merit</span>
                            <input type="number" step="0.01" min="1" max="5" class="merit-gwa-input" id="schMeritFullMin" name="merit_full_min" value="1.00" aria-label="Full Merit from GWA">
                            <span>–</span>
                            <input type="number" step="0.01" min="1" max="5" class="merit-gwa-input" id="schMeritFullMax" name="merit_full_max" value="1.30" aria-label="Full Merit to GWA">
                        </div>
                        <div class="merit-rules-row">
                            <span class="merit-tier merit-tier-half">Half Merit</span>
                            <input type="number" step="0.01" min="1" max="5" class="merit-gwa-input" id="schMeritHalfMin" name="merit_half_min" value="1.31" aria-label="Half Merit from GWA">
                            <span>–</span>
                            <input type="number" step="0.01" min="1" max="5" class="merit-gwa-input" id="schMeritHalfMax" name="merit_half_max" value="1.50" aria-label="Half Merit to GWA">
                        </div>
                    </div>
                    <div class="merit-rules-group" id="schMeritBasic">
                        <span class="merit-rules-level">Basic Education</span>
                        <textarea class="merit-basic-input" id="schMeritBasicCriteria" name="merit_basic_criteria" rows="2" maxlength="255" placeholder="e.g. Top 1 or Top 2 in class">Top 1 or Top 2 in class</textarea>
                        <span class="merit-rules-hint">Verified by the registrar.</span>
                    </div>
                    </div>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label style="font-size:13px; font-weight:600; color:#374151;">Application Start</label>
                        <input type="date" id="schAppStart" name="application_start" class="sch-input">
                    </div>
                    <div class="field">
                        <label style="font-size:13px; font-weight:600; color:#374151;">Application Deadline</label>
                        <input type="date" id="schAppDeadline" name="application_deadline" class="sch-input">
                    </div>
                </div>
                <div class="field-row">
                    <div class="field">
                        <label style="font-size:13px; font-weight:600; color:#374151;">GWA Requirement</label>
                        <input type="number" step="0.01" id="schGwa" name="gwa_requirement" placeholder="Leave blank if none" class="sch-input">
                    </div>
                    <div class="field">
                        <label style="font-size:13px; font-weight:600; color:#374151;">Total Slots</label>
                        <input type="number" id="schSlots" name="slots" placeholder="50" class="sch-input">
                    </div>
                </div>
                <div class="field">
                    <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:#374151; cursor:pointer;">
                        <input type="checkbox" id="schUnlimited" name="unlimited_slots" value="1">
                        No slot limit (unlimited)
                    </label>
                    <span style="font-size:12px; color:#6b7280; display:block; margin-top:4px;">For scholarships given to every student who meets the eligibility criteria, such as MERIT-BASED Academic.</span>
                </div>
                <div class="field">
                    <label style="font-size:13px; font-weight:600; color:#374151;">Description</label>
                    <textarea id="schDescription" name="description" rows="2" placeholder="Short description of this program" class="sch-input" style="height:auto; padding:10px 12px; resize:vertical;"></textarea>
                </div>
                </div>

                <!-- Step 2: Eligibility Criteria -->
                <div class="sch-step" data-step="2">
                    <p class="sch-step-note">Add only the criteria that actually apply to this program — the registrar decides what's checked automatically (GWA, failing grades, enrollment, ...) versus what needs manual verification (leadership, awards, ...). Poverty Threshold: enter the monthly amount — applicants declare their family income and it is compared automatically.</p>
                    <div id="schCriteriaRows"></div>
                    <button type="button" class="btn-secondary" id="schCriteriaAddBtn" style="align-self:flex-start;"><i data-lucide="plus"></i> Add Criterion</button>
                </div>

                <!-- Step 3: Required Documents -->
                <div class="sch-step" data-step="3">
                    <p class="sch-step-note">Every document listed here is what applicants for this program will be asked to upload — not necessarily the same 3 for every scholarship. The <strong>Certificate of Enrollment (COE)</strong> is always required for every program (proof of being a bonafide CM student), so you don't need to add it here. <strong>NEED-BASED</strong> programs also always require <strong>Academic Records</strong> and one <strong>ITR or Certificate of Indigency</strong> upload (either one is enough) — a separate ITR / Certificate of Indigency added here is folded into that single upload. <strong>Community Service or Leadership</strong> programs always require a <strong>Documented Record of Leadership Role or Community Involvement</strong>.</p>
                    <div id="schDocumentRows"></div>
                    <button type="button" class="btn-secondary" id="schDocumentAddBtn" style="align-self:flex-start;"><i data-lucide="plus"></i> Add Document</button>
                </div>

                <!-- Step 4: Benefits / Incentives -->
                <div class="sch-step" data-step="4">
                    <p class="sch-step-note">What a scholar under this program receives. Shown to applicants and kept for the registrar's reference.</p>
                    <div id="schBenefitRows"></div>
                    <button type="button" class="btn-secondary" id="schBenefitAddBtn" style="align-self:flex-start;"><i data-lucide="plus"></i> Add Benefit</button>
                </div>

                <!-- Step 5: Renewal Rules -->
                <div class="sch-step" data-step="5">
                    <div class="field">
                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:#374151; cursor:pointer;">
                            <input type="checkbox" id="schRenewalRequired" checked>
                            This scholarship requires renewal each term
                        </label>
                        <span style="font-size:12px; color:#6b7280; display:block; margin-top:4px;">Unchecked means an approved scholar is never sent to Renewal &amp; Retention for reassessment.</span>
                    </div>
                    <div class="field-row" id="schRenewalDetails">
                        <div class="field">
                            <label style="font-size:13px; font-weight:600; color:#374151;">Renewal Period</label>
                            <select id="schRenewalPeriod" class="sch-input">
                                <option>Every Semester</option>
                                <option>Every School Year</option>
                            </select>
                        </div>
                        <div class="field">
                            <label style="font-size:13px; font-weight:600; color:#374151;">Minimum GWA to Renew (optional override)</label>
                            <input type="number" step="0.01" id="schRenewalMinGwa" placeholder="Defaults to the program's own GWA requirement" class="sch-input">
                        </div>
                    </div>
                    <div class="field" id="schRenewalFlags">
                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:#374151; cursor:pointer; margin-bottom:8px;">
                            <input type="checkbox" id="schRenewalNoFailing" checked>
                            No failing grades required to renew
                        </label>
                        <label style="display:flex; align-items:center; gap:8px; font-size:13px; color:#374151; cursor:pointer;">
                            <input type="checkbox" id="schRenewalUpdatedDocs">
                            Updated documents required to renew
                        </label>
                    </div>
                    <div class="field">
                        <label style="font-size:13px; font-weight:600; color:#374151;">Other Renewal Conditions</label>
                        <textarea id="schRenewalDescription" rows="2" placeholder="Optional notes" class="sch-input" style="height:auto; padding:10px 12px; resize:vertical;"></textarea>
                    </div>
                </div>

                <!-- Step 6: Review -->
                <div class="sch-step" data-step="6">
                    <p class="sch-step-note">Review before saving.</p>
                    <div id="schReviewBody" class="sch-review-body"></div>
                </div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn-secondary" id="schFormCancelBtn">Cancel</button>
                <button type="button" class="btn-secondary" id="schWizardBackBtn" style="display:none;">Back</button>
                <button type="button" class="btn-primary" id="schWizardNextBtn">Next</button>
                <button type="submit" class="btn-primary" id="schWizardSaveBtn" style="display:none;">Save Program</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Program Modal -->
<div class="custom-modal-overlay" id="subtypeBulkOverlay">
    <div class="custom-modal-card">
        <div class="custom-modal-header">
            <div>
                <h3>Add Program</h3>
                <p id="subtypeBulkTypeLabel">Under: &mdash;</p>
            </div>
            <button type="button" class="custom-modal-close" id="subtypeBulkCloseBtn"><i data-lucide="x"></i></button>
        </div>
        <div class="custom-modal-body" style="display:flex; flex-direction:column; gap:10px;">
            <div id="subtypeBulkRows"></div>
        </div>
        <div class="custom-modal-footer">
            <button type="button" class="btn-secondary" id="subtypeBulkCancelBtn">Cancel</button>
            <button type="button" class="btn-primary" id="subtypeBulkSaveBtn">Save Program</button>
        </div>
    </div>
</div>

<!-- View Scholarship Details Modal -->
<div class="custom-modal-overlay" id="schViewOverlay">
    <div class="custom-modal-card lg">
        <div class="custom-modal-header">
            <div>
                <h3>Scholarship Overview</h3>
                <p>Program requirements and capacity details.</p>
            </div>
            <button type="button" class="custom-modal-close" id="schViewCloseBtn"><i data-lucide="x"></i></button>
        </div>
        <div class="custom-modal-body" id="schViewBody"></div>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div class="custom-modal-overlay" id="schDeleteOverlay">
    <div class="custom-modal-card sm">
        <div class="custom-modal-header">
            <div>
                <h3>Delete Scholarship</h3>
                <p>Confirm program removal.</p>
            </div>
            <button type="button" class="custom-modal-close" id="schDeleteCloseBtn"><i data-lucide="x"></i></button>
        </div>
        <div class="custom-modal-body">
            <p style="font-size:14px; color:#4b5563;">Are you sure you want to delete <strong id="schDeleteTarget"></strong>?</p>
        </div>
        <div class="custom-modal-footer">
            <button type="button" class="btn-secondary" id="schDeleteCancelBtn">Cancel</button>
            <button type="button" class="btn-danger" id="schDeleteConfirmBtn">Delete Program</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>