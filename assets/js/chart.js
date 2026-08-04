// ---- Edit these values whenever you have real data ----
const scholarshipData = {
  Merit: 0,
  Endorsement: 0,
  Academic: 0
};

const monthlyData = {
  January: 0,
  February: 0,
  March: 0,
  April: 0
};
// ---------------------------------------------------------

const colors = ["#2a78d6", "#eda100", "#1baf7a"];

function makeBarChart(canvasId, dataObj) {
  const labels = Object.keys(dataObj);
  const values = Object.values(dataObj);

  new Chart(document.getElementById(canvasId), {
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