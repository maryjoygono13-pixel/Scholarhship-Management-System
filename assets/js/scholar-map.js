"use strict";

let scholarMarkers = [];

document.addEventListener("DOMContentLoaded", () => {
    const mapElement = document.getElementById("scholarMap");
    if (!mapElement) return;

    // Initialize Leaflet Map centered around Leyte / Southern Leyte
    const map = L.map("scholarMap").setView([10.2500, 124.9000], 10);
    window.scholarMap = map;

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        attribution: "&copy; OpenStreetMap contributors"
    }).addTo(map);

    loadStudentLocations(map);
    setupDepartmentFilter();
    setupLegendToggle();
});

/* =========================================================
   LEGEND OPEN / CLOSE TOGGLE
========================================================= */

function setupLegendToggle() {
    const legend = document.getElementById("mapLegend");
    const toggleBtn = document.getElementById("mapLegendToggle");
    if (!legend || !toggleBtn) return;

    const STORAGE_KEY = "scholarMapLegendCollapsed";
    let collapsed = false;
    try {
        collapsed = localStorage.getItem(STORAGE_KEY) === "1";
    } catch (e) {
        collapsed = false;
    }

    const applyState = (isCollapsed) => {
        legend.classList.toggle("collapsed", isCollapsed);
        toggleBtn.setAttribute("aria-expanded", String(!isCollapsed));
    };

    applyState(collapsed);

    toggleBtn.addEventListener("click", () => {
        collapsed = !legend.classList.contains("collapsed");
        applyState(collapsed);
        try {
            localStorage.setItem(STORAGE_KEY, collapsed ? "1" : "0");
        } catch (e) {
            // Ignore storage errors (e.g. private browsing).
        }
    });
}

// Program & Department Color Palette
const departmentColors = {
    "Nursing": "#ec4899",
    "Information Technology": "#2563eb",
    "Accountancy": "#7c3aed",
    "Business Administration": "#f59e0b",
    "Political Science": "#84cc16",
    "Elementary Education": "#16a34a",
    "Secondary Education": "#06b6d4",

};


function normalizeDepartment(deptStr) {
    const d = (deptStr || "").toLowerCase();

    if (d.includes("nursing")) return "Nursing";

    if (
        d.includes("information technology") ||
        d.includes("bsit") ||
        d.includes("computer")
    ) {
        return "Information Technology";
    }

    if (d.includes("accountancy") || d.includes("accounting")) {
        return "Accountancy";
    }

    if (d.includes("business")) {
        return "Business Administration";
    }
    if (
        d.includes("political")
    ) {
        return "Political Science";
    }
    if (
        d.includes("elementary")
    ) {
        return "Elementary Education";
    }
    if (
        d.includes("secondary") ||
        d.includes("education")
    ) {
        return "Secondary Education";
    }
    if (
        d.includes("public") ||
        d.includes("administration")
    ){
        return "Public Administration";
    }   
    return "Other";
}


/* =========================================================
   GET A SLIGHTLY DIFFERENT POSITION FOR DUPLICATE LOCATIONS
========================================================= */

function getDisplayPosition(student, locationCounts) {
    const latitude = Number(student.latitude);
    const longitude = Number(student.longitude);

    // Create a unique key for the exact coordinates
    const key = `${latitude.toFixed(6)},${longitude.toFixed(6)}`;

    if (!locationCounts[key]) {
        locationCounts[key] = 0;
    }

    const index = locationCounts[key];
    locationCounts[key]++;

    // First student stays at the real location
    if (index === 0) {
        return [latitude, longitude];
    }

    /*
        Students with the same coordinates are moved slightly
        around the original point.

        This is ONLY for displaying them on the map.
        Their real latitude/longitude in the database is unchanged.
    */

    const offset = 0.00012;

    // Arrange duplicate markers around the original location
    const angle = (index - 1) * (Math.PI / 4);

    const newLatitude =
        latitude + Math.sin(angle) * offset;

    const newLongitude =
        longitude + Math.cos(angle) * offset;

    return [newLatitude, newLongitude];
}


/* =========================================================
   LOAD STUDENT LOCATIONS
========================================================= */

