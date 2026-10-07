"use strict";
// What a renewal row's status is called on screen ("eligible" in the database means renewed).
function renewalStatusLabel(r) {
  const s = String(r.status || "").toLowerCase();
  if (s === "eligible") return "Renewed";
  if (s === "terminated") return "Terminated";
  return "Pending";
}

// "Met"/"Not met" only mean something once a GWA is actually on file — a GWA of 0.00 is no
// grades yet (e.g. a new first-year scholar), not a passed check, so it gets its own label.
function gwaMetLabel(r) {
    if (!(r.gwa > 0))
        return "No grades yet";
    return r.meetsGwa === false ? "Not met" : "Met";
}
function gwaMetColor(r) {
    if (!(r.gwa > 0))
        return "#6b7280";
    return r.meetsGwa === false ? "#be123c" : "#15803d";
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
    }

  // Enrollment for the active term, as the Registrar's database reports it (checked whenever the
  // Active Semester / Academic Year changes). Falls back to the stored flag when not checked yet.
  const enrEsc = (v) => String(v ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
  function enrollmentText(r) {
    if (r.enrollmentStatus) return r.enrollmentStatus;
    return r.enrolled ? "Enrolled" : "Not Enrolled";
  }
  function enrollmentCell(r) {
    if (!r.enrollmentStatus) return r.enrolled ? "Enrolled" : "Not Enrolled";
    const cls = r.registrarNotEnrolled ? "enroll-pill bad" : (r.enrolled ? "enroll-pill ok" : "enroll-pill none");
    return '<span class="' + cls + '" title="Registrar\'s database, ' + enrEsc(r.enrollmentTerm) + '">' + enrEsc(r.enrollmentStatus) + '</span>';
  }
  function enrollmentDetail(r) {
    if (!r.enrollmentStatus) return r.enrolled ? "Validated Enrollment" : "Unconfirmed Enrollment";
    return enrEsc(r.enrollmentStatus) + ' <span style="color:#6b7280; font-size:12px;">— Registrar\'s database, ' + enrEsc(r.enrollmentTerm) + '</span>';
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
        <td><span class="font-mono" style="color:${r.meetsGwa === false ? '#be123c' : 'inherit'};">${r.gwa.toFixed(2)}</span><div style="font-size:11px; color:#6b7280;">${r.gwaSemester || r.semester || "1st Semester"} GWA · Req. ≤ ${Number(r.gwaRequirement).toFixed(2)}</div></td>
        <td>${r.failingGrades > 0 ? `<span class="font-mono" style="color:red;">${r.failingGrades} Failing</span>` : (r.gwa > 0 ? "Passed All" : "No grades yet")}</td>
        <td>${enrollmentCell(r)}</td>
        <td><span class="font-mono">${r.decisionSemester || r.semester || "1st Semester"}</span>${r.decisionSchoolYear && r.decisionSchoolYear !== r.schoolYear ? `<div style="font-size:11px; color:#6b7280;">${r.decisionSchoolYear}</div>` : ""}</td>
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
        // Read-only: shows the term a renew/terminate decision here takes effect in — the
        // term right after the one the scholar was evaluated for, not that term itself.
        // Message icon: opens the same message window as Evaluation (templates, Sent log), with a
        // starting template that fits this scholar's renewal status.
        const modalMsgBtn = document.getElementById("modalMsgBtn");
        if (modalMsgBtn) {
          const rec = selectedRecord;
          modalMsgBtn.onclick = () => {
            if (!window.openApplicantMessage) return;
            const defaultType = rec.status === "terminated" || rec.status === "at-risk" ? "failed_retention" : (rec.status === "pending" ? "renewal_deadline" : "approval_status");
            window.openApplicantMessage({ kind: "renewal", id: rec.id, name: rec.name, studentId: rec.studentId, email: rec.email || "", defaultType });
          };
        }
        if (modalCriteria) {
            modalCriteria.innerHTML = `
        <div class="view-detail-grid" style="margin-bottom:12px;">
          <div class="detail-item full-width">
            <span class="detail-label">Scholarship Type</span>
            <span class="detail-value highlight">${selectedRecord.scholarshipType}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Current GWA (${selectedRecord.gwaSemester || selectedRecord.semester || "1st Semester"})</span>
            <span class="detail-value mono font-mono">${selectedRecord.gwa.toFixed(2)}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Required GWA</span>
            <span class="detail-value font-mono" style="color:${gwaMetColor(selectedRecord)};">≤ ${Number(selectedRecord.gwaRequirement).toFixed(2)} — ${gwaMetLabel(selectedRecord)}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Failing Grades</span>
            <span class="detail-value font-mono ${selectedRecord.failingGrades > 0 ? 'metric fail' : 'metric pass'}">${selectedRecord.failingGrades}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Semester</span>
            <span class="detail-value font-mono" title="The term a Renew/Terminate decision takes effect in (set by the Active Semester in Settings)">${normalizeSemesterValue(selectedRecord.decisionSemester || selectedRecord.semester)}</span>
          </div>
          <div class="detail-item full-width">
            <span class="detail-label">Enrollment Status</span>
            <span class="detail-value">${enrollmentDetail(selectedRecord)}</span>
          </div>
        </div>
      `;
        }
            const lockNote = document.getElementById("modalLockNote");
    if (lockNote) {
      const r = selectedRecord;
      let note = "";
      if (r.status === "eligible" && r.locked) note = "Renewed for " + (r.decidedSchoolYear || r.schoolYear) + ". Locked until a new Academic Year begins (Settings > Portal Configuration), then this can be reassessed.";
      else if (r.status === "terminated" && r.locked) note = "Terminated for " + (r.decidedSchoolYear || r.schoolYear) + ". Locked until a new Academic Year begins (Settings > Portal Configuration).";
      else if (r.status === "eligible" || r.status === "terminated") note = "A new Academic Year has begun — this entry can now be reassessed (Renew or Terminate).";
      else if (r.locked) note = "Locked: view only. This entry can be renewed or terminated once the Active Semester moves past " + r.semester + " (Settings > Portal Configuration).";
      else if (r.registrarNotEnrolled) note = "The Registrar's database shows this scholar is not enrolled for " + r.enrollmentTerm + " (" + r.enrollmentStatus + "): the scholarship can only be terminated.";
      else if (r.origin === "rejected") note = "Rejected in Evaluation: this entry can only be terminated (closed). The applicant can still apply again.";
      else if (!(r.gwa > 0)) note = "No grades are recorded for " + (r.gwaSemester || r.semester) + " yet — Renew or Terminate can still be decided manually.";
      else if (r.meetsGwa) note = "GWA meets the requirement — Renew or Terminate can be decided manually.";
      else note = "GWA does not meet the requirement — Renew or Terminate can be decided manually.";
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

    // Exports the whole ledger (every term, every status), not just what the
    // current search/filters happen to show.
    function exportRenewalToCsv() {
        if (ledgerData.length === 0) {
            alert("There are no Renewal & Retention records to export.");
            return;
        }
        const headers = [
            "Student ID", "Name", "Scholarship Type", "GWA", "GWA Semester", "Required GWA", "Meets GWA",
            "Failing Grades", "Enrollment", "School Year", "Decision Semester", "Status", "Remarks"
        ];
        const out = [headers];
        ledgerData.forEach((r) => {
            out.push([
                r.studentId,
                r.name,
                r.scholarshipType,
                Number(r.gwa).toFixed(2),
                r.gwaSemester || r.semester || "1st Semester",
                Number(r.gwaRequirement).toFixed(2),
                !(r.gwa > 0) ? "No grades yet" : (r.meetsGwa === false ? "No" : "Yes"),
                r.failingGrades,
                enrollmentText(r),
                r.schoolYear,
                r.decisionSemester || r.semester,
                r.status,
                r.remarks || "",
            ]);
        });
        window.downloadXlsx({ filename: "renewal-retention-" + new Date().toISOString().slice(0, 10), sheet: "Renewal & Retention", rows: out, numberCols: [3, 5, 7] });
    }
    const exportRenewalBtn = document.getElementById("exportRenewalBtn");
    if (exportRenewalBtn)
        exportRenewalBtn.addEventListener("click", exportRenewalToCsv);
    loadLedger();
});
