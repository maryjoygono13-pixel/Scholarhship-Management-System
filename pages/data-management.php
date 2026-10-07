<?php
require_once __DIR__ . '/../config/config.php';

$current_page = 'data-management';
$page_title = "Data Management";
$page_css = "data-management.css";
$page_js = "data-management.js";

include __DIR__ . '/../includes/header.php';

?>

<!-- Same size as the Notifications page (max 1400px wide, 24px padding). -->
<div class="dm-page">

<!-- Fetch from Registrar Database: look up an Excel/CSV list of students in the Registrar's
     database (connection: config/registrar_database.php) and pull their program, year level
     and grades. -->
<div class="registrar-fetch-card">
    <div class="registrar-fetch-head">
        <div>
            <h2>Fetch from Registrar Database</h2>
        </div>
        <span class="registrar-conn" id="registrarConn">Checking connection…</span>
    </div>

    <div class="registrar-fetch-controls">
        <input type="file" id="registrarFile" hidden accept=".xlsx,.csv">
        <button type="button" class="registrar-choose-btn" id="registrarChooseBtn">Choose File</button>
        <span class="registrar-file-name" id="registrarFileName">No file selected</span>
        <label class="registrar-save-toggle"><input type="checkbox" id="registrarSave" checked> Add to Applicants &amp; save grades</label>
        <button type="button" class="import-btn registrar-fetch-btn" id="registrarFetchBtn">Fetch</button>
    </div>

    <!-- After a fetch: a short summary and where to see the students. Only rows that need
         attention (not found, name mismatch, program problem) are listed here. -->
    <div class="registrar-results" id="registrarResults" hidden>
        <div class="registrar-summary" id="registrarSummary"></div>
        <div class="registrar-goto" id="registrarGoto">
            <a class="registrar-goto-btn" href="<?= SITE_BASE ?>/applicants">View in Applicants</a>
            <a class="registrar-goto-btn" href="<?= SITE_BASE ?>/evaluation">Open Evaluation</a>
        </div>
        <div class="registrar-issues" id="registrarIssues" hidden>
            <h4>Needs attention</h4>
            <div class="registrar-table-wrap">
                <table class="registrar-table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Name in File</th>
                            <th>Name in Registrar</th>
                            <th>Problem</th>
                        </tr>
                    </thead>
                    <tbody id="registrarIssuesBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Verify CHED Enrollment List: check every student on a CHED list against the Registrar's
     database and mark the officially enrolled ones (includes/enrollment_verification_helper.php). -->
<div class="registrar-fetch-card" id="enrollmentVerifyCard">
    <div class="registrar-fetch-head">
        <div>
            <h2>Verify CHED Enrollment List</h2>
        </div>
    </div>

    <div class="registrar-fetch-controls">
        <input type="file" id="verifyFile" hidden accept=".xlsx,.csv">
        <button type="button" class="registrar-choose-btn" id="verifyChooseBtn">Choose File</button>
        <span class="registrar-file-name" id="verifyFileName">No file selected</span>
        <label class="registrar-save-toggle"><input type="checkbox" id="verifyMark" checked> Mark confirmed students as enrolled</label>
        <button type="button" class="import-btn registrar-fetch-btn" id="verifyBtn">Verify</button>
    </div>

    <div class="registrar-results" id="verifyResults" hidden>
        <div class="registrar-summary" id="verifySummary"></div>
        <div class="registrar-goto">
            <a class="registrar-goto-btn" id="verifyDownloadBtn" href="#">Download results (Excel)</a>
            <div class="verify-filter" id="verifyFilter">
                <button type="button" class="active" data-filter="all">All</button>
                <button type="button" data-filter="enrolled">Enrolled</button>
                <button type="button" data-filter="not_enrolled">Not enrolled</button>
                <button type="button" data-filter="not_found">Not found</button>
                <button type="button" data-filter="review">Check manually</button>
            </div>
        </div>
        <div class="registrar-table-wrap verify-table-wrap">
            <table class="registrar-table">
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name in CHED List</th>
                        <th>Result</th>
                        <th>Program</th>
                        <th>Year Level</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody id="verifyResultsBody"></tbody>
            </table>
        </div>
    </div>

    <details class="verify-history" id="verifyHistory">
        <summary>Previous checks</summary>
        <div id="verifyHistoryBody" class="verify-history-body">Loading…</div>
    </details>
</div>

<!-- Import Academic / Enrollment Records: hidden for now (kept in the page so it can be shown again
     by removing the "hidden" attribute). -->
