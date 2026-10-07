"use strict";

document.addEventListener("DOMContentLoaded", () => {
    const tableWrap = document.getElementById("historyTableWrap");
    const paginationWrap = document.getElementById("historyPagination");

    const searchInput = document.getElementById("historySearch");
    const dateFilter = document.getElementById("historyDateFilter");
    const actionFilter = document.getElementById("historyActionFilter");
    const moduleFilter = document.getElementById("historyModuleFilter");
    const clearFiltersBtn = document.getElementById("historyClearFiltersBtn");
    const exportBtn = document.getElementById("historyExportBtn");

    const statTotal = document.getElementById("statTotal");
    const statToday = document.getElementById("statToday");
    const statMostActive = document.getElementById("statMostActive");

    const detailOverlay = document.getElementById("historyDetailOverlay");
    const detailBody = document.getElementById("historyDetailBody");
    const detailCloseBtn = document.getElementById("historyDetailCloseBtn");

    const apiBase = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";

    let currentPage = 1;
    let currentData = [];
    let searchDebounce = null;
    let filtersPopulated = false;

    function actionBadgeClass(action) {
        const a = (action || "").toLowerCase();
        if (a.includes("rejected")) return "action-rejected";
        if (a.includes("approved")) return "action-approved";
        if (a.includes("deleted")) return "action-deleted";
        if (a.includes("created") || a.includes("added")) return "action-created";
        if (a.includes("updated") || a.includes("assignment") || a.includes("renewal")) return "action-updated";
        if (a.includes("login") || a.includes("logout")) return "action-auth";
        if (a.includes("status")) return "action-status";
        return "action-default";
    }

    function esc(str) {
        const d = document.createElement("div");
        d.textContent = str == null ? "" : String(str);
        return d.innerHTML;
    }

    function formatDateTime(value) {
        if (!value) return "";
        // Timestamps are stored in UTC (SQLite CURRENT_TIMESTAMP, no zone marker);
        // tag them so the browser converts to the viewer's local time.
        const iso = value.includes("T") ? value : value.replace(" ", "T") + "Z";
        const d = new Date(iso);
        if (isNaN(d.getTime())) return esc(value);
        return d.toLocaleString(undefined, {
            year: "numeric", month: "short", day: "numeric",
            hour: "numeric", minute: "2-digit"
        });
    }

    function buildParams(extra) {
        const params = new URLSearchParams();
        const search = searchInput ? searchInput.value.trim() : "";
        const date = dateFilter ? dateFilter.value : "";
        const action = actionFilter ? actionFilter.value : "all";
        const module = moduleFilter ? moduleFilter.value : "all";

        if (search) params.set("search", search);
        if (date) params.set("date", date);
        if (action && action !== "all") params.set("action", action);
        if (module && module !== "all") params.set("module", module);

        // Minutes east of UTC, so the server can work out the viewer's "today".
        params.set("tz", String(-new Date().getTimezoneOffset()));

        Object.keys(extra || {}).forEach(k => params.set(k, extra[k]));
        return params;
    }

    function populateFilterOptions(filters) {
        if (filtersPopulated || !filters) return;
        filtersPopulated = true;

        if (actionFilter && Array.isArray(filters.actions)) {
            const current = actionFilter.value;
            filters.actions.forEach(a => {
                const opt = document.createElement("option");
                opt.value = a;
                opt.textContent = a;
                actionFilter.appendChild(opt);
            });
            actionFilter.value = current;
        }

        if (moduleFilter && Array.isArray(filters.modules)) {
            const current = moduleFilter.value;
            filters.modules.forEach(m => {
                const opt = document.createElement("option");
                opt.value = m;
                opt.textContent = m;
                moduleFilter.appendChild(opt);
            });
            moduleFilter.value = current;
        }
    }

    function renderStats(stats) {
        if (!stats) return;
        if (statTotal) statTotal.textContent = Number(stats.total || 0).toLocaleString();
        if (statToday) statToday.textContent = Number(stats.today || 0).toLocaleString();
        if (statMostActive) statMostActive.textContent = stats.mostActiveUser || "—";
    }

    function renderTable(rows) {
        if (!tableWrap) return;

        if (rows.length === 0) {
            tableWrap.innerHTML = '<div class="empty">No activity found for the selected filters.</div>';
            return;
        }

        const body = rows.map((r) => {
            const ticked = selectedIds.has(r.id);
            return (
                '<tr data-id="' + r.id + '"' + (ticked ? ' class="is-selected"' : "") + '>' +
                '<td class="history-select-cell"><input type="checkbox" class="history-select" data-id="' + r.id + '"' + (ticked ? " checked" : "") + ' aria-label="Select entry"></td>' +
                '<td class="font-mono">' + esc(formatDateTime(r.createdAt)) + '</td>' +
                '<td>' + esc(r.userName) + '</td>' +
                '<td><span class="module-pill">' + esc(r.module) + '</span></td>' +
                '<td><span class="action-badge ' + actionBadgeClass(r.action) + '">' + esc(r.action) + '</span></td>' +
                '<td class="desc-cell" title="' + esc(r.description) + '">' + esc(r.description) + '</td>' +
                '<td class="history-actions-cell"><button type="button" class="btn-icon-action delete history-row-delete" data-id="' + r.id + '" title="Delete entry" aria-label="Delete entry"><i data-lucide="trash-2"></i></button></td>' +
                '</tr>'
            );
        }).join("");

        tableWrap.innerHTML =
            '<table class="history-table"><thead><tr>' +
            '<th class="history-select-cell"><input type="checkbox" id="historySelectAll" title="Select all on this page" aria-label="Select all entries on this page"></th>' +
            '<th>Date &amp; Time</th><th>User</th><th>Module</th><th>Action</th><th>Description</th><th class="history-actions-cell"></th>' +
            '</tr></thead><tbody>' + body + '</tbody></table>';

        tableWrap.querySelectorAll("tr[data-id]").forEach((tr) => {
            tr.addEventListener("click", (e) => {
                // Checkbox and trash icon have their own actions.
                if (e.target.closest(".history-select-cell, .history-actions-cell")) return;
                const id = parseInt(tr.getAttribute("data-id"), 10);
                const row = currentData.find(r => r.id === id);
                if (row) openDetailModal(row);
            });
        });

        tableWrap.querySelectorAll("input.history-select").forEach((box) => {
            box.addEventListener("change", () => {
                const id = parseInt(box.getAttribute("data-id"), 10);
                if (box.checked) selectedIds.add(id); else selectedIds.delete(id);
                updateSelectionUi();
            });
        });
        const selectAll = document.getElementById("historySelectAll");
        if (selectAll) {
            selectAll.addEventListener("change", () => {
                tableWrap.querySelectorAll("input.history-select").forEach((box) => {
                    box.checked = selectAll.checked;
                    const id = parseInt(box.getAttribute("data-id"), 10);
                    if (selectAll.checked) selectedIds.add(id); else selectedIds.delete(id);
                });
                updateSelectionUi();
            });
        }
        tableWrap.querySelectorAll(".history-row-delete").forEach((btn) => {
            btn.addEventListener("click", () => openDeleteConfirm([parseInt(btn.getAttribute("data-id"), 10)]));
        });
        updateSelectionUi();
    }

    /* ---------- Delete history entries (one, or several selected) ---------- */
    const selectedIds = new Set();          // kept across pages until deleted or cleared
    const bulkDeleteBtn = document.getElementById("historyBulkDeleteBtn");
    const bulkCount = document.getElementById("historyBulkCount");
    const deleteOverlay = document.getElementById("historyDeleteOverlay");
    const deleteCount = document.getElementById("historyDeleteCount");
    const deletePlural = document.getElementById("historyDeletePlural");
    const deleteConfirmBtn = document.getElementById("historyDeleteConfirmBtn");
    let pendingDelete = [];
    let deleteAllMatching = false;   // true: delete every entry matching the current filters
    let lastMatchingTotal = 0;
    const deleteMessage = document.getElementById("historyDeleteMessage");

    function updateSelectionUi() {
        if (bulkCount) bulkCount.textContent = String(selectedIds.size);
        const countWrap = document.getElementById("historyBulkCountWrap");
        if (countWrap) countWrap.hidden = selectedIds.size === 0;
        if (!tableWrap) return;
        const boxes = Array.from(tableWrap.querySelectorAll("input.history-select"));
        boxes.forEach((b) => { const tr = b.closest("tr"); if (tr) tr.classList.toggle("is-selected", b.checked); });
        const all = document.getElementById("historySelectAll");
        if (all) {
            const ticked = boxes.filter((b) => b.checked).length;
            all.checked = boxes.length > 0 && ticked === boxes.length;
            all.indeterminate = ticked > 0 && ticked < boxes.length;
        }
    }

    function openDeleteConfirm(ids) {
        pendingDelete = ids.filter((id) => id > 0);
        deleteAllMatching = false;
        if (!pendingDelete.length || !deleteOverlay) return;
        if (deleteMessage) deleteMessage.innerHTML = 'Delete <strong id="historyDeleteCount">0</strong> history entr<span id="historyDeletePlural">ies</span>?';
        const deleteCount = document.getElementById("historyDeleteCount");
        const deletePlural = document.getElementById("historyDeletePlural");
        if (deleteCount) deleteCount.textContent = String(pendingDelete.length);
        if (deletePlural) deletePlural.textContent = pendingDelete.length === 1 ? "y" : "ies";
        deleteOverlay.classList.add("open");
        if (typeof lucide !== "undefined") lucide.createIcons();
    }
    function closeDeleteConfirm() {
        if (deleteOverlay) deleteOverlay.classList.remove("open");
        pendingDelete = [];
        deleteAllMatching = false;
    }

    // No entries ticked: delete everything the list currently shows (all pages, current filters).
    function openDeleteAllMatching() {
        if (!deleteOverlay) return;
        if (lastMatchingTotal <= 0) { alert("There are no history entries to delete."); return; }
        const filtered = (searchInput && searchInput.value.trim()) || (dateFilter && dateFilter.value) ||
            (actionFilter && actionFilter.value !== "all") || (moduleFilter && moduleFilter.value !== "all");
        pendingDelete = [];
        deleteAllMatching = true;
        if (deleteMessage) {
            deleteMessage.innerHTML = filtered
                ? "Delete all <strong>" + lastMatchingTotal.toLocaleString() + "</strong> history entr" + (lastMatchingTotal === 1 ? "y" : "ies") + " matching the current filters?"
                : "Delete <strong>all " + lastMatchingTotal.toLocaleString() + "</strong> history entr" + (lastMatchingTotal === 1 ? "y" : "ies") + "? Tick entries first to delete only some of them.";
        }
        deleteOverlay.classList.add("open");
        if (typeof lucide !== "undefined") lucide.createIcons();
    }

    if (bulkDeleteBtn) bulkDeleteBtn.addEventListener("click", () => {
        if (selectedIds.size > 0) openDeleteConfirm(Array.from(selectedIds));
        else openDeleteAllMatching();
    });
    ["historyDeleteCloseBtn", "historyDeleteCancelBtn"].forEach((id) => {
        const el = document.getElementById(id);
        if (el) el.addEventListener("click", closeDeleteConfirm);
    });
    if (deleteConfirmBtn) {
        deleteConfirmBtn.addEventListener("click", async () => {
            if (!pendingDelete.length && !deleteAllMatching) return closeDeleteConfirm();
            const fd = new FormData();
            if (deleteAllMatching) {
                fd.append("scope", "filtered");
                buildParams({}).forEach((value, key) => fd.append(key, value));
            } else {
                pendingDelete.forEach((id) => fd.append("ids[]", String(id)));
            }
            deleteConfirmBtn.disabled = true;
            deleteConfirmBtn.textContent = "Deleting…";
            try {
                const res = await fetch(`${apiBase}/delete_history.php`, { method: "POST", body: fd });
                const json = await res.json();
                if (!json.success) {
                    alert(json.message || "The entries could not be deleted.");
                    return;
                }
                if (deleteAllMatching) selectedIds.clear(); else pendingDelete.forEach((id) => selectedIds.delete(id));
                closeDeleteConfirm();
                currentPage = 1;
                await loadHistory();
            } catch (e) {
                alert("Could not reach the server.");
            } finally {
                deleteConfirmBtn.disabled = false;
                deleteConfirmBtn.textContent = "Delete";
            }
        });
    }

    function renderPagination(pagination) {
        if (!paginationWrap) return;
        if (!pagination || pagination.total === 0) {
            paginationWrap.innerHTML = "";
            return;
        }

        const { page, totalPages, total, limit } = pagination;
        const start = (page - 1) * limit + 1;
        const end = Math.min(page * limit, total);

        const buttons = '<button type="button" class="active" data-page="' + page + '" disabled>' + page + '</button>';

        paginationWrap.innerHTML =
            '<span>Showing ' + start + '–' + end + ' of ' + total + ' entries</span>' +
            '<div class="page-btns">' +
            '<button type="button" data-page="' + (page - 1) + '" ' + (page <= 1 ? "disabled" : "") + '>Prev</button>' +
            buttons +
            '<button type="button" data-page="' + (page + 1) + '" ' + (page >= totalPages ? "disabled" : "") + '>Next</button>' +
            '</div>';

        paginationWrap.querySelectorAll("button[data-page]").forEach((btn) => {
            btn.addEventListener("click", () => {
                const p = parseInt(btn.getAttribute("data-page"), 10);
                if (!isNaN(p) && p >= 1 && p <= totalPages) {
                    currentPage = p;
                    loadHistory();
                }
            });
        });
    }

    function openDetailModal(row) {
        if (!detailOverlay || !detailBody) return;
        detailBody.innerHTML =
            '<div class="view-detail-grid">' +
            '<div class="detail-item full-width"><span class="detail-label">Date &amp; Time</span><span class="detail-value font-mono">' + esc(formatDateTime(row.createdAt)) + '</span></div>' +
            '<div class="detail-item"><span class="detail-label">User</span><span class="detail-value highlight">' + esc(row.userName) + '</span></div>' +
            '<div class="detail-item"><span class="detail-label">Module</span><span class="detail-value">' + esc(row.module) + '</span></div>' +
            '<div class="detail-item full-width"><span class="detail-label">Action</span><span class="action-badge ' + actionBadgeClass(row.action) + '">' + esc(row.action) + '</span></div>' +
            '<div class="detail-item full-width"><span class="detail-label">Description</span><span class="detail-value remarks">' + esc(row.description || "No description recorded.") + '</span></div>' +
            (row.recordId ? '<div class="detail-item"><span class="detail-label">Record ID</span><span class="detail-value font-mono">#' + esc(row.recordId) + '</span></div>' : '') +
            '</div>';
        detailOverlay.classList.add("open");
    }

    function closeDetailModal() {
        if (detailOverlay) detailOverlay.classList.remove("open");
    }

    async function loadHistory() {
        if (!tableWrap) return;
        tableWrap.innerHTML = '<div class="empty">Loading activity…</div>';

        try {
            const params = buildParams({ page: currentPage, limit: 10 });
            const res = await fetch(`${apiBase}/history.php?${params.toString()}`);
            const json = await res.json();

            if (!json.success) {
                tableWrap.innerHTML = '<div class="empty">' + esc(json.message || "Failed to load activity history.") + '</div>';
                if (paginationWrap) paginationWrap.innerHTML = "";
                return;
            }

            currentData = json.data || [];
            populateFilterOptions(json.filters);
            renderStats(json.stats);
            renderTable(currentData);
            renderPagination(json.pagination);
            lastMatchingTotal = (json.pagination && Number(json.pagination.total)) || currentData.length;

            if (typeof lucide !== "undefined") lucide.createIcons();
        } catch (e) {
            tableWrap.innerHTML = '<div class="empty">Could not load activity history. Check your connection.</div>';
            if (paginationWrap) paginationWrap.innerHTML = "";
        }
    }

    function resetToFirstPageAndLoad() {
        currentPage = 1;
        loadHistory();
    }

    if (searchInput) {
        searchInput.addEventListener("input", () => {
            clearTimeout(searchDebounce);
            searchDebounce = setTimeout(resetToFirstPageAndLoad, 350);
        });
    }
    if (dateFilter) dateFilter.addEventListener("change", resetToFirstPageAndLoad);
    if (actionFilter) actionFilter.addEventListener("change", resetToFirstPageAndLoad);
    if (moduleFilter) moduleFilter.addEventListener("change", resetToFirstPageAndLoad);

    if (clearFiltersBtn) {
        clearFiltersBtn.addEventListener("click", () => {
            if (searchInput) searchInput.value = "";
            if (dateFilter) dateFilter.value = "";
            if (actionFilter) actionFilter.value = "all";
            if (moduleFilter) moduleFilter.value = "all";
            resetToFirstPageAndLoad();
        });
    }

    if (exportBtn) {
        exportBtn.addEventListener("click", () => {
            const params = buildParams({ export: "csv" });
            window.location.href = `${apiBase}/history.php?${params.toString()}`;
        });
    }

    if (detailCloseBtn) detailCloseBtn.addEventListener("click", closeDetailModal);
    if (detailOverlay) {
        detailOverlay.addEventListener("click", (e) => {
            if (e.target === detailOverlay) closeDetailModal();
        });
    }

    loadHistory();
});
