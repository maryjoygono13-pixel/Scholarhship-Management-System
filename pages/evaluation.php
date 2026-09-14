    <?php
    require_once __DIR__ . '/../config/config.php';

    $current_page = 'evaluation';
    $page_title = "Evaluation";
    $page_css = "evaluation.css";
    $page_js = "evaluation.js";
    $extra_js = ["curriculum-data.js"];
    include __DIR__ . '/../includes/header.php';
    ?>

    <div class="page">
        <div class="table-header-toolbar">
            <div class="toolbar">
                <div class="search-wrap">
                    <input id="searchInput" placeholder="Search applicant...">
                    <i data-lucide="search"></i>
                </div>
                <div class="select-wrap">
                    <select id="filterType"><option value="all">All Scholarship Types</option></select>
                    <i data-lucide="chevron-down"></i>
                </div>

            <div class="header-actions">
                <input type="file" id="gradeFile" hidden accept=".csv,.xlsx,.xls">
                <button type="button" class="btn-primary btn-no-anim" id="evalGradeHeaderBtn">
                    <i data-lucide="file-spreadsheet"></i>
                    Import Academic
                </button>

                <input type="file" id="enrollmentFile" hidden accept=".csv,.xlsx,.xls">
                <button type="button" class="btn-primary btn-no-anim" id="evalEnrollmentHeaderBtn">
                    <i data-lucide="user-check"></i>
                    Import Enrollment
                </button>
            </div>
        </div>

        <div class="table-wrap">
            <div class="table-card" id="tableWrap">
                <!-- Dynamic Evaluation Table rendered by JS -->
            </div>
        </div>
    </div>

    <!-- Evaluation Review Modal Overlay -->
    <div class="custom-modal-overlay" id="evalModalOverlay">
        <div class="custom-modal-card lg" id="rightPanel" style="max-width: 680px; width: 100%; height: 680px; max-height: calc(100vh - 48px); display: flex; flex-direction: column;">
            <!-- Rendered dynamically by evaluation.ts -->
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>