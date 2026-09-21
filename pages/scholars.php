<?php
$page_title = 'Scholars';
$page_css = 'scholars.css';
include __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/db_helper.php';
require_once __DIR__ . '/../includes/term_helper.php';
$activeSchoolYear = getActiveSchoolYear(getDB());
?>

<div class="scholars-container">
    <!-- Grade Maintenance Policy Banner -->
    <div class="rule-banner">
        <div class="rule-banner-info">
            <h3>
                <i data-lucide="award"></i>
                Scholarship Grade Maintenance Policy
            </h3>
            <p>
                To maintain active scholarship status, scholars must keep the GWA required by their scholarship (a lower number is better, so a GWA of <strong>1.50</strong> or below meets a 1.50 requirement).
                GWA comes from the imported academic records and is tracked separately for each semester. Scholars whose latest GWA is above their requirement are automatically flagged for removal.
            </p>
        </div>
        <div class="rule-chip">
            Requirement: set per scholarship
        </div>
    </div>

    <!-- Filters & Action Bar -->
    <div class="filters-bar">
        <div class="filter-group-left">
            <div class="search-wrapper">
                <i data-lucide="search"></i>
                <input type="text" id="searchScholarInput" class="search-input-field" placeholder="Search student name or ID...">
            </div>

            <!-- Department Filter -->
            <select id="filterDepartment" class="filter-select">
                <option value="all">Departments</option>
                <option value="Nursing">Nursing</option>
                <option value="Information Technology">Information Technology</option>
                <option value="Accountancy">Accountancy</option>
                <option value="Business Administration">Business Administration</option>
                <option value="Liberal Arts and Education">Liberal Arts and Education</option>
                <option value="Food Preparation & Service Technology">Food Preparation & Service Technology</option>
            </select>

            <!-- Grade Year Filter (1 to 4) -->
            <select id="filterYear" class="filter-select">
                <option value="all">Grade Years</option>
                <option value="1">1st Year (Year 1)</option>
                <option value="2">2nd Year (Year 2)</option>
                <option value="3">3rd Year (Year 3)</option>
                <option value="4">4th Year (Year 4)</option>
            </select>

            <!-- Status Filter -->
            <select id="filterStatus" class="filter-select">
                <option value="above" selected>Meets GWA Requirement</option>
                <option value="below">Below GWA Requirement</option>
            </select>
        </div>

        <div style="display:flex; gap:10px; align-items:center;">
            <a href="<?= SITE_BASE ?>/scholar-map" class="btn-add-scholar" style="background: linear-gradient(135deg, #134e2a 0%, #1b6336 100%); text-decoration:none;">
                <i data-lucide="map-pin"></i>
                Map
            </a>
            <button type="button" class="btn-add-scholar" id="addScholarBtn">
                <i data-lucide="user-plus"></i>
                Add Scholar
            </button>
        </div>
    </div>

    <!-- Scholars Table -->
    <div class="table-card">
        <table class="scholars-table">
            <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Grade Year</th>
                    <th>1st Sem GWA</th>
                    <th>2nd Sem GWA</th>
                    <th>School Year</th>
                    <th>Status</th>
                    <th style="text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody id="scholarsTableBody">
                <!-- Dynamically populated via scholars.js -->
            </tbody>
        </table>

        <div id="scholarsEmptyState" class="empty-state" style="display:none; padding: 40px; text-align:center; color:#64748b;">
            <i data-lucide="user-x" style="width:48px; height:48px; margin-bottom:12px; opacity:0.5;"></i>
            <p style="font-size:15px; font-weight:600; margin:0;">No scholars found</p>
            <p style="font-size:13px; color:#94a3b8; margin-top:4px;">Try adjusting your department, grade year, or search filters.</p>
        </div>
    </div>

    <div class="pagination-bar" id="scholarsPagination"></div>
</div>

<!-- Add / Edit Scholar Modal -->
<div class="custom-modal-overlay" id="scholarModalOverlay">
    <div class="custom-modal-card">
        <div class="custom-modal-header">
            <div>
                <h3 id="scholarModalTitle">Add New Scholar</h3>
                <p>Manage scholar details and grade maintenance standing.</p>
            </div>
            <button type="button" class="custom-modal-close" id="scholarModalCloseBtn">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form id="scholarForm">
            <div class="custom-modal-body" style="padding: 20px; display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div style="grid-column: span 1;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#334155;">Student ID *</label>
                    <input type="text" id="modalStudentId" required placeholder="e.g. 20230088" class="search-input-field" style="width:100%;">
                </div>
                <div style="grid-column: span 1;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#334155;">Full Name *</label>
                    <input type="text" id="modalName" required placeholder="e.g. Maria Santos" class="search-input-field" style="width:100%;">
                </div>

                <div style="grid-column: span 1;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#334155;">Department *</label>
                    <select id="modalDepartment" required class="filter-select" style="width:100%;">
                        <option value="Nursing">Nursing</option>
                        <option value="Information Technology">Information Technology</option>
                        <option value="Accountancy">Accountancy</option>
                        <option value="Business Administration">Business Administration</option>
                        <option value="Liberal Arts and Education">Liberal Arts and Education</option>
                        <option value="Food Preparation & Service Technology">Food Preparation & Service Technology</option>
                    </select>
                </div>

                <div style="grid-column: span 1;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#334155;">Grade Year (1 - 4) *</label>
                    <select id="modalYearLevel" required class="filter-select" style="width:100%;">
                        <option value="1">1st Year (Year 1)</option>
                        <option value="2">2nd Year (Year 2)</option>
                        <option value="3">3rd Year (Year 3)</option>
                        <option value="4">4th Year (Year 4)</option>
                    </select>
                </div>

                <div style="grid-column: span 1;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#334155;">School Year</label>
                    <input type="text" id="modalSchoolYear" value="<?= htmlspecialchars($activeSchoolYear) ?>" class="search-input-field" style="width:100%;">
                </div>

                <div style="grid-column: span 2;">
                    <label style="display:block; font-size:13px; font-weight:600; margin-bottom:6px; color:#334155;">Remarks / Maintenance Notes</label>
                    <textarea id="modalRemarks" rows="3" class="search-input-field" style="width:100%; height:auto; padding:10px;" placeholder="Optional notes regarding grade maintenance or academic evaluation..."></textarea>
                </div>
            </div>

            <div class="custom-modal-footer">
                <button type="button" class="btn-secondary" id="scholarModalCancelBtn">Cancel</button>
                <button type="submit" class="btn-primary">Save Scholar</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="custom-modal-overlay" id="deleteScholarConfirmOverlay">
    <div class="custom-modal-card sm">
        <div class="custom-modal-header">
            <div>
                <h3>Confirm Delete Scholar</h3>
                <p>This will remove the scholar entry.</p>
            </div>
            <button type="button" class="custom-modal-close" id="deleteScholarCloseBtn">
                <i data-lucide="x"></i>
            </button>
        </div>
        <div class="custom-modal-body">
            <p style="font-size:14px; color:#4b5563;">Are you sure you want to delete scholar <strong id="deleteScholarTargetName"></strong>?</p>
        </div>
        <div class="custom-modal-footer">
            <button type="button" class="btn-secondary" id="deleteScholarCancelBtn">Cancel</button>
            <button type="button" class="btn-danger" id="deleteScholarConfirmBtn">Delete Scholar</button>
        </div>
    </div>
</div>

<script src="<?= SITE_BASE ?>/assets/js/scholars.js?v=<?= time() ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
