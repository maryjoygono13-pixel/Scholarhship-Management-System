<?php

$page_css = "applicants.css";
$page_js = "applicants.js";

include __DIR__ . '/../includes/header.php';
?>

  <div class="top-nav">
    <h2>Applicants</h2>
  <?php include __DIR__ . '/../includes/navbar.php'; ?>
</div>


    <div class="page">
        <div class="table-header">
            <button class="btn-primary btn-add-applicant" id="openBtn">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 11v6M19 14h6"/></svg>
              Add Applicant
            </button>
        </div>
       <div class="table-wrap">
        <div class="table-card">
          <table class="applicants-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Student ID</th>
              <th>Scholarship type</th>
              <th>Status</th>
              <th>Date applied</th>
              <th class="actions-head">Actions</th>
            </tr>
          </thead>
          <tbody id="tableBody">

          </tbody>
        </table>
      </div>
      <div class="empty-state" id="emptyState">
        <p>No applications yet.</p>
        <span>Click "Add Applicant" to create the first one.</span>
      </div>
    </div>

    <div class="applicant-overlay" id="overlay">
      <div class="applicant-modal">
        <form id="applicantForm">

        <!-- Header -->
        <div class="applicant-modal-header">
          <div>
            <h2>Add Applicant</h2>
            <p id="stepCounter">Step 1 of 4</p>
          </div>
          <button class="close-btn" id="closeBtn" aria-label="Close">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
          </button>
        </div>
          <!-- Progress -->
        <div class="applicant-progress" id="progressBar"></div>
        <!-- Body -->
        <div class="applicant-modal-body">
          <!-- Step 1: Personal -->
          <div class="step-panel active" data-step="0">
          <div class="step-title">
            <h3>Personal information</h3>
            <p>Basic contact details for the applicant.</p>
          </div>
          <br>
          <div class="grid-2">
            <div class="field">
              <label>First name <span class="req">*</span></label>
              <input type="text" name="first_name" placeholder="Juan" data-field="firstName" required>
            </div>
            <div class="field">
              <label>Last name <span class="req">*</span></label>
              <input type="text" name="last_name" placeholder="Dela Cruz" data-field="lastName" required>
            </div>
            <div class="field">
              <label>Student ID <span class="req">*</span></label>
              <input type="text" name="student_id" placeholder="2023-00123" data-field="studentId" inputmode="numeric" required>
            </div>
            <div class="field">
              <label>Email address <span class="req">*</span></label>
              <input type="email" name="email" placeholder="juan@email.com" data-field="email" required>
            </div>
            <div class="field">
              <label>Phone number <span class="req">*</span></label>
              <input type="text" name="phone" placeholder="09XX XXX XXXX" data-field="phone" inputmode="numeric" maxlength="11" required></div>
            <div class="field">
              <label>Date of birth <span class="req">*</span></label>
              <input type="date" name="birthdate" data-field="birthdate" required></div>
            <div class="field">
              <label>Home address <span class="req">*</span></label>
              <input type="text" name="address" placeholder="City,Province" data-field="address" required></div>
          </div>
        </div>
        <!-- Step 2: Academic -->
        <div class="step-panel" data-step="1">
          <div class="step-title">
            <h3>Academic background</h3>
            <p>Where the applicant currently studies.</p>
          </div>
          <div class="grid-2">
            <div class="field">
              <label>School / University <span class="req">*</span></label>
              <input type="text" name="school" placeholder="University name" data-field="school" required></div>
            <div class="field">
              <label>Program / Course <span class="req">*</span></label>
              <input type="text" name="program" placeholder="BS Computer Science" data-field="program" required></div>
            <div class="field">
              <label>Year level <span class="req">*</span></label>
              <select data-field="yearLevel"required name="year_level">
                <option value="">Select year level</option>
                <option>1st Year</option>
                <option>2nd Year</option>
                <option>3rd Year</option>
                <option>4th Year</option>

              </select>
            </div>
            <div class="field"><label>GPA / General average <span class="req">*</span></label><input type="text" name="gpa" placeholder="e.g. 1.75 or 92%" data-field="gpa" required></div>
          </div>
        </div>

        <!-- Step 3: Scholarship -->
        <div class="step-panel" data-step="2">
          <div class="step-title">
            <h3>Scholarship details</h3>
            <p>What the applicant is applying for.</p>
          </div>
          <div class="grid-2">
            <div class="field">
              <label>Scholarship type <span class="req">*</span></label>
              <select data-field="scholarshipType" name="scholarship_type"required >
                <option value="">Select type</option>
                <option>Academic Merit</option>
                <option>Financial Need-Based</option>
                <option>Athletic</option>
                <option>Community Service</option>
              </select>
            </div>

            <div class="field full-width">
              <label>Motivation essay</label>
              <textarea placeholder="Briefly explain why the applicant is applying for this scholarship..."name="essay" data-field="essay"></textarea>
            </div>
          </div>
        </div>

        <!-- Step 4: Documents -->
        <div class="step-panel" data-step="3">
          <div class="step-title">
            <h3>Supporting documents</h3>
            <p>Upload the required files to complete the application.</p>
          </div>

          <label class="upload-row">
            <div class="upload-left">
              <span class="upload-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M12 12v9M9 15l3-3 3 3"/></svg>
              </span>
              <div class="upload-text">
                <p class="name">Transcript of Records <span class="req">*</span></p>
                <p class="hint" data-hint="transcript">PDF, JPG, or PNG · max 5MB</p>
              </div>
            </div>
            <span class="upload-action" data-action="transcript">Upload</span>
            <input type="file" name="transcript" data-field="transcript">
          </label>

          <label class="upload-row">
            <div class="upload-left">
              <span class="upload-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M12 12v9M9 15l3-3 3 3"/></svg>
              </span>
              <div class="upload-text">
                <p class="name">Recommendation Letter <span class="req">*</span></p>
                <p class="hint" data-hint="recommendation">PDF, JPG, or PNG · max 5MB</p>
              </div>
            </div>
            <span class="upload-action" data-action="recommendation">Upload</span>
            <input type="file" name="recommendation" data-field="recommendation">
          </label>

          <label class="upload-row">
            <div class="upload-left">
              <span class="upload-icon">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M12 12v9M9 15l3-3 3 3"/></svg>
              </span>
              <div class="upload-text">
                <p class="name">Valid ID <span class="req">*</span></p>
                <p class="hint" data-hint="validId">PDF, JPG, or PNG · max 5MB</p>
              </div>
            </div>
            <span class="upload-action" data-action="validId">Upload</span>
            <input type="file" name="valid_id" data-field="validId">
          </label>
        </div>

        <!-- Success -->
        <div class="step-panel" data-step="success">
          <div class="success">
            <span class="success-icon">
              <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            </span>
            <h3>Applicant added</h3>
            <p id="successMsg">The applicant has been successfully added to the system.</p>
            <button class="btn-primary" id="doneBtn">Done</button>
          </div>
        </div>

      </div>

      <!-- Footer -->
      <div class="applicant-modal-footer" id="modalFooter">
        <button class="btn-back" id="backBtn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
          Back
        </button>
        <button
    type="submit"
    class="btn-next"
    id="nextBtn">
    Submit Application

          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
        </button>
</form>
      </div>

    </div>

  </div>

  <!-- View info modal -->
  <div class="applicant-overlay" id="viewOverlay">
    <div class="applicant-modal applicant-modal-sm">
      <div class="applicant-modal-header">
        <div>
          <h2>Applicant information</h2>
          <p>Read-only summary of the application.</p>
        </div>
        <button class="close-btn" id="viewCloseBtn" aria-label="Close">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="modal-body" id="viewBody"></div>
      <div class="modal-footer modal-footer-end">
        <button class="btn-next" id="viewCloseBtn2">Close</button>
      </div>
    </div>
  </div>

  <!-- Documents modal -->
  <div class="applicant-overlay" id="docsOverlay">
    <div class="applicant-modal applicant-modal-sm">
      <div class="applicant-modal-header">
        <div>
          <h2>Submitted documents</h2>
          <p>Files uploaded with this application.</p>
        </div>
        <button class="close-btn" id="docsCloseBtn" aria-label="Close">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="applicant-modal-body" id="docsBody"></div>
      <div class="applicant-modal-footer applicant-modal-footer-end">
        <button class="btn-next" id="docsCloseBtn2">Close</button>
      </div>
    </div>
  </div>

 <?php include __DIR__ . '/../includes/footer.php'; ?>
