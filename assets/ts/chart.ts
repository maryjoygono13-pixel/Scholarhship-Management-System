// ---- Edit these values whenever you have real data ----
const scholarshipData: Record<string, number> = {
  Merit: 0,
  Endorsement: 0,
  Academic: 0
};

const monthlyData: Record<string, number> = {
  January: 0,
  February: 0,
  March: 0,
  April: 0
};
// ---------------------------------------------------------

const colors: string[] = ["#238f54", "#2f8a58", "#4a9a6c", "#1b6336"];

function makeBarChart(canvasId: string, dataObj: Record<string, number>): void {
  const canvas = document.getElementById(canvasId) as HTMLCanvasElement | null;
  if (!canvas) return;
  const labels = Object.keys(dataObj);
  const values = Object.values(dataObj);

  new Chart(canvas, {
    type: "bar",
    data: {
      labels: labels,
      datasets: [{
        data: values,
        backgroundColor: colors,
        borderRadius: 4,
        maxBarThickness: 48
      }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { display: false },
        tooltip: { enabled: true }
      },
      scales: {
        y: {
          beginAtZero: true,
          ticks: { precision: 0 },
          grid: { color: "rgba(0,0,0,0.06)" }
        },
        x: {
          grid: { display: false }
        }
      }
    }
  });
}

makeBarChart("scholarshipChart", scholarshipData);
makeBarChart("monthlyChart", monthlyData);
