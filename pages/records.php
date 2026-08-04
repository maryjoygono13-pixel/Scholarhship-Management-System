<?php
$page_css = "records.css";
$page_js = "records.js";

include __DIR__ . '/../includes/header.php';
?>

<div class="top-nav">
    <h2>Records</h2>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
</div>

<div class="page">

        <!-- Toolbar -->
        <div class="records-toolbar">
            <div class="toolbar">
                <select id="filterType">
                    <option>All Scholarship Types</option>
                </select>

                <select id="filterStatus">
                    <option>All Status</option>
                </select>

                <div class="search-wrap">
                    <input type="text" placeholder="Search applicant...">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </div>
            </div>

            <button class="btn-export">
                <i class='bx bx-export'></i>
                Export Records
            </button>
        </div>

        <div class="table-card">
        <!-- Table -->
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
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <!-- Database -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>