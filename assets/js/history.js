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
        const iso = value.includes("T") ? value : value.replace(" ", "T");
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
            return (
                '<tr data-id="' + r.id + '">' +
                '<td class="font-mono">' + esc(formatDateTime(r.createdAt)) + '</td>' +
                '<td>' + esc(r.userName) + '</td>' +
                '<td><span class="module-pill">' + esc(r.module) + '</span></td>' +
                '<td><span class="action-badge ' + actionBadgeClass(r.action) + '">' + esc(r.action) + '</span></td>' +
                '<td class="desc-cell" title="' + esc(r.description) + '">' + esc(r.description) + '</td>' +
                '</tr>'
            );
        }).join("");

        tableWrap.innerHTML =
            '<table class="history-table"><thead><tr>' +
            '<th>Date &amp; Time</th><th>User</th><th>Module</th><th>Action</th><th>Description</th>' +
            '</tr></thead><tbody>' + body + '</tbody></table>';

        tableWrap.querySelectorAll("tr[data-id]").forEach((tr) => {
            tr.addEventListener("click", () => {
                const id = parseInt(tr.getAttribute("data-id"), 10);
                const row = currentData.find(r => r.id === id);
                if (row) openDetailModal(row);
            });
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

        let buttons = "";
        const windowSize = 5;
        let startPage = Math.max(1, page - Math.floor(windowSize / 2));
        let endPage = Math.min(totalPages, startPage + windowSize - 1);
        startPage = Math.max(1, endPage - windowSize + 1);

        for (let p = startPage; p <= endPage; p++) {
            buttons += '<button type="button" class="' + (p === page ? "active" : "") + '" data-page="' + p + '">' + p + '</button>';
        }

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
            const params = buildParams({ page: currentPage, limit: 20 });
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
