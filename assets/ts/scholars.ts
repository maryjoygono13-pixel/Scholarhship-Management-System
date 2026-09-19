interface ScholarRecord {
  id?: number;
  student_id: string;
  name: string;
  department: string;
  year_level: number;
  gwa: number;
  status: string;
  school_year: string;
  remarks?: string;
  maintainsGrade?: boolean;
}

let loadedScholarsList: ScholarRecord[] = [];
let editingScholarId: number | null = null;
let deletingScholarId: number | null = null;

const SCHOLARS_PAGE_SIZE = 10;
let scholarsCurrentPage = 1;

function getScholarEl<T extends HTMLElement = HTMLElement>(id: string): T | null {
  return document.getElementById(id) as T | null;
}

async function loadScholarsData(): Promise<void> {
  try {
    const deptFilter = getScholarEl<HTMLSelectElement>("filterDepartment");
    const yearFilter = getScholarEl<HTMLSelectElement>("filterYear");
    const statusFilter = getScholarEl<HTMLSelectElement>("filterStatus");
    const searchInput = getScholarEl<HTMLInputElement>("searchScholarInput");

    const deptVal = deptFilter ? deptFilter.value : "all";
    const yearVal = yearFilter ? yearFilter.value : "all";
    const statusVal = statusFilter ? statusFilter.value : "above";
    const searchVal = searchInput ? searchInput.value.trim() : "";

    const apiFunc = (window as any).apiListScholars;
    if (typeof apiFunc === "function") {
      loadedScholarsList = await apiFunc(
        deptVal === "all" ? "" : deptVal,
        yearVal === "all" ? "" : yearVal,
        statusVal,
        searchVal
      );
    } else {
      const params = new URLSearchParams();
      if (deptVal !== "all") params.append("department", deptVal);
      if (yearVal !== "all") params.append("year_level", yearVal);
      if (statusVal) params.append("status", statusVal);
      if (searchVal) params.append("search", searchVal);

      const apiBase = (window as any).API_BASE || "api";
      const res = await fetch(`${apiBase}/list_scholars.php?${params.toString()}`);
      const json = await res.json();
      loadedScholarsList = json.data || [];
    }

    renderScholarsTable();
  } catch (err) {
    console.error("Error loading scholars data:", err);
  }
}

function renderScholarsTable(): void {
  const tbody = getScholarEl("scholarsTableBody");
  const emptyState = getScholarEl("scholarsEmptyState");
  if (!tbody) return;

  tbody.innerHTML = "";

  if (!loadedScholarsList || loadedScholarsList.length === 0) {
    if (emptyState) emptyState.style.display = "block";
    renderScholarsPagination(0);
    return;
  }

  if (emptyState) emptyState.style.display = "none";

  const totalPages = Math.max(1, Math.ceil(loadedScholarsList.length / SCHOLARS_PAGE_SIZE));
  if (scholarsCurrentPage > totalPages) scholarsCurrentPage = totalPages;
  if (scholarsCurrentPage < 1) scholarsCurrentPage = 1;
  const pageItems = loadedScholarsList.slice((scholarsCurrentPage - 1) * SCHOLARS_PAGE_SIZE, scholarsCurrentPage * SCHOLARS_PAGE_SIZE);

  pageItems.forEach((s) => {
    const tr = document.createElement("tr");
    const isMaintaining = Number(s.gwa) <= 1.50;
    const statusText = s.status || (isMaintaining ? "Active" : "Removed");
    const badgeClass = isMaintaining && statusText.toLowerCase() === "active" ? "badge-maintained" : "badge-removed";
    const gwaClass = isMaintaining ? "gwa-pass" : "gwa-fail";

    tr.innerHTML = `
      <td><strong class="font-mono">${s.student_id}</strong></td>
      <td><strong>${s.name}</strong></td>
      <td><span class="dept-tag">${s.department}</span></td>
      <td><span class="badge-year">Year ${s.year_level}</span></td>
      <td><span class="gwa-pill ${gwaClass}">${Number(s.gwa).toFixed(2)}</span></td>
      <td><span class="font-mono">${s.school_year || '2025-2026'}</span></td>
      <td>
        <span class="${badgeClass}">
          ${statusText} ${isMaintaining ? '(<= 1.50)' : '(Below 1.50)'}
        </span>
      </td>
      <td class="actions-cell">
        <button type="button" class="btn-icon-action edit" title="Edit Scholar" onclick="editScholarEntry(event, ${s.id})">
          <i data-lucide="pencil"></i>
        </button>
        <button type="button" class="btn-icon-action delete" title="Delete Scholar" onclick="confirmDeleteScholarEntry(event, ${s.id})">
          <i data-lucide="trash-2"></i>
        </button>
      </td>
    `;

    tbody.appendChild(tr);
  });

  renderScholarsPagination(loadedScholarsList.length);

  if (typeof (window as any).lucide !== "undefined") {
    (window as any).lucide.createIcons();
  }
}

