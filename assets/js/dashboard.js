"use strict";
// Dashboard Charts initialization with forest green theme
document.addEventListener("DOMContentLoaded", () => {
    // Type names can be long ("Community Service or Leadership Scholarship"),
    // so break them onto several short lines for the axis.
    function wrapLabel(label, maxLen = 16) {
        const lines = [];
        let current = "";
        label.split(/\s+/).forEach((word) => {
            if (current && (current + " " + word).length > maxLen) {
                lines.push(current);
                current = word;
            }
            else {
                current = current ? current + " " + word : word;
            }
        });
        if (current)
            lines.push(current);
        return lines;
    }
    const chartColors = ["#238f54", "#2ea263", "#3ab774", "#1b6336", "#42c082", "#5fd39a"];
    /* =========================================================
       MONTHLY APPLICATIONS
    ========================================================= */
    const realMonthly = window.monthlyApplicationsData;
    const monthlyData = {};
    if (realMonthly && Array.isArray(realMonthly.labels) && realMonthly.labels.length) {
        realMonthly.labels.forEach((label, i) => {
            monthlyData[label] = realMonthly.counts[i] ?? 0;
        });
    }
    else {
        // Fallback sample data, used only if the server couldn't compute real counts.
        Object.assign(monthlyData, { "Jan": 0, "Feb": 0, "Mar": 0, "Apr": 0, "May": 0, "Jun": 0 });
    }
    function createMonthlyChart(canvasId, dataObj) {
        const ctx = document.getElementById(canvasId);
        if (!ctx)
            return;
        const labels = Object.keys(dataObj);
        const values = Object.values(dataObj);
        new Chart(ctx, {
            type: "bar",
            data: {
                labels: labels.map((l) => wrapLabel(l)),
                datasets: [{
                        data: values,
                        backgroundColor: labels.map((_, i) => chartColors[i % chartColors.length]),
                        borderRadius: 6,
                        maxBarThickness: 72,
                        categoryPercentage: 0.8,
                        barPercentage: 0.85
                    }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 24 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: "#134e2a",
                        padding: 10,
                        cornerRadius: 6
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, color: "#6b7280" },
                        afterFit: (axis) => { axis.width = 44; },
                        grid: { color: "rgba(0,0,0,0.04)" }
                    },
                    x: {
                        ticks: { color: "#6b7280" },
                        afterFit: (axis) => { axis.height = 72; },
                        grid: { display: false }
                    }
                }
            }
        });
    }
    /* =========================================================
       SCHOLARSHIP DISTRIBUTION
       Approved scholars per scholarship type. The server computes it live
       from the records table and the Scholarships module, so nothing here
       knows any type name; the chart re-fetches to stay current.
    ========================================================= */
    const DISTRIBUTION_REFRESH_MS = 10000;
    const distCanvas = document.getElementById("scholarshipChart");
    let distChart = null;
    // Writes each bar's count above it, so 0-value types are visible too.
    const barValueLabels = {
        id: "barValueLabels",
        afterDatasetsDraw(chart) {
            const { ctx } = chart;
            const meta = chart.getDatasetMeta(0);
            const values = chart.data.datasets[0].data;
            ctx.save();
            ctx.font = "600 12px sans-serif";
            ctx.fillStyle = "#374151";
            ctx.textAlign = "center";
            ctx.textBaseline = "bottom";
            meta.data.forEach((bar, i) => {
                ctx.fillText(String(values[i]), bar.x, bar.y - 6);
            });
            ctx.restore();
        }
    };
    function renderDistribution(data) {
        const items = data.types || [];
        const setText = (id, text) => {
            const el = document.getElementById(id);
            if (el)
                el.textContent = text;
        };
        setText("distTotalApproved", String(data.totalApproved ?? 0));
        setText("distTotalTypes", String(data.totalTypes ?? 0));
        setText("distMostPopular", data.mostPopular ? `${data.mostPopular.type} (${data.mostPopular.count})` : "—");
        if (!distCanvas)
            return;
        const labels = items.map((i) => wrapLabel(i.type, 12));
        const values = items.map((i) => i.count);
        const colors = items.map((_, i) => chartColors[i % chartColors.length]);
        if (distChart) {
            distChart.data.labels = labels;
            distChart.data.datasets[0].data = values;
            distChart.data.datasets[0].backgroundColor = colors;
            distChart.update();
            return;
        }
        distChart = new Chart(distCanvas, {
            type: "bar",
            data: {
                labels,
                datasets: [{
                        label: "Total Approved Scholars",
                        data: values,
                        backgroundColor: colors,
                        borderRadius: 6,
                        maxBarThickness: 72,
                        categoryPercentage: 0.8,
                        barPercentage: 0.85
                    }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                layout: { padding: { top: 24 } },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: "#134e2a",
                        padding: 10,
                        cornerRadius: 6,
                        callbacks: {
                            title: (ctx) => items[ctx[0].dataIndex] ? items[ctx[0].dataIndex].type : "",
                            label: (ctx) => `Total Approved Scholars: ${ctx.parsed.y}`
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, color: "#6b7280" },
                        afterFit: (axis) => { axis.width = 44; },
                        grid: { color: "rgba(0,0,0,0.04)" }
                    },
                    x: {
                        ticks: { color: "#374151", autoSkip: false, maxRotation: 0, minRotation: 0, font: { size: 11 } },
                        afterFit: (axis) => { axis.height = 72; },
                        grid: { display: false }
                    }
                }
            },
            plugins: [barValueLabels]
        });
    }
    async function refreshDistribution() {
        try {
            const res = await fetch("api/scholarship_distribution.php?sy=" + encodeURIComponent(window.dashboardSchoolYear || "all"), { cache: "no-store" });
            const json = await res.json();
            if (json && json.success)
                renderDistribution(json);
        }
        catch (e) {
            console.error("Failed to refresh scholarship distribution:", e);
        }
    }
    const initialDistribution = window.scholarshipDistributionData;
    if (initialDistribution && Array.isArray(initialDistribution.types)) {
        renderDistribution(initialDistribution);
    }
    refreshDistribution();
    window.setInterval(() => { if (!document.hidden)
        refreshDistribution(); }, DISTRIBUTION_REFRESH_MS);
    document.addEventListener("visibilitychange", () => { if (!document.hidden)
        refreshDistribution(); });
    window.addEventListener("focus", refreshDistribution);
    createMonthlyChart("monthlyChart", monthlyData);
});
