"use strict";

const STEPS = [
    { key: "personal", label: "Personal" },
    { key: "academic", label: "Academic" },
    { key: "scholarship", label: "Scholarship" },
    { key: "documents", label: "Documents" },
];

function getEl(id) {
    return document.getElementById(id);
}

/* ============================================================
   IN-SYSTEM NOTIFICATION
============================================================ */

function showAppNotification(message, type = "success") {

    let container =
        document.getElementById("appNotificationContainer");

    if (!container) {

        container =
            document.createElement("div");

        container.id =
            "appNotificationContainer";

        container.style.position =
            "fixed";

        container.style.top =
            "20px";

        container.style.right =
            "20px";

        container.style.zIndex =
            "99999";

        container.style.display =
            "flex";

        container.style.flexDirection =
            "column";

        container.style.gap =
            "10px";

        container.style.pointerEvents =
            "none";

        document.body.appendChild(
            container
        );
    }

    let borderLeftColor = "#10b981"; // success
    let iconColor = "#10b981";

    if (type === "error") {
        borderLeftColor = "#ef4444";
        iconColor = "#ef4444";
    } else if (type === "info") {
        borderLeftColor = "#3b82f6";
        iconColor = "#3b82f6";
    } else if (type === "warning") {
        borderLeftColor = "#f59e0b";
        iconColor = "#f59e0b";
    }

    const notification =
        document.createElement("div");

    notification.className =
        `app-notification ${type}`;

    notification.style.minWidth =
        "280px";

    notification.style.maxWidth =
        "420px";

    notification.style.padding =
        "13px 18px";

    notification.style.borderRadius =
        "8px";

    notification.style.background =
        "#ffffff";

    notification.style.boxShadow =
        "0 8px 25px rgba(0,0,0,0.12), 0 2px 6px rgba(0,0,0,0.06)";

    notification.style.border =
        "1px solid #e5e7eb";

    notification.style.borderLeft =
        `5px solid ${borderLeftColor}`;

    notification.style.fontSize =
        "14px";

    notification.style.fontWeight =
        "500";

    notification.style.color =
        "#1f2937";

    notification.style.display =
        "flex";

    notification.style.alignItems =
        "center";

    notification.style.gap =
        "10px";

    notification.style.pointerEvents =
        "auto";

    notification.style.cursor =
        "pointer";

    notification.style.opacity =
        "0";

    notification.style.transform =
        "translateX(30px)";

    notification.style.transition =
        "all 0.3s cubic-bezier(0.16, 1, 0.3, 1)";

    const textSpan =
        document.createElement("span");

    textSpan.style.flex =
        "1";

    textSpan.style.lineHeight =
        "1.4";

    textSpan.textContent =
        message;

    notification.appendChild(
        textSpan
    );

    container.appendChild(
        notification
    );

    requestAnimationFrame(() => {
        notification.style.opacity = "1";
        notification.style.transform = "translateX(0)";
    });

    const dismiss = () => {
        notification.style.opacity =
            "0";

        notification.style.transform =
            "translateX(30px)";

        notification.style.transition =
            "all 0.25s ease";

        setTimeout(() => {
            notification.remove();
        }, 250);
    };

    notification.addEventListener(
        "click",
        dismiss
    );

    setTimeout(
        dismiss,
        3800
    );
}

if (typeof window !== "undefined") {
    window.showAppNotification = showAppNotification;
}

let currentIndex = 0;
let furthestIndex = 0;

const formData = {};
let loadedApplicants = [];
let editingApplicantId = null;
let deletingApplicantId = null;

const checkIcon = `
<svg width="16" height="16" viewBox="0 0 24 24"
fill="none" stroke="currentColor" stroke-width="3"
stroke-linecap="round" stroke-linejoin="round">
<path d="M20 6 9 17l-5-5"/>
</svg>`;

const ICONS = {
    personal: `
    <svg width="16" height="16" viewBox="0 0 24 24"
    fill="none" stroke="currentColor" stroke-width="2.25"
    stroke-linecap="round" stroke-linejoin="round">
    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
    <circle cx="9" cy="7" r="4"/>
    </svg>`,

    academic: `
    <svg width="16" height="16" viewBox="0 0 24 24"
    fill="none" stroke="currentColor" stroke-width="2.25"
    stroke-linecap="round" stroke-linejoin="round">
    <path d="M22 10 12 5 2 10l10 5 10-5Z"/>
    <path d="M6 12v5c0 1.5 2.5 3 6 3s6-1.5 6-3v-5"/>
    </svg>`,

    scholarship: `
    <svg width="16" height="16" viewBox="0 0 24 24"
    fill="none" stroke="currentColor" stroke-width="2.25"
    stroke-linecap="round" stroke-linejoin="round">
    <circle cx="12" cy="8" r="6"/>
    <path d="M15.5 13.5 17 22l-5-3-5 3 1.5-8.5"/>
    </svg>`,

    documents: `
    <svg width="16" height="16" viewBox="0 0 24 24"
    fill="none" stroke="currentColor" stroke-width="2.25"
    stroke-linecap="round" stroke-linejoin="round">
    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/>
    <path d="M14 2v6h6"/>
    </svg>`
};


/* ============================================================
   PHONE FORMAT
============================================================ */

