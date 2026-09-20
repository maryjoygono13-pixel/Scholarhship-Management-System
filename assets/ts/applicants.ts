interface Step {
  key: "personal" | "academic" | "scholarship" | "documents";
  label: string;
}

const STEPS: Step[] = [
  { key: "personal", label: "Personal" },
  { key: "academic", label: "Academic" },
  { key: "scholarship", label: "Scholarship" },
  { key: "documents", label: "Documents" },
];

function getEl<T extends HTMLElement = HTMLElement>(id: string): T | null {
  return document.getElementById(id) as T | null;
}

let currentIndex = 0;
let furthestIndex = 0;
const formData: Record<string, any> = {};
let loadedApplicants: any[] = [];
let editingApplicantId: number | null = null;
let deletingApplicantId: number | null = null;

const APPLICANTS_PAGE_SIZE = 10;
let applicantsCurrentPage = 1;

const checkIcon = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>`;

const ICONS: Record<string, string> = {
  personal: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>`,
  academic: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.5 2.5 3 6 3s6-1.5 6-3v-5"/></svg>`,
  scholarship: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.5 13.5 17 22l-5-3-5 3 1.5-8.5"/></svg>`,
  documents: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg>`,
};

function formatPhoneNumber(val: string): string {
  if (!val) return "";
  const isPlus = val.trim().startsWith("+");
  let digits = val.replace(/\D/g, "");
  
  if (isPlus || digits.startsWith("63")) {
    if (digits.startsWith("63")) digits = digits.slice(2);
    digits = digits.slice(0, 10);
    const p1 = digits.slice(0, 3);
    const p2 = digits.slice(3, 6);
    const p3 = digits.slice(6, 10);
    return `+63${p1 ? ' ' + p1 : ''}${p2 ? ' ' + p2 : ''}${p3 ? ' ' + p3 : ''}`;
  }
  
  digits = digits.slice(0, 11);
  const p1 = digits.slice(0, 4);
  const p2 = digits.slice(4, 7);
  const p3 = digits.slice(7, 11);
  return `${p1}${p2 ? ' ' + p2 : ''}${p3 ? ' ' + p3 : ''}`;
}

async function loadTableData(): Promise<void> {
  try {
    const filterStatus = getEl<HTMLSelectElement>("filterStatus");
    const statusVal = filterStatus ? filterStatus.value : 'all';
    loadedApplicants = await (window as any).apiListApplicants(statusVal === 'all' ? '' : statusVal);
    renderTable();
  } catch (e) {
    console.error("Error loading applicants:", e);
  }
}

function renderTable(): void {
  const tableBody = getEl("tableBody");
  const emptyState = getEl("emptyState");
  const searchInput = getEl<HTMLInputElement>("searchInput");
  const filterType = getEl<HTMLSelectElement>("filterType");
  const filterStatus = getEl<HTMLSelectElement>("filterStatus");

  if (!tableBody) return;

  const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
  const typeVal = filterType ? filterType.value : 'all';
  const statusVal = filterStatus ? filterStatus.value : 'all';

  const filtered = loadedApplicants.filter(app => {
    const nameMatch = (app.name || '').toLowerCase().includes(query) || (app.studentId || '').toLowerCase().includes(query);
    const typeMatch = typeVal === 'all' || (app.scholarshipType || '').toLowerCase() === typeVal.toLowerCase();
    const statusMatch = statusVal === 'all' || (app.status || '').toLowerCase() === statusVal.toLowerCase();
    return nameMatch && typeMatch && statusMatch;
  });

  tableBody.innerHTML = '';

  if (filtered.length === 0) {
    if (emptyState) {
      emptyState.style.display = 'block';
      emptyState.classList.add('show');
    }
    renderApplicantsPagination(0);
    return;
  }

  if (emptyState) {
    emptyState.style.display = 'none';
    emptyState.classList.remove('show');
  }

  const totalPages = Math.max(1, Math.ceil(filtered.length / APPLICANTS_PAGE_SIZE));
  if (applicantsCurrentPage > totalPages) applicantsCurrentPage = totalPages;
  if (applicantsCurrentPage < 1) applicantsCurrentPage = 1;
  const pageItems = filtered.slice((applicantsCurrentPage - 1) * APPLICANTS_PAGE_SIZE, applicantsCurrentPage * APPLICANTS_PAGE_SIZE);

  pageItems.forEach(app => {
    const tr = document.createElement('tr');
    const statusLower = (app.status || '').toLowerCase();
    const statusBadgeClass = statusLower === 'approved' ? 'badge-approved' : (statusLower === 'rejected' ? 'badge-rejected' : 'badge-pending');
    const formattedStatus = app.status ? app.status.charAt(0).toUpperCase() + app.status.slice(1) : 'Pending';

    tr.innerHTML = `
      <td><strong class="font-mono">${app.studentId || '-'}</strong></td>
      <td>${app.name}</td>
      <td>${app.scholarshipType}</td>
      <td><span class="status-badge ${statusBadgeClass}">${formattedStatus}</span></td>
      <td><span class="font-mono">${(app.createdAt || '').split(' ')[0] || '2026-08-10'}</span></td>
      <td class="actions-cell">
        <button type="button" class="btn-icon-action edit" title="Edit Applicant" onclick="editApplicant(event, ${app.id})">
          <i data-lucide="pencil"></i>
        </button>
        <button type="button" class="btn-icon-action delete" title="Delete Applicant" onclick="confirmDeleteApplicant(event, ${app.id})">
          <i data-lucide="trash-2"></i>
        </button>
      </td>
    `;

    tr.addEventListener('click', (e: MouseEvent) => {
      if (e.target && (e.target as HTMLElement).closest && (e.target as HTMLElement).closest('.actions-cell, .btn-icon-action')) {
        return;
      }
      openViewModal(app);
    });
    tableBody.appendChild(tr);
  });

  renderApplicantsPagination(filtered.length);

  if (typeof (window as any).lucide !== 'undefined') {
    (window as any).lucide.createIcons();
  }
}

function renderApplicantsPagination(total: number): void {
  const wrap = getEl("applicantsPagination");
  if (!wrap) return;

  const totalPages = Math.max(1, Math.ceil(total / APPLICANTS_PAGE_SIZE));
  if (applicantsCurrentPage > totalPages) applicantsCurrentPage = totalPages;
  if (applicantsCurrentPage < 1) applicantsCurrentPage = 1;

  if (total === 0) {
    wrap.innerHTML = '';
    return;
  }

  const start = (applicantsCurrentPage - 1) * APPLICANTS_PAGE_SIZE + 1;
  const end = Math.min(applicantsCurrentPage * APPLICANTS_PAGE_SIZE, total);

  const buttons = `<button type="button" class="active" data-page="${applicantsCurrentPage}" disabled>${applicantsCurrentPage}</button>`;

  wrap.innerHTML =
    `<span>Showing ${start}–${end} of ${total} entries</span>` +
    `<div class="page-btns">` +
    `<button type="button" data-page="${applicantsCurrentPage - 1}" ${applicantsCurrentPage <= 1 ? 'disabled' : ''}>Prev</button>` +
    buttons +
    `<button type="button" data-page="${applicantsCurrentPage + 1}" ${applicantsCurrentPage >= totalPages ? 'disabled' : ''}>Next</button>` +
    `</div>`;

  wrap.querySelectorAll<HTMLButtonElement>('button[data-page]').forEach((btn) => {
    btn.addEventListener('click', () => {
      const p = parseInt(btn.getAttribute('data-page') || '', 10);
      if (!isNaN(p) && p >= 1 && p <= totalPages) {
        applicantsCurrentPage = p;
        renderTable();
      }
    });
  });
}

function openViewModal(app: any): void {
  const viewOverlay = getEl("viewOverlay");
  const viewBody = getEl("viewBody");
  if (!viewOverlay || !viewBody) return;

  const statusLower = (app.status || '').toLowerCase();
  const statusClass = statusLower === 'approved' ? 'badge-approved' : (statusLower === 'rejected' ? 'badge-rejected' : 'badge-pending');
  const formattedStatus = app.status ? app.status.charAt(0).toUpperCase() + app.status.slice(1) : 'Pending';

  viewBody.innerHTML = `
    <div class="view-detail-grid">
      <div class="detail-item">
        <span class="detail-label">Student ID</span>
        <span class="detail-value mono font-mono">${app.studentId}</span>
      </div>
      <div class="detail-item">
        <span class="detail-label">Status</span>
        <span class="status-badge ${statusClass}">${formattedStatus}</span>
      </div>
      <div class="detail-item full-width">
        <span class="detail-label">Full Name</span>
        <span class="detail-value highlight">${app.name}</span>
      </div>
      <div class="detail-item">
        <span class="detail-label">Email Address</span>
        <span class="detail-value">${app.email}</span>
      </div>
      <div class="detail-item">
        <span class="detail-label">Phone Number</span>
        <span class="detail-value font-mono">${formatPhoneNumber(app.phone || 'N/A')}</span>
      </div>
      <div class="detail-item full-width">
        <span class="detail-label">School / Program</span>
        <span class="detail-value">${app.school || 'College of Maasin'} — ${app.program}</span>
      </div>
      <div class="detail-item">
        <span class="detail-label">Scholarship Type</span>
        <span class="detail-value">${app.scholarshipType}</span>
      </div>
      <div class="detail-item">
        <span class="detail-label">Current GPA / GWA</span>
        <span class="detail-value font-mono">${app.gpa || app.gwa || 'N/A'}</span>
      </div>
      <div class="detail-item full-width">
        <span class="detail-label">Home Address</span>
        <span class="detail-value">${app.address || 'N/A'}</span>
      </div>
      ${app.remarks ? `
      <div class="detail-item full-width">
        <span class="detail-label">Remarks</span>
        <span class="detail-value remarks">${app.remarks}</span>
      </div>` : ''}
    </div>
  `;

  viewOverlay.classList.add("open");
}

function closeViewModal(): void {
  const viewOverlay = getEl("viewOverlay");
  if (viewOverlay) viewOverlay.classList.remove("open");
}

(window as any).editApplicant = async function(event: MouseEvent, id: number): Promise<void> {
  event.stopPropagation();
  try {
    const app = await (window as any).apiGetApplicant(id);
    editingApplicantId = id;

    const fId = document.querySelector<HTMLInputElement>('[data-field="studentId"]');
    const fFirst = document.querySelector<HTMLInputElement>('[data-field="firstName"]');
    const fLast = document.querySelector<HTMLInputElement>('[data-field="lastName"]');
    const fEmail = document.querySelector<HTMLInputElement>('[data-field="email"]');
    const fPhone = document.querySelector<HTMLInputElement>('[data-field="phone"]');
    const fBirth = document.querySelector<HTMLInputElement>('[data-field="birthdate"]');
    const fAddr = document.querySelector<HTMLInputElement>('[data-field="address"]');
    const fSchool = document.querySelector<HTMLInputElement>('[data-field="school"]');
    const fProg = document.querySelector<HTMLInputElement>('[data-field="program"]');
    const fYear = document.querySelector<HTMLSelectElement>('[data-field="yearLevel"]');
    const fGpa = document.querySelector<HTMLInputElement>('[data-field="gpa"]');
    const fType = document.querySelector<HTMLSelectElement>('[data-field="scholarshipType"]');
    const fEssay = document.querySelector<HTMLTextAreaElement>('[data-field="essay"]');

    if (fId) fId.value = app.studentId || '';
    if (fFirst) fFirst.value = app.firstName || '';
    if (fLast) fLast.value = app.lastName || '';
    if (fEmail) fEmail.value = app.email || '';
    if (fPhone) fPhone.value = app.phone || '';
    if (fBirth) fBirth.value = app.birthdate || '';
    if (fAddr) fAddr.value = app.address || '';
    if (fSchool) fSchool.value = app.school || '';
    if (fProg) fProg.value = app.program || '';
    if (fYear) fYear.value = app.yearLevel || '';
    if (fGpa) fGpa.value = app.gpa || '';
    if (fType) {
      const wantedType = app.scholarshipType || '';
      if (wantedType && !Array.from(fType.options).some(o => o.value === wantedType)) {
        // Legacy value that predates the current Type/Sub-type taxonomy —
        // add it so editing doesn't silently blank the field.
        const legacyOpt = document.createElement("option");
        legacyOpt.value = wantedType;
        legacyOpt.textContent = wantedType + " (legacy)";
        fType.appendChild(legacyOpt);
      }
      fType.value = wantedType;
    }
    // Setting .value above doesn't fire "change", so the GWA hidden field
    // (normally synced on select) needs to be seeded from the applicant's
    // own already-saved requirement — otherwise saving without touching
    // this dropdown would silently reset it back to the 1.75 default.
    const gwaReqInput = getEl<HTMLInputElement>("applicantGwaReq");
    if (gwaReqInput) gwaReqInput.value = app.gwaReq != null ? String(app.gwaReq) : "";
    if (fEssay) fEssay.value = app.essay || '';

    openModal(true);
  } catch (e) {
    alert("Could not load applicant data for edit.");
  }
};

(window as any).confirmDeleteApplicant = function(event: MouseEvent | Event, id: number | string): void {
  if (event && typeof event.stopPropagation === "function") {
    event.stopPropagation();
  }
  closeViewModal();
  const targetId = Number(id);
  if (!targetId) return;
  deletingApplicantId = targetId;
  const item = loadedApplicants.find(a => Number(a.id) === targetId);
  const deleteTargetName = getEl("deleteTargetName");
  const deleteConfirmOverlay = getEl("deleteConfirmOverlay");
  if (deleteTargetName) deleteTargetName.textContent = item ? (item.name || `${item.firstName || ''} ${item.lastName || ''}`.trim()) : `Applicant #${targetId}`;
  if (deleteConfirmOverlay) deleteConfirmOverlay.classList.add("open");
};

