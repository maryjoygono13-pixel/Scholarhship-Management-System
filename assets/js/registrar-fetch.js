"use strict";
/* ============================================================
   Data Management — Fetch from Registrar Database
   Talks to api/registrar_fetch.php (connection settings live in
   config/registrar_database.php).
   ============================================================ */
document.addEventListener("DOMContentLoaded", () => {
    const apiPath = (window.API_BASE || "api") + "/registrar_fetch.php";
    const conn = document.getElementById("registrarConn");
    const fileInput = document.getElementById("registrarFile");
    const chooseBtn = document.getElementById("registrarChooseBtn");
    const fileNameEl = document.getElementById("registrarFileName");
    const saveBox = document.getElementById("registrarSave");
    const fetchBtn = document.getElementById("registrarFetchBtn");
    const results = document.getElementById("registrarResults");
    const summaryEl = document.getElementById("registrarSummary");
    const gotoEl = document.getElementById("registrarGoto");
    const issuesEl = document.getElementById("registrarIssues");
    const issuesBody = document.getElementById("registrarIssuesBody");
    if (!fetchBtn || !fileInput) return;

    const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

    // Connection status (host:port/database from config/registrar_database.php).
    fetch(apiPath).then((r) => r.json()).then((json) => {
        const d = json.data || {};
        if (d.connected) {
            conn.textContent = `Connected · ${d.host}:${d.port} / ${d.database} · ${d.students} students`;
            conn.className = "registrar-conn is-ok";
        } else {
            conn.textContent = `Not connected · ${d.host}:${d.port} / ${d.database}`;
            conn.title = d.error || "";
            conn.className = "registrar-conn is-bad";
        }
    }).catch(() => {
        conn.textContent = "Connection status unavailable";
        conn.className = "registrar-conn is-bad";
    });

    chooseBtn.addEventListener("click", () => fileInput.click());
    fileInput.addEventListener("change", () => {
        fileNameEl.textContent = fileInput.files && fileInput.files[0] ? fileInput.files[0].name : "No file selected";
    });

    fetchBtn.addEventListener("click", async () => {
        const file = fileInput.files && fileInput.files[0];
        if (!file) {
            alert("Please choose an Excel (.xlsx) or CSV file first.");
            return;
        }
        const fd = new FormData();
        fd.append("file", file);
        fd.append("save", saveBox && saveBox.checked ? "1" : "0");

        fetchBtn.disabled = true;
        fetchBtn.textContent = "Fetching…";
        try {
            const res = await fetch(apiPath, { method: "POST", body: fd });
            const json = await res.json();
            if (!json.success) {
                alert(json.message || "Could not fetch the students.");
                return;
            }
            // The fetched students themselves are shown in Applicants and Evaluation; here we
            // only summarize, and list the rows that need attention.
            const s = json.summary;
            summaryEl.innerHTML =
                `<span><strong>${s.rows}</strong> in file</span>` +
                `<span class="ok"><strong>${s.found}</strong> found</span>` +
                (json.saved
                    ? `<span class="ok"><strong>${s.applicantsCreated}</strong> added to Applicants</span>` +
                      (s.applicantsUpdated ? `<span><strong>${s.applicantsUpdated}</strong> already there — updated</span>` : "") +
                      `<span><strong>${s.gradesSaved}</strong> grades saved</span>`
                    : `<span class="warn">Preview only — nothing was added</span>`) +
                (s.notFound ? `<span class="bad"><strong>${s.notFound}</strong> not found</span>` : "") +
                (s.nameMismatch ? `<span class="warn"><strong>${s.nameMismatch}</strong> name mismatch</span>` : "") +
                (s.programFull ? `<span class="bad"><strong>${s.programFull}</strong> not added — program full</span>` : "") +
                (s.programIssues - (s.programFull || 0) > 0 ? `<span class="warn"><strong>${s.programIssues - (s.programFull || 0)}</strong> program problem</span>` : "");
            gotoEl.hidden = !json.saved || (s.applicantsCreated + s.applicantsUpdated) === 0;

            const issues = json.results.filter((r) => !r.found || r.nameMatches === false || r.programIssue);
            issuesBody.innerHTML = issues.map((r) => {
                const problem = !r.found
                    ? '<span class="rf-badge bad">Not in the Registrar\'s database</span>'
                    : (r.nameMatches === false
                        ? '<span class="rf-badge warn">Name doesn\'t match — not added</span>'
                        : `<span class="rf-badge warn">${esc(r.programIssue)}</span>`);
                return `<tr><td class="mono">${esc(r.studentId)}</td><td>${esc(r.nameInFile)}</td><td>${esc(r.nameInRegistrar || "—")}</td><td>${problem}</td></tr>`;
            }).join("");
            issuesEl.hidden = issues.length === 0;
            results.hidden = false;
        } catch (e) {
            alert("Could not reach the server.");
        } finally {
            fetchBtn.disabled = false;
            fetchBtn.textContent = "Fetch";
        }
    });
});
