"use strict";
/* ============================================================
   Scholars page — "Scan Registrar Now". Asks api/scan_deans_list.php
   to look through the Registrar's database for students holding a
   scholarship program and for Dean's Listers, adds the new ones to
   Scholars and Records, and says what it found.
   ============================================================ */
(function () {
    const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
    const plural = (n, one, many) => n + " " + (n === 1 ? one : many);

    document.addEventListener("DOMContentLoaded", () => {
        const btn = document.getElementById("scanRegistrarBtn");
        const box = document.getElementById("scanResult");
        if (!btn || !box) return;
        const label = btn.innerHTML;

        function show(kind, html) {
            box.className = "scan-result" + (kind ? " is-" + kind : "");
            box.innerHTML = '<span class="scan-result-text">' + html + '</span><button type="button" class="scan-result-close" aria-label="Dismiss">×</button>';
            box.hidden = false;
            box.querySelector(".scan-result-close").addEventListener("click", () => { box.hidden = true; });
        }

        btn.addEventListener("click", async () => {
            btn.disabled = true;
            btn.textContent = "Scanning…";
            try {
                const res = await fetch((window.API_BASE || "api") + "/scan_deans_list.php", { method: "POST" });
                const json = await res.json();
                if (!json.success) {
                    show("error", esc(json.message || "The scan couldn't be completed."));
                    return;
                }
                const extra = [];
                if (json.alreadyListed) extra.push(json.alreadyListed + " already on the list");
                if (json.inTrash) extra.push(json.inTrash + " in the Trash Bin (restore them there to bring them back)");
                const sub = '<span class="scan-result-sub">' +
                    plural(json.qualified, "student meets", "students meet") + " the Dean's List rule for " + esc(json.semester ? json.semester + " " + json.schoolYear : (json.schoolYear || "this semester")) + " grades" +
                    " (GWA 1.50 or better, no subject grade of 2.00 or worse)" + (extra.length ? ": " + extra.join(", ") : "") + ".</span>";
                // Scholarship holders from the Registrar's scholarship list.
                const sc = json.scholarships || {};
                const scNew = (sc.added || 0) + (sc.converted || 0);
                const scSkipped = [];
                if (sc.alreadyListed) scSkipped.push(sc.alreadyListed + " already on the list");
                if (sc.notEnrolled) scSkipped.push(sc.notEnrolled + " not enrolled for " + esc(json.semester || "this semester"));
                if (sc.unknownProgram) scSkipped.push(sc.unknownProgram + " with a program not on the Scholarships page");
                if (sc.inTrash) scSkipped.push(sc.inTrash + " in the Trash Bin");
                const scLine = sc.tableFound === false ? "" :
                    '<span class="scan-result-line">' + (scNew
                        ? "<strong>Found " + plural(scNew, "new scholar", "new scholars") + " with a scholarship program</strong> — added to Scholars" + (sc.recordsAdded ? " and Records (" + sc.recordsAdded + ")" : "") + ": " + (sc.addedNames || []).map(esc).join(", ") + (scNew > (sc.addedNames || []).length ? ", …" : "") + "."
                        : "<strong>No new scholarship holders found.</strong>")
                    + (scSkipped.length ? ' <span class="scan-result-sub">' + plural(sc.found || 0, "student holds", "students hold") + " a scholarship in the Registrar's database: " + scSkipped.join(", ") + ".</span>" : "") + "</span>";

                // Records is where the list is exported for posting.
                const records = (json.recordsAdded ? " " + plural(json.recordsAdded, "approved record was", "approved records were") + " added to Records for exporting." : "")
                    + (json.recordsUpdated ? " " + plural(json.recordsUpdated, "record was", "records were") + " moved on to this semester." : "");
                if (json.added > 0) {
                    const names = (json.addedNames || []).map(esc).join(", ") + (json.added > (json.addedNames || []).length ? ", …" : "");
                    show("", scLine + '<span class="scan-result-line"><strong>Found ' + plural(json.added, "new Dean's Lister", "new Dean's Listers") + "</strong> — added to Scholars: " + names + "." + records + sub + "</span>");
                    if (typeof loadScholarsData === "function") await loadScholarsData();
                } else {
                    show(scNew || json.recordsAdded || json.recordsUpdated ? "" : "none", scLine + "<span class=\"scan-result-line\"><strong>No new Dean's Listers found.</strong>" + records + sub + "</span>");
                    if (scNew && typeof loadScholarsData === "function") await loadScholarsData();
                }
            } catch (e) {
                show("error", "Could not reach the server.");
            } finally {
                btn.disabled = false;
                btn.innerHTML = label;
            }
        });
    });
})();