function closeDeleteModal(): void {
  const deleteConfirmOverlay = getEl("deleteConfirmOverlay");
  if (deleteConfirmOverlay) deleteConfirmOverlay.classList.remove("open");
  deletingApplicantId = null;
}

function renderProgress(): void {
  const progressBar = getEl("progressBar");
  if (!progressBar) return;
  progressBar.innerHTML = "";

  STEPS.forEach((step, i) => {
    const wrap = document.createElement("div");
    wrap.className = "progress-step";
    
    const isCompleted = i < currentIndex;
    const isCurrent = i === currentIndex;
    const isReachable = i <= furthestIndex;

    const btn = document.createElement("button");
    btn.className = "step-btn";
    btn.type = "button";
    btn.disabled = !isReachable;
    btn.innerHTML = `
      <span class="step-circle ${isCompleted ? "completed" : isCurrent ? "current" : ""}">
        ${isCompleted ? checkIcon : ICONS[step.key]}
      </span>
      <span class="step-label ${isCompleted ? "completed" : isCurrent ? "current" : ""}">${step.label}</span>
    `;

    btn.addEventListener("click", () => {
      if (isReachable) {
        currentIndex = i;
        render();
      }
    });

    wrap.appendChild(btn);

    if (i < STEPS.length - 1) {
      const line = document.createElement("div");
      line.className = "step-line" + (i < currentIndex ? " completed" : "");
      wrap.appendChild(line);
    }

    progressBar.appendChild(wrap);
  });
}

