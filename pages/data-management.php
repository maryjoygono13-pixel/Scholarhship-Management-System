<?php

$page_css = "data-management.css";
$page_js = "data-management.js";

include __DIR__ . '/../includes/header.php';

?>

<div class="top-nav">
    <h2>Data Management</h2>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
</div>


<div class="page-container">

    <!-- LEFT COLUMN -->
    <div class="management-column">
        <div class="import-card">
            <h2>Import Academic Records</h2>
            <input type="file" id="gradeFile" hidden>

            <div class="button-group">
                <button type="button" id="gradeBtn">
                    Choose File
                </button>

                <button type="button" class="import-btn">
                    Import
                </button>
            </div>

            <div class="selected-file" id="gradeSelectedFile">
                <span id="gradeFileName">
                    No file selected
                </span>
                <button type="button" id="gradeDeleteBtn">
                    <i class='bx bx-x'></i>
                </button>
            </div>
        </div>

        <div class="table-card">
            <h3>Imported Grade Files</h3>
            
            <table>
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <!-- Database -->
                </tbody>

            </table>
        </div>

    </div>



    <!-- RIGHT COLUMN -->
    <div class="management-column">

        <div class="import-card">
            <h2>Import Enrollment Records</h2>

            <input type="file" id="enrollmentFile" hidden>

            <div class="button-group">
                <button type="button" id="enrollmentBtn">
                    Choose File
                </button>

                <button type="button" class="import-btn">
                    Import
                </button>
            </div>


            <div class="selected-file" id="enrollmentSelectedFile">

                <span id="enrollmentFileName">
                    No file selected
                </span>

                <button type="button" id="enrollmentDeleteBtn">
                    <i class='bx bx-x'></i>
                </button>

            </div>

        </div>


        <div class="table-card">
            <h3>Imported Enrollment Files</h3>

            <table>
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    <!-- Database -->
                </tbody>

            </table>
        </div>

    </div>

</div>


<?php include __DIR__ . '/../includes/footer.php'; ?>