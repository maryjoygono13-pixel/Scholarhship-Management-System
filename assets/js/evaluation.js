"use strict";
/* ============================================================
   Evaluation page typescript logic.
   ============================================================ */
const EVAL_API_BASE = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE + "/applicants.php" : "api/applicants.php";
/*
 * Some older seeded records stored the year level baked into the
 * program string itself (e.g. "BS Criminology · 4th Year"). Strip
 * that off so combining it with the real yearLevel field below never
 * shows the year level twice, then always show it as one line —
 * "<program> · <year level>" — right next to the course/department,
 * never as a separate standalone line.
 */
function cleanProgramName(program, yearLevel) {
    let base = (program || "").trim();
    const yl = (yearLevel || "").trim();
    if (yl) {
        const suffix = "· " + yl;
        if (base.endsWith(suffix)) {
            base = base.slice(0, base.length - suffix.length).trim();
        }
    }
    return base;
}
function formatDeptLine(program, major, yearLevel) {
    let base = cleanProgramName(program, yearLevel);
    const yl = (yearLevel || "").trim();
    if (major) {
        base = base ? base + " — " + major : major;
    }
    if (!base) return yl || "Not on file";
    return yl ? base + " · " + yl : base;
}
function normalizeSemesterGwa(raw) {
    const num = (v) => (v == null || v === "" || Number(v) <= 0 ? null : Number(v));
    return { first: num(raw && raw.first), second: num(raw && raw.second), summer: num(raw && raw.summer) };
}
function normalizeEval(record) {
    return {
        id: String(record.id ?? record._id ?? String(record.studentId ?? record.student_id ?? Math.random())),
        name: String(record.name ?? record.full_name ?? ""),
        fullName: String(record.fullName ?? record.full_name ?? record.name ?? ""),
        studentId: String(record.studentId ?? record.student_id ?? ""),
        program: String(record.program ?? record.program_year ?? ""),
        major: String(record.major ?? ""),
        yearLevel: String(record.yearLevel ?? record.year_level ?? ""),
        semester: String(record.semester ?? "1st Semester"),
        type: String(record.type ?? record.scholarship_type ?? ""),
        gwa: Number(record.gwa ?? record.current_gwa ?? 0),
        semesterGwa: normalizeSemesterGwa(record.semesterGwa),
        gwaReq: Number(record.gwaReq ?? record.gwa_requirement ?? 0),
        failingGrades: Number(record.failingGrades ?? record.failing_grades ?? 0),
        units: Number(record.units ?? record.units_earned ?? 0),
        enrolled: Boolean(record.enrolled ?? record.is_enrolled ?? false),
        docsComplete: Boolean(record.docsComplete ?? record.documents_complete ?? false),
        transcriptFile: String(record.transcriptFile ?? record.transcript_file ?? ""),
        coeFile: String(record.coeFile ?? record.coe_file ?? ""),
        goodMoralFile: String(record.goodMoralFile ?? record.good_moral_file ?? ""),
        status: String(record.status ?? "review"),
        remarks: String(record.remarks ?? ""),
        grades: record.grades && typeof record.grades === "object" ? record.grades : {},
        criteria: Array.isArray(record.criteria) ? record.criteria : [],
        documents: Array.isArray(record.documents) ? record.documents : [],
        specialQualification: String(record.specialQualification ?? ""),
        specialQualificationLabel: String(record.specialQualificationLabel ?? ""),
    };
}
(function () {
    let applicants = [];
    let selectedId = null;
    let activeTab = "overview";
    const EVAL_PAGE_SIZE = 10;
    let evalCurrentPage = 1;
    function initials(name) {
        return name.split(" ").map((n) => n[0]).slice(0, 2).join("").toUpperCase();
    }
    function esc(str) {
        const d = document.createElement("div");
        d.textContent = str == null ? "" : String(str);
        return d.innerHTML;
    }
    function gwaColor(gwa, req) {
        if (req <= 0 || gwa <= req)
            return "var(--green)";
        if (gwa <= req + 0.5)
            return "var(--amber)";
        return "var(--red)";
    }
    function fmtGwa(v) {
        return v == null ? "\u2014" : Number(v).toFixed(2);
    }
    function semGwaCell(v, req) {
        return '<td class="font-mono" style="font-weight:600;color:' + (v == null ? "var(--ink-soft)" : gwaColor(v, req)) + '">' + fmtGwa(v) + "</td>";
    }
    function semesterSummaryCard(label, v, req) {
        const has = v != null;
        const pass = has && (req <= 0 || v <= req);
        return '<div class="summary-card"><div class="big font-mono" style="color:' + (has ? gwaColor(v, req) : "var(--ink-soft)") + '">' + fmtGwa(v) + '</div><div class="lbl">' + label + '</div><div class="sub" style="color:' + (!has ? "var(--ink-soft)" : pass ? "var(--green)" : "var(--red)") + '">' + (!has ? "NO GRADES YET" : pass ? "PASSED" : "FAILED") + "</div></div>";
    }
    // A heads-up shown next to Approve when the GWA on file doesn't meet the requirement — it no
    // longer blocks the click. Missing grades (a new first-year applicant with no college semester
    // GWA yet) isn't a warning at all; their eligibility follows their Grade 12 record instead.
    function approveBlockReason(a) {
        if (a.status === "non-compliant")
            return "Note: " + a.semester + " GWA " + Number(a.gwa).toFixed(2) + " is above the required " + Number(a.gwaReq).toFixed(2) + " for " + a.type + ".";
        if (a.gwaReq > 0 && a.gwa > 0 && a.gwa > a.gwaReq)
            return "Note: " + a.semester + " GWA " + Number(a.gwa).toFixed(2) + " is above the required " + Number(a.gwaReq).toFixed(2) + " for " + a.type + ".";
        return "";
    }
    function approveBlockNote(a) {
        const reason = approveBlockReason(a);
        return reason ? '<span style="margin-right:auto; max-width:52%; font-size:12.5px; line-height:1.35; color:var(--red); font-weight:600;">' + esc(reason) + "</span>" : "";
    }
    function showToast(msg, kind) {
        const t = document.getElementById("toast");
        if (!t)
            return;
        t.textContent = msg;
        t.classList.remove("toast-success", "toast-error");
        if (kind)
            t.classList.add("toast-" + kind);
        t.classList.add("show");
        // Longer messages (e.g. why an approval was refused) stay up long enough to read.
        setTimeout(() => t.classList.remove("show"), msg.length > 40 ? 5000 : 2200);
    }
    async function loadApplicants() {
        try {
            const res = await fetch(EVAL_API_BASE);
            if (!res.ok)
                throw new Error("Request failed: " + res.status);
                const data = await res.json();
                applicants = Array.isArray(data) ? data.map(normalizeEval) : [];
        }
        catch (e) {
            applicants = [];
            showToast("Could not load applicants from server.", "error");
        }
    }
    async function saveApplicant(applicant) {
        try {
            const res = await fetch(EVAL_API_BASE + "?id=" + encodeURIComponent(applicant.id), {
                method: "PATCH",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                    id: applicant.id,
                    status: applicant.status,
                    remarks: applicant.remarks,
                }),
            });
            if (!res.ok) {
                const body = await res.json().catch(() => null);
                showToast(body && body.message ? body.message : "Could not save (error " + res.status + ").", "error");
                return false;
            }
            return true;
        }
        catch (e) {
            showToast("Could not save — check your API connection.", "error");
            return false;
        }
    }
    // Fallback only \u2014 used when a scholarship has no configured criteria at all.
    function computeChecklist(a) {
        const hasGwa = a.gwa > 0;
        const gwaPass = !hasGwa || a.gwaReq <= 0 || a.gwa <= a.gwaReq;
        const failPass = Number(a.failingGrades) === 0;
        return [
            { label: "Currently Enrolled", value: a.enrolled ? "Enrolled" : "Not Enrolled", pass: a.enrolled },
            { label: "GWA Requirement (\u2264 " + a.gwaReq + ")", value: hasGwa ? (gwaPass ? "Passed" : "Failed") : "No grades yet", pass: gwaPass },
            { label: "No Failing Grade", value: failPass ? "Passed" : "Failed", pass: failPass },
            { label: "Complete Documents", value: a.docsComplete ? "Complete" : "Missing", pass: a.docsComplete },
        ];
    }
    function criterionStatusColor(status) {
        return status === "pass" ? "var(--green)" : status === "fail" ? "var(--red)" : "var(--amber)";
    }
    function criterionStatusIcon(status) {
        if (status === "pass")
            return '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>';
        if (status === "fail")
            return '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
        return '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="var(--amber)" stroke-width="3"><circle cx="12" cy="12" r="9"/></svg>';
    }
    function criterionStatusBg(status) {
        return status === "pass" ? "var(--green-bg)" : status === "fail" ? "var(--red-bg)" : "#fef3c7";
    }
    function criterionValueLabel(c) {
        // Poverty Threshold: show the income the applicant declared alongside the result.
        if (c.type === "poverty_threshold" && c.actualValue != null && (c.status === "pass" || c.status === "fail"))
          return (c.status === "pass" ? "Passed" : "Failed") + " · income ₱" + Number(c.actualValue).toLocaleString("en-PH", { minimumFractionDigits: 2 });
        if (c.status === "pass") return "Passed";
        if (c.status === "fail") return "Failed";
        if (c.autoChecked) return c.remarks || "Pending Data";
        return "Manual Verification Required";
    }
    // Renders one configured eligibility criterion row. Auto-checkable criteria (GWA, No
    // Failing Grades, enrollment, ...) show a live computed Pass/Fail. Everything else shows
    // "Manual Verification Required" with inline Pass/Fail/Pending buttons for the registrar.
    // What the applicant declared on the Apply page (Talent / Community Service / Other
    // Discounts). Shown for the registrar to verify — never counted toward eligibility.
    function declaredQualificationHtml(a) {
        if (!a.specialQualification) return "";
        return ('<div class="section"><h3>' + esc(a.specialQualificationLabel || "Declared Qualification") + "</h3>" +
            '<div class="req-row"><div class="req-left">' + esc(a.specialQualification) + "</div>" +
            '<div class="req-val" style="color:var(--ink-soft)">Declared by applicant — verify with documents</div></div></div>');
    }
    function renderCriterionRow(c) {
        const manualControls = c.autoChecked
            ? ""
            : '<div style="display:flex; gap:6px; margin:2px 0 10px 34px;">' +
                ["pass", "fail", "pending"]
                    .map((v) => '<button type="button" class="btn-secondary" style="height:26px; padding:0 10px; font-size:11.5px;' +
                        (c.status === v ? " border-color:" + criterionStatusColor(v) + "; color:" + criterionStatusColor(v) + ";" : "") +
                        '" data-crit-mark="' + c.id + '" data-mark-value="' + v + '">Mark ' + v[0].toUpperCase() + v.slice(1) + "</button>")
                    .join("") +
                "</div>";
        return ('<div class="req-row"><div class="req-left"><span class="req-icon" style="background:' + criterionStatusBg(c.status) + '">' +
            criterionStatusIcon(c.status) + "</span>" + esc(c.label) + (c.required ? "" : ' <span style="color:var(--ink-soft); font-weight:400;">(optional)</span>') +
            '</div><div class="req-val" style="color:' + criterionStatusColor(c.status) + '">' + criterionValueLabel(c) + "</div></div>" +
            manualControls);
    }
    // A required criterion that failed or is still pending keeps the applicant out of
    // "ELIGIBLE" — an optional one never blocks it either way.
    function isEligible(a) {
        if (a.criteria.length > 0) {
            return a.criteria.every((c) => !c.required || c.status === "pass");
        }
        return computeChecklist(a).every((c) => c.pass);
    }
    function statusBadge(status) {
        const map = {
            review: { label: "For Review", cls: "badge-review" },
            approved: { label: "Approved", cls: "badge-approved" },
            rejected: { label: "Rejected", cls: "badge-rejected" },
            "non-compliant": { label: "Non-Compliant", cls: "badge-non-compliant" },
        };
        const s = map[status] || map.review;
        return '<span class="badge ' + s.cls + '">' + s.label + "</span>";
    }
    function gradeStatusCell(grade) {
        if (grade === undefined || grade === null) {
            return '<span class="badge badge-pending">Not yet graded</span>';
        }
        const passed = Number(grade) <= 3.00;
        return '<span class="font-mono" style="font-weight:600; margin-right:8px;">' + Number(grade).toFixed(2) + '</span>' +
            '<span class="badge ' + (passed ? 'badge-approved' : 'badge-rejected') + '">' + (passed ? 'Passed' : 'Failed') + '</span>';
    }
    function buildSemesterSubjectBlock(subjects, label, grades, gwa) {
        if (!subjects.length) return "";
        const g = grades || {};
        const rows = subjects
            .map((s) => '<tr><td><b class="font-mono">' + esc(s.code) + '</b></td><td>' + esc(s.name) + '</td><td>' + gradeStatusCell(g[s.code]) + '</td></tr>')
            .join("");
        return (
            '<div style="margin-bottom:14px;">' +
            '<div style="font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.03em; color:var(--green); margin-bottom:6px; display:flex; justify-content:space-between;"><span>' + esc(label) + '</span><span class="font-mono" style="text-transform:none;">GWA ' + fmtGwa(gwa == null ? null : gwa) + '</span></div>' +
            '<div style="border:1px solid #e2e8f0; border-radius:10px; overflow:hidden;">' +
            '<table class="applicants-table" style="font-size:13px; margin:0; table-layout:fixed; width:100%;">' +
            '<thead><tr><th style="width:18%;">Code</th><th style="width:57%;">Subject Description</th><th style="width:25%;">Status</th></tr></thead>' +
            '<tbody>' + rows + '</tbody></table></div></div>'
        );
    }
    function populateTypeFilter() {
        const sel = document.getElementById("filterType");
        if (!sel)
            return;
        const current = sel.value;
        const types = Array.from(new Set(applicants.map((a) => a.type))).sort();
        sel.innerHTML =
            '<option value="all">Scholarship Types</option>' +
                types.map((t) => '<option value="' + esc(t) + '">' + esc(t) + "</option>").join("");
        sel.value = types.includes(current) ? current : "all";
    }
    function getFiltered() {
        const typeEl = document.getElementById("filterType");
        const statusEl = document.getElementById("filterStatus");
        const searchEl = document.getElementById("searchInput");
        const type = typeEl ? typeEl.value : "all";
        const status = statusEl ? statusEl.value : "all";
        const q = searchEl ? searchEl.value.trim().toLowerCase() : "";
        return applicants.filter((a) => {
            if (type !== "all" && a.type !== type)
                return false;
            if (status !== "all" && a.status !== status)
                return false;
            if (q && !(a.name.toLowerCase().includes(q) || a.studentId.toLowerCase().includes(q)))
                return false;
            return true;
        });
    }
    function renderEvalPagination(total) {
        const wrap = document.getElementById("evaluationPagination");
        if (!wrap)
            return;
        const totalPages = Math.max(1, Math.ceil(total / EVAL_PAGE_SIZE));
        if (evalCurrentPage > totalPages)
            evalCurrentPage = totalPages;
        if (evalCurrentPage < 1)
            evalCurrentPage = 1;
        if (total === 0) {
            wrap.innerHTML = "";
            return;
        }
        const start = (evalCurrentPage - 1) * EVAL_PAGE_SIZE + 1;
        const end = Math.min(evalCurrentPage * EVAL_PAGE_SIZE, total);
        const buttons = '<button type="button" class="active" data-page="' + evalCurrentPage + '" disabled>' + evalCurrentPage + "</button>";
        wrap.innerHTML =
            "<span>Showing " + start + "–" + end + " of " + total + " entries</span>" +
            '<div class="page-btns">' +
            '<button type="button" data-page="' + (evalCurrentPage - 1) + '" ' + (evalCurrentPage <= 1 ? "disabled" : "") + ">Prev</button>" +
            buttons +
            '<button type="button" data-page="' + (evalCurrentPage + 1) + '" ' + (evalCurrentPage >= totalPages ? "disabled" : "") + ">Next</button>" +
            "</div>";
        wrap.querySelectorAll("button[data-page]").forEach((btn) => {
            btn.addEventListener("click", () => {
                const p = parseInt(btn.getAttribute("data-page") || "", 10);
                if (!isNaN(p) && p >= 1 && p <= totalPages) {
                    evalCurrentPage = p;
                    renderTable();
                }
            });
        });
    }
    function renderTable() {
        const wrap = document.getElementById("tableWrap");
        if (!wrap)
            return;
        const list = getFiltered();
        if (applicants.length === 0) {
            wrap.innerHTML = '<div class="empty">No applicants yet.<br>Applicants added elsewhere will appear here.</div>';
            renderEvalPagination(0);
            return;
        }
        if (list.length === 0) {
            wrap.innerHTML = '<div class="empty">No applicants match your filters.</div>';
            renderEvalPagination(0);
            return;
        }
        const totalPages = Math.max(1, Math.ceil(list.length / EVAL_PAGE_SIZE));
        if (evalCurrentPage > totalPages)
            evalCurrentPage = totalPages;
        if (evalCurrentPage < 1)
            evalCurrentPage = 1;
        const pageItems = list.slice((evalCurrentPage - 1) * EVAL_PAGE_SIZE, evalCurrentPage * EVAL_PAGE_SIZE);
        let rows = pageItems
            .map((a) => {
            return ('<tr data-id="' + a.id + '" class="' + (a.id === selectedId ? "active" : "") + '">' +
                '<td><div class="who"><div class="avatar">' + initials(a.name) + '</div><div><div class="name">' + esc(a.name) + '</div><div class="id font-mono">' + esc(a.studentId) + "</div></div></div></td>" +
                '<td class="type-cell">' + esc(a.type) + "</td>" +
                semGwaCell(a.semesterGwa.first, a.gwaReq) + semGwaCell(a.semesterGwa.second, a.gwaReq) +
                '<td><span style="color:' + (a.enrolled ? "var(--green)" : "var(--red)") + '"><span class="dot" style="background:' + (a.enrolled ? "var(--green)" : "var(--red)") + '"></span>' + (a.enrolled ? "Enrolled" : "Not Enrolled") + "</span></td>" +
                "<td>" + statusBadge(a.status) + "</td>" +
                '<td><button class="btn-primary" style="height:32px; padding:0 12px; font-size:12.5px;" data-review="' + a.id + '">Review</button></td>' +
                "</tr>");
        })
            .join("");
        wrap.innerHTML =
            '<table class="applicants-table"><thead><tr>' +
                '<th>Applicant</th><th>Scholarship Type</th><th>1st Sem GWA</th><th>2nd Sem GWA</th><th>Enrollment</th><th>Status</th><th class="actions-head">Action</th>' +
                '</tr></thead><tbody>' + rows + '</tbody></table>';
        wrap.querySelectorAll("[data-review]").forEach((btn) => {
            btn.addEventListener("click", (e) => {
                e.stopPropagation();
                const id = btn.getAttribute("data-review");
                if (id)
                    selectApplicant(id);
            });
        });
        wrap.querySelectorAll("tr[data-id]").forEach((tr) => {
            tr.addEventListener("click", () => {
                const id = tr.getAttribute("data-id");
                if (id)
                    selectApplicant(id);
            });
        });
        renderEvalPagination(list.length);
    }
    function selectApplicant(id) {
        selectedId = id;
        activeTab = "overview";
        renderTable();
        renderRightPanel();
    }
    function closeRightPanel() {
        selectedId = null;
        const overlay = document.getElementById("evalModalOverlay");
        const panel = document.getElementById("rightPanel");
        if (overlay)
            overlay.classList.remove("open");
        if (panel)
            panel.innerHTML = "";
        renderTable();
    }
    // Every subject currently listed on the applicant's curriculum (their own semester, plus
    // the 1st semester's too once they're in 2nd Semester/Summer — same subjects the Grades
    // tab breaks down) is worth 3 units, whether or not it has a grade recorded yet.
    function computeCurriculumUnits(a) {
        const bySem = window.getCurriculumSubjectsBySemester
            ? window.getCurriculumSubjectsBySemester(cleanProgramName(a.program, a.yearLevel), a.major, a.yearLevel)
            : { firstSem: [], secondSem: [] };
        const semLower = String(a.semester || "").toLowerCase();
        const isSecondSem = semLower.includes("2") || semLower.includes("second") || semLower.includes("summer");
        const count = bySem.firstSem.length + (isSecondSem ? bySem.secondSem.length : 0);
        return count * 3;
    }
    function getTabBodyHtml(a) {
        const eligible = isEligible(a);
        const requirementsHtml = a.criteria.length > 0
            ? a.criteria.map(renderCriterionRow).join("")
            : computeChecklist(a)
                .map((c) => '<div class="req-row"><div class="req-left"><span class="req-icon" style="background:' +
                    (c.pass ? "var(--green-bg)" : "var(--red-bg)") +
                    '">' +
                    (c.pass
                        ? '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>'
                        : '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>') +
                    "</span>" + esc(c.label) + '</div><div class="req-val" style="color:' + (c.pass ? "var(--green)" : "var(--red)") + '">' + c.value + "</div></div>")
                .join("");
        if (activeTab === "overview") {
            return ('<div class="section"><h3>Academic Summary</h3><div class="summary-grid">' +
                semesterSummaryCard("1st Semester GWA", a.semesterGwa.first, a.gwaReq) +
        semesterSummaryCard("2nd Semester GWA", a.semesterGwa.second, a.gwaReq) +
                '<div class="summary-card"><div class="big font-mono">' + a.failingGrades + '</div><div class="lbl">Failing Grades</div><div class="sub" style="color:var(--ink-soft)">' + (Number(a.failingGrades) === 0 ? "None" : "Review") + "</div></div>" +
                '<div class="summary-card"><div class="big font-mono">' + computeCurriculumUnits(a) + '</div><div class="lbl">Units Earned</div><div class="sub" style="color:var(--ink-soft)">Units</div></div>' +
                "</div></div>" +
                declaredQualificationHtml(a) +
                '<div class="section"><h3>Eligibility Criteria</h3>' +
                requirementsHtml +
                "</div>" +
                '<div class="section"><h3>Evaluation Result</h3>' +
                '<div class="result-big" style="color:' + (eligible ? "var(--green)" : "var(--blue)") + '">' + (eligible ? "ELIGIBLE" : "PENDING") + "</div>" +
                '<div class="result-sub">' + (eligible ? "Applicant meets all requirements." : "Still awaiting review — not all requirements are confirmed yet.") + "</div>" +
                "</div>" +
                '<div class="section"><h3>Remarks</h3>' +
                '<textarea id="remarksInput" rows="3" placeholder="Enter remarks (optional)...">' + esc(a.remarks || "") + "</textarea>" +
                "</div>");
        }
        if (activeTab === "grades") {
            const bySem = window.getCurriculumSubjectsBySemester
                ? window.getCurriculumSubjectsBySemester(cleanProgramName(a.program, a.yearLevel), a.major, a.yearLevel)
                : { firstSem: [], secondSem: [] };
            // 2nd Semester and Summer Term both show the 2nd-semester subjects below the 1st.
            const semLower = String(a.semester || "").toLowerCase();
            const isSecondSem = semLower.includes("2") || semLower.includes("second") || semLower.includes("summer");
            let breakdownHtml;
            if (!bySem.firstSem.length && !bySem.secondSem.length) {
                breakdownHtml = '<p style="font-size:12.5px; color:#6b7280; margin-bottom:14px;">' +
                    (a.yearLevel
                        ? 'No curriculum reference is available for this program/major yet.'
                        : "Set the applicant's year level to view the subject breakdown.") +
                    '</p>';
            } else {
                breakdownHtml = buildSemesterSubjectBlock(bySem.firstSem, "1st Semester", a.grades, a.semesterGwa.first);
                if (isSecondSem) {
                    breakdownHtml += buildSemesterSubjectBlock(bySem.secondSem, "2nd Semester", a.grades, a.semesterGwa.second);
                }
            }
            return ('<div class="section"><h3>Academic Subject Breakdown</h3>' +
                breakdownHtml +
                '<div style="padding:14px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; display:flex; justify-content:space-between; align-items:center;">' +
                '<span style="font-size:13px; font-weight:600; color:#334155;">GWA per semester (' + (a.gwaReq > 0 ? 'required \u2264 ' + Number(a.gwaReq).toFixed(2) : 'no GWA requirement') + ')</span>' +
                '<span class="font-mono" style="font-size:14px; font-weight:700; color:var(--green);">1st: ' + fmtGwa(a.semesterGwa.first) + ' &nbsp;|&nbsp; 2nd: ' + fmtGwa(a.semesterGwa.second) + '</span>' +
                '</div></div>');
        }
        if (activeTab === "enrollment") {
            // Driven only by real evidence — the uploaded Certificate of Enrollment — not the
            // generic `enrolled` flag, which defaults true regardless of whether anything was
            // actually submitted or verified.
            var isVerified = Boolean(a.coeFile);
            return ('<div class="section"><h3>Enrollment Verification</h3>' +
                '<div class="view-detail-grid">' +
                '<div class="detail-item"><span class="detail-label">Enrollment Status</span><span class="detail-value highlight" style="color:' + (isVerified ? "var(--green)" : "var(--red)") + '">' + (isVerified ? "Enrolled & Official" : "Unconfirmed — no Certificate of Enrollment on file") + '</span></div>' +
                '<div class="detail-item"><span class="detail-label">Semester</span><span class="detail-value">' + esc(a.semester || "1st Semester") + '</span></div>' +
                '<div class="detail-item"><span class="detail-label">Registrar Verified</span><span class="detail-value">' + (isVerified ? "Office of the Registrar (via uploaded COE)" : "Not yet verified") + '</span></div>' +
                '<div class="detail-item full-width"><span class="detail-label">Degree Program</span><span class="detail-value">' + esc(formatDeptLine(a.program, a.major, a.yearLevel)) + '</span></div>' +
                '</div></div>');
        }
        if (activeTab === "documents") {
            const uploadsBase = (window.SITE_BASE || "") + "/uploads/";
            const docRow = (label, filename, required) => {
                if (!filename) {
                    return '<div class="doc-row">' +
                        '<div class="doc-row-info"><div class="doc-row-label">' + esc(label) + (required ? "" : ' <span style="color:var(--ink-soft); font-weight:400;">(optional)</span>') + '</div><div class="doc-row-meta">Not submitted</div></div>' +
                        '<span class="badge ' + (required ? "badge-rejected" : "badge-pending") + '">' + (required ? "Missing" : "Optional") + '</span>' +
                        '</div>';
                }
                const url = uploadsBase + encodeURIComponent(filename);
                return '<div class="doc-row">' +
                    '<a href="' + url + '" target="_blank" rel="noopener" class="doc-row-thumb"><img src="' + url + '" alt="' + esc(label) + '"></a>' +
                    '<div class="doc-row-info"><div class="doc-row-label">' + esc(label) + '</div><div class="doc-row-meta">Uploaded · PNG</div></div>' +
                    '<a href="' + url + '" target="_blank" rel="noopener" class="badge badge-approved">View Full Size</a>' +
                    '</div>';
            };
            const rowsHtml = a.documents.length > 0
                ? a.documents.map((d) => docRow(d.label, d.filename, d.required)).join("")
                : docRow("Official Transcript of Records (TOR)", a.transcriptFile, true) +
                    docRow("Certificate of Enrollment (COE)", a.coeFile, true) +
                    docRow("Certificate of Good Moral Character", a.goodMoralFile, true);
            return ('<div class="section"><h3>Submitted Verification Documents</h3>' +
                '<p style="font-size:12.5px; color:#6b7280; margin-bottom:14px;">Uploaded by the applicant through the public Apply page. Review each image before approving or rejecting this application.</p>' +
                '<div style="display:flex; flex-direction:column; gap:10px;">' +
                rowsHtml +
                '</div></div>');
        }
        return ('<div class="section"><h3>Scholarship Committee Assessment</h3>' +
            '<div class="view-detail-grid">' +
            '<div class="detail-item"><span class="detail-label">Academic Merit Score</span><span class="detail-value font-mono highlight">96 / 100</span></div>' +
            '<div class="detail-item"><span class="detail-label">Financial Need Rating</span><span class="detail-value font-mono">High Priority</span></div>' +
            '<div class="detail-item full-width"><span class="detail-label">Committee Recommendation</span><span class="detail-value remarks">Applicant meets all requirements and exhibits exceptional academic performance for continuation of scholarship grant.</span></div>' +
            '</div></div>');
    }
    // Wires up the Mark Pass/Fail/Pending buttons for non-auto-checkable criteria (see
    // renderCriterionRow). Re-run after every re-render of #evalTabContainer.
    function wireCriterionMarkButtons(a) {
        const panel = document.getElementById("rightPanel");
        if (!panel) return;
        panel.querySelectorAll("[data-crit-mark]").forEach((btn) => {
            btn.addEventListener("click", async () => {
                const critId = btn.getAttribute("data-crit-mark");
                const markValue = btn.getAttribute("data-mark-value");
                if (!critId || !markValue) return;
                btn.disabled = true;
                try {
                    const apiPath = (typeof window !== "undefined" && window.API_BASE) ? window.API_BASE : "api";
                    const res = await fetch(`${apiPath}/save_criteria_review.php`, {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify({ applicantId: a.id, criterionId: critId, status: markValue }),
                    });
                    const json = await res.json();
                    if (json.success) {
                        await loadApplicants();
                        renderRightPanel();
                        showToast("Criterion marked " + markValue + ".", "success");
                    } else {
                        showToast(json.message || "Could not save mark.", "error");
                        btn.disabled = false;
                    }
                } catch (e) {
                    showToast("Server error — could not save mark.", "error");
                    btn.disabled = false;
                }
            });
        });
    }
    function renderRightPanel() {
        const overlay = document.getElementById("evalModalOverlay");
        const panel = document.getElementById("rightPanel");
        if (!panel || !overlay)
            return;
        const a = applicants.find((x) => x.id === selectedId);
        if (!a) {
            overlay.classList.remove("open");
            panel.innerHTML = "";
            return;
        }
        overlay.classList.add("open");
        const tabsHtml = ["overview", "grades", "enrollment", "documents", "evaluation"]
            .map((t) => '<button class="tab ' + (activeTab === t ? "active" : "") + '" data-tab="' + t + '">' + t + "</button>")
            .join("");
        panel.innerHTML =
            '<div class="custom-modal-header"><h3>Applicant Evaluation</h3><button type="button" class="custom-modal-close" id="closePanelBtn">' +
                '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
                "</button></div>" +
                '<div class="custom-modal-body" style="flex:1; overflow-y:auto;">' +
                '<div class="profile">' +
                '<div class="profile-top"><div class="avatar">' + initials(a.name) + "</div>" +
                '<div><div class="eval-profile-name">' + esc(a.fullName) + " " + statusBadge(a.status) + '</div><div class="profile-id font-mono">' + esc(a.studentId) + "</div></div></div>" +
                '<div class="profile-meta">' +
                "<span>" + esc(formatDeptLine(a.program, a.major, a.yearLevel)) + "</span>" +
                "<span>" + esc(a.type) + " Scholarship</span>" +
                "</div>" +
                "</div>" +
                '<div class="tabs">' + tabsHtml + "</div>" +
                '<div id="evalTabContainer">' + getTabBodyHtml(a) + '</div>' +
                "</div>" +
                '<div class="custom-modal-footer">' +
                approveBlockNote(a) +
                '<button type="button" class="btn-danger" data-decide="rejected">Reject</button>' +
                '<button type="button" class="btn-primary" data-decide="approved"' + (approveBlockReason(a) ? ' title="' + esc(approveBlockReason(a)) + '"' : "") + '>Approve</button>' +
                "</div>";
        const closeBtn = panel.querySelector("#closePanelBtn");
        if (closeBtn)
            closeBtn.addEventListener("click", closeRightPanel);
        panel.querySelectorAll(".tab").forEach((btn) => {
            btn.addEventListener("click", () => {
                const tab = btn.getAttribute("data-tab");
                if (tab) {
                    activeTab = tab;
                    const container = panel.querySelector("#evalTabContainer");
                    if (container)
                        container.innerHTML = getTabBodyHtml(a);
                    panel.querySelectorAll(".tab").forEach((t) => t.classList.remove("active"));
                    btn.classList.add("active");
                    wireCriterionMarkButtons(a);
                }
            });
        });
        wireCriterionMarkButtons(a);
       panel.querySelectorAll("[data-decide]").forEach((btn) => {
    btn.addEventListener("click", async () => {
        const decision = btn.getAttribute("data-decide");

        if (!decision)
            return;

        const remarksEl = panel.querySelector("#remarksInput");

        // Approve is always clickable and always submits, whatever the GWA status shows
        // (missing grades, non-compliant, below requirement). The GWA note next to the
        // button stays as a heads-up for staff — it no longer blocks the decision.

        const previousStatus = a.status;
        const previousRemarks = a.remarks;
        a.status = decision;
        a.remarks = remarksEl
            ? remarksEl.value
            : a.remarks || "";

        const saved = await saveApplicant(a);
        if (!saved) {
            // Not saved (e.g. the server refused): undo the local change; the error toast stays up.
            a.status = previousStatus;
            a.remarks = previousRemarks;
            return;
        }

        // Remove from evaluation table after approval/rejection
        if (decision === "approved" || decision === "rejected") {
            applicants = applicants.filter(
                (item) => String(item.id) !== String(a.id)
            );
        }

        renderTable();
        closeRightPanel();

        showToast(
            decision === "approved" ? "Applicant approved." : "Applicant rejected.",
            decision === "rejected" ? undefined : "success"
        );
    });
});
        const remarksEl = panel.querySelector("#remarksInput");
        if (remarksEl) {
            remarksEl.addEventListener("blur", async () => {
                a.remarks = remarksEl.value;
                await saveApplicant(a);
            });
        }
    }
    function setupImportHandlers() {
        const gradeFile = document.getElementById("gradeFile");
        const evalGradeHeaderBtn = document.getElementById("evalGradeHeaderBtn");
        if (gradeFile && evalGradeHeaderBtn) {
            evalGradeHeaderBtn.addEventListener("click", () => gradeFile.click());
            gradeFile.addEventListener("change", function () {
                if (this.files && this.files.length > 0) {
                    showToast(`Academic records file "${this.files[0].name}" imported!`, "success");
                    this.value = "";
                }
            });
        }
        const enrollmentFile = document.getElementById("enrollmentFile");
        const evalEnrollmentHeaderBtn = document.getElementById("evalEnrollmentHeaderBtn");
        if (enrollmentFile && evalEnrollmentHeaderBtn) {
            evalEnrollmentHeaderBtn.addEventListener("click", () => enrollmentFile.click());
            enrollmentFile.addEventListener("change", function () {
                if (this.files && this.files.length > 0) {
                    showToast(`Enrollment records file "${this.files[0].name}" imported!`, "success");
                    this.value = "";
                }
            });
        }
    }
    const filterTypeEl = document.getElementById("filterType");
    const filterStatusEl = document.getElementById("filterStatus");
    const searchInputEl = document.getElementById("searchInput");
    function resetEvalPageAndRender() {
        evalCurrentPage = 1;
        renderTable();
    }
    if (filterTypeEl)
        filterTypeEl.addEventListener("change", resetEvalPageAndRender);
    if (filterStatusEl)
        filterStatusEl.addEventListener("change", resetEvalPageAndRender);
    if (searchInputEl)
        searchInputEl.addEventListener("input", resetEvalPageAndRender);
    const evalOverlay = document.getElementById("evalModalOverlay");
    if (evalOverlay) {
        evalOverlay.addEventListener("click", (e) => {
            if (e.target === evalOverlay)
                closeRightPanel();
        });
    }
    (async function init() {
        setupImportHandlers();
        const wrap = document.getElementById("tableWrap");
        if (wrap)
            wrap.innerHTML = '<div class="empty">Loading applicants\u2026</div>';
        await loadApplicants();
        console.log("Applicants:", applicants);
        populateTypeFilter();
        renderTable();
    })();
})();
