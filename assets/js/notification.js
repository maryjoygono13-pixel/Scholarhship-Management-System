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

const TYPE_LABELS = {
  missing_requirements: "Missing requirements",
  renewal_deadline: "Renewal deadline",
  failed_retention: "Failed retention",
  approval_status: "Approval status",
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
  if (!iso) return "—";
  const d = new Date(iso);
  if (isNaN(d)) return iso;
  return d.toLocaleDateString("en-US", { year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit" });
}

/* ================= Log table ================= */

async function refreshNotifications() {
  try {
    const { data, summary } = await apiListNotifications(activeType);
    renderNotifTable(data);
    statSentToday.textContent = summary.sentToday;
    statMissingReq.textContent = summary.missingRequirements;
    statRenewal.textContent = summary.renewalDeadline;
    statFailed.textContent = summary.failed;
  } catch (err) {
    notifTableBody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding:32px; color:var(--slate-400);">Couldn't load notifications: ${err.message}</td></tr>`;
  }
}

function renderNotifTable(notifications) {
  notifTableBody.innerHTML = "";

  if (notifications.length === 0) {
    notifTableWrap.classList.add("hide");
    notifEmptyState.classList.add("show");
    return;
  }
  notifTableWrap.classList.remove("hide");
  notifEmptyState.classList.remove("show");

  notifications.forEach(n => {
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
          <span class="name">${n.recipientName}</span>
          <span class="email">${n.recipientEmail}</span>
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
      <td>${formatDate(n.sentAt)}</td>
    `;
    notifTableBody.appendChild(tr);
  });
}

pillFilter.addEventListener("click", (e) => {
  const btn = e.target.closest(".pill-btn");
  if (!btn) return;
  document.querySelectorAll(".pill-btn").forEach(b => b.classList.remove("active"));
  btn.classList.add("active");
  activeType = btn.dataset.type;
  refreshNotifications();
});

/* ================= Compose modal ================= */

async function openComposeModal() {
  notifType.value = "missing_requirements";
  applyTemplate();
  recipientMode = "segment";
  modeButtons.forEach(b => b.classList.toggle("active", b.dataset.mode === "segment"));
  segmentField.style.display = "block";
  individualField.style.display = "none";

  if (allApplicants.length === 0) {
    try {
      allApplicants = await apiListApplicants();
      individualSelect.innerHTML = allApplicants
        .map(a => `<option value="${a.id}">${a.firstName} ${a.lastName} (${a.studentId})</option>`)
        .join("");
    } catch (err) {
      individualSelect.innerHTML = `<option value="">Couldn't load applicants</option>`;
    }
  }

  await refreshRecipientCount();
  composeOverlay.classList.add("open");
}

function closeComposeModal() {
  composeOverlay.classList.remove("open");
}

function applyTemplate() {
  const tpl = TEMPLATES[notifType.value];
  notifSubject.value = tpl.subject;
  notifMessage.value = tpl.message;
  deadlineField.style.display = tpl.showDeadline ? "block" : "none";
}

notifType.addEventListener("change", applyTemplate);

modeButtons.forEach(btn => {
  btn.addEventListener("click", () => {
    recipientMode = btn.dataset.mode;
    modeButtons.forEach(b => b.classList.toggle("active", b === btn));
    segmentField.style.display = recipientMode === "segment" ? "block" : "none";
    individualField.style.display = recipientMode === "individual" ? "block" : "none";
    refreshRecipientCount();
  });
});

segmentSelect.addEventListener("change", refreshRecipientCount);
individualSelect.addEventListener("change", refreshRecipientCount);

async function refreshRecipientCount() {
  recipientLabel.textContent = "Loading recipients...";
  recipientCount.textContent = "—";

  if (recipientMode === "individual") {
    const selected = individualSelect.options[individualSelect.selectedIndex];
    recipientLabel.textContent = selected ? selected.textContent : "No applicants available";
    recipientCount.textContent = selected ? "1 recipient" : "0";
    return;
  }

  try {
    const { count } = await apiGetRecipients(segmentSelect.value);
    recipientLabel.textContent = segmentSelect.options[segmentSelect.selectedIndex].textContent;
    recipientCount.textContent = `${count} recipient${count === 1 ? "" : "s"}`;
  } catch (err) {
    recipientLabel.textContent = "Couldn't load recipients";
    recipientCount.textContent = "—";
  }
}

composeBtn.addEventListener("click", openComposeModal);
composeCloseBtn.addEventListener("click", closeComposeModal);
composeCancelBtn.addEventListener("click", closeComposeModal);
composeOverlay.addEventListener("click", (e) => { if (e.target === composeOverlay) closeComposeModal(); });

sendBtn.addEventListener("click", async () => {
  const payload = {
    type: notifType.value,
    subject: notifSubject.value,
    message: notifMessage.value,
    deadline: notifDeadline.value,
    recipientMode,
  };

  if (recipientMode === "segment") {
    payload.segment = segmentSelect.value;
  } else {
    if (!individualSelect.value) {
      alert("No applicant selected.");
      return;
    }
    payload.applicantId = individualSelect.value;
  }

  sendBtn.disabled = true;
  sendBtn.textContent = "Sending...";
  try {
    const result = await apiSendNotification(payload);
    alert(result.message);
    closeComposeModal();
    await refreshNotifications();
    await updateNavCounts();
  } catch (err) {
    alert(err.message);
  } finally {
    sendBtn.disabled = false;
    sendBtn.textContent = "Send";
  }
});

/* ================= Init ================= */
refreshNotifications();
updateNavCounts();