function formatPhoneNumber(val) {

    if (!val)
        return "";

    const isPlus =
        val.trim().startsWith("+");

    let digits =
        val.replace(/\D/g, "");

    if (isPlus || digits.startsWith("63")) {

        if (digits.startsWith("63"))
            digits = digits.slice(2);

        digits = digits.slice(0, 10);

        const p1 = digits.slice(0, 3);
        const p2 = digits.slice(3, 6);
        const p3 = digits.slice(6, 10);

        return `+63${p1 ? " " + p1 : ""}${p2 ? " " + p2 : ""}${p3 ? " " + p3 : ""}`;
    }

    digits = digits.slice(0, 11);

    const p1 = digits.slice(0, 4);
    const p2 = digits.slice(4, 7);
    const p3 = digits.slice(7, 11);

    return `${p1}${p2 ? " " + p2 : ""}${p3 ? " " + p3 : ""}`;
}


/* ============================================================
   PROGRAM → MAJOR
============================================================ */

function updateMajorOptions() {

    const programSelect =
        getEl("programSelect");

    const majorField =
        getEl("majorField");

    const majorSelect =
        getEl("majorSelect");

    if (
        !programSelect ||
        !majorField ||
        !majorSelect
    ) {
        return;
    }

    const program =
        programSelect.value;

    majorSelect.innerHTML = `
        <option value="">
            Select major
        </option>
    `;

    let majors = [];

    /*
     * BSBA
     */
    if (
        program ===
        "BS Business Administration"
    ) {

        majors = [
            "Human Resource Development Management (HRDM)",
            "Financial Management (FM)",
            "Marketing Management (MM)"
        ];
    }

    /*
     * BSEd
     */
    else if (
        program ===
        "Bachelor of Secondary Education"
    ) {

        majors = [
            "English",
            "Science",
            "Mathematics"
        ];
    }


    /*
     * Programs with Major
     */
    if (majors.length > 0) {

        majorField.style.display =
            "block";

        majorSelect.required =
            true;

        majors.forEach(major => {

            const option =
                document.createElement("option");

            option.value =
                major;

            option.textContent =
                major;

            majorSelect.appendChild(
                option
            );
        });

    }


    /*
     * Programs without Major
     */
    else {

        majorField.style.display =
            "none";

        majorSelect.required =
            false;

        majorSelect.value = "";

        formData.major = "";
    }
}


/* ============================================================
   LOAD TABLE
============================================================ */

async function loadTableData() {

    try {

        loadedApplicants =
            await window.apiListApplicants();

        renderTable();

    }
    catch (e) {

        console.error(
            "Error loading applicants:",
            e
        );

        loadedApplicants = [];

        renderTable();
    }
}


/* ============================================================
   RENDER TABLE
============================================================ */

function renderTable() {

    const tableBody =
        getEl("tableBody");

    const emptyState =
        getEl("emptyState");

    const searchInput =
        getEl("searchInput");

    const filterType =
        getEl("filterType");


    if (!tableBody)
        return;


    const query =
        searchInput
            ? searchInput.value
                .toLowerCase()
                .trim()
            : "";


    const typeVal =
        filterType
            ? filterType.value
            : "all";


    const filtered =
        loadedApplicants.filter(app => {

            const nameMatch =
                (app.name || "")
                    .toLowerCase()
                    .includes(query)

                ||

                (app.studentId || "")
                    .toLowerCase()
                    .includes(query);


            const typeMatch =
                typeVal === "all"

                ||

                (app.scholarshipType || "")
                    .toLowerCase() ===
                typeVal.toLowerCase();


            return (
                nameMatch &&
                typeMatch
            );
        });


    tableBody.innerHTML = "";


    if (filtered.length === 0) {

        if (emptyState) {

            emptyState.style.display =
                "block";

            emptyState.classList.add(
                "show"
            );
        }

        return;
    }


    if (emptyState) {

        emptyState.style.display =
            "none";

        emptyState.classList.remove(
            "show"
        );
    }


    filtered.forEach(app => {

        const tr =
            document.createElement("tr");


        const statusLower =
            (app.status || "")
                .toLowerCase();


        const statusBadgeClass =
            statusLower === "approved"
                ? "badge-approved"
                : statusLower === "rejected"
                    ? "badge-rejected"
                    : "badge-pending";


        const formattedStatus =
            app.status
                ? app.status.charAt(0).toUpperCase() +
                  app.status.slice(1)
                : "Pending";


        tr.innerHTML = `
            <td>
                <strong class="font-mono">
                    ${app.studentId || "-"}
                </strong>
            </td>

            <td>
                ${app.name || "-"}
            </td>

            <td>
                ${app.scholarshipType || "-"}
            </td>

            <td>
                <span class="status-badge ${statusBadgeClass}">
                    ${formattedStatus}
                </span>
            </td>

            <td>
                <span class="font-mono">
                    ${(app.createdAt || "").split(" ")[0] || "2026-08-10"}
                </span>
            </td>

            <td class="actions-cell">

                <button
                    type="button"
                    class="btn-icon-action edit"
                    title="Edit Applicant"
                    onclick="editApplicant(event, ${app.id})"
                >
                    <i data-lucide="pencil"></i>
                </button>

                <button
                    type="button"
                    class="btn-icon-action delete"
                    title="Delete Applicant"
                    onclick="confirmDeleteApplicant(event, ${app.id})"
                >
                    <i data-lucide="trash-2"></i>
                </button>

            </td>
        `;


        tr.addEventListener(
            "click",
            e => {

                if (
                    e.target &&
                    e.target.closest &&
                    e.target.closest(
                        ".actions-cell, .btn-icon-action, button, svg, path"
                    )
                ) {
                    return;
                }

                openViewModal(app);
            }
        );


        tableBody.appendChild(tr);
    });


    if (typeof lucide !== "undefined")
        lucide.createIcons();
}


