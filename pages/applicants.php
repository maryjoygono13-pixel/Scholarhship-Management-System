<?php
$page_css = 'applicants.css';
include __DIR__ . '/../includes/header.php';
?>

<div class="top-nav">
    <h2>Applicants</h2>
<?php include __DIR__ . '/../includes/navbar.php'; ?>
</div>

<div class="main-content">

    <div class="toolbar">
    <div class="toolbar-buttons">
    <button id="btnAdd" class="btn btn-primary">Add Applicant</button>
    <button id="btnImport" class="btn">Import (Excel)</button>
    <div class="dropdown">
    <button id="btnExport" class="btn">Export</button>
    <div id="exportMenu" class="dropdown-menu hidden">
    <a href="#" id="exportExcel">Export as Excel</a>
    <a href="#" id="exportPdf">Export as PDF</a>
    </div>
    </div>
    </div>
    </div>

<div id="searchFilterBar" class="search-filter-bar">
    <input type="text" id="searchName" placeholder="Search by name" />
    <input type="number"id="searchId"placeholder="Search by Student ID"/>

    <select id="filterScholarship">
    <option value="">All scholarships</option>
    <option value="Merit">Merit</option>
    <option value="Endorsement">Endorsement</option>
    <option value="Dean's Lister">Dean's Lister</option>
    </select>

    <select id="filterStatus">
    <option value="">All statuses</option>
    <option value="Approved">Approved</option>
    <option value="Under Evaluation">Under Evaluation</option>
    <option value="Pending Requirements">Pending Requirements</option>
    <option value="Rejected">Rejected</option>
    </select>
    <input type="date" id="filterDate" />
    <button id="btnClearFilters" class="btn btn-ghost">Clear</button>
    </div>

<div class="table-wrapper">
    <table id="applicantTable">
    <thead>
    <tr>
    <th>Name</th>
    <th>Student ID</th>
    <th>Scholarship</th>
    <th>Status</th>
    <th>Date Applied</th>
    <th class="actions-col">Actions</th>
    </tr>
    </thead>
    <tbody id="tableBody"></tbody>
    </table>
    <p id="emptyState" class="empty-state hidden">No applicants match your search or filters.</p>
</div>

<<div id="addModalOverlay" class="applicant-modal-overlay hidden">
<div class="applicant-modal">
    <div class="modal-header">
    <h2>Add Applicant</h2>
    <button id="btnCloseModal" class="modal-close">&times;</button>
    </div>

    <form id="addApplicantForm" class="modal-body">
    <label>
    Full name
    <input type="text" id="fieldName" required />
    </label>

    <label>
Student ID
<input
    type="text"
    id="fieldId"
    name="student_id"
    oninput="this.value = this.value.replace(/[^0-9]/g, '')"
    required
/>
</label>

    <label>
        Scholarship type
        <select id="fieldScholarship" required>
            <option value="" disabled selected>Select scholarship</option>
            <option value="Merit">Merit</option>
            <option value="Endorsement">Endorsement</option>
            <option value="Dean's Lister">Dean's Lister</option>
        </select>
    </label>

    <label>
        Status
        <select id="fieldStatus" required>
            <option value="Pending Requirements" selected>Pending Requirements</option>
            <option value="Under Evaluation">Under Evaluation</option>
            <option value="Approved">Approved</option>
            <option value="Rejected">Rejected</option>
        </select>
    </label>

    <label>
    Date applied
    <input type="date" id="fieldDate" required />
    </label>

    <p id="formError" class="form-error hidden"></p>

    <div class="modal-actions">
    <button type="button" id="btnCancelAdd" class="btn btn-ghost">Cancel</button>
    <button type="submit" class="btn btn-primary">Add Applicant</button>
    </div>
    </form>
</div>
</div>
</div>

<?php
$page_js = 'applicants.js';
include __DIR__ . '/../includes/footer.php';