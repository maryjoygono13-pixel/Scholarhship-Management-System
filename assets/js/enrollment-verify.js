"use strict";
/* ============================================================
   Data Management — Verify CHED Enrollment List.
   Talks to api/verify_enrollment.php (check a list / past checks)
   and api/download_verification.php (result file).
   ============================================================ */
document.addEventListener("DOMContentLoaded", () => {
    const API = window.API_BASE || "api";
    const fileInput = document.getElementById("verifyFile");
    const chooseBtn = document.getElementById("verifyChooseBtn");
    const fileName = document.getElementById("verifyFileName");
    const markBox = document.getElementById("verifyMark");
    const verifyBtn = document.getElementById("verifyBtn");
    const results = document.getElementById("verifyResults");
    const summaryEl = document.getElementById("verifySummary");
    const body = document.getElementById("verifyResultsBody");
    const downloadBtn = document.getElementById("verifyDownloadBtn");
    const filterBar = document.getElementById("verifyFilter");
    const historyBody = document.getElementById("verifyHistoryBody");
    if (!verifyBtn || !fileInput) return;

    const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
    const BADGE = { enrolled: "ok", not_enrolled: "bad", not_found: "muted", review: "warn" };
    // created_at is stored in UTC; shown in Philippine time.
    const fmtDate = (s) => {
        const d = new Date(String(s).replace(" ", "T") + "Z");
        return isNaN(d.getTime()) ? String(s) : d.toLocaleString("en-PH", { timeZone: "Asia/Manila", month: "short", day: "numeric", year: "numeric", hour: "numeric", minute: "2-digit" });
    };
    let rows = [];
    let filter = "all";

    function renderRows() {
        const shown = filter === "all" ? rows : rows.filter((r) => r.result === filter);
        body.innerHTML = shown.length
            ? shown.map((r) =>
                "<tr>" +
                // Student ID first (the Registrar's, or the one in the list when not found).
                `<td class="mono"><strong>${esc(r.studentId || r.idInFile || "—")}</strong></td>` +
                `<td>${esc(r.nameInFile)}</td>` +
                `<td><span class="rf-badge ${BADGE[r.result] || ""}">${esc(r.resultLabel)}</span>${r.markedInSystem ? '<div class="verify-marked">Marked enrolled</div>' : ""}</td>` +
                `<td>${esc(r.program || "—")}</td>` +
                `<td>${esc(r.yearLevel || "—")}</td>` +
                `<td class="muted">${esc(r.remarks)}</td>` +
                "</tr>").join("")
            : '<tr><td colspan="6" class="muted">No students in this group.</td></tr>';
    }

    async function loadHistory() {
        if (!historyBody) return;
        try {
            const res = await fetch(API + "/verify_enrollment.php");
            const json = await res.json();
            const list = json.data || [];
            historyBody.innerHTML = list.length
                ? '<table class="registrar-table"><thead><tr><th>Checked</th><th>File</th><th class="text-center">Students</th><th class="text-center">Enrolled</th><th class="text-center">Not enrolled</th><th class="text-center">Not found</th><th class="text-center">Check manually</th><th></th></tr></thead><tbody>' +
                  list.map((v) =>
                    "<tr>" +
                    `<td>${esc(fmtDate(v.createdAt))}<div class="muted">${esc(v.checkedBy)} · A.Y. ${esc(v.schoolYear)}</div></td>` +
                    `<td>${esc(v.fileName)}</td>` +
                    `<td class="text-center">${v.total}</td><td class="text-center">${v.enrolled}</td><td class="text-center">${v.notEnrolled}</td><td class="text-center">${v.notFound}</td><td class="text-center">${v.review}</td>` +
                    `<td>${v.hasResultFile ? `<a class="registrar-test-link" href="${API}/download_verification.php?id=${v.id}">Download</a>` : ""}</td>` +
                    "</tr>").join("") +
                  "</tbody></table>"
                : '<p class="muted">No lists checked yet.</p>';
        } catch (e) {
            historyBody.textContent = "Couldn't load previous checks.";
        }
    }

    chooseBtn.addEventListener("click", () => fileInput.click());
    fileInput.addEventListener("change", () => {
        fileName.textContent = fileInput.files && fileInput.files[0] ? fileInput.files[0].name : "No file selected";
    });

    if (filterBar) {
        filterBar.addEventListener("click", (e) => {
            const btn = e.target.closest("button[data-filter]");
            if (!btn) return;
            filter = btn.getAttribute("data-filter");
            filterBar.querySelectorAll("button").forEach((b) => b.classList.toggle("active", b === btn));
            renderRows();
        });
    }

    verifyBtn.addEventListener("click", async () => {
        const file = fileInput.files && fileInput.files[0];
        if (!file) {
            alert("Please choose the CHED list first (Excel .xlsx or CSV).");
            return;
        }
        const fd = new FormData();
        fd.append("file", file);
        fd.append("mark", markBox && markBox.checked ? "1" : "0");
        verifyBtn.disabled = true;
        verifyBtn.textContent = "Checking…";
        try {
            const res = await fetch(API + "/verify_enrollment.php", { method: "POST", body: fd });
            const json = await res.json();
            if (!json.success) {
                alert(json.message || "The list could not be checked.");
                return;
            }
            const s = json.summary;
            summaryEl.innerHTML =
                `<span><strong>${s.rows}</strong> students in list</span>` +
                `<span class="ok"><strong>${s.enrolled}</strong> enrolled</span>` +
                `<span class="${s.not_enrolled ? "bad" : ""}"><strong>${s.not_enrolled}</strong> not enrolled</span>` +
                `<span class="${s.not_found ? "bad" : ""}"><strong>${s.not_found}</strong> not found</span>` +
                `<span class="${s.review ? "warn" : ""}"><strong>${s.review}</strong> check manually</span>` +
                (json.marked ? `<span><strong>${s.marked}</strong> applicant record(s) marked enrolled</span>` : `<span class="warn">Not marked in the system</span>`) +
                `<span>A.Y. ${esc(json.schoolYear)}</span>`;
            downloadBtn.href = API + "/download_verification.php?id=" + json.verificationId;
            rows = json.results || [];
            filter = "all";
            if (filterBar) filterBar.querySelectorAll("button").forEach((b) => b.classList.toggle("active", b.getAttribute("data-filter") === "all"));
            renderRows();
            results.hidden = false;
            loadHistory();
        } catch (e) {
            alert("Could not reach the server.");
        } finally {
            verifyBtn.disabled = false;
            verifyBtn.textContent = "Verify";
        }
    });

    loadHistory();
});