/* ============================================================
   VIEW APPLICANT
============================================================ */

function openViewModal(app) {

    console.log("Applicant:", app);
    console.log("Address:", app.address);
    console.log("Latitude:", app.latitude);
    console.log("Longitude:", app.longitude);


    const viewOverlay =
        getEl("viewOverlay");

    const viewBody =
        getEl("viewBody");


    if (!viewOverlay || !viewBody)
        return;


    const statusLower =
        (app.status || "")
            .toLowerCase();


    const statusClass =
        statusLower === "approved"
            ? "badge-approved"
            : statusLower === "rejected"
                ? "badge-rejected"
                : "badge-pending";


    const formattedStatus =
        app.status
            ? app.status.charAt(0).toUpperCase() +
              app.status.slice(1)
            : "Pending";


    viewBody.innerHTML = `
        <div class="view-detail-grid">

            <div class="detail-item">
                <span class="detail-label">
                    Student ID
                </span>

                <span class="detail-value mono font-mono">
                    ${app.studentId || "N/A"}
                </span>
            </div>


            <div class="detail-item">
                <span class="detail-label">
                    Status
                </span>

                <span class="status-badge ${statusClass}">
                    ${formattedStatus}
                </span>
            </div>


            <div class="detail-item full-width">
                <span class="detail-label">
                    Full Name
                </span>

                <span class="detail-value highlight">
                    ${
                        [
                            app.firstName || "",
                            app.middleName || "",
                            app.lastName || ""
                        ]
                        .join(" ")
                        .replace(/\s+/g, " ")
                        .trim() || "N/A"
                    }
                </span>
            </div>

            <div class="detail-item">
                <span class="detail-label">
                    Gender
                </span>

                <span class="detail-value">
                    ${app.gender || "N/A"}
                </span>
            </div>

            <div class="detail-item">
                <span class="detail-label">
                    Age
                </span>

                <span class="detail-value">
                    ${
                        app.age !== null &&
                        app.age !== undefined &&
                        app.age !== ""
                            ? app.age
                            : "N/A"
                    }
                </span>
            </div>

            <div class="detail-item">
                <span class="detail-label">
                    Birthdate
                </span>

                <span class="detail-value">
                    ${app.birthdate || "N/A"}
                </span>
            </div>


            <div class="detail-item">
                <span class="detail-label">
                    Email Address
                </span>

                <span class="detail-value">
                    ${app.email || "N/A"}
                </span>
            </div>


            <div class="detail-item">
                <span class="detail-label">
                    Phone Number
                </span>

                <span class="detail-value font-mono">
                    ${formatPhoneNumber(app.phone || "N/A")}
                </span>
            </div>


            <div class="detail-item">
                <span class="detail-label">
                    School
                </span>

                <span class="detail-value">
                    ${app.school || "N/A"}
                </span>
            </div>

            <div class="detail-item">
                <span class="detail-label">
                    School Year
                </span>

                <span class="detail-value">
                    ${app.schoolYear || "N/A"}
                </span>
            </div>

            <div class="detail-item">
                <span class="detail-label">
                    Program
                </span>

                <span class="detail-value">
                    ${app.program || "N/A"}
                </span>
            </div>

            ${
                app.major
                    ? `
                    <div class="detail-item">
                        <span class="detail-label">
                            Major
                        </span>

                        <span class="detail-value">
                            ${app.major}
                        </span>
                    </div>
                    `
                    : ""
            }

            <div class="detail-item">
                <span class="detail-label">
                    Year Level
                </span>

                <span class="detail-value">
                    ${app.yearLevel || "N/A"}
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">
                    Scholarship Type
                </span>

                <span class="detail-value">
                    ${app.scholarshipType || "N/A"}
                </span>
            </div>


            <div class="detail-item">
                <span class="detail-label">
                    Current GPA / GWA
                </span>

                <span class="detail-value font-mono">
                    ${app.gpa || app.gwa || "N/A"}
                </span>
            </div>


            <div class="detail-item full-width">
                <span class="detail-label">
                    Home Address
                </span>

                <span class="detail-value">
                    ${app.address || "N/A"}
                </span>
            </div>


            <div
                id="applicantMap"
                style="height: 300px; width: 200%; margin-top: 15px;"
            ></div>


            ${
                app.remarks
                    ? `
                    <div class="detail-item full-width">
                        <span class="detail-label">
                            Remarks
                        </span>

                        <span class="detail-value remarks">
                            ${app.remarks}
                        </span>
                    </div>
                    `
                    : ""
            }

        </div>
    `;


    if (
        app.latitude &&
        app.longitude &&
        typeof L !== "undefined"
    ) {

        const map =
            L.map("applicantMap").setView(
                [
                    Number(app.latitude),
                    Number(app.longitude)
                ],
                15
            );


        L.tileLayer(
            "https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png",
            {
                attribution:
                    "&copy; OpenStreetMap contributors"
            }
        ).addTo(map);


        L.marker([
            Number(app.latitude),
            Number(app.longitude)
        ])
            .addTo(map)
            .bindPopup(
                app.address ||
                "Applicant location"
            )
            .openPopup();


        setTimeout(() => {

            map.invalidateSize();

        }, 200);
    }


    viewOverlay.classList.add("open");
}


