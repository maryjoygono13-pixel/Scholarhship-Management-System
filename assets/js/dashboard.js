"use strict";
// Dashboard Charts initialization with forest green theme
document.addEventListener("DOMContentLoaded", () => {
    const scholarshipData = {
        "Merit": 45,
        "Endorsement": 32,
        "Academic": 65
    };
    const realMonthly = window.monthlyApplicationsData;
    const monthlyData = {};
    if (realMonthly && Array.isArray(realMonthly.labels) && realMonthly.labels.length) {
        realMonthly.labels.forEach((label, i) => {
            monthlyData[label] = realMonthly.counts[i] ?? 0;
        });
    }
    else {
        Object.assign(monthlyData, { "Jan": 0, "Feb": 0, "Mar": 0, "Apr": 0, "May": 0, "Jun": 0 });
    }
    const chartColors = ["#238f54", "#2ea263", "#3ab774", "#1b6336", "#42c082", "#5fd39a"];
    function createChart(canvasId, dataObj) {
        const ctx = document.getElementById(canvasId);
        if (!ctx)
            return;
        const labels = Object.keys(dataObj);
        const values = Object.values(dataObj);
        new Chart(ctx, {
            type: "bar",
            data: {
                labels: labels,
                datasets: [{
                        data: values,
                        backgroundColor: chartColors.slice(0, labels.length),
                        borderRadius: 6,
                        maxBarThickness: 44
                    }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
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
                        grid: { color: "rgba(0,0,0,0.04)" }
                    },
                    x: {
                        ticks: { color: "#6b7280" },
                        grid: { display: false }
                    }
                }
            }
        });
    }
    createChart("scholarshipChart", scholarshipData);
    createChart("monthlyChart", monthlyData);
});
