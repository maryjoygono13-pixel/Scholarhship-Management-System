document.addEventListener("DOMContentLoaded", () => {
  const tableBody = document.getElementById("tableBody");
  const searchInput = document.querySelector<HTMLInputElement>(".search-wrap input");
  const filterType = document.getElementById("filterType") as HTMLSelectElement | null;
  const filterSubtype = document.getElementById("filterSubtype") as HTMLSelectElement | null;
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
  const schUnlimited = document.getElementById("schUnlimited") as HTMLInputElement | null;
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

  interface ScholarshipSubtype { id: number; name: string; gwaRequirement: number; }
  interface ScholarshipType { id: number; name: string; subtypes: ScholarshipSubtype[]; }

  let scholarshipTypes: ScholarshipType[] = [];
  let selectedTypeId: number | null = null;
  // Add mode lets several sub-types be picked at once (one program is
  // created per pick); edit mode keeps the single-pick behaviour.
  let pickedSubtypes: string[] = [];
  const isAddMode = (): boolean => !(schId && schId.value);

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
    pickedSubtypes = [];
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
      const isActive = isAddMode() ? pickedSubtypes.includes(s.name) : s.name === selectedName;
      pill.className = "pill" + (isActive ? " active" : "");
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

    const selectAllBtn = document.getElementById("schSubtypeSelectAll") as HTMLButtonElement | null;
    const hint = document.getElementById("schSubtypeHint");
    if (selectAllBtn) {
      selectAllBtn.hidden = !isAddMode() || subtypes.length < 2;
      selectAllBtn.textContent = subtypes.length && pickedSubtypes.length === subtypes.length ? "Clear all" : "Select all";
    }
    if (hint) hint.hidden = !isAddMode();

    if (typeof lucide !== "undefined") lucide.createIcons();
  }

  function applyPickedGwa(): void {
    const type = scholarshipTypes.find((t) => t.id === selectedTypeId);
    if (!schGwa) return;
    if (pickedSubtypes.length > 1) {
      schGwa.value = "";
      schGwa.placeholder = "Each sub-type uses its own GWA";
      schGwa.disabled = true;
      return;
    }
    schGwa.disabled = false;
    schGwa.placeholder = "e.g. 1.75";
    const only = pickedSubtypes[0] || (schSubtype ? schSubtype.value : "");
    const subtype = type ? type.subtypes.find((s) => s.name === only) : undefined;
    if (subtype) schGwa.value = String(subtype.gwaRequirement);
  }

  function selectSubtype(name: string): void {
    if (isAddMode()) {
      pickedSubtypes = pickedSubtypes.includes(name)
        ? pickedSubtypes.filter((n) => n !== name)
        : pickedSubtypes.concat(name);
      if (schSubtype) schSubtype.value = pickedSubtypes[0] || "";
    } else if (schSubtype) {
      schSubtype.value = name;
    }
    renderSubtypePicker(name);
    updateIdentityPreview();

    // Each sub-type carries its own required GWA — picking one applies it
    // straight to the scholarship's GWA Requirement field.
    applyPickedGwa();
  }

  function toggleAllSubtypes(): void {
    const type = scholarshipTypes.find((t) => t.id === selectedTypeId);
    const all = type ? type.subtypes.map((s) => s.name) : [];
    pickedSubtypes = pickedSubtypes.length === all.length ? [] : all;
    if (schSubtype) schSubtype.value = pickedSubtypes[0] || "";
    renderSubtypePicker();
    updateIdentityPreview();
    applyPickedGwa();
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

    if (isAddMode() && pickedSubtypes.length > 1) {
      if (previewName) previewName.textContent = pickedSubtypes.length + " scholarships: " + pickedSubtypes.join(", ");
      if (previewCode) previewCode.textContent = pickedSubtypes.length + " programs";
      return;
    }

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

    const previousValue = filterType.value;

    filterType.innerHTML = '<option value="all">Types</option>';

    // Every canonical type, plus any type still used by an older
    // program that isn't in the canonical list.
    const names = scholarshipTypes.map((t) => t.name);
    scholarships.forEach((s) => {
      if (s.type && !names.includes(s.type)) names.push(s.type);
    });

    names.forEach((n) => {
      const opt = document.createElement("option");
      opt.value = n;
      opt.textContent = n;
      filterType.appendChild(opt);
    });

    filterType.value = names.includes(previousValue) ? previousValue : "all";

    populateFilterSubtypes();
  }


  /* =========================================================
   POPULATE SUB-TYPE FILTER (scoped to the chosen type)
  ========================================================= */

  function populateFilterSubtypes(): void {

    if (!filterSubtype) return;

    const previousValue = filterSubtype.value;
    const typeVal = filterType ? filterType.value : "all";

    filterSubtype.innerHTML = '<option value="all">Sub-types</option>';

    const names: string[] = [];
    scholarshipTypes.forEach((t) => {
      if (typeVal !== "all" && t.name !== typeVal) return;
      (t.subtypes || []).forEach((st) => {
        if (!names.includes(st.name)) names.push(st.name);
      });
    });
    scholarships.forEach((s) => {
      if (!s.subtype) return;
      if (typeVal !== "all" && s.type !== typeVal) return;
      if (!names.includes(s.subtype)) names.push(s.subtype);
    });

    names.forEach((n) => {
      const opt = document.createElement("option");
      opt.value = n;
      opt.textContent = n;
      filterSubtype.appendChild(opt);
    });

    filterSubtype.value = names.includes(previousValue) ? previousValue : "all";
  }


  /* =========================================================
   TABLE ROWS
   Every sub-type is listed, whether or not a scholarship
   program has been created for it yet.
  ========================================================= */

  function buildTableRows(): any[] {

    const rows: any[] = [];
    const usedIds = new Set<any>();

    scholarshipTypes.forEach((t) => {
      (t.subtypes || []).forEach((st) => {
        const programs = scholarships.filter(
          (s) => s.type === t.name && s.subtype === st.name
        );

        if (programs.length) {
          programs.forEach((p) => {
            usedIds.add(p.id);
            rows.push({ kind: "program", s: p, sub: st });
          });
        } else {
          rows.push({ kind: "subtype", type: t, sub: st });
        }
      });
    });

    // Programs without a matching sub-type (older data, type-only).
    scholarships.forEach((s) => {
      if (!usedIds.has(s.id)) rows.push({ kind: "program", s, sub: null });
    });

    return rows;
  }


  /* =========================================================
   RENDER SCHOLARSHIPS
  ========================================================= */

  function renderScholarships(): void {

    if (!tableBody) return;

    const query = searchInput
      ? searchInput.value.toLowerCase().trim()
      : "";

    const typeVal = filterType ? filterType.value : "all";
    const subtypeVal = filterSubtype ? filterSubtype.value : "all";

    const filtered = buildTableRows().filter((row) => {

      const typeName = row.kind === "program" ? row.s.type : row.type.name;
      const subName = row.kind === "program" ? row.s.subtype : row.sub.name;
      const name = row.kind === "program" ? row.s.name : row.sub.name;
      const code = row.kind === "program" ? row.s.code : "";

      const haystack = [name, code, typeName, subName]
        .map((v) => String(v || "").toLowerCase());

      const matchQuery = !query || haystack.some((v) => v.includes(query));
      const matchType = typeVal === "all" || typeName === typeVal;
      const matchSubtype = subtypeVal === "all" || subName === subtypeVal;

      return matchQuery && matchType && matchSubtype;
    });


    tableBody.innerHTML = "";


    if (filtered.length === 0) {

      tableBody.innerHTML = `
        <tr>
          <td colspan="6"
            style="
              text-align:center;
              padding:24px;
              color:#6b7280;
            ">
            No scholarships found.
          </td>
        </tr>
      `;

      return;
    }


    filtered.forEach((row) => {

      const tr = document.createElement("tr");

      if (row.kind === "subtype") {
        renderSubtypeRow(tr, row);
      } else {
        renderProgramRow(tr, row);
      }

      tableBody.appendChild(tr);

    });


    if (typeof lucide !== "undefined") {
      lucide.createIcons();
    }

  }


  function renderProgramRow(tr: HTMLTableRowElement, row: any): void {

    const s = row.s;

    const status = String(s.status || "").toLowerCase();
    const badgeClass = status === "active" ? "badge-active" : "badge-inactive";

    let displayName = s.name || "";

    if (
      s.code &&
      !displayName.toLowerCase().includes(String(s.code).toLowerCase())
    ) {
      displayName = `${displayName} (${s.code})`;
    }

    // The sub-type's requirement is what Evaluation/Renewal enforce.
    const gwa = Number(row.sub ? row.sub.gwaRequirement : s.gwaRequirement);

    tr.innerHTML = `
      <td>
        <div class="name-cell">
          <span class="name">${escapeHtml(displayName)}</span>
          <span class="subtext">${escapeHtml(s.coverage || s.description || "Standard Benefit Coverage")}</span>
        </div>
      </td>

      <td>
        ${escapeHtml(s.type || "")}
        ${s.subtype ? `<div style="font-size:12px; color:#6b7280;">${escapeHtml(s.subtype)}</div>` : ""}
      </td>

      <td><span class="font-mono">${isNaN(gwa) ? "—" : gwa.toFixed(2)}</span></td>

      <td>
        <span class="font-mono">
          ${s.unlimitedSlots ? "Unlimited" : `${s.slotsAvailable ?? 0} &nbsp;/&nbsp; ${s.slots ?? 0}`}
        </span>
      </td>

      <td>
        <span class="status-badge ${badgeClass}">${escapeHtml(s.status || "")}</span>
      </td>

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
  }


  function renderSubtypeRow(tr: HTMLTableRowElement, row: any): void {

    const t = row.type;
    const st = row.sub;

    tr.classList.add("subtype-row");
    tr.setAttribute("data-subtype-row", String(st.id));

    tr.innerHTML = `
      <td>
        <div class="name-cell">
          <span class="name st-name">${escapeHtml(st.name)}</span>
          <span class="subtext">No program created yet</span>
        </div>
      </td>

      <td>${escapeHtml(t.name)}</td>

      <td class="st-gwa font-mono">${Number(st.gwaRequirement).toFixed(2)}</td>

      <td><span class="font-mono">—</span></td>

      <td><span class="status-badge badge-none">Not set up</span></td>

      <td class="actions-cell st-actions">
        <button type="button" class="btn-icon-action" data-st-create title="Create program for this sub-type">
          <i data-lucide="plus"></i>
        </button>
        <button type="button" class="btn-icon-action edit" data-st-edit title="Edit sub-type">
          <i data-lucide="pencil"></i>
        </button>
        <button type="button" class="btn-icon-action delete" data-st-delete title="Delete sub-type">
          <i data-lucide="trash-2"></i>
        </button>
      </td>
    `;

    const on = (sel: string, fn: () => void) => {
      const btn = tr.querySelector(sel);
      if (btn) btn.addEventListener("click", (e: Event) => { e.stopPropagation(); fn(); });
    };

    on("[data-st-create]", () => createProgramForSubtype(t, st.name));
    on("[data-st-edit]", () => startEditSubtypeRow(tr, st));
    on("[data-st-delete]", () => deleteSubtype(st.id));
  }

  function createProgramForSubtype(type: ScholarshipType, subtypeName: string): void {
    openFormModal(null);
    selectType(type.id, type.name);
    selectSubtype(subtypeName);
  }


  /* =========================================================
   SUB-TYPE INLINE EDIT / DELETE (rows without a program)
  ========================================================= */

  function escapeHtml(str: any): string {
    const d = document.createElement("div");
    d.textContent = str == null ? "" : String(str);
    return d.innerHTML;
  }

  function startEditSubtypeRow(tr: HTMLElement, st: ScholarshipSubtype): void {

    const nameEl = tr.querySelector(".st-name");
    const gwaCell = tr.querySelector(".st-gwa");
    const actionsCell = tr.querySelector(".st-actions");
    if (!nameEl || !gwaCell || !actionsCell) return;

    nameEl.innerHTML = '<input type="text" class="mt-edit-name" value="' + escapeHtml(st.name) + '" style="width:100%;">';
    gwaCell.innerHTML = '<input type="number" step="0.01" min="0.01" class="mt-edit-gwa" value="' + escapeHtml(Number(st.gwaRequirement).toFixed(2)) + '" style="width:90px;">';
    actionsCell.innerHTML =
      '<button type="button" class="btn-icon-action" data-st-save title="Save"><i data-lucide="check"></i></button>' +
      '<button type="button" class="btn-icon-action delete" data-st-cancel title="Cancel"><i data-lucide="x"></i></button>';

    const stop = (e: Event) => e.stopPropagation();
    tr.querySelectorAll("input").forEach((i) => i.addEventListener("click", stop));
    actionsCell.querySelector("[data-st-save]")!.addEventListener("click", (e: Event) => { stop(e); saveSubtypeRow(st.id, tr); });
    actionsCell.querySelector("[data-st-cancel]")!.addEventListener("click", (e: Event) => { stop(e); renderScholarships(); });

    if (typeof lucide !== "undefined") lucide.createIcons();
  }

  async function saveSubtypeRow(id: number, tr: HTMLElement): Promise<void> {

    const nameInput = tr.querySelector(".mt-edit-name") as HTMLInputElement | null;
    const gwaInput = tr.querySelector(".mt-edit-gwa") as HTMLInputElement | null;
    const name = nameInput ? nameInput.value.trim() : "";
    const gwa = gwaInput ? gwaInput.value.trim() : "";

    if (!name || !gwa || Number(gwa) <= 0) {
      alert("Please enter a name and a valid GWA greater than 0.");
      return;
    }

    try {
      const apiPath = "api";
      const res = await fetch(`${apiPath}/scholarship_types.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "update_subtype", id, name, gwa_requirement: gwa }),
      });
      const json = await res.json();
      if (json.success) {
        await loadScholarshipTypes();
        populateFilterTypes();
        renderScholarships();
      } else {
        alert(json.message || "Failed to update sub-type.");
      }
    } catch (e) {
      alert("Server error.");
    }
  }

  async function deleteSubtype(id: number): Promise<void> {

    if (!confirm("Delete this sub-type? This cannot be undone.")) return;

    try {
      const apiPath = "api";
      const res = await fetch(`${apiPath}/scholarship_types.php`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "delete_subtype", id }),
      });
      const json = await res.json();
      if (json.success) {
        await loadScholarshipTypes();
        populateFilterTypes();
        renderScholarships();
      } else {
        alert(json.message || "Failed to delete sub-type.");
      }
    } catch (e) {
      alert("Server error.");
    }
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
          <span class="detail-value font-mono">${s.unlimitedSlots ? "Unlimited (no slot limit)" : `${s.slots} (${s.slotsAvailable} available)`}</span>
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

  // "No slot limit": the Total Slots box is switched off while it is ticked.
  function syncUnlimitedSlots(): void {
    const unlimited = !!(schUnlimited && schUnlimited.checked);
    if (!schSlots) return;
    schSlots.disabled = unlimited;
    if (unlimited) schSlots.value = "";
    schSlots.placeholder = unlimited ? "Unlimited" : "50";
  }
  if (schUnlimited) schUnlimited.addEventListener("change", syncUnlimitedSlots);

  function openFormModal(editItem: any = null): void {
    if (!schFormOverlay) return;
    if (editItem) {
      if (schFormTitle) schFormTitle.textContent = "Edit Scholarship Program";
      if (schId) schId.value = String(editItem.id);
      if (schGwa) schGwa.value = String(editItem.gwaRequirement);
      if (schSlots) schSlots.value = String(editItem.slots);
      if (schUnlimited) schUnlimited.checked = !!editItem.unlimitedSlots;
      if (schCoverage) schCoverage.value = editItem.coverage || '';

      const matchedType = scholarshipTypes.find((t) => t.name === editItem.type);
      selectedTypeId = matchedType ? matchedType.id : null;
      pickedSubtypes = [];
      if (schGwa) { schGwa.disabled = false; schGwa.placeholder = "e.g. 1.75"; }
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
      pickedSubtypes = [];
      if (schGwa) { schGwa.disabled = false; schGwa.placeholder = "e.g. 1.75"; }
      if (schType) schType.value = "";
      if (schSubtype) schSubtype.value = "";
      renderTypePicker();
      renderSubtypePicker();
      updateIdentityPreview();
    }
    syncUnlimitedSlots();
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
          populateFilterTypes();
        }
        selectType(json.id, json.name);
      } else {
        alert(json.message || "Failed to add type.");
      }
    } catch (e) {
      alert("Server error.");
    }
  });

  /* =========================================================
     BULK ADD SUB-TYPES (with required GWA per sub-type)
  ========================================================= */

  let subtypeBulkRowCount = 0;

  function addSubtypeBulkRow(): void {
    const rows = document.getElementById("subtypeBulkRows");
    if (!rows) return;
    const row = document.createElement("div");
    row.className = "subtype-bulk-row";
    row.innerHTML =
      '<input type="text" class="subtype-bulk-name" placeholder="Sub-type name">' +
      '<input type="number" step="0.01" min="0.01" class="subtype-bulk-gwa" placeholder="GWA e.g. 1.75">' +
      '<button type="button" class="subtype-bulk-remove" title="Remove row"><i data-lucide="x"></i></button>';
    rows.appendChild(row);

    const removeBtn = row.querySelector<HTMLElement>(".subtype-bulk-remove");
    if (removeBtn) removeBtn.addEventListener("click", () => row.remove());

    subtypeBulkRowCount++;
    if (typeof lucide !== "undefined") lucide.createIcons();
  }

  function openSubtypeBulkModal(): void {
    if (!selectedTypeId) {
      alert("Select a Type first.");
      return;
    }
    const overlay = document.getElementById("subtypeBulkOverlay");
    const rows = document.getElementById("subtypeBulkRows");
    const label = document.getElementById("subtypeBulkTypeLabel");
    if (!overlay || !rows) return;

    rows.innerHTML = "";
    subtypeBulkRowCount = 0;
    addSubtypeBulkRow();
    addSubtypeBulkRow();
    addSubtypeBulkRow();

    const type = scholarshipTypes.find((t) => t.id === selectedTypeId);
    if (label) label.textContent = "Under: " + (type ? type.name : "");
    overlay.classList.add("open");
  }

  function closeSubtypeBulkModal(): void {
    const overlay = document.getElementById("subtypeBulkOverlay");
    if (overlay) overlay.classList.remove("open");
  }

  async function saveSubtypeBulk(): Promise<void> {
    if (!selectedTypeId) return;

    const rowEls = document.querySelectorAll<HTMLElement>("#subtypeBulkRows .subtype-bulk-row");
    const items: { name: string; gwa_requirement: string }[] = [];
    let hasError = false;

    rowEls.forEach((row) => {
      const nameInput = row.querySelector<HTMLInputElement>(".subtype-bulk-name");
      const gwaInput = row.querySelector<HTMLInputElement>(".subtype-bulk-gwa");
      if (nameInput) nameInput.classList.remove("error");
      if (gwaInput) gwaInput.classList.remove("error");

      const name = nameInput ? nameInput.value.trim() : "";
      const gwa = gwaInput ? gwaInput.value.trim() : "";
      if (!name && !gwa) return; // skip a fully empty row

      if (!name || !gwa || Number(gwa) <= 0) {
        hasError = true;
        if (nameInput && !name) nameInput.classList.add("error");
        if (gwaInput && (!gwa || Number(gwa) <= 0)) gwaInput.classList.add("error");
        return;
      }
      items.push({ name, gwa_requirement: gwa });
    });

    if (hasError) {
      alert("Please fill in both a name and a valid GWA (greater than 0) for every sub-type row.");
      return;
    }
    if (items.length === 0) {
      alert("Add at least one sub-type.");
      return;
    }

    try {
      const res = await fetch("api/scholarship_types.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ action: "add_subtypes_batch", type_id: selectedTypeId, subtypes: items }),
      });
      const json = await res.json();
      if (json.success) {
        const type = scholarshipTypes.find((t) => t.id === selectedTypeId);
        let lastName = "";
        (json.subtypes || []).forEach((s: any) => {
          if (type && !type.subtypes.some((existing) => existing.id === s.id)) {
            type.subtypes.push({ id: s.id, name: s.name, gwaRequirement: s.gwaRequirement });
          }
          lastName = s.name;
        });
        closeSubtypeBulkModal();
        if (isAddMode()) {
          // Pick everything that was just added.
          (json.subtypes || []).forEach((s: any) => { if (!pickedSubtypes.includes(s.name)) pickedSubtypes.push(s.name); });
          if (schSubtype) schSubtype.value = pickedSubtypes[0] || "";
          renderSubtypePicker();
          updateIdentityPreview();
          applyPickedGwa();
        } else if (lastName) selectSubtype(lastName);
        else renderSubtypePicker();
        // New sub-types show up in the table right away.
        populateFilterTypes();
        renderScholarships();
      } else {
        alert(json.message || "Failed to save sub-types.");
      }
    } catch (e) {
      alert("Server error.");
    }
  }

  const subtypeSelectAllBtn = document.getElementById("schSubtypeSelectAll");
  if (subtypeSelectAllBtn) subtypeSelectAllBtn.addEventListener("click", toggleAllSubtypes);

  const subtypeAddBtn = document.getElementById("schSubtypeAddBtn");
  if (subtypeAddBtn) subtypeAddBtn.addEventListener("click", openSubtypeBulkModal);

  const subtypeBulkAddRowBtn = document.getElementById("subtypeBulkAddRowBtn");
  if (subtypeBulkAddRowBtn) subtypeBulkAddRowBtn.addEventListener("click", addSubtypeBulkRow);

  const subtypeBulkCancelBtn = document.getElementById("subtypeBulkCancelBtn");
  if (subtypeBulkCancelBtn) subtypeBulkCancelBtn.addEventListener("click", closeSubtypeBulkModal);

  const subtypeBulkCloseBtn = document.getElementById("subtypeBulkCloseBtn");
  if (subtypeBulkCloseBtn) subtypeBulkCloseBtn.addEventListener("click", closeSubtypeBulkModal);

  const subtypeBulkSaveBtn = document.getElementById("subtypeBulkSaveBtn");
  if (subtypeBulkSaveBtn) subtypeBulkSaveBtn.addEventListener("click", saveSubtypeBulk);

  async function saveMultipleScholarships(): Promise<void> {
    if (!schForm) return;
    const type = scholarshipTypes.find((t) => t.id === selectedTypeId);
    const typeName = schType ? schType.value : "";
    let created = 0;
    const skipped: string[] = [];
    const failed: string[] = [];

    for (const subName of pickedSubtypes) {
      if (scholarships.some((s: any) => s.type === typeName && s.subtype === subName)) { skipped.push(subName); continue; }

      const sub = type ? type.subtypes.find((s) => s.name === subName) : undefined;
      const { name, code } = computeIdentity(typeName, subName);
      const fd = new FormData(schForm);
      fd.set("name", name);
      fd.set("code", code);
      fd.set("subtype", subName);
      fd.set("gwa_requirement", sub ? String(sub.gwaRequirement) : "1.75");

      try {
        const res = await fetch("api/list_scholarships.php", { method: "POST", body: fd });
        const json = await res.json();
        if (json.success) created++; else failed.push(subName);
      } catch (e) {
        failed.push(subName);
      }
    }

    closeFormModal();
    await loadScholarships();

    const notes: string[] = [];
    if (skipped.length) notes.push("Skipped (already added): " + skipped.join(", "));
    if (failed.length) notes.push("Failed: " + failed.join(", "));
    alert(created + " scholarship" + (created === 1 ? "" : "s") + " added." + (notes.length ? "\n\n" + notes.join("\n") : ""));
  }

  if (schForm) {
    schForm.addEventListener("submit", async (e) => {
      e.preventDefault();
      if (!schType || !schType.value) {
        alert("Please select a scholarship Type.");
        return;
      }
      // Several sub-types picked in Add mode: create one program per pick.
      if (isAddMode() && pickedSubtypes.length > 1) {
        await saveMultipleScholarships();
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
  if (filterType) {
    filterType.addEventListener("change", () => {
      if (filterSubtype) filterSubtype.value = "all";
      populateFilterSubtypes();
      renderScholarships();
    });
  }
  if (filterSubtype) filterSubtype.addEventListener("change", renderScholarships);
  if (filterStatus) filterStatus.addEventListener("change", renderScholarships);

  // Types first, so every sub-type row can be listed with the programs.
  loadScholarshipTypes().then(() => {
    renderTypePicker();
    return loadScholarships();
  });
});