function renderScholarsPagination(total: number): void {
  const wrap = getScholarEl("scholarsPagination");
  if (!wrap) return;

  const totalPages = Math.max(1, Math.ceil(total / SCHOLARS_PAGE_SIZE));
  if (scholarsCurrentPage > totalPages) scholarsCurrentPage = totalPages;
  if (scholarsCurrentPage < 1) scholarsCurrentPage = 1;

  if (total === 0) {
    wrap.innerHTML = "";
    return;
  }

  const start = (scholarsCurrentPage - 1) * SCHOLARS_PAGE_SIZE + 1;
  const end = Math.min(scholarsCurrentPage * SCHOLARS_PAGE_SIZE, total);

  const buttons = `<button type="button" class="active" data-page="${scholarsCurrentPage}" disabled>${scholarsCurrentPage}</button>`;

  wrap.innerHTML =
    `<span>Showing ${start}–${end} of ${total} entries</span>` +
    `<div class="page-btns">` +
    `<button type="button" data-page="${scholarsCurrentPage - 1}" ${scholarsCurrentPage <= 1 ? "disabled" : ""}>Prev</button>` +
    buttons +
    `<button type="button" data-page="${scholarsCurrentPage + 1}" ${scholarsCurrentPage >= totalPages ? "disabled" : ""}>Next</button>` +
    `</div>`;

  wrap.querySelectorAll<HTMLButtonElement>("button[data-page]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const p = parseInt(btn.getAttribute("data-page") || "", 10);
      if (!isNaN(p) && p >= 1 && p <= totalPages) {
        scholarsCurrentPage = p;
        renderScholarsTable();
      }
    });
  });
}

function openScholarModal(isEdit: boolean = false): void {
  const overlay = getScholarEl("scholarModalOverlay");
  const modalTitle = getScholarEl("scholarModalTitle");
  if (!isEdit) {
    editingScholarId = null;
    if (modalTitle) modalTitle.textContent = "Add New Scholar";
    const form = getScholarEl<HTMLFormElement>("scholarForm");
    if (form) form.reset();
  } else {
    if (modalTitle) modalTitle.textContent = "Edit Scholar Record";
  }
  if (overlay) overlay.classList.add("open");
}

function closeScholarModal(): void {
  const overlay = getScholarEl("scholarModalOverlay");
  if (overlay) overlay.classList.remove("open");
  editingScholarId = null;
}

(window as any).editScholarEntry = async function (event: Event, id: number): Promise<void> {
  if (event && typeof event.stopPropagation === "function") event.stopPropagation();
  const scholar = loadedScholarsList.find((item) => Number(item.id) === Number(id));
  if (!scholar) return;

  editingScholarId = Number(id);

  const fStudentId = getScholarEl<HTMLInputElement>("modalStudentId");
  const fName = getScholarEl<HTMLInputElement>("modalName");
  const fDepartment = getScholarEl<HTMLSelectElement>("modalDepartment");
  const fYearLevel = getScholarEl<HTMLSelectElement>("modalYearLevel");
  const fGwa = getScholarEl<HTMLInputElement>("modalGwa");
  const fSchoolYear = getScholarEl<HTMLInputElement>("modalSchoolYear");
  const fRemarks = getScholarEl<HTMLTextAreaElement>("modalRemarks");

  if (fStudentId) fStudentId.value = scholar.student_id || "";
  if (fName) fName.value = scholar.name || "";
  if (fDepartment) fDepartment.value = scholar.department || "Information Technology";
  if (fYearLevel) fYearLevel.value = String(scholar.year_level || 1);
  if (fGwa) fGwa.value = String(scholar.gwa || 1.50);
  if (fSchoolYear) fSchoolYear.value = scholar.school_year || "2025-2026";
  if (fRemarks) fRemarks.value = scholar.remarks || "";

  openScholarModal(true);
};

(window as any).confirmDeleteScholarEntry = function (event: Event, id: number): void {
  if (event && typeof event.stopPropagation === "function") event.stopPropagation();
  const scholar = loadedScholarsList.find((item) => Number(item.id) === Number(id));
  if (!scholar) return;

  deletingScholarId = Number(id);
  const targetName = getScholarEl("deleteScholarTargetName");
  const overlay = getScholarEl("deleteScholarConfirmOverlay");

  if (targetName) targetName.textContent = scholar.name;
  if (overlay) overlay.classList.add("open");
};

function closeDeleteScholarModal(): void {
  const overlay = getScholarEl("deleteScholarConfirmOverlay");
  if (overlay) overlay.classList.remove("open");
  deletingScholarId = null;
}

