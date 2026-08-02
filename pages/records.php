<?php

$page_css = "records.css";
$page_js = "records.js";
include __DIR__ . '/../includes/header.php';

?>

<div class="records-container">

    <!-- LEFT SIDE -->
    <div class="records-content">

        <!-- Toolbar -->
        <div class="records-toolbar">

            <div class="search-box">
                <i class='bx bx-search'></i>
                <input
                    type="text"
                    id="searchInput"
                    placeholder="Search by name or student ID..."
                >
            </div>

            <select id="scholarshipFilter">
                <option value="">All Scholarship Types</option>
                <option>Academic Merit</option>
                <option>Financial Need-Based</option>
                <option>Athletic Scholarship</option>
                <option>Community Service</option>
            </select>

            <select id="statusFilter">
                <option value="">All Status</option>
                <option>Approved</option>
                <option>At Risk</option>
                <option>Retained</option>
                <option>Rejected</option>
                <option>Terminated</option>
            </select>

            <select id="semesterFilter">
                <option value="">All Semesters</option>
                <option>1st Semester</option>
                <option>2nd Semester</option>
                <option>Summer</option>
            </select>

            <button class="export-btn">
                <i class='bx bx-export'></i>
                Export
            </button>

        </div>

        <!-- Table -->
        <div class="records-table-card">

            <table class="records-table">

                <thead>

                <tr>

                    <th>
                        <input type="checkbox">
                    </th>

                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Scholarship</th>
                    <th>Status</th>
                    <th>Semester</th>
                    <th>SY</th>
                    <th>Date Evaluated</th>
                    <th>Action</th>

                </tr>

                </thead>

                <tbody id="tableBody">

                    <!-- SAMPLE DATA -->

                    <tr>

                        <td><input type="checkbox"></td>

                        <td>20230001</td>

                        <td>Juan Dela Cruz</td>

                        <td>Academic Merit</td>

                        <td>
                            <span class="badge approved">
                                Approved
                            </span>
                        </td>

                        <td>1st Semester</td>

                        <td>2026-2027</td>

                        <td>Jul 25, 2026</td>

                        <td>
                            <button class="view-btn">
                                <i class='bx bx-show'></i>
                                View
                            </button>
                        </td>
                    </tr>

                </tbody>

            </table>

        </div>

        <!-- Pagination -->
        <div class="pagination">

            <span>
                Showing 1 to 10 of 356 records
            </span>

            <div class="pages">

                <button>
                    <i class='bx bx-chevron-left'></i>
                </button>

                <button class="active">
                    1
                </button>

                <button>2</button>

                <button>3</button>

                <button>...</button>

                <button>36</button>

                <button>
                    <i class='bx bx-chevron-right'></i>
                </button>

            </div>

        </div>

    </div>

    <!-- RIGHT PANEL -->
    <div class="record-details">

        <div class="details-header">

            <h3>
                Record Details
            </h3>

            <button class="close-panel">
                <i class='bx bx-x'></i>
            </button>

        </div>

        <div class="student-profile">

            <img
                src="../assets/images/profile.png"
                alt="Student"
                class="profile-image"
            >

            <div>

                <h2>
                    Juan Dela Cruz
                </h2>

                <span class="badge approved">
                    Approved
                </span>

                <p>
                    Student ID : 20230001
                </p>

                <p>
                    BS Information Technology
                </p>

                <p>
                    Academic Merit Scholarship
                </p>

            </div>

        </div>

        <!-- Tabs -->
        <div class="tabs">

            <button class="tab active">
                Overview
            </button>

            <button class="tab">
                Academic
            </button>

            <button class="tab">
                Documents
            </button>

            <button class="tab">
                Evaluation
            </button>

            <button class="tab">
                History
            </button>

        </div>

        <!-- Personal -->
        <div class="info-card">

            <h4>
                Personal Information
            </h4>

            <div class="info-grid">

                <div>
                    <label>Date of Birth</label>
                    <span>May 14, 2004</span>
                </div>

                <div>
                    <label>Gender</label>
                    <span>Male</span>
                </div>

                <div>
                    <label>Email</label>
                    <span>juan@email.com</span>
                </div>

                <div>
                    <label>Contact</label>
                    <span>09123456789</span>
                </div>

                <div class="full">

                    <label>Address</label>

                    <span>
                        Brgy. Sto. Niño, Maasin City,
                        Southern Leyte
                    </span>

                </div>

            </div>

        </div>

        <!-- Scholarship -->
        <div class="info-card">

            <h4>
                Scholarship Information
            </h4>

            <div class="info-grid">

                <div>

                    <label>Scholarship</label>

                    <span>
                        Academic Merit
                    </span>

                </div>

                <div>

                    <label>Date Applied</label>

                    <span>
                        Jul 10, 2026
                    </span>

                </div>

                <div>

                    <label>Semester</label>

                    <span>
                        1st Semester
                    </span>

                </div>

                <div>

                    <label>Date Evaluated</label>

                    <span>
                        Jul 25, 2026
                    </span>

                </div>

                <div class="full">

                    <label>Evaluator</label>

                    <span>
                        Scholarship Officer
                    </span>

                </div>

            </div>

        </div>

        <!-- Final Decision -->
        <div class="decision-card">

            <h4>
                Final Decision
            </h4>

            <div class="decision approved">

                <i class='bx bxs-check-circle'></i>

                <div>

                    <strong>
                        APPROVED
                    </strong>

                    <p>
                        Applicant meets all scholarship requirements.
                    </p>

                </div>

            </div>

        </div>

        <!-- Remarks -->
        <div class="info-card">

            <h4>
                Remarks
            </h4>

            <p>

                Qualified for Academic Merit Scholarship.

            </p>

        </div>

    </div>

</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>