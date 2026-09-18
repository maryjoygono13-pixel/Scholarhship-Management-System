"use strict";

document.addEventListener("DOMContentLoaded", () => {

    const tableBody = document.getElementById("tableBody");
    const searchInput = document.querySelector(".search-wrap input");
    const filterType = document.getElementById("filterType");

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
    const schCoverage = document.getElementById("schCoverage");

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

    function selectSubtype(name) {
        if (schSubtype) schSubtype.value = name;
        renderSubtypePicker(name);
        updateIdentityPreview();
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

        const types = Array.from(
            new Set(
                scholarships
                    .map(s => s.type)
                    .filter(Boolean)
            )
        );

        filterType.innerHTML =
            '<option value="all">All Scholarship Types</option>';

        types.forEach(type => {

            const opt = document.createElement("option");

            opt.value = type;
            opt.textContent = type;

            filterType.appendChild(opt);

        });

    }


    /* =========================================================
       RENDER SCHOLARSHIPS
    ========================================================= */

    function renderScholarships() {

        if (!tableBody) return;

        const query = searchInput
            ? searchInput.value.toLowerCase().trim()
            : "";

        const typeVal = filterType
            ? filterType.value.toLowerCase()
            : "all";


        const filtered = scholarships.filter((s) => {

            const name = String(s.name || "").toLowerCase();
            const code = String(s.code || "").toLowerCase();
            const type = String(s.type || "").toLowerCase();

            const matchQuery =
                name.includes(query) ||
                code.includes(query);

            const matchType =
                typeVal === "all" ||
                type === typeVal;

            return matchQuery && matchType;

        });


        tableBody.innerHTML = "";


        if (filtered.length === 0) {

            tableBody.innerHTML = `
                <tr>
                    <td colspan="5"
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


        filtered.forEach((s) => {

            const tr = document.createElement("tr");


            const status =
                String(s.status || "").toLowerCase();

            const badgeClass =
                status === "active"
                    ? "badge-active"
                    : "badge-inactive";


            let displayName = s.name || "";

            if (
                s.code &&
                !displayName
                    .toLowerCase()
                    .includes(String(s.code).toLowerCase())
            ) {

                displayName =
                    `${displayName} (${s.code})`;

            }


            tr.innerHTML = `
                <td>
                    <div class="name-cell">
                        <span class="name">
                            ${displayName}
                        </span>

                        <span class="subtext">
                            ${
                                s.coverage ||
                                s.description ||
                                "Standard Benefit Coverage"
                            }
                        </span>
                    </div>
                </td>

                <td>
                    ${s.type || ""}
                    ${s.subtype ? `<div style="font-size:12px; color:#6b7280;">${s.subtype}</div>` : ""}
                </td>

                <td>
                    <span class="font-mono">
                        ${s.slotsAvailable ?? 0}
                        &nbsp;/&nbsp;
                        ${s.slots ?? 0}
                    </span>
                </td>

                <td>
                    <span class="status-badge ${badgeClass}">
                        ${s.status || ""}
                    </span>
                </td>

                <td class="actions-cell">

                    <button
                        type="button"
                        class="btn-icon-action edit"
                        title="Edit Program"
                        onclick="editScholarship(event, ${s.id})"
                    >
                        <i data-lucide="pencil"></i>
                    </button>

                    <button
                        type="button"
                        class="btn-icon-action delete"
                        title="Delete Program"
                        onclick="confirmDeleteScholarship(event, ${s.id})"
                    >
                        <i data-lucide="trash-2"></i>
                    </button>

                </td>
            `;


            tr.addEventListener(
                "click",
                () => openViewModal(s)
            );


            tableBody.appendChild(tr);

        });


        if (typeof lucide !== "undefined") {
            lucide.createIcons();
        }

    }


    /* =========================================================
       VIEW MODAL
    ========================================================= */

    function openViewModal(s) {

        if (!schViewOverlay || !schViewBody) {
            return;
        }


        const status =
            String(s.status || "").toLowerCase();

        const statusClass =
            status === "active"
                ? "badge-active"
                : "badge-inactive";


        let displayName = s.name || "";

        if (
            s.code &&
            !displayName
                .toLowerCase()
                .includes(String(s.code).toLowerCase())
        ) {

            displayName =
                `${displayName} (${s.code})`;

        }


        schViewBody.innerHTML = `
            <div class="view-detail-grid">

                <div class="detail-item full-width">

                    <span class="detail-label">
                        Program Name
                    </span>

                    <span class="detail-value highlight">
                        ${displayName}
                    </span>

                </div>


                <div class="detail-item">

                    <span class="detail-label">
                        Category / Type
                    </span>

                    <span class="detail-value">
                        ${s.type || ""}
                    </span>

                </div>

                ${s.subtype ? `
                <div class="detail-item">
                    <span class="detail-label">Sub-type</span>
                    <span class="detail-value">${s.subtype}</span>
                </div>
                ` : ""}

                <div class="detail-item">

                    <span class="detail-label">
                        Status
                    </span>

                    <span class="status-badge ${statusClass}">
                        ${s.status || ""}
                    </span>

                </div>


                <div class="detail-item">

                    <span class="detail-label">
                        GWA Requirement
                    </span>

                    <span class="detail-value mono font-mono">
                        &le; ${s.gwaRequirement || "1.75"}
                    </span>

                </div>


                <div class="detail-item">

                    <span class="detail-label">
                        Total Slots Capacity
                    </span>

                    <span class="detail-value font-mono">
                        ${s.slots ?? 0}
                        (${s.slotsAvailable ?? 0} available)
                    </span>

                </div>


                <div class="detail-item full-width">

                    <span class="detail-label">
                        Benefit Coverage & Overview
                    </span>

                    <span class="detail-value">
                        ${
                            s.coverage ||
                            s.description ||
                            "Full Tuition Coverage"
                        }
                    </span>

                </div>

            </div>
        `;


        schViewOverlay.classList.add("open");

    }


    function closeViewModal() {

        if (schViewOverlay) {
            schViewOverlay.classList.remove("open");
        }

    }


    /* =========================================================
       ADD / EDIT FORM MODAL
    ========================================================= */

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

            if (schCoverage) {
                schCoverage.value =
                    editItem.coverage || "";
            }

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
            if (schType) schType.value = "";
            if (schSubtype) schSubtype.value = "";
            renderTypePicker();
            renderSubtypePicker();
            updateIdentityPreview();

        }


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
            const apiPath = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";
            const res = await fetch(`${apiPath}/scholarship_types.php`, {
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

    /* =========================================================
       SAVE SCHOLARSHIP
    ========================================================= */

    if (schForm) {

        schForm.addEventListener(
            "submit",
            async (e) => {

                e.preventDefault();

                if (!schType || !schType.value) {
                    alert("Please select a scholarship Type.");
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
        filterType.addEventListener(
            "change",
            renderScholarships
        );
    }


    /* =========================================================
       INITIAL LOAD
    ========================================================= */

    loadScholarshipTypes().then(() => renderTypePicker());
    loadScholarships();

});