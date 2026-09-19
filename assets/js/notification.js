"use strict";
const notifTableBody = document.getElementById("notifTableBody");
const notifTableWrap = document.getElementById("notifTableWrap");
const notifEmptyState = document.getElementById("notifEmptyState");
const pillFilter = document.getElementById("pillFilter");
const statSentToday = document.getElementById("statSentToday");
const statMissingReq = document.getElementById("statMissingReq");
const statRenewal = document.getElementById("statRenewal");
const statFailed = document.getElementById("statFailed");
const composeOverlay = document.getElementById("composeOverlay");
const composeBtn = document.getElementById("composeBtn");
const composeCloseBtn = document.getElementById("composeCloseBtn");
const composeCancelBtn = document.getElementById("composeCancelBtn");
const sendBtn = document.getElementById("sendBtn");
const notifType = document.getElementById("notifType");
const modeButtons = document.querySelectorAll(".mode-btn");
const segmentField = document.getElementById("segmentField");
const individualField = document.getElementById("individualField");
const segmentSelect = document.getElementById("segmentSelect");
const individualSelect = document.getElementById("individualSelect");
const recipientLabel = document.getElementById("recipientLabel");
const recipientCount = document.getElementById("recipientCount");
const deadlineField = document.getElementById("deadlineField");
const notifDeadline = document.getElementById("notifDeadline");
const notifSubject = document.getElementById("notifSubject");
const notifMessage = document.getElementById("notifMessage");
let activeType = "";
let recipientMode = "segment";
let allApplicants = [];
const NOTIF_PAGE_SIZE = 10;
let notifCurrentPage = 1;
let notifCurrentData = [];
const TYPE_LABELS = {
    missing_requirements: "Missing requirements",
    renewal_deadline: "Renewal deadline",
    failed_retention: "Failed retention",
    approval_status: "Approval status",
    new_applicant: "New applicant",
    applicant_updated: "Applicant updated",
};
const TEMPLATES = {
    missing_requirements: {
        subject: "Action needed: missing scholarship requirements",
        message: "Hi {{first_name}},\n\nYour scholarship application is missing one or more required documents. Please upload them before {{deadline}} to keep your application active.\n\nThank you!",
        showDeadline: true,
    },
    renewal_deadline: {
        subject: "Reminder: scholarship renewal deadline approaching",
        message: "Hi {{first_name}},\n\nThis is a reminder that your scholarship renewal is due on {{deadline}}. Please submit your renewal requirements before then to keep your scholarship active.\n\nThank you!",
        showDeadline: true,
    },
    failed_retention: {
        subject: "Important: scholarship retention requirements not met",
        message: "Hi {{first_name}},\n\nOur records show that your academic standing no longer meets the retention requirements for your scholarship. Please contact our office as soon as possible to discuss your options.\n\nThank you!",
        showDeadline: false,
    },
    approval_status: {
        subject: "Update on your scholarship application",
        message: "Hi {{first_name}},\n\nWe have an update regarding your scholarship application. Please log in to your portal or contact our office for details.\n\nThank you!",
        showDeadline: false,
    },
};
function formatDate(iso) {
    if (!iso)
        return "—";
    const d = new Date(iso);
    if (isNaN(d.getTime()))
        return iso;
    return d.toLocaleDateString("en-US", { year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit" });
}
/* ================= Log table ================= */
const notifViewOverlay = document.getElementById("notifViewOverlay");
const notifViewCloseBtn = document.getElementById("notifViewCloseBtn");
const notifViewCloseBtn2 = document.getElementById("notifViewCloseBtn2");
const notifViewBody = document.getElementById("notifViewBody");
const notifDeleteOverlay = document.getElementById("notifDeleteOverlay");
const notifDeleteCloseBtn = document.getElementById("notifDeleteCloseBtn");
const notifDeleteCancelBtn = document.getElementById("notifDeleteCancelBtn");
const notifDeleteConfirmBtn = document.getElementById("notifDeleteConfirmBtn");
let deletingNotifId = null;
async function refreshNotifications() {
    try {
        let resData;
        if (typeof window.apiListNotifications === 'function') {
            resData = await window.apiListNotifications(activeType);
        } else {
            const apiPath = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";
            const url = activeType ? `${apiPath}/list_notifications.php?type=${encodeURIComponent(activeType)}` : `${apiPath}/list_notifications.php`;
            const res = await fetch(url);
            resData = await res.json();
        }
        const data = resData.data || [];
        const summary = resData.summary;
        renderNotifTable(data);
        if (summary) {
            if (statSentToday)
                statSentToday.textContent = String(summary.sent_today ?? summary.sentToday ?? 0);
            if (statMissingReq)
                statMissingReq.textContent = String(summary.missing_req ?? summary.missingRequirements ?? 0);
            if (statRenewal)
                statRenewal.textContent = String(summary.renewal ?? summary.renewalDeadline ?? 0);
            if (statFailed)
                statFailed.textContent = String(summary.failed ?? 0);
        }
    }
    catch (err) {
        console.error("Error refreshing notifications:", err);
        if (notifTableBody)
            notifTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:32px; color:var(--slate-400);">Couldn't load notifications: ${err.message}</td></tr>`;
    }
}
function renderNotifPagination(total) {
    const wrap = document.getElementById("notificationPagination");
    if (!wrap)
        return;
    const totalPages = Math.max(1, Math.ceil(total / NOTIF_PAGE_SIZE));
    if (notifCurrentPage > totalPages)
        notifCurrentPage = totalPages;
    if (notifCurrentPage < 1)
        notifCurrentPage = 1;
    if (total === 0) {
        wrap.innerHTML = "";
        return;
    }
    const start = (notifCurrentPage - 1) * NOTIF_PAGE_SIZE + 1;
    const end = Math.min(notifCurrentPage * NOTIF_PAGE_SIZE, total);
    const buttons = `<button type="button" class="active" data-page="${notifCurrentPage}" disabled>${notifCurrentPage}</button>`;
    wrap.innerHTML =
        `<span>Showing ${start}–${end} of ${total} entries</span>` +
        `<div class="page-btns">` +
        `<button type="button" data-page="${notifCurrentPage - 1}" ${notifCurrentPage <= 1 ? "disabled" : ""}>Prev</button>` +
        buttons +
        `<button type="button" data-page="${notifCurrentPage + 1}" ${notifCurrentPage >= totalPages ? "disabled" : ""}>Next</button>` +
        `</div>`;
    wrap.querySelectorAll("button[data-page]").forEach(btn => {
        btn.addEventListener("click", () => {
            const p = parseInt(btn.getAttribute("data-page") || "", 10);
            if (!isNaN(p) && p >= 1 && p <= totalPages) {
                notifCurrentPage = p;
                renderNotifTable(notifCurrentData);
            }
        });
    });
}
function renderNotifTable(notifications) {
    if (!notifTableBody)
        return;
    notifCurrentData = notifications || [];
    notifTableBody.innerHTML = "";
    if (!notifications || notifications.length === 0) {
        if (notifTableWrap)
            notifTableWrap.classList.add("hide");
        if (notifEmptyState)
            notifEmptyState.classList.add("show");
        renderNotifPagination(0);
        return;
    }
    if (notifTableWrap)
        notifTableWrap.classList.remove("hide");
    if (notifEmptyState)
        notifEmptyState.classList.remove("show");
    const totalPages = Math.max(1, Math.ceil(notifications.length / NOTIF_PAGE_SIZE));
    if (notifCurrentPage > totalPages)
        notifCurrentPage = totalPages;
    if (notifCurrentPage < 1)
        notifCurrentPage = 1;
    const pageItems = notifications.slice((notifCurrentPage - 1) * NOTIF_PAGE_SIZE, notifCurrentPage * NOTIF_PAGE_SIZE);
    pageItems.forEach(n => {
        const tr = document.createElement("tr");
        const statusIcon = n.status === "sent"
            ? `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>`
            : n.status === "failed"
                ? `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>`
                : `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/></svg>`;
        const statusText = n.status === "sent" ? "Delivered" : n.status === "failed" ? "Failed" : "Pending";
        tr.innerHTML = `
      <td>
        <div class="name-cell">
          <span class="name">${n.recipientName || 'Recipient'}</span>
          <span class="email">${n.recipientEmail || ''}</span>
        </div>
      </td>
      <td><span class="badge badge-neutral">${TYPE_LABELS[n.type] || n.type}</span></td>
      <td>
        <span style="display:inline-flex; align-items:center; gap:6px; color:var(--slate-500);">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z" opacity="0"/><path d="M22 6 12 13 2 6"/><rect x="2" y="4" width="20" height="16" rx="2"/></svg>
          Email
        </span>
      </td>
      <td><span class="notif-status ${n.status}">${statusIcon} ${statusText}</span></td>
      <td><span class="font-mono">${formatDate(n.sentAt || n.sent_at)}</span></td>
      <td class="actions-cell">
        <button type="button" class="btn-icon-action delete" title="Delete Notification" onclick="confirmDeleteNotif(event, ${n.id})">
          <i data-lucide="trash-2"></i>
        </button>
      </td>
    `;
        tr.addEventListener("click", () => openNotifViewModal(n));
        notifTableBody.appendChild(tr);
    });
    renderNotifPagination(notifications.length);
    if (typeof lucide !== "undefined")
        lucide.createIcons();
}
function openNotifViewModal(n) {
    if (!notifViewOverlay || !notifViewBody)
        return;
    notifViewBody.innerHTML = `
    <div class="view-detail-grid">
      <div class="detail-item full-width">
        <span class="detail-label">Recipient</span>
        <span class="detail-value highlight">${n.recipientName || 'Recipient'} (${n.recipientEmail || 'No email'})</span>
      </div>
      <div class="detail-item">
        <span class="detail-label">Notification Type</span>
        <span class="detail-value">${TYPE_LABELS[n.type] || n.type}</span>
      </div>
      <div class="detail-item">
        <span class="detail-label">Sent Timestamp</span>
        <span class="detail-value mono font-mono">${formatDate(n.sentAt || n.sent_at)}</span>
      </div>
      <div class="detail-item full-width">
        <span class="detail-label">Subject</span>
        <span class="detail-value">${n.subject}</span>
      </div>
      <div class="detail-item full-width">
        <span class="detail-label">Message Content</span>
        <div class="detail-value remarks" style="white-space:pre-wrap;">${n.message}</div>
      </div>
    </div>
  `;
    notifViewOverlay.classList.add("open");
}
function closeNotifViewModal() {
    if (notifViewOverlay)
        notifViewOverlay.classList.remove("open");
}
window.confirmDeleteNotif = function (event, id) {
    event.stopPropagation();
    deletingNotifId = id;
    if (notifDeleteOverlay)
        notifDeleteOverlay.classList.add("open");
};
function closeNotifDeleteModal() {
    if (notifDeleteOverlay)
        notifDeleteOverlay.classList.remove("open");
    deletingNotifId = null;
}
if (notifDeleteConfirmBtn) {
    notifDeleteConfirmBtn.addEventListener("click", async () => {
        if (!deletingNotifId)
            return;
        try {
            const apiPath = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";
            const res = await fetch(`${apiPath}/delete_notification.php?id=${deletingNotifId}`, { method: "POST" });
            const json = await res.json();
            if (json.success) {
                closeNotifDeleteModal();
                refreshNotifications();
            }
            else {
                alert(json.message || "Failed to delete notification.");
            }
        }
        catch (e) {
            alert("Server error.");
        }
    });
}
if (notifViewCloseBtn)
    notifViewCloseBtn.addEventListener("click", closeNotifViewModal);
if (notifViewCloseBtn2)
    notifViewCloseBtn2.addEventListener("click", closeNotifViewModal);
if (notifDeleteCloseBtn)
    notifDeleteCloseBtn.addEventListener("click", closeNotifDeleteModal);
if (notifDeleteCancelBtn)
    notifDeleteCancelBtn.addEventListener("click", closeNotifDeleteModal);
if (pillFilter) {
    pillFilter.addEventListener("click", (e) => {
        const target = e.target;
        const btn = target ? target.closest(".pill-btn") : null;
        if (!btn)
            return;
        document.querySelectorAll(".pill-btn").forEach(b => b.classList.remove("active"));
        btn.classList.add("active");
        activeType = btn.dataset.type || "";
        notifCurrentPage = 1;
        refreshNotifications();
    });
}
/* ================= Compose modal ================= */
async function openComposeModal() {
    if (notifType)
        notifType.value = "missing_requirements";
    applyTemplate();
    recipientMode = "segment";
    modeButtons.forEach(b => b.classList.toggle("active", b.dataset.mode === "segment"));
    if (segmentField)
        segmentField.style.display = "block";
    if (individualField)
        individualField.style.display = "none";
    if (allApplicants.length === 0 && individualSelect) {
        try {
            allApplicants = await window.apiListApplicants();
            individualSelect.innerHTML = allApplicants
                .map(a => `<option value="${a.id}">${a.firstName} ${a.lastName} (${a.studentId})</option>`)
                .join("");
        }
        catch (err) {
            individualSelect.innerHTML = `<option value="">Couldn't load applicants</option>`;
        }
    }
    await refreshRecipientCount();
    if (composeOverlay)
        composeOverlay.classList.add("open");
}
function closeComposeModal() {
    if (composeOverlay)
        composeOverlay.classList.remove("open");
}
function applyTemplate() {
    if (!notifType)
        return;
    const tpl = TEMPLATES[notifType.value];
    if (!tpl)
        return;
    if (notifSubject)
        notifSubject.value = tpl.subject;
    if (notifMessage)
        notifMessage.value = tpl.message;
    if (deadlineField)
        deadlineField.style.display = tpl.showDeadline ? "block" : "none";
}
if (notifType)
    notifType.addEventListener("change", applyTemplate);
modeButtons.forEach(btn => {
    btn.addEventListener("click", () => {
        recipientMode = btn.dataset.mode || "segment";
        modeButtons.forEach(b => b.classList.toggle("active", b === btn));
        if (segmentField)
            segmentField.style.display = recipientMode === "segment" ? "block" : "none";
        if (individualField)
            individualField.style.display = recipientMode === "individual" ? "block" : "none";
        refreshRecipientCount();
    });
});
if (segmentSelect)
    segmentSelect.addEventListener("change", refreshRecipientCount);
if (individualSelect)
    individualSelect.addEventListener("change", refreshRecipientCount);
async function refreshRecipientCount() {
    if (recipientLabel)
        recipientLabel.textContent = "Loading recipients...";
    if (recipientCount)
        recipientCount.textContent = "—";
    if (recipientMode === "individual") {
        if (individualSelect && individualSelect.selectedIndex >= 0) {
            const selected = individualSelect.options[individualSelect.selectedIndex];
            if (recipientLabel)
                recipientLabel.textContent = selected ? selected.textContent : "No applicants available";
            if (recipientCount)
                recipientCount.textContent = selected ? "1 recipient" : "0";
        }
        return;
    }
    try {
        if (!segmentSelect)
            return;
        const { count } = await window.apiGetRecipients(segmentSelect.value);
        if (recipientLabel && segmentSelect.selectedIndex >= 0) {
            recipientLabel.textContent = segmentSelect.options[segmentSelect.selectedIndex].textContent;
        }
        if (recipientCount)
            recipientCount.textContent = `${count} recipient${count === 1 ? "" : "s"}`;
    }
    catch (err) {
        if (recipientLabel)
            recipientLabel.textContent = "Couldn't load recipients";
        if (recipientCount)
            recipientCount.textContent = "—";
    }
}
if (composeBtn)
    composeBtn.addEventListener("click", openComposeModal);
if (composeCloseBtn)
    composeCloseBtn.addEventListener("click", closeComposeModal);
if (composeCancelBtn)
    composeCancelBtn.addEventListener("click", closeComposeModal);
if (composeOverlay)
    composeOverlay.addEventListener("click", (e) => { if (e.target === composeOverlay)
        closeComposeModal(); });
if (sendBtn) {
    sendBtn.addEventListener("click", async () => {
        const payload = {
            type: notifType ? notifType.value : "",
            subject: notifSubject ? notifSubject.value : "",
            message: notifMessage ? notifMessage.value : "",
            deadline: notifDeadline ? notifDeadline.value : "",
            recipientMode,
        };
        if (recipientMode === "segment") {
            payload.segment = segmentSelect ? segmentSelect.value : "";
        }
        else {
            if (!individualSelect || !individualSelect.value) {
                alert("No applicant selected.");
                return;
            }
            payload.applicantId = individualSelect.value;
        }
        sendBtn.disabled = true;
        sendBtn.textContent = "Sending...";
        try {
            const result = await window.apiSendNotification(payload);
            alert(result.message);
            closeComposeModal();
            await refreshNotifications();
            if (typeof window.updateNavCounts === 'function') await window.updateNavCounts();
        }
        catch (err) {
            alert(err.message);
        }
        finally {
            sendBtn.disabled = false;
            sendBtn.textContent = "Send";
        }
    });
}
/* ================= Init ================= */
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
        refreshNotifications();
        if (typeof window.updateNavCounts === 'function') window.updateNavCounts();
    });
} else {
    refreshNotifications();
    if (typeof window.updateNavCounts === 'function') window.updateNavCounts();
}
