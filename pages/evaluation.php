<?php
$page_css = "evaluation.css";
$page_js = "evaluation.js";
 include __DIR__ . '/../includes/header.php';
?>

<div class="top-nav">
    <h2>Evaluation</h2>

    <?php include __DIR__ . '/../includes/navbar.php'; ?>
</div>
<div class="page">
<div class="evaluation-main-content">
  <div class="evaluation-card left">
    <div class="evaluatioon-left-head">

      <div class="toolbar">
        <select id="filterType"><option value="all">All Scholarship Types</option></select>
        <select id="filterStatus">
          <option value="all">All Status</option>
          <option value="review">For Review</option>
          <option value="interview">For Interview</option>
          <option value="approved">Approved</option>
          <option value="rejected">Rejected</option>
        </select>
        <div class="search-wrap">
          <input id="searchInput" placeholder="Search applicant...">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </div>
      </div>
    </div>

    <div class="table-wrap">
        <div class="table-card">
          <table class="applicants-table">
          <thead>
            <tr>
              <th>Student ID</th>
              <th>Name</th>
              <th>Scholarship type</th>
              <th>Status</th>
              <th>Date applied</th>
              <th class="actions-head">Actions</th>
            </tr>
          </thead>
          <tbody id="tableBody">

          </tbody>
        </table>
      </div>
<div class="toast" id="toast"></div>



<script src="applicant-evaluation.js"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>