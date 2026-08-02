/* ============================================================
   API CONFIG — this is the only part you should need to edit.
   Point these at your real backend.
   ============================================================ */
const API_BASE = "https://yourapi.com/applicants"; // <-- replace with your real endpoint

/* Expected shape per applicant record from GET {API_BASE}:
{
  id: "123",
  name: "Juan Dela Cruz",
  studentId: "20230001",
  program: "BS Information Technology · 2nd Year",
  type: "Academic Merit",
  gwa: 1.43,
  gwaReq: 1.75,
  failingGrades: 0,
  units: 21,
  enrolled: true,
  docsComplete: true,
  status: "review",      // "review" | "interview" | "approved" | "rejected"
  remarks: ""
}
If your backend uses different field names (e.g. student_id instead
of studentId), edit the `normalize()` function below to map them —
everything else in this file reads only the camelCase names above. */

function normalize(record) {
  return {
    id: record.id ?? record._id ?? String(record.studentId ?? record.student_id ?? Math.random()),
    name: record.name ?? record.full_name ?? "",
    studentId: record.studentId ?? record.student_id ?? "",
    program: record.program ?? record.program_year ?? "",
    type: record.type ?? record.scholarship_type ?? "",
    gwa: Number(record.gwa ?? record.current_gwa ?? 0),
    gwaReq: Number(record.gwaReq ?? record.gwa_requirement ?? 0),
    failingGrades: Number(record.failingGrades ?? record.failing_grades ?? 0),
    units: Number(record.units ?? record.units_earned ?? 0),
    enrolled: Boolean(record.enrolled ?? record.is_enrolled ?? false),
    docsComplete: Boolean(record.docsComplete ?? record.documents_complete ?? false),
    status: record.status ?? "review",
    remarks: record.remarks ?? "",
  };
}