function render(): void {
  const stepCounter = getEl("stepCounter");
  const modalFooter = getEl("modalFooter");
  const backBtn = getEl("backBtn");
  const nextBtn = getEl<HTMLButtonElement>("nextBtn");

  document.querySelectorAll(".step-panel").forEach(panel => {
    panel.classList.remove("active");
  });

  const activePanel = document.querySelector(`.step-panel[data-step="${currentIndex}"]`);
  if (activePanel) activePanel.classList.add("active");

  renderProgress();

  if (stepCounter) stepCounter.textContent = `Step ${currentIndex + 1} of ${STEPS.length}`;

  if (currentIndex === 0) {
    if (backBtn) backBtn.style.display = "none";
    if (modalFooter) modalFooter.style.justifyContent = "flex-end";
  } else {
    if (backBtn) backBtn.style.display = "inline-flex";
    if (modalFooter) modalFooter.style.justifyContent = "space-between";
  }

  if (nextBtn) {
    nextBtn.innerHTML = currentIndex === STEPS.length - 1
      ? (editingApplicantId ? "Update Application" : "Submit Application")
      : `Next <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>`;
  }

  if (modalFooter) modalFooter.style.display = "flex";
}

function showSuccess(): void {
  const successMsg = getEl("successMsg");
  const modalFooter = getEl("modalFooter");

  document.querySelectorAll(".step-panel").forEach(panel => panel.classList.remove("active"));
  const successPanel = document.querySelector('.step-panel[data-step="success"]');
  if (successPanel) successPanel.classList.add("active");

  if (modalFooter) modalFooter.style.display = "none";

  const name = `${formData.firstName || "The applicant"} ${formData.lastName || ""}`.trim();
  if (successMsg) successMsg.textContent = `${name} has been successfully ${editingApplicantId ? "updated" : "added"} in the system.`;
}

