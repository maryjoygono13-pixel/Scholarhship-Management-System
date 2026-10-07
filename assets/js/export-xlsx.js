"use strict";
/* ============================================================
   Shared by every export (Scholars, Records, Renewal & Retention):
   sends the rows to api/export_xlsx.php and downloads the Excel
   file it returns — all exports share the same look (centered,
   sized columns, Student IDs kept whole, GWAs with 2 decimals).

     downloadXlsx({ filename: "scholars-2026-10-03", sheet: "Scholars",
                    rows: [[header...], [cells...]], numberCols: [5, 6] });
   ============================================================ */
window.downloadXlsx = async function (opts) {
    try {
        const res = await fetch((window.API_BASE || "api") + "/export_xlsx.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify({ filename: opts.filename, sheet: opts.sheet, rows: opts.rows, numberCols: opts.numberCols || [] }),
        });
        if (!res.ok || !(res.headers.get("Content-Type") || "").includes("spreadsheetml")) {
            let message = "The export couldn't be created.";
            try { message = (await res.json()).message || message; } catch (e) { /* not JSON */ }
            alert(message);
            return;
        }
        const url = URL.createObjectURL(await res.blob());
        const link = document.createElement("a");
        link.href = url;
        link.download = String(opts.filename || "export").replace(/\.(xlsx|csv)$/i, "") + ".xlsx";
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    } catch (e) {
        alert("Could not reach the server to create the export.");
    }
};
