
const STEPS = [
  { key: "personal", label: "Personal" },
  { key: "academic", label: "Academic" },
  { key: "scholarship", label: "Scholarship" },
  { key: "documents", label: "Documents" },
];

const overlay = document.getElementById("overlay");
const openBtn = document.getElementById("openBtn");
const closeBtn = document.getElementById("closeBtn");
const doneBtn = document.getElementById("doneBtn");
const backBtn = document.getElementById("backBtn");
const nextBtn = document.getElementById("nextBtn");
const progressBar = document.getElementById("progressBar");
const stepCounter = document.getElementById("stepCounter");
const modalFooter = document.getElementById("modalFooter");
const successMsg = document.getElementById("successMsg");

let currentIndex = 0;
let furthestIndex = 0;
const formData = {};

const checkIcon = `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>`;

const ICONS = {
  personal: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>`,
  academic: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.5 2.5 3 6 3s6-1.5 6-3v-5"/></svg>`,
  scholarship: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="6"/><path d="M15.5 13.5 17 22l-5-3-5 3 1.5-8.5"/></svg>`,
  documents: `<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6"/></svg>`,
};

function renderProgress() {
  progressBar.innerHTML = "";
  STEPS.forEach((step, i) => {
    const wrap = document.createElement("div");
    wrap.className = "progress-step";

    const isCompleted = i < currentIndex;
    const isCurrent = i === currentIndex;
    const isReachable = i <= furthestIndex;

    const btn = document.createElement("button");
    btn.className = "step-btn";
    btn.disabled = !isReachable;
    btn.innerHTML = `
      <span class="step-circle ${isCompleted ? "completed" : isCurrent ? "current" : ""}">
        ${isCompleted ? checkIcon : ICONS[step.key]}
      </span>
      <span class="step-label ${isCompleted ? "completed" : isCurrent ? "current" : ""}">${step.label}</span>
    `;
    btn.addEventListener("click", () => {
      if (isReachable) {
        currentIndex = i;
        render();
      }
    });
    wrap.appendChild(btn);

    if (i < STEPS.length - 1) {
      const line = document.createElement("div");
      line.className = "step-line" + (i < currentIndex ? " completed" : "");
      wrap.appendChild(line);
    }

    progressBar.appendChild(wrap);
  });
}

function render() {
  // panels
  document.querySelectorAll(".step-panel").forEach(panel => {
    panel.classList.remove("active");
  });
  const activePanel = document.querySelector(`.step-panel[data-step="${currentIndex}"]`);
  if (activePanel) activePanel.classList.add("active");

  renderProgress();
stepCounter.textContent = `Step ${currentIndex + 1} of ${STEPS.length}`;

// Hide Back button on Step 1
if (currentIndex === 0) {
    backBtn.style.display = "none";
    modalFooter.style.justifyContent = "flex-end";
} else {
    backBtn.style.display = "inline-flex";
    modalFooter.style.justifyContent = "space-between";
}
nextBtn.innerHTML = currentIndex === STEPS.length - 1
  ? "Submit Application"
  : `Next <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>`;

modalFooter.style.display = "flex";
}

function showSuccess() {
  document.querySelectorAll(".step-panel").forEach(panel => panel.classList.remove("active"));
  document.querySelector('.step-panel[data-step="success"]').classList.add("active");
  modalFooter.style.display = "none";
  const name = `${formData.firstName || "The applicant"} ${formData.lastName || ""}`.trim();
  successMsg.textContent = `${name} has been successfully added to the system.`;
}

function openModal() {
  overlay.classList.add("open");
}

function closeModal() {
  overlay.classList.remove("open");
  currentIndex = 0;
  furthestIndex = 0;
  document.querySelectorAll("[data-field]").forEach(el => {
    el.value = "";
  });
  document.querySelectorAll(".hint").forEach(el => {
    el.textContent = "PDF, JPG, or PNG · max 5MB";
  });
  document.querySelectorAll(".upload-action").forEach(el => {
    el.textContent = "Upload";
  });
  Object.keys(formData).forEach(k => delete formData[k]);
  render();
}

// collect field values as user types
document.querySelectorAll("input[data-field], select[data-field], textarea[data-field]").forEach(el => {
  el.addEventListener("change", () => {
    const key = el.dataset.field;
    if (el.type === "file") {
      const file = el.files[0];
      formData[key] = file || null;
      const hint = document.querySelector(`.hint[data-hint="${key}"]`);
      const action = document.querySelector(`.upload-action[data-action="${key}"]`);
      if (file) {
        hint.textContent = file.name;
        action.textContent = "Replace";
      } else {
        hint.textContent = "PDF, JPG, or PNG · max 5MB";
        action.textContent = "Upload";
      }
    } else {
      formData[key] = el.value;
    }
  });
});

openBtn.addEventListener("click", openModal);
closeBtn.addEventListener("click", closeModal);
doneBtn.addEventListener("click", closeModal);

backBtn.addEventListener("click", () => {
  if (currentIndex > 0) {
    currentIndex--;
    render();
  }
});

nextBtn.addEventListener("click", () => {

    // Remove previous error highlights
    document.querySelectorAll(".error").forEach(el => {
        el.classList.remove("error");
    });

    // Get required fields in the current step
    const currentPanel = document.querySelector(`.step-panel[data-step="${currentIndex}"]`);
    const requiredFields = currentPanel.querySelectorAll("[required]");

    let hasError = false;

    requiredFields.forEach(field => {

        if (field.type === "file") {

            if (field.files.length === 0) {
                field.classList.add("error");

                if (!hasError) {
                    field.focus();
                }

                hasError = true;
            }

        } else {

            if (field.value.trim() === "") {
                field.classList.add("error");

                if (!hasError) {
                    field.focus();
                }

                hasError = true;
            }

        }

    });

    if (hasError) return;

    if (currentIndex === STEPS.length - 1) {
       const form = document.getElementById("applicantForm");

    // Add saveApplicant so PHP can detect the submission
    const hidden = document.createElement("input");
    hidden.type = "hidden";
    hidden.name = "saveApplicant";
    hidden.value = "1";
    form.appendChild(hidden);

    form.submit();
    return;

    }

    currentIndex++;
    furthestIndex = Math.max(furthestIndex, currentIndex);
    render();

});
overlay.addEventListener("click", (e) => {
  if (e.target === overlay) closeModal();
});
document.querySelectorAll("[required]").forEach(field => {

    field.addEventListener("input", () => {
        if (field.value.trim() !== "") {
            field.classList.remove("error");
        }
    });

    field.addEventListener("change", () => {
        if (
            (field.type === "file" && field.files.length > 0) ||
            (field.type !== "file" && field.value.trim() !== "")
        ) {
            field.classList.remove("error");
        }
    });

});
const studentId = document.querySelector('[data-field="studentId"]');
const phoneNumber = document.querySelector('[data-field="phone"]');

studentId.addEventListener("input", function () {
    this.value = this.value.replace(/\D/g, "");
});

phoneNumber.addEventListener("input", function () {
    this.value = this.value.replace(/\D/g, "");
});
render();