function closeViewModal() {

    const viewOverlay =
        getEl("viewOverlay");

    if (viewOverlay)
        viewOverlay.classList.remove("open");
}


/* ============================================================
   EDIT APPLICANT
============================================================ */

window.editApplicant =
    async function (event, id) {

        if (
            event &&
            typeof event.stopPropagation ===
            "function"
        ) {

            event.stopPropagation();
        }


        closeViewModal();


        try {

            const app =
                await window.apiGetApplicant(id);


            editingApplicantId =
                Number(id);


            const fId =
                document.querySelector(
                    '[data-field="studentId"]'
                );


            const fFirst =
                document.querySelector(
                    '[data-field="firstName"]'
                );


            const fLast =
                document.querySelector(
                    '[data-field="lastName"]'
                );

            const fMiddle =
                document.querySelector(
                    '[data-field="middleName"]'
                );

            const fGender =
                document.querySelector(
                    '[data-field="gender"]'
                );
            const fEmail =
                document.querySelector(
                    '[data-field="email"]'
                );


            const fPhone =
                document.querySelector(
                    '[data-field="phone"]'
                );


            const fBirth =
                document.querySelector(
                    '[data-field="birthdate"]'
                );

            const fAge =
                document.querySelector(
                    '[data-field="age"]'
                );

            const fAddr =
                document.querySelector(
                    '[data-field="address"]'
                );


            const fSchool =
                document.querySelector(
                    '[data-field="school"]'
                );


            const fProg =
                document.querySelector(
                    '[data-field="program"]'
                );


            const fMajor =
                document.querySelector(
                    '[data-field="major"]'
                );


            const fYear =
                document.querySelector(
                    '[data-field="yearLevel"]'
                );

            const fSchoolYear =
                document.querySelector(
                    '[data-field="schoolYear"]'
                );

            const fGpa =
                document.querySelector(
                    '[data-field="gpa"]'
                );


            const fType =
                document.querySelector(
                    '[data-field="scholarshipType"]'
                );


            const fEssay =
                document.querySelector(
                    '[data-field="essay"]'
                );


            if (fId)
                fId.value =
                    app.studentId || "";


            if (fFirst)
                fFirst.value =
                    app.firstName || "";

            if (fMiddle)
                fMiddle.value =
                    app.middleName || "";

            if (fLast)
                fLast.value =
                    app.lastName || "";

            if (fGender)
                fGender.value =
                    app.gender || "";

            if (fEmail)
                fEmail.value =
                    app.email || "";


            if (fPhone)
                fPhone.value =
                    app.phone || "";


            if (fBirth)
                fBirth.value =
                    app.birthdate || "";

            if (fAge)
                fAge.value =
                    app.age || "";

            if (fAddr)
                fAddr.value =
                    app.address || "";


            if (fSchool)
                fSchool.value =
                    app.school || "";

            if (fSchoolYear)
                fSchoolYear.value =
                    app.schoolYear || "";


            /*
             * Set Program first.
             */
            if (fProg)
                fProg.value =
                    app.program || "";


            /*
             * Build the correct Major options
             * based on the saved Program.
             */
            updateMajorOptions();


            /*
             * Restore saved Major.
             */
            if (fMajor)
                fMajor.value =
                    app.major || "";


            if (fYear)
                fYear.value =
                    app.yearLevel || "";


            if (fGpa)
                fGpa.value =
                    app.gpa || "";


            if (fType)
                fType.value =
                    app.scholarshipType || "";


            if (fEssay)
                fEssay.value =
                    app.essay || "";


            /*
             * Keep formData synchronized
             * with the edited applicant.
             */
            document
                .querySelectorAll(
                    "[data-field]"
                )
                .forEach(el => {

                    if (
                        el instanceof HTMLInputElement &&
                        el.type === "file"
                    ) {
                        return;
                    }


                    const key =
                        el.dataset.field;


                    if (key)
                        formData[key] =
                            el.value;
                });


            openModal(true);

        }
        catch (e) {

            console.error(
                "Edit applicant error:",
                e
            );


            showAppNotification(
            "Could not load applicant data for edit.",
            "error"
        );
        }
    };


/* ============================================================
   DELETE
============================================================ */

window.confirmDeleteApplicant =
    function (event, id) {

        if (
            event &&
            typeof event.stopPropagation ===
            "function"
        ) {

            event.stopPropagation();
        }


        closeViewModal();


        const targetId =
            Number(id);


        if (!targetId)
            return;


        deletingApplicantId =
            targetId;


        const item =
            loadedApplicants.find(
                a =>
                    Number(a.id) ===
                    targetId
            );


        const deleteTargetName =
            getEl("deleteTargetName");


        const deleteConfirmOverlay =
            getEl("deleteConfirmOverlay");


        if (deleteTargetName) {

            deleteTargetName.textContent =
                item
                    ? (
                        item.name ||
                        `${item.firstName || ""} ${item.lastName || ""}`
                            .trim()
                    )
                    : `Applicant #${targetId}`;
        }


        if (deleteConfirmOverlay)
            deleteConfirmOverlay.classList.add(
                "open"
            );
    };


