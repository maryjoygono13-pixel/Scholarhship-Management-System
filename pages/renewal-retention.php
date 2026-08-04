<?php
$page_css = "renewal-retention.css";
$page_js = "renewal-retention.js";
include __DIR__ . '/../includes/header.php';
?>
<div class="top-nav">
    <h2>Renewal & Retention</h2>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
</div>
 <main class="page">

    <div class="summary-row" id="summaryRow">
      <div class="summary-card eligible">
        <div class="label">Eligible</div>
        <div class="value" id="countEligible">0</div>
      </div>
      <div class="summary-card at-risk">
        <div class="label">At-Risk</div>
        <div class="value" id="countAtRisk">0</div>
      </div>
      <div class="summary-card terminated">
        <div class="label">Terminated</div>
        <div class="value" id="countTerminated">0</div>
      </div>
      <div class="summary-card">
        <div class="label">Total Scholars</div>
        <div class="value" id="countTotal">0</div>
      </div>
    </div>

    <div class="toolbar">
      <input type="search" id="searchBox" placeholder="Search by name or student ID…">
      <select id="SchoolYearFilter">
        <option value="">All School Year</option>
        <option value="eligible">2024</option>
        <option value="at-risk">2025</option>
        <option value="terminated">2026</option>
      </select>

      <select id="semesterFilter">
        <option value="">All Semesters</option>
        <option value="first">First Semester</option>
        <option value="second">Second Semester</option>
      </select>

      <select id="scholarshipFilter">
        <option value="">All Schoalrship Type</option>
        <option value="eligible">Academic Scholarship</option>
        <option value="at-risk">Merit Scholarship</option>
        <option value="terminated">Endorsment Scholarships</option>
      </select>

      <select id="statusFilter">
        <option value="">All statuses</option>
        <option value="eligible">Eligible</option>
        <option value="at-risk">At-Risk</option>
        <option value="terminated">Terminated</option>
      </select>
    </div>

    <table class="ledger" id="ledgerTable">
      <thead>
        <tr>
          <th>Student ID</th>
          <th>Name</th>
          <th>GWA</th>
          <th>Grades</th>
          <th>Enrollment</th>
          <th>Status</th>
          <th>View</th>
        </tr>
      </thead>
      <tbody id="ledgerBody"></tbody>
    </table>
  </main>
</div>

<!-- Evaluation detail modal -->
<div class="modal-overlay" id="modalOverlay">
  <div class="modal">
    <button class="modal-close" id="modalClose" aria-label="Close">&times;</button>
    <div class="modal-header">
      <div>
        <div class="modal-name" id="modalName">—</div>
        <div class="modal-id" id="modalId">—</div>
      </div>
      <div class="term-tag">Term: 2025-2nd Sem</div>
    </div>

    <div class="criteria-list" id="modalCriteria"></div>

    <div class="remarks-box" id="modalRemarks">
      <div class="remarks-label">Result</div>
      <span class="seal" id="modalSeal">—</span>
      <div class="remarks-text" id="modalRemarksText"></div>
    </div>

    <div class="modal-actions">
      <button class="btn btn-primary" id="renewBtn">Renew Scholarship</button>
      <button class="btn btn-ghost" id="flagBtn">Flag for Review</button>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../includes/footer.php'; ?>
