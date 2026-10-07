"use strict";
/* ============================================================
   Evaluation → message an applicant without leaving the page.
   Opened by the message icon on the applicant card. It works like
   the Notifications page's Sent log: the same templates and
   {{placeholders}}, sent through api/send_notification.php (Gmail
   or SMTP) and recorded in the same Sent log, with this person's
   past messages listed above the form.
   ============================================================ */
(function () {
    const API = (window.API_BASE || "api");
    const TYPE_LABELS = {
        missing_requirements: "Missing requirements",
        renewal_deadline: "Renewal deadline",
        failed_retention: "Failed retention",
        approval_status: "Approval status",
    };
    // Same templates as the Notifications compose dialog (assets/js/notification.js).
    const TEMPLATES = {
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
            subject: "Approved Scholarship Application",
            message: "Hi {{first_name}},\n\nWe have an update regarding your scholarship application. Your application has been approved. Our registrar committee is truly happy for you. Keep up the good work and always SOAR HIGHER!",
            showDeadline: false,
        },
    };
    const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
    const fmtDate = (s) => {
        if (!s) return "";
        const d = new Date(String(s).replace(" ", "T") + (String(s).includes("Z") || String(s).includes("+") ? "" : "Z"));
        return isNaN(d.getTime()) ? String(s) : d.toLocaleString("en-PH", { month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" });
    };

    let overlay = null;
    let current = null;

    function build() {
        overlay = document.createElement("div");
        overlay.className = "custom-modal-overlay eval-msg-overlay";
        overlay.innerHTML =
            '<div class="custom-modal-card eval-msg-card">' +
            '<div class="custom-modal-header"><div><h3 id="evalMsgTitle">Message</h3><p id="evalMsgTo"></p></div>' +
            '<button type="button" class="custom-modal-close" id="evalMsgClose" aria-label="Close">' +
            '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>' +
            '<div class="custom-modal-body eval-msg-body">' +
            '<div class="eval-msg-section"><h4>Sent messages</h4><div id="evalMsgHistory" class="eval-msg-history">Loading\u2026</div></div>' +
            '<div class="eval-msg-section"><h4>New message</h4>' +
            '<label class="eval-msg-label">Type</label>' +
            '<select id="evalMsgType" class="eval-msg-input">' + Object.keys(TYPE_LABELS).map((k) => '<option value="' + k + '">' + TYPE_LABELS[k] + "</option>").join("") + "</select>" +
            '<div id="evalMsgDeadlineWrap"><label class="eval-msg-label">Deadline</label><input type="text" id="evalMsgDeadline" class="eval-msg-input" placeholder="e.g. August 15, 2026"></div>' +
            '<label class="eval-msg-label">Subject</label><input type="text" id="evalMsgSubject" class="eval-msg-input" maxlength="200">' +
            '<label class="eval-msg-label">Message</label><textarea id="evalMsgText" class="eval-msg-input" rows="7"></textarea>' +
            '<p class="eval-msg-hint">{{first_name}}, {{last_name}}, {{student_id}} and {{deadline}} are filled in when it\'s sent.</p>' +
            "</div></div>" +
            '<div class="custom-modal-footer"><span id="evalMsgStatus" class="eval-msg-status"></span>' +
            '<button type="button" class="btn-secondary" id="evalMsgCancel">Cancel</button>' +
            '<button type="button" class="btn-primary" id="evalMsgSend">Send</button></div>' +
            "</div>";
        document.body.appendChild(overlay);

        const close = () => overlay.classList.remove("open");
        overlay.querySelector("#evalMsgClose").addEventListener("click", close);
        overlay.querySelector("#evalMsgCancel").addEventListener("click", close);
        overlay.addEventListener("click", (e) => { if (e.target === overlay) close(); });
        overlay.querySelector("#evalMsgType").addEventListener("change", applyTemplate);
        overlay.querySelector("#evalMsgSend").addEventListener("click", send);
    }

    function applyTemplate() {
        const tpl = TEMPLATES[overlay.querySelector("#evalMsgType").value];
        if (!tpl) return;
        overlay.querySelector("#evalMsgSubject").value = tpl.subject;
        overlay.querySelector("#evalMsgText").value = tpl.message;
        overlay.querySelector("#evalMsgDeadlineWrap").hidden = !tpl.showDeadline;
    }

    async function loadHistory() {
        const box = overlay.querySelector("#evalMsgHistory");
        if (!current.email) {
            box.innerHTML = '<p class="eval-msg-empty">No email address on file for this ' + (current.kind === "renewal" ? "scholar" : "applicant") + ', so nothing can be sent yet.</p>';
            return;
        }
        box.textContent = "Loading\u2026";
        try {
            const res = await fetch(API + "/list_notifications.php?email=" + encodeURIComponent(current.email));
            const json = await res.json();
            const rows = (json.data || []);
            box.innerHTML = rows.length
                ? rows.map((r) =>
                    '<details class="eval-msg-item"><summary>' +
                    '<span class="eval-msg-item-subject">' + esc(r.subject) + "</span>" +
                    '<span class="eval-msg-item-meta">' + esc(TYPE_LABELS[r.type] || r.type) + " \u00b7 " + esc(fmtDate(r.sentAt || r.createdAt)) + "</span>" +
                    '<span class="eval-msg-badge ' + (String(r.status).toLowerCase() === "sent" ? "ok" : "bad") + '">' + esc(r.status) + "</span>" +
                    "</summary>" +
                    '<div class="eval-msg-item-body">' + esc(r.message).replace(/\n/g, "<br>") +
                    (r.errorMessage ? '<div class="eval-msg-error">' + esc(r.errorMessage) + "</div>" : "") + "</div></details>"
                ).join("")
                : '<p class="eval-msg-empty">No messages sent to this ' + (current.kind === "renewal" ? "scholar" : "applicant") + ' yet.</p>';
        } catch (e) {
            box.innerHTML = '<p class="eval-msg-empty">Couldn\'t load the sent messages.</p>';
        }
    }

    async function send() {
        const status = overlay.querySelector("#evalMsgStatus");
        const btn = overlay.querySelector("#evalMsgSend");
        const subject = overlay.querySelector("#evalMsgSubject").value.trim();
        const message = overlay.querySelector("#evalMsgText").value.trim();
        if (!current.email) { status.textContent = "No email address on file."; return; }
        if (!subject || !message) { status.textContent = "Subject and message are required."; return; }

        const fd = new FormData();
        fd.append("type", overlay.querySelector("#evalMsgType").value);
        fd.append("recipientMode", "individual");
        fd.append("individualKind", current.kind || "applicant");   // "renewal" from Renewal & Retention
        fd.append("applicantId", String(current.id));
        fd.append("deadline", overlay.querySelector("#evalMsgDeadlineWrap").hidden ? "" : overlay.querySelector("#evalMsgDeadline").value.trim());
        fd.append("subject", subject);
        fd.append("message", message);

        btn.disabled = true;
        btn.textContent = "Sending\u2026";
        status.textContent = "";
        try {
            const res = await fetch(API + "/send_notification.php", { method: "POST", body: fd });
            const json = await res.json();
            status.textContent = json.message || (json.success ? "Sent." : "Couldn't send.");
            status.className = "eval-msg-status " + (json.success ? "ok" : "bad");
            loadHistory();
        } catch (e) {
            status.textContent = "Couldn't reach the server.";
            status.className = "eval-msg-status bad";
        } finally {
            btn.disabled = false;
            btn.textContent = "Send";
        }
    }

    // a: an Evaluation applicant (id, fullName/name, studentId, email, documents), or a Renewal &
    // Retention entry with kind: "renewal" (its id is the renewal entry's) and an optional defaultType.
    window.openApplicantMessage = function (a) {
        if (!overlay) build();
        current = a;
        overlay.querySelector("#evalMsgTitle").textContent = "Message " + (a.fullName || a.name || (a.kind === "renewal" ? "scholar" : "applicant"));
        overlay.querySelector("#evalMsgTo").textContent = a.email ? "To: " + a.email + " \u00b7 " + a.studentId : "No email address on file \u00b7 " + a.studentId;
        // A sensible default: missing documents -> that reminder; otherwise a general update.
        const missingDocs = (a.documents || []).some((d) => d.required && !d.submitted);
        overlay.querySelector("#evalMsgType").value = a.defaultType || (missingDocs ? "missing_requirements" : "approval_status");
        applyTemplate();
        overlay.querySelector("#evalMsgDeadline").value = "";
        overlay.querySelector("#evalMsgStatus").textContent = "";
        overlay.querySelector("#evalMsgSend").disabled = !a.email;
        overlay.classList.add("open");
        loadHistory();
    };
})();