async function loadStudentLocations(map) {
    try {
        const apiBase = window.API_BASE || "api";

        const response = await fetch(
            `${apiBase}/scholar-map.php`
        );

        if (!response.ok) {
            throw new Error("Failed to load student location data.");
        }

        const result = await response.json();

        if (
            !result.success ||
            !Array.isArray(result.data)
        ) {
            throw new Error("Invalid response format.");
        }

        // Clear existing markers
        scholarMarkers.forEach(item => {
            if (map.hasLayer(item.marker)) {
                map.removeLayer(item.marker);
            }
        });

        scholarMarkers = [];

        /*
            Keeps track of students who have exactly
            the same coordinates.
        */
        const locationCounts = {};

        result.data.forEach((student) => {

            if (
                student.latitude === null ||
                student.longitude === null ||
                isNaN(Number(student.latitude)) ||
                isNaN(Number(student.longitude))
            ) {
                return;
            }

            const deptKey = normalizeDepartment(
                student.department
            );

            const color =
                departmentColors[deptKey] || "#2ea263";

            /*
                Get a display position.

                IMPORTANT:
                This does NOT change the student's
                actual database coordinates.
            */
            const displayPosition = getDisplayPosition(
                student,
                locationCounts
            );

            // Circle Marker
            const marker = L.circleMarker(
                displayPosition,
                {
                    radius: 10,
                    fillColor: color,
                    color: "#ffffff",
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.9
                }
            ).addTo(map);

            const isMaintained =
                Number(student.gwa) <= 1.50;

            const statusLabel = isMaintained
                ? "Active (≤ 1.50 GWA)"
                : "Removed (Below 1.50)";

            const statusColor = isMaintained
                ? "#16a34a"
                : "#dc2626";


            /* =================================================
               POPUP
            ================================================= */

            marker.bindPopup(`
                <div style="
                    font-family: 'DM Sans', sans-serif;
                    padding: 4px;
                ">

                    <h3 style="
                        margin: 0 0 6px 0;
                        font-size: 15px;
                        color: #134e2a;
                    ">
                        ${escapeHtml(student.name)}
                    </h3>

                    <div style="
                        font-size: 12.5px;
                        color: #334155;
                        line-height: 1.6;
                    ">

                        <div>
                            <strong>Student ID:</strong>
                            <span style="font-family: monospace;">
                                ${escapeHtml(student.studentId)}
                            </span>
                        </div>

                        <div>
                            <strong>Department:</strong>
                            <span style="
                                color: ${color};
                                font-weight:700;
                            ">
                                ${escapeHtml(deptKey)}
                            </span>
                        </div>

                        <div>
                            <strong>Year Level:</strong>
                            Year ${escapeHtml(
                                String(student.yearLevel || 1)
                            )}
                        </div>

                        <div>
                            <strong>Current GWA:</strong>
                            <span style="
                                font-family: monospace;
                                font-weight:700;
                            ">
                                ${Number(
                                    student.gwa || 1.50
                                ).toFixed(2)}
                            </span>
                        </div>

                        <div>
                            <strong>Status:</strong>
                            <span style="
                                color: ${statusColor};
                                font-weight: 700;
                            ">
                                ${escapeHtml(statusLabel)}
                            </span>
                        </div>

                        <div style="margin-top: 4px;">
                            <strong>Location:</strong>
                            ${escapeHtml(student.address)}
                        </div>

                    </div>
                </div>
            `);


            /* =================================================
               TOOLTIP
            ================================================= */

            marker.bindTooltip(
                `<strong>${escapeHtml(student.name)}</strong> (${deptKey})`,
                {
                    direction: "top",
                    sticky: true
                }
            );


            scholarMarkers.push({
                marker: marker,
                department: deptKey,
                data: student
            });
        });


        /* =====================================================
           AUTO ZOOM
        ===================================================== */

        if (scholarMarkers.length > 0) {

            const bounds = L.latLngBounds(
                scholarMarkers.map(
                    item => item.marker.getLatLng()
                )
            );

            map.fitBounds(
                bounds,
                {
                    padding: [50, 50]
                }
            );
        }

    } catch (err) {
        console.error(
            "Map location loading error:",
            err
        );
    }
}


/* =========================================================
   DEPARTMENT FILTER
========================================================= */

function setupDepartmentFilter() {

    const filterEl =
        document.getElementById("programFilter");

    if (!filterEl) return;

    filterEl.addEventListener("change", () => {

        const selectedDept = filterEl.value;
        const map = window.scholarMap;

        if (!map) return;

        const visibleLatLngs = [];

        scholarMarkers.forEach((item) => {

            const matches =
                selectedDept === "all" ||
                item.department === selectedDept;

            if (matches) {

                if (!map.hasLayer(item.marker)) {
                    item.marker.addTo(map);
                }

                visibleLatLngs.push(
                    item.marker.getLatLng()
                );

            } else {

                if (map.hasLayer(item.marker)) {
                    map.removeLayer(item.marker);
                }

            }
        });


        // Fit map to filtered markers
        if (visibleLatLngs.length > 0) {

            const bounds =
                L.latLngBounds(visibleLatLngs);

            map.fitBounds(
                bounds,
                {
                    padding: [60, 60],
                    maxZoom: 14
                }
            );
        }
    });
}


/* =========================================================
   ESCAPE HTML
========================================================= */

function escapeHtml(str) {

    const div =
        document.createElement("div");

    div.textContent = str ?? "";

    return div.innerHTML;
}