"use strict";
let loadedScholarsList = [];
// Everything the server returned; the Scholarship Type filter (incl. Dean's Listers) narrows it here.
let allScholarsList = [];
let editingScholarId = null;
let deletingScholarId = null;

const SCHOLARS_PAGE_SIZE = 100;
let scholarsCurrentPage = 1;

function getScholarEl(id) {
    return document.getElementById(id);
}

async function loadScholarsData() {
    try {
        const deptFilter = getScholarEl("filterDepartment");
        const yearFilter = getScholarEl("filterYear");
        const statusFilter = getScholarEl("filterStatus");
        const searchInput = getScholarEl("searchScholarInput");

        const deptVal = deptFilter ? deptFilter.value : "all";
        const yearVal = yearFilter ? yearFilter.value : "all";
        const statusVal = statusFilter ? statusFilter.value : "above";
        const searchVal = searchInput ? searchInput.value.trim() : "";

        const apiFunc = window.apiListScholars;
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

            const apiBase = window.API_BASE || "api";
            const res = await fetch(`${apiBase}/list_scholars.php?${params.toString()}`);
            const json = await res.json();
            loadedScholarsList = json.data || [];
        }

        allScholarsList = loadedScholarsList;
        applyScholarClientFilters();
    } catch (err) {
        console.error("Error loading scholars data:", err);
    }
}

// Scholarship Type filter, which also offers "Dean's Listers" (applied to the loaded list, no reload).
function applyScholarClientFilters() {
    const typeSel = getScholarEl("filterScholarType");
    if (typeSel) {
        const current = typeSel.value;
        const types = Array.from(new Set(allScholarsList.map((s) => (s.scholarshipType || "").trim()).filter((t) => t && t !== "Dean's List"))).sort();   // the "Dean's Listers" option covers it
        typeSel.innerHTML = '<option value="all">Scholarship Types</option><option value="deans">Dean&#39;s Listers</option>' + types.map((t) => `<option value="${t.replace(/"/g, "&quot;")}">${t}</option>`).join("");
        typeSel.value = current === "deans" || types.includes(current) ? current : "all";
    }
    const typeVal = typeSel ? typeSel.value : "all";
    loadedScholarsList = allScholarsList.filter((s) =>
        typeVal === "all" || (typeVal === "deans" ? !!s.deansLister : (s.scholarshipType || "").trim() === typeVal));
    renderScholarsTable();
}

// Export: the scholars shown right now (search and filters applied), Student ID through School Year.
function exportScholarsToCsv() {
    if (!loadedScholarsList.length) {
        alert("No scholars to export for the current filters.");
        return;
    }
    const gwa = (v) => (v === null || v === undefined ? "" : Number(v).toFixed(2));
    const out = [["Student ID", "Name", "Scholarship Type", "Department", "Grade Year", "1st Sem GWA", "2nd Sem GWA", "School Year"]];
    loadedScholarsList.forEach((s) => {
        out.push([s.student_id, s.name, s.scholarshipType || "", s.department, "Year " + s.year_level, gwa(s.gwa_first), gwa(s.gwa_second), s.school_year || ""]);
    });
    // "scholars-Dean-s-Listers-BS-Nursing-2026-10-03.xlsx" — names the filters that were applied.
    const picked = ["filterDepartment", "filterYear", "filterScholarType"]
        .map((id) => getScholarEl(id))
        .filter((el) => el && el.value !== "all")
        .map((el) => el.options[el.selectedIndex].text.replace(/[^A-Za-z0-9]+/g, "-").replace(/^-+|-+$/g, ""));
    window.downloadXlsx({ filename: ["scholars", ...picked, new Date().toISOString().slice(0, 10)].join("-"), sheet: "Scholars", rows: out, numberCols: [5, 6] });
}

function renderScholarsTable() {
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
        // GWA and the requirement come from the server: grades per semester, and the
        // GWA required by this scholar's own scholarship.
        const required = Number(s.thresholdRequirement ?? 1.50);
        const isMaintaining = s.maintainsGrade !== undefined ? !!s.maintainsGrade : Number(s.gwa) <= required;
        const statusText = s.displayStatus || s.status || (isMaintaining ? "Active" : "Removed");
        const badgeClass = statusText.toLowerCase() === "active" ? "badge-maintained" : "badge-removed";
        const semGwaPill = (v) => {
            if (v === null || v === undefined)
                return '<span class="gwa-pill gwa-none">\u2014</span>';
            return '<span class="gwa-pill ' + (Number(v) <= required ? "gwa-pass" : "gwa-fail") + '">' + Number(v).toFixed(2) + '</span>';
        };
        const gwaBasis = s.gwaSemester
            ? "GWA " + Number(s.gwa).toFixed(2) + " (" + s.gwaSemester + ") \u00b7 required \u2264 " + required.toFixed(2)
            : "No grades imported \u00b7 required \u2264 " + required.toFixed(2);

        tr.innerHTML = `
      <td class="row-select-cell"><input type="checkbox" class="row-select" data-id="${s.id}" aria-label="Select ${s.name}"></td>
      <td><strong class="font-mono">${s.student_id}</strong></td>
      <td><strong>${s.name}</strong>${s.deansLister ? '<span class="dl-tag">Dean\'s Lister</span>' : ""}</td>
      <td>${s.scholarshipType ? '<span class="sch-type">' + s.scholarshipType + '</span>' + (s.meritTier ? '<span class="status-sub">' + s.meritTier + '</span>' : '') : '<span class="status-sub">\u2014</span>'}</td>
      <td><span class="dept-tag">${s.department}</span></td>
      <td><span class="badge-year">Year ${s.year_level}</span></td>
      <td>${semGwaPill(s.gwa_first)}</td>
      <td>${semGwaPill(s.gwa_second)}</td>
      <td><span class="font-mono">${s.school_year || '2025-2026'}</span></td>
      <td>
        <span class="${badgeClass}" title="${gwaBasis}">${statusText}</span>
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

    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
}

function renderScholarsPagination(total) {
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

    wrap.querySelectorAll("button[data-page]").forEach((btn) => {
        btn.addEventListener("click", () => {
            const p = parseInt(btn.getAttribute("data-page") || "", 10);
            if (!isNaN(p) && p >= 1 && p <= totalPages) {
                scholarsCurrentPage = p;
                renderScholarsTable();
            }
        });
    });
}

function openScholarModal(isEdit = false) {
    const overlay = getScholarEl("scholarModalOverlay");
    const modalTitle = getScholarEl("scholarModalTitle");
    if (!isEdit) {
        editingScholarId = null;
        if (modalTitle) modalTitle.textContent = "Add New Scholar";
        const form = getScholarEl("scholarForm");
        if (form) form.reset();
    } else {
        if (modalTitle) modalTitle.textContent = "Edit Scholar Record";
    }
    if (overlay) overlay.classList.add("open");
}

function closeScholarModal() {
    const overlay = getScholarEl("scholarModalOverlay");
    if (overlay) overlay.classList.remove("open");
    editingScholarId = null;
}

window.editScholarEntry = async function (event, id) {
    if (event && typeof event.stopPropagation === "function") event.stopPropagation();
    const scholar = loadedScholarsList.find((item) => Number(item.id) === Number(id));
    if (!scholar) return;

    editingScholarId = Number(id);

    const fStudentId = getScholarEl("modalStudentId");
    const fName = getScholarEl("modalName");
    const fDepartment = getScholarEl("modalDepartment");
    const fYearLevel = getScholarEl("modalYearLevel");
    const fSchoolYear = getScholarEl("modalSchoolYear");
    const fRemarks = getScholarEl("modalRemarks");

    if (fStudentId) fStudentId.value = scholar.student_id || "";
    if (fName) fName.value = scholar.name || "";
    if (fDepartment) fDepartment.value = scholar.department || "BS Information Technology";
    if (fYearLevel) fYearLevel.value = String(scholar.year_level || 1);
    if (fSchoolYear) fSchoolYear.value = scholar.school_year || "2025-2026";
    if (fRemarks) fRemarks.value = scholar.remarks || "";

    openScholarModal(true);
};

window.confirmDeleteScholarEntry = function (event, id) {
    if (event && typeof event.stopPropagation === "function") event.stopPropagation();
    const scholar = loadedScholarsList.find((item) => Number(item.id) === Number(id));
    if (!scholar) return;

    deletingScholarId = Number(id);
    const targetName = getScholarEl("deleteScholarTargetName");
    const overlay = getScholarEl("deleteScholarConfirmOverlay");

    if (targetName) targetName.textContent = scholar.name;
    if (overlay) overlay.classList.add("open");
};

function closeDeleteScholarModal() {
    const overlay = getScholarEl("deleteScholarConfirmOverlay");
    if (overlay) overlay.classList.remove("open");
    deletingScholarId = null;
}

function initScholarsPage() {
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
                const apiFunc = window.apiDeleteScholar;
                if (typeof apiFunc === "function") {
                    await apiFunc(deletingScholarId);
                } else {
                    const apiBase = window.API_BASE || "api";
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

    const scholarForm = getScholarEl("scholarForm");
    if (scholarForm) {
        scholarForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            const fStudentId = getScholarEl("modalStudentId");
            const fName = getScholarEl("modalName");
            const fDepartment = getScholarEl("modalDepartment");
            const fYearLevel = getScholarEl("modalYearLevel");
            const fSchoolYear = getScholarEl("modalSchoolYear");
            const fRemarks = getScholarEl("modalRemarks");

            const data = {
                id: editingScholarId,
                student_id: fStudentId ? fStudentId.value.trim() : "",
                name: fName ? fName.value.trim() : "",
                department: fDepartment ? fDepartment.value : "BS Information Technology",
                year_level: fYearLevel ? Number(fYearLevel.value) : 1,
                school_year: fSchoolYear ? fSchoolYear.value.trim() : "2025-2026",
                remarks: fRemarks ? fRemarks.value.trim() : "",
            };

            try {
                const apiFunc = window.apiSaveScholar;
                if (typeof apiFunc === "function") {
                    await apiFunc(data);
                } else {
                    const apiBase = window.API_BASE || "api";
                    const formData = new FormData();
                    Object.entries(data).forEach(([k, v]) => formData.append(k, String(v ?? "")));
                    await fetch(`${apiBase}/save_scholar.php`, { method: "POST", body: formData });
                }
                closeScholarModal();
                await loadScholarsData();
            } catch (err) {
                alert(err.message || "Failed to save scholar.");
            }
        });
    }

    function resetScholarsPageAndLoad() {
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

    ["filterScholarType"].forEach((id) => {
        const el = getScholarEl(id);
        if (el) el.addEventListener("change", () => { scholarsCurrentPage = 1; applyScholarClientFilters(); });
    });

    const exportBtn = getScholarEl("exportScholarsBtn");
    if (exportBtn) exportBtn.addEventListener("click", exportScholarsToCsv);

    loadScholarsData();
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initScholarsPage);
} else {
    initScholarsPage();
}