function closeDeleteModal() {

    const deleteConfirmOverlay =
        getEl("deleteConfirmOverlay");


    if (deleteConfirmOverlay)
        deleteConfirmOverlay.classList.remove(
            "open"
        );


    deletingApplicantId = null;
}


/* ============================================================
   PROGRESS
============================================================ */

function renderProgress() {

    const progressBar =
        getEl("progressBar");


    if (!progressBar)
        return;


    progressBar.innerHTML = "";


    STEPS.forEach((step, i) => {

        const wrap =
            document.createElement("div");


        wrap.className =
            "progress-step";


        const isCompleted =
            i < currentIndex;


        const isCurrent =
            i === currentIndex;


        const isReachable =
            i <= furthestIndex;


        const btn =
            document.createElement("button");


        btn.className =
            "step-btn";


        btn.type =
            "button";


        btn.disabled =
            !isReachable;


        btn.innerHTML = `
            <span class="step-circle ${
                isCompleted
                    ? "completed"
                    : isCurrent
                        ? "current"
                        : ""
            }">
                ${
                    isCompleted
                        ? checkIcon
                        : ICONS[step.key]
                }
            </span>

            <span class="step-label ${
                isCompleted
                    ? "completed"
                    : isCurrent
                        ? "current"
                        : ""
            }">
                ${step.label}
            </span>
        `;


        btn.addEventListener(
            "click",
            () => {

                if (isReachable) {

                    currentIndex = i;

                    render();
                }
            }
        );


        wrap.appendChild(btn);


        if (
            i <
            STEPS.length - 1
        ) {

            const line =
                document.createElement("div");


            line.className =
                "step-line" +
                (
                    i < currentIndex
                        ? " completed"
                        : ""
                );


            wrap.appendChild(line);
        }


        progressBar.appendChild(wrap);
    });
}


/* ============================================================
   RENDER
============================================================ */

function render() {

    const stepCounter =
        getEl("stepCounter");


    const modalFooter =
        getEl("modalFooter");


    const backBtn =
        getEl("backBtn");


    const nextBtn =
        getEl("nextBtn");


    document
        .querySelectorAll(".step-panel")
        .forEach(panel => {

            panel.classList.remove(
                "active"
            );
        });


    const activePanel =
        document.querySelector(
            `.step-panel[data-step="${currentIndex}"]`
        );


    if (activePanel)
        activePanel.classList.add(
            "active"
        );


    renderProgress();


    if (stepCounter) {

        stepCounter.textContent =
            `Step ${currentIndex + 1} of ${STEPS.length}`;
    }


    if (currentIndex === 0) {

        if (backBtn)
            backBtn.style.display =
                "none";


        if (modalFooter)
            modalFooter.style.justifyContent =
                "flex-end";

    }

    else {

        if (backBtn)
            backBtn.style.display =
                "inline-flex";


        if (modalFooter)
            modalFooter.style.justifyContent =
                "space-between";
    }


    /*
     * DO NOT REMOVE THIS.
     */
    if (nextBtn) {

        nextBtn.innerHTML =
            currentIndex ===
            STEPS.length - 1

                ? (
                    editingApplicantId
                        ? "Update Application"
                        : "Submit Application"
                )

                : `Next
                    <svg width="16" height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round">
                    <path d="m9 18 6-6-6-6"/>
                    </svg>`;
    }


    if (modalFooter)
        modalFooter.style.display =
            "flex";
}


/* ============================================================
   SUCCESS
============================================================ */

function showSuccess() {

    const successMsg =
        getEl("successMsg");


    const modalFooter =
        getEl("modalFooter");


    document
        .querySelectorAll(".step-panel")
        .forEach(panel => {

            panel.classList.remove(
                "active"
            );
        });


    const successPanel =
        document.querySelector(
            '.step-panel[data-step="success"]'
        );


    if (successPanel)
        successPanel.classList.add(
            "active"
        );


    if (modalFooter)
        modalFooter.style.display =
            "none";


    const name =
        `${formData.firstName || "The applicant"} ${formData.lastName || ""}`
            .trim();


    if (successMsg) {

        successMsg.textContent = editingApplicantId
            ? `${name} has been successfully updated in the system.`
            : `${name} has been successfully added and sent to Evaluation.`;
    }
}


/* ============================================================
   OPEN MODAL
============================================================ */

function openModal(isEdit = false) {

    const overlay =
        getEl("overlay");


    if (!isEdit) {

        editingApplicantId = null;

        currentIndex = 0;

        furthestIndex = 0;


        document
            .querySelectorAll("[data-field]")
            .forEach(el => {

                if (
                    el instanceof HTMLInputElement &&
                    el.type === "file"
                ) {

                    el.value = "";

                    return;
                }


                el.value = "";


                el.classList.remove(
                    "error"
                );
            });


        /*
         * Reset Program → Major
         */
        updateMajorOptions();


        document
            .querySelectorAll(".hint")
            .forEach(el => {

                el.textContent =
                    "PDF, JPG, or PNG · max 5MB";
            });


        document
            .querySelectorAll(".upload-action")
            .forEach(el => {

                el.textContent =
                    "Upload";
            });


        Object
            .keys(formData)
            .forEach(k =>
                delete formData[k]
            );
    }


    render();


    if (overlay)
        overlay.classList.add(
            "open"
        );
}