<div class="page-container" hidden>

    <!-- LEFT COLUMN -->
    <div class="management-column">
        <div class="import-card">
            <h2>Import Academic Records</h2>
            <input type="file" id="gradeFile" hidden accept=".csv, .xlsx, .xls">

            <div class="button-group">
                <button type="button" id="gradeBtn">Choose File</button>
                <button type="button" class="import-btn" id="gradeImportBtn">Import</button>
            </div>

            <div class="selected-file" id="gradeSelectedFile">
                <span id="gradeFileName">No file selected</span>
                <button type="button" id="gradeDeleteBtn">&times;</button>
            </div>
        </div>

        <div class="table-card" id="gradeFilesCard">
            <div class="table-card-header" id="gradeCardHeader">
                <h3>Imported Grade Files</h3>
                <span class="card-toggle-icon" id="gradeToggleIcon" title="Toggle section">
                    <i data-lucide="chevron-down"></i>
                </span>
            </div>
            
            <div class="table-card-content" id="gradeCardContent">
                <table>
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Imported On</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>

                    <tbody id="gradeFilesBody">
                        <tr>
                            <td colspan="3" class="empty-state">Loading grade files...</td>
                        </tr>
                    </tbody>
                </table>
                <div class="pagination-bar" id="gradeFilesPagination"></div>
            </div>
        </div>
    </div>

    <!-- RIGHT COLUMN -->
    <div class="management-column">
        <div class="import-card">
            <h2>Import Enrollment Records</h2>
            <input type="file" id="enrollmentFile" hidden accept=".csv, .xlsx, .xls">

            <div class="button-group">
                <button type="button" id="enrollmentBtn">Choose File</button>
                <button type="button" class="import-btn" id="enrollmentImportBtn">Import</button>
            </div>

            <div class="selected-file" id="enrollmentSelectedFile">
                <span id="enrollmentFileName">No file selected</span>
                <button type="button" id="enrollmentDeleteBtn">&times;</button>
            </div>
        </div>

        <div class="table-card" id="enrollmentFilesCard">
            <div class="table-card-header" id="enrollmentCardHeader">
                <h3>Imported Enrollment Files</h3>
                <span class="card-toggle-icon" id="enrollmentToggleIcon" title="Toggle section">
                    <i data-lucide="chevron-down"></i>
                </span>
            </div>

            <div class="table-card-content" id="enrollmentCardContent">
                <table>
                    <thead>
                        <tr>
                            <th>File Name</th>
                            <th>Imported On</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>

                    <tbody id="enrollmentFilesBody">
                        <tr>
                            <td colspan="3" class="empty-state">Loading enrollment files...</td>
                        </tr>
                    </tbody>
                </table>
                <div class="pagination-bar" id="enrollmentFilesPagination"></div>
            </div>
        </div>
    </div>

</div>

<!-- FILE DETAILS MODAL -->
<div class="custom-modal-overlay" id="fileDetailsModal">
    <div class="custom-modal-card">
        <div class="custom-modal-header">
            <h3>Imported File Details</h3>
            <button type="button" class="custom-modal-close" id="closeDetailsModalBtn">&times;</button>
        </div>
        <div class="custom-modal-body">
            <div class="modal-detail-row">
                <span class="modal-detail-label">File Name:</span>
                <span class="modal-detail-val font-bold" id="modalFileName">-</span>
            </div>
            <div class="modal-detail-row">
                <span class="modal-detail-label">Record Type:</span>
                <span class="modal-detail-val" id="modalFileType">-</span>
            </div>
            <div class="modal-detail-row">
                <span class="modal-detail-label">Last Imported Date & Time:</span>
                <span class="modal-detail-val" id="modalFileDate">-</span>
            </div>
            <div class="modal-detail-row">
                <span class="modal-detail-label">Records Processed:</span>
                <span class="modal-detail-val" id="modalRecordsCount">0 Records</span>
            </div>
            <div class="modal-detail-row">
                <span class="modal-detail-label">Imported By:</span>
                <span class="modal-detail-val" id="modalImportedBy">Registrar Staff</span>
            </div>
        </div>
        <div class="custom-modal-footer">
            <button type="button" class="btn-modal-secondary" id="closeDetailsBtn">Close</button>
        </div>
    </div>
</div>

<!-- DELETE CONFIRMATION MODAL -->
<div class="custom-modal-overlay" id="deleteImportModal">
    <div class="custom-modal-card">
        <div class="custom-modal-header">
            <h3 class="danger-title">Delete Imported File</h3>
            <button type="button" class="custom-modal-close" id="closeDeleteModalBtn">&times;</button>
        </div>
        <div class="custom-modal-body">
            <p>Are you sure you want to delete this imported file record?</p>
            <div class="file-delete-box">
                <strong id="deleteFileNamePreview">file.csv</strong>
                <div class="text-sub" id="deleteFileDatePreview">-</div>
            </div>
            <p class="text-muted-sm">This action removes the file entry from the import history.</p>
        </div>
        <div class="custom-modal-footer">
            <button type="button" class="btn-modal-secondary" id="cancelDeleteBtn">Cancel</button>
            <button type="button" class="btn-modal-danger" id="confirmDeleteBtn">Delete File</button>
        </div>
    </div>
</div>

</div><!-- /.dm-page -->

<script src="<?= SITE_BASE ?>/assets/js/registrar-fetch.js?v=<?= time() ?>"></script>
<script src="<?= SITE_BASE ?>/assets/js/enrollment-verify.js?v=<?= time() ?>"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>