function openModal(isEdit: boolean = false): void {
  const overlay = getEl("overlay");
  if (!isEdit) {
    editingApplicantId = null;
    currentIndex = 0;
    furthestIndex = 0;
    document.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>("[data-field]").forEach(el => {
      el.value = "";
      el.classList.remove("error");
    });
    document.querySelectorAll(".hint").forEach(el => {
      el.textContent = "PDF, JPG, or PNG · max 5MB";
    });
    document.querySelectorAll(".upload-action").forEach(el => {
      el.textContent = "Upload";
    });
    Object.keys(formData).forEach(k => delete formData[k]);
  }
  render();
  if (overlay) overlay.classList.add("open");
}

function closeModal(): void {
  const overlay = getEl("overlay");
  if (overlay) overlay.classList.remove("open");
  currentIndex = 0;
  furthestIndex = 0;
  editingApplicantId = null;
  document.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>("[data-field]").forEach(el => {
    el.value = "";
    el.classList.remove("error");
  });
  document.querySelectorAll(".hint").forEach(el => {
    el.textContent = "PDF, JPG, or PNG · max 5MB";
  });
  document.querySelectorAll(".upload-action").forEach(el => {
    el.textContent = "Upload";
  });
  Object.keys(formData).forEach(k => delete formData[k]);
  render();
  loadTableData();
}

