// ===========================
// Records Page
// ===========================

const searchInput = document.getElementById("searchInput");
const scholarshipFilter = document.getElementById("scholarshipFilter");
const statusFilter = document.getElementById("statusFilter");

const rows = document.querySelectorAll(".records-table tbody tr");

function filterTable() {

    const search = searchInput.value.toLowerCase();
    const scholarship = scholarshipFilter.value.toLowerCase();
    const status = statusFilter.value.toLowerCase();

    rows.forEach(row => {

        const studentId = row.children[1].textContent.toLowerCase();
        const name = row.children[2].textContent.toLowerCase();
        const scholar = row.children[3].textContent.toLowerCase();
        const stat = row.children[4].textContent.toLowerCase();

        const matchSearch =
            studentId.includes(search) ||
            name.includes(search);

        const matchScholar =
            scholarship === "" ||
            scholar === scholarship;

        const matchStatus =
            status === "" ||
            stat === status;

        if (matchSearch && matchScholar && matchStatus) {
            row.style.display = "";
        } else {
            row.style.display = "none";
        }

    });

}

searchInput.addEventListener("keyup", filterTable);
scholarshipFilter.addEventListener("change", filterTable);
statusFilter.addEventListener("change", filterTable);


// ====================================
// Record Selection
// ====================================

const tableRows = document.querySelectorAll(".records-table tbody tr");

tableRows.forEach(row => {

    const btn = row.querySelector(".view-btn");

    if (btn) {

        btn.addEventListener("click", () => {

            tableRows.forEach(r => r.classList.remove("selected"));

            row.classList.add("selected");

        });

    }

});


// ====================================
// Tabs
// ====================================

const tabs = document.querySelectorAll(".tab");
const tabContents = document.querySelectorAll(".tab-content");

tabs.forEach(tab => {

    tab.addEventListener("click", () => {

        tabs.forEach(t => t.classList.remove("active"));
        tab.classList.add("active");

        const target = tab.dataset.tab;

        tabContents.forEach(content => {

            if (content.id === target) {
                content.style.display = "block";
            } else {
                content.style.display = "none";
            }

        });

    });

});


// ====================================
// Close Details Panel
// ====================================

const closePanel = document.querySelector(".close-panel");
const detailsPanel = document.querySelector(".record-details");

if (closePanel) {

    closePanel.addEventListener("click", () => {

        detailsPanel.style.display = "none";

    });

}


// ====================================
// Open Details Panel
// ====================================

const viewButtons = document.querySelectorAll(".view-btn");

viewButtons.forEach(btn => {

    btn.addEventListener("click", () => {

        detailsPanel.style.display = "block";

    });

});