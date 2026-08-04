    <?php
$page_css = "notification.css";
$page_js = "notification.js";
 include __DIR__ . '/../includes/header.php';
?>

<div class="top-nav">
    <h2>Notifications</h2>
    <?php include __DIR__ . '/../includes/navbar.php'; ?>
</div>
  <div class="page">

    <div class="stat-grid">
      <div class="stat-card">
        <div class="label">Sent today</div>
        <div class="value" id="statSentToday">0</div>
      </div>
      <div class="stat-card">
        <div class="label">Missing requirements</div>
        <div class="value warning" id="statMissingReq">0</div>
      </div>
      <div class="stat-card">
        <div class="label">Renewal deadlines</div>
        <div class="value accent" id="statRenewal">0</div>
      </div>
      <div class="stat-card">
        <div class="label">Failed to deliver</div>
        <div class="value danger" id="statFailed">0</div>
      </div>
    </div>

      <div class="pill-filter" id="pillFilter">
        <button class="pill-btn active type-all" data-type="">All</button>
        <button class="pill-btn type-missing_requirements" data-type="missing_requirements">Missing requirements</button>
        <button class="pill-btn type-renewal_deadline" data-type="renewal_deadline">Renewal deadline</button>
        <button class="pill-btn type-failed_retention" data-type="failed_retention">Failed retention</button>
        <button class="pill-btn type-approval_status" data-type="approval_status">Approval status</button>
 <button class="btn-primary" id="composeBtn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
          New notification
        </button>
      </div>

      <div class="table-wrap" id="notifTableWrap">
        <table class="notification-table">
          <thead>
            <tr>
              <th>Recipient</th>
              <th>Type</th>
              <th>Channel</th>
              <th>Status</th>
              <th>Sent</th>
            </tr>
          </thead>
          <tbody id="notifTableBody"></tbody>
        </table>
      </div>

      <div class="empty-state" id="notifEmptyState">
        <p>No notifications sent yet.</p>
        <span>Click "New notification" to send your first one.</span>
      </div>
    </div>
  </div>

  <!-- Compose notification modal -->
  <div class="notification-overlay" id="composeOverlay">
    <div class="notification-modal">
      <div class="notification-modal-header">
        <div>
          <h2>New notification</h2>
          <p>Sends an email to the selected scholars.</p>
        </div>
        <button class="close-btn" id="composeCloseBtn" aria-label="Close">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
        </button>
      </div>

      <div class="modal-body">
        <div class="field" >
          <label>Notification type</label>
          <select id="notifType">
            <option value="missing_requirements">Missing requirements</option>
            <option value="renewal_deadline">Renewal deadline</option>
            <option value="failed_retention">Failed retention</option>
            <option value="approval_status">Approval status</option>
          </select>
        </div>

        <label style="display:block; font-size:13px; font-weight:500; color:var(--slate-700); margin-bottom:8px;">Recipients</label>
        <div class="mode-toggle">
          <button class="mode-btn active" data-mode="segment">By status</button>
          <button class="mode-btn" data-mode="individual">Individual</button>
        </div>

        <div class="field" id="segmentField" style="margin-bottom:14px;">
          <select id="segmentSelect">
            <option value="missing_docs">Pending applicants missing documents</option>
            <option value="pending">All pending applicants</option>
            <option value="evaluation">Applicants in evaluation</option>
            <option value="approved">Approved scholars</option>
            <option value="rejected">Rejected applicants</option>
            <option value="all">All scholars</option>
          </select>
        </div>

        <div class="field" id="individualField" style="margin-bottom:14px; display:none;">
          <select id="individualSelect"></select>
        </div>

        <div class="recipient-count-row">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <span id="recipientLabel">Loading recipients...</span>
          <span class="count" id="recipientCount">—</span>
        </div>

        <div class="field" id="deadlineField" style="margin-bottom:14px; display:none;">
          <label>Deadline</label>
          <input type="text" id="notifDeadline" placeholder="e.g. August 15, 2026">
        </div>

        <div class="field" style="margin-bottom:14px;">
          <label>Subject</label>
          <input type="text" id="notifSubject">
        </div>

        <div class="field">
          <label>Message</label>
          <textarea id="notifMessage" rows="5"></textarea>
          <p style="font-size:11.5px; color:var(--slate-400); margin-top:6px;">
            Placeholders: <code>{{first_name}}</code>, <code>{{last_name}}</code>, <code>{{student_id}}</code>, <code>{{deadline}}</code> — filled in per recipient.
          </p>
        </div>
      </div>

      <div class="modal-footer">
        <button class="btn-back" id="composeCancelBtn">Cancel</button>
        <button class="btn-next" id="sendBtn">Send</button>
      </div>
    </div>
  </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>