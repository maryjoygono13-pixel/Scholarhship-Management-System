"use strict";
/* ============================================================
   Multi-select delete, shared by Applicants, Evaluation, Scholars
   and Records. Each page renders a checkbox per row
   (<td class="row-select-cell"><input class="row-select" data-id>)
   plus a header checkbox, and calls:

     setupBulkDelete({
       host:      "#bulkDeleteHost",       // where the trash icon goes
       rows:      "#tableBody",            // element holding the rows (re-rendered freely)
       selectAll: "#selectAllX",           // header checkbox (may be re-rendered too)
       endpoint:  "delete_x.php",          // POST ids[]=…, moves them to the Trash Bin
       noun:      ["applicant", "applicants"],
       label:     (tr, id) => "Name (ID)", // shown in the confirmation list
       reload:    async () => {…},         // refresh the page's list afterwards
     });

   Ticks survive re-renders, filters and page changes; the trash
   icon (with a count badge) appears once something is ticked.
   ============================================================ */
(function () {
    const TRASH_SVG = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>';
    const X_SVG = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
    const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));

    window.setupBulkDelete = function (cfg) {
        const host = document.querySelector(cfg.host);
        const rowsEl = document.querySelector(cfg.rows);
        if (!host || !rowsEl) return null;
        const [one, many] = cfg.noun || ["item", "items"];
        const selected = new Map();   // id -> label (kept while the row is on another page)

        // Trash icon with a count badge.
        const btn = document.createElement("button");
        btn.type = "button";
        btn.className = "bulk-delete-icon";
        btn.hidden = true;
        btn.innerHTML = TRASH_SVG + '<span class="bulk-delete-badge">0</span>';
        host.appendChild(btn);
        const badge = btn.querySelector(".bulk-delete-badge");

        // Confirmation window.
        const overlay = document.createElement("div");
        overlay.className = "custom-modal-overlay bulk-delete-overlay";
        overlay.innerHTML =
            '<div class="custom-modal-card" style="max-width:460px;">' +
            '<div class="custom-modal-header"><div><h3>Delete selected ' + esc(many) + "</h3><p>They'll be moved to the Trash Bin and can be restored anytime.</p></div>" +
            '<button type="button" class="custom-modal-close" data-bulk-close aria-label="Close">' + X_SVG + "</button></div>" +
            '<div class="custom-modal-body"><p style="margin:0 0 10px; font-size:14px;" data-bulk-question></p><ul class="bulk-delete-list" data-bulk-list></ul></div>' +
            '<div class="custom-modal-footer"><button type="button" class="btn-secondary" data-bulk-close>Cancel</button>' +
            '<button type="button" class="btn-danger" data-bulk-confirm>Delete</button></div></div>';
        document.body.appendChild(overlay);
        const confirmBtn = overlay.querySelector("[data-bulk-confirm]");
        const close = () => overlay.classList.remove("open");
        overlay.querySelectorAll("[data-bulk-close]").forEach((el) => el.addEventListener("click", close));
        overlay.addEventListener("click", (e) => { if (e.target === overlay) close(); });

        const boxes = () => Array.from(rowsEl.querySelectorAll("input.row-select"));
        const labelOf = (box) => {
            const tr = box.closest("tr");
            try { return (cfg.label && tr && cfg.label(tr, box.dataset.id)) || one + " #" + box.dataset.id; } catch (e) { return one + " #" + box.dataset.id; }
        };

        function refresh() {
            const n = selected.size;
            btn.hidden = n === 0;
            badge.textContent = String(n);
            btn.title = "Delete " + n + " selected " + (n === 1 ? one : many);
            btn.setAttribute("aria-label", btn.title);
            const visible = boxes();
            visible.forEach((b) => {
                b.checked = selected.has(b.dataset.id);
                const tr = b.closest("tr");
                if (tr) tr.classList.toggle("is-selected", b.checked);
            });
            const all = document.querySelector(cfg.selectAll);
            if (all) {
                const ticked = visible.filter((b) => b.checked).length;
                all.checked = visible.length > 0 && ticked === visible.length;
                all.indeterminate = ticked > 0 && ticked < visible.length;
            }
        }

        // Re-applies ticks whenever the page re-renders its rows.
        new MutationObserver(refresh).observe(rowsEl, { childList: true, subtree: true });

        document.addEventListener("change", (e) => {
            const t = e.target;
            if (!(t instanceof HTMLInputElement)) return;
            if (t.matches("input.row-select") && rowsEl.contains(t)) {
                if (t.checked) selected.set(t.dataset.id, labelOf(t)); else selected.delete(t.dataset.id);
                refresh();
            } else if (cfg.selectAll && t.matches(cfg.selectAll)) {
                boxes().forEach((b) => { if (t.checked) selected.set(b.dataset.id, labelOf(b)); else selected.delete(b.dataset.id); });
                refresh();
            }
        });
        // A click anywhere in the checkbox cell toggles it (and never opens the row).
        rowsEl.addEventListener("click", (e) => {
            const cell = e.target.closest && e.target.closest(".row-select-cell");
            if (!cell) return;
            e.stopPropagation();
            if (e.target.matches("input")) return;
            const box = cell.querySelector("input.row-select");
            if (box) { box.checked = !box.checked; box.dispatchEvent(new Event("change", { bubbles: true })); }
        }, true);

        btn.addEventListener("click", () => {
            const ids = Array.from(selected.keys());
            if (!ids.length) return;
            overlay.querySelector("[data-bulk-question]").innerHTML = "Move <strong>" + ids.length + "</strong> " + esc(ids.length === 1 ? one : many) + " to the Trash Bin?";
            overlay.querySelector("[data-bulk-list]").innerHTML =
                ids.slice(0, 8).map((id) => "<li>" + esc(selected.get(id)) + "</li>").join("") +
                (ids.length > 8 ? "<li>…and " + (ids.length - 8) + " more</li>" : "");
            overlay.classList.add("open");
        });

        confirmBtn.addEventListener("click", async () => {
            const ids = Array.from(selected.keys());
            if (!ids.length) return close();
            const fd = new FormData();
            ids.forEach((id) => fd.append("ids[]", id));
            confirmBtn.disabled = true;
            confirmBtn.textContent = "Deleting…";
            try {
                const res = await fetch((window.API_BASE || "api") + "/" + cfg.endpoint, { method: "POST", body: fd });
                const json = await res.json();
                if (!json.success) {
                    alert(json.message || "The selected " + many + " could not be deleted.");
                    return;
                }
                selected.clear();
                close();
                if (typeof cfg.reload === "function") await cfg.reload(); else location.reload();
                refresh();
            } catch (e) {
                alert("Could not reach the server.");
            } finally {
                confirmBtn.disabled = false;
                confirmBtn.textContent = "Delete";
            }
        });

        refresh();
        return { refresh, clear: () => { selected.clear(); refresh(); } };
    };
})();
