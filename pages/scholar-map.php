    <?php
    require_once __DIR__ . '/../config/config.php';

    $current_page = 'scholar-map';
    $page_title = "Scholar Map";
    $page_css = "scholar-map.css";
    $page_js = "scholar-map.js";

    include __DIR__ . '/../includes/header.php';
    ?>

    <div class="page">
        <div class="map-card">

            <div class="map-controls">
                <div class="map-filter">
                    <label for="programFilter" style="font-weight:700; color:#134e2a;">Filter Students by Department</label>
                    <select id="programFilter" class="filter-select">
                        <option value="all">All Departments</option>
                        <option value="Nursing">BS Nursing</option>
                        <option value="Information Technology">BS Information Technology</option>
                        <option value="Accountancy">BS Accountancy</option>
                        <option value="Business Administration">BS Business Administration</option>
                        <option value="Food Preparation & Service Technology"> Food Preparation & Service Technology</option>
                        <option value="Political Science">BA Political Science</option>
                        <option value="Elementary Education">BE Elementary Education</option>
                        <option value="Secondary Education">BS Secondary Education</option>
                        <option value="Public Administration">Master of Public Administration</option>
                        <option value="Juris Doctor">Juris Doctor</option>
                        <option value="Bookkeeping">Bookkeeping NC II</option>
                        <option value="Caregiver">Caregiver NC II </option>
                        <option value="Bread & Pastry Production">Bread & Pastry Production NC II</option>
                    </select>
                </div>
            </div>

            <div id="scholarMap"></div>

            <div class="map-legend" id="mapLegend">
                <button type="button" class="map-legend-toggle" id="mapLegendToggle" aria-expanded="true" aria-controls="mapLegendBody">
                    <h4>Academic Program</h4>
                    <i data-lucide="chevron-down" class="map-legend-chevron"></i>
                </button>

                <div class="map-legend-body" id="mapLegendBody">

                <div class="legend-item">
                    <span class="legend-dot" style="background:#ec4899;"></span>
                    <span>BS Nursing</span>
                </div>

                <div class="legend-item">
                    <span class="legend-dot" style="background:#2563eb;"></span>
                    <span>BS Information Technology</span>
                </div>

                <div class="legend-item">
                    <span class="legend-dot" style="background:#7c3aed;"></span>
                    <span>BS Accountancy</span>
                </div>

                <div class="legend-item">
                    <span class="legend-dot" style="background:#f59e0b;"></span>
                    <span>BS Business Administration</span>
                </div>

                <div class="legend-item">
                    <span class="legend-dot" style="background:#14b8a6;"></span>
                    <span>Food Preparation &amp; Service Technology</span>
                </div>

                <div class="legend-item">
                    <span class="legend-dot" style="background:#84cc16;"></span>
                    <span>BA Political Science</span>
            </div>

                <div class="legend-item">
                    <span class="legend-dot" style="background:#16a34a;"></span>
                    <span>BE Elementary Education</span>
                </div>

                <div class="legend-item">
                    <span class="legend-dot" style="background:#06b6d4;"></span>
                    <span>BS Secondary Education</span>
                </div>

                <div class="legend-item">
                    <span class="legend-dot" style="background:#f97316;"></span>
                    <span>Master in Public Administration</span>
                </div>

                <div class="legend-item">
                    <span class="legend-dot" style="background:#dc2626;"></span>
                    <span>Juris Doctor</span>
                </div>

                <div class="legend-item">
                    <span class="legend-dot" style="background:#eab308;"></span>
                    <span>Bookkeeping NC II</span>
                </div>

                <div class="legend-item">
                    <span class="legend-dot" style="background:#0f766e;"></span>
                    <span>Caregiver NC II </span>
                </div>

                <div class="legend-item">
                    <span class="legend-dot" style="background:#be185d;"></span>
                    <span>Bread & Pastry Production NC II</span>
                </div>

                </div>

            </div>

        </div>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>