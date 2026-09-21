<?php
require_once __DIR__ . '/../config/config.php';

$current_page = 'renewal-retention';
$page_title = "Renewal & Retention";
$page_css = "renewal-retention.css";
$page_js = "renewal-retention.js";
include __DIR__ . '/../includes/header.php';
?>
 <main class="page">

    <div class="summary-row" id="summaryRow">
      <div class="summary-card pending">
        <div class="label">Pending</div>
        <div class="value" id="countPending">0</div>
      </div>
      <div class="summary-card eligible">
        <div class="label">Renewed</div>
        <div class="value" id="countEligible">0</div>
      </div>
      <div class="summary-card terminated">
        <div class="label">Terminated</div>
        <div class="value" id="countTerminated">0</div>
      </div>
      <div class="summary-card">
        <div class="label">Total Entries</div>
        <div class="value" id="countTotal">0</div>
      </div>
    </div>

    <div class="toolbar">
      <input type="search" id="searchBox" placeholder="Search by name or student ID…">
      <div class="select-wrap">
        <select id="SchoolYearFilter">
          <option value="">School Year</option>
        </select>
      </div>

      <div class="select-wrap">
        <select id="semesterFilter">
          <option value="">Semesters</option>
          <option value="1st Semester">1st Semester</option>
          <option value="2nd Semester">2nd Semester</option>
          <option value="Summer Term">Summer Term</option>
        </select>
      </div>

      <div class="select-wrap">
        <select id="scholarshipFilter">
          <option value="">Scholarship Type</option>
        </select>
      </div>

      <div class="select-wrap">
        <select id="programFilter">
          <option value="">Program</option>
        </select>
      </div>

      <div class="select-wrap">
        <select id="statusFilter">
          <option value="">Statuses</option>
          <option value="pending">Pending</option>
          <option value="eligible">Renewed</option>
          <option value="terminated">Terminated</option>
        </select>
      </div>

      <button type="button" class="btn-export" id="exportRenewalBtn">
        <i data-lucide="download"></i>
        Export
      </button>
    </div>

    <table class="ledger" id="ledgerTable">
      <thead>
        <tr>
          <th>Student ID</th>
          <th>Name</th>
          <th>Scholarship</th>
          <th>GWA</th>
          <th>Grades</th>
          <th>Enrollment</th>
          <th>Semester</th>
          <th>Status</th>
          <th style="text-align:right;">Actions</th>
        </tr>
      </thead>
      <tbody id="ledgerBody"></tbody>
    </table>

    <div class="pagination-bar" id="renewalPagination"></div>
  </main>
</div>

<!-- Evaluation detail modal -->
<div class="custom-modal-overlay" id="modalOverlay">
  <div class="custom-modal-card lg">
    <div class="custom-modal-header">
      <div>
        <h3 class="modal-name" id="modalName" style="font-size: 18px; font-weight: 700; color: #134e2a;">—</h3>
        <p class="modal-id font-mono" id="modalId" style="font-size: 13px; color: #6b7280; margin-top: 2px;">—</p>
      </div>
      <div style="display: flex; align-items: center; gap: 12px;">
        <span id="modalSemesterBadge" class="font-mono" style="font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 99px; border: 1px solid #d1d5db; background: #fff; color: #134e2a;" title="Set by the Active Semester in Settings">—</span>
        <button type="button" class="custom-modal-close" id="modalClose"><i data-lucide="x"></i></button>
      </div>
    </div>

    <div class="custom-modal-body">
      <div class="criteria-list" id="modalCriteria" style="margin-bottom: 20px;"></div>

      <div id="modalLockNote" style="display:none; background:#fffbeb; border:1px solid #fde68a; color:#92400e; border-radius:10px; padding:10px 14px; font-size:13px; line-height:1.4; margin-bottom:12px;"></div>

      <div class="remarks-box" id="modalRemarks" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-top: 12px;">
        <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 8px;">Evaluation Result</div>
        <span class="status-badge" id="modalSeal">—</span>
        <div class="remarks-text" id="modalRemarksText" style="font-size: 13.5px; color: #334155; margin-top: 8px;"></div>
      </div>
    </div>

    <div class="custom-modal-footer">
      <button type="button" class="btn-danger" id="terminateBtn">Terminate</button>
      <button type="button" class="btn-primary" id="renewBtn">Renew Scholarship</button>
    </div>
  </div>
</div>

<!-- Delete Confirm Modal -->
<div class="custom-modal-overlay" id="renDeleteOverlay">
  <div class="custom-modal-card sm">
    <div class="custom-modal-header">
      <div>
        <h3>Delete Scholar Entry</h3>
        <p>Confirm deletion from renewal ledger.</p>
      </div>
      <button type="button" class="custom-modal-close" id="renDeleteCloseBtn"><i data-lucide="x"></i></button>
    </div>
    <div class="custom-modal-body">
      <p style="font-size:14px; color:#4b5563;">Are you sure you want to delete renewal entry for <strong id="renDeleteTarget"></strong>?</p>
    </div>
    <div class="custom-modal-footer">
      <button type="button" class="btn-secondary" id="renDeleteCancelBtn">Cancel</button>
      <button type="button" class="btn-danger" id="renDeleteConfirmBtn">Delete Entry</button>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
