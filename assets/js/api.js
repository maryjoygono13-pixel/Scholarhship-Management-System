"use strict";

/* ============================================================
   Frontend API client — talks to the PHP backend under /api.
   ============================================================ */

const API_BASE =
    (typeof window !== "undefined" && window.API_BASE)
        ? window.API_BASE
        : "api";


/* ============================================================
   APPLICANTS
============================================================ */

async function apiListApplicants(status) {
    const url = status
        ? `${API_BASE}/list_applicants.php?status=${encodeURIComponent(status)}`
        : `${API_BASE}/list_applicants.php`;

    const res = await fetch(url);
    const json = await res.json();

    if (!json.success || !json.data) {
        throw new Error(
            json.message || "Failed to load applicants."
        );
    }

    return json.data;
}


async function apiGetApplicant(id) {
    const res = await fetch(
        `${API_BASE}/get_applicant.php?id=${id}`
    );

    const json = await res.json();

    if (!json.success || !json.data) {
        throw new Error(
            json.message || "Failed to load applicant."
        );
    }

    return json.data;
}


/* ============================================================
   SAVE APPLICANT
============================================================ */

async function apiSaveApplicant(formEl, applicantId, options) {

    const formData = new FormData();


    /* --------------------------------------------------------
       Regular form fields
    -------------------------------------------------------- */

    formEl
        .querySelectorAll("[data-field]")
        .forEach(el => {

            if (
                el instanceof HTMLInputElement &&
                el.type === "file"
            ) {
                return;
            }

            const key = el.dataset.field;

            if (key) {
                formData.append(
                    key,
                    el.value
                );
            }
        });


    /* --------------------------------------------------------
       File fields
    -------------------------------------------------------- */

    formEl
        .querySelectorAll(
            "input[type=file][data-field]"
        )
        .forEach(el => {

            const key = el.dataset.field;

            if (
                key &&
                el.files &&
                el.files[0]
            ) {
                formData.append(
                    key,
                    el.files[0]
                );
            }
        });


    /* --------------------------------------------------------
       Existing applicant ID

       If ID exists:
       PHP updates that exact applicant.

       If ID does not exist:
       PHP checks for duplicate
       Student ID + Scholarship Type.
    -------------------------------------------------------- */

    if (applicantId) {
        formData.append(
            "id",
            String(applicantId)
        );
    }

    if (options && options.confirmNameMismatch) {
        formData.append("confirmNameMismatch", "1");
    }


    /* --------------------------------------------------------
       Send request to PHP
    -------------------------------------------------------- */

    const res = await fetch(
        `${API_BASE}/save_applicant.php`,
        {
            method: "POST",
            body: formData
        }
    );


    /* --------------------------------------------------------
       Read PHP response

       IMPORTANT:
       This MUST happen before json.duplicate is checked.
    -------------------------------------------------------- */

    const json = await res.json();


    /* --------------------------------------------------------
       DUPLICATE APPLICATION

       Do NOT throw an error here.

       applicants.js will receive this response and ask
       the user whether they want to update the existing
       application.
    -------------------------------------------------------- */

    if (json.duplicate) {
        return json;
    }

    if (json.nameMismatch) {
        return json;
    }


    /* --------------------------------------------------------
       Normal error
    -------------------------------------------------------- */

    if (!json.success) {
        throw new Error(
            json.message ||
            "Failed to save applicant."
        );
    }


    /* --------------------------------------------------------
       Successful create/update
    -------------------------------------------------------- */

    return json;
}


/* ============================================================
   MOVE APPLICANT TO EVALUATION
============================================================ */

async function apiMoveToEvaluation(id) {

    const formData = new FormData();

    formData.append(
        "id",
        String(id)
    );

    const res = await fetch(
        `${API_BASE}/move_to_evaluation.php`,
        {
            method: "POST",
            body: formData
        }
    );

    const json = await res.json();

    if (!json.success) {
        throw new Error(
            json.message ||
            "Failed to move applicant to evaluation."
        );
    }

    return json;
}


/* ============================================================
   APPROVE / REJECT APPLICANT
============================================================ */

async function apiDecideApplicant(id, decision) {

    const formData = new FormData();

    formData.append(
        "id",
        String(id)
    );

    formData.append(
        "decision",
        decision
    );

    const res = await fetch(
        `${API_BASE}/decide.php`,
        {
            method: "POST",
            body: formData
        }
    );

    const json = await res.json();

    if (!json.success) {
        throw new Error(
            json.message ||
            "Failed to save decision."
        );
    }

    return json;
}


/* ============================================================
   NOTIFICATIONS
============================================================ */

async function apiListNotifications(type) {

    const url = type
        ? `${API_BASE}/list_notifications.php?type=${encodeURIComponent(type)}`
        : `${API_BASE}/list_notifications.php`;

    const res = await fetch(url);

    const json = await res.json();

    if (!json.success) {
        throw new Error(
            json.message ||
            "Failed to load notifications."
        );
    }

    return json;
}


