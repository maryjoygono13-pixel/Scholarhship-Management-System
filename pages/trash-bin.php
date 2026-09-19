<?php
$page_title = 'Trash Bin / Revert';
$page_css = 'trash-bin.css';
include __DIR__ . '/../includes/header.php';
?>

<div class="trash-container">
    <!-- Header Banner -->
    <div class="trash-banner">
        <div class="trash-banner-info">
            <h3>
                <i data-lucide="rotate-ccw"></i>
                Trash Bin &amp; Revert System
            </h3>
            <p>
                Deleted applicants, scholars, scholarships, records, notifications, and import records are automatically archived here.
                You can review deleted entries and click <strong>Restore / Revert</strong> to return them back to active system records.
            </p>
        </div>
    </div>

    <!-- Controls Bar -->
    <div class="trash-controls">
        <div style="display:flex; align-items:center; gap:12px;">
            <label for="trashTypeFilter" style="font-size:13.5px; font-weight:600; color:#334155;">Filter by Category:</label>
            <select id="trashTypeFilter" class="trash-type-filter">
                <option value="all">All Categories</option>
                <option value="applicant">Applicants</option>
                <option value="scholar">Scholars</option>
                <option value="scholarship">Scholarships</option>
                <option value="record">Records</option>
                <option value="notification">Notifications</option>
                <option value="import">Imported Files</option>
            </select>
        </div>

        <button type="button" class="btn-purge" id="emptyTrashBtn">
            <i data-lucide="trash-2"></i>
            Empty Trash Bin
        </button>
    </div>

    <!-- Trash Table -->
    <div class="table-card" style="background:#ffffff; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.05); overflow:hidden;">
        <table class="scholars-table" style="width:100%; border-collapse:collapse;">
            <thead>
                <tr style="background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                    <th style="padding:14px 18px; font-size:12px; text-transform:uppercase; color:#475569;">Category</th>
                    <th style="padding:14px 18px; font-size:12px; text-transform:uppercase; color:#475569;">Deleted Item Title / Name</th>
                    <th style="padding:14px 18px; font-size:12px; text-transform:uppercase; color:#475569;">Deleted On</th>
                    <th style="padding:14px 18px; font-size:12px; text-transform:uppercase; color:#475569;">Deleted By</th>
                    <th style="padding:14px 18px; font-size:12px; text-transform:uppercase; color:#475569; text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody id="trashTableBody">
                <!-- Dynamically populated via trash-bin.js -->
            </tbody>
        </table>

        <div id="trashEmptyState" class="empty-state" style="display:none; padding:48px; text-align:center; color:#64748b;">
            <i data-lucide="archive" style="width:48px; height:48px; margin-bottom:12px; opacity:0.4;"></i>
            <p style="font-size:15px; font-weight:600; margin:0;">Trash Bin is empty</p>
            <p style="font-size:13px; color:#94a3b8; margin-top:4px;">No deleted items found in this category.</p>
        </div>
    </div>

    <div class="pagination-bar" id="trashPagination"></div>
</div>

<!-- Restore / Revert Confirm Modal -->
<div class="custom-modal-overlay" id="restoreConfirmOverlay">
    <div class="custom-modal-card sm">
        <div class="custom-modal-header">
            <div>
                <h3>Restore / Revert Item</h3>
                <p>Return archived item back to active system records.</p>
            </div>
            <button type="button" class="custom-modal-close" id="restoreCloseBtn"><i data-lucide="x"></i></button>
        </div>
        <div class="custom-modal-body">
            <p style="font-size:14px; color:#4b5563;">Are you sure you want to restore <strong id="restoreTargetTitle"></strong> back to active records?</p>
        </div>
        <div class="custom-modal-footer">
            <button type="button" class="btn-secondary" id="restoreCancelBtn">Cancel</button>
            <button type="button" class="btn-primary" id="restoreConfirmBtn" style="background:#134e2a; border-color:#134e2a;">Restore / Revert</button>
        </div>
    </div>
</div>

<!-- Purge / Permanent Delete Confirm Modal -->
<div class="custom-modal-overlay" id="purgeConfirmOverlay">
    <div class="custom-modal-card sm">
        <div class="custom-modal-header">
            <div>
                <h3>Permanently Delete</h3>
                <p>This action cannot be undone.</p>
            </div>
            <button type="button" class="custom-modal-close" id="purgeCloseBtn"><i data-lucide="x"></i></button>
        </div>
        <div class="custom-modal-body">
            <p style="font-size:14px; color:#4b5563;">Are you sure you want to permanently delete <strong id="purgeTargetTitle"></strong>?</p>
        </div>
        <div class="custom-modal-footer">
            <button type="button" class="btn-secondary" id="purgeCancelBtn">Cancel</button>
            <button type="button" class="btn-danger" id="purgeConfirmBtn">Permanently Delete</button>
        </div>
    </div>
</div>

<script src="<?= SITE_BASE ?>/assets/js/trash-bin.js?v=<?= time() ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