/* ============================================================
   CLOSE MODAL
============================================================ */

function closeModal() {

    const overlay =
        getEl("overlay");


    if (overlay)
        overlay.classList.remove(
            "open"
        );


    currentIndex = 0;

    furthestIndex = 0;

    editingApplicantId = null;


    document
        .querySelectorAll("[data-field]")
        .forEach(el => {

            if (
                el instanceof HTMLInputElement &&
                el.type === "file"
            ) {

                el.value = "";
            }

            else {

                el.value = "";
            }


            el.classList.remove(
                "error"
            );
        });


    /*
     * Reset Program → Major
     */
    updateMajorOptions();


    document
        .querySelectorAll(".hint")
        .forEach(el => {

            el.textContent =
                "PDF, JPG, or PNG · max 5MB";
        });


    document
        .querySelectorAll(".upload-action")
        .forEach(el => {

            el.textContent =
                "Upload";
        });


    Object
        .keys(formData)
        .forEach(k =>
            delete formData[k]
        );


    render();

    loadTableData();
}


/* ============================================================
   INITIALIZE PAGE
============================================================ */

function initApplicantsPage() {

    const applicantForm =
        getEl("applicantForm");


    if (applicantForm) {

        applicantForm.addEventListener(
            "submit",
            e => {

                e.preventDefault();
            }
        );
    }


    /* ========================================================
       OPEN BUTTON
    ======================================================== */

    document.addEventListener(
        "click",
        e => {

            const target =
                e.target;


            if (
                target &&
                target.closest &&
                target.closest(
                    "#openBtn, #emptyStateAddBtn"
                )
            ) {

                e.preventDefault();

                openModal(false);
            }
        }
    );


    /* ========================================================
       FORM FIELDS
    ======================================================== */

    document
        .querySelectorAll(
            "input[data-field], select[data-field], textarea[data-field]"
        )
        .forEach(el => {

            el.addEventListener(
                "change",
                () => {

                    const key =
                        el.dataset.field;


                    if (!key)
                        return;


                    if (
                        el instanceof HTMLInputElement &&
                        el.type === "file"
                    ) {

                        const file =
                            el.files
                                ? el.files[0]
                                : null;


                        formData[key] =
                            file || null;


                        const hint =
                            document.querySelector(
                                `.hint[data-hint="${key}"]`
                            );


                        const action =
                            document.querySelector(
                                `.upload-action[data-action="${key}"]`
                            );


                        if (file) {

                            if (hint)
                                hint.textContent =
                                    file.name;


                            if (action)
                                action.textContent =
                                    "Replace";

                        }

                        else {

                            if (hint)
                                hint.textContent =
                                    "PDF, JPG, or PNG · max 5MB";


                            if (action)
                                action.textContent =
                                    "Upload";
                        }

                    }

                    else {

                        formData[key] =
                            el.value;
                    }
                }
            );
        });


    /* ========================================================
       PROGRAM → MAJOR
    ======================================================== */

    const programSelect =
        getEl("programSelect");


    if (programSelect) {

        programSelect.addEventListener(
            "change",
            updateMajorOptions
        );


        updateMajorOptions();
    }


    /* ========================================================
       FILE UPLOAD BUTTONS
    ======================================================== */

    document
        .querySelectorAll(".upload-action")
        .forEach(actionEl => {

            actionEl.addEventListener(
                "click",
                () => {

                    const action =
                        actionEl.dataset.action;


                    if (action) {

                        const fileInput =
                            document.querySelector(
                                `input[data-field="${action}"]`
                            );


                        if (fileInput)
                            fileInput.click();
                    }
                }
            );
        });


    /* ========================================================
       CLOSE BUTTON
    ======================================================== */

    const closeBtn =
        getEl("closeBtn");


    if (closeBtn)
        closeBtn.addEventListener(
            "click",
            closeModal
        );


    const doneBtn =
        getEl("doneBtn");


    if (doneBtn)
        doneBtn.addEventListener(
            "click",
            closeModal
        );


    /* ========================================================
       BACK BUTTON
    ======================================================== */

    const backBtn =
        getEl("backBtn");


    if (backBtn) {

        backBtn.addEventListener(
            "click",
            () => {

                if (currentIndex > 0) {

                    currentIndex--;

                    render();
                }
            }
        );
    }


    /* ========================================================
       NEXT / SUBMIT BUTTON
    ======================================================== */

    const nextBtn =
        getEl("nextBtn");


    if (nextBtn) {

        nextBtn.addEventListener(
            "click",
            async e => {

                e.preventDefault();


                /*
                 * Clear old errors.
                 */
                document
                    .querySelectorAll(".error")
                    .forEach(el => {

                        el.classList.remove(
                            "error"
                        );
                    });


                const currentPanel =
                    document.querySelector(
                        `.step-panel[data-step="${currentIndex}"]`
                    );


                if (!currentPanel)
                    return;


                /*
                 * Validate required fields.
                 */
                const requiredFields =
                    currentPanel.querySelectorAll(
                        "[required]"
                    );


                let hasError = false;


                requiredFields.forEach(
                    field => {

                        if (
                            field instanceof HTMLInputElement &&
                            field.type === "file"
                        ) {

                            /*
                             * Existing applicants being edited
                             * don't need to re-upload files.
                             */
                            if (
                                !editingApplicantId &&
                                (
                                    !field.files ||
                                    field.files.length === 0
                                )
                            ) {

                                field.classList.add(
                                    "error"
                                );


                                if (!hasError)
                                    field.focus();


                                hasError = true;
                            }

                        }

                        else {

                            if (
                                field.value
                                    .trim() === ""
                            ) {

                                field.classList.add(
                                    "error"
                                );


                                if (!hasError)
                                    field.focus();


                                hasError = true;
                            }
                        }
                    }
                );


                if (hasError)
                    return;


                /* =================================================
                   FINAL STEP = SAVE
                ================================================= */

                if (
                    currentIndex ===
                    STEPS.length - 1
                ) {

                    /*
                     * Prevent double clicking.
                     */
                    if (
                        window.isSavingApplicant
                    ) {
                        return;
                    }


                    const form =
                        document.getElementById(
                            "applicantForm"
                        );


                    if (!form)
                        return;


                    window.isSavingApplicant =
                        true;


                    nextBtn.disabled = true;


                    nextBtn.textContent =
                        "Saving...";


                    try {

                        /* =========================================
                           EDITING EXISTING APPLICANT
                        ========================================= */

                        if (
                            editingApplicantId
                        ) {

                            const result =
                                await window.apiSaveApplicant(
                                    form,
                                    editingApplicantId
                                );


                            if (
                                !result ||
                                !result.success
                            ) {

                                throw new Error(
                                    result?.message ||
                                    "Failed to update the applicant."
                                );
                            }

                            showAppNotification(
                                "Applicant updated successfully.",
                                "success"
                            );
                        }


                        /* =========================================
                           ADDING NEW APPLICANT
                        ========================================= */

                        else {

                            /*
                             * IMPORTANT:
                             *
                             * Do NOT send an ID.
                             *
                             * PHP checks:
                             *
                             * Student ID +
                             * Scholarship Type
                             *
                             * Email is NOT used.
                             */
                            let result =
                                await window.apiSaveApplicant(
                                    form,
                                    null
                                );


                                                   /* =====================================
                               DUPLICATE FOUND
                            ===================================== */

                            if (
                                result &&
                                result.duplicate
                            ) {

                                const existing =
                                    result.existingApplicant ||
                                    {};

                                const existingName =
                                    [
                                        existing.firstName || "",
                                        existing.lastName || ""
                                    ]
                                    .join(" ")
                                    .trim();

                                const existingScholarship =
                                    existing.scholarshipType ||
                                    "Unknown scholarship";

                                const existingStatus =
                                    existing.status ||
                                    "Unknown";

                                const confirmMessage =
                                    "An application already exists " +
                                    "for this Student ID and scholarship type.\n\n" +

                                    "Applicant: " +
                                    existingName +
                                    "\n" +

                                    "Student ID: " +
                                    (
                                        existing.studentId ||
                                        ""
                                    ) +
                                    "\n" +

                                    "Scholarship: " +
                                    existingScholarship +
                                    "\n" +

                                    "Current Status: " +
                                    existingStatus +
                                    "\n\n" +

                                    "Do you want to UPDATE the existing application?";

                                const shouldUpdate =
                                    window.confirm(
                                        confirmMessage
                                    );


                                /* =================================
                                   USER SELECTED NO
                                ================================= */

                                if (!shouldUpdate) {

                                    showAppNotification(
                                        "Application was not saved. The existing application was not changed.",
                                        "info"
                                    );

                                    return;
                                }


                                /* =================================
                                   USER SELECTED YES
                                ================================= */

                                result =
                                    await window.apiSaveApplicant(
                                        form,
                                        existing.id
                                    );

                                if (
                                    !result ||
                                    !result.success
                                ) {

                                    throw new Error(
                                        result?.message ||
                                        "Failed to update the existing applicant."
                                    );
                                }

                                showAppNotification(
                                    "The existing applicant has been updated successfully.",
                                    "success"
                                );
                            }


                            /* =====================================
                               NEW APPLICANT CREATED
                            ===================================== */

                            else if (
                                result &&
                                result.success &&
                                result.action === "created"
                            ) {

                                showAppNotification(
                                    "Applicant added successfully.",
                                    "success"
                                );
                            }


                            /* =====================================
                               UNEXPECTED RESPONSE
                            ===================================== */

                             else if (
                                !result ||
                                !result.success
                            ) {

                                throw new Error(
                                    result?.message ||
                                    "Failed to save the applicant."
                                );
                            }
                        }
                        /* =========================================
                           REFRESH TABLE
                        ========================================= */

                        await loadTableData();


                        if (
                            typeof window.updateNavCounts ===
                            "function"
                        ) {

                            window.updateNavCounts();
                        }


                        showSuccess();

                    }

                    catch (err) {

                        console.error(
                            "Save applicant error:",
                            err
                        );


                        showAppNotification(
                            err.message ||
                            "Failed to submit application.",
                            "error"
                        );
                    }

                    finally {

                        window.isSavingApplicant =
                            false;


                        nextBtn.disabled =
                            false;


                        /*
                         * Restore correct button text.
                         */
                        nextBtn.textContent =
                            editingApplicantId
                                ? "Update Application"
                                : "Submit Application";
                    }


                    return;
                }


                /* =================================================
                   NEXT STEP
                ================================================= */

                currentIndex++;


                furthestIndex =
                    Math.max(
                        furthestIndex,
                        currentIndex
                    );


                render();
            }
        );
    }


    /* ========================================================
       OVERLAY
    ======================================================== */

    const overlay =
        getEl("overlay");


    if (overlay) {

        overlay.addEventListener(
            "click",
            e => {

                if (
                    e.target ===
                    overlay
                ) {

                    closeModal();
                }
            }
        );
    }


    /* ========================================================
       DELETE OVERLAY
    ======================================================== */

    const deleteConfirmOverlay =
        getEl("deleteConfirmOverlay");


    if (deleteConfirmOverlay) {

        deleteConfirmOverlay.addEventListener(
            "click",
            e => {

                if (
                    e.target ===
                    deleteConfirmOverlay
                ) {

                    closeDeleteModal();
                }
            }
        );
    }


    /* ========================================================
       VIEW OVERLAY
    ======================================================== */

    const viewOverlay =
        getEl("viewOverlay");


    if (viewOverlay) {

        viewOverlay.addEventListener(
            "click",
            e => {

                if (
                    e.target ===
                    viewOverlay
                ) {

                    closeViewModal();
                }
            }
        );
    }


    /* ========================================================
       DELETE CONFIRM
    ======================================================== */

    const deleteConfirmBtn =
        getEl("deleteConfirmBtn");


    if (deleteConfirmBtn) {

        deleteConfirmBtn.addEventListener(
            "click",
            async () => {

                if (!deletingApplicantId)
                    return;


                try {

                    const apiPath =
                        (
                            typeof window !==
                            "undefined" &&
                            window.API_BASE
                        )
                            ? window.API_BASE
                            : "api";


                    const res =
                        await fetch(
                            `${apiPath}/delete_applicant.php?id=${deletingApplicantId}`,
                            {
                                method: "POST"
                            }
                        );


                    const json =
                        await res.json();


                    if (json.success) {

                        closeDeleteModal();

                        showAppNotification(
                            json.message || "Applicant deleted successfully.",
                            "success"
                        );

                        await loadTableData();


                        if (
                            typeof window.updateNavCounts ===
                            "function"
                        ) {

                            window.updateNavCounts();
                        }

                    }

                    else {

                       showAppNotification(
                            json.message ||
                            "Failed to delete applicant.",
                            "error"
                        );
                    }

                }

                catch (e) {

                    console.error(
                        "Delete applicant error:",
                        e
                    );


                    showAppNotification(
                        "Server error when deleting applicant.",
                        "error"
                    );
                }
            }
        );
    }


    /* ========================================================
       DELETE CLOSE/CANCEL
    ======================================================== */

    const deleteCloseBtn =
        getEl("deleteCloseBtn");


    if (deleteCloseBtn)
        deleteCloseBtn.addEventListener(
            "click",
            closeDeleteModal
        );


    const deleteCancelBtn =
        getEl("deleteCancelBtn");


    if (deleteCancelBtn)
        deleteCancelBtn.addEventListener(
            "click",
            closeDeleteModal
        );


    /* ========================================================
       VIEW CLOSE BUTTONS
    ======================================================== */

    const viewCloseBtn =
        getEl("viewCloseBtn");


    if (viewCloseBtn)
        viewCloseBtn.addEventListener(
            "click",
            closeViewModal
        );


    const viewCloseBtn2 =
        getEl("viewCloseBtn2");


    if (viewCloseBtn2)
        viewCloseBtn2.addEventListener(
            "click",
            closeViewModal
        );


    /* ========================================================
       SEARCH
    ======================================================== */

    const searchInput =
        getEl("searchInput");


    if (searchInput) {

        searchInput.addEventListener(
            "input",
            renderTable
        );
    }


    /* ========================================================
       FILTER TYPE
    ======================================================== */

    const filterType =
        getEl("filterType");


    if (filterType) {

        filterType.addEventListener(
            "change",
            renderTable
        );
    }


    /* ========================================================
       STUDENT ID
    ======================================================== */

    const studentId =
        document.querySelector(
            '[data-field="studentId"]'
        );


    if (studentId) {

        studentId.addEventListener(
            "input",
            function () {

                this.value =
                    this.value.replace(
                        /\D/g,
                        ""
                    );
            }
        );
    }


    /* ========================================================
       PHONE
    ======================================================== */

    const phoneNumber =
        document.querySelector(
            '[data-field="phone"]'
        );


    if (phoneNumber) {

        phoneNumber.addEventListener(
            "input",
            function () {

                this.value =
                    formatPhoneNumber(
                        this.value
                    );
            }
        );
    }


    /* ========================================================
       INITIAL RENDER
    ======================================================== */

    render();

    loadTableData();
}


/* ============================================================
   PAGE READY
============================================================ */

if (
    document.readyState ===
    "loading"
) {

    document.addEventListener(  
        "DOMContentLoaded",
        initApplicantsPage
    );

}
else {

    initApplicantsPage();
}