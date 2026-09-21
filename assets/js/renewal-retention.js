"use strict";
// What a renewal row's status is called on screen ("eligible" in the database means renewed).
function renewalStatusLabel(r) {
  const s = String(r.status || "").toLowerCase();
  if (s === "eligible") return "Renewed";
  if (s === "terminated") return "Terminated";
  return "Pending";
}

function normalizeSemesterValue(val) {
    const v = (val || "").toLowerCase();
    if (v.includes("summer"))
        return "Summer Term";
    return (v.includes("2") || v.includes("second")) ? "2nd Semester" : "1st Semester";
}
document.addEventListener("DOMContentLoaded", () => {
    const ledgerBody = document.getElementById("ledgerBody");
    const countPending = document.getElementById("countPending");
    const countEligible = document.getElementById("countEligible");
    const countAtRisk = document.getElementById("countAtRisk");
    const countTerminated = document.getElementById("countTerminated");
    const countTotal = document.getElementById("countTotal");
    const searchBox = document.getElementById("searchBox");
    const statusFilter = document.getElementById("statusFilter");
    const semesterFilter = document.getElementById("semesterFilter");
    const schoolYearFilter = document.getElementById("SchoolYearFilter");
    const scholarshipFilter = document.getElementById("scholarshipFilter");
    const programFilter = document.getElementById("programFilter");
    const modalOverlay = document.getElementById("modalOverlay");
    const modalClose = document.getElementById("modalClose");
    const modalName = document.getElementById("modalName");
    const modalId = document.getElementById("modalId");
    const modalCriteria = document.getElementById("modalCriteria");
    const modalRemarksText = document.getElementById("modalRemarksText");
    const modalSeal = document.getElementById("modalSeal");
    const renewBtn = document.getElementById("renewBtn");
    const terminateBtn = document.getElementById("terminateBtn");
    const renDeleteOverlay = document.getElementById("renDeleteOverlay");
    const renDeleteCloseBtn = document.getElementById("renDeleteCloseBtn");
    const renDeleteCancelBtn = document.getElementById("renDeleteCancelBtn");
    const renDeleteConfirmBtn = document.getElementById("renDeleteConfirmBtn");
    const renDeleteTarget = document.getElementById("renDeleteTarget");
    let ledgerData = [];
    let selectedRecord = null;
    let deletingRenId = null;
    const RENEWAL_PAGE_SIZE = 10;
    let renewalCurrentPage = 1;
    async function loadLedger() {
        try {
            const apiPath = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";
            const res = await fetch(`${apiPath}/list_renewal.php`);
            const json = await res.json();
            if (json.success) {
                ledgerData = json.data;
                fillLedgerFilters();
                if (json.summary) {
                    if (countPending)
                        countPending.textContent = String(json.summary.pending ?? 0);
                    if (countEligible)
                        countEligible.textContent = String(json.summary.eligible);
                    if (countAtRisk)
                        countAtRisk.textContent = String(json.summary.at_risk);
                    if (countTerminated)
                        countTerminated.textContent = String(json.summary.terminated);
                    if (countTotal)
                        countTotal.textContent = String(json.summary.total);
                }
                renderLedger();
            }
        }
        catch (e) {
            console.error("Failed to load renewal ledger:", e);
        }
    }
    // School Year and Scholarship Type choices come from the ledger itself.
    function fillLedgerFilters() {
        const fill = (sel, placeholder, values) => {
            if (!sel)
                return;
            const current = sel.value;
            const unique = Array.from(new Set(values.filter((v) => v))).sort();
            sel.innerHTML = `<option value="">${placeholder}</option>` + unique.map((v) => `<option value="${v}">${v}</option>`).join("");
            sel.value = unique.includes(current) ? current : "";
        };
        fill(schoolYearFilter, "School Year", ledgerData.map((r) => r.schoolYear));
        fill(scholarshipFilter, "Scholarship Type", ledgerData.map((r) => r.scholarshipType));
        fill(programFilter, "Program", ledgerData.map((r) => r.programCode));
    }
    function renderLedger() {
        if (!ledgerBody)
            return;
        const query = searchBox ? searchBox.value.toLowerCase().trim() : "";
        const statusVal = statusFilter ? statusFilter.value.toLowerCase() : "";
        const semVal = semesterFilter ? semesterFilter.value : "";
    const syVal = schoolYearFilter ? schoolYearFilter.value : "";
    const typeVal = scholarshipFilter ? scholarshipFilter.value : "";
    const progVal = programFilter ? programFilter.value : "";
        const filtered = ledgerData.filter((r) => {
            const matchQuery = r.name.toLowerCase().includes(query) || r.studentId.toLowerCase().includes(query);
            const matchStatus = !statusVal || r.status.toLowerCase() === statusVal;
            const matchSem = !semVal || normalizeSemesterValue(r.semester) === semVal;
      const matchSy = !syVal || r.schoolYear === syVal;
      const matchType = !typeVal || r.scholarshipType === typeVal;
      const matchProg = !progVal || r.programCode === progVal;
            return matchQuery && matchStatus && matchSem && matchSy && matchType && matchProg;
        });
        ledgerBody.innerHTML = "";
        if (filtered.length === 0) {
            ledgerBody.innerHTML = `<tr><td colspan="9" style="text-align:center; padding:24px; color:#6b7280;">No scholars found.</td></tr>`;
            renderRenewalPagination(0);
            return;
        }
        const totalPages = Math.max(1, Math.ceil(filtered.length / RENEWAL_PAGE_SIZE));
        if (renewalCurrentPage > totalPages) renewalCurrentPage = totalPages;
        if (renewalCurrentPage < 1) renewalCurrentPage = 1;
        const pageItems = filtered.slice((renewalCurrentPage - 1) * RENEWAL_PAGE_SIZE, renewalCurrentPage * RENEWAL_PAGE_SIZE);
        pageItems.forEach((r) => {
            const tr = document.createElement("tr");
            const statusBadge = r.status === "eligible" ? "badge-eligible" : (r.status === "pending" ? "badge-pending" : (r.status === "at-risk" ? "badge-at-risk" : "badge-terminated"));
            tr.innerHTML = `
        <td><strong class="font-mono">${r.studentId}</strong></td>
        <td>${r.name}</td>
        <td>${r.scholarshipType}</td>
        <td><span class="font-mono" style="color:${r.meetsGwa === false ? '#be123c' : 'inherit'};">${r.gwa.toFixed(2)}</span><div style="font-size:11px; color:#6b7280;">Req. ≤ ${Number(r.gwaRequirement).toFixed(2)}</div></td>
        <td>${r.failingGrades > 0 ? `<span class="font-mono" style="color:red;">${r.failingGrades} Failing</span>` : "Passed All"}</td>
        <td>${r.enrolled ? "Enrolled" : "Not Enrolled"}</td>
        <td><span class="font-mono">${r.semester || "1st Semester"}</span></td>
        <td><span class="status-badge ${statusBadge}">${renewalStatusLabel(r)}</span>${r.locked ? '<div style="font-size:11px; color:#6b7280; margin-top:2px;">Locked · view only</div>' : ""}</td>
        <td class="actions-cell">
          <button type="button" class="btn-icon-action edit" title="Edit / Review Scholar" onclick="editRenewal(event, ${r.id})">
            <i data-lucide="pencil"></i>
          </button>
          <button type="button" class="btn-icon-action delete" title="Delete Scholar Entry" onclick="confirmDeleteRenewal(event, ${r.id})">
            <i data-lucide="trash-2"></i>
          </button>
        </td>
      `;
            tr.addEventListener("click", () => window.openEvalModal ? window.openEvalModal(r.id) : null);
            ledgerBody.appendChild(tr);
        });
        renderRenewalPagination(filtered.length);
        if (typeof lucide !== "undefined")
            lucide.createIcons();
    }
    function renderRenewalPagination(total) {
        const wrap = document.getElementById("renewalPagination");
        if (!wrap) return;
        const totalPages = Math.max(1, Math.ceil(total / RENEWAL_PAGE_SIZE));
        if (renewalCurrentPage > totalPages) renewalCurrentPage = totalPages;
        if (renewalCurrentPage < 1) renewalCurrentPage = 1;
        if (total === 0) {
            wrap.innerHTML = "";
            return;
        }
        const start = (renewalCurrentPage - 1) * RENEWAL_PAGE_SIZE + 1;
        const end = Math.min(renewalCurrentPage * RENEWAL_PAGE_SIZE, total);
        const buttons = `<button type="button" class="active" data-page="${renewalCurrentPage}" disabled>${renewalCurrentPage}</button>`;
        wrap.innerHTML =
            `<span>Showing ${start}–${end} of ${total} entries</span>` +
            `<div class="page-btns">` +
            `<button type="button" data-page="${renewalCurrentPage - 1}" ${renewalCurrentPage <= 1 ? "disabled" : ""}>Prev</button>` +
            buttons +
            `<button type="button" data-page="${renewalCurrentPage + 1}" ${renewalCurrentPage >= totalPages ? "disabled" : ""}>Next</button>` +
            `</div>`;
        wrap.querySelectorAll("button[data-page]").forEach((btn) => {
            btn.addEventListener("click", () => {
                const p = parseInt(btn.getAttribute("data-page") || "", 10);
                if (!isNaN(p) && p >= 1 && p <= totalPages) {
                    renewalCurrentPage = p;
                    renderLedger();
                }
            });
        });
    }
    window.openEvalModal = function (id) {
        selectedRecord = ledgerData.find((r) => r.id === id);
        if (!selectedRecord || !modalOverlay)
            return;
        if (modalName)
            modalName.textContent = selectedRecord.name;
        if (modalId)
            modalId.textContent = `Student ID: ${selectedRecord.studentId}`;
        if (modalSeal) {
            modalSeal.textContent = renewalStatusLabel(selectedRecord).toUpperCase();
            const badgeCls = selectedRecord.status === 'eligible' ? 'badge-approved' : (selectedRecord.status === 'at-risk' || selectedRecord.status === 'pending' ? 'badge-pending' : 'badge-rejected');
            modalSeal.className = `status-badge ${badgeCls}`;
        }
        if (modalRemarksText)
            modalRemarksText.textContent = selectedRecord.remarks || "No remarks logged.";
        // Read-only: the term follows the Active Semester in Settings.
        const modalSemesterBadge = document.getElementById("modalSemesterBadge");
        if (modalSemesterBadge)
            modalSemesterBadge.textContent = normalizeSemesterValue(selectedRecord.semester);
        if (modalCriteria) {
            modalCriteria.innerHTML = `
        <div class="view-detail-grid" style="margin-bottom:12px;">
          <div class="detail-item full-width">
            <span class="detail-label">Scholarship Type</span>
            <span class="detail-value highlight">${selectedRecord.scholarshipType}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Current GWA</span>
            <span class="detail-value mono font-mono">${selectedRecord.gwa.toFixed(2)}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Required GWA</span>
            <span class="detail-value font-mono" style="color:${selectedRecord.meetsGwa === false ? '#be123c' : '#15803d'};">≤ ${Number(selectedRecord.gwaRequirement).toFixed(2)} — ${selectedRecord.meetsGwa === false ? 'Not met' : 'Met'}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Failing Grades</span>
            <span class="detail-value font-mono ${selectedRecord.failingGrades > 0 ? 'metric fail' : 'metric pass'}">${selectedRecord.failingGrades}</span>
          </div>
          <div class="detail-item full-width">
            <span class="detail-label">Enrollment Status</span>
            <span class="detail-value">${selectedRecord.enrolled ? "Validated Enrollment" : "Unconfirmed Enrollment"}</span>
          </div>
        </div>
      `;
        }
            const lockNote = document.getElementById("modalLockNote");
    if (lockNote) {
      const r = selectedRecord;
      let note = "";
      if (r.locked) note = "Locked: view only. This entry can be renewed or terminated once the Active Semester moves past " + r.semester + " (Settings > Portal Configuration).";
      else if (r.status === "eligible") note = "Already renewed.";
      else if (r.status === "terminated") note = "Already terminated.";
      else if (r.origin === "rejected") note = "Rejected in Evaluation: this entry can only be terminated (closed). The applicant can still apply again.";
      else if (!(r.gwa > 0)) note = "No grades are recorded for " + r.semester + " yet. Import the academic records in Data Management to decide.";
      else if (r.canRenew) note = "GWA meets the requirement: this scholar can be renewed.";
      else if (r.canTerminate) note = "GWA does not meet the requirement: this scholar can be terminated and will not be able to apply for " + r.scholarshipType + " again.";
      lockNote.textContent = note;
      lockNote.style.display = note ? "block" : "none";
    }
    if (renewBtn) renewBtn.toggleAttribute("disabled", !selectedRecord.canRenew);
    if (terminateBtn) terminateBtn.toggleAttribute("disabled", !selectedRecord.canTerminate);
    modalOverlay.classList.add("open");
    };
    function closeModal() {
        if (modalOverlay)
            modalOverlay.classList.remove("open");
        selectedRecord = null;
    }
    window.editRenewal = function (event, id) {
        event.stopPropagation();
        if (window.openEvalModal)
            window.openEvalModal(id);
    };
    window.confirmDeleteRenewal = function (event, id) {
        event.stopPropagation();
        const item = ledgerData.find((r) => r.id === id);
        if (!item)
            return;
        deletingRenId = id;
        if (renDeleteTarget)
            renDeleteTarget.textContent = item.name;
        if (renDeleteOverlay)
            renDeleteOverlay.classList.add("open");
    };
    function closeDeleteModal() {
        if (renDeleteOverlay)
            renDeleteOverlay.classList.remove("open");
        deletingRenId = null;
    }
    async function updateStatus(action) {
        if (!selectedRecord)
            return;
        if (action === "renew" && selectedRecord.meetsGwa === false) {
            alert(`Cannot renew: GWA ${selectedRecord.gwa.toFixed(2)} does not meet the required ${Number(selectedRecord.gwaRequirement).toFixed(2)} for ${selectedRecord.scholarshipType}.`);
            return;
        }
            if (action === "terminate" && selectedRecord.origin !== "rejected" &&
        !confirm("Terminate " + selectedRecord.name + " from " + selectedRecord.scholarshipType + "?\n\nThey will not be able to apply for this scholarship again (a different scholarship is still allowed).")) {
      return;
    }
    const remarks = prompt(`Enter remarks for ${action.toUpperCase()}:`, selectedRecord.remarks || "");
        const formData = new FormData();
        formData.append("id", String(selectedRecord.id));
        formData.append("action", action);
        formData.append("remarks", remarks || "");
        try {
            const apiPath = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";
            const res = await fetch(`${apiPath}/list_renewal.php`, { method: "POST", body: formData });
            const json = await res.json();
            if (json.success) {
                if (json.message && action === "terminate") alert(json.message);
                closeModal();
                loadLedger();
            }
            else {
                alert(json.message || "Failed to update status.");
            }
        }
        catch (e) {
            alert("Failed to update status.");
        }
    }
    if (renDeleteConfirmBtn) {
        renDeleteConfirmBtn.addEventListener("click", async () => {
            if (!deletingRenId)
                return;
            try {
                const apiPath = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";
                const res = await fetch(`${apiPath}/delete_renewal.php?id=${deletingRenId}`, { method: "POST" });
                const json = await res.json();
                if (json.success) {
                    closeDeleteModal();
                    loadLedger();
                }
                else {
                    alert(json.message || "Failed to delete item.");
                }
            }
            catch (e) {
                alert("Server error.");
            }
        });
    }
    if (modalClose)
        modalClose.addEventListener("click", closeModal);
    if (renewBtn)
        renewBtn.addEventListener("click", () => updateStatus("renew"));
    if (terminateBtn) terminateBtn.addEventListener("click", () => updateStatus("terminate"));
    if (renDeleteCloseBtn)
        renDeleteCloseBtn.addEventListener("click", closeDeleteModal);
    if (renDeleteCancelBtn)
        renDeleteCancelBtn.addEventListener("click", closeDeleteModal);
    function resetRenewalPageAndRender() {
        renewalCurrentPage = 1;
        renderLedger();
    }
    if (searchBox)
        searchBox.addEventListener("input", resetRenewalPageAndRender);
    if (statusFilter)
        statusFilter.addEventListener("change", resetRenewalPageAndRender);
    if (semesterFilter) semesterFilter.addEventListener("change", resetRenewalPageAndRender);
  if (schoolYearFilter) schoolYearFilter.addEventListener("change", resetRenewalPageAndRender);
  if (scholarshipFilter) scholarshipFilter.addEventListener("change", resetRenewalPageAndRender);
  if (programFilter) programFilter.addEventListener("change", resetRenewalPageAndRender);

    function csvEscape(value) {
        const str = value == null ? "" : String(value);
        if (/[",\n\r]/.test(str))
            return '"' + str.replace(/"/g, '""') + '"';
        return str;
    }
    // Exports the whole ledger (every term, every status), not just what the
    // current search/filters happen to show.
    function exportRenewalToCsv() {
        if (ledgerData.length === 0) {
            alert("There are no Renewal & Retention records to export.");
            return;
        }
        const headers = [
            "Student ID", "Name", "Scholarship Type", "GWA", "Required GWA", "Meets GWA",
            "Failing Grades", "Enrollment", "School Year", "Semester", "Status", "Remarks"
        ];
        const lines = [headers.map(csvEscape).join(",")];
        ledgerData.forEach((r) => {
            lines.push([
                r.studentId,
                r.name,
                r.scholarshipType,
                Number(r.gwa).toFixed(2),
                Number(r.gwaRequirement).toFixed(2),
                r.meetsGwa === false ? "No" : "Yes",
                r.failingGrades,
                r.enrolled ? "Enrolled" : "Not Enrolled",
                r.schoolYear,
                r.semester,
                r.status,
                r.remarks || "",
            ].map(csvEscape).join(","));
        });
        // BOM so Excel reads names with accents (ñ) correctly.
        const blob = new Blob(["\ufeff" + lines.join("\r\n")], { type: "text/csv;charset=utf-8;" });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.href = url;
        link.download = "renewal-retention-" + new Date().toISOString().slice(0, 10) + ".csv";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    }
    const exportRenewalBtn = document.getElementById("exportRenewalBtn");
    if (exportRenewalBtn)
        exportRenewalBtn.addEventListener("click", exportRenewalToCsv);
    loadLedger();
});
