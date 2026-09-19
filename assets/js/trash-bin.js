"use strict";

let loadedDeletedItems = [];
let restoringItemId = null;
let purgingItemId = null;
const TRASH_PAGE_SIZE = 10;
let trashCurrentPage = 1;

function getTrashEl(id) {
    return document.getElementById(id);
}

async function loadTrashData() {
    try {
        const typeFilter = getTrashEl("trashTypeFilter");
        const typeVal = typeFilter ? typeFilter.value : "all";

        const apiBase = window.API_BASE || "api";
        const url = typeVal === "all" ? `${apiBase}/list_deleted_items.php` : `${apiBase}/list_deleted_items.php?type=${encodeURIComponent(typeVal)}`;

        const res = await fetch(url);
        const json = await res.json();
        loadedDeletedItems = json.data || [];

        renderTrashTable();
    } catch (err) {
        console.error("Error loading trash data:", err);
    }
}

function renderTrashTable() {
    const tbody = getTrashEl("trashTableBody");
    const emptyState = getTrashEl("trashEmptyState");
    if (!tbody) return;

    tbody.innerHTML = "";

    if (!loadedDeletedItems || loadedDeletedItems.length === 0) {
        if (emptyState) emptyState.style.display = "block";
        renderTrashPagination(0);
        return;
    }

    if (emptyState) emptyState.style.display = "none";

    const totalPages = Math.max(1, Math.ceil(loadedDeletedItems.length / TRASH_PAGE_SIZE));
    if (trashCurrentPage > totalPages) trashCurrentPage = totalPages;
    if (trashCurrentPage < 1) trashCurrentPage = 1;
    const pageItems = loadedDeletedItems.slice((trashCurrentPage - 1) * TRASH_PAGE_SIZE, trashCurrentPage * TRASH_PAGE_SIZE);

    pageItems.forEach((item) => {
        const tr = document.createElement("tr");
        const typeKey = (item.item_type || "").toLowerCase();
        const badgeClass = `item-type-badge item-type-${typeKey}`;

        tr.innerHTML = `
            <td><span class="${badgeClass}">${escapeHtml(item.item_type)}</span></td>
            <td><strong>${escapeHtml(item.title)}</strong></td>
            <td><span class="font-mono" style="font-size:12.5px; color:#64748b;">${escapeHtml(item.deleted_at || '-')}</span></td>
            <td><span style="font-size:13px; color:#475569;">${escapeHtml(item.deleted_by || 'Registrar Staff')}</span></td>
            <td style="text-align:right;">
                <button type="button" class="btn-restore" onclick="confirmRestoreItem(event, ${item.id})">
                    <i data-lucide="rotate-ccw"></i> Restore / Revert
                </button>
                <button type="button" class="btn-purge" onclick="confirmPurgeItem(event, ${item.id})" style="margin-left: 6px;">
                    <i data-lucide="trash-2"></i> Delete
                </button>
            </td>
        `;

        tbody.appendChild(tr);
    });

    renderTrashPagination(loadedDeletedItems.length);

    if (typeof lucide !== "undefined") {
        lucide.createIcons();
    }
}

function renderTrashPagination(total) {
    const wrap = getTrashEl("trashPagination");
    if (!wrap) return;

    const totalPages = Math.max(1, Math.ceil(total / TRASH_PAGE_SIZE));
    if (trashCurrentPage > totalPages) trashCurrentPage = totalPages;
    if (trashCurrentPage < 1) trashCurrentPage = 1;

    if (total === 0) {
        wrap.innerHTML = "";
        return;
    }

    const start = (trashCurrentPage - 1) * TRASH_PAGE_SIZE + 1;
    const end = Math.min(trashCurrentPage * TRASH_PAGE_SIZE, total);

    const buttons = `<button type="button" class="active" data-page="${trashCurrentPage}" disabled>${trashCurrentPage}</button>`;

    wrap.innerHTML =
        `<span>Showing ${start}–${end} of ${total} entries</span>` +
        `<div class="page-btns">` +
        `<button type="button" data-page="${trashCurrentPage - 1}" ${trashCurrentPage <= 1 ? "disabled" : ""}>Prev</button>` +
        buttons +
        `<button type="button" data-page="${trashCurrentPage + 1}" ${trashCurrentPage >= totalPages ? "disabled" : ""}>Next</button>` +
        `</div>`;

    wrap.querySelectorAll("button[data-page]").forEach((btn) => {
        btn.addEventListener("click", () => {
            const p = parseInt(btn.getAttribute("data-page") || "", 10);
            if (!isNaN(p) && p >= 1 && p <= totalPages) {
                trashCurrentPage = p;
                renderTrashTable();
            }
        });
    });
}

window.confirmRestoreItem = function (event, id) {
    if (event && typeof event.stopPropagation === "function") event.stopPropagation();

    const targetId = Number(id);
    if (!targetId) return;

    restoringItemId = targetId;
    const item = loadedDeletedItems.find(i => Number(i.id) === targetId);

    const targetTitle = getTrashEl("restoreTargetTitle");
    const overlay = getTrashEl("restoreConfirmOverlay");

    if (targetTitle) {
        targetTitle.textContent = item ? item.title : `Item #${targetId}`;
    }

    if (overlay) {
        overlay.classList.add("open");
    }
};

