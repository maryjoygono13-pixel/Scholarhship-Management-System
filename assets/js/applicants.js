 // ---- Sample data — replace with your real applicants ----
let applicants = [
  { id: "2023-0001", name: "Ana Cruz", scholarship: "Merit", status: "Approved", dateApplied: "2026-06-02" },
  { id: "2023-0002", name: "Liam Reyes", scholarship: "Endorsement", status: "Under Evaluation", dateApplied: "2026-06-10" },
  { id: "2023-0003", name: "Sofia Tan", scholarship: "Dean's Lister", status: "Pending Requirements", dateApplied: "2026-06-15" },
  { id: "2023-0004", name: "Marco Diaz", scholarship: "Merit", status: "Rejected", dateApplied: "2026-06-18" }
];
// ------------------------------------------------------------

const tableBody = document.getElementById("tableBody");
const emptyState = document.getElementById("emptyState");

const searchName = document.getElementById("searchName");
const searchId = document.getElementById("searchId");
const filterScholarship = document.getElementById("filterScholarship");
const filterStatus = document.getElementById("filterStatus");
const filterDate = document.getElementById("filterDate");
const btnClearFilters = document.getElementById("btnClearFilters");

function statusClass(status) {
  return "status-" + status.toLowerCase().replace(/\s+/g, "-");
}

function renderTable() {
  const nameQuery = searchName.value.trim().toLowerCase();
  const idQuery = searchId.value.trim().toLowerCase();
  const scholarshipFilter = filterScholarship.value;
  const statusFilter = filterStatus.value;
  const dateFilter = filterDate.value;

  const filtered = applicants.filter(a => {
    const matchesName = a.name.toLowerCase().includes(nameQuery);
    const matchesId = a.id.toLowerCase().includes(idQuery);
    const matchesScholarship = !scholarshipFilter || a.scholarship === scholarshipFilter;
    const matchesStatus = !statusFilter || a.status === statusFilter;
    const matchesDate = !dateFilter || a.dateApplied === dateFilter;
    return matchesName && matchesId && matchesScholarship && matchesStatus && matchesDate;
  });

  tableBody.innerHTML = "";

  if (filtered.length === 0) {
    emptyState.classList.remove("hidden");
    return;
  }
  emptyState.classList.add("hidden");

  filtered.forEach(a => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${a.name}</td>
      <td>${a.id}</td>
      <td>${a.scholarship}</td>
      <td><span class="status-badge ${statusClass(a.status)}">${a.status}</span></td>
      <td>${a.dateApplied}</td>
      <td class="actions-col">
        <div class="actions">
          <button title="View" data-action="view" data-id="${a.id}">👁</button>
          <button title="Edit" data-action="edit" data-id="${a.id}">✏</button>
          <button title="Delete" data-action="delete" data-id="${a.id}">🗑</button>
          <button title="Evaluate" data-action="evaluate" data-id="${a.id}">✔</button>
          <button title="View submitted requirements" data-action="requirements" data-id="${a.id}">📄</button>
        </div>
      </td>
    `;
    tableBody.appendChild(tr);
  });
}

// ---- Row actions — wire these up to your real view/edit/evaluate screens ----
tableBody.addEventListener("click", (e) => {
  const btn = e.target.closest("button[data-action]");
  if (!btn) return;
  const id = btn.dataset.id;
  const applicant = applicants.find(a => a.id === id);

  switch (btn.dataset.action) {
    case "view":
      console.log("View applicant:", applicant);
      // e.g. openApplicantDetail(applicant)
      break;
    case "edit":
      console.log("Edit applicant:", applicant);
      // e.g. openEditForm(applicant)
      break;
    case "delete":
      if (confirm(`Delete ${applicant.name}? This cannot be undone.`)) {
        applicants = applicants.filter(a => a.id !== id);
        renderTable();
      }
      break;
    case "evaluate":
      console.log("Evaluate applicant:", applicant);
      // e.g. openEvaluationPanel(applicant)
      break;
    case "requirements":
      console.log("View requirements for:", applicant);
      // e.g. openRequirementsViewer(applicant)
      break;
  }
});

// ---- Search & filter events ----
[searchName, searchId, filterScholarship, filterStatus, filterDate].forEach(el => {
  el.addEventListener("input", renderTable);
});

btnClearFilters.addEventListener("click", () => {
  searchName.value = "";
  searchId.value = "";
  filterScholarship.value = "";
  filterStatus.value = "";
  filterDate.value = "";
  renderTable();
});

// ---- Add Applicant modal ----
const addModalOverlay = document.getElementById("addModalOverlay");
const addApplicantForm = document.getElementById("addApplicantForm");
const formError = document.getElementById("formError");

const fieldName = document.getElementById("fieldName");
const fieldId = document.getElementById("fieldId");
const fieldScholarship = document.getElementById("fieldScholarship");
const fieldStatus = document.getElementById("fieldStatus");
const fieldDate = document.getElementById("fieldDate");

function openAddModal() {
  addApplicantForm.reset();
  formError.classList.add("hidden");
  addModalOverlay.classList.remove("hidden");
  fieldName.focus();
}

function closeAddModal() {
  addModalOverlay.classList.add("hidden");
}

document.getElementById("btnAdd").addEventListener("click", openAddModal);
document.getElementById("btnCloseModal").addEventListener("click", closeAddModal);
document.getElementById("btnCancelAdd").addEventListener("click", closeAddModal);

// close when clicking outside the modal box
addModalOverlay.addEventListener("click", (e) => {
  if (e.target === addModalOverlay) closeAddModal();
});

addApplicantForm.addEventListener("submit", (e) => {
  e.preventDefault();

  const newApplicant = {
    id: fieldId.value.trim(),
    name: fieldName.value.trim(),
    scholarship: fieldScholarship.value,
    status: fieldStatus.value,
    dateApplied: fieldDate.value
  };

  // basic validation: no duplicate student IDs
  if (applicants.some(a => a.id === newApplicant.id)) {
    formError.textContent = "An applicant with this Student ID already exists.";
    formError.classList.remove("hidden");
    return;
  }

  applicants.push(newApplicant);
  renderTable();
  closeAddModal();
});


document.getElementById("btnImport").addEventListener("click", () => {
  console.log("Trigger Excel import");
  // e.g. use SheetJS (xlsx) to parse an uploaded .xlsx file into `applicants`
});

// Export dropdown toggle
const btnExport = document.getElementById("btnExport");
const exportMenu = document.getElementById("exportMenu");

btnExport.addEventListener("click", (e) => {
  e.stopPropagation();
  exportMenu.classList.toggle("hidden");
});

document.addEventListener("click", () => {
  exportMenu.classList.add("hidden");
});

document.getElementById("exportExcel").addEventListener("click", (e) => {
  e.preventDefault();
  console.log("Export applicants as Excel");
  // e.g. use SheetJS (xlsx) to build a workbook from `applicants`
});

document.getElementById("exportPdf").addEventListener("click", (e) => {
  e.preventDefault();
  console.log("Export applicants as PDF");
  // e.g. use a library like jsPDF to generate a PDF from `applicants`
});

// ---- Initial render ----
renderTable();