/*
 * The Scholarship type dropdown is fed by whatever Types/Sub-types are
 * defined on the Scholarships page — never hardcoded here. Picking a
 * sub-type also carries its required GWA into a hidden field, so a new
 * applicant's evaluation threshold matches exactly what that scholarship
 * requires instead of a flat default.
 */
async function loadScholarshipTypeOptions(): Promise<void> {
  const select = document.getElementById("applicantScholarshipType") as HTMLSelectElement | null;
  const gwaReqInput = document.getElementById("applicantGwaReq") as HTMLInputElement | null;
  if (!select) return;

  try {
    const res = await fetch("api/scholarship_types.php");
    const json = await res.json();
    if (!json.success) return;

    const currentValue = select.value;
    select.innerHTML = '<option value="">Select type</option>';

    (json.data || []).forEach((type: any) => {
      if (Array.isArray(type.subtypes) && type.subtypes.length > 0) {
        const group = document.createElement("optgroup");
        group.label = type.name;
        type.subtypes.forEach((sub: any) => {
          const opt = document.createElement("option");
          opt.value = sub.name;
          opt.textContent = sub.name;
          opt.dataset.gwa = String(sub.gwaRequirement);
          group.appendChild(opt);
        });
        select.appendChild(group);
      } else {
        // No sub-types defined yet for this type — still let staff pick
        // the broad category itself, with a sensible default GWA.
        const opt = document.createElement("option");
        opt.value = type.name;
        opt.textContent = type.name;
        opt.dataset.gwa = "1.75";
        select.appendChild(opt);
      }
    });

    if (currentValue) select.value = currentValue;
  } catch (e) {
    console.error("Failed to load scholarship types:", e);
  }

  if (!select.dataset.gwaBound) {
    select.dataset.gwaBound = "1";
    select.addEventListener("change", () => {
      const selectedOption = select.options[select.selectedIndex];
      const gwa = selectedOption ? selectedOption.dataset.gwa : "";
      if (gwaReqInput) gwaReqInput.value = gwa || "";
    });
  }
}

