<?php
$current_page = 'notification';
$page_title = "Notifications";
$page_css = "notification.css";
$page_js = "notification.js";
include __DIR__ . '/../includes/header.php';
?>

<div class="page">

    <div class="notif-tabs" id="notifTabs" role="tablist">
      <button type="button" class="notif-tab active" data-tab="inbox" role="tab">
        Inbox <span class="tab-badge" id="inboxTabBadge" hidden>0</span>
      </button>
      <button type="button" class="notif-tab" data-tab="sent" role="tab">Sent log</button>
    </div>

    <!-- ================= INBOX (received messages) ================= -->
    <div id="inboxPanel">
      <div class="inbox-toolbar">
        <div class="inbox-search">
          <input type="text" id="inboxSearch" placeholder="Search sender, subject or message...">
        </div>
        <select id="inboxFilter">
          <option value="">All messages</option>
          <option value="unread">Unread only</option>
        </select>
        <button type="button" class="btn-secondary" id="inboxMarkAllBtn">Mark all as read</button>
        <button type="button" class="btn-primary" id="inboxLogBtn">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14"/></svg>
          Log received message
        </button>
      </div>

      <div class="table-wrap" id="inboxTableWrap">
        <table class="notification-table inbox-table">
          <thead>
            <tr>
              <th style="width:28px;"></th>
              <th style="width:24%;">From</th>
              <th>Message</th>
              <th style="width:16%;">Received</th>
              <th style="width:110px; text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody id="inboxTableBody"></tbody>
        </table>
      </div>

      <div class="empty-state" id="inboxEmptyState">
        <p>No messages received yet.</p>
        <span>Messages from scholars and applicants will show up here. Use "Log received message" to record one.</span>
      </div>
    </div>

    <!-- ================= SENT LOG ================= -->
    <div id="sentPanel" hidden>

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
              <th style="text-align:right;">Actions</th>
            </tr>
          </thead>
          <tbody id="notifTableBody"></tbody>
        </table>
      </div>

      <div class="empty-state" id="notifEmptyState">
        <p>No notifications sent yet.</p>
        <span>Click "New notification" to send your first one.</span>
      </div>

      <div class="pagination-bar" id="notificationPagination"></div>
    </div>
  </div>

  <!-- View Inbox Message Modal -->
  <div class="custom-modal-overlay" id="inboxViewOverlay">
    <div class="custom-modal-card">
      <div class="custom-modal-header">
        <div>
          <h3 id="inboxViewSubject">Message</h3>
          <p id="inboxViewMeta"></p>
        </div>
        <button type="button" class="custom-modal-close" id="inboxViewCloseBtn"><i data-lucide="x"></i></button>
      </div>
      <div class="custom-modal-body">
        <div class="inbox-view-from" id="inboxViewFrom"></div>
        <div class="inbox-view-message" id="inboxViewMessage"></div>
      </div>
      <div class="custom-modal-footer">
        <button type="button" class="btn-danger" id="inboxViewDeleteBtn">Delete</button>
        <button type="button" class="btn-secondary" id="inboxViewUnreadBtn">Mark as unread</button>
        <a class="btn-primary" id="inboxViewReplyBtn" href="#" style="text-decoration:none;">Reply by email</a>
      </div>
    </div>
  </div>

  <!-- Log Received Message Modal -->
  <div class="custom-modal-overlay" id="inboxLogOverlay">
    <div class="custom-modal-card">
      <div class="custom-modal-header">
        <div>
          <h3>Log received message</h3>
          <p>Record a message a scholar or applicant sent to the office.</p>
        </div>
        <button type="button" class="custom-modal-close" id="inboxLogCloseBtn"><i data-lucide="x"></i></button>
      </div>
      <div class="custom-modal-body" style="display:flex; flex-direction:column; gap:12px;">
        <div class="field"><label>From (name) <span style="color:red;">*</span></label><input type="text" id="inboxLogName" placeholder="e.g. Maria Santos"></div>
        <div class="field"><label>Email</label><input type="email" id="inboxLogEmail" placeholder="name@example.com"></div>
        <div class="field"><label>Student ID</label><input type="text" id="inboxLogStudentId" placeholder="Optional"></div>
        <div class="field"><label>Subject</label><input type="text" id="inboxLogSubject"></div>
        <div class="field"><label>Message <span style="color:red;">*</span></label><textarea id="inboxLogMessage" rows="5"></textarea></div>
      </div>
      <div class="custom-modal-footer">
        <button type="button" class="btn-secondary" id="inboxLogCancelBtn">Cancel</button>
        <button type="button" class="btn-primary" id="inboxLogSaveBtn">Save message</button>
      </div>
    </div>
  </div>

  <!-- View Notification Modal -->
  <div class="custom-modal-overlay" id="notifViewOverlay">
    <div class="custom-modal-card sm">
      <div class="custom-modal-header">
        <div>
          <h3>Notification Log Details</h3>
          <p>Full record of sent notification dispatch.</p>
        </div>
        <button type="button" class="custom-modal-close" id="notifViewCloseBtn"><i data-lucide="x"></i></button>
      </div>
      <div class="custom-modal-body" id="notifViewBody"></div>
    </div>
  </div>

  <!-- Delete Confirm Modal -->
  <div class="custom-modal-overlay" id="notifDeleteOverlay">
    <div class="custom-modal-card sm">
      <div class="custom-modal-header">
        <div>
          <h3>Delete Notification Log</h3>
          <p>Confirm notification record removal.</p>
        </div>
        <button type="button" class="custom-modal-close" id="notifDeleteCloseBtn"><i data-lucide="x"></i></button>
      </div>
      <div class="custom-modal-body">
        <p style="font-size:14px; color:#4b5563;">Are you sure you want to delete this notification record?</p>
      </div>
      <div class="custom-modal-footer">
        <button type="button" class="btn-secondary" id="notifDeleteCancelBtn">Cancel</button>
        <button type="button" class="btn-danger" id="notifDeleteConfirmBtn">Delete Log</button>
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