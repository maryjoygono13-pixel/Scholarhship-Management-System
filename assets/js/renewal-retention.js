"use strict";
function normalizeSemesterValue(val) {
    const v = (val || "").toLowerCase();
    return (v.includes("2") || v.includes("second")) ? "2nd Semester" : "1st Semester";
}
document.addEventListener("DOMContentLoaded", () => {
    const ledgerBody = document.getElementById("ledgerBody");
    const countEligible = document.getElementById("countEligible");
    const countAtRisk = document.getElementById("countAtRisk");
    const countTerminated = document.getElementById("countTerminated");
    const countTotal = document.getElementById("countTotal");
    const searchBox = document.getElementById("searchBox");
    const statusFilter = document.getElementById("statusFilter");
    const modalOverlay = document.getElementById("modalOverlay");
    const modalClose = document.getElementById("modalClose");
    const modalName = document.getElementById("modalName");
    const modalId = document.getElementById("modalId");
    const modalCriteria = document.getElementById("modalCriteria");
    const modalRemarksText = document.getElementById("modalRemarksText");
    const modalSeal = document.getElementById("modalSeal");
    const renewBtn = document.getElementById("renewBtn");
    const flagBtn = document.getElementById("flagBtn");
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
                if (json.summary) {
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
    function renderLedger() {
        if (!ledgerBody)
            return;
        const query = searchBox ? searchBox.value.toLowerCase().trim() : "";
        const statusVal = statusFilter ? statusFilter.value.toLowerCase() : "";
        const filtered = ledgerData.filter((r) => {
            const matchQuery = r.name.toLowerCase().includes(query) || r.studentId.toLowerCase().includes(query);
            const matchStatus = !statusVal || r.status.toLowerCase() === statusVal;
            return matchQuery && matchStatus;
        });
        ledgerBody.innerHTML = "";
        if (filtered.length === 0) {
            ledgerBody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding:24px; color:#6b7280;">No scholars found.</td></tr>`;
            renderRenewalPagination(0);
            return;
        }
        const totalPages = Math.max(1, Math.ceil(filtered.length / RENEWAL_PAGE_SIZE));
        if (renewalCurrentPage > totalPages) renewalCurrentPage = totalPages;
        if (renewalCurrentPage < 1) renewalCurrentPage = 1;
        const pageItems = filtered.slice((renewalCurrentPage - 1) * RENEWAL_PAGE_SIZE, renewalCurrentPage * RENEWAL_PAGE_SIZE);
        pageItems.forEach((r) => {
            const tr = document.createElement("tr");
            const statusBadge = r.status === "eligible" ? "badge-eligible" : (r.status === "at-risk" ? "badge-at-risk" : "badge-terminated");
            tr.innerHTML = `
        <td><strong class="font-mono">${r.studentId}</strong></td>
        <td>${r.name}</td>
        <td><span class="font-mono">${r.gwa.toFixed(2)}</span></td>
        <td>${r.failingGrades > 0 ? `<span class="font-mono" style="color:red;">${r.failingGrades} Failing</span>` : "Passed All"}</td>
        <td>${r.enrolled ? "Enrolled" : "Not Enrolled"}</td>
        <td><span class="font-mono">${r.semester || "1st Semester"}</span></td>
        <td><span class="status-badge ${statusBadge}">${r.status}</span></td>
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
            modalSeal.textContent = selectedRecord.status.toUpperCase();
            const badgeCls = selectedRecord.status === 'eligible' ? 'badge-approved' : (selectedRecord.status === 'at-risk' ? 'badge-pending' : 'badge-rejected');
            modalSeal.className = `status-badge ${badgeCls}`;
        }
        if (modalRemarksText)
            modalRemarksText.textContent = selectedRecord.remarks || "No remarks logged.";
        const modalSemesterSelect = document.getElementById("modalSemesterSelect");
        if (modalSemesterSelect)
            modalSemesterSelect.value = normalizeSemesterValue(selectedRecord.semester);
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
                closeModal();
                loadLedger();
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
    const modalSemesterSelectEl = document.getElementById("modalSemesterSelect");
    if (modalSemesterSelectEl) {
        modalSemesterSelectEl.addEventListener("click", (e) => e.stopPropagation());
        modalSemesterSelectEl.addEventListener("change", async () => {
            if (!selectedRecord) return;
            const newSemester = modalSemesterSelectEl.value;
            try {
                const apiPath = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";
                const formData = new FormData();
                formData.append("id", String(selectedRecord.id));
                formData.append("action", "update_semester");
                formData.append("semester", newSemester);
                const res = await fetch(`${apiPath}/list_renewal.php`, { method: "POST", body: formData });
                const json = await res.json();
                if (json.success) {
                    selectedRecord.semester = newSemester;
                    await loadLedger();
                } else {
                    alert(json.message || "Failed to update semester.");
                }
            } catch (e) {
                alert("Server error while updating semester.");
            }
        });
    }
    if (renewBtn)
        renewBtn.addEventListener("click", () => updateStatus("renew"));
    if (flagBtn)
        flagBtn.addEventListener("click", () => updateStatus("flag"));
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
    loadLedger();
});
