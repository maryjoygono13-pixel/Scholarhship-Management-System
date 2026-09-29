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
    // server-side too — this just gives faster feedback before uploading. Re-run after every
    // re-render of the documents step, since its file inputs are (re)created dynamically.
    function wireFileTypeChecks() {
        form.querySelectorAll('input[type="file"]').forEach((input) => {
            input.addEventListener("change", () => {
                const file = input.files && input.files[0];
                if (file && file.type && file.type !== "image/png") {
                    showFlash(`"${file.name}" is not a PNG image. Please choose a .png file.`);
                    input.value = "";
                }
            });
        });
    }
    wireFileTypeChecks();

    // Step 6's document fields depend on which scholarship program was picked in Step 5 —
    // each program can require a different set of documents (see pages/apply.php, which
    // embeds window.SCHOLARSHIP_DOCUMENTS keyed by program name).
    const documentsGrid = document.getElementById("apDocumentsGrid");
    function renderDocumentFields() {
        if (!documentsGrid) return;
        const checked = form.querySelector('input[name="scholarship"]:checked');
        const docsMap = window.SCHOLARSHIP_DOCUMENTS || {};
        const docs = checked ? docsMap[checked.value] || [] : [];
        if (docs.length === 0) {
            documentsGrid.innerHTML = checked
                ? '<p class="ap-empty">No documents are required for this program.</p>'
                : '<p class="ap-empty">Please go back and select a scholarship program first.</p>';
            return;
        }
        documentsGrid.innerHTML = docs
            .map(
                (d) =>
                    '<div class="ap-field"><label>' + d.label + (d.required ? " *" : "") + "</label>" +
                    '<input type="file" name="documents[' + d.type + ']" accept="image/png"' + (d.required ? " required" : "") + "></div>"
            )
            .join("");
        wireFileTypeChecks();
    }
    // Family income is only asked for when the picked program has a Poverty Threshold. The
    // input is disabled (so it's neither validated nor submitted) whenever it's hidden.
    const incomeField = document.getElementById("apIncomeField");
    const incomeInput = document.getElementById("apFamilyIncome");
    function renderIncomeField() {
        if (!incomeField || !incomeInput) return;
        const checked = form.querySelector('input[name="scholarship"]:checked');
        const needs = !!(checked && (window.SCHOLARSHIP_NEEDS_INCOME || {})[checked.value]);
        incomeField.hidden = !needs;
        incomeInput.disabled = !needs;
        incomeInput.required = needs;
    }

    // Talent / Community Service / Other Discounts programs ask one extra question. Its label
    // and options come from window.SCHOLARSHIP_QUALIFICATION (built from
    // includes/special_qualification_helper.php). Switching programs rebuilds the options and
    // clears the old answer; hidden fields are disabled so they're neither validated nor sent.
    const qualField = document.getElementById("apQualField");
    const qualLabel = document.getElementById("apQualLabel");
    const qualSelect = document.getElementById("apQualSelect");
    const qualOtherField = document.getElementById("apQualOtherField");
    const qualOtherInput = document.getElementById("apQualOther");
    let qualProgram = null;

    function escapeText(s) {
        const div = document.createElement("div");
        div.textContent = s;
        return div.innerHTML;
    }

    function renderQualOther() {
        if (!qualOtherField || !qualOtherInput) return;
        const isOther = !!qualSelect && !qualSelect.disabled && qualSelect.value === "Other";
        qualOtherField.hidden = !isOther;
        qualOtherInput.disabled = !isOther;
        qualOtherInput.required = isOther;
        // Whitespace alone doesn't count as a description.
        if (isOther) qualOtherInput.setAttribute("pattern", ".*\\S.*");
        else qualOtherInput.value = "";
    }

    function renderQualificationField() {
        if (!qualField || !qualSelect) return;
        const checked = form.querySelector('input[name="scholarship"]:checked');
        const name = checked ? checked.value : null;
        const cfg = name ? (window.SCHOLARSHIP_QUALIFICATION || {})[name] : null;

        if (name !== qualProgram) {
            qualProgram = name;
            qualSelect.innerHTML = "";
            if (qualOtherInput) qualOtherInput.value = "";
            if (cfg) {
                qualSelect.innerHTML = '<option value="">Select</option>' +
                    cfg.options.map((o) => '<option value="' + escapeText(o) + '">' + escapeText(o) + "</option>").join("");
                if (qualLabel) qualLabel.textContent = cfg.label + " *";
            }
        }
        qualField.hidden = !cfg;
        qualSelect.disabled = !cfg;
        qualSelect.required = !!cfg;
        renderQualOther();
    }
    if (qualSelect) qualSelect.addEventListener("change", renderQualOther);

    form.querySelectorAll('input[name="scholarship"]').forEach((radio) => {
        radio.addEventListener("change", () => {
            renderDocumentFields();
            renderIncomeField();
            renderQualificationField();
        });
    });
    renderDocumentFields();
    renderIncomeField();
    renderQualificationField();

    // Belt-and-suspenders against a double submission (e.g. a fast double-click, or Enter
    // pressed again before the button's own `disabled` state has visually registered) — this
    // flag is checked synchronously before anything else, not just the button's own state.
    let isSubmitting = false;

    form.addEventListener("submit", async (e) => {
        e.preventDefault();
        if (isSubmitting) return;

        if (!form.querySelector('input[name="scholarship"]:checked')) {
            showFlash("Please select a scholarship program.");
            return;
        }

        isSubmitting = true;
        submitBtn.disabled = true;
        submitBtn.textContent = "Submitting...";
        if (flash) flash.hidden = true;

        try {
            const formData = new FormData(form);
            const res = await fetch("api/student_apply.php", { method: "POST", body: formData });
            const json = await res.json();

            if (json.success) {
                // Left disabled and left on the form permanently — the success screen below
                // replaces the form, so there is nothing left to (re-)submit.
                form.hidden = true;
                if (progressWrap) progressWrap.hidden = true;
                if (successBox) {
                    successBox.hidden = false;
                    successBox.scrollIntoView({ behavior: "smooth", block: "start" });
                }
            } else {
                showFlash(json.message || "Could not submit your application.");
                isSubmitting = false;
                submitBtn.disabled = false;
                submitBtn.textContent = "Submit Application";
            }
        } catch (err) {
            showFlash("Could not reach the server. Please try again.");
            isSubmitting = false;
            submitBtn.disabled = false;
            submitBtn.textContent = "Submit Application";
        }
    });
});
