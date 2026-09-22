const notifTableBody = document.getElementById("notifTableBody");
const notifTableWrap = document.getElementById("notifTableWrap");
const notifEmptyState = document.getElementById("notifEmptyState");
const pillFilter = document.getElementById("pillFilter");

const statSentToday = document.getElementById("statSentToday");
const statMissingReq = document.getElementById("statMissingReq");
const statRenewal = document.getElementById("statRenewal");
const statFailedRetention = document.getElementById("statFailedRetention");

const composeOverlay = document.getElementById("composeOverlay");
const composeBtn = document.getElementById("composeBtn");
const composeCloseBtn = document.getElementById("composeCloseBtn");
const composeCancelBtn = document.getElementById("composeCancelBtn");
const sendBtn = document.getElementById("sendBtn") as HTMLButtonElement | null;

const notifType = document.getElementById("notifType") as HTMLSelectElement | null;
const modeButtons = document.querySelectorAll<HTMLElement>(".mode-btn");
const segmentField = document.getElementById("segmentField");
const individualField = document.getElementById("individualField");
const segmentSelect = document.getElementById("segmentSelect") as HTMLSelectElement | null;
const individualSelect = document.getElementById("individualSelect") as HTMLSelectElement | null;
const recipientLabel = document.getElementById("recipientLabel");
const recipientCount = document.getElementById("recipientCount");
const deadlineField = document.getElementById("deadlineField");
const notifDeadline = document.getElementById("notifDeadline") as HTMLInputElement | null;
const notifSubject = document.getElementById("notifSubject") as HTMLInputElement | null;
const notifMessage = document.getElementById("notifMessage") as HTMLTextAreaElement | null;

let activeType = "";
let recipientMode = "segment";
const NOTIF_PAGE_SIZE = 10;
let notifCurrentPage = 1;
let notifCurrentData: any[] = [];

const TYPE_LABELS: Record<string, string> = {
  missing_requirements: "Missing requirements",
  renewal_deadline: "Renewal deadline",
  failed_retention: "Failed retention",
  approval_status: "Approval status",
  new_applicant: "New applicant",
  applicant_updated: "Applicant updated",
  reply: "Reply",
};

interface TemplateInfo {
  subject: string;
  message: string;
  showDeadline: boolean;
}

const TEMPLATES: Record<string, TemplateInfo> = {
  missing_requirements: {
    subject: "Action needed: missing scholarship requirements",
    message: "Hi {{first_name}},\n\nWe noticed that your scholarship application is still missing some required documents. Please submit the remaining requirements by {{deadline}} so that your application can continue to be processed.\n\nThank you, and we look forward to receiving your documents.",
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
    message: "Hi {{first_name}},\n\nWe have an update regarding your scholarship application. Please contact our office for details.\n\nThank you!",
    showDeadline: false,
  },
};

