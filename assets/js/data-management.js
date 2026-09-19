"use strict";

document.addEventListener("DOMContentLoaded", () => {
    const API_PATH = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";

    let importedFilesData = [];
    let activePendingDelete = null;

    // File Input Elements
    const gradeFile = document.getElementById("gradeFile");
    const gradeBtn = document.getElementById("gradeBtn");
    const gradeImportBtn = document.getElementById("gradeImportBtn");
    const gradeFileName = document.getElementById("gradeFileName");
    const gradeDeleteBtn = document.getElementById("gradeDeleteBtn");
    const gradeFilesCard = document.getElementById("gradeFilesCard");
    const gradeCardHeader = document.getElementById("gradeCardHeader");

    const enrollmentFile = document.getElementById("enrollmentFile");
    const enrollmentBtn = document.getElementById("enrollmentBtn");
    const enrollmentImportBtn = document.getElementById("enrollmentImportBtn");
    const enrollmentFileName = document.getElementById("enrollmentFileName");
    const enrollmentDeleteBtn = document.getElementById("enrollmentDeleteBtn");
    const enrollmentFilesCard = document.getElementById("enrollmentFilesCard");
    const enrollmentCardHeader = document.getElementById("enrollmentCardHeader");

    // Modal Elements
    const fileDetailsModal = document.getElementById("fileDetailsModal");
    const closeDetailsModalBtn = document.getElementById("closeDetailsModalBtn");
    const closeDetailsBtn = document.getElementById("closeDetailsBtn");

    const deleteImportModal = document.getElementById("deleteImportModal");
    const closeDeleteModalBtn = document.getElementById("closeDeleteModalBtn");
    const cancelDeleteBtn = document.getElementById("cancelDeleteBtn");
    const confirmDeleteBtn = document.getElementById("confirmDeleteBtn");

    // ==========================================
    // 1. COLLAPSIBLE CARD TOGGLES
    // ==========================================
    if (gradeCardHeader && gradeFilesCard) {
        gradeCardHeader.addEventListener("click", () => {
            gradeFilesCard.classList.toggle("collapsed");
        });
    }

    if (enrollmentCardHeader && enrollmentFilesCard) {
        enrollmentCardHeader.addEventListener("click", () => {
            enrollmentFilesCard.classList.toggle("collapsed");
        });
    }

    // ==========================================
    // 2. FILE SELECTION HANDLERS
    // ==========================================
    if (gradeBtn && gradeFile && gradeFileName && gradeDeleteBtn) {
        gradeBtn.addEventListener("click", () => gradeFile.click());
        gradeFile.addEventListener("change", function () {
            if (this.files && this.files.length > 0) {
                gradeFileName.textContent = this.files[0].name;
                gradeDeleteBtn.style.display = "inline-block";
            } else {
                gradeFileName.textContent = "No file selected";
                gradeDeleteBtn.style.display = "none";
            }
        });
        gradeDeleteBtn.addEventListener("click", () => {
            gradeFile.value = "";
            gradeFileName.textContent = "No file selected";
            gradeDeleteBtn.style.display = "none";
        });
    }

    if (enrollmentBtn && enrollmentFile && enrollmentFileName && enrollmentDeleteBtn) {
        enrollmentBtn.addEventListener("click", () => enrollmentFile.click());
        enrollmentFile.addEventListener("change", function () {
            if (this.files && this.files.length > 0) {
                enrollmentFileName.textContent = this.files[0].name;
                enrollmentDeleteBtn.style.display = "inline-block";
            } else {
                enrollmentFileName.textContent = "No file selected";
                enrollmentDeleteBtn.style.display = "none";
            }
        });
        enrollmentDeleteBtn.addEventListener("click", () => {
            enrollmentFile.value = "";
            enrollmentFileName.textContent = "No file selected";
            enrollmentDeleteBtn.style.display = "none";
        });
    }

    // ==========================================
    // 3. IMPORT UPLOAD SUBMISSION
    // ==========================================
    async function handleImportSubmit(fileInput, type) {
        if (!fileInput.files || fileInput.files.length === 0) {
            alert(`Please select an academic or enrollment file to import.`);
            return;
        }

        const formData = new FormData();
        formData.append("file", fileInput.files[0]);
        formData.append("type", type);

        try {
            const res = await fetch(`${API_PATH}/import_data.php`, {
                method: "POST",
                body: formData
            });
            const json = await res.json();
            if (json.success) {
                alert(json.message || "File imported successfully!");
                fileInput.value = "";
                if (type === 'grades') {
                    gradeFileName.textContent = "No file selected";
                    gradeDeleteBtn.style.display = "none";
                    gradeFilesCard.classList.remove("collapsed");
                } else {
                    enrollmentFileName.textContent = "No file selected";
                    enrollmentDeleteBtn.style.display = "none";
                    enrollmentFilesCard.classList.remove("collapsed");
                }
                loadImportedFilesList();
            } else {
                alert(json.message || "Failed to import file.");
            }
        } catch (err) {
            console.error("Import error:", err);
            alert("Error connecting to server during import.");
        }
    }

    if (gradeImportBtn) {
        gradeImportBtn.addEventListener("click", () => handleImportSubmit(gradeFile, "grades"));
    }
    if (enrollmentImportBtn) {
        enrollmentImportBtn.addEventListener("click", () => handleImportSubmit(enrollmentFile, "enrollment"));
    }

    // ==========================================
    // 4. LOAD & RENDER IMPORTED FILES LIST
    // ==========================================
    async function loadImportedFilesList() {
        try {
            const res = await fetch(`${API_PATH}/list_imports.php`);
            const json = await res.json();
            if (json.success && json.data) {
                importedFilesData = json.data;
                renderTables();
            }
        } catch (e) {
            console.error("Failed to load imported files:", e);
        }
    }

    const FILES_PAGE_SIZE = 3;
    let gradeFilesPage = 1;
    let enrollmentFilesPage = 1;

    function renderTables() {
        const gradeFiles = importedFilesData.filter(f => f.fileType === 'grades');
        const enrollmentFiles = importedFilesData.filter(f => f.fileType === 'enrollment');

        renderTableSection("gradeFilesBody", "gradeFilesPagination", gradeFiles, "No grade files imported yet.", gradeFilesPage, (p) => {
            gradeFilesPage = p;
            renderTables();
        });
        renderTableSection("enrollmentFilesBody", "enrollmentFilesPagination", enrollmentFiles, "No enrollment files imported yet.", enrollmentFilesPage, (p) => {
            enrollmentFilesPage = p;
            renderTables();
        });

        if (typeof lucide !== "undefined") {
            lucide.createIcons();
        }
    }

    function renderTableSection(tbodyId, paginationId, fileList, emptyMsg, currentPage, onPageChange) {
        const tbody = document.getElementById(tbodyId);
        if (!tbody) return;

        const paginationEl = document.getElementById(paginationId);

        if (fileList.length === 0) {
            tbody.innerHTML = `<tr><td colspan="3" class="empty-state">${emptyMsg}</td></tr>`;
            if (paginationEl) paginationEl.innerHTML = "";
            return;
        }

        const totalPages = Math.max(1, Math.ceil(fileList.length / FILES_PAGE_SIZE));
        if (currentPage > totalPages) currentPage = totalPages;
        if (currentPage < 1) currentPage = 1;
        const pageItems = fileList.slice((currentPage - 1) * FILES_PAGE_SIZE, currentPage * FILES_PAGE_SIZE);

        tbody.innerHTML = pageItems.map(file => `
            <tr>
                <td>
                    <div class="file-name-cell">
                        <i data-lucide="${file.fileName.endsWith('.csv') ? 'file-text' : 'file-spreadsheet'}"></i>
                        ${file.hasFile
                            ? `<a href="${API_PATH}/download_import.php?id=${file.id}" class="file-name-link" title="Download ${escapeHtml(file.fileName)}">${escapeHtml(file.fileName)}</a>`
                            : `<span title="Original file not available for download">${escapeHtml(file.fileName)}</span>`
                        }
                    </div>
                </td>
                <td class="date-cell">${escapeHtml(file.formattedDate)}</td>
                <td class="text-center">
                    <div class="action-buttons">
                        <button type="button" class="btn-action btn-view" data-action="view" data-id="${file.id}" title="View File Details">
                            <i data-lucide="eye"></i>
                        </button>
                        <button type="button" class="btn-action btn-delete" data-action="delete" data-id="${file.id}" title="Delete File Record">
                            <i data-lucide="trash-2"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join("");

        tbody.querySelectorAll("button[data-action]").forEach(btn => {
            btn.addEventListener("click", (e) => {
                e.stopPropagation();
                const action = btn.dataset.action;
                const id = parseInt(btn.dataset.id, 10);
                const targetFile = importedFilesData.find(f => f.id === id);
                if (!targetFile) return;

                if (action === "view") {
                    showFileDetailsModal(targetFile);
                } else if (action === "delete") {
                    promptDeleteFileModal(targetFile);
                }
            });
        });

        if (paginationEl) {
            const start = (currentPage - 1) * FILES_PAGE_SIZE + 1;
            const end = Math.min(currentPage * FILES_PAGE_SIZE, fileList.length);

            paginationEl.innerHTML =
                `<span>Showing ${start}–${end} of ${fileList.length} entries</span>` +
                `<div class="page-btns">` +
                `<button type="button" data-page="${currentPage - 1}" ${currentPage <= 1 ? "disabled" : ""} aria-label="Previous page"><i data-lucide="chevron-left"></i></button>` +
                `<button type="button" class="active" data-page="${currentPage}" disabled>${currentPage}</button>` +
                `<button type="button" data-page="${currentPage + 1}" ${currentPage >= totalPages ? "disabled" : ""} aria-label="Next page"><i data-lucide="chevron-right"></i></button>` +
                `</div>`;

            paginationEl.querySelectorAll("button[data-page]").forEach(btn => {
                btn.addEventListener("click", () => {
                    const p = parseInt(btn.getAttribute("data-page") || "", 10);
                    if (!isNaN(p) && p >= 1 && p <= totalPages) {
                        onPageChange(p);
                    }
                });
            });
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatBytes(bytes, decimals = 1) {
        if (!bytes || bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    // ==========================================
    // 5. VIEW FILE DETAILS MODAL
    // ==========================================
    function showFileDetailsModal(file) {
        const modalFileName = document.getElementById("modalFileName");
        const modalFileType = document.getElementById("modalFileType");
        const modalFileDate = document.getElementById("modalFileDate");
        const modalRecordsCount = document.getElementById("modalRecordsCount");
        const modalImportedBy = document.getElementById("modalImportedBy");

        if (modalFileName) modalFileName.textContent = file.fileName;
        if (modalFileType) modalFileType.textContent = file.fileType === 'grades' ? 'Academic Grade Records' : 'Enrollment Records';
        if (modalFileDate) modalFileDate.textContent = file.formattedDate;
        if (modalRecordsCount) modalRecordsCount.textContent = `${file.recordsCount} Records (${formatBytes(file.fileSize)})`;
        if (modalImportedBy) modalImportedBy.textContent = file.importedBy || 'Registrar Staff';

        if (fileDetailsModal) fileDetailsModal.classList.add("open");
    }

    function closeFileDetailsModal() {
        if (fileDetailsModal) fileDetailsModal.classList.remove("open");
    }

    if (closeDetailsModalBtn) closeDetailsModalBtn.addEventListener("click", closeFileDetailsModal);
    if (closeDetailsBtn) closeDetailsBtn.addEventListener("click", closeFileDetailsModal);
    if (fileDetailsModal) {
        fileDetailsModal.addEventListener("click", (e) => {
            if (e.target === fileDetailsModal) closeFileDetailsModal();
        });
    }

    // ==========================================
    // 6. DELETE CONFIRMATION MODAL
    // ==========================================
    function promptDeleteFileModal(file) {
        activePendingDelete = file;
        const namePreview = document.getElementById("deleteFileNamePreview");
        const datePreview = document.getElementById("deleteFileDatePreview");
        if (namePreview) namePreview.textContent = file.fileName;
        if (datePreview) datePreview.textContent = `Imported on ${file.formattedDate}`;

        if (deleteImportModal) deleteImportModal.classList.add("open");
    }

    function closeDeleteModal() {
        activePendingDelete = null;
        if (deleteImportModal) deleteImportModal.classList.remove("open");
    }

    if (closeDeleteModalBtn) closeDeleteModalBtn.addEventListener("click", closeDeleteModal);
    if (cancelDeleteBtn) cancelDeleteBtn.addEventListener("click", closeDeleteModal);
    if (deleteImportModal) {
        deleteImportModal.addEventListener("click", (e) => {
            if (e.target === deleteImportModal) closeDeleteModal();
        });
    }

    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener("click", async () => {
            if (!activePendingDelete) return;
            try {
                const res = await fetch(`${API_PATH}/delete_import.php?id=${activePendingDelete.id}`, {
                    method: "POST"
                });
                const json = await res.json();
                if (json.success) {
                    closeDeleteModal();
                    if (json.removedApplicants > 0 || json.removedGrades > 0) {
                        alert(json.message);
                    }
                    loadImportedFilesList();
                } else {
                    alert(json.message || "Failed to delete file record.");
                }
            } catch (err) {
                console.error("Delete error:", err);
                alert("Error connecting to server during delete.");
            }
        });
    }

    // Initialize list load
    loadImportedFilesList();
});