(function () {
  let applicants = [];
  let selectedId = null;
  let activeTab = "overview";

  function initials(name) {
    return name.split(" ").map((n) => n[0]).slice(0, 2).join("").toUpperCase();
  }
  function esc(str) {
    const d = document.createElement("div");
    d.textContent = str == null ? "" : String(str);
    return d.innerHTML;
  }
  function gwaColor(gwa, req) {
    if (gwa <= req) return "var(--green)";
    if (gwa <= req + 0.5) return "var(--amber)";
    return "var(--red)";
  }
  function showToast(msg) {
    const t = document.getElementById("toast");
    t.textContent = msg;
    t.classList.add("show");
    setTimeout(() => t.classList.remove("show"), 2200);
  }

  /* ---- DATA SOURCE: real backend (Option B) ---- */
  async function loadApplicants() {
    try {
      const res = await fetch(API_BASE);
      if (!res.ok) throw new Error("Request failed: " + res.status);
      const data = await res.json();
      applicants = Array.isArray(data) ? data.map(normalize) : [];
    } catch (e) {
      applicants = [];
      showToast("Could not load applicants from server.");
    }
  }

  // Called after a decision (approve/reject/interview) or a remarks edit.
  // Sends only the changed record — adjust the method/path to match your API.
  async function saveApplicant(applicant) {
    try {
      const res = await fetch(API_BASE + "/" + encodeURIComponent(applicant.id), {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          status: applicant.status,
          remarks: applicant.remarks,
        }),
      });
      if (!res.ok) throw new Error("Save failed: " + res.status);
    } catch (e) {
      showToast("Could not save — check your API connection.");
    }
  }

  function computeChecklist(a) {
    const gwaPass = a.gwa <= a.gwaReq;
    const failPass = Number(a.failingGrades) === 0;
    return [
      { label: "Currently Enrolled", value: a.enrolled ? "Enrolled" : "Not Enrolled", pass: a.enrolled },
      { label: "GWA Requirement (\u2264 " + a.gwaReq + ")", value: gwaPass ? "Passed" : "Failed", pass: gwaPass },
      { label: "No Failing Grade", value: failPass ? "Passed" : "Failed", pass: failPass },
      { label: "Complete Documents", value: a.docsComplete ? "Complete" : "Missing", pass: a.docsComplete },
    ];
  }

  function statusBadge(status) {
    const map = {
      review: { label: "For Review", cls: "badge-review" },
      interview: { label: "For Interview", cls: "badge-interview" },
      approved: { label: "Approved", cls: "badge-approved" },
      rejected: { label: "Rejected", cls: "badge-rejected" },
    };
    const s = map[status] || map.review;
    return '<span class="badge ' + s.cls + '">' + s.label + "</span>";
  }

  function populateTypeFilter() {
    const sel = document.getElementById("filterType");
    const current = sel.value;
    const types = Array.from(new Set(applicants.map((a) => a.type))).sort();
    sel.innerHTML =
      '<option value="all">All Scholarship Types</option>' +
      types.map((t) => '<option value="' + esc(t) + '">' + esc(t) + "</option>").join("");
    sel.value = types.includes(current) ? current : "all";
  }

  function getFiltered() {
    const type = document.getElementById("filterType").value;
    const status = document.getElementById("filterStatus").value;
    const q = document.getElementById("searchInput").value.trim().toLowerCase();
    return applicants.filter((a) => {
      if (type !== "all" && a.type !== type) return false;
      if (status !== "all" && a.status !== status) return false;
      if (q && !(a.name.toLowerCase().includes(q) || a.studentId.toLowerCase().includes(q))) return false;
      return true;
    });
  }

  function renderTable() {
    const wrap = document.getElementById("tableWrap");
    const list = getFiltered();

    if (applicants.length === 0) {
      wrap.innerHTML = '<div class="empty">No applicants yet.<br>Applicants added elsewhere will appear here.</div>';
      return;
    }
    if (list.length === 0) {
      wrap.innerHTML = '<div class="empty">No applicants match your filters.</div>';
      return;
    }

    let rows = list
      .map((a) => {
        return (
          '<tr data-id="' + a.id + '" class="' + (a.id === selectedId ? "active" : "") + '">' +
          '<td><div class="who"><div class="avatar">' + initials(a.name) + '</div><div><div class="name">' + esc(a.name) + '</div><div class="id">' + esc(a.studentId) + "</div></div></div></td>" +
          '<td class="type-cell">' + esc(a.type) + "</td>" +
          '<td style="font-weight:600;color:' + gwaColor(a.gwa, a.gwaReq) + '">' + Number(a.gwa).toFixed(2) + "</td>" +
          '<td><span style="color:' + (a.enrolled ? "var(--green)" : "var(--red)") + '"><span class="dot" style="background:' + (a.enrolled ? "var(--green)" : "var(--red)") + '"></span>' + (a.enrolled ? "Enrolled" : "Not Enrolled") + "</span></td>" +
          '<td style="color:' + (a.docsComplete ? "var(--ink)" : "var(--red)") + '">' + (a.docsComplete ? "Complete" : "Missing") + "</td>" +
          "<td>" + statusBadge(a.status) + "</td>" +
          '<td><button class="btn-review" data-review="' + a.id + '">Review</button></td>' +
          "</tr>"
        );
      })
      .join("");

    wrap.innerHTML =
      "<table><thead><tr>" +
      "<th>Applicant</th><th>Scholarship Type</th><th>GWA</th><th>Enrollment</th><th>Documents</th><th>Status</th><th>Action</th>" +
      "</tr></thead><tbody>" + rows + "</tbody></table>";

    wrap.querySelectorAll("[data-review]").forEach((btn) => {
      btn.addEventListener("click", (e) => {
        e.stopPropagation();
        selectApplicant(btn.getAttribute("data-review"));
      });
    });
    wrap.querySelectorAll("tr[data-id]").forEach((tr) => {
      tr.addEventListener("click", () => selectApplicant(tr.getAttribute("data-id")));
    });
  }

  function selectApplicant(id) {
    selectedId = id;
    activeTab = "overview";
    renderTable();
    renderRightPanel();
  }

  function closeRightPanel() {
    selectedId = null;
    document.getElementById("rightPanel").style.display = "none";
    document.getElementById("rightPanel").innerHTML = "";
    renderTable();
  }

  function renderRightPanel() {
    const panel = document.getElementById("rightPanel");
    const a = applicants.find((x) => x.id === selectedId);
    if (!a) {
      panel.style.display = "none";
      panel.innerHTML = "";
      return;
    }
    panel.style.display = "flex";

    const checklist = computeChecklist(a);
    const eligible = checklist.every((c) => c.pass);

    const tabsHtml = ["overview", "grades", "enrollment", "documents", "evaluation"]
      .map((t) => '<button class="tab ' + (activeTab === t ? "active" : "") + '" data-tab="' + t + '">' + t + "</button>")
      .join("");

    let bodyHtml = "";
    if (activeTab === "overview") {
      bodyHtml =
        '<div class="section"><h3>Academic Summary</h3><div class="summary-grid">' +
        '<div class="summary-card"><div class="big" style="color:' + gwaColor(a.gwa, a.gwaReq) + '">' + Number(a.gwa).toFixed(2) + '</div><div class="lbl">GWA</div><div class="sub" style="color:' + (a.gwa <= a.gwaReq ? "var(--green)" : "var(--red)") + '">' + (a.gwa <= a.gwaReq ? "PASSED" : "FAILED") + "</div></div>" +
        '<div class="summary-card"><div class="big">' + a.failingGrades + '</div><div class="lbl">Failing Grades</div><div class="sub" style="color:var(--ink-soft)">' + (Number(a.failingGrades) === 0 ? "None" : "Review") + "</div></div>" +
        '<div class="summary-card"><div class="big">' + a.units + '</div><div class="lbl">Units Earned</div><div class="sub" style="color:var(--ink-soft)">Units</div></div>' +
        "</div></div>" +
        '<div class="section"><h3>Requirements Checklist</h3>' +
        checklist
          .map(
            (c) =>
              '<div class="req-row"><div class="req-left"><span class="req-icon" style="background:' +
              (c.pass ? "var(--green-bg)" : "var(--red-bg)") +
              '">' +
              (c.pass
                ? '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>'
                : '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="3"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>') +
              "</span>" + esc(c.label) + '</div><div class="req-val" style="color:' + (c.pass ? "var(--green)" : "var(--red)") + '">' + c.value + "</div></div>"
          )
          .join("") +
        "</div>" +
        '<div class="section"><h3>Evaluation Result</h3>' +
        '<div class="result-big" style="color:' + (eligible ? "var(--green)" : "var(--red)") + '">' + (eligible ? "ELIGIBLE" : "NOT ELIGIBLE") + "</div>" +
        '<div class="result-sub">' + (eligible ? "Applicant meets all requirements." : "Applicant does not meet all requirements.") + "</div>" +
        "</div>" +
        '<div class="section"><h3>Remarks</h3>' +
        '<textarea id="remarksInput" rows="3" placeholder="Enter remarks (optional)...">' + esc(a.remarks || "") + "</textarea>" +
        "</div>";
    } else {
      bodyHtml =
        '<div class="section" style="text-align:center;color:var(--ink-soft);padding:40px 20px;">' +
        activeTab.charAt(0).toUpperCase() + activeTab.slice(1) + " details go here.</div>";
    }

    panel.innerHTML =
      '<div class="right-head"><h2>Applicant Evaluation</h2><button id="closePanelBtn">' +
      '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>' +
      "</button></div>" +
      '<div class="right-body">' +
      '<div class="profile">' +
      '<div class="profile-top"><div class="avatar">' + initials(a.name) + "</div>" +
      '<div><div class="profile-name">' + esc(a.name) + " " + statusBadge(a.status) + '</div><div class="profile-id">' + esc(a.studentId) + "</div></div></div>" +
      '<div class="profile-meta">' +
      "<span>" + esc(a.program) + "</span>" +
      "<span>" + esc(a.type) + " Scholarship</span>" +
      "</div>" +
      "</div>" +
      '<div class="tabs">' + tabsHtml + "</div>" +
      bodyHtml +
      "</div>" +
      '<div class="actions">' +
      '<button class="interview ' + (a.status === "interview" ? "active-choice" : "") + '" data-decide="interview">For Interview</button>' +
      '<button class="reject" data-decide="rejected">Reject</button>' +
      '<button class="approve" data-decide="approved">Approve</button>' +
      "</div>";

    panel.querySelector("#closePanelBtn").addEventListener("click", closeRightPanel);
    panel.querySelectorAll(".tab").forEach((btn) => {
      btn.addEventListener("click", () => {
        activeTab = btn.getAttribute("data-tab");
        renderRightPanel();
      });
    });
    panel.querySelectorAll("[data-decide]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        const decision = btn.getAttribute("data-decide");
        const remarksEl = panel.querySelector("#remarksInput");
        a.status = decision;
        a.remarks = remarksEl ? remarksEl.value : a.remarks || "";
        await saveApplicant(a);
        renderTable();
        renderRightPanel();
        showToast(decision === "approved" ? "Applicant approved." : decision === "rejected" ? "Applicant rejected." : "Moved to interview.");
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

  document.getElementById("filterType").addEventListener("change", renderTable);
  document.getElementById("filterStatus").addEventListener("change", renderTable);
  document.getElementById("searchInput").addEventListener("input", renderTable);

  (async function init() {
    document.getElementById("tableWrap").innerHTML = '<div class="empty">Loading applicants\u2026</div>';
    await loadApplicants();
    populateTypeFilter();
    renderTable();
  })();
})();