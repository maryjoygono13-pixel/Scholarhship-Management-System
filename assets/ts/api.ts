/* ============================================================
   Frontend API client — talks to the PHP backend under /api.
   Converted to strict TypeScript script.
   ============================================================ */

const API_BASE = "api";

async function apiListApplicants(status?: string): Promise<ApplicantData[]> {
  const url = status ? `${API_BASE}/list_applicants.php?status=${encodeURIComponent(status)}` : `${API_BASE}/list_applicants.php`;
  const res = await fetch(url);
  const json: ApiResponse<ApplicantData[]> = await res.json();
  if (!json.success || !json.data) throw new Error(json.message || "Failed to load applicants.");
  return json.data;
}

async function apiGetApplicant(id: number | string): Promise<ApplicantData> {
  const res = await fetch(`${API_BASE}/get_applicant.php?id=${id}`);
  const json: ApiResponse<ApplicantData> = await res.json();
  if (!json.success || !json.data) throw new Error(json.message || "Failed to load applicant.");
  return json.data;
}

async function apiSaveApplicant(formEl: HTMLFormElement, applicantId?: number | string | null): Promise<ApiResponse> {
  const formData = new FormData();

  formEl.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>("[data-field]").forEach(el => {
    if (el instanceof HTMLInputElement && el.type === "file") return;
    const key = el.dataset.field;
    if (key) formData.append(key, el.value);
  });

  formEl.querySelectorAll<HTMLInputElement>("input[type=file][data-field]").forEach(el => {
    const key = el.dataset.field;
    if (key && el.files && el.files[0]) {
      formData.append(key, el.files[0]);
    }
  });

  if (applicantId) formData.append("id", String(applicantId));

  const res = await fetch(`${API_BASE}/save_applicant.php`, {
    method: "POST",
    body: formData,
  });
  const json: ApiResponse = await res.json();
  if (!json.success) throw new Error(json.message || "Failed to save applicant.");
  return json;
}

async function apiMoveToEvaluation(id: number | string): Promise<ApiResponse> {
  const formData = new FormData();
  formData.append("id", String(id));
  const res = await fetch(`${API_BASE}/move_to_evaluation.php`, { method: "POST", body: formData });
  const json: ApiResponse = await res.json();
  if (!json.success) throw new Error(json.message || "Failed to move applicant to evaluation.");
  return json;
}

async function apiDecideApplicant(id: number | string, decision: string): Promise<ApiResponse> {
  const formData = new FormData();
  formData.append("id", String(id));
  formData.append("decision", decision);
  const res = await fetch(`${API_BASE}/decide.php`, { method: "POST", body: formData });
  const json: ApiResponse = await res.json();
  if (!json.success) throw new Error(json.message || "Failed to save decision.");
  return json;
}

async function apiListNotifications(type?: string): Promise<ApiResponse> {
  const url = type ? `${API_BASE}/list_notifications.php?type=${encodeURIComponent(type)}` : `${API_BASE}/list_notifications.php`;
  const res = await fetch(url);
  const json: ApiResponse = await res.json();
  if (!json.success) throw new Error(json.message || "Failed to load notifications.");
  return json;
}

async function apiGetRecipients(segment: string): Promise<ApiResponse> {
  const res = await fetch(`${API_BASE}/get_recipients.php?segment=${encodeURIComponent(segment)}`);
  const json: ApiResponse = await res.json();
  if (!json.success) throw new Error(json.message || "Failed to load recipients.");
  return json;
}

async function apiSendNotification(payload: Record<string, string>): Promise<ApiResponse> {
  const formData = new FormData();
  Object.entries(payload).forEach(([key, value]) => formData.append(key, value));
  const res = await fetch(`${API_BASE}/send_notification.php`, { method: "POST", body: formData });
  const json: ApiResponse = await res.json();
  if (!json.success) throw new Error(json.message || "Failed to send notification.");
  return json;
}

async function updateNavCounts(): Promise<void> {
  try {
    const [pending, evaluation, decided, inboxRes] = await Promise.all([
      apiListApplicants("pending"),
      apiListApplicants("evaluation"),
      apiListApplicants("approved,rejected"),
      // The bell shows how many received messages are unread, not how many
      // notifications have ever been sent.
      fetch(`${API_BASE}/inbox.php?filter=unread`)
        .then((r) => r.json())
        .catch(() => ({ unread: 0 })),
    ]);
    const appEl = document.getElementById("navAppCount");
    const evalEl = document.getElementById("navEvalCount");
    const recEl = document.getElementById("navRecordsCount");
    const notifEl = document.getElementById("navNotifBadge") as HTMLElement | null;
    if (appEl) appEl.textContent = String(pending.length);
    if (evalEl) evalEl.textContent = String(evaluation.length);
    if (recEl) recEl.textContent = String(decided.length);
    if (notifEl) {
      const unread = (inboxRes && (inboxRes as any).success) ? Number((inboxRes as any).unread || 0) : 0;
      notifEl.textContent = String(unread);
      notifEl.hidden = unread === 0;
    }
  } catch (e) {
    console.error("Failed to update nav counts:", e);
  }
}

// Assign to window for global availability across pages
(window as any).apiListApplicants = apiListApplicants;
(window as any).apiGetApplicant = apiGetApplicant;
(window as any).apiSaveApplicant = apiSaveApplicant;
(window as any).apiMoveToEvaluation = apiMoveToEvaluation;
(window as any).apiDecideApplicant = apiDecideApplicant;
(window as any).apiListNotifications = apiListNotifications;
(window as any).apiGetRecipients = apiGetRecipients;
(window as any).apiSendNotification = apiSendNotification;
(window as any).updateNavCounts = updateNavCounts;
