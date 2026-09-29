"use strict";

document.addEventListener("DOMContentLoaded", () => {

    const tableBody = document.getElementById("tableBody");
    const searchInput = document.querySelector(".search-wrap input");
    const filterType = document.getElementById("filterType");
    const filterSubtype = document.getElementById("filterSubtype");

    const addBtn = document.getElementById("addScholarshipBtn");

    const schFormOverlay = document.getElementById("schFormOverlay");
    const schFormTitle = document.getElementById("schFormTitle");
    const schFormCloseBtn = document.getElementById("schFormCloseBtn");
    const schFormCancelBtn = document.getElementById("schFormCancelBtn");
    const schForm = document.getElementById("schForm");

    const schId = document.getElementById("schId");
    const schName = document.getElementById("schName");
    const schCode = document.getElementById("schCode");
    const schType = document.getElementById("schType");
    const schSubtype = document.getElementById("schSubtype");
    const schGwa = document.getElementById("schGwa");
    const schSlots = document.getElementById("schSlots");
    const schUnlimited = document.getElementById("schUnlimited");
    const schCoverage = document.getElementById("schCoverage");

    // Wizard steps 1-6 (Basic Info / Criteria / Documents / Benefits / Renewal / Review)
    const schEducationLevel = document.getElementById("schEducationLevel");
    const schSchoolYear = document.getElementById("schSchoolYear");
    const schAppStart = document.getElementById("schAppStart");
    const schAppDeadline = document.getElementById("schAppDeadline");
    const schDescription = document.getElementById("schDescription");
    const schStepCounter = document.getElementById("schStepCounter");
    const schProgressFill = document.getElementById("schProgressFill");
    const schWizardBackBtn = document.getElementById("schWizardBackBtn");
    const schWizardNextBtn = document.getElementById("schWizardNextBtn");
    const schWizardSaveBtn = document.getElementById("schWizardSaveBtn");
    const schCriteriaRows = document.getElementById("schCriteriaRows");
    const schDocumentRows = document.getElementById("schDocumentRows");
    const schBenefitRows = document.getElementById("schBenefitRows");
    const schRenewalRequired = document.getElementById("schRenewalRequired");
    const schRenewalDetails = document.getElementById("schRenewalDetails");
    const schRenewalFlags = document.getElementById("schRenewalFlags");
    const schRenewalPeriod = document.getElementById("schRenewalPeriod");
    const schRenewalMinGwa = document.getElementById("schRenewalMinGwa");
    const schRenewalNoFailing = document.getElementById("schRenewalNoFailing");
    const schRenewalUpdatedDocs = document.getElementById("schRenewalUpdatedDocs");
    const schRenewalDescription = document.getElementById("schRenewalDescription");
    const schReviewBody = document.getElementById("schReviewBody");

    const schViewOverlay = document.getElementById("schViewOverlay");
    const schViewCloseBtn = document.getElementById("schViewCloseBtn");
    const schViewCloseBtn2 = document.getElementById("schViewCloseBtn2");
    const schViewBody = document.getElementById("schViewBody");

    const schDeleteOverlay = document.getElementById("schDeleteOverlay");
    const schDeleteCloseBtn = document.getElementById("schDeleteCloseBtn");
    const schDeleteCancelBtn = document.getElementById("schDeleteCancelBtn");
    const schDeleteConfirmBtn = document.getElementById("schDeleteConfirmBtn");
    const schDeleteTarget = document.getElementById("schDeleteTarget");

    let scholarships = [];
    let deletingSchId = null;

    let scholarshipTypes = [];
    let selectedTypeId = null;
    // Add mode lets several sub-types be picked at once (one program is
    // created per pick); edit mode keeps the single-pick behaviour.
    let pickedSubtypes = [];
    const isAddMode = () => !(schId && schId.value);

    /* =========================================================
       SCHOLARSHIP TYPE / SUB-TYPE PICKER
    ========================================================= */

    async function loadScholarshipTypes() {
        try {
            const apiPath = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";
            const res = await fetch(`${apiPath}/scholarship_types.php`);
            const json = await res.json();
            if (json.success) scholarshipTypes = json.data || [];
        } catch (e) {
            console.error("Failed to load scholarship types:", e);
        }
    }

    function renderTypePicker(selectedName) {
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

        if (selectedName && !scholarshipTypes.some((t) => t.name === selectedName)) {
            const pill = document.createElement("button");
            pill.type = "button";
            pill.className = "pill active";
            pill.textContent = selectedName;
            picker.insertBefore(pill, addWrap);
        }

        if (typeof lucide !== "undefined") lucide.createIcons();
    }

    function selectType(id, name) {
        selectedTypeId = id;
        pickedSubtypes = [];
        if (schType) schType.value = name;
        if (schSubtype) schSubtype.value = "";
        renderTypePicker(name);
        renderSubtypePicker();
        updateIdentityPreview();
    }

    function renderSubtypePicker(selectedName) {
        const field = document.getElementById("schSubtypeField");
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
            empty.textContent = "No programs yet — add one";
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

        const selectAllBtn = document.getElementById("schSubtypeSelectAll");
        const hint = document.getElementById("schSubtypeHint");
        if (selectAllBtn) {
            selectAllBtn.hidden = !isAddMode() || subtypes.length < 2;
            selectAllBtn.textContent = subtypes.length && pickedSubtypes.length === subtypes.length ? "Clear all" : "Select all";
        }
        if (hint) hint.hidden = !isAddMode();

        if (typeof lucide !== "undefined") lucide.createIcons();
    }

    function applyPickedGwa() {
        const type = scholarshipTypes.find((t) => t.id === selectedTypeId);
        if (!schGwa) return;
        if (pickedSubtypes.length > 1) {
            schGwa.value = "";
            schGwa.placeholder = "Each program uses its own GWA";
            schGwa.disabled = true;
            return;
        }
        schGwa.disabled = false;
        schGwa.placeholder = "Leave blank if none";
        const only = pickedSubtypes[0] || (schSubtype ? schSubtype.value : "");
        const subtype = type ? type.subtypes.find((s) => s.name === only) : undefined;
        if (subtype) schGwa.value = subtype.gwaRequirement > 0 ? String(subtype.gwaRequirement) : "";
    }

    function selectSubtype(name) {
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

        // Each sub-type carries its own required GWA — picking one applies
        // it straight to the scholarship's GWA Requirement field.
        applyPickedGwa();
    }

    function toggleAllSubtypes() {
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
     * derived from whichever Type/Sub-type is picked. When the source
     * string already looks like "ACRONYM (Full Description)" (the
     * convention used throughout this app), the acronym becomes the code;
     * otherwise a code is built from the initials of the significant words.
     */
    function computeIdentity(typeName, subtypeName) {
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

    function updateIdentityPreview() {
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
    // Scholarship Program)" — only append "(code)" separately when it isn't
    // already part of the name.
    function formatNameWithCode(name, code) {
        if (!name) return "";
        if (!code || name.includes("(")) return name;
        return `${name} (${code})`;
    }

    function setupPillAdd(addBtnId, inputId, onSubmit) {
        const btn = document.getElementById(addBtnId);
        const input = document.getElementById(inputId);
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

    /* =========================================================
       LOAD SCHOLARSHIPS
    ========================================================= */

    async function loadScholarships() {

        try {

            const apiPath =
                (typeof window !== "undefined" && window.API_BASE)
                    ? window.API_BASE
                    : "api";

            const res = await fetch(
                `${apiPath}/list_scholarships.php`
            );

            const json = await res.json();

            if (json.success) {

                scholarships = json.data || [];

                populateFilterTypes();
                renderScholarships();

            } else {

                console.error(
                    json.message || "Failed to load scholarships."
                );

            }

        } catch (e) {

            console.error(
                "Failed to load scholarships:",
                e
            );

        }

    }


    /* =========================================================
       POPULATE SCHOLARSHIP TYPE FILTER
    ========================================================= */

    function populateFilterTypes() {

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

    function populateFilterSubtypes() {

        if (!filterSubtype) return;

        const previousValue = filterSubtype.value;
        const typeVal = filterType ? filterType.value : "all";

        filterSubtype.innerHTML = '<option value="all">Programs</option>';

        const names = [];
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

    function buildTableRows() {

        const rows = [];
        const usedIds = new Set();

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

    function renderScholarships() {

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


    function renderProgramRow(tr, row) {

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

            <td><span class="font-mono">${isNaN(gwa) || gwa <= 0 ? "—" : gwa.toFixed(2)}</span></td>

            <td>
                <span class="font-mono">
                    ${s.unlimitedSlots ? "Unlimited" : `${s.slotsTaken ?? 0} &nbsp;/&nbsp; ${s.slots ?? 0}`}
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


    function renderSubtypeRow(tr, row) {

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

            <td class="st-gwa font-mono">${Number(st.gwaRequirement) > 0 ? Number(st.gwaRequirement).toFixed(2) : "—"}</td>

            <td><span class="font-mono">—</span></td>

            <td><span class="status-badge badge-none">Not set up</span></td>

            <td class="actions-cell st-actions">
                <button type="button" class="btn-icon-action" data-st-create title="Create scholarship for this program">
                    <i data-lucide="plus"></i>
                </button>
                <button type="button" class="btn-icon-action edit" data-st-edit title="Edit program">
                    <i data-lucide="pencil"></i>
                </button>
                <button type="button" class="btn-icon-action delete" data-st-delete title="Delete program">
                    <i data-lucide="trash-2"></i>
                </button>
            </td>
        `;

        const on = (sel, fn) => {
            const btn = tr.querySelector(sel);
            if (btn) btn.addEventListener("click", (e) => { e.stopPropagation(); fn(); });
        };

        on("[data-st-create]", () => createProgramForSubtype(t, st.name));
        on("[data-st-edit]", () => startEditSubtypeRow(tr, st));
        on("[data-st-delete]", () => deleteSubtype(st.id));
    }

    function createProgramForSubtype(type, subtypeName) {
        openFormModal(null);
        selectType(type.id, type.name);
        selectSubtype(subtypeName);
    }


    /* =========================================================
       SUB-TYPE INLINE EDIT / DELETE (rows without a program)
    ========================================================= */

    function escapeHtml(str) {
        const d = document.createElement("div");
        d.textContent = str == null ? "" : String(str);
        return d.innerHTML;
    }

    // A titled list-or-"None configured" block — used by both the wizard's Review step and
    // the read-only Scholarship Overview popup.
    function sectionListHtml(title, items, formatter) {
        const body = items.length
            ? "<ul>" + items.map((i) => `<li>${formatter(i)}</li>`).join("") + "</ul>"
            : '<p class="sch-review-empty">None configured.</p>';
        return `<div class="sch-review-section"><h4>${escapeHtml(title)}</h4>${body}</div>`;
    }

    function startEditSubtypeRow(tr, st) {

        const nameEl = tr.querySelector(".st-name");
        const gwaCell = tr.querySelector(".st-gwa");
        const actionsCell = tr.querySelector(".st-actions");
        if (!nameEl || !gwaCell || !actionsCell) return;

        nameEl.innerHTML = '<input type="text" class="mt-edit-name" value="' + escapeHtml(st.name) + '" style="width:100%;">';
        gwaCell.innerHTML = '<input type="number" step="0.01" min="0.01" class="mt-edit-gwa" value="' + escapeHtml(Number(st.gwaRequirement) > 0 ? Number(st.gwaRequirement).toFixed(2) : "") + '" placeholder="None" style="width:90px;">';
        actionsCell.innerHTML =
            '<button type="button" class="btn-icon-action" data-st-save title="Save"><i data-lucide="check"></i></button>' +
            '<button type="button" class="btn-icon-action delete" data-st-cancel title="Cancel"><i data-lucide="x"></i></button>';

        const stop = (e) => e.stopPropagation();
        tr.querySelectorAll("input").forEach((i) => i.addEventListener("click", stop));
        actionsCell.querySelector("[data-st-save]").addEventListener("click", (e) => { stop(e); saveSubtypeRow(st.id, tr); });
        actionsCell.querySelector("[data-st-cancel]").addEventListener("click", (e) => { stop(e); renderScholarships(); });

        if (typeof lucide !== "undefined") lucide.createIcons();
    }

    async function saveSubtypeRow(id, tr) {

        const nameInput = tr.querySelector(".mt-edit-name");
        const gwaInput = tr.querySelector(".mt-edit-gwa");
        const name = nameInput ? nameInput.value.trim() : "";
        const gwa = gwaInput ? gwaInput.value.trim() : "";

        if (!name || (gwa !== "" && Number(gwa) <= 0)) {
            alert("Please enter a name, and a GWA greater than 0 or leave it blank.");
            return;
        }

        try {
            const apiPath = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";
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
                alert(json.message || "Failed to update program.");
            }
        } catch (e) {
            alert("Server error.");
        }
    }

    async function deleteSubtype(id) {

        if (!confirm("Delete this program? This cannot be undone.")) return;

        try {
            const apiPath = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";
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
                alert(json.message || "Failed to delete program.");
            }
        } catch (e) {
            alert("Server error.");
        }
    }


    /* =========================================================
       VIEW MODAL
    ========================================================= */

    async function openViewModal(s) {

        if (!schViewOverlay || !schViewBody) {
            return;
        }

        const status = String(s.status || "").toLowerCase();
        const statusClass = status === "active" ? "badge-active" : "badge-inactive";

        let displayName = s.name || "";
        if (s.code && !displayName.toLowerCase().includes(String(s.code).toLowerCase())) {
            displayName = `${displayName} (${s.code})`;
        }

        const applicationWindow = (s.applicationStart || s.applicationDeadline)
            ? `${s.applicationStart || "—"} to ${s.applicationDeadline || "—"}`
            : "Not set";

        schViewBody.innerHTML = `
            <div class="view-detail-grid">
                <div class="detail-item full-width">
                    <span class="detail-label">Program Name</span>
                    <span class="detail-value highlight">${escapeHtml(displayName)}</span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Category / Type</span>
                    <span class="detail-value">${escapeHtml(s.type || "")}</span>
                </div>

                ${s.subtype ? `
                <div class="detail-item">
                    <span class="detail-label">Program</span>
                    <span class="detail-value">${escapeHtml(s.subtype)}</span>
                </div>
                ` : ""}

                <div class="detail-item">
                    <span class="detail-label">Status</span>
                    <span class="status-badge ${statusClass}">${escapeHtml(s.status || "")}</span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Education Level</span>
                    <span class="detail-value">${escapeHtml(s.educationLevel || "Collegiate")}</span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">School Year</span>
                    <span class="detail-value">${escapeHtml(s.schoolYear || "—")}</span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Application Window</span>
                    <span class="detail-value">${escapeHtml(applicationWindow)}</span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">GWA Requirement</span>
                    <span class="detail-value mono font-mono">${Number(s.gwaRequirement) > 0 ? "&le; " + escapeHtml(s.gwaRequirement) : "No GWA requirement"}</span>
                </div>

                <div class="detail-item">
                    <span class="detail-label">Total Slots Capacity</span>
                    <span class="detail-value font-mono">
                        ${s.unlimitedSlots ? "Unlimited (no slot limit)" : `${s.slots ?? 0} (${s.slotsAvailable ?? 0} available)`}
                    </span>
                </div>

                <div class="detail-item full-width">
                    <span class="detail-label">Benefit Coverage & Overview</span>
                    <span class="detail-value">${escapeHtml(s.coverage || s.description || "Full Tuition Coverage")}</span>
                </div>
            </div>

            <div id="schViewExtra" class="sch-view-extra">
                <p class="sch-review-empty">Loading eligibility criteria, documents, benefits &amp; renewal rules&hellip;</p>
            </div>
        `;

        schViewOverlay.classList.add("open");

        // The configured layer is fetched live (not cached on the row), so edits made
        // elsewhere on this page show up immediately without a full reload.
        try {
            const apiPath = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";
            const [c, d, b, r] = await Promise.all([
                fetch(`${apiPath}/scholarship_criteria.php?scholarship_id=${s.id}`).then((res) => res.json()),
                fetch(`${apiPath}/scholarship_documents.php?scholarship_id=${s.id}`).then((res) => res.json()),
                fetch(`${apiPath}/scholarship_benefits.php?scholarship_id=${s.id}`).then((res) => res.json()),
                fetch(`${apiPath}/scholarship_renewal_rules.php?scholarship_id=${s.id}`).then((res) => res.json()),
            ]);

            const extra = document.getElementById("schViewExtra");
            if (!extra) return; // the modal was closed before this resolved

            const criteria = c.success ? (c.data || []) : [];
            const documents = d.success ? (d.data || []) : [];
            const benefits = b.success ? (b.data || []) : [];
            const renewal = r.success ? r.data : null;

            extra.innerHTML =
                sectionListHtml("Eligibility Criteria", criteria, formatCriterionSummary) +
                sectionListHtml("Required Documents", documents, (item) => `${escapeHtml(item.label)}${item.required ? "" : " (optional)"}`) +
                sectionListHtml("Benefits", benefits, (item) => `${escapeHtml(item.label)}${item.value ? ": " + escapeHtml(item.value) : ""}`) +
                `<div class="sch-review-section"><h4>Renewal Rules</h4><p style="font-size:13px; color:#374151;">${renewal && renewal.requiresRenewal ? "Requires renewal (" + escapeHtml(renewal.renewalPeriod) + ")" : "Does not require renewal"}${renewal && renewal.minGwa ? " · Min GWA " + escapeHtml(renewal.minGwa) : ""}</p></div>`;
        } catch (e) {
            const extra = document.getElementById("schViewExtra");
            if (extra) extra.innerHTML = '<p class="sch-review-empty">Could not load eligibility criteria, documents, benefits &amp; renewal rules.</p>';
        }
    }


    function closeViewModal() {

        if (schViewOverlay) {
            schViewOverlay.classList.remove("open");
        }

    }


    /* =========================================================
       ADD / EDIT FORM MODAL
    ========================================================= */

    // "No slot limit": the Total Slots box is switched off while it is ticked.
    function syncUnlimitedSlots() {
        const unlimited = !!(schUnlimited && schUnlimited.checked);
        if (!schSlots)
            return;
        schSlots.disabled = unlimited;
        if (unlimited)
            schSlots.value = "";
        schSlots.placeholder = unlimited ? "Unlimited" : "50";
    }
    if (schUnlimited)
        schUnlimited.addEventListener("change", syncUnlimitedSlots);
    /* =========================================================
       WIZARD: Criteria / Documents / Benefits / Renewal Rules
       (registrar-configurable eligibility layer, per scholarship)
    ========================================================= */

    const SCH_WIZARD_STEPS = [
        "Basic Information", "Eligibility Criteria", "Required Documents",
        "Benefits / Incentives", "Renewal Rules", "Review",
    ];
    let schWizardStep = 1;
    let CRITERION_TYPES = {};
    let DOCUMENT_TYPES = {};
    let BENEFIT_TYPES = {};
    const OPERATOR_LABELS = { gt: "Greater than", gte: "At least", lt: "Less than", lte: "At most", eq: "Equal to", between: "Between" };

    async function loadWizardVocab() {
        const apiPath = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";
        try {
            const [c, d, b] = await Promise.all([
                fetch(`${apiPath}/scholarship_criteria.php`).then((r) => r.json()),
                fetch(`${apiPath}/scholarship_documents.php`).then((r) => r.json()),
                fetch(`${apiPath}/scholarship_benefits.php`).then((r) => r.json()),
            ]);
            if (c.success) CRITERION_TYPES = c.types || {};
            if (d.success) DOCUMENT_TYPES = d.types || {};
            if (b.success) BENEFIT_TYPES = b.types || {};
        } catch (e) {
            console.error("Failed to load criteria/document/benefit types:", e);
        }
    }

    function optionsHtml(typesObj, selected) {
        return Object.keys(typesObj)
            .map((key) => `<option value="${key}" ${key === selected ? "selected" : ""}>${escapeHtml(typesObj[key])}</option>`)
            .join("");
    }

    function goToWizardStep(n) {
        schWizardStep = Math.max(1, Math.min(SCH_WIZARD_STEPS.length, n));
        document.querySelectorAll(".sch-step").forEach((el) => {
            el.classList.toggle("active", Number(el.getAttribute("data-step")) === schWizardStep);
        });
        if (schStepCounter) schStepCounter.textContent = `Step ${schWizardStep} of ${SCH_WIZARD_STEPS.length} — ${SCH_WIZARD_STEPS[schWizardStep - 1]}`;
        if (schProgressFill) schProgressFill.style.width = `${(schWizardStep / SCH_WIZARD_STEPS.length) * 100}%`;
        // Plain `.hidden` loses to the `.btn-primary`/`.btn-secondary` classes' own
        // `display` rule (equal specificity, author stylesheet order) — set `display`
        // directly instead of relying on the `hidden` attribute for these buttons.
        if (schWizardBackBtn) schWizardBackBtn.style.display = schWizardStep === 1 ? "none" : "";
        const isLast = schWizardStep === SCH_WIZARD_STEPS.length;
        if (schWizardNextBtn) schWizardNextBtn.style.display = isLast ? "none" : "";
        if (schWizardSaveBtn) schWizardSaveBtn.style.display = isLast ? "" : "none";
        if (isLast) renderReviewStep();
    }

    function resetWizard() {
        schWizardStep = 1;
        if (schCriteriaRows) schCriteriaRows.innerHTML = "";
        if (schDocumentRows) schDocumentRows.innerHTML = "";
        if (schBenefitRows) schBenefitRows.innerHTML = "";
        if (schEducationLevel) schEducationLevel.value = "Collegiate";
        if (schSchoolYear) schSchoolYear.value = "";
        if (schAppStart) schAppStart.value = "";
        if (schAppDeadline) schAppDeadline.value = "";
        if (schDescription) schDescription.value = "";
        if (schRenewalRequired) schRenewalRequired.checked = true;
        if (schRenewalPeriod) schRenewalPeriod.value = "Every Semester";
        if (schRenewalMinGwa) schRenewalMinGwa.value = "";
        if (schRenewalNoFailing) schRenewalNoFailing.checked = true;
        if (schRenewalUpdatedDocs) schRenewalUpdatedDocs.checked = false;
        if (schRenewalDescription) schRenewalDescription.value = "";
        syncRenewalFieldVisibility();
        goToWizardStep(1);
    }

    function syncRenewalFieldVisibility() {
        const required = !!(schRenewalRequired && schRenewalRequired.checked);
        if (schRenewalDetails) schRenewalDetails.style.opacity = required ? "1" : "0.45";
        if (schRenewalFlags) schRenewalFlags.style.opacity = required ? "1" : "0.45";
        [schRenewalPeriod, schRenewalMinGwa, schRenewalNoFailing, schRenewalUpdatedDocs].forEach((el) => {
            if (el) el.disabled = !required;
        });
    }
    if (schRenewalRequired) schRenewalRequired.addEventListener("change", syncRenewalFieldVisibility);

    // Only these actually get compared numerically (see evaluateCriterion() in
    // includes/scholarship_criteria_helper.php) — everything else either auto-checks a
    // yes/no fact (no Operator/Value needed) or needs a registrar's manual Pass/Fail/Pending
    // mark (Operator/Value are never read for it). Showing Operator/Value for those was
    // misleading — the boxes looked live but were silently ignored.
    const NUMERIC_CRITERIA = ["gwa", "cwra", "minimum_grade"];
    // These match a single exact value (year level, program) rather than a number range,
    // so they get a plain Value box with no Operator dropdown.
    const TEXT_MATCH_CRITERIA = ["year_level", "program"];
    // Auto-checked yes/no facts (see AUTO_CHECKABLE_CRITERIA in
    // includes/scholarship_criteria_helper.php) that aren't numeric or text-match — the
    // system still checks these itself, it's just not a "manual verification" item like
    // Leadership Experience or Family Income.
    const AUTO_BOOLEAN_CRITERIA = ["no_failing_grades", "enrollment_status", "regular_student", "existing_scholarship_restriction"];
    // The registrar sets only a peso amount; the applicant's own Family Income (entered on
    // the Apply page) is compared against it automatically — always "at most".
    const THRESHOLD_CRITERIA = ["poverty_threshold"];

    function criterionValuePlaceholder(type) {
        if (type === "year_level") return "e.g. 3rd Year";
        if (type === "program") return "e.g. BS Information Technology";
        if (THRESHOLD_CRITERIA.includes(type)) return "Monthly threshold in ₱ (e.g. 13873)";
        if (NUMERIC_CRITERIA.includes(type)) return "Value (e.g. 1.75)";
        if (AUTO_BOOLEAN_CRITERIA.includes(type)) return "Optional notes (checked automatically)";
        return "Optional notes (not auto-checked)";
    }

    // Same split as applyCriterionFieldVisibility(), applied to how a saved criterion reads
    // back on the Review step / Scholarship Overview popup.
    function formatCriterionSummary(c) {
        const label = escapeHtml(CRITERION_TYPES[c.type] || c.type);
        const optionalNote = c.required ? "" : " (optional)";
        if (NUMERIC_CRITERIA.includes(c.type)) {
            return `${label} — ${OPERATOR_LABELS[c.operator] || c.operator} ${escapeHtml(c.value)}${c.value2 ? " and " + escapeHtml(c.value2) : ""}${optionalNote}`;
        }
        if (THRESHOLD_CRITERIA.includes(c.type)) {
          return `${label} — monthly family income at most ₱${escapeHtml(c.value)}${optionalNote} (checked automatically)`;
        }
        if (TEXT_MATCH_CRITERIA.includes(c.type)) {
            return `${label}: ${escapeHtml(c.value)}${optionalNote}`;
        }
        if (AUTO_BOOLEAN_CRITERIA.includes(c.type)) {
            return `${label}${optionalNote} (checked automatically)`;
        }
        return `${label}${c.value ? " — " + escapeHtml(c.value) : ""}${optionalNote} (manual verification)`;
    }

    function applyCriterionFieldVisibility(row) {
        const type = row.querySelector(".crit-type").value;
        const opSelect = row.querySelector(".crit-op");
        const valueInput = row.querySelector(".crit-value");
        const value2Input = row.querySelector(".crit-value2");
        const isNumeric = NUMERIC_CRITERIA.includes(type);
        opSelect.style.display = isNumeric ? "" : "none";
        if (!isNumeric) opSelect.value = THRESHOLD_CRITERIA.includes(type) ? "lte" : "eq";
        valueInput.placeholder = criterionValuePlaceholder(type);
        value2Input.style.display = isNumeric && opSelect.value === "between" ? "" : "none";
    }

    function addCriteriaRow(data) {
        if (!schCriteriaRows) return;
        const d = data || { type: "", operator: "lte", value: "", value2: "", required: true, label: "" };
        const row = document.createElement("div");
        row.className = "sch-crit-row";
        row.innerHTML =
            `<select class="sch-row-type crit-type">${optionsHtml(CRITERION_TYPES, d.type)}</select>` +
            `<select class="sch-row-op crit-op">${Object.keys(OPERATOR_LABELS).map((k) => `<option value="${k}" ${k === d.operator ? "selected" : ""}>${OPERATOR_LABELS[k]}</option>`).join("")}</select>` +
            `<input type="text" class="sch-row-value crit-value" placeholder="Value (e.g. 1.75)" value="${escapeHtml(d.value || "")}">` +
            `<input type="text" class="sch-row-value2 crit-value2" placeholder="and…" value="${escapeHtml(d.value2 || "")}" style="${d.operator === "between" ? "" : "display:none;"}">` +
            `<label class="sch-row-required-label"><input type="checkbox" class="crit-required" ${d.required !== false ? "checked" : ""}> Required</label>` +
            `<button type="button" class="sch-row-remove" title="Remove"><i data-lucide="x"></i></button>`;
        schCriteriaRows.appendChild(row);
        const typeSelect = row.querySelector(".crit-type");
        const opSelect = row.querySelector(".crit-op");
        typeSelect.addEventListener("change", () => applyCriterionFieldVisibility(row));
        opSelect.addEventListener("change", () => applyCriterionFieldVisibility(row));
        row.querySelector(".sch-row-remove").addEventListener("click", () => row.remove());
        applyCriterionFieldVisibility(row);
        if (typeof lucide !== "undefined") lucide.createIcons();
    }

    function addDocumentRow(data) {
        if (!schDocumentRows) return;
        const d = data || { type: "", description: "", required: true };
        const row = document.createElement("div");
        row.className = "sch-doc-row";
        row.innerHTML =
            `<select class="sch-row-type doc-type">${optionsHtml(DOCUMENT_TYPES, d.type)}</select>` +
            `<input type="text" class="sch-row-desc doc-desc" placeholder="Instructions (optional)" value="${escapeHtml(d.description || "")}">` +
            `<label class="sch-row-required-label"><input type="checkbox" class="doc-required" ${d.required !== false ? "checked" : ""}> Required</label>` +
            `<button type="button" class="sch-row-remove" title="Remove"><i data-lucide="x"></i></button>`;
        schDocumentRows.appendChild(row);
        row.querySelector(".sch-row-remove").addEventListener("click", () => row.remove());
        if (typeof lucide !== "undefined") lucide.createIcons();
    }

    function addBenefitRow(data) {
        if (!schBenefitRows) return;
        const d = data || { type: "", value: "", applyScope: "", description: "" };
        const row = document.createElement("div");
        row.className = "sch-benefit-row";
        row.innerHTML =
            `<select class="sch-row-type benefit-type">${optionsHtml(BENEFIT_TYPES, d.type)}</select>` +
            `<input type="text" class="sch-row-value benefit-value" placeholder="e.g. 50%" value="${escapeHtml(d.value || "")}">` +
            `<select class="sch-row-scope benefit-scope" style="${d.type === "tuition_discount" ? "" : "display:none;"}">${optionsHtml({ tuition_only: "Tuition Fees Only", tuition_plus_fees: "Tuition + Misc. Fees", custom: "Custom Scope" }, d.applyScope)}</select>` +
            `<input type="text" class="sch-row-desc benefit-desc" placeholder="Notes (optional)" value="${escapeHtml(d.description || "")}">` +
            `<button type="button" class="sch-row-remove" title="Remove"><i data-lucide="x"></i></button>`;
        schBenefitRows.appendChild(row);
        const typeSelect = row.querySelector(".benefit-type");
        const scopeSelect = row.querySelector(".benefit-scope");
        typeSelect.addEventListener("change", () => { scopeSelect.style.display = typeSelect.value === "tuition_discount" ? "" : "none"; });
        row.querySelector(".sch-row-remove").addEventListener("click", () => row.remove());
        if (typeof lucide !== "undefined") lucide.createIcons();
    }

    if (schCriteriaAddBtnRef()) schCriteriaAddBtnRef().addEventListener("click", () => addCriteriaRow());
    if (schDocumentAddBtnRef()) schDocumentAddBtnRef().addEventListener("click", () => addDocumentRow());
    if (schBenefitAddBtnRef()) schBenefitAddBtnRef().addEventListener("click", () => addBenefitRow());
    function schCriteriaAddBtnRef() { return document.getElementById("schCriteriaAddBtn"); }
    function schDocumentAddBtnRef() { return document.getElementById("schDocumentAddBtn"); }
    function schBenefitAddBtnRef() { return document.getElementById("schBenefitAddBtn"); }

    function collectCriteriaPayload() {
        return Array.from(document.querySelectorAll("#schCriteriaRows .sch-crit-row"))
            .map((row) => ({
                type: row.querySelector(".crit-type").value,
                operator: row.querySelector(".crit-op").value,
                value: row.querySelector(".crit-value").value.trim(),
                value2: row.querySelector(".crit-value2").value.trim(),
                required: row.querySelector(".crit-required").checked,
                label: CRITERION_TYPES[row.querySelector(".crit-type").value] || "",
            }))
            .filter((c) => c.type)
            // A peso amount may be typed as "₱15,000.00" — keep just the number.
            .map((c) => THRESHOLD_CRITERIA.includes(c.type) ? Object.assign(c, { value: c.value.replace(/[^0-9.]/g, "") }) : c);
    }
    function collectDocumentsPayload() {
        return Array.from(document.querySelectorAll("#schDocumentRows .sch-doc-row"))
            .map((row) => ({
                type: row.querySelector(".doc-type").value,
                description: row.querySelector(".doc-desc").value.trim(),
                required: row.querySelector(".doc-required").checked,
            }))
            .filter((d) => d.type);
    }
    function collectBenefitsPayload() {
        return Array.from(document.querySelectorAll("#schBenefitRows .sch-benefit-row"))
            .map((row) => ({
                type: row.querySelector(".benefit-type").value,
                value: row.querySelector(".benefit-value").value.trim(),
                applyScope: row.querySelector(".benefit-scope").value,
                description: row.querySelector(".benefit-desc").value.trim(),
            }))
            .filter((b) => b.type);
    }
    function collectRenewalPayload() {
        return {
            requiresRenewal: !!(schRenewalRequired && schRenewalRequired.checked),
            renewalPeriod: schRenewalPeriod ? schRenewalPeriod.value : "Every Semester",
            minGwa: schRenewalMinGwa && schRenewalMinGwa.value ? schRenewalMinGwa.value : null,
            noFailingGradesRequired: !!(schRenewalNoFailing && schRenewalNoFailing.checked),
            updatedDocumentsRequired: !!(schRenewalUpdatedDocs && schRenewalUpdatedDocs.checked),
            description: schRenewalDescription ? schRenewalDescription.value.trim() : "",
        };
    }

    function renderReviewStep() {
        if (!schReviewBody) return;
        const criteria = collectCriteriaPayload();
        const documents = collectDocumentsPayload();
        const benefits = collectBenefitsPayload();
        const renewal = collectRenewalPayload();

        const previewNameEl = document.getElementById("schPreviewName");
        schReviewBody.innerHTML =
            `<div class="sch-review-section"><h4>Program</h4><p style="font-size:13px; color:#374151;">${escapeHtml(previewNameEl ? previewNameEl.textContent : "")} &middot; ${escapeHtml(schEducationLevel ? schEducationLevel.value : "")}${schSchoolYear && schSchoolYear.value ? " · " + escapeHtml(schSchoolYear.value) : ""}</p></div>` +
            sectionListHtml("Eligibility Criteria", criteria, formatCriterionSummary) +
            sectionListHtml("Required Documents", documents, (d) => `${escapeHtml(DOCUMENT_TYPES[d.type] || d.type)}${d.required ? "" : " (optional)"}`) +
            sectionListHtml("Benefits", benefits, (b) => `${escapeHtml(BENEFIT_TYPES[b.type] || b.type)}${b.value ? ": " + escapeHtml(b.value) : ""}`) +
            `<div class="sch-review-section"><h4>Renewal Rules</h4><p style="font-size:13px; color:#374151;">${renewal.requiresRenewal ? "Requires renewal (" + escapeHtml(renewal.renewalPeriod) + ")" : "Does not require renewal"}${renewal.minGwa ? " · Min GWA " + escapeHtml(renewal.minGwa) : ""}</p></div>`;
    }

    async function loadWizardExtras(scholarshipId) {
        const apiPath = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";
        try {
            const [c, d, b, r] = await Promise.all([
                fetch(`${apiPath}/scholarship_criteria.php?scholarship_id=${scholarshipId}`).then((res) => res.json()),
                fetch(`${apiPath}/scholarship_documents.php?scholarship_id=${scholarshipId}`).then((res) => res.json()),
                fetch(`${apiPath}/scholarship_benefits.php?scholarship_id=${scholarshipId}`).then((res) => res.json()),
                fetch(`${apiPath}/scholarship_renewal_rules.php?scholarship_id=${scholarshipId}`).then((res) => res.json()),
            ]);
            if (c.success) (c.data || []).forEach((item) => addCriteriaRow(item));
            if (d.success) (d.data || []).forEach((item) => addDocumentRow(item));
            if (b.success) (b.data || []).forEach((item) => addBenefitRow(item));
            if (r.success && r.data) {
                const rules = r.data;
                if (schRenewalRequired) schRenewalRequired.checked = !!rules.requiresRenewal;
                if (schRenewalPeriod) schRenewalPeriod.value = rules.renewalPeriod || "Every Semester";
                if (schRenewalMinGwa) schRenewalMinGwa.value = rules.minGwa != null ? rules.minGwa : "";
                if (schRenewalNoFailing) schRenewalNoFailing.checked = !!rules.noFailingGradesRequired;
                if (schRenewalUpdatedDocs) schRenewalUpdatedDocs.checked = !!rules.updatedDocumentsRequired;
                if (schRenewalDescription) schRenewalDescription.value = rules.description || "";
                syncRenewalFieldVisibility();
            }
        } catch (e) {
            console.error("Failed to load scholarship criteria/documents/benefits/renewal rules:", e);
        }
    }

    async function saveWizardExtras(scholarshipId) {
        const apiPath = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";
        const post = (url, body) => fetch(`${apiPath}/${url}`, {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(body),
        }).then((r) => r.json());

        await Promise.all([
            post("scholarship_criteria.php", { scholarship_id: scholarshipId, action: "save_batch", criteria: collectCriteriaPayload() }),
            post("scholarship_documents.php", { scholarship_id: scholarshipId, action: "save_batch", documents: collectDocumentsPayload() }),
            post("scholarship_benefits.php", { scholarship_id: scholarshipId, action: "save_batch", benefits: collectBenefitsPayload() }),
            post("scholarship_renewal_rules.php", Object.assign({ scholarship_id: scholarshipId }, collectRenewalPayload())),
        ]);
    }

    if (schWizardNextBtn) schWizardNextBtn.addEventListener("click", () => {
        if (schWizardStep === 1 && (!schType || !schType.value)) {
            alert("Please select a scholarship Type.");
            return;
        }
        if (schWizardStep === 2) {
          const badThreshold = collectCriteriaPayload().some((c) => THRESHOLD_CRITERIA.includes(c.type) && !(Number(c.value) > 0));
          if (badThreshold) {
            alert("Please enter the Poverty Threshold amount (monthly, in pesos).");
            return;
          }
        }
        goToWizardStep(schWizardStep + 1);
    });
    if (schWizardBackBtn) schWizardBackBtn.addEventListener("click", () => goToWizardStep(schWizardStep - 1));

    function openFormModal(editItem = null) {

        if (!schFormOverlay) return;


        if (editItem) {

            if (schFormTitle) {
                schFormTitle.textContent =
                    "Edit Scholarship Program";
            }

            if (schId) {
                schId.value = String(editItem.id);
            }

            if (schType) {
                schType.value = editItem.type || "";
            }

            if (schSubtype) {
                schSubtype.value = editItem.subtype || "";
            }

            const matchedType = scholarshipTypes.find((t) => t.name === editItem.type);
            selectedTypeId = matchedType ? matchedType.id : null;
            pickedSubtypes = [];
            if (schGwa) { schGwa.disabled = false; schGwa.placeholder = "Leave blank if none"; }
            renderTypePicker(editItem.type);
            renderSubtypePicker(editItem.subtype || undefined);
            updateIdentityPreview();

            if (schGwa) {
                schGwa.value =
                    String(editItem.gwaRequirement ?? "");
            }

            if (schSlots) {
                schSlots.value =
                    String(editItem.slots ?? "");
            }

            if (schUnlimited) {
                schUnlimited.checked = !!editItem.unlimitedSlots;
            }

            if (schCoverage) {
                schCoverage.value =
                    editItem.coverage || "";
            }

            if (schEducationLevel) schEducationLevel.value = editItem.educationLevel || "Collegiate";
            if (schSchoolYear) schSchoolYear.value = editItem.schoolYear || "";
            if (schAppStart) schAppStart.value = editItem.applicationStart || "";
            if (schAppDeadline) schAppDeadline.value = editItem.applicationDeadline || "";
            if (schDescription) schDescription.value = editItem.description || "";

            if (schCriteriaRows) schCriteriaRows.innerHTML = "";
            if (schDocumentRows) schDocumentRows.innerHTML = "";
            if (schBenefitRows) schBenefitRows.innerHTML = "";
            if (schRenewalRequired) schRenewalRequired.checked = true;
            if (schRenewalPeriod) schRenewalPeriod.value = "Every Semester";
            if (schRenewalMinGwa) schRenewalMinGwa.value = "";
            if (schRenewalNoFailing) schRenewalNoFailing.checked = true;
            if (schRenewalUpdatedDocs) schRenewalUpdatedDocs.checked = false;
            if (schRenewalDescription) schRenewalDescription.value = "";
            syncRenewalFieldVisibility();
            loadWizardExtras(editItem.id);
            goToWizardStep(1);

        } else {

            if (schFormTitle) {
                schFormTitle.textContent =
                    "Add Scholarship Program";
            }

            if (schForm) {
                schForm.reset();
            }

            if (schId) {
                schId.value = "";
            }

            selectedTypeId = null;
            pickedSubtypes = [];
            if (schGwa) { schGwa.disabled = false; schGwa.placeholder = "Leave blank if none"; }
            if (schType) schType.value = "";
            if (schSubtype) schSubtype.value = "";
            renderTypePicker();
            renderSubtypePicker();
            updateIdentityPreview();

            resetWizard();

        }


        syncUnlimitedSlots();
        schFormOverlay.classList.add("open");
        if (typeof lucide !== "undefined") lucide.createIcons();

    }


    function closeFormModal() {

        if (schFormOverlay) {
            schFormOverlay.classList.remove("open");
        }

        if (schForm) {
            schForm.reset();
        }

    }


    /* =========================================================
       EDIT SCHOLARSHIP
    ========================================================= */

    window.editScholarship = function (event, id) {

        event.stopPropagation();

        const item =
            scholarships.find(
                s => Number(s.id) === Number(id)
            );

        if (item) {
            openFormModal(item);
        }

    };


    /* =========================================================
       DELETE SCHOLARSHIP
    ========================================================= */

    window.confirmDeleteScholarship = function (event, id) {

        event.stopPropagation();

        const item =
            scholarships.find(
                s => Number(s.id) === Number(id)
            );

        if (!item) return;

        deletingSchId = id;


        if (schDeleteTarget) {
            schDeleteTarget.textContent =
                item.name || "";
        }


        if (schDeleteOverlay) {
            schDeleteOverlay.classList.add("open");
        }

    };


    function closeDeleteModal() {

        if (schDeleteOverlay) {
            schDeleteOverlay.classList.remove("open");
        }

        deletingSchId = null;

    }


    /* =========================================================
       ADD NEW TYPE / SUB-TYPE
    ========================================================= */

    setupPillAdd("schTypeAddBtn", "schTypeAddInput", async (value) => {
        try {
            const apiPath = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";
            const res = await fetch(`${apiPath}/scholarship_types.php`, {
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
       ADD SUB-TYPE (name only — GWA comes from the program's GWA Requirement)
    ========================================================= */

    let subtypeBulkRowCount = 0;

    function addSubtypeBulkRow() {
        const rows = document.getElementById("subtypeBulkRows");
        if (!rows) return;
        const row = document.createElement("div");
        row.className = "subtype-bulk-row";
        row.innerHTML = '<input type="text" class="subtype-bulk-name" placeholder="Program name">';
        rows.appendChild(row);
        subtypeBulkRowCount++;
    }

    function openSubtypeBulkModal() {
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

        const type = scholarshipTypes.find((t) => t.id === selectedTypeId);
        if (label) label.textContent = "Under: " + (type ? type.name : "");
        overlay.classList.add("open");
        const firstInput = rows.querySelector(".subtype-bulk-name");
        if (firstInput) firstInput.focus();
    }

    function closeSubtypeBulkModal() {
        const overlay = document.getElementById("subtypeBulkOverlay");
        if (overlay) overlay.classList.remove("open");
    }

    async function saveSubtypeBulk() {
        if (!selectedTypeId) return;

        const rowEls = document.querySelectorAll("#subtypeBulkRows .subtype-bulk-row");
        // GWA is set on the scholarship's own GWA Requirement, so a sub-type is just a name.
        const items = [];

        rowEls.forEach((row) => {
            const nameInput = row.querySelector(".subtype-bulk-name");
            if (nameInput) nameInput.classList.remove("error");
            const name = nameInput ? nameInput.value.trim() : "";
            if (name) items.push({ name });
            else if (nameInput) nameInput.classList.add("error");
        });

        if (items.length === 0) {
            alert("Please enter a program name.");
            return;
        }

        try {
            const apiPath = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";
            const res = await fetch(`${apiPath}/scholarship_types.php`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ action: "add_subtypes_batch", type_id: selectedTypeId, subtypes: items }),
            });
            const json = await res.json();
            if (json.success) {
                const type = scholarshipTypes.find((t) => t.id === selectedTypeId);
                let lastName = "";
                (json.subtypes || []).forEach((s) => {
                    if (type && !type.subtypes.some((existing) => existing.id === s.id)) {
                        type.subtypes.push({ id: s.id, name: s.name, gwaRequirement: s.gwaRequirement });
                    }
                    lastName = s.name;
                });
                closeSubtypeBulkModal();
                if (isAddMode()) {
                    // Pick everything that was just added.
                    (json.subtypes || []).forEach((s) => { if (!pickedSubtypes.includes(s.name)) pickedSubtypes.push(s.name); });
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
                alert(json.message || "Failed to save program.");
            }
        } catch (e) {
            alert("Server error.");
        }
    }

    const subtypeSelectAllBtn = document.getElementById("schSubtypeSelectAll");
    if (subtypeSelectAllBtn) subtypeSelectAllBtn.addEventListener("click", toggleAllSubtypes);

    const subtypeAddBtn = document.getElementById("schSubtypeAddBtn");
    if (subtypeAddBtn) subtypeAddBtn.addEventListener("click", openSubtypeBulkModal);

    // Enter in the name field saves.
    const subtypeBulkRowsEl = document.getElementById("subtypeBulkRows");
    if (subtypeBulkRowsEl) subtypeBulkRowsEl.addEventListener("keydown", (e) => {
        if (e.key === "Enter") { e.preventDefault(); saveSubtypeBulk(); }
    });

    const subtypeBulkCancelBtn = document.getElementById("subtypeBulkCancelBtn");
    if (subtypeBulkCancelBtn) subtypeBulkCancelBtn.addEventListener("click", closeSubtypeBulkModal);

    const subtypeBulkCloseBtn = document.getElementById("subtypeBulkCloseBtn");
    if (subtypeBulkCloseBtn) subtypeBulkCloseBtn.addEventListener("click", closeSubtypeBulkModal);

    const subtypeBulkSaveBtn = document.getElementById("subtypeBulkSaveBtn");
    if (subtypeBulkSaveBtn) subtypeBulkSaveBtn.addEventListener("click", saveSubtypeBulk);

    /* =========================================================
       SAVE SCHOLARSHIP
    ========================================================= */

    async function saveMultipleScholarships() {
        const apiPath = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";
        const type = scholarshipTypes.find((t) => t.id === selectedTypeId);
        const typeName = schType ? schType.value : "";
        let created = 0;
        const skipped = [];
        const failed = [];

        for (const subName of pickedSubtypes) {
            const already = scholarships.some((s) => s.type === typeName && s.subtype === subName);
            if (already) { skipped.push(subName); continue; }

            const sub = type ? type.subtypes.find((s) => s.name === subName) : undefined;
            const { name, code } = computeIdentity(typeName, subName);
            const fd = new FormData(schForm);
            fd.set("name", name);
            fd.set("code", code);
            fd.set("subtype", subName);
            fd.set("gwa_requirement", sub && sub.gwaRequirement > 0 ? String(sub.gwaRequirement) : "");

            try {
                const res = await fetch(`${apiPath}/list_scholarships.php`, { method: "POST", body: fd });
                const json = await res.json();
                if (json.success) created++; else failed.push(subName);
            } catch (e) {
                failed.push(subName);
            }
        }

        closeFormModal();
        await loadScholarships();

        const notes = [];
        if (skipped.length) notes.push("Skipped (already added): " + skipped.join(", "));
        if (failed.length) notes.push("Failed: " + failed.join(", "));
        alert(created + " scholarship" + (created === 1 ? "" : "s") + " added." + (notes.length ? "\n\n" + notes.join("\n") : ""));
    }

    if (schForm) {

        schForm.addEventListener(
            "submit",
            async (e) => {

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

                const formData =
                    new FormData(schForm);


                try {

                    const apiPath =
                        (typeof window !== "undefined" &&
                         window.API_BASE)
                            ? window.API_BASE
                            : "api";


                    const res = await fetch(
                        `${apiPath}/list_scholarships.php`,
                        {
                            method: "POST",
                            body: formData
                        }
                    );


                    const json =
                        await res.json();


                    if (json.success) {

                        await saveWizardExtras(json.id);

                        closeFormModal();

                        await loadScholarships();

                    } else {

                        alert(
                            json.message ||
                            "Failed to save scholarship."
                        );

                    }

                } catch (err) {

                    console.error(err);

                    alert("Server error.");

                }

            }
        );

    }


    /* =========================================================
       DELETE SCHOLARSHIP REQUEST
    ========================================================= */

    if (schDeleteConfirmBtn) {

        schDeleteConfirmBtn.addEventListener(
            "click",
            async () => {

                if (!deletingSchId) return;


                try {

                    const apiPath =
                        (typeof window !== "undefined" &&
                         window.API_BASE)
                            ? window.API_BASE
                            : "api";


                    const res = await fetch(
                        `${apiPath}/delete_scholarship.php?id=${deletingSchId}`,
                        {
                            method: "POST"
                        }
                    );


                    const json =
                        await res.json();


                    if (json.success) {

                        closeDeleteModal();

                        await loadScholarships();

                    } else {

                        alert(
                            json.message ||
                            "Failed to delete."
                        );

                    }

                } catch (e) {

                    console.error(e);

                    alert("Server error.");

                }

            }
        );

    }


    /* =========================================================
       BUTTON EVENTS
    ========================================================= */

    if (addBtn) {
        addBtn.addEventListener(
            "click",
            () => openFormModal()
        );
    }


    if (schFormCloseBtn) {
        schFormCloseBtn.addEventListener(
            "click",
            closeFormModal
        );
    }


    if (schFormCancelBtn) {
        schFormCancelBtn.addEventListener(
            "click",
            closeFormModal
        );
    }


    if (schViewCloseBtn) {
        schViewCloseBtn.addEventListener(
            "click",
            closeViewModal
        );
    }


    if (schViewCloseBtn2) {
        schViewCloseBtn2.addEventListener(
            "click",
            closeViewModal
        );
    }


    if (schDeleteCloseBtn) {
        schDeleteCloseBtn.addEventListener(
            "click",
            closeDeleteModal
        );
    }


    if (schDeleteCancelBtn) {
        schDeleteCancelBtn.addEventListener(
            "click",
            closeDeleteModal
        );
    }


    /* =========================================================
       SEARCH & TYPE FILTER
       STATUS FILTER REMOVED
    ========================================================= */

    if (searchInput) {
        searchInput.addEventListener(
            "input",
            renderScholarships
        );
    }


    if (filterType) {
        filterType.addEventListener("change", () => {
            if (filterSubtype) filterSubtype.value = "all";
            populateFilterSubtypes();
            renderScholarships();
        });
    }

    if (filterSubtype) {
        filterSubtype.addEventListener("change", renderScholarships);
    }


    /* =========================================================
       INITIAL LOAD
    ========================================================= */

    // Types first, so every sub-type row can be listed with the programs.
    loadScholarshipTypes().then(() => {
        renderTypePicker();
        return loadScholarships();
    });
    loadWizardVocab();

});