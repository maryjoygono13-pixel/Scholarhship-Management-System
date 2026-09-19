<?php
require_once __DIR__ . '/../config/config.php';

$current_page = 'data-management';
$page_title = "Data Management";
$page_css = "data-management.css";
$page_js = "data-management.js";

include __DIR__ . '/../includes/header.php';

?>

<div class="page-container">

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


<?php include __DIR__ . '/../includes/footer.php'; ?>