/* ============================================================
   GET NOTIFICATION RECIPIENTS
============================================================ */

async function apiGetRecipients(segment) {

    const res = await fetch(
        `${API_BASE}/get_recipients.php?segment=${encodeURIComponent(segment)}`
    );

    const json = await res.json();

    if (!json.success) {
        throw new Error(
            json.message ||
            "Failed to load recipients."
        );
    }

    return json;
}


/* ============================================================
   SEND NOTIFICATION
============================================================ */

async function apiSendNotification(payload) {

    const formData = new FormData();

    Object.entries(payload).forEach(
        ([key, value]) => {
            formData.append(
                key,
                value
            );
        }
    );

    const res = await fetch(
        `${API_BASE}/send_notification.php`,
        {
            method: "POST",
            body: formData
        }
    );

    const json = await res.json();

    if (!json.success) {
        throw new Error(
            json.message ||
            "Failed to send notification."
        );
    }

    return json;
}


/* ============================================================
   NAVIGATION COUNTS
============================================================ */

async function updateNavCounts() {

    try {

        const [
            pending,
            evaluation,
            decided,
            inboxRes
        ] = await Promise.all([

            apiListApplicants("pending"),

            apiListApplicants("evaluation"),

            apiListApplicants(
                "approved,rejected"
            ),

            // The bell shows how many received messages are unread,
            // not how many notifications have ever been sent.
            fetch(`${API_BASE}/inbox.php?filter=unread`)
                .then(r => r.json())
                .catch(() => ({ unread: 0 }))
        ]);


        const appEl =
            document.getElementById(
                "navAppCount"
            );

        const evalEl =
            document.getElementById(
                "navEvalCount"
            );

        const recEl =
            document.getElementById(
                "navRecordsCount"
            );

        const notifEl =
            document.getElementById(
                "navNotifBadge"
            );


        if (appEl) {
            appEl.textContent =
                String(pending.length);
        }


        if (evalEl) {
            evalEl.textContent =
                String(evaluation.length);
        }


        if (recEl) {
            recEl.textContent =
                String(decided.length);
        }


        if (notifEl) {
            const unread =
                (inboxRes && inboxRes.success)
                    ? Number(inboxRes.unread || 0)
                    : 0;
            notifEl.textContent = String(unread);
            notifEl.hidden = unread === 0;
        }

    } catch (e) {

        console.error(
            "Failed to update nav counts:",
            e
        );
    }
}


/* ============================================================
   SCHOLARS
============================================================ */

async function apiListScholars(
    department,
    yearLevel,
    status,
    search
) {

    const params =
        new URLSearchParams();


    if (department) {
        params.append(
            "department",
            department
        );
    }


    if (yearLevel) {
        params.append(
            "year_level",
            String(yearLevel)
        );
    }


    if (status) {
        params.append(
            "status",
            status
        );
    }


    if (search) {
        params.append(
            "search",
            search
        );
    }


    const res = await fetch(
        `${API_BASE}/list_scholars.php?${params.toString()}`
    );

    const json = await res.json();

    if (!json.success || !json.data) {
        throw new Error(
            json.message ||
            "Failed to load scholars."
        );
    }

    return json.data;
}


/* ============================================================
   SAVE SCHOLAR
============================================================ */

async function apiSaveScholar(data) {

    const formData =
        new FormData();


    Object.entries(data).forEach(
        ([k, v]) => {

            formData.append(
                k,
                String(v ?? "")
            );

        }
    );


    const res = await fetch(
        `${API_BASE}/save_scholar.php`,
        {
            method: "POST",
            body: formData
        }
    );


    const json = await res.json();


    if (!json.success) {
        throw new Error(
            json.message ||
            "Failed to save scholar record."
        );
    }


    return json;
}


/* ============================================================
   DELETE SCHOLAR
============================================================ */

async function apiDeleteScholar(id) {

    const formData =
        new FormData();


    formData.append(
        "id",
        String(id)
    );


    const res = await fetch(
        `${API_BASE}/delete_scholar.php`,
        {
            method: "POST",
            body: formData
        }
    );


    const json = await res.json();


    if (!json.success) {
        throw new Error(
            json.message ||
            "Failed to delete scholar record."
        );
    }


    return json;
}


/* ============================================================
   GLOBAL WINDOW EXPORTS
============================================================ */

window.apiListApplicants =
    apiListApplicants;

window.apiGetApplicant =
    apiGetApplicant;

window.apiSaveApplicant =
    apiSaveApplicant;

window.apiMoveToEvaluation =
    apiMoveToEvaluation;

window.apiDecideApplicant =
    apiDecideApplicant;

window.apiListNotifications =
    apiListNotifications;

window.apiGetRecipients =
    apiGetRecipients;

window.apiSendNotification =
    apiSendNotification;

window.apiListScholars =
    apiListScholars;

window.apiSaveScholar =
    apiSaveScholar;

window.apiDeleteScholar =
    apiDeleteScholar;

window.updateNavCounts =
    updateNavCounts;

