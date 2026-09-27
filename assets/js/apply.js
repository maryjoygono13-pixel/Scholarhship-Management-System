"use strict";
document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("apApplyForm");
    if (!form) return; // Step 1 (Student ID) page has nothing to wire up

    const submitBtn = document.getElementById("apSubmitBtn");
    const flash = document.getElementById("apFlash");
    const successBox = document.getElementById("apSuccess");
    const progressWrap = document.getElementById("apProgressWrap");
    const progressFill = document.getElementById("apProgressFill");
    const stepCounter = document.getElementById("apStepCounter");
    const steps = Array.from(form.querySelectorAll(".ap-step"));
    const totalSteps = steps.length;

    function showFlash(text) {
        if (!flash) return;
        flash.textContent = text;
        flash.hidden = false;
        flash.scrollIntoView({ behavior: "smooth", block: "center" });
    }

    function goToStep(n) {
        steps.forEach((stepEl) => {
            stepEl.classList.toggle("active", Number(stepEl.dataset.step) === n);
        });
        if (stepCounter) stepCounter.textContent = `Step ${n} of ${totalSteps}`;
        if (progressFill) progressFill.style.width = `${(n / totalSteps) * 100}%`;
        if (progressWrap) progressWrap.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    function validateStep(stepEl) {
        const fields = Array.from(stepEl.querySelectorAll("input, select"));
        for (const field of fields) {
            if (!field.checkValidity()) {
                field.reportValidity();
                return false;
            }
        }
        return true;
    }

    steps.forEach((stepEl) => {
        const nextBtn = stepEl.querySelector(".ap-btn-next");
        const backBtn = stepEl.querySelector(".ap-btn-back");
        const n = Number(stepEl.dataset.step);

        if (nextBtn) {
            nextBtn.addEventListener("click", () => {
                if (!validateStep(stepEl)) return;
                goToStep(n + 1);
            });
        }
        if (backBtn) {
            backBtn.addEventListener("click", () => goToStep(n - 1));
        }
    });

    // A PNG file the browser mis-typed (rare, but some phone cameras do this) is caught
    // server-side too — this just gives faster feedback before uploading.
    form.querySelectorAll('input[type="file"]').forEach((input) => {
        input.addEventListener("change", () => {
            const file = input.files && input.files[0];
            if (file && file.type && file.type !== "image/png") {
                showFlash(`"${file.name}" is not a PNG image. Please choose a .png file.`);
                input.value = "";
            }
        });
    });

    form.addEventListener("submit", async (e) => {
        e.preventDefault();

        if (!form.querySelector('input[name="scholarship"]:checked')) {
            showFlash("Please select a scholarship program.");
            return;
        }

        submitBtn.disabled = true;
        submitBtn.textContent = "Submitting...";
        if (flash) flash.hidden = true;

        try {
            const formData = new FormData(form);
            const res = await fetch("api/student_apply.php", { method: "POST", body: formData });
            const json = await res.json();

            if (json.success) {
                form.hidden = true;
                if (progressWrap) progressWrap.hidden = true;
                if (successBox) {
                    successBox.hidden = false;
                    successBox.scrollIntoView({ behavior: "smooth", block: "start" });
                }
            } else {
                showFlash(json.message || "Could not submit your application.");
                submitBtn.disabled = false;
                submitBtn.textContent = "Submit Application";
            }
        } catch (err) {
            showFlash("Could not reach the server. Please try again.");
            submitBtn.disabled = false;
            submitBtn.textContent = "Submit Application";
        }
    });
});
