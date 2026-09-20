// Dashboard Charts initialization with forest green theme
document.addEventListener("DOMContentLoaded", () => {
  interface DistributionItem { type: string; count: number; }
  interface DistributionData {
    types: DistributionItem[];
    totalApproved: number;
    totalTypes: number;
    mostPopular: DistributionItem | null;
  }

  // Type names can be long ("Community Service or Leadership Scholarship"),
  // so break them onto several short lines for the axis.
  function wrapLabel(label: string, maxLen = 16): string[] {
    const lines: string[] = [];
    let current = "";
    label.split(/\s+/).forEach((word) => {
      if (current && (current + " " + word).length > maxLen) {
        lines.push(current);
        current = word;
      } else {
        current = current ? current + " " + word : word;
      }
    });
    if (current) lines.push(current);
    return lines;
  }

  // Both charts share this plot height so the two cards line up.
  const CHART_AREA_HEIGHT = 320;
  // Both charts use the same axis sizes, so their bars start and end in line.
  const Y_AXIS_WIDTH = 40;
  const X_AXIS_HEIGHT = 56;
  const COLUMN_MIN_WIDTH = 58;
  const chartLayout = { padding: { top: 22, right: 8, bottom: 0, left: 0 } };
  const fitYAxis = (scale: any) => { scale.width = Y_AXIS_WIDTH; };
  const fitXAxis = (scale: any) => { scale.height = X_AXIS_HEIGHT; };

  // "Community Service or Leadership Scholarship" -> "Community Service Leadership":
  // generic filler words are dropped for the axis (the tooltip keeps the full name).
  function shortTypeName(name: string): string {
    const paren = name.match(/\(([^)]+)\)/);
    if (paren) return paren[1].trim();
    const filler = /^(scholarships?|programs?|types?|of|and|or|the|&)$/i;
    const kept = name.split(/\s+/).filter((w) => w && !filler.test(w));
    return kept.length ? kept.join(" ") : name;
  }
  const chartColors: string[] = ["#238f54", "#2ea263", "#3ab774", "#1b6336", "#42c082", "#5fd39a"];

  /* =========================================================
     MONTHLY APPLICATIONS
  ========================================================= */
  const realMonthly = (window as any).monthlyApplicationsData as { labels: string[]; counts: number[] } | undefined;

  const monthlyData: Record<string, number> = {};
  if (realMonthly && Array.isArray(realMonthly.labels) && realMonthly.labels.length) {
    realMonthly.labels.forEach((label, i) => {
      monthlyData[label] = realMonthly.counts[i] ?? 0;
    });
  } else {
    // Fallback sample data, used only if the server couldn't compute real counts.
    Object.assign(monthlyData, { "Jan": 0, "Feb": 0, "Mar": 0, "Apr": 0, "May": 0, "Jun": 0 });
  }

  function createMonthlyChart(canvasId: string, dataObj: Record<string, number>): void {
    const ctx = document.getElementById(canvasId) as HTMLCanvasElement | null;
    if (!ctx) return;

    const labels = Object.keys(dataObj);
    const values = Object.values(dataObj);

    new Chart(ctx, {
      type: "bar", plugins: [barValueLabels],
      data: {
        labels: labels.map((l) => wrapLabel(l)),
        datasets: [{
          data: values,
          backgroundColor: labels.map((_, i) => chartColors[i % chartColors.length]),
          borderRadius: 6,
          maxBarThickness: 44
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false, layout: chartLayout,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: "#134e2a",
            padding: 10,
            cornerRadius: 6
          }
        },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0, color: "#6b7280" }, grid: { color: "rgba(0,0,0,0.04)" }, afterFit: fitYAxis }, x: { ticks: { color: "#6b7280", autoSkip: false, maxRotation: 0 }, grid: { display: false }, afterFit: fitXAxis }
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
  const distCanvas = document.getElementById("scholarshipChart") as HTMLCanvasElement | null;
  const distWrap = document.getElementById("distChartWrap");
  let distChart: any = null;

  // Writes each bar's count at its end, so 0-value types are visible too.
  const barValueLabels = {
    id: "barValueLabels",
    afterDatasetsDraw(chart: any) {
      const { ctx } = chart;
      const meta = chart.getDatasetMeta(0);
      const values = chart.data.datasets[0].data as number[];
      ctx.save();
      ctx.font = "600 12px sans-serif";
      ctx.fillStyle = "#374151";
      ctx.textBaseline = "bottom";
      ctx.textAlign = "center";
      meta.data.forEach((bar: any, i: number) => {
        ctx.fillText(String(values[i]), bar.x, bar.y - 4);
      });
      ctx.restore();
    }
  };

  function renderDistribution(data: DistributionData): void {
    const items = data.types || [];

    const setText = (id: string, text: string) => {
      const el = document.getElementById(id);
      if (el) el.textContent = text;
    };
    setText("distTotalApproved", String(data.totalApproved ?? 0));
    setText("distTotalTypes", String(data.totalTypes ?? 0));
    setText(
      "distMostPopular",
      data.mostPopular ? `${data.mostPopular.type} (${data.mostPopular.count})` : "—"
    );

    if (!distCanvas) return;

    const labels = items.map((i) => wrapLabel(shortTypeName(i.type).replace(/-(?=\w)/g, "- "), 10));
    const values = items.map((i) => i.count);
    const colors = items.map((_, i) => chartColors[i % chartColors.length]);

    // One row per type: the chart grows with the number of types.
    if (distWrap) distWrap.style.minWidth = items.length * COLUMN_MIN_WIDTH + "px";

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
          maxBarThickness: 28
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: chartLayout,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: "#134e2a",
            padding: 10,
            cornerRadius: 6,
            callbacks: {
              title: (ctx: any[]) => items[ctx[0].dataIndex] ? items[ctx[0].dataIndex].type : "",
              label: (ctx: any) => `Total Approved Scholars: ${ctx.parsed.y}`
            }
          }
        },
        scales: {
          x: { ticks: { color: "#374151", font: { size: 10 }, maxRotation: 0, minRotation: 0, autoSkip: false }, grid: { display: false }, afterFit: fitXAxis }, y: { beginAtZero: true, ticks: { precision: 0, color: "#6b7280" }, grid: { color: "rgba(0,0,0,0.04)" }, afterFit: fitYAxis }
        }
      },
      plugins: [barValueLabels]
    });
  }

  async function refreshDistribution(): Promise<void> {
    try {
      const res = await fetch("api/scholarship_distribution.php", { cache: "no-store" });
      const json = await res.json();
      if (json && json.success) renderDistribution(json as DistributionData);
    } catch (e) {
      console.error("Failed to refresh scholarship distribution:", e);
    }
  }

  const initialDistribution = (window as any).scholarshipDistributionData as DistributionData | undefined;
  if (initialDistribution && Array.isArray(initialDistribution.types)) {
    renderDistribution(initialDistribution);
  }
  refreshDistribution();
  window.setInterval(() => { if (!document.hidden) refreshDistribution(); }, DISTRIBUTION_REFRESH_MS);
  document.addEventListener("visibilitychange", () => { if (!document.hidden) refreshDistribution(); });
  window.addEventListener("focus", refreshDistribution);

  createMonthlyChart("monthlyChart", monthlyData);
});