function initScholarsPage(): void {
  const addBtn = getScholarEl("addScholarBtn");
  if (addBtn) addBtn.addEventListener("click", () => openScholarModal(false));

  const closeBtn = getScholarEl("scholarModalCloseBtn");
  if (closeBtn) closeBtn.addEventListener("click", closeScholarModal);

  const cancelBtn = getScholarEl("scholarModalCancelBtn");
  if (cancelBtn) cancelBtn.addEventListener("click", closeScholarModal);

  const overlay = getScholarEl("scholarModalOverlay");
  if (overlay) {
    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) closeScholarModal();
    });
  }

  const deleteOverlay = getScholarEl("deleteScholarConfirmOverlay");
  if (deleteOverlay) {
    deleteOverlay.addEventListener("click", (e) => {
      if (e.target === deleteOverlay) closeDeleteScholarModal();
    });
  }

  const deleteCloseBtn = getScholarEl("deleteScholarCloseBtn");
  if (deleteCloseBtn) deleteCloseBtn.addEventListener("click", closeDeleteScholarModal);

  const deleteCancelBtn = getScholarEl("deleteScholarCancelBtn");
  if (deleteCancelBtn) deleteCancelBtn.addEventListener("click", closeDeleteScholarModal);

  const deleteConfirmBtn = getScholarEl("deleteScholarConfirmBtn");
  if (deleteConfirmBtn) {
    deleteConfirmBtn.addEventListener("click", async () => {
      if (!deletingScholarId) return;
      try {
        const apiFunc = (window as any).apiDeleteScholar;
        if (typeof apiFunc === "function") {
          await apiFunc(deletingScholarId);
        } else {
          const apiBase = (window as any).API_BASE || "api";
          const formData = new FormData();
          formData.append("id", String(deletingScholarId));
          await fetch(`${apiBase}/delete_scholar.php`, { method: "POST", body: formData });
        }
        closeDeleteScholarModal();
        await loadScholarsData();
      } catch (err) {
        closeDeleteScholarModal();
        alert("Failed to delete scholar entry.");
      }
    });
  }

  const scholarForm = getScholarEl<HTMLFormElement>("scholarForm");
  if (scholarForm) {
    scholarForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const fStudentId = getScholarEl<HTMLInputElement>("modalStudentId");
      const fName = getScholarEl<HTMLInputElement>("modalName");
      const fDepartment = getScholarEl<HTMLSelectElement>("modalDepartment");
      const fYearLevel = getScholarEl<HTMLSelectElement>("modalYearLevel");
      const fGwa = getScholarEl<HTMLInputElement>("modalGwa");
      const fSchoolYear = getScholarEl<HTMLInputElement>("modalSchoolYear");
      const fRemarks = getScholarEl<HTMLTextAreaElement>("modalRemarks");

      const data = {
        id: editingScholarId,
        student_id: fStudentId ? fStudentId.value.trim() : "",
        name: fName ? fName.value.trim() : "",
        department: fDepartment ? fDepartment.value : "Information Technology",
        year_level: fYearLevel ? Number(fYearLevel.value) : 1,
        gwa: fGwa ? Number(fGwa.value) : 1.50,
        school_year: fSchoolYear ? fSchoolYear.value.trim() : "2025-2026",
        remarks: fRemarks ? fRemarks.value.trim() : "",
      };

      try {
        const apiFunc = (window as any).apiSaveScholar;
        if (typeof apiFunc === "function") {
          await apiFunc(data);
        } else {
          const apiBase = (window as any).API_BASE || "api";
          const formData = new FormData();
          Object.entries(data).forEach(([k, v]) => formData.append(k, String(v ?? "")));
          await fetch(`${apiBase}/save_scholar.php`, { method: "POST", body: formData });
        }
        closeScholarModal();
        await loadScholarsData();
      } catch (err: any) {
        alert(err.message || "Failed to save scholar.");
      }
    });
  }

  function resetScholarsPageAndLoad(): void {
    scholarsCurrentPage = 1;
    loadScholarsData();
  }

  const filterDept = getScholarEl("filterDepartment");
  if (filterDept) filterDept.addEventListener("change", resetScholarsPageAndLoad);

  const filterYear = getScholarEl("filterYear");
  if (filterYear) filterYear.addEventListener("change", resetScholarsPageAndLoad);

  const filterStatus = getScholarEl("filterStatus");
  if (filterStatus) filterStatus.addEventListener("change", resetScholarsPageAndLoad);

  const searchInput = getScholarEl("searchScholarInput");
  if (searchInput) searchInput.addEventListener("input", resetScholarsPageAndLoad);

  loadScholarsData();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initScholarsPage);
} else {
  initScholarsPage();
}
