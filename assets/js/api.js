/* ============================================================
   Frontend API client — talks to the PHP backend under /api.
   If your project folder isn't served at the site root, change
   API_BASE below (e.g. "/scholarship-portal/api").
   ============================================================ */

const API_BASE = "api";

async function apiListApplicants(status) {
  const url = status ? `${API_BASE}/list_applicants.php?status=${encodeURIComponent(status)}` : `${API_BASE}/list_applicants.php`;
  const res = await fetch(url);
  const json = await res.json();
  if (!json.success) throw new Error(json.message || "Failed to load applicants.");
  return json.data;
}

async function apiGetApplicant(id) {
  const res = await fetch(`${API_BASE}/get_applicant.php?id=${id}`);
  const json = await res.json();
  if (!json.success) throw new Error(json.message || "Failed to load applicant.");
  return json.data;
}

async function apiSaveApplicant(formEl, applicantId) {
  const formData = new FormData();

  // Collect every text/select/textarea field
  formEl.querySelectorAll("[data-field]").forEach(el => {
    if (el.type === "file") return;
    formData.append(el.dataset.field, el.value);
  });

  // Attach files only if the user actually chose one
  formEl.querySelectorAll("input[type=file][data-field]").forEach(el => {
    if (el.files && el.files[0]) {
      formData.append(el.dataset.field, el.files[0]);
    }
  });

  if (applicantId) formData.append("id", applicantId);

  const res = await fetch(`${API_BASE}/save_applicant.php`, {
    method: "POST",
    body: formData,
  });
  const json = await res.json();
  if (!json.success) throw new Error(json.message || "Failed to save applicant.");
  return json;
}

async function apiMoveToEvaluation(id) {
  const formData = new FormData();
  formData.append("id", id);
  const res = await fetch(`${API_BASE}/move_to_evaluation.php`, { method: "POST", body: formData });
  const json = await res.json();
  if (!json.success) throw new Error(json.message || "Failed to move applicant to evaluation.");
  return json;
}

async function apiDecideApplicant(id, decision) {
  const formData = new FormData();
  formData.append("id", id);
  formData.append("decision", decision);
  const res = await fetch(`${API_BASE}/decide.php`, { method: "POST", body: formData });
  const json = await res.json();
  if (!json.success) throw new Error(json.message || "Failed to save decision.");
  return json;
}

async function apiListNotifications(type) {
  const url = type ? `${API_BASE}/list_notifications.php?type=${encodeURIComponent(type)}` : `${API_BASE}/list_notifications.php`;
  const res = await fetch(url);
  const json = await res.json();
  if (!json.success) throw new Error(json.message || "Failed to load notifications.");
  return json; // { data, summary }
}

async function apiGetRecipients(segment) {
  const res = await fetch(`${API_BASE}/get_recipients.php?segment=${encodeURIComponent(segment)}`);
  const json = await res.json();
  if (!json.success) throw new Error(json.message || "Failed to load recipients.");
  return json; // { data, count }
}

async function apiSendNotification(payload) {
  const formData = new FormData();
  Object.entries(payload).forEach(([key, value]) => formData.append(key, value));
  const res = await fetch(`${API_BASE}/send_notification.php`, { method: "POST", body: formData });
  const json = await res.json();
  if (!json.success) throw new Error(json.message || "Failed to send notification.");
  return json; // { sent, failed, total, message }
}

/* Updates the small count badge next to nav links, if present on the page */
async function updateNavCounts() {
  try {
    const [pending, evaluation, decided] = await Promise.all([
      apiListApplicants("pending"),
      apiListApplicants("evaluation"),
      apiListApplicants("approved,rejected"),
    ]);
    const appEl = document.getElementById("navAppCount");
    const evalEl = document.getElementById("navEvalCount");
    const recEl = document.getElementById("navRecordsCount");
    if (appEl) appEl.textContent = pending.length;
    if (evalEl) evalEl.textContent = evaluation.length;
    if (recEl) recEl.textContent = decided.length;
  } catch (e) {
    console.error("Failed to update nav counts:", e);
  }
}