document.addEventListener("DOMContentLoaded", () => {
  const tableBody = document.getElementById("tableBody");
  const searchInput = document.querySelector<HTMLInputElement>(".search-wrap input");
  const filterType = document.getElementById("filterType") as HTMLSelectElement | null;
  const filterStatus = document.getElementById("filterStatus") as HTMLSelectElement | null;
  const addBtn = document.getElementById("addScholarshipBtn");

  const schFormOverlay = document.getElementById("schFormOverlay");
  const schFormTitle = document.getElementById("schFormTitle");
  const schFormCloseBtn = document.getElementById("schFormCloseBtn");
  const schFormCancelBtn = document.getElementById("schFormCancelBtn");
  const schForm = document.getElementById("schForm") as HTMLFormElement | null;

  const schId = document.getElementById("schId") as HTMLInputElement | null;
  const schName = document.getElementById("schName") as HTMLInputElement | null;
  const schCode = document.getElementById("schCode") as HTMLInputElement | null;
  const schType = document.getElementById("schType") as HTMLInputElement | null;
  const schSubtype = document.getElementById("schSubtype") as HTMLInputElement | null;
  const schGwa = document.getElementById("schGwa") as HTMLInputElement | null;
  const schSlots = document.getElementById("schSlots") as HTMLInputElement | null;
  const schCoverage = document.getElementById("schCoverage") as HTMLTextAreaElement | null;

  const schViewOverlay = document.getElementById("schViewOverlay");
  const schViewCloseBtn = document.getElementById("schViewCloseBtn");
  const schViewCloseBtn2 = document.getElementById("schViewCloseBtn2");
  const schViewBody = document.getElementById("schViewBody");

  const schDeleteOverlay = document.getElementById("schDeleteOverlay");
  const schDeleteCloseBtn = document.getElementById("schDeleteCloseBtn");
  const schDeleteCancelBtn = document.getElementById("schDeleteCancelBtn");
  const schDeleteConfirmBtn = document.getElementById("schDeleteConfirmBtn");
  const schDeleteTarget = document.getElementById("schDeleteTarget");

  let scholarships: any[] = [];
  let deletingSchId: number | null = null;

  interface ScholarshipSubtype { id: number; name: string; }
  interface ScholarshipType { id: number; name: string; subtypes: ScholarshipSubtype[]; }

  let scholarshipTypes: ScholarshipType[] = [];
  let selectedTypeId: number | null = null;

  async function loadScholarshipTypes(): Promise<void> {
    try {
      const res = await fetch("api/scholarship_types.php");
      const json = await res.json();
      if (json.success) scholarshipTypes = json.data;
    } catch (e) {
      console.error("Failed to load scholarship types:", e);
    }
  }

  function renderTypePicker(selectedName?: string): void {
    const picker = document.getElementById("schTypePicker");
    const addWrap = document.getElementById("schTypeAddWrap");
    if (!picker || !addWrap) return;
    picker.querySelectorAll(".pill").forEach((p) => p.remove());

    scholarshipTypes.forEach((t) => {
      const pill = document.createElement("button");
      pill.type = "button";
      pill.className = "pill" + (t.name === selectedName ? " active" : "");
      pill.textContent = t.name;
      pill.addEventListener("click", () => selectType(t.id, t.name));
      picker.insertBefore(pill, addWrap);
    });

    // A legacy type value that no longer matches anything in the taxonomy —
    // show it selected so editing doesn't silently blank the field.
    if (selectedName && !scholarshipTypes.some((t) => t.name === selectedName)) {
      const pill = document.createElement("button");
      pill.type = "button";
      pill.className = "pill active";
      pill.textContent = selectedName;
      picker.insertBefore(pill, addWrap);
    }

    if (typeof lucide !== "undefined") lucide.createIcons();
  }

  function selectType(id: number, name: string): void {
    selectedTypeId = id;
    if (schType) schType.value = name;
    if (schSubtype) schSubtype.value = "";
    renderTypePicker(name);
    renderSubtypePicker();
    updateIdentityPreview();
  }

  function renderSubtypePicker(selectedName?: string): void {
    const field = document.getElementById("schSubtypeField") as HTMLElement | null;
    const picker = document.getElementById("schSubtypePicker");
    const addWrap = document.getElementById("schSubtypeAddWrap");
    if (!field || !picker || !addWrap) return;

    const hasType = !!(schType && schType.value);
    if (!hasType) {
      field.hidden = true;
      return;
    }
    field.hidden = false;

    picker.querySelectorAll(".pill, .pill-picker-empty").forEach((p) => p.remove());

    const type = scholarshipTypes.find((t) => t.id === selectedTypeId);
    const subtypes = type ? type.subtypes : [];

    if (subtypes.length === 0 && !selectedName) {
      const empty = document.createElement("span");
      empty.className = "pill-picker-empty";
      empty.textContent = "No sub-types yet — add one";
      picker.insertBefore(empty, addWrap);
    }

    subtypes.forEach((s) => {
      const pill = document.createElement("button");
      pill.type = "button";
      pill.className = "pill" + (s.name === selectedName ? " active" : "");
      pill.textContent = s.name;
      pill.addEventListener("click", () => selectSubtype(s.name));
      picker.insertBefore(pill, addWrap);
    });

    if (selectedName && !subtypes.some((s) => s.name === selectedName)) {
      const pill = document.createElement("button");
      pill.type = "button";
      pill.className = "pill active";
      pill.textContent = selectedName;
      picker.insertBefore(pill, addWrap);
    }

    if (typeof lucide !== "undefined") lucide.createIcons();
  }

  function selectSubtype(name: string): void {
    if (schSubtype) schSubtype.value = name;
    renderSubtypePicker(name);
    updateIdentityPreview();
  }

  /*
   * The scholarship's Name and Code are no longer typed by hand — they're
   * derived from whichever Type/Sub-type is picked. When the source string
   * already looks like "ACRONYM (Full Description)" (the convention used
   * throughout this app), the acronym becomes the code; otherwise a code
   * is built from the initials of the significant words.
   */
  function computeIdentity(typeName: string, subtypeName: string): { name: string; code: string } {
    const source = (subtypeName || typeName || "").trim();
    if (!source) return { name: "", code: "" };

    const match = source.match(/^([^(]+)\(/);
    if (match) {
      return { name: source, code: match[1].trim() };
    }

    const skip = new Set(["of", "the", "and", "or", "for", "a", "an"]);
    const code = source
      .split(/[\s-]+/)
      .filter((w) => w && !skip.has(w.toLowerCase()))
      .map((w) => w[0].toUpperCase())
      .join("")
      .slice(0, 6);

    return { name: source, code: code || "GEN" };
  }

  function updateIdentityPreview(): void {
    const previewName = document.getElementById("schPreviewName");
    const previewCode = document.getElementById("schPreviewCode");
    const typeVal = schType ? schType.value : "";
    const subtypeVal = schSubtype ? schSubtype.value : "";

    const { name, code } = computeIdentity(typeVal, subtypeVal);
    if (schName) schName.value = name;
    if (schCode) schCode.value = code;

    if (previewName) previewName.textContent = name || "Select a type to continue";
    if (previewCode) previewCode.textContent = code;
  }

  // The name often already spells out its acronym, e.g. "CMSP (CHED Merit
  // Scholarship Program)" — only append "(code)" separately when the code
  // text isn't already present somewhere in the name.
  function formatNameWithCode(name: string, code: string): string {
    if (!name) return "";
    if (!code || name.toLowerCase().includes(code.toLowerCase())) return name;
    return `${name} (${code})`;
  }

  function setupPillAdd(addBtnId: string, inputId: string, onSubmit: (value: string) => Promise<void>): void {
    const btn = document.getElementById(addBtnId);
    const input = document.getElementById(inputId) as HTMLInputElement | null;
    if (!btn || !input) return;

    btn.addEventListener("click", () => {
      input.hidden = !input.hidden;
      if (!input.hidden) input.focus();
    });

    input.addEventListener("keydown", async (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        const value = input.value.trim();
        if (!value) return;
        await onSubmit(value);
        input.value = "";
        input.hidden = true;
      } else if (e.key === "Escape") {
        input.value = "";
        input.hidden = true;
      }
    });
  }

  async function loadScholarships(): Promise<void> {
    try {
      const res = await fetch("api/list_scholarships.php");
      const json = await res.json();
      if (json.success) {
        scholarships = json.data;
        populateFilterTypes();
        renderScholarships();
      }
    } catch (e) {
      console.error("Failed to load scholarships:", e);
    }
  }

  function populateFilterTypes(): void {
    if (!filterType) return;
    const types = Array.from(new Set(scholarships.map((s: any) => s.type))).filter(Boolean);
    filterType.innerHTML = '<option value="all">All Scholarship Types</option>';
    types.forEach(t => {
      const opt = document.createElement("option");
      opt.value = t;
      opt.textContent = t;
      filterType.appendChild(opt);
    });
  }

  function renderScholarships(): void {
    if (!tableBody) return;
    const query = searchInput ? searchInput.value.toLowerCase().trim() : "";
    const typeVal = filterType ? filterType.value.toLowerCase() : "all";
    const statusVal = filterStatus ? filterStatus.value.toLowerCase() : "all";

    const filtered = scholarships.filter((s: any) => {
      const matchQuery = s.name.toLowerCase().includes(query) || s.code.toLowerCase().includes(query);
      const matchType = typeVal === "all" || s.type.toLowerCase() === typeVal;
      const matchStatus = statusVal === "all" || statusVal === "all status" || s.status.toLowerCase() === statusVal;
      return matchQuery && matchType && matchStatus;
    });

    tableBody.innerHTML = "";
    if (filtered.length === 0) {
      tableBody.innerHTML = `<tr><td colspan="5" style="text-align:center; padding: 24px; color: #6b7280;">No scholarships found.</td></tr>`;
      return;
    }

    filtered.forEach((s: any) => {
      const tr = document.createElement("tr");
      const badgeClass = s.status === "active" ? "badge-active" : "badge-inactive";
      tr.innerHTML = `
        <td>
          <strong>${formatNameWithCode(s.name, s.code)}</strong>
          <div style="font-size:12px; color:#6b7280;">${s.coverage || s.description || 'Standard Benefit Coverage'}</div>
        </td>
        <td>${s.type}${s.subtype ? '<div style="font-size:12px; color:#6b7280;">' + s.subtype + '</div>' : ''}</td>
        <td style="white-space:nowrap !important; min-width:120px;"><span class="font-mono" style="white-space:nowrap !important; display:inline-block;">${s.slotsAvailable}&nbsp;/&nbsp;${s.slots}</span></td>
        <td><span class="status-badge ${badgeClass}">${s.status}</span></td>
        <td class="actions-cell">
          <button type="button" class="btn-icon-action edit" title="Edit Program" onclick="editScholarship(event, ${s.id})">
            <i data-lucide="pencil"></i>
          </button>
          <button type="button" class="btn-icon-action delete" title="Delete Program" onclick="confirmDeleteScholarship(event, ${s.id})">
            <i data-lucide="trash-2"></i>
          </button>
        </td>
      `;

      tr.addEventListener("click", () => openViewModal(s));
      tableBody.appendChild(tr);
    });

    if (typeof lucide !== "undefined") lucide.createIcons();
  }

  function openViewModal(s: any): void {
    if (!schViewOverlay || !schViewBody) return;
    const statusClass = s.status === 'active' ? 'badge-active' : 'badge-inactive';
    schViewBody.innerHTML = `
      <div class="view-detail-grid">
        <div class="detail-item full-width">
          <span class="detail-label">Program Name</span>
          <span class="detail-value highlight">${formatNameWithCode(s.name, s.code)}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Category / Type</span>
          <span class="detail-value">${s.type}</span>
        </div>
        ${s.subtype ? '<div class="detail-item"><span class="detail-label">Sub-type</span><span class="detail-value">' + s.subtype + '</span></div>' : ''}
        <div class="detail-item">
          <span class="detail-label">Status</span>
          <span class="status-badge ${statusClass}">${s.status}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">GWA Requirement</span>
          <span class="detail-value mono font-mono"><= ${s.gwaRequirement || '1.75'}</span>
        </div>
        <div class="detail-item">
          <span class="detail-label">Total Slots Capacity</span>
          <span class="detail-value font-mono">${s.slots} (${s.slotsAvailable} available)</span>
        </div>
        <div class="detail-item full-width">
          <span class="detail-label">Benefit Coverage & Overview</span>
          <span class="detail-value">${s.coverage || s.description || 'Full Tuition Coverage'}</span>
        </div>
      </div>
    `;
    schViewOverlay.classList.add("open");
  }

  function closeViewModal(): void {
    if (schViewOverlay) schViewOverlay.classList.remove("open");
  }

  function openFormModal(editItem: any = null): void {
    if (!schFormOverlay) return;
    if (editItem) {
      if (schFormTitle) schFormTitle.textContent = "Edit Scholarship Program";
      if (schId) schId.value = String(editItem.id);
      if (schGwa) schGwa.value = String(editItem.gwaRequirement);
      if (schSlots) schSlots.value = String(editItem.slots);
      if (schCoverage) schCoverage.value = editItem.coverage || '';

      const matchedType = scholarshipTypes.find((t) => t.name === editItem.type);
      selectedTypeId = matchedType ? matchedType.id : null;
      if (schType) schType.value = editItem.type || '';
      if (schSubtype) schSubtype.value = editItem.subtype || '';
      renderTypePicker(editItem.type);
      renderSubtypePicker(editItem.subtype || undefined);
      updateIdentityPreview();
    } else {
      if (schFormTitle) schFormTitle.textContent = "Add Scholarship Program";
      if (schForm) schForm.reset();
      if (schId) schId.value = "";
      selectedTypeId = null;
      if (schType) schType.value = "";
      if (schSubtype) schSubtype.value = "";
      renderTypePicker();
      renderSubtypePicker();
      updateIdentityPreview();
    }
    schFormOverlay.classList.add("open");
    if (typeof lucide !== "undefined") lucide.createIcons();
  }

  function closeFormModal(): void {
    if (schFormOverlay) schFormOverlay.classList.remove("open");
    if (schForm) schForm.reset();
  }

  window.editScholarship = function(event: MouseEvent, id: number): void {
    event.stopPropagation();
    const item = scholarships.find((s: any) => s.id === id);
    if (item) openFormModal(item);
  };

  window.confirmDeleteScholarship = function(event: MouseEvent, id: number): void {
    event.stopPropagation();
    const item = scholarships.find((s: any) => s.id === id);
    if (!item) return;
    deletingSchId = id;
    if (schDeleteTarget) schDeleteTarget.textContent = item.name;
    if (schDeleteOverlay) schDeleteOverlay.classList.add("open");
  };

  function closeDeleteModal(): void {
    if (schDeleteOverlay) schDeleteOverlay.classList.remove("open");
    deletingSchId = null;
  }

  setupPillAdd("schTypeAddBtn", "schTypeAddInput", async (value) => {
    try {
      const res = await fetch("api/scholarship_types.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "add_type", name: value }),
      });
      const json = await res.json();
      if (json.success) {
        if (!scholarshipTypes.some((t) => t.id === json.id)) {
          scholarshipTypes.push({ id: json.id, name: json.name, subtypes: [] });
        }
        selectType(json.id, json.name);
      } else {
        alert(json.message || "Failed to add type.");
      }
    } catch (e) {
      alert("Server error.");
    }
  });

  setupPillAdd("schSubtypeAddBtn", "schSubtypeAddInput", async (value) => {
    if (!selectedTypeId) {
      alert("Select a Type first.");
      return;
    }
    try {
      const res = await fetch("api/scholarship_types.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "add_subtype", type_id: selectedTypeId, name: value }),
      });
      const json = await res.json();
      if (json.success) {
        const type = scholarshipTypes.find((t) => t.id === selectedTypeId);
        if (type && !type.subtypes.some((s) => s.id === json.id)) {
          type.subtypes.push({ id: json.id, name: json.name });
        }
        selectSubtype(json.name);
      } else {
        alert(json.message || "Failed to add sub-type.");
      }
    } catch (e) {
      alert("Server error.");
    }
  });

  if (schForm) {
    schForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (!schType || !schType.value) {
        alert("Please select a scholarship Type.");
        return;
      }
      const formData = new FormData(schForm);
      try {
        const res = await fetch("api/list_scholarships.php", { method: "POST", body: formData });
        const json = await res.json();
        if (json.success) {
          closeFormModal();
          loadScholarships();
        } else {
          alert(json.message || "Failed to save scholarship.");
        }
      } catch (err) {
        alert("Server error.");
      }
    });
  }

  if (schDeleteConfirmBtn) {
    schDeleteConfirmBtn.addEventListener("click", async () => {
      if (!deletingSchId) return;
      try {
        const res = await fetch(`api/delete_scholarship.php?id=${deletingSchId}`, { method: "POST" });
        const json = await res.json();
        if (json.success) {
          closeDeleteModal();
          loadScholarships();
        } else {
          alert(json.message || "Failed to delete.");
        }
      } catch (e) {
        alert("Server error.");
      }
    });
  }

  if (addBtn) addBtn.addEventListener("click", () => openFormModal());
  if (schFormCloseBtn) schFormCloseBtn.addEventListener("click", closeFormModal);
  if (schFormCancelBtn) schFormCancelBtn.addEventListener("click", closeFormModal);

  if (schViewCloseBtn) schViewCloseBtn.addEventListener("click", closeViewModal);
  if (schViewCloseBtn2) schViewCloseBtn2.addEventListener("click", closeViewModal);

  if (schDeleteCloseBtn) schDeleteCloseBtn.addEventListener("click", closeDeleteModal);
  if (schDeleteCancelBtn) schDeleteCancelBtn.addEventListener("click", closeDeleteModal);

  if (searchInput) searchInput.addEventListener("input", renderScholarships);
  if (filterType) filterType.addEventListener("change", renderScholarships);
  if (filterStatus) filterStatus.addEventListener("change", renderScholarships);

  loadScholarshipTypes().then(() => renderTypePicker());
  loadScholarships();
});
