<?php
$page_css = "scholarships.css";
$page_js = "scholarships.js";

include __DIR__ . '/../includes/header.php';
?>

<div class="top-nav">
    <h2>Scholarships</h2>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
</div>

<div class="page">



        <!-- Toolbar -->
        <div class="scholarships-toolbar">

            <div class="toolbar">

                <select id="filterType">
                    <option>All Scholarship Types</option>
                </select>

                <select id="filterStatus">
                    <option>All Status</option>
                </select>

                <div class="search-wrap">
                    <input type="text" placeholder="Search scholarship...">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                        stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </div>

            </div>

            <button class="btn-export">
                <i class='bx bx-export'></i>
                Export to Excel
            </button>

        </div>

        <div class="table-card">
        <!-- Table -->
        <div class="table-wrap">

            <table class="scholarships-table">

                <thead>
                    <tr>
                        <th>Scholarship Name</th>
                        <th>Type</th>
                        <th>Slots</th>
                        <th>Status</th>
                        <th>Actions</th>
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