function initApplicantsPage(): void {
  loadScholarshipTypeOptions();

  document.addEventListener("click", (e: MouseEvent) => {
    const target = e.target as HTMLElement | null;
    if (target && target.closest("#openBtn, #emptyStateAddBtn")) {
      e.preventDefault();
      openModal(false);
    }
  });

  document.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>("input[data-field], select[data-field], textarea[data-field]").forEach(el => {
    el.addEventListener("change", () => {
      const key = el.dataset.field;
      if (!key) return;
      if (el instanceof HTMLInputElement && el.type === "file") {
        const file = el.files ? el.files[0] : null;
        formData[key] = file || null;
        const hint = document.querySelector(`.hint[data-hint="${key}"]`);
        const action = document.querySelector(`.upload-action[data-action="${key}"]`);
        if (file) {
          if (hint) hint.textContent = file.name;
          if (action) action.textContent = "Replace";
        } else {
          if (hint) hint.textContent = "PDF, JPG, or PNG · max 5MB";
          if (action) action.textContent = "Upload";
        }
      } else {
        formData[key] = el.value;
      }
    });
  });

  document.querySelectorAll(".upload-action").forEach(actionEl => {
    actionEl.addEventListener("click", () => {
      const action = (actionEl as HTMLElement).dataset.action;
      if (action) {
        const fileInput = document.querySelector<HTMLInputElement>(`input[data-field="${action}"]`);
        if (fileInput) fileInput.click();
      }
    });
  });

  const closeBtn = getEl("closeBtn");
  if (closeBtn) closeBtn.addEventListener("click", closeModal);

  const doneBtn = getEl("doneBtn");
  if (doneBtn) doneBtn.addEventListener("click", closeModal);

  const backBtn = getEl("backBtn");
  if (backBtn) {
    backBtn.addEventListener("click", () => {
      if (currentIndex > 0) {
        currentIndex--;
        render();
      }
    });
  }

  const nextBtn = getEl("nextBtn");
  if (nextBtn) {
    nextBtn.addEventListener("click", async (e) => {
      e.preventDefault();
      document.querySelectorAll(".error").forEach(el => el.classList.remove("error"));

      const currentPanel = document.querySelector(`.step-panel[data-step="${currentIndex}"]`);
      if (!currentPanel) return;
      const requiredFields = currentPanel.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>("[required]");

      let hasError = false;
      requiredFields.forEach(field => {
        if (field instanceof HTMLInputElement && field.type === "file") {
          if (!editingApplicantId && (!field.files || field.files.length === 0)) {
            field.classList.add("error");
            if (!hasError) field.focus();
            hasError = true;
          }
        } else {
          if (field.value.trim() === "") {
            field.classList.add("error");
            if (!hasError) field.focus();
            hasError = true;
          }
        }
      });

      if (hasError) return;

      if (currentIndex === STEPS.length - 1) {
        if ((window as any).isSavingApplicant) return;
        const form = document.getElementById("applicantForm") as HTMLFormElement | null;
        if (!form) return;
        (window as any).isSavingApplicant = true;
        if (nextBtn) {
          nextBtn.disabled = true;
          nextBtn.textContent = "Saving...";
        }
        try {
          await (window as any).apiSaveApplicant(form, editingApplicantId);
          await loadTableData();
          if (typeof (window as any).updateNavCounts === "function") {
            (window as any).updateNavCounts();
          }
          showSuccess();
        } catch (err: any) {
          alert(err.message || "Failed to submit application.");
        } finally {
          (window as any).isSavingApplicant = false;
          if (nextBtn) {
            nextBtn.disabled = false;
          }
        }
        return;
      }

      currentIndex++;
      furthestIndex = Math.max(furthestIndex, currentIndex);
      render();
    });
  }

  const overlay = getEl("overlay");
  if (overlay) {
    overlay.addEventListener("click", (e) => {
      if (e.target === overlay) closeModal();
    });
  }

  const deleteConfirmBtn = getEl("deleteConfirmBtn");
  if (deleteConfirmBtn) {
    deleteConfirmBtn.addEventListener("click", async () => {
      if (!deletingApplicantId) return;
  const deleteConfirmOverlay = getEl("deleteConfirmOverlay");
  if (deleteConfirmOverlay) {
    deleteConfirmOverlay.addEventListener("click", (e) => {
      if (e.target === deleteConfirmOverlay) closeDeleteModal();
    });
  }

  const deleteConfirmBtn = getEl("deleteConfirmBtn");
  if (deleteConfirmBtn) {
    deleteConfirmBtn.addEventListener("click", async () => {
      if (!deletingApplicantId) return;
      try {
        const apiPath = (typeof window !== "undefined" && (window as any).API_BASE) ? (window as any).API_BASE : "api";
        const res = await fetch(`${apiPath}/delete_applicant.php?id=${deletingApplicantId}`, { method: "POST" });
        const json = await res.json();
        if (json.success) {
          closeDeleteModal();
          await loadTableData();
          if (typeof (window as any).updateNavCounts === "function") {
            (window as any).updateNavCounts();
          }
        } else {
          alert(json.message || "Failed to delete applicant.");
        }
      } catch (e) {
        alert("Server error when deleting applicant.");
      }
    });
  }

  const deleteCloseBtn = getEl("deleteCloseBtn");
  if (deleteCloseBtn) deleteCloseBtn.addEventListener("click", closeDeleteModal);

  const deleteCancelBtn = getEl("deleteCancelBtn");
  if (deleteCancelBtn) deleteCancelBtn.addEventListener("click", closeDeleteModal);

  const viewCloseBtn = getEl("viewCloseBtn");
  if (viewCloseBtn) viewCloseBtn.addEventListener("click", closeViewModal);

  const viewCloseBtn2 = getEl("viewCloseBtn2");
  if (viewCloseBtn2) viewCloseBtn2.addEventListener("click", closeViewModal);

  const searchInput = getEl<HTMLInputElement>("searchInput");
  if (searchInput) searchInput.addEventListener("input", () => { applicantsCurrentPage = 1; renderTable(); });

  const filterType = getEl<HTMLSelectElement>("filterType");
  if (filterType) filterType.addEventListener("change", () => { applicantsCurrentPage = 1; renderTable(); });

  const filterStatus = getEl<HTMLSelectElement>("filterStatus");
  if (filterStatus) filterStatus.addEventListener("change", () => { applicantsCurrentPage = 1; loadTableData(); });

  const studentId = document.querySelector<HTMLInputElement>('[data-field="studentId"]');
  const phoneNumber = document.querySelector<HTMLInputElement>('[data-field="phone"]');

  if (studentId) {
    studentId.addEventListener("input", function (this: HTMLInputElement) {
      this.value = this.value.replace(/\D/g, "");
    });
  }

  if (phoneNumber) {
    phoneNumber.addEventListener("input", function (this: HTMLInputElement) {
      this.value = formatPhoneNumber(this.value);
    });
  }

  render();
  loadTableData();
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initApplicantsPage);
} else {
  initApplicantsPage();
}