function closeRestoreModal() {
    const overlay = getTrashEl("restoreConfirmOverlay");
    if (overlay) overlay.classList.remove("open");
    restoringItemId = null;
}

window.confirmPurgeItem = function (event, id) {
    if (event && typeof event.stopPropagation === "function") event.stopPropagation();

    purgingItemId = id;
    const targetTitle = getTrashEl("purgeTargetTitle");
    const overlay = getTrashEl("purgeConfirmOverlay");

    if (id === "all") {
        if (targetTitle) targetTitle.textContent = "all items in the Trash Bin";
    } else {
        const item = loadedDeletedItems.find(i => Number(i.id) === Number(id));
        if (targetTitle) targetTitle.textContent = item ? item.title : `Item #${id}`;
    }

    if (overlay) {
        overlay.classList.add("open");
    }
};

function closePurgeModal() {
    const overlay = getTrashEl("purgeConfirmOverlay");
    if (overlay) overlay.classList.remove("open");
    purgingItemId = null;
}

function initTrashPage() {
    const typeFilter = getTrashEl("trashTypeFilter");
    if (typeFilter) typeFilter.addEventListener("change", () => { trashCurrentPage = 1; loadTrashData(); });

    const emptyTrashBtn = getTrashEl("emptyTrashBtn");
    if (emptyTrashBtn) {
        emptyTrashBtn.addEventListener("click", (e) => {
            if (loadedDeletedItems.length === 0) return;
            window.confirmPurgeItem(e, "all");
        });
    }

    // Restore Overlay Controls
    const restoreConfirmBtn = getTrashEl("restoreConfirmBtn");
    if (restoreConfirmBtn) {
        restoreConfirmBtn.addEventListener("click", async () => {
            if (!restoringItemId) return;
            restoreConfirmBtn.disabled = true;
            restoreConfirmBtn.textContent = "Restoring...";
            try {
                const apiBase = window.API_BASE || "api";
                const formData = new FormData();
                formData.append("id", String(restoringItemId));

                const res = await fetch(`${apiBase}/restore_deleted_item.php`, { method: "POST", body: formData });
                const json = await res.json();

                closeRestoreModal();
                if (json.success) {
                    await loadTrashData();
                    if (typeof window.updateNavCounts === "function") {
                        window.updateNavCounts();
                    }
                } else {
                    alert(json.message || "Failed to restore item.");
                }
            } catch (err) {
                closeRestoreModal();
                alert("Server error when restoring item.");
            } finally {
                restoreConfirmBtn.disabled = false;
                restoreConfirmBtn.textContent = "Restore / Revert";
            }
        });
    }

    const restoreCloseBtn = getTrashEl("restoreCloseBtn");
    if (restoreCloseBtn) restoreCloseBtn.addEventListener("click", closeRestoreModal);

    const restoreCancelBtn = getTrashEl("restoreCancelBtn");
    if (restoreCancelBtn) restoreCancelBtn.addEventListener("click", closeRestoreModal);

    const restoreConfirmOverlay = getTrashEl("restoreConfirmOverlay");
    if (restoreConfirmOverlay) {
        restoreConfirmOverlay.addEventListener("click", (e) => {
            if (e.target === restoreConfirmOverlay) closeRestoreModal();
        });
    }

    // Purge Overlay Controls
    const purgeConfirmBtn = getTrashEl("purgeConfirmBtn");
    if (purgeConfirmBtn) {
        purgeConfirmBtn.addEventListener("click", async () => {
            if (!purgingItemId) return;
            purgeConfirmBtn.disabled = true;
            purgeConfirmBtn.textContent = "Deleting...";
            try {
                const apiBase = window.API_BASE || "api";
                const formData = new FormData();
                formData.append("id", String(purgingItemId));

                const res = await fetch(`${apiBase}/purge_deleted_item.php`, { method: "POST", body: formData });
                const json = await res.json();

                closePurgeModal();
                if (json.success) {
                    await loadTrashData();
                    if (typeof window.updateNavCounts === "function") {
                        window.updateNavCounts();
                    }
                } else {
                    alert(json.message || "Failed to delete item.");
                }
            } catch (err) {
                closePurgeModal();
                alert("Server error when deleting item.");
            } finally {
                purgeConfirmBtn.disabled = false;
                purgeConfirmBtn.textContent = "Permanently Delete";
            }
        });
    }

    const purgeCloseBtn = getTrashEl("purgeCloseBtn");
    if (purgeCloseBtn) purgeCloseBtn.addEventListener("click", closePurgeModal);

    const purgeCancelBtn = getTrashEl("purgeCancelBtn");
    if (purgeCancelBtn) purgeCancelBtn.addEventListener("click", closePurgeModal);

    const purgeConfirmOverlay = getTrashEl("purgeConfirmOverlay");
    if (purgeConfirmOverlay) {
        purgeConfirmOverlay.addEventListener("click", (e) => {
            if (e.target === purgeConfirmOverlay) closePurgeModal();
        });
    }

    loadTrashData();
}

function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = str ?? "";
    return div.innerHTML;
}

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initTrashPage);
} else {
    initTrashPage();
}
