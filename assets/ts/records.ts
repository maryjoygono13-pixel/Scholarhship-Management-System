document.addEventListener("DOMContentLoaded", () => {
  const tableBody = document.getElementById("tableBody");
  const searchInput = document.querySelector<HTMLInputElement>(".search-wrap input");
  const filterType = document.getElementById("filterType") as HTMLSelectElement | null;
  const filterStatus = document.getElementById("filterStatus") as HTMLSelectElement | null;
  const exportBtn = document.getElementById("exportRecordsBtn");

  const recFormOverlay = document.getElementById("recFormOverlay");
  const recFormTitle = document.getElementById("recFormTitle");
  const recFormCloseBtn = document.getElementById("recFormCloseBtn");
  const recFormCancelBtn = document.getElementById("recFormCancelBtn");
  const recForm = document.getElementById("recForm") as HTMLFormElement | null;

  const recId = document.getElementById("recId") as HTMLInputElement | null;
  const recStudentId = document.getElementById("recStudentId") as HTMLInputElement | null;
  const recName = document.getElementById("recName") as HTMLInputElement | null;
  const recType = document.getElementById("recType") as HTMLSelectElement | null;
  const recStatus = document.getElementById("recStatus") as HTMLSelectElement | null;
  const recSemester = document.getElementById("recSemester") as HTMLInputElement | null;
  const recSy = document.getElementById("recSy") as HTMLInputElement | null;
  const recRemarks = document.getElementById("recRemarks") as HTMLTextAreaElement | null;

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

  let recordsData: any[] = [];
  let deletingRecordId: number | null = null;
  let viewingRecord: any = null;
  let activeRecordTab = "overview";

  async function loadRecords(): Promise<void> {
    try {
      const res = await fetch("api/list_records.php");
      const json = await res.json();
      if (json.success) {
        recordsData = json.data;
        populateFilterTypes();
        renderRecords();
      }
    } catch (e) {
      console.error("Failed to load records:", e);
    }
  }

  function populateFilterTypes(): void {
    if (!filterType) return;
    const types = Array.from(new Set(recordsData.map((r: any) => r.scholarshipType))).filter(Boolean);
    filterType.innerHTML = '<option value="all">All Scholarship Types</option>';
    types.forEach(t => {
      const opt = document.createElement("option");
      opt.value = t;
      opt.textContent = typeAcronym(t);
      filterType.appendChild(opt);
    });
  }

  function typeAcronym(type: string): string {
    if (!type) return "";
    const match = type.match(/^([^(]+)\(/);
    return match ? match[1].trim() : type.trim();
  }

  function dateOnly(value: string): string {
    if (!value) return "";
    return value.split(" ")[0].split("T")[0];
  }

  function getFilteredRecords(): any[] {
    const query = searchInput ? searchInput.value.toLowerCase().trim() : "";
    const typeVal = filterType ? filterType.value.toLowerCase() : "all";
    const statusVal = filterStatus ? filterStatus.value.toLowerCase() : "all";

    return recordsData.filter((r: any) => {
      const matchQuery = r.name.toLowerCase().includes(query) || r.studentId.toLowerCase().includes(query);
      const matchType = typeVal === "all" || r.scholarshipType.toLowerCase() === typeVal;
      const matchStatus = statusVal === "all" || statusVal === "all status" || r.status.toLowerCase() === statusVal;
      return matchQuery && matchType && matchStatus;
    });
  }

  function renderRecords(): void {
    if (!tableBody) return;
    const filtered = getFilteredRecords();

    tableBody.innerHTML = "";
    if (filtered.length === 0) {
      tableBody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding: 24px; color: #6b7280;">No records found.</td></tr>`;
      return;
    }

    filtered.forEach((r: any) => {
      const tr = document.createElement("tr");
      const badgeClass = r.status === "approved" ? "badge-approved" : (r.status === "rejected" ? "badge-rejected" : "badge-pending");
      tr.innerHTML = `
        <td><strong class="font-mono">${r.studentId}</strong></td>
        <td>${r.name}</td>
        <td>${typeAcronym(r.scholarshipType)}</td>
        <td><span class="status-badge ${badgeClass}">${r.status}</span></td>
        <td>${r.semester}</td>
        <td><span class="font-mono">${r.sy}</span></td>
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

    if (typeof lucide !== "undefined") lucide.createIcons();
  }

  function esc(str: any): string {
    const d = document.createElement("div");
    d.textContent = str == null ? "" : String(str);
    return d.innerHTML;
  }

  function initials(name: string): string {
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
  function formatDeptLine(program: string, major: string, yearLevel: string): string {
    let base = (program || "").trim();
    const yl = (yearLevel || "").trim();
    if (yl) {
      const suffix = "· " + yl;
      if (base.endsWith(suffix)) {
        base = base.slice(0, base.length - suffix.length).trim();
      }
    }
    if (major) {
      base = base ? base + " — " + major : major;
    }
    if (!base) return yl || "Not on file";
    return yl ? base + " · " + yl : base;
  }

  function getRecordTabHtml(r: any, tab: string): string {
    const hasAcademic = r.gwa !== null && r.gwa !== undefined;
    const passColor = "#15803d", failColor = "#be123c";
    const passBg = "#dcfce7", failBg = "#ffe4e6";

    if (tab === "overview") {
      const gwaPass = hasAcademic && Number(r.gwa) <= Number(r.gwaReq);
      const failPass = hasAcademic && Number(r.failingGrades) === 0;
      const checklist = hasAcademic ? [
        { label: "Currently Enrolled", value: r.enrolled ? "Enrolled" : "Not Enrolled", pass: !!r.enrolled },
        { label: `GWA Requirement (≤ ${r.gwaReq})`, value: gwaPass ? "Passed" : "Failed", pass: gwaPass },
        { label: "No Failing Grade", value: failPass ? "Passed" : "Failed", pass: failPass },
        { label: "Complete Documents", value: r.docsComplete ? "Complete" : "Missing", pass: !!r.docsComplete },
      ] : [];

      return (
        '<div class="section"><h3>Academic Summary</h3>' +
        (hasAcademic
          ? '<div class="summary-grid">' +
            '<div class="summary-card"><div class="big font-mono" style="color:' + (gwaPass ? passColor : failColor) + '">' + Number(r.gwa).toFixed(2) + '</div><div class="lbl">GWA</div><div class="sub" style="color:' + (gwaPass ? passColor : failColor) + '">' + (gwaPass ? "PASSED" : "FAILED") + '</div></div>' +
            '<div class="summary-card"><div class="big font-mono">' + esc(r.failingGrades) + '</div><div class="lbl">Failing Grades</div><div class="sub" style="color:#6b7280">' + (failPass ? "None" : "Review") + '</div></div>' +
            '<div class="summary-card"><div class="big font-mono">' + esc(r.units) + '</div><div class="lbl">Units Earned</div><div class="sub" style="color:#6b7280">Units</div></div>' +
            '</div>'
          : '<p class="empty-note">No academic evaluation data on file for this record.</p>'
        ) +
        '</div>' +
        (hasAcademic
          ? '<div class="section"><h3>Requirements Checklist</h3>' +
            checklist.map(c =>
              '<div class="req-row"><div class="req-left"><span class="req-icon" style="background:' + (c.pass ? passBg : failBg) + '">' +
              (c.pass
                ? '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="' + passColor + '" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>'
                : '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="' + failColor + '" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>') +
              '</span>' + esc(c.label) + '</div><div class="req-val" style="color:' + (c.pass ? passColor : failColor) + '">' + c.value + '</div></div>'
            ).join("") +
            '</div>'
          : ''
        ) +
        '<div class="section"><h3>Final Decision</h3>' +
        '<div class="result-big" style="color:' + (r.status === "approved" ? passColor : failColor) + '">' + (r.status === "approved" ? "APPROVED" : "REJECTED") + '</div>' +
        '<div class="result-sub">Recorded on ' + esc(dateOnly(r.dateEvaluated)) + '</div>' +
        '</div>' +
        '<div class="section"><h3>Remarks</h3>' +
        '<p style="font-size:13px;color:#374151;white-space:pre-wrap;">' + (r.remarks ? esc(r.remarks) : 'No remarks recorded.') + '</p>' +
        '</div>'
      );
    }

    if (tab === "grades") {
      const subjects = (window as any).getCurriculumSubjects
        ? (window as any).getCurriculumSubjects(r.program || "", r.major || "", r.yearLevel || "")
        : [];

      const tableRows = subjects
        .map(
          (s: { code: string; name: string }) =>
            '<tr><td><b class="font-mono">' + esc(s.code) + '</b></td><td>' + esc(s.name) + '</td><td><span class="badge badge-pending">Not yet graded</span></td></tr>'
        )
        .join("");

      const breakdownHtml = subjects.length
        ? '<div style="border:1px solid #e2e8f0; border-radius:10px; overflow:hidden;">' +
          '<table class="applicants-table" style="font-size:13px; margin:0;">' +
          '<thead><tr><th>Code</th><th>Subject Description</th><th>Status</th></tr></thead>' +
          '<tbody>' + tableRows + '</tbody></table></div>'
        : '<p class="empty-note">No detailed subject-by-subject grade breakdown is recorded for this record.</p>';

      return '<div class="section"><h3>Academic Subject Breakdown</h3>' + breakdownHtml + '</div>';
    }

    if (tab === "enrollment") {
      return '<div class="section"><h3>Enrollment Verification</h3>' +
        '<div class="view-detail-grid">' +
        '<div class="detail-item"><span class="detail-label">Enrollment Status</span><span class="detail-value highlight">' + (hasAcademic ? (r.enrolled ? "Validated & Official" : "Unconfirmed") : "Not available") + '</span></div>' +
        '<div class="detail-item"><span class="detail-label">School Year</span><span class="detail-value font-mono">' + esc(r.sy) + '</span></div>' +
        '<div class="detail-item"><span class="detail-label">Semester</span><span class="detail-value">' + esc(r.semester) + '</span></div>' +
        '<div class="detail-item full-width"><span class="detail-label">Degree Program</span><span class="detail-value">' + esc(formatDeptLine(r.program, r.major, r.yearLevel)) + '</span></div>' +
        '</div></div>';
    }

    if (tab === "documents") {
      return '<div class="section"><h3>Submitted Verification Documents</h3>' +
        '<p class="empty-note">No document records are on file for this archived record.</p>' +
        '</div>';
    }

    // "evaluation" tab
    const statusClass = r.status === 'approved' ? 'badge-approved' : 'badge-rejected';
    return '<div class="section"><h3>Scholarship Committee Decision</h3>' +
      '<div class="view-detail-grid">' +
      '<div class="detail-item"><span class="detail-label">Outcome</span><span class="status-badge ' + statusClass + '">' + esc(r.status) + '</span></div>' +
      '<div class="detail-item"><span class="detail-label">Date Evaluated</span><span class="detail-value font-mono">' + esc(dateOnly(r.dateEvaluated)) + '</span></div>' +
      '<div class="detail-item full-width"><span class="detail-label">Remarks</span><span class="detail-value remarks">' + (r.remarks ? esc(r.remarks) : 'No remarks recorded.') + '</span></div>' +
      '</div></div>';
  }

  function renderViewModal(): void {
    if (!recViewBody || !viewingRecord) return;
    const r = viewingRecord;
    const statusClass = r.status === 'approved' ? 'badge-approved' : 'badge-rejected';
    const tabs = ["overview", "grades", "enrollment", "documents", "evaluation"];
    const tabsHtml = tabs
      .map(t => '<button type="button" class="tab ' + (activeRecordTab === t ? "active" : "") + '" data-rec-tab="' + t + '">' + t + '</button>')
      .join("");

    recViewBody.innerHTML =
      '<div class="profile">' +
      '<div class="profile-top"><div class="avatar">' + initials(r.name) + '</div>' +
      '<div><div class="record-profile-name">' + esc(r.fullName || r.name) + ' <span class="status-badge ' + statusClass + '">' + esc(r.status) + '</span></div>' +
      '<div class="profile-id font-mono">' + esc(r.studentId) + '</div></div></div>' +
      '<div class="profile-meta">' +
      '<span>' + esc(typeAcronym(r.scholarshipType)) + ' Scholarship</span>' +
      '<span>' + esc(r.semester) + ' &middot; <span class="font-mono">' + esc(r.sy) + '</span></span>' +
      '<span>' + esc(formatDeptLine(r.program, r.major, r.yearLevel)) + '</span>' +
      '</div>' +
      '</div>' +
      '<div class="tabs">' + tabsHtml + '</div>' +
      '<div id="recTabContainer">' + getRecordTabHtml(r, activeRecordTab) + '</div>';

    recViewBody.querySelectorAll<HTMLElement>("[data-rec-tab]").forEach((btn) => {
      btn.addEventListener("click", () => {
        const tab = btn.getAttribute("data-rec-tab");
        if (tab) {
          activeRecordTab = tab;
          renderViewModal();
        }
      });
    });
  }

  function openViewModal(r: any): void {
    if (!recViewOverlay || !recViewBody) return;
    viewingRecord = r;
    activeRecordTab = "overview";
    renderViewModal();
    recViewOverlay.classList.add("open");
  }

  function closeViewModal(): void {
    if (recViewOverlay) recViewOverlay.classList.remove("open");
    viewingRecord = null;
  }

  function openFormModal(editItem: any): void {
    if (!recFormOverlay || !editItem) return;
    if (recFormTitle) recFormTitle.textContent = "Edit Evaluation Record";
    if (recId) recId.value = String(editItem.id);
    if (recStudentId) recStudentId.value = editItem.studentId;
    if (recName) recName.value = editItem.fullName || editItem.name;
    if (recType) recType.value = editItem.scholarshipType;
    if (recStatus) recStatus.value = editItem.status;
    if (recSemester) recSemester.value = editItem.semester;
    if (recSy) recSy.value = editItem.sy;
    if (recRemarks) recRemarks.value = editItem.remarks || '';
    recFormOverlay.classList.add("open");
  }

  function closeFormModal(): void {
    if (recFormOverlay) recFormOverlay.classList.remove("open");
    if (recForm) recForm.reset();
  }

  window.editRecord = function(event: MouseEvent, id: number): void {
    event.stopPropagation();
    const item = recordsData.find((r: any) => r.id === id);
    if (item) openFormModal(item);
  };

  window.confirmDeleteRecord = function(event: MouseEvent, id: number): void {
    event.stopPropagation();
    const item = recordsData.find((r: any) => r.id === id);
    if (!item) return;
    deletingRecordId = id;
    if (recDeleteTarget) recDeleteTarget.textContent = item.name;
    if (recDeleteOverlay) recDeleteOverlay.classList.add("open");
  };

  function closeDeleteModal(): void {
    if (recDeleteOverlay) recDeleteOverlay.classList.remove("open");
    deletingRecordId = null;
  }

  if (recForm) {
    recForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      const formData = new FormData(recForm);
      try {
        const res = await fetch("api/list_records.php", { method: "POST", body: formData });
        const json = await res.json();
        if (json.success) {
          closeFormModal();
          loadRecords();
        } else {
          alert(json.message || "Failed to save record.");
        }
      } catch (err) {
        alert("Server error.");
      }
    });
  }

  if (recDeleteConfirmBtn) {
    recDeleteConfirmBtn.addEventListener("click", async () => {
      if (!deletingRecordId) return;
      try {
        const res = await fetch(`api/delete_record.php?id=${deletingRecordId}`, { method: "POST" });
        const json = await res.json();
        if (json.success) {
          closeDeleteModal();
          loadRecords();
        } else {
          alert(json.message || "Failed to delete record.");
        }
      } catch (e) {
        alert("Server error.");
      }
    });
  }

  if (recFormCloseBtn) recFormCloseBtn.addEventListener("click", closeFormModal);
  if (recFormCancelBtn) recFormCancelBtn.addEventListener("click", closeFormModal);

  if (recViewCloseBtn) recViewCloseBtn.addEventListener("click", closeViewModal);
  if (recViewCloseBtn2) recViewCloseBtn2.addEventListener("click", closeViewModal);
  if (recViewEditBtn) {
    recViewEditBtn.addEventListener("click", () => {
      const item = viewingRecord;
      closeViewModal();
      if (item) openFormModal(item);
    });
  }

  if (recDeleteCloseBtn) recDeleteCloseBtn.addEventListener("click", closeDeleteModal);
  if (recDeleteCancelBtn) recDeleteCancelBtn.addEventListener("click", closeDeleteModal);

  if (searchInput) searchInput.addEventListener("input", renderRecords);
  if (filterType) filterType.addEventListener("change", renderRecords);
  if (filterStatus) filterStatus.addEventListener("change", renderRecords);

  function csvEscape(value: any): string {
    const str = value == null ? "" : String(value);
    if (/[",\n]/.test(str)) return '"' + str.replace(/"/g, '""') + '"';
    return str;
  }

  function exportRecordsToCsv(): void {
    const rows = getFilteredRecords();
    if (rows.length === 0) {
      alert("No records to export for the current filters.");
      return;
    }

    const headers = ["Student ID", "Name", "Scholarship Type", "Status", "Semester", "School Year", "Date Evaluated", "Remarks"];
    const lines = [headers.map(csvEscape).join(",")];

    rows.forEach((r: any) => {
      lines.push([
        r.studentId,
        r.fullName || r.name,
        r.scholarshipType,
        r.status,
        r.semester,
        r.sy,
        dateOnly(r.dateEvaluated),
        r.remarks || "",
      ].map(csvEscape).join(","));
    });

    const blob = new Blob([lines.join("\r\n")], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = "scholarship-records-" + new Date().toISOString().slice(0, 10) + ".csv";
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }

  if (exportBtn) {
    exportBtn.addEventListener("click", exportRecordsToCsv);
  }

  loadRecords();
});
