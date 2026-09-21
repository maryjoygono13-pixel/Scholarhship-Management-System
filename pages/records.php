<?php
$current_page = 'records';
$page_title = "Records";
$page_css = "records.css";
$page_js = "records.js";
$extra_js = ["curriculum-data.js"];

include __DIR__ . '/../includes/header.php';
?>

<div class="page">
    <div class="table-header-toolbar">
        <div class="toolbar">
            <div class="search-wrap">
                <input type="text" placeholder="Search applicant...">
                <i data-lucide="search"></i>
            </div>

            <div class="select-wrap">
                <select id="filterType">
                    <option value="all">Scholarship Types</option>
                </select>
                <i data-lucide="chevron-down"></i>
            </div>
            <div class="select-wrap">
                <select id="filterStatus">
                    <option value="all">Status</option>
                    <option value="pending">Pending</option>
                    <option value="approved">Approved</option>
                    <option value="rejected">Rejected</option>
                </select>
                <i data-lucide="chevron-down"></i>
            </div>
            <div class="select-wrap">
                <select id="filterSemester">
                    <option value="all">Semesters</option>
                    <option value="1st">1st Semester</option>
                    <option value="2nd">2nd Semester</option>
                    <option value="summer">Summer Term</option>
                </select>
                <i data-lucide="chevron-down"></i>
            </div>
            <div class="select-wrap">
                <select id="filterSy">
                    <option value="all">School Years</option>
                </select>
                <i data-lucide="chevron-down"></i>
            </div>
        </div>
        <div class="toolbar-actions">
            <button class="btn-primary btn-export" id="exportRecordsBtn">
                <i data-lucide="download"></i>
                Export Records
            </button>
        </div>
    </div>

    <div class="table-card">
        <div class="table-wrap">
            <table class="records-table">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Scholarship Type</th>
                        <th>Status</th>
                        <th>Semester</th>
                        <th>SY</th>
                        <th>Date Evaluated</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <!-- Dynamic -->
                </tbody>
            </table>
        </div>
    </div>
    <div class="pagination-bar" id="recordsPagination"></div>
</div>

<!-- Add / Edit Record Modal -->
<div class="custom-modal-overlay" id="recFormOverlay">
    <div class="custom-modal-card">
        <div class="custom-modal-header">
            <div>
                <h3 id="recFormTitle">Edit Record</h3>
                <p>Log evaluation record details.</p>
            </div>
            <button type="button" class="custom-modal-close" id="recFormCloseBtn"><i data-lucide="x"></i></button>
        </div>
        <form id="recForm">
            <input type="hidden" id="recId" name="id">
            <div class="custom-modal-body" style="display:flex; flex-direction:column; gap:14px;">
                <div class="field">
                    <label style="font-size:13px; font-weight:600; color:#374151;">Student ID <span style="color:red;">*</span></label>
                    <input type="text" id="recStudentId" name="student_id" placeholder="20230001" required style="width:100%; height:40px; padding:0 12px; border:1px solid #d1d5db; border-radius:8px; outline:none;">
                </div>
                <div class="field">
                    <label style="font-size:13px; font-weight:600; color:#374151;">Student Name <span style="color:red;">*</span></label>
                    <input type="text" id="recName" name="name" placeholder="Juan Dela Cruz" required style="width:100%; height:40px; padding:0 12px; border:1px solid #d1d5db; border-radius:8px; outline:none;">
                </div>
                <div class="field">
                    <label style="font-size:13px; font-weight:600; color:#374151;">Scholarship Type</label>
                    <select id="recType" name="scholarship_type" style="width:100%; height:40px; padding:0 12px; border:1px solid #d1d5db; border-radius:8px; outline:none;">
                        <option value="CMSP">CMSP (CHED Merit Scholarship Program)</option>
                        <option value="TDP">TDP (Tulong Dunong Program)</option>
                        <option value="TES">TES (Tertiary Education Subsidy)</option>
                        <option value="COSCHO">COSCHO (Scholarship for Coconut Farmers and Their Families)</option>
                    </select>
                </div>
                <div class="field">
                    <label style="font-size:13px; font-weight:600; color:#374151;">Status</label>
                    <select id="recStatus" name="status" style="width:100%; height:40px; padding:0 12px; border:1px solid #d1d5db; border-radius:8px; outline:none;">
                        <option value="pending">Pending (awaiting renewal check)</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="field">
                    <label style="font-size:13px; font-weight:600; color:#374151;">Remarks</label>
                    <textarea id="recRemarks" name="remarks" placeholder="Evaluation notes..." style="width:100%; height:70px; padding:8px 12px; border:1px solid #d1d5db; border-radius:8px; outline:none; font-family:inherit;"></textarea>
                </div>
            </div>
            <div class="custom-modal-footer">
                <button type="button" class="btn-secondary" id="recFormCancelBtn">Cancel</button>
                <button type="submit" class="btn-primary">Save Record</button>
            </div>
        </form>
    </div>
</div>

<!-- View Record Details Modal -->
<div class="custom-modal-overlay" id="recViewOverlay">
    <div class="custom-modal-card lg" style="max-width: 680px; width: 100%; height: 680px; max-height: calc(100vh - 48px); display: flex; flex-direction: column;">
        <div class="custom-modal-header">
            <div>
                <h3>Record Details</h3>
                <p>Full evaluation outcome summary.</p>
            </div>
            <button type="button" class="custom-modal-close" id="recViewCloseBtn"><i data-lucide="x"></i></button>
        </div>
        <div class="custom-modal-body" id="recViewBody" style="flex:1; overflow-y:auto;"></div>
        <div class="custom-modal-footer">
            <button type="button" class="btn-secondary" id="recViewCloseBtn2">Close</button>
            <button type="button" class="btn-primary" id="recViewEditBtn">
                <i data-lucide="pencil"></i>
                Edit Record
            </button>
        </div>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div class="custom-modal-overlay" id="recDeleteOverlay">
    <div class="custom-modal-card sm">
        <div class="custom-modal-header">
            <div>
                <h3>Delete Record</h3>
                <p>Confirm archive record removal.</p>
            </div>
            <button type="button" class="custom-modal-close" id="recDeleteCloseBtn"><i data-lucide="x"></i></button>
        </div>
        <div class="custom-modal-body">
            <p style="font-size:14px; color:#4b5563;">Are you sure you want to delete evaluation record for <strong id="recDeleteTarget"></strong>?</p>
        </div>
        <div class="custom-modal-footer">
            <button type="button" class="btn-secondary" id="recDeleteCancelBtn">Cancel</button>
            <button type="button" class="btn-danger" id="recDeleteConfirmBtn">Delete Record</button>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>