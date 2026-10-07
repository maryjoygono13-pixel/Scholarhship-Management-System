"use strict";
document.addEventListener("DOMContentLoaded", () => {
    const tableBody = document.getElementById("tableBody");
    const searchInput = document.querySelector(".search-wrap input");
    const filterType = document.getElementById("filterType");
    const filterStatus = document.getElementById("filterStatus");
    const filterSemester = document.getElementById("filterSemester");
    const filterSy = document.getElementById("filterSy");
    const filterProgram = document.getElementById("filterProgram");
    const filterYearLevel = document.getElementById("filterYearLevel");
    const exportBtn = document.getElementById("exportRecordsBtn");
    const recFormOverlay = document.getElementById("recFormOverlay");
    const recFormTitle = document.getElementById("recFormTitle");
    const recFormCloseBtn = document.getElementById("recFormCloseBtn");
    const recFormCancelBtn = document.getElementById("recFormCancelBtn");
    const recForm = document.getElementById("recForm");
    const recId = document.getElementById("recId");
    const recStudentId = document.getElementById("recStudentId");
    const recName = document.getElementById("recName");
    const recType = document.getElementById("recType");
    const recStatus = document.getElementById("recStatus");
    const recRemarks = document.getElementById("recRemarks");
    const recViewOverlay = document.getElementById("recViewOverlay");
    const recViewCloseBtn = document.getElementById("recViewCloseBtn");
    const recViewCloseBtn2 = document.getElementById("recViewCloseBtn2");
    const recViewEditBtn = document.getElementById("recViewEditBtn");
    const recViewBody = document.getElementById("recViewBody");
    const recDeleteOverlay = document.getElementById("recDeleteOverlay");
    const recDeleteCloseBtn = document.getElementById("recDeleteCloseBtn");
    const recDeleteCancelBtn = document.getElementById("recDeleteCancelBtn");
    const recDeleteConfirmBtn = document.getElementById("recDeleteConfirmBtn");
    const recDeleteTarget = document.getElementById("recDeleteTarget");
    let recordsData = [];
    let deletingRecordId = null;
    let viewingRecord = null;
    let activeRecordTab = "overview";
    // No page limit: every matching record is listed on one page.
    const RECORDS_PAGE_SIZE = Infinity;
    let recordsCurrentPage = 1;
    // Lets the multi-select delete (assets/js/bulk-delete.js) refresh the list afterwards.
    window.reloadRecords = () => loadRecords();
    async function loadRecords() {
        try {
            const apiPath = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";
            const res = await fetch(`${apiPath}/list_records.php`);
            const json = await res.json();
            if (json.success) {
                recordsData = json.data;
                populateFilterTypes();
                renderRecords();
            }
        }
        catch (e) {
            console.error("Failed to load records:", e);
        }
    }
    // "1st Semester" / "First Semester" / "2nd" / "Summer Term" -> "1st" | "2nd" | "summer"
    function semesterKey(value) {
        const v = (value || "").toLowerCase();
        if (v.includes("summer"))
            return "summer";
        return (v.includes("2") || v.includes("second")) ? "2nd" : "1st";
    }
    // The status a record shows and is filtered by: "renewed" / "terminated" (decided in Renewal &
    // Retention) or its own status (pending / approved / rejected).
    function recordStatusKey(r) {
        if (r.renewed) return "renewed";
        if (r.terminated) return "terminated";
        return String(r.status || "").toLowerCase();
    }
    function recordStatusText(r) {
        if (r.renewed) return "Renewed";
        if (r.terminated) return "Terminated";
        return recordStatusLabel(r.status);
    }
    // "approved"/"rejected"/"pending" (as stored) -> the wording shown on the Status badge.
    function recordStatusLabel(status) {
        const labels = { approved: "Approved", rejected: "Declined", pending: "Pending" };
        return labels[(status || "").toLowerCase()] || status;
    }
    function populateSchoolYearFilter() {
        if (!filterSy)
            return;
        const current = filterSy.value;
        const years = Array.from(new Set(recordsData.map((r) => r.displaySy || r.sy))).filter(Boolean).sort().reverse();
        filterSy.innerHTML = '<option value="all">School Years</option>' +
            years.map((y) => '<option value="' + y + '">' + y + '</option>').join("");
        filterSy.value = years.includes(current) ? current : "all";
    }
    function populateFilterTypes() {
        populateSchoolYearFilter();
        if (!filterType)
            return;
        const types = Array.from(new Set(recordsData.map((r) => r.scholarshipType))).filter((t) => t && t !== "Dean's List");   // the "Dean's Listers" option covers it
        // "Dean's Listers": newest graded semester GWA 1.50 or better, no subject grade of 2.00 or worse.
        filterType.innerHTML = '<option value="all">Scholarship Types</option><option value="deans">Dean&#39;s Listers</option>';
        types.forEach(t => {
            const opt = document.createElement("option");
            opt.value = t;
            opt.textContent = typeAcronym(t);
            filterType.appendChild(opt);
        });
    }
    function typeAcronym(type) {
        if (!type)
            return "";
        const match = type.match(/^([^(]+)\(/);
        return match ? match[1].trim() : type.trim();
    }
    function dateOnly(value) {
        if (!value)
            return "";
        return value.split(" ")[0].split("T")[0];
    }
    // A–Z by surname, then first name (the table and the export use the same order).
    function sortBySurname(list) {
      const key = (r) => [String(r.lastName || r.name || ""), String(r.firstName || "")];
      return list.slice().sort((a, b) => {
        const [al, af] = key(a);
        const [bl, bf] = key(b);
        return al.localeCompare(bl, "en", { sensitivity: "base" }) || af.localeCompare(bf, "en", { sensitivity: "base" });
      });
    }

    function getFilteredRecords() {
        const query = searchInput ? searchInput.value.toLowerCase().trim() : "";
        const typeVal = filterType ? filterType.value.toLowerCase() : "all";
        const statusVal = filterStatus ? filterStatus.value.toLowerCase() : "all";
        const semVal = filterSemester ? filterSemester.value : "all";
        const syVal = filterSy ? filterSy.value : "all";
    const progVal = filterProgram ? filterProgram.value : "all";
    const yearVal = filterYearLevel ? filterYearLevel.value : "all";
        return sortBySurname(recordsData.filter((r) => {
            // Name, Student ID, department, scholarship type, year level, semester or school year.
            const matchQuery = !query || [r.name, r.studentId, r.department, r.programCode, r.scholarshipType, r.yearLevel, r.currentSemester || r.semester, r.displaySy || r.sy].join(" ").toLowerCase().includes(query);
            const matchType = typeVal === "all" || (typeVal === "deans" ? !!r.deansLister : r.scholarshipType.toLowerCase() === typeVal);
            const matchStatus = statusVal === "all" || statusVal === "all status" || recordStatusKey(r) === statusVal;
            const matchSem = semVal === "all" || semesterKey(r.currentSemester || r.semester) === semVal;
            const matchSy = syVal === "all" || (r.displaySy || r.sy) === syVal;
      const matchProg = progVal === "all" || r.programCode === progVal;
      const matchYear = yearVal === "all" || String(r.yearLevel || "").toLowerCase() === yearVal.toLowerCase();
            return matchQuery && matchType && matchStatus && matchSem && matchSy && matchProg && matchYear;
        }));
    }
    function renderPaginationBar(total) {
        const wrap = document.getElementById("recordsPagination");
        if (!wrap)
            return;
        const totalPages = Math.max(1, Math.ceil(total / RECORDS_PAGE_SIZE));
        if (recordsCurrentPage > totalPages)
            recordsCurrentPage = totalPages;
        if (recordsCurrentPage < 1)
            recordsCurrentPage = 1;
        if (total === 0) {
            wrap.innerHTML = "";
            return;
        }
        if (totalPages <= 1) {
            wrap.innerHTML = `<span>Showing all ${total} entr${total === 1 ? "y" : "ies"}</span>`;
            return;
        }
        const start = (recordsCurrentPage - 1) * RECORDS_PAGE_SIZE + 1;
        const end = Math.min(recordsCurrentPage * RECORDS_PAGE_SIZE, total);
        const buttons = `<button type="button" class="active" data-page="${recordsCurrentPage}" disabled>${recordsCurrentPage}</button>`;
        wrap.innerHTML =
            `<span>Showing ${start}–${end} of ${total} entries</span>` +
            `<div class="page-btns">` +
            `<button type="button" data-page="${recordsCurrentPage - 1}" ${recordsCurrentPage <= 1 ? "disabled" : ""}>Prev</button>` +
            buttons +
            `<button type="button" data-page="${recordsCurrentPage + 1}" ${recordsCurrentPage >= totalPages ? "disabled" : ""}>Next</button>` +
            `</div>`;
        wrap.querySelectorAll("button[data-page]").forEach((btn) => {
            btn.addEventListener("click", () => {
                const p = parseInt(btn.getAttribute("data-page") || "", 10);
                if (!isNaN(p) && p >= 1 && p <= totalPages) {
                    recordsCurrentPage = p;
                    renderRecords();
                }
            });
        });
    }
    function renderRecords() {
        if (!tableBody)
            return;
        const filtered = getFilteredRecords();
        tableBody.innerHTML = "";
        if (filtered.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="10" style="text-align:center; padding: 24px; color: #6b7280;">No records found.</td></tr>`;
            renderPaginationBar(0);
            return;
        }
        const totalPages = Math.max(1, Math.ceil(filtered.length / RECORDS_PAGE_SIZE));
        if (recordsCurrentPage > totalPages)
            recordsCurrentPage = totalPages;
        if (recordsCurrentPage < 1)
            recordsCurrentPage = 1;
        const pageItems = filtered.slice((recordsCurrentPage - 1) * RECORDS_PAGE_SIZE, recordsCurrentPage * RECORDS_PAGE_SIZE);
        pageItems.forEach((r) => {
            const tr = document.createElement("tr");
            const badgeClass = r.terminated ? "badge-rejected" : (r.status === "approved" ? "badge-approved" : (r.status === "rejected" ? "badge-rejected" : "badge-pending"));
            tr.innerHTML = `
        <td class="row-select-cell"><input type="checkbox" class="row-select" data-id="${r.id}" aria-label="Select ${r.name}"></td>
        <td><strong class="font-mono">${r.studentId}</strong></td>
        <td>${r.name}${r.deansLister ? '<span class="dl-tag">Dean\'s Lister</span>' : ""}</td>
        <td>${r.department || "—"}</td>
        <td>${typeAcronym(r.scholarshipType)}</td>
        <td><span class="status-badge ${badgeClass}">${recordStatusText(r)}</span></td>
        <td>${r.currentSemester || r.semester}</td>
        <td><span class="font-mono">${r.displaySy || r.sy}</span></td>
        <td><span class="font-mono">${dateOnly(r.dateEvaluated)}</span></td>
        <td class="actions-cell">
          <button type="button" class="btn-icon-action edit" title="Edit Record" onclick="editRecord(event, ${r.id})">
            <i data-lucide="pencil"></i>
          </button>
          <button type="button" class="btn-icon-action delete" title="Delete Record" onclick="confirmDeleteRecord(event, ${r.id})">
            <i data-lucide="trash-2"></i>
          </button>
        </td>
      `;
            tr.addEventListener("click", () => openViewModal(r));
            tableBody.appendChild(tr);
        });
        renderPaginationBar(filtered.length);
        if (typeof lucide !== "undefined")
            lucide.createIcons();
    }
    function esc(str) {
        const d = document.createElement("div");
        d.textContent = str == null ? "" : String(str);
        return d.innerHTML;
    }
    function gradeStatusCell(grade) {
        if (grade === undefined || grade === null) {
            return '<span class="badge badge-pending">Not yet graded</span>';
        }
        const passed = Number(grade) <= 3.00;
        return '<span class="font-mono" style="font-weight:600; margin-right:8px;">' + Number(grade).toFixed(2) + '</span>' +
            '<span class="badge ' + (passed ? 'badge-approved' : 'badge-rejected') + '">' + (passed ? 'Passed' : 'Failed') + '</span>';
    }
    function buildSemesterSubjectBlock(subjects, label, grades, gwa) {
        if (!subjects.length) return "";
        const g = grades || {};
        const rows = subjects
            .map((s) => '<tr><td><b class="font-mono">' + esc(s.code) + '</b></td><td>' + esc(s.name) + '</td><td>' + gradeStatusCell(g[s.code]) + '</td></tr>')
            .join("");
        return (
            '<div style="margin-bottom:14px;">' +
            '<div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.03em; color:#134e2a; margin-bottom:6px; display:flex; justify-content:space-between;"><span>' + esc(label) + '</span><span class="font-mono" style="text-transform:none;">GWA ' + (gwa == null ? "\u2014" : Number(gwa).toFixed(2)) + '</span></div>' +
            '<div style="border:1px solid #e2e8f0; border-radius:10px; overflow:hidden;">' +
            '<table class="applicants-table" style="font-size:13px; margin:0; table-layout:fixed; width:100%;">' +
            '<thead><tr><th style="width:18%;">Code</th><th style="width:57%;">Subject Description</th><th style="width:25%;">Status</th></tr></thead>' +
            '<tbody>' + rows + '</tbody></table></div></div>'
        );
    }
    function initials(name) {
        return (name || "").split(" ").map(n => n[0]).filter(Boolean).slice(0, 2).join("").toUpperCase();
    }
    /*
     * Some older seeded records stored the year level baked into the
     * program string itself (e.g. "BS Criminology · 4th Year"). Strip
     * that off so combining it with the real yearLevel field below
     * never shows the year level twice, then always show it as one
     * line — "<program> · <year level>" — right next to the
     * course/department, never as a separate standalone line.
     */
    function cleanProgramName(program, yearLevel) {
        let base = (program || "").trim();
        const yl = (yearLevel || "").trim();
        if (yl) {
            const suffix = "· " + yl;
            if (base.endsWith(suffix)) {
                base = base.slice(0, base.length - suffix.length).trim();
            }
        }
        return base;
    }
    function formatDeptLine(program, major, yearLevel) {
        let base = cleanProgramName(program, yearLevel);
        const yl = (yearLevel || "").trim();
        if (major) {
            base = base ? base + " — " + major : major;
        }
        if (!base) return yl || "Not on file";
        return yl ? base + " · " + yl : base;
    }
    function getRecordTabHtml(r, tab) {
        const hasAcademic = r.gwa !== null && r.gwa !== undefined;
        const passColor = "#15803d", failColor = "#be123c";
        const passBg = "#dcfce7", failBg = "#ffe4e6";
        if (tab === "overview") {
            const gwaPass = hasAcademic && (Number(r.gwaReq) <= 0 || Number(r.gwa) <= Number(r.gwaReq));
            const failPass = hasAcademic && Number(r.failingGrades) === 0;
            const checklist = hasAcademic ? [
                { label: "Currently Enrolled", value: r.enrolled ? "Enrolled" : "Not Enrolled", pass: !!r.enrolled },
                { label: Number(r.gwaReq) > 0 ? `GWA Requirement (≤ ${r.gwaReq})` : "GWA Requirement (none)", value: Number(r.gwaReq) > 0 ? (gwaPass ? "Passed" : "Failed") : "Not required", pass: gwaPass },
                { label: "No Failing Grade", value: failPass ? "Passed" : "Failed", pass: failPass },
                { label: "Complete Documents", value: r.docsComplete ? "Complete" : "Missing", pass: !!r.docsComplete },
            ] : [];
            return ('<div class="section"><h3>Academic Summary</h3>' +
                (hasAcademic
                    ? '<div class="summary-grid">' +
                        '<div class="summary-card"><div class="big font-mono" style="color:' + (gwaPass ? passColor : failColor) + '">' + Number(r.gwa).toFixed(2) + '</div><div class="lbl">' + esc(r.semester) + ' GWA</div><div class="sub" style="color:' + (gwaPass ? passColor : failColor) + '">' + (gwaPass ? "PASSED" : "FAILED") + '</div></div>' +
                        '<div class="summary-card"><div class="big font-mono">' + esc(r.failingGrades) + '</div><div class="lbl">Failing Grades</div><div class="sub" style="color:#6b7280">' + (failPass ? "None" : "Review") + '</div></div>' +
                        '<div class="summary-card"><div class="big font-mono">' + esc(r.units) + '</div><div class="lbl">Units Earned</div><div class="sub" style="color:#6b7280">Units</div></div>' +
                        '</div>'
                    : '<p class="empty-note">No academic evaluation data on file for this record.</p>') +
                '</div>' +
                (hasAcademic
                    ? '<div class="section"><h3>Requirements Checklist</h3>' +
                        checklist.map(c => '<div class="req-row"><div class="req-left"><span class="req-icon" style="background:' + (c.pass ? passBg : failBg) + '">' +
                            (c.pass
                                ? '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="' + passColor + '" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>'
                                : '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="' + failColor + '" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>') +
                            '</span>' + esc(c.label) + '</div><div class="req-val" style="color:' + (c.pass ? passColor : failColor) + '">' + c.value + '</div></div>').join("") +
                        '</div>'
                    : '') +
                '<div class="section"><h3>Final Decision</h3>' +
                '<div class="result-big" style="color:' + (r.status === "approved" ? passColor : failColor) + '">' + (r.status === "approved" ? "APPROVED" : "REJECTED") + '</div>' +
                '<div class="result-sub">Recorded on ' + esc(dateOnly(r.dateEvaluated)) + '</div>' +
                '</div>' +
                '<div class="section"><h3>Remarks</h3>' +
                '<p style="font-size:13px;color:#374151;white-space:pre-wrap;">' + (r.remarks ? esc(r.remarks) : 'No remarks recorded.') + '</p>' +
                '</div>');
        }
        if (tab === "grades") {
            const bySem = window.getCurriculumSubjectsBySemester
                ? window.getCurriculumSubjectsBySemester(cleanProgramName(r.program, r.yearLevel), r.major || "", r.yearLevel || "")
                : { firstSem: [], secondSem: [] };
            // 2nd Semester and Summer Term both show the 2nd-semester subjects below the 1st.
            const semLower = String(r.semester || "").toLowerCase();
            const isSecondSem = semLower.includes("2") || semLower.includes("second") || semLower.includes("summer");
            let breakdownHtml;
            if (!bySem.firstSem.length && !bySem.secondSem.length) {
                breakdownHtml = '<p class="empty-note">No detailed subject-by-subject grade breakdown is recorded for this record.</p>';
            } else {
                breakdownHtml = buildSemesterSubjectBlock(bySem.firstSem, "1st Semester", r.grades, r.semesterGwa && r.semesterGwa.first);
                if (isSecondSem) {
                    breakdownHtml += buildSemesterSubjectBlock(bySem.secondSem, "2nd Semester", r.grades, r.semesterGwa && r.semesterGwa.second);
                }
            }
            return '<div class="section"><h3>Academic Subject Breakdown</h3>' + breakdownHtml + '</div>';
        }
        if (tab === "enrollment") {
            return '<div class="section"><h3>Enrollment Verification</h3>' +
                '<div class="view-detail-grid">' +
                '<div class="detail-item"><span class="detail-label">Enrollment Status</span><span class="detail-value highlight">' + (hasAcademic ? (r.enrolled ? "Validated & Official" : "Unconfirmed") : "Not available") + '</span></div>' +
                '<div class="detail-item"><span class="detail-label">School Year</span><span class="detail-value font-mono">' + esc(r.displaySy || r.sy) + '</span></div>' +
                '<div class="detail-item"><span class="detail-label">Semester</span><span class="detail-value">' + esc(r.currentSemester || r.semester) + '</span></div>' +
                '<div class="detail-item full-width"><span class="detail-label">Degree Program</span><span class="detail-value">' + esc(formatDeptLine(r.program, r.major, r.yearLevel)) + '</span></div>' +
                '</div></div>';
        }
        if (tab === "documents") {
          // The documents the applicant submitted (api/list_records.php), shown like Evaluation's tab.
          const uploadsBase = (window.SITE_BASE || "") + "/uploads/";
          const docs = Array.isArray(r.documents) ? r.documents : [];
          const docRow = (d) => {
            const label = esc(d.label) + (d.required ? "" : ' <span class="doc-optional">(optional)</span>');
            if (!d.filename) {
              return '<div class="doc-row"><div class="doc-row-info"><div class="doc-row-label">' + label + '</div><div class="doc-row-meta">Not submitted</div></div>' +
                '<span class="status-badge ' + (d.required ? "badge-rejected" : "badge-pending") + '">' + (d.required ? "Missing" : "Optional") + "</span></div>";
            }
            const url = uploadsBase + encodeURIComponent(d.filename);
            return '<div class="doc-row">' +
              '<a href="' + url + '" target="_blank" rel="noopener" class="doc-row-thumb"><img src="' + url + '" alt="' + esc(d.label) + '"></a>' +
              '<div class="doc-row-info"><div class="doc-row-label">' + label + '</div><div class="doc-row-meta">Uploaded · PNG</div></div>' +
              '<a href="' + url + '" target="_blank" rel="noopener" class="status-badge badge-approved doc-view-link">View Full Size</a></div>';
          };
          return '<div class="section"><h3>Submitted Verification Documents</h3>' +
            (docs.length
              ? '<p class="empty-note" style="margin-bottom:12px;">Uploaded by the applicant. Click a document to view it full size.</p>' + docs.map(docRow).join("")
              : '<p class="empty-note">No documents are on file for this record.</p>') +
            "</div>";
        }
        const statusClass = r.status === 'approved' ? 'badge-approved' : 'badge-rejected';
        return '<div class="section"><h3>Scholarship Committee Decision</h3>' +
            '<div class="view-detail-grid">' +
            '<div class="detail-item"><span class="detail-label">Outcome</span><span class="status-badge ' + statusClass + '">' + esc(recordStatusText(r)) + '</span></div>' +
            '<div class="detail-item"><span class="detail-label">Date Evaluated</span><span class="detail-value font-mono">' + esc(dateOnly(r.dateEvaluated)) + '</span></div>' +
            '<div class="detail-item full-width"><span class="detail-label">Remarks</span><span class="detail-value remarks">' + (r.remarks ? esc(r.remarks) : 'No remarks recorded.') + '</span></div>' +
            '</div></div>';
    }
    function renderViewModal() {
        if (!recViewBody || !viewingRecord)
            return;
        const r = viewingRecord;
        const statusClass = r.status === 'approved' ? 'badge-approved' : 'badge-rejected';
        const tabs = ["overview", "grades", "enrollment", "documents", "evaluation"];
        const tabsHtml = tabs
            .map(t => '<button type="button" class="tab ' + (activeRecordTab === t ? "active" : "") + '" data-rec-tab="' + t + '">' + t + '</button>')
            .join("");
        recViewBody.innerHTML =
            '<div class="profile">' +
                '<div class="profile-top"><div class="avatar">' + initials(r.name) + '</div>' +
                '<div><div class="record-profile-name">' + esc(r.fullName || r.name) + ' <span class="status-badge ' + statusClass + '">' + esc(recordStatusText(r)) + '</span></div>' +
                '<div class="profile-id font-mono">' + esc(r.studentId) + (r.age != null ? ' · <span title="Born ' + esc(r.birthdate) + '">' + r.age + ' yrs old</span>' : '') + '</div></div></div>' +
                '<div class="profile-meta">' +
                '<span>' + esc(typeAcronym(r.scholarshipType)) + ' Scholarship</span>' +
                '<span>' + esc(r.currentSemester || r.semester) + ' &middot; <span class="font-mono">' + esc(r.displaySy || r.sy) + '</span></span>' +
                '<span>' + esc(formatDeptLine(r.program, r.major, r.yearLevel)) + '</span>' +
                '</div>' +
                '</div>' +
                '<div class="tabs">' + tabsHtml + '</div>' +
                '<div id="recTabContainer">' + getRecordTabHtml(r, activeRecordTab) + '</div>';
        recViewBody.querySelectorAll("[data-rec-tab]").forEach((btn) => {
            btn.addEventListener("click", () => {
                const tab = btn.getAttribute("data-rec-tab");
                if (tab) {
                    activeRecordTab = tab;
                    renderViewModal();
                }
            });
        });
    }
    function openViewModal(r) {
        if (!recViewOverlay || !recViewBody)
            return;
        viewingRecord = r;
        activeRecordTab = "overview";
        renderViewModal();
        recViewOverlay.classList.add("open");
    }
    function closeViewModal() {
        if (recViewOverlay)
            recViewOverlay.classList.remove("open");
        viewingRecord = null;
    }
    function openFormModal(editItem) {
        if (!recFormOverlay || !editItem)
            return;
        if (recFormTitle)
            recFormTitle.textContent = "Edit Evaluation Record";
        if (recId)
            recId.value = String(editItem.id);
        if (recStudentId)
            recStudentId.value = editItem.studentId;
        if (recName)
            recName.value = editItem.recordName || editItem.fullName || editItem.name;
        if (recType)
            recType.value = editItem.scholarshipType;
        if (recStatus)
            recStatus.value = editItem.status;
        if (recRemarks)
            recRemarks.value = editItem.remarks || '';
        recFormOverlay.classList.add("open");
    }
    function closeFormModal() {
        if (recFormOverlay)
            recFormOverlay.classList.remove("open");
        if (recForm)
            recForm.reset();
    }
    window.editRecord = function (event, id) {
        event.stopPropagation();
        const item = recordsData.find((r) => r.id === id);
        if (item)
            openFormModal(item);
    };
    window.confirmDeleteRecord = function (event, id) {
        event.stopPropagation();
        const item = recordsData.find((r) => r.id === id);
        if (!item)
            return;
        deletingRecordId = id;
        if (recDeleteTarget)
            recDeleteTarget.textContent = item.name;
        if (recDeleteOverlay)
            recDeleteOverlay.classList.add("open");
    };
    function closeDeleteModal() {
        if (recDeleteOverlay)
            recDeleteOverlay.classList.remove("open");
        deletingRecordId = null;
    }
    if (recForm) {
        recForm.addEventListener("submit", async (e) => {
            e.preventDefault();
            const formData = new FormData(recForm);
            try {
                const apiPath = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";
                const res = await fetch(`${apiPath}/list_records.php`, { method: "POST", body: formData });
                const json = await res.json();
                if (json.success) {
                    closeFormModal();
                    loadRecords();
                }
                else {
                    alert(json.message || "Failed to save record.");
                }
            }
            catch (err) {
                alert("Server error.");
            }
        });
    }
    if (recDeleteConfirmBtn) {
        recDeleteConfirmBtn.addEventListener("click", async () => {
            if (!deletingRecordId)
                return;
            try {
                const apiPath = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";
                const res = await fetch(`${apiPath}/delete_record.php?id=${deletingRecordId}`, { method: "POST" });
                const json = await res.json();
                if (json.success) {
                    closeDeleteModal();
                    loadRecords();
                }
                else {
                    alert(json.message || "Failed to delete record.");
                }
            }
            catch (e) {
                alert("Server error.");
            }
        });
    }
    if (recFormCloseBtn)
        recFormCloseBtn.addEventListener("click", closeFormModal);
    if (recFormCancelBtn)
        recFormCancelBtn.addEventListener("click", closeFormModal);
    if (recViewCloseBtn)
        recViewCloseBtn.addEventListener("click", closeViewModal);
    if (recViewCloseBtn2)
        recViewCloseBtn2.addEventListener("click", closeViewModal);
    if (recViewEditBtn) {
        recViewEditBtn.addEventListener("click", () => {
            const item = viewingRecord;
            closeViewModal();
            if (item)
                openFormModal(item);
        });
    }
    if (recDeleteCloseBtn)
        recDeleteCloseBtn.addEventListener("click", closeDeleteModal);
    if (recDeleteCancelBtn)
        recDeleteCancelBtn.addEventListener("click", closeDeleteModal);
    function resetRecordsPageAndRender() {
        recordsCurrentPage = 1;
        renderRecords();
    }
    if (searchInput)
        searchInput.addEventListener("input", resetRecordsPageAndRender);
    if (filterType)
        filterType.addEventListener("change", resetRecordsPageAndRender);
    if (filterStatus)
        filterStatus.addEventListener("change", resetRecordsPageAndRender);
    if (filterSemester)
        filterSemester.addEventListener("change", resetRecordsPageAndRender);
    if (filterSy)
        filterSy.addEventListener("change", resetRecordsPageAndRender);
  if (filterProgram) filterProgram.addEventListener("change", resetRecordsPageAndRender);
  if (filterYearLevel) filterYearLevel.addEventListener("change", resetRecordsPageAndRender);
    // "records-BSIT-1st-Year-2026-10-02.xlsx" — names the filters that were applied.
    function exportFileName() {
      const picked = [filterProgram, filterYearLevel, filterType, filterStatus, filterSemester, filterSy]
        .filter((el) => el && el.value && el.value !== "all")
        .map((el) => (el).options[(el).selectedIndex].text)
        .map((t) => t.replace(/[^A-Za-z0-9]+/g, "-").replace(/^-+|-+$/g, ""));
      return ["records", ...picked, new Date().toISOString().slice(0, 10)].join("-");
    }

    function exportRecordsToCsv() {
        const rows = getFilteredRecords();
        if (rows.length === 0) {
            alert("No records to export for the current filters.");
            return;
        }
        const headers = ["Student ID", "Surname", "First Name", "Full Name", "Program", "Year Level", "Scholarship Type", "Semester", "School Year", "Status"];
        const out = [headers];
        rows.forEach((r) => {
            out.push([
                r.studentId,
                r.lastName || "",
                r.firstName || "",
                r.fullName || r.name,
                r.programCode || r.program || "",
                r.yearLevel || "",
                r.scholarshipType,
                r.currentSemester || r.semester,
                r.displaySy || r.sy,
                recordStatusKey(r),
            ]);
        });
        window.downloadXlsx({ filename: exportFileName(), sheet: "Records", rows: out });
    }
    if (exportBtn) {
        exportBtn.addEventListener("click", exportRecordsToCsv);
    }
    loadRecords();
});