function formatDate(iso?: string): string {
  if (!iso) return "—";
  const d = new Date(iso);
  if (isNaN(d.getTime())) return iso;
  return d.toLocaleDateString("en-US", { year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit" });
}

function utcIso(v: string): string {
  if (!v) return v;
  return v.includes("T") ? v : v.replace(" ", "T") + "Z";
}
// Times in the database are UTC (CURRENT_TIMESTAMP); tag them so they display in local time.
/* ================= Log table ================= */

const notifViewOverlay = document.getElementById("notifViewOverlay");
const notifViewCloseBtn = document.getElementById("notifViewCloseBtn");
const notifViewCloseBtn2 = document.getElementById("notifViewCloseBtn2");
const notifViewBody = document.getElementById("notifViewBody");

const notifDeleteOverlay = document.getElementById("notifDeleteOverlay");
const notifDeleteCloseBtn = document.getElementById("notifDeleteCloseBtn");
const notifDeleteCancelBtn = document.getElementById("notifDeleteCancelBtn");
const notifDeleteConfirmBtn = document.getElementById("notifDeleteConfirmBtn");

let deletingNotifId: number | null = null;

async function refreshNotifications(): Promise<void> {
  try {
    const { data, summary } = await (window as any).apiListNotifications(activeType);
    renderNotifTable(data || []);
    if (summary) {
      if (statSentToday) statSentToday.textContent = String(summary.sent_today ?? summary.sentToday ?? 0);
      if (statMissingReq) statMissingReq.textContent = String(summary.missing_req ?? summary.missingRequirements ?? 0);
      if (statRenewal) statRenewal.textContent = String(summary.renewal ?? summary.renewalDeadline ?? 0);
      if (statFailedRetention) statFailedRetention.textContent = String(summary.failedRetention ?? summary.failed_retention ?? 0);
    }
  } catch (err: any) {
    if (notifTableBody) notifTableBody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding:32px; color:var(--slate-400);">Couldn't load notifications: ${err.message}</td></tr>`;
  }
}

function renderNotifPagination(total: number): void {
  const wrap = document.getElementById("notificationPagination");
  if (!wrap) return;
  const totalPages = Math.max(1, Math.ceil(total / NOTIF_PAGE_SIZE));
  if (notifCurrentPage > totalPages) notifCurrentPage = totalPages;
  if (notifCurrentPage < 1) notifCurrentPage = 1;
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
  wrap.querySelectorAll<HTMLButtonElement>("button[data-page]").forEach(btn => {
    btn.addEventListener("click", () => {
      const p = parseInt(btn.getAttribute("data-page") || "", 10);
      if (!isNaN(p) && p >= 1 && p <= totalPages) {
        notifCurrentPage = p;
        renderNotifTable(notifCurrentData);
      }
    });
  });
}

function renderNotifTable(notifications: any[]): void {
  if (!notifTableBody) return;
  notifCurrentData = notifications || [];
  notifTableBody.innerHTML = "";

  if (notifications.length === 0) {
    if (notifTableWrap) notifTableWrap.classList.add("hide");
    if (notifEmptyState) notifEmptyState.classList.add("show");
    renderNotifPagination(0);
    return;
  }
  if (notifTableWrap) notifTableWrap.classList.remove("hide");
  if (notifEmptyState) notifEmptyState.classList.remove("show");

  const totalPages = Math.max(1, Math.ceil(notifications.length / NOTIF_PAGE_SIZE));
  if (notifCurrentPage > totalPages) notifCurrentPage = totalPages;
  if (notifCurrentPage < 1) notifCurrentPage = 1;
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
          <span class="name">${inboxEscape(n.recipientName || 'Recipient')}</span>
          <span class="email">${inboxEscape(n.recipientEmail || '')}</span>
        </div>
      </td>
      <td><span class="badge badge-neutral">${inboxEscape(TYPE_LABELS[n.type] || n.type)}</span></td>
      <td>
        <span style="display:inline-flex; align-items:center; gap:6px; color:var(--slate-500);">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16v16H4z" opacity="0"/><path d="M22 6 12 13 2 6"/><rect x="2" y="4" width="20" height="16" rx="2"/></svg>
          Email
        </span>
      </td>
      <td><span class="notif-status ${n.status}">${statusIcon} ${statusText}</span></td>
      <td><span class="font-mono">${formatDate(utcIso(n.sentAt || n.sent_at))}</span></td>
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
  if (typeof lucide !== "undefined") lucide.createIcons();
}

function openNotifViewModal(n: any): void {
  if (!notifViewOverlay || !notifViewBody) return;
  notifViewBody.innerHTML = `
    <div class="view-detail-grid">
      <div class="detail-item full-width">
        <span class="detail-label">Recipient</span>
        <span class="detail-value highlight">${inboxEscape(n.recipientName || 'Recipient')} (${inboxEscape(n.recipientEmail || 'No email')})</span>
      </div>
      <div class="detail-item">
        <span class="detail-label">Notification Type</span>
        <span class="detail-value">${inboxEscape(TYPE_LABELS[n.type] || n.type)}</span>
      </div>
      <div class="detail-item">
        <span class="detail-label">Sent Timestamp</span>
        <span class="detail-value mono font-mono">${formatDate(utcIso(n.sentAt || n.sent_at))}</span>
      </div>
      <div class="detail-item full-width">
        <span class="detail-label">Subject</span>
        <span class="detail-value">${inboxEscape(n.subject)}</span>
      </div>
      <div class="detail-item full-width">
        <span class="detail-label">Message Content</span>
        <div class="detail-value remarks" style="white-space:pre-wrap;">${inboxEscape(n.message)}</div>
      </div>
      ${n.status === 'failed' && n.errorMessage ? '<div class="detail-item full-width"><span class="detail-label">Why it was not sent</span><span class="detail-value" style="color:#b91c1c;">' + inboxEscape(n.errorMessage) + '</span></div>' : ''}
    </div>
  `;
  notifViewOverlay.classList.add("open");
}

function closeNotifViewModal(): void {
  if (notifViewOverlay) notifViewOverlay.classList.remove("open");
}

window.confirmDeleteNotif = function(event: MouseEvent, id: number): void {
  event.stopPropagation();
  deletingNotifId = id;
  if (notifDeleteOverlay) notifDeleteOverlay.classList.add("open");
};

function closeNotifDeleteModal(): void {
  if (notifDeleteOverlay) notifDeleteOverlay.classList.remove("open");
  deletingNotifId = null;
}

if (notifDeleteConfirmBtn) {
  notifDeleteConfirmBtn.addEventListener("click", async () => {
    if (!deletingNotifId) return;
    try {
      const apiPath = (typeof window !== "undefined" && (window as any).API_BASE) ? (window as any).API_BASE : "api";
      const res = await fetch(`${apiPath}/delete_notification.php?id=${deletingNotifId}`, { method: "POST" });
      const json = await res.json();
      if (json.success) {
        closeNotifDeleteModal();
        refreshNotifications();
      } else {
        alert(json.message || "Failed to delete notification.");
      }
    } catch (e) {
      alert("Server error.");
    }
  });
}

if (notifViewCloseBtn) notifViewCloseBtn.addEventListener("click", closeNotifViewModal);
if (notifViewCloseBtn2) notifViewCloseBtn2.addEventListener("click", closeNotifViewModal);
if (notifDeleteCloseBtn) notifDeleteCloseBtn.addEventListener("click", closeNotifDeleteModal);
if (notifDeleteCancelBtn) notifDeleteCancelBtn.addEventListener("click", closeNotifDeleteModal);

if (pillFilter) {
  pillFilter.addEventListener("click", (e) => {
    const target = e.target as HTMLElement | null;
    const btn = target ? target.closest<HTMLElement>(".pill-btn") : null;
    if (!btn) return;
    document.querySelectorAll(".pill-btn").forEach(b => b.classList.remove("active"));
    btn.classList.add("active");
    activeType = btn.dataset.type || "";
    notifCurrentPage = 1;
    refreshNotifications();
  });
}

/* ================= Compose modal ================= */

async function openComposeModal(): Promise<void> {
  if (notifType) notifType.value = "missing_requirements";
  applyTemplate();
  recipientMode = "segment";
  modeButtons.forEach(b => b.classList.toggle("active", b.dataset.mode === "segment"));
  if (segmentField) segmentField.style.display = "block";
  if (individualField) individualField.style.display = "none";

  await loadIndividualOptions();
  await refreshRecipientCount();
  if (composeOverlay) composeOverlay.classList.add("open");
}

function closeComposeModal(): void {
  if (composeOverlay) composeOverlay.classList.remove("open");
}

function applyTemplate(): void {
  if (!notifType) return;
  const tpl = TEMPLATES[notifType.value];
  if (!tpl) return;
  if (notifSubject) notifSubject.value = tpl.subject;
  if (notifMessage) notifMessage.value = tpl.message;
  if (deadlineField) deadlineField.style.display = tpl.showDeadline ? "block" : "none";
}

/* Who shows up in the Individual dropdown depends on the notification type:
   Missing requirements -> Applicants with a document missing; Renewal deadline /
   Failed retention -> Renewal & Retention entries; Approval status -> Records. */
async function loadIndividualOptions(): Promise<void> {
  if (!individualSelect) return;
  const type = notifType ? notifType.value : "";
  individualSelect.innerHTML = `<option value="">Loading...</option>`;
  try {
    const res = await fetch(`api/get_recipients.php?mode=individual&type=${encodeURIComponent(type)}`);
    const json = await res.json();
    if (!json.success) throw new Error(json.message || "Failed to load recipients.");
    const options = (json.data || []) as { id: number; kind: string; studentId: string; name: string }[];
    individualSelect.innerHTML = options.length
      ? options.map(o => `<option value="${o.kind}:${o.id}">${o.name}${o.studentId ? " (" + o.studentId + ")" : ""}</option>`).join("")
      : `<option value="">No matching recipients</option>`;
  } catch (err) {
    individualSelect.innerHTML = `<option value="">Couldn't load recipients</option>`;
  }
  if (recipientMode === "individual") refreshRecipientCount();
}

if (notifType) notifType.addEventListener("change", () => {
  applyTemplate();
  loadIndividualOptions();
});

modeButtons.forEach(btn => {
  btn.addEventListener("click", () => {
    recipientMode = btn.dataset.mode || "segment";
    modeButtons.forEach(b => b.classList.toggle("active", b === btn));
    if (segmentField) segmentField.style.display = recipientMode === "segment" ? "block" : "none";
    if (individualField) individualField.style.display = recipientMode === "individual" ? "block" : "none";
    refreshRecipientCount();
  });
});

if (segmentSelect) segmentSelect.addEventListener("change", refreshRecipientCount);
if (individualSelect) individualSelect.addEventListener("change", refreshRecipientCount);

async function refreshRecipientCount(): Promise<void> {
  if (recipientLabel) recipientLabel.textContent = "Loading recipients...";
  if (recipientCount) recipientCount.textContent = "—";

  if (recipientMode === "individual") {
    if (individualSelect && individualSelect.selectedIndex >= 0) {
      const selected = individualSelect.options[individualSelect.selectedIndex];
      if (recipientLabel) recipientLabel.textContent = selected ? selected.textContent : "No applicants available";
      if (recipientCount) recipientCount.textContent = selected ? "1 recipient" : "0";
    }
    return;
  }

  try {
    if (!segmentSelect) return;
    const { count } = await (window as any).apiGetRecipients(segmentSelect.value);
    if (recipientLabel && segmentSelect.selectedIndex >= 0) {
      recipientLabel.textContent = segmentSelect.options[segmentSelect.selectedIndex].textContent;
    }
    if (recipientCount) recipientCount.textContent = `${count} recipient${count === 1 ? "" : "s"}`;
  } catch (err) {
    if (recipientLabel) recipientLabel.textContent = "Couldn't load recipients";
    if (recipientCount) recipientCount.textContent = "—";
  }
}

if (composeBtn) composeBtn.addEventListener("click", openComposeModal);
if (composeCloseBtn) composeCloseBtn.addEventListener("click", closeComposeModal);
if (composeCancelBtn) composeCancelBtn.addEventListener("click", closeComposeModal);
if (composeOverlay) composeOverlay.addEventListener("click", (e) => { if (e.target === composeOverlay) closeComposeModal(); });

if (sendBtn) {
  sendBtn.addEventListener("click", async () => {
    const payload: Record<string, string> = {
      type: notifType ? notifType.value : "",
      subject: notifSubject ? notifSubject.value : "",
      message: notifMessage ? notifMessage.value : "",
      deadline: notifDeadline ? notifDeadline.value : "",
      recipientMode,
    };

    if (recipientMode === "segment") {
      payload.segment = segmentSelect ? segmentSelect.value : "";
    } else {
      if (!individualSelect || !individualSelect.value) {
        alert("No recipient selected.");
        return;
      }
      const [kind, id] = individualSelect.value.split(":");
      payload.individualKind = kind;
      payload.applicantId = id;
    }

    sendBtn.disabled = true;
    sendBtn.textContent = "Sending...";
    try {
      const result = await (window as any).apiSendNotification(payload);
      alert(result.message);
      closeComposeModal();
      await refreshNotifications();
      await (window as any).updateNavCounts();
    } catch (err: any) {
      alert(err.message);
    } finally {
      sendBtn.disabled = false;
      sendBtn.textContent = "Send";
    }
  });
}

/* ================= Inbox (received messages) ================= */
interface InboxMessage {
  id: number;
  senderName: string;
  senderEmail: string;
  studentId: string;
  subject: string;
  message: string;
  source: string;
  threadId: string;
  gmailAccount: string;
  isRead: boolean;
  receivedAt: string;
}

const inboxTabs = document.querySelectorAll<HTMLButtonElement>(".notif-tab");
const inboxPanel = document.getElementById("inboxPanel");
const sentPanel = document.getElementById("sentPanel");
const inboxTabBadge = document.getElementById("inboxTabBadge");
const inboxTableBody = document.getElementById("inboxTableBody");
const inboxTableWrap = document.getElementById("inboxTableWrap");
const inboxEmptyState = document.getElementById("inboxEmptyState");
const inboxSearch = document.getElementById("inboxSearch") as HTMLInputElement | null;
const inboxFilter = document.getElementById("inboxFilter") as HTMLSelectElement | null;
const inboxMarkAllBtn = document.getElementById("inboxMarkAllBtn");
const inboxLogBtn = document.getElementById("inboxLogBtn");

const inboxViewOverlay = document.getElementById("inboxViewOverlay");
const inboxViewSubject = document.getElementById("inboxViewSubject");
const inboxViewMeta = document.getElementById("inboxViewMeta");
const inboxViewFrom = document.getElementById("inboxViewFrom");
const inboxViewMessage = document.getElementById("inboxViewMessage");
const inboxViewReplyBtn = document.getElementById("inboxViewReplyBtn") as HTMLAnchorElement | null;
const inboxViewUnreadBtn = document.getElementById("inboxViewUnreadBtn");
const inboxViewDeleteBtn = document.getElementById("inboxViewDeleteBtn");

const inboxLogOverlay = document.getElementById("inboxLogOverlay");
const inboxLogName = document.getElementById("inboxLogName") as HTMLInputElement | null;
const inboxLogEmail = document.getElementById("inboxLogEmail") as HTMLInputElement | null;
const inboxLogStudentId = document.getElementById("inboxLogStudentId") as HTMLInputElement | null;
const inboxLogSubject = document.getElementById("inboxLogSubject") as HTMLInputElement | null;
const inboxLogMessage = document.getElementById("inboxLogMessage") as HTMLTextAreaElement | null;

let inboxMessages: InboxMessage[] = [];
let inboxOpenId: number | null = null;
let inboxSearchTimer: number | undefined;

function inboxEscape(str: any): string {
  const d = document.createElement("div");
  d.textContent = str == null ? "" : String(str);
  return d.innerHTML;
}

function setNotifTab(tab: string): void {
  inboxTabs.forEach((b) => b.classList.toggle("active", b.dataset.tab === tab));
  if (inboxPanel) inboxPanel.hidden = tab !== "inbox";
  if (sentPanel) sentPanel.hidden = tab !== "sent";
}

async function inboxPost(body: Record<string, string>): Promise<any> {
  const res = await fetch("api/inbox.php", { method: "POST", body: new URLSearchParams(body) });
  return res.json();
}

async function loadInbox(): Promise<void> {
  try {
    const params = new URLSearchParams();
    if (inboxFilter && inboxFilter.value) params.set("filter", inboxFilter.value);
    if (inboxSearch && inboxSearch.value.trim()) params.set("q", inboxSearch.value.trim());
    const res = await fetch("api/inbox.php?" + params.toString());
    const json = await res.json();
    if (!json.success) return;
    inboxMessages = json.data || [];
    if (inboxTabBadge) {
      inboxTabBadge.textContent = String(json.unread);
      inboxTabBadge.hidden = !json.unread;
    }
    renderInbox();
  } catch (e) {
    console.error("Failed to load inbox:", e);
  }
}

function renderInbox(): void {
  if (!inboxTableBody) return;
  inboxTableBody.innerHTML = "";

  const has = inboxMessages.length > 0;
  if (inboxTableWrap) inboxTableWrap.classList.toggle("hide", !has);
  if (inboxEmptyState) inboxEmptyState.classList.toggle("show", !has);
  if (!has) return;

  inboxMessages.forEach((m) => {
    const tr = document.createElement("tr");
    tr.className = "inbox-row" + (m.isRead ? "" : " unread");
    const preview = m.message.replace(/\s+/g, " ").slice(0, 110);
    tr.innerHTML = `
      <td><span class="inbox-dot" title="${m.isRead ? "Read" : "Unread"}"></span></td>
      <td>
        <div class="inbox-from">${inboxEscape(m.senderName || m.senderEmail)}</div>
        <div class="inbox-sub">${inboxEscape(m.senderName ? m.senderEmail : "")}</div>
      </td>
      <td>
        <div class="inbox-subject">${inboxEscape(m.subject)}${m.source === "gmail" ? ' <span class="src-tag" title="Received through Gmail">Gmail</span>' : ''}</div>
        <div class="inbox-sub">${inboxEscape(preview)}${m.message.length > 110 ? "…" : ""}</div>
      </td>
      <td>${inboxEscape(formatDate(utcIso(m.receivedAt)))}</td>
      <td class="actions-cell" style="text-align:right;">
        <button type="button" class="btn-icon-action" data-inbox-toggle title="${m.isRead ? "Mark as unread" : "Mark as read"}"><i data-lucide="${m.isRead ? "mail" : "mail-open"}"></i></button>
        <button type="button" class="btn-icon-action delete" data-inbox-delete title="Delete"><i data-lucide="trash-2"></i></button>
      </td>
    `;
    tr.addEventListener("click", () => openInboxMessage(m.id));

    const toggle = tr.querySelector("[data-inbox-toggle]");
    if (toggle) toggle.addEventListener("click", async (e) => {
      e.stopPropagation();
      await inboxPost({ action: m.isRead ? "mark_unread" : "mark_read", id: String(m.id) });
      await loadInbox();
    });
    const del = tr.querySelector("[data-inbox-delete]");
    if (del) del.addEventListener("click", (e) => { e.stopPropagation(); deleteInboxMessage(m.id); });

    inboxTableBody.appendChild(tr);
  });

  if (typeof lucide !== "undefined") lucide.createIcons();
}

async function openInboxMessage(id: number): Promise<void> {
  const m = inboxMessages.find((x) => x.id === id);
  if (!m || !inboxViewOverlay) return;
  inboxOpenId = id;

  if (inboxViewSubject) inboxViewSubject.textContent = m.subject;
  if (inboxViewMeta) inboxViewMeta.textContent = "Received " + formatDate(utcIso(m.receivedAt));
  if (inboxViewFrom) {
    inboxViewFrom.innerHTML =
      `<strong>${inboxEscape(m.senderName || m.senderEmail)}</strong>` +
      (m.senderEmail ? ` &lt;${inboxEscape(m.senderEmail)}&gt;` : "") +
      (m.studentId ? ` &middot; ID ${inboxEscape(m.studentId)}` : "");
  }
  if (inboxViewMessage) inboxViewMessage.textContent = m.message;
  if (inboxViewReplyBtn) {
    inboxViewReplyBtn.hidden = !m.senderEmail;
    inboxViewReplyBtn.href = "mailto:" + m.senderEmail + "?subject=" + encodeURIComponent("Re: " + m.subject);
  }
  inboxViewOverlay.classList.add("open");
  prepareInboxThreadAndReply(m);

  if (!m.isRead) {
    await inboxPost({ action: "mark_read", id: String(id) });
    m.isRead = true;
    await loadInbox();
  }
}

function closeInboxView(): void {
  if (inboxViewOverlay) inboxViewOverlay.classList.remove("open");
  inboxOpenId = null;
}

async function deleteInboxMessage(id: number): Promise<void> {
  if (!confirm("Delete this message? This cannot be undone.")) return;
  const json = await inboxPost({ action: "delete", id: String(id) });
  if (!json.success) { alert(json.message || "Failed to delete message."); return; }
  closeInboxView();
  await loadInbox();
}

function openInboxLog(): void {
  [inboxLogName, inboxLogEmail, inboxLogStudentId, inboxLogSubject].forEach((i) => { if (i) i.value = ""; });
  if (inboxLogMessage) inboxLogMessage.value = "";
  if (inboxLogOverlay) inboxLogOverlay.classList.add("open");
  if (inboxLogName) inboxLogName.focus();
}

function closeInboxLog(): void {
  if (inboxLogOverlay) inboxLogOverlay.classList.remove("open");
}

async function saveInboxLog(): Promise<void> {
  const json = await inboxPost({
    action: "create",
    sender_name: inboxLogName ? inboxLogName.value : "",
    sender_email: inboxLogEmail ? inboxLogEmail.value : "",
    student_id: inboxLogStudentId ? inboxLogStudentId.value : "",
    subject: inboxLogSubject ? inboxLogSubject.value : "",
    message: inboxLogMessage ? inboxLogMessage.value : "",
    source: "manual",
  });
  if (!json.success) { alert(json.message || "Failed to save message."); return; }
  closeInboxLog();
  await loadInbox();
}

inboxTabs.forEach((b) => b.addEventListener("click", () => setNotifTab(b.dataset.tab || "inbox")));
if (inboxSearch) inboxSearch.addEventListener("input", () => {
  window.clearTimeout(inboxSearchTimer);
  inboxSearchTimer = window.setTimeout(loadInbox, 250);
});
if (inboxFilter) inboxFilter.addEventListener("change", loadInbox);
if (inboxMarkAllBtn) inboxMarkAllBtn.addEventListener("click", async () => { await inboxPost({ action: "mark_all_read" }); await loadInbox(); });
if (inboxLogBtn) inboxLogBtn.addEventListener("click", openInboxLog);
const inboxViewCloseBtn = document.getElementById("inboxViewCloseBtn");
if (inboxViewCloseBtn) inboxViewCloseBtn.addEventListener("click", closeInboxView);
if (inboxViewOverlay) inboxViewOverlay.addEventListener("click", (e) => { if (e.target === inboxViewOverlay) closeInboxView(); });
if (inboxViewUnreadBtn) inboxViewUnreadBtn.addEventListener("click", async () => {
  if (inboxOpenId === null) return;
  await inboxPost({ action: "mark_unread", id: String(inboxOpenId) });
  closeInboxView();
  await loadInbox();
});
if (inboxViewDeleteBtn) inboxViewDeleteBtn.addEventListener("click", () => { if (inboxOpenId !== null) deleteInboxMessage(inboxOpenId); });
const inboxLogCloseBtn = document.getElementById("inboxLogCloseBtn");
const inboxLogCancelBtn = document.getElementById("inboxLogCancelBtn");
const inboxLogSaveBtn = document.getElementById("inboxLogSaveBtn");
if (inboxLogCloseBtn) inboxLogCloseBtn.addEventListener("click", closeInboxLog);
if (inboxLogCancelBtn) inboxLogCancelBtn.addEventListener("click", closeInboxLog);
if (inboxLogSaveBtn) inboxLogSaveBtn.addEventListener("click", saveInboxLog);

// Inbox is the first thing shown on this page.
setNotifTab("inbox");
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", loadInbox);
} else {
  loadInbox();
}

/* ================= Gmail (connection bar, sync, thread + reply) =================
   The page only talks to our own api/gmail_*.php endpoints. OAuth tokens and the
   client secret stay on the server; this code never sees them. */
interface GmailStatus {
  configured: boolean;
  environment: string;
  redirectUri: string;
  connected: boolean;
  needsReauth: boolean;
  email: string | null;
  lastSyncAt: string | null;
  lastError: string;
}

const GMAIL_SYNC_INTERVAL_MS = 60000;
const gmailStatusText = document.getElementById("gmailStatusText");
const gmailDot = document.getElementById("gmailDot");
const gmailSyncBtn = document.getElementById("gmailSyncBtn") as HTMLButtonElement | null;
const gmailConnectBtn = document.getElementById("gmailConnectBtn");
const gmailDisconnectBtn = document.getElementById("gmailDisconnectBtn");
const gmailFlash = document.getElementById("gmailFlash");
const gmailSetupHint = document.getElementById("gmailSetupHint");

const inboxViewThread = document.getElementById("inboxViewThread");
const inboxReplyBox = document.getElementById("inboxReplyBox");
const inboxReplyText = document.getElementById("inboxReplyText") as HTMLTextAreaElement | null;
const inboxReplyStatus = document.getElementById("inboxReplyStatus");
const inboxReplySendBtn = document.getElementById("inboxReplySendBtn") as HTMLButtonElement | null;

let gmailState: GmailStatus | null = null;
let gmailSyncing = false;
let gmailTimer: number | undefined;

const GMAIL_ERROR_MESSAGES: Record<string, string> = {
  denied: "Gmail was not connected: the request was cancelled on the Google page.",
  state: "The Gmail connection request expired or could not be verified. Please try again.",
  config: "Gmail could not be connected because of a setup problem. Check config/gmail.local.php.",
  not_configured: "Gmail is not set up yet. Add the Google client ID and secret to config/gmail.local.php first.",
  network: "Could not reach Google. Check the internet connection and try again.",
  google: "Google reported a problem while connecting Gmail. Please try again.",
};

function showGmailFlash(text: string, kind: string): void {
  if (!gmailFlash) return;
  gmailFlash.textContent = text;
  gmailFlash.className = "gmail-flash " + kind;
  gmailFlash.hidden = !text;
}

function renderGmailBar(): void {
  const s = gmailState;
  if (!s || !gmailStatusText) return;

  const show = (el: HTMLElement | null, visible: boolean) => { if (el) el.hidden = !visible; };
  let text = "";
  let dot = "off";

  if (!s.configured) {
    text = "Gmail is not set up yet";
    if (gmailSetupHint) {
      gmailSetupHint.textContent = "Copy config/gmail.local.example.php to config/gmail.local.php and add your Google client ID and secret. Redirect URI to register in Google Cloud: " + s.redirectUri;
      gmailSetupHint.hidden = false;
    }
  } else if (s.connected) {
    text = "Connected as " + s.email;
    dot = s.lastError ? "warn" : "on";
    if (gmailSetupHint) gmailSetupHint.hidden = true;
  } else if (s.needsReauth) {
    text = "Gmail authorization expired" + (s.email ? " (" + s.email + ")" : "") + " — reconnect to continue";
    dot = "warn";
    if (gmailSetupHint) gmailSetupHint.hidden = true;
  } else {
    text = "Gmail is not connected";
    if (gmailSetupHint) gmailSetupHint.hidden = true;
  }

  gmailStatusText.textContent = text;
  if (gmailDot) gmailDot.className = "gmail-dot " + dot;
  show(gmailSyncBtn, s.connected);
  show(gmailDisconnectBtn, s.connected || s.needsReauth);
  show(gmailConnectBtn, s.configured && !s.connected);
  if (gmailConnectBtn) gmailConnectBtn.textContent = s.needsReauth ? "Reconnect Gmail" : "Connect Gmail";

  if (s.connected && s.lastError) showGmailFlash(s.lastError, "error");
}

async function loadGmailStatus(): Promise<void> {
  try {
    const res = await fetch("api/gmail_status.php", { cache: "no-store" });
    const json = await res.json();
    if (json && json.success) {
      gmailState = json;
      renderGmailBar();
    }
  } catch (e) {
    if (gmailStatusText) gmailStatusText.textContent = "Gmail status unavailable";
  }
}

async function syncGmail(manual: boolean): Promise<void> {
  if (gmailSyncing || !gmailState || !gmailState.connected) return;
  gmailSyncing = true;
  if (manual && gmailSyncBtn) { gmailSyncBtn.disabled = true; gmailSyncBtn.textContent = "Syncing…"; }

  try {
    const res = await fetch("api/gmail_sync.php", { method: "POST" });
    const json = await res.json();
    if (json.success) {
      if (!manual && gmailFlash && gmailFlash.classList.contains("error")) showGmailFlash("", "");
      if (json.new > 0 || manual) await loadInbox();
      if (manual) {
        showGmailFlash(json.new > 0 ? json.new + " new email" + (json.new === 1 ? "" : "s") + " received." : "No new emails.", "ok");
      }
    } else {
      showGmailFlash(json.message || "Gmail could not be synchronized.", "error");
    }
    await loadGmailStatus();
  } catch (e) {
    showGmailFlash("Could not reach the server to synchronize Gmail.", "error");
  } finally {
    gmailSyncing = false;
    if (gmailSyncBtn) { gmailSyncBtn.disabled = false; gmailSyncBtn.textContent = "Sync now"; }
  }
}

async function disconnectGmail(): Promise<void> {
  const who = gmailState && gmailState.email ? gmailState.email : "the Gmail account";
  if (!confirm("Disconnect " + who + "?\n\nSending and receiving through Gmail will stop until an account is connected again. Messages already downloaded stay in the Inbox.")) return;
  try {
    const res = await fetch("api/gmail_disconnect.php", { method: "POST" });
    const json = await res.json();
    showGmailFlash(json.success ? "Gmail account disconnected." : (json.message || "Could not disconnect Gmail."), json.success ? "ok" : "error");
  } catch (e) {
    showGmailFlash("Could not reach the server.", "error");
  }
  await loadGmailStatus();
}

/* ---------- thread + reply inside the existing message dialog ---------- */

function renderInboxThread(items: any[]): void {
  if (!inboxViewThread) return;
  if (items.length < 2) { inboxViewThread.hidden = true; inboxViewThread.innerHTML = ""; return; }

  inboxViewThread.innerHTML =
    '<div class="thread-title">Conversation (' + items.length + ' messages)</div>' +
    items.map((it) =>
      '<div class="thread-item ' + inboxEscape(it.direction) + '">' +
        '<div class="thread-meta"><strong>' + inboxEscape(it.name) + '</strong> &middot; ' + inboxEscape(formatDate(utcIso(it.at))) + '</div>' +
        '<div class="thread-body">' + inboxEscape(it.message) + '</div>' +
      '</div>'
    ).join("");
  inboxViewThread.hidden = false;
}

async function prepareInboxThreadAndReply(m: InboxMessage): Promise<void> {
  const isGmail = m.source === "gmail";
  const canReply = isGmail && !!m.senderEmail && !!gmailState && gmailState.connected;

  if (inboxViewThread) { inboxViewThread.hidden = true; inboxViewThread.innerHTML = ""; }
  if (inboxReplyBox) inboxReplyBox.hidden = !canReply;
  if (inboxReplyText) inboxReplyText.value = "";
  if (inboxReplyStatus) { inboxReplyStatus.textContent = ""; inboxReplyStatus.className = ""; }
  // Gmail messages are answered in-app (same thread); others keep the mail-client link.
  if (inboxViewReplyBtn && isGmail) inboxViewReplyBtn.hidden = true;

  if (isGmail && m.threadId) {
    try {
      const res = await fetch("api/inbox.php?thread=" + encodeURIComponent(m.threadId) + "&account=" + encodeURIComponent(m.gmailAccount || ""));
      const json = await res.json();
      if (json.success && inboxOpenId === m.id) renderInboxThread(json.data || []);
    } catch (e) { /* the message itself is already shown */ }
  }
}

async function sendInboxReply(): Promise<void> {
  if (inboxOpenId === null || !inboxReplyText || !inboxReplySendBtn) return;
  const body = inboxReplyText.value.trim();
  const setStatus = (t: string, cls: string) => { if (inboxReplyStatus) { inboxReplyStatus.textContent = t; inboxReplyStatus.className = cls; } };
  if (!body) { setStatus("Write a reply first.", "bad"); return; }

  const openId = inboxOpenId;
  inboxReplySendBtn.disabled = true;
  setStatus("Sending…", "");
  try {
    const res = await fetch("api/gmail_send.php", { method: "POST", body: new URLSearchParams({ reply_to_id: String(openId), body }) });
    const json = await res.json();
    if (json.success) {
      inboxReplyText.value = "";
      setStatus("Reply sent.", "ok");
      const m = inboxMessages.find((x) => x.id === openId);
      if (m && m.threadId) {
        const t = await (await fetch("api/inbox.php?thread=" + encodeURIComponent(m.threadId) + "&account=" + encodeURIComponent(m.gmailAccount || ""))).json();
        if (t.success && inboxOpenId === openId) renderInboxThread(t.data || []);
      }
      refreshNotifications();
    } else {
      setStatus(json.message || "The reply could not be sent.", "bad");
      if (json.code === "reauthorize" || json.code === "not_connected") loadGmailStatus();
    }
  } catch (e) {
    setStatus("Could not reach the server.", "bad");
  } finally {
    inboxReplySendBtn.disabled = false;
  }
}

if (gmailSyncBtn) gmailSyncBtn.addEventListener("click", () => syncGmail(true));
if (gmailDisconnectBtn) gmailDisconnectBtn.addEventListener("click", disconnectGmail);
if (inboxReplySendBtn) inboxReplySendBtn.addEventListener("click", sendInboxReply);

async function initGmail(): Promise<void> {
  // Result of the OAuth round-trip (api/gmail_callback.php redirects back here).
  const params = new URLSearchParams(window.location.search);
  if (params.get("gmail") === "connected") showGmailFlash("Gmail connected. Fetching your emails…", "ok");
  const err = params.get("gmail_error");
  if (err) showGmailFlash(GMAIL_ERROR_MESSAGES[err] || GMAIL_ERROR_MESSAGES.google, "error");
  if (params.has("gmail") || err) window.history.replaceState({}, "", window.location.pathname);

  await loadGmailStatus();
  await syncGmail(false);
  window.clearInterval(gmailTimer);
  gmailTimer = window.setInterval(() => { if (!document.hidden) syncGmail(false); }, GMAIL_SYNC_INTERVAL_MS);
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initGmail);
} else {
  initGmail();
}

/* ================= Init ================= */
refreshNotifications();
(window as any).updateNavCounts();
