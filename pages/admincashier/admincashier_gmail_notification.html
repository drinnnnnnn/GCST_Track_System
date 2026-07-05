﻿<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  
  <title>GCST Admin Cashier Gmail Notifications</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
  <link rel="stylesheet" href="http://localhost/GCST_Track_System/assets/css/admincashier.css" />
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .balance-card p {
      margin: 0;
      font-size: 1.9rem;
      font-weight: 700;
    }
    .logs-section {
      background: var(--surface);
      border-radius: 24px;
      padding: 24px;
      box-shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
    }
    .logs-section h2 {
      margin-top: 0;
      margin-bottom: 18px;
      font-size: 1.35rem;
      color: var(--text);
    }
    .logs-toolbar {
      display: flex;
      flex-wrap: wrap;
      gap: 14px;
      justify-content: space-between;
      margin-bottom: 18px;
    }
    .toolbar-group {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      align-items: center;
    }
    .toolbar-group input,
    .toolbar-group select {
      min-width: 160px;
      padding: 12px 14px;
      border: 1px solid rgba(15, 23, 42, 0.12);
      border-radius: 14px;
      background: var(--surface);
      color: var(--text);
      transition: border-color 0.2s ease;
    }
    .toolbar-group input:focus,
    .toolbar-group select:focus {
      outline: none;
      border-color: rgba(59, 130, 246, 0.5);
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
    }
    .toolbar-group .search-input {
      width: 320px;
      min-width: 240px;
    }
    .toolbar-button,
    .action-button {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 11px 18px;
      border-radius: 14px;
      border: 1px solid transparent;
      cursor: pointer;
      font-weight: 600;
      transition: transform 0.15s ease, background 0.15s ease, border-color 0.15s ease;
    }
    .toolbar-button:hover,
    .action-button:hover {
      transform: translateY(-1px);
    }
    .toolbar-button.primary,
    .action-button.primary {
      background: var(--primary);
      color: white;
      box-shadow: 0 12px 26px rgba(59, 130, 246, 0.18);
    }
    .toolbar-button.secondary,
    .action-button.secondary {
      background: var(--surface);
      color: var(--text);
      border-color: rgba(15, 23, 42, 0.08);
    }
    .logs-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.95rem;
      min-width: 820px;
    }
    .logs-table th,
    .logs-table td {
      padding: 14px 12px;
      text-align: left;
      border-bottom: 1px solid #f1f5fb;
      vertical-align: middle;
    }
    .logs-table th {
      color: var(--muted);
      font-weight: 600;
      letter-spacing: 0.02em;
    }
    .logs-table tbody tr:hover {
      background: rgba(59, 130, 246, 0.04);
    }
    .status-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 8px 12px;
      border-radius: 999px;
      font-size: 0.82rem;
      font-weight: 700;
      text-transform: capitalize;
    }
    .status-sent { background: rgba(34, 197, 94, 0.12); color: var(--success); }
    .status-failed { background: rgba(239, 68, 68, 0.12); color: var(--danger); }
    .status-pending { background: rgba(245, 158, 11, 0.12); color: #b45309; }
    .empty-state {
      padding: 24px;
      color: var(--muted);
      text-align: center;
    }
    .toast-container {
      position: fixed;
      top: 20px;
      right: 20px;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 10px;
      width: min(360px, calc(100% - 24px));
      pointer-events: none;
    }
    .toast {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 14px 16px;
      border-radius: 14px;
      color: #fff;
      box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
      animation: slideInRight 0.25s ease;
      pointer-events: auto;
      overflow: hidden;
    }
    .toast.success { background: #16a34a; }
    .toast.error { background: #dc2626; }
    .toast.warning { background: #f59e0b; }
    .toast.info { background: #2563eb; }
    .toast-icon {
      font-size: 1rem;
      font-weight: 700;
      line-height: 1;
      margin-top: 2px;
    }
    .toast-message {
      font-size: 0.92rem;
      line-height: 1.45;
      word-break: break-word;
    }
    .toast.hide {
      animation: fadeOut 0.2s ease forwards;
    }
    @keyframes overlayFade {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    @keyframes modalFade {
      from { opacity: 0; transform: translateY(-10px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes slideInRight {
      from { opacity: 0; transform: translateX(18px); }
      to { opacity: 1; transform: translateX(0); }
    }
    @keyframes fadeOut {
      from { opacity: 1; transform: translateX(0); }
      to { opacity: 0; transform: translateX(18px); }
    }
    .modal-overlay {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.55);
      display: none;
      align-items: center;
      justify-content: center;
      padding: 24px;
      z-index: 100;
      animation: overlayFade 0.18s ease;
    }
    .modal-overlay.open {
      display: flex;
    }
    .modal-card {
      width: min(760px, 100%);
      background: var(--surface);
      border-radius: 24px;
      padding: 28px;
      box-shadow: 0 32px 80px rgba(15, 23, 42, 0.18);
      position: relative;
      animation: modalFade 0.18s ease;
    }
    .modal-card h3 {
      margin: 0 0 18px;
      font-size: 1.35rem;
    }
    .delete-modal-card {
      max-width: 520px;
      border-top: 6px solid #dc2626;
      text-align: center;
    }
    .delete-warning-icon {
      width: 64px;
      height: 64px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 999px;
      background: rgba(239, 68, 68, 0.12);
      color: #dc2626;
      font-size: 1.8rem;
      margin-bottom: 16px;
    }
    .delete-modal-message {
      color: var(--muted);
      font-size: 0.98rem;
      line-height: 1.6;
      margin: 0 0 18px;
    }
    .delete-modal-message strong {
      display: block;
      margin-top: 6px;
      color: var(--text);
    }
    .delete-modal-details {
      background: #f8fafc;
      border: 1px solid #e2e8f0;
      border-radius: 16px;
      padding: 14px 16px;
      text-align: left;
      margin-bottom: 18px;
    }
    .delete-modal-details div {
      display: grid;
      grid-template-columns: 90px 1fr;
      gap: 10px;
      font-size: 0.92rem;
      margin: 6px 0;
    }
    .delete-modal-details span {
      color: var(--muted);
      font-weight: 600;
    }
    .delete-modal-details strong {
      color: var(--text);
      word-break: break-word;
    }
    .modal-row {
      display: grid;
      gap: 16px;
      margin-bottom: 20px;
    }
    .modal-row.split {
      grid-template-columns: 1fr 1fr;
    }
    .modal-row label {
      font-size: 0.9rem;
      font-weight: 600;
      color: var(--muted);
    }
    .modal-row input,
    .modal-row textarea,
    .modal-row select {
      width: 100%;
      padding: 12px 14px;
      border-radius: 14px;
      border: 1px solid rgba(15, 23, 42, 0.12);
      background: var(--bg);
      color: var(--text);
      resize: vertical;
      min-height: 42px;
    }
    .modal-row textarea {
      min-height: 120px;
    }
    .modal-actions {
      display: flex;
      justify-content: flex-end;
      gap: 14px;
      flex-wrap: wrap;
      margin-top: 10px;
      padding-top: 24px;
      border-top: 1px solid rgba(15, 23, 42, 0.05);
    }
    .modal-actions .toolbar-button {
      min-width: 130px;
      justify-content: center;
      padding: 12px 24px;
      border-radius: 16px;
      font-size: 0.94rem;
    }
    .toolbar-button:active {
      transform: scale(0.97);
    }
    .toolbar-button.secondary {
      background: #f8fafc;
      border-color: #e2e8f0;
      color: #64748b;
    }
    .delete-btn {
      background: #dc2626;
      color: white;
    }
    .delete-btn:hover {
      background: #b91c1c;
    }
    .modal-close {
      position: absolute;
      right: 20px;
      top: 20px;
      border: none;
      background: transparent;
      color: var(--muted);
      font-size: 1.4rem;
      cursor: pointer;
    }
    .action-buttons {
      display: flex;
      gap: 6px;
      justify-content: flex-end;
    }
    .btn-icon {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: none;
      cursor: pointer;
      font-size: 0.85rem;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .btn-icon.view { color: #2563eb; background: #eff6ff; }
    .btn-icon.retry { color: #d97706; background: #fffbeb; }
    .btn-icon.delete { color: #dc2626; background: #fef2f2; }

    .btn-icon:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
    }
    .btn-icon.view:hover { background: #2563eb; color: #fff; }
    .btn-icon.retry:hover { background: #d97706; color: #fff; }
    .btn-icon.delete:hover { background: #dc2626; color: #fff; }

    @media (max-width: 900px) {
      .logs-toolbar { flex-direction: column; }
      .toolbar-group { width: 100%; justify-content: flex-start; }
      .toolbar-group .search-input { width: 100%; min-width: unset; }
      .modal-row.split { grid-template-columns: 1fr; }
    }
    @media (max-width: 680px) {
      header { flex-direction: column; align-items: stretch; }
      .greeting-section { flex-direction: column; }
      .logs-table { min-width: 100%; }
      .modal-actions { flex-direction: column-reverse; }
      .modal-actions .toolbar-button { width: 100%; min-width: unset; }
    }
  </style>
</head>

<body>
  <!-- Sidebar Component -->
  <div id="sidebar-container"></div>
  <div id="sidebar-overlay" onclick="toggleSidebar()"></div>
  <div class="content-wrapper">

<main>
    <!-- Greeting Section -->
    <section class="greeting-section">
      <div class="greeting-content">
        <h1>Gmail Notifications</h1>
        <p>Manage and monitor your email notifications here.</p>
      </div>
      <div class="greeting-icon">📩</div>
    </section>

    <!-- Dashboard Overview -->
    <section class="balance-section">
      <h2>Email Metrics</h2>
      <div class="balance-cards">
        <div class="balance-card">
          <h3>Sent Today</h3>
          <p class="amount" id="sentToday">0</p>
        </div>
        <div class="balance-card">
          <h3>Failed Emails</h3>
          <p class="amount" id="failedEmails">0</p>
        </div>
        <div class="balance-card">
          <h3>Pending Emails</h3>
          <p class="amount" id="pendingEmails">0</p>
        </div>
        <div class="balance-card">
          <h3>Total Emails Sent</h3>
          <p class="amount" id="totalEmailsSent">0</p>
        </div>
      </div>
    </section>

    <!-- Email Logs Table -->
    <section class="logs-section">
      <h2>Email Logs</h2>
      <div class="logs-toolbar">
        <div class="toolbar-group">
          <input id="searchInput" class="search-input" type="search" placeholder="Search recipient, subject or type" />
          <button id="refreshLogsBtn" class="toolbar-button secondary"><i class="fas fa-sync-alt"></i> Refresh</button>
        </div>
        <div class="toolbar-group">
          <select id="statusFilter">
            <option value="">All Status</option>
            <option value="sent">Sent</option>
            <option value="failed">Failed</option>
            <option value="pending">Pending</option>
          </select>
          <select id="typeFilter">
            <option value="">All Types</option>
          </select>
          <input id="fromDate" type="date" />
          <input id="toDate" type="date" />
          <button id="sendEmailBtn" class="toolbar-button primary"><i class="fas fa-paper-plane"></i> Send Email</button>
        </div>
      </div>
      <div style="overflow-x:auto;">
        <table class="logs-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Recipient</th>
              <th>Subject</th>
              <th>Notification Type</th>
              <th>Status</th>
              <th>Date & Time Sent</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody id="emailLogsBody">
            <tr><td colspan="7" class="empty-state">Loading email logs...</td></tr>
          </tbody>
        </table>
      </div>
    </section>
  </main>

  <div class="toast-container" id="toastContainer"></div>

  <div class="modal-overlay" id="emailModal">
    <div class="modal-card">
      <button class="modal-close" id="modalCloseBtn" aria-label="Close modal">×</button>
      <h3 id="modalTitle">Send Email Notification</h3>
      <div class="modal-row split">
        <div>
          <label for="modalRecipient">Recipient Email</label>
          <input id="modalRecipient" type="email" placeholder="user@example.com" />
        </div>
        <div>
          <label for="modalStatus">Log Status</label>
          <input id="modalStatus" type="text" readonly style="font-weight: 700; text-transform: uppercase; background: #f8fafc;" />
        </div>
        <div>
          <label for="modalType">Notification Type</label>
          <select id="modalType">
            <option value="System Alert">System Alert</option>
            <option value="User Notification">User Notification</option>
            <option value="Reminder">Reminder</option>
            <option value="Report">Report</option>
            <option value="Other">Other</option>
          </select>
        </div>
      </div>
      <div class="modal-row">
        <label for="modalSubject">Subject</label>
        <input id="modalSubject" type="text" placeholder="Enter email subject" />
      </div>
      <div class="modal-row">
        <label for="modalMessage">Message</label>
        <textarea id="modalMessage" placeholder="Write your notification message"></textarea>
      </div>
      <div class="modal-row">
        <label for="modalAttachments" class="flex items-center gap-2 cursor-pointer text-blue-600 hover:text-blue-800 font-semibold text-sm transition-colors">
          <i class="fas fa-paperclip"></i> Add Attachments (Images/Files)
        </label>
        <input id="modalAttachments" name="attachments[]" type="file" multiple class="hidden" onchange="window.updateAttachmentUI()" />
        <div id="attachmentPreview" class="flex flex-wrap gap-2 mt-2"></div>
        <p class="text-[10px] text-gray-400 mt-1 italic">Maximum 5MB per file. Allowed: JPG, PNG, PDF, DOCX, ZIP, CSV.</p>
      </div>
      <div class="modal-actions">
        <button class="toolbar-button secondary" id="modalCancelBtn">Cancel</button>
        <button class="toolbar-button primary" id="modalSendBtn">
          <i class="fas fa-paper-plane"></i> Send Email
        </button>
      </div>
    </div>
  </div>

  <div class="modal-overlay" id="deleteLogModal">
    <div class="modal-card delete-modal-card">
      <button class="modal-close" id="deleteModalCloseBtn" aria-label="Close delete modal">×</button>
      <div class="delete-warning-icon">
        <i class="fas fa-exclamation-triangle"></i>
      </div>
      <h3>Delete Notification Log</h3>
      <p class="delete-modal-message">
        Are you sure you want to delete this notification log?
        <strong>This action cannot be undone.</strong>
      </p>
      <div class="delete-modal-details" id="deleteLogDetails">
        <div><span>Email:</span><strong id="deleteLogRecipient">-</strong></div>
        <div><span>Subject:</span><strong id="deleteLogSubject">-</strong></div>
        <div><span>Sent:</span><strong id="deleteLogSentAt">-</strong></div>
      </div>
      <div class="modal-actions">
        <button class="toolbar-button secondary" id="deleteModalCancelBtn">Cancel</button>
        <button class="toolbar-button delete-btn" id="deleteModalConfirmBtn">
          <i class="fas fa-trash"></i> Delete Log
        </button>
      </div>
    </div>
  </div>

  <script src="../../assets/js/admincashier.js"></script>
  <script>
    // --- Gmail Notification Logic (Local) ---
    const GMAIL_API_URL = '../../actions/get_admincashier_gmail_notifications.php';
    let emailLogs = [];
    let gmailPollInterval = null;
    let selectedDeleteLogId = null;

    function showToast(message, type = 'success') {
      const container = document.getElementById('toastContainer');
      if (!container || !message) return;

      const toast = document.createElement('div');
      toast.className = `toast ${type}`;
      const icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ'
      };
      toast.innerHTML = `
        <div class="toast-icon">${icons[type] || icons.info}</div>
        <div class="toast-message">${escapeHtml(message)}</div>
      `;
      container.appendChild(toast);

      setTimeout(() => {
        toast.classList.add('hide');
        setTimeout(() => toast.remove(), 200);
      }, 4000);
    }

    window.openDeleteLogModal = function(logIdOrObj) {
      const log = typeof logIdOrObj === 'object' ? logIdOrObj : emailLogs.find(e => e.id === logIdOrObj);
      if (!log) return;

      selectedDeleteLogId = log.id;
      document.getElementById('deleteLogRecipient').textContent = log.recipient || 'N/A';
      document.getElementById('deleteLogSubject').textContent = log.subject || '(No Subject)';
      document.getElementById('deleteLogSentAt').textContent = log.created_at ? new Date(log.created_at).toLocaleString() : 'N/A';

      const deleteBtn = document.getElementById('deleteModalConfirmBtn');
      if (deleteBtn) {
        deleteBtn.disabled = false;
        deleteBtn.innerHTML = '<i class="fas fa-trash"></i> Delete Log';
      }

      const modal = document.getElementById('deleteLogModal');
      if (modal) modal.classList.add('open');
    };

    window.closeDeleteModal = function() {
      const modal = document.getElementById('deleteLogModal');
      if (modal) modal.classList.remove('open');
      selectedDeleteLogId = null;

      const deleteBtn = document.getElementById('deleteModalConfirmBtn');
      if (deleteBtn) {
        deleteBtn.disabled = false;
        deleteBtn.innerHTML = '<i class="fas fa-trash"></i> Delete Log';
      }
    };

    window.deleteLog = async function(logId) {
      const id = logId ?? selectedDeleteLogId;
      if (!id) return;

      const deleteBtn = document.getElementById('deleteModalConfirmBtn');
      if (deleteBtn) {
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Deleting...';
      }

      try {
        const response = await fetch(GMAIL_API_URL, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'delete_log', id })
        });
        const result = await response.json();

        if (!result.success) {
          showToast(result.error || 'Failed to delete log.', 'error');
          return;
        }

        showToast('Notification log deleted.', 'success');
        window.closeDeleteModal();
        window.loadGmailData();
      } catch (error) {
        console.error('Delete log error:', error);
        showToast('Unable to delete log. Please try again.', 'error');
      } finally {
        if (deleteBtn) {
          deleteBtn.disabled = false;
          deleteBtn.innerHTML = '<i class="fas fa-trash"></i> Delete Log';
        }
      }
    };

    window.loadGmailData = function() {
      const params = new URLSearchParams();
      const filters = {
        status: 'statusFilter',
        email_type: 'typeFilter',
        from_date: 'fromDate',
        to_date: 'toDate',
        search: 'searchInput'
      };

      for (const [param, id] of Object.entries(filters)) {
        const val = document.getElementById(id)?.value?.trim();
        if (val) params.set(param, val);
      }

      fetch(`${GMAIL_API_URL}?${params.toString()}`)
        .then(async res => {
          const data = await res.json().catch(() => ({}));
          if (!res.ok || !data.success) {
            throw new Error(data.error || 'Unable to load notification logs.');
          }
          return data;
        })
        .then(data => {
          const payload = data.data || data;
          const metricMap = {
            sentToday: payload.sent_today ?? payload.sentToday ?? 0,
            failedEmails: payload.failed_emails ?? payload.failedEmails ?? 0,
            pendingEmails: payload.pending_emails ?? payload.pendingEmails ?? 0,
            totalEmailsSent: payload.total_emails_sent ?? payload.totalEmailsSent ?? 0
          };

          Object.entries(metricMap).forEach(([id, value]) => {
            const el = document.getElementById(id);
            if (el) el.textContent = value ?? 0;
          });

          emailLogs = Array.isArray(payload.email_logs) ? payload.email_logs : [];
          window.renderEmailLogs(emailLogs);
          window.populateEmailTypes(Array.isArray(payload.email_types) ? payload.email_types : []);
        })
        .catch(err => {
          console.error('Gmail data error:', err);
          const body = document.getElementById('emailLogsBody');
          if (body) body.innerHTML = '<tr><td colspan="7" class="empty-state">Unable to load logs.</td></tr>';
          showToast(err.message || 'Unable to load notification logs.', 'error');
        });
    };

    window.renderEmailLogs = function(logs) {
      const body = document.getElementById('emailLogsBody');
      if (!body) return;

      if (!Array.isArray(logs) || logs.length === 0) {
        body.innerHTML = '<tr><td colspan="7" class="empty-state"><i class="fas fa-inbox"></i><p>No transmission records found.</p></td></tr>';
        return;
      }

      body.innerHTML = '';
      logs.forEach(log => {
        const row = document.createElement('tr');
        const statusClass = log.status === 'sent' ? 'status-sent' : log.status === 'failed' ? 'status-failed' : 'status-pending';
        const createdAt = log.created_at || log.sent_at || '';
        const formattedDate = createdAt ? new Date(createdAt).toLocaleString() : 'N/A';
        const subjectText = log.subject || '(No Subject)';
        row.innerHTML = `
          <td>#${escapeHtml(log.id ?? '')}</td>
          <td>${escapeHtml(log.recipient || 'N/A')}</td>
          <td title="${escapeHtml(subjectText)}">${escapeHtml(subjectText)}</td>
          <td>${escapeHtml(log.notification_type || 'General')}</td>
          <td><span class="status-badge ${statusClass}">${escapeHtml(log.status || 'pending')}</span></td>
          <td>${escapeHtml(formattedDate)}</td>
          <td class="text-right">
            <div class="action-buttons">
              <button class="btn-icon view" onclick="window.openEmailModal('view', ${log.id})"><i class="fas fa-eye"></i></button>
              <button class="btn-icon retry" onclick="window.handleEmailRowAction('retry', ${log.id})"><i class="fas fa-redo"></i></button>
              <button class="btn-icon delete" onclick="window.openDeleteLogModal(${log.id})"><i class="fas fa-trash"></i></button>
            </div>
          </td>`;
        body.appendChild(row);
      });
    };

    window.populateEmailTypes = function(types) {
      const filter = document.getElementById('typeFilter');
      if (!filter) return;
      const existing = Array.from(filter.options).map(opt => opt.value);
      types.filter(t => t && !existing.includes(t)).forEach(type => {
        const opt = document.createElement('option');
        opt.value = opt.textContent = type;
        filter.appendChild(opt);
      });
    };

    window.openEmailModal = function(mode, logIdOrObj) {
      const modal = document.getElementById('emailModal');
      if (!modal) return;

      const isSend = mode === 'send';
      const log = typeof logIdOrObj === 'object' ? logIdOrObj : emailLogs.find(e => e.id === logIdOrObj);

      // Show modal and update labels
      modal.classList.add('open');
      document.getElementById('modalTitle').textContent = isSend ? 'Send Email Notification' : 'Email Details';
      document.getElementById('modalSendBtn').style.display = isSend ? 'inline-flex' : 'none';

      // Target DOM elements
      const recipientInput = document.getElementById('modalRecipient');
      const subjectInput = document.getElementById('modalSubject');
      const messageInput = document.getElementById('modalMessage');
      const statusInput = document.getElementById('modalStatus');
      const typeInput = document.getElementById('modalType');
      const attachmentRow = document.getElementById('modalAttachments')?.closest('.modal-row');

      // Populate values - Handle both possible property names for content
      recipientInput.value = log?.recipient || '';
      subjectInput.value = log?.subject || '';
      // Fix: Ensure body displays by checking both database field and API alias
      const rawMessage = log?.email_body || log?.message || '';
      messageInput.value = isSend ? rawMessage : stripHtml(rawMessage);
      statusInput.value = log?.status || 'NEW';
      typeInput.value = log?.notification_type || 'System Alert';

      // Set interaction states based on mode
      recipientInput.readOnly = !isSend;
      subjectInput.readOnly = !isSend;
      messageInput.readOnly = !isSend;
      typeInput.disabled = !isSend;

      // Show/Hide status field based on context
      statusInput.closest('div').style.display = isSend ? 'none' : 'block';
      statusInput.closest('.modal-row').style.gridTemplateColumns = isSend ? '1fr 1fr' : '1.5fr 120px 1fr';

      // Hide attachment controls in view mode for visual clarity
      if (attachmentRow) {
        attachmentRow.style.display = isSend ? 'block' : 'none';
      }

      // Reset file inputs and preview if creating a new message
      if (isSend && !log) {
        document.getElementById('attachmentPreview').innerHTML = '';
        document.getElementById('modalAttachments').value = '';
      }
    };

    window.handleEmailRowAction = function(action, id) {
      if (action === 'retry') {
        fetch(GMAIL_API_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'retry_email', id }) })
          .then(res => res.json())
          .then(res => {
            if (res.success) {
              showToast('Retry initiated successfully.', 'success');
              window.loadGmailData();
            } else {
              showToast(res.error || 'Retry failed.', 'error');
            }
          })
          .catch(() => {
            showToast('Unable to retry email.', 'error');
          });
      } else if (action === 'delete') {
        window.openDeleteLogModal(id);
      }
    };

    window.startGmailPolling = function() {
      if (gmailPollInterval) clearInterval(gmailPollInterval);
      gmailPollInterval = setInterval(window.loadGmailData, 30000);
    };

    function escapeHtml(value) {
      return String(value || '').replace(/[&"'<>]/g, tag => ({ '&': '&amp;', '"': '&quot;', "'": '&#39;', '<': '&lt;', '>': '&gt;' })[tag]);
    }

    function stripHtml(value) {
      let text = String(value || '');
      // Remove style/script blocks entirely so CSS/JS does not remain as text.
      text = text.replace(/<(script|style)[^>]*>[\s\S]*?<\/\1>/gi, '');
      // Preserve common HTML line breaks and block boundaries.
      text = text.replace(/<br\s*\/?>/gi, '\n');
      text = text.replace(/<\/p>|<\/div>|<\/li>|<\/tr>|<\/h[1-6]>/gi, '\n');
      // Strip any remaining tags.
      text = text.replace(/<[^>]+>/g, '');
      // Decode HTML entities, preserving the plain text.
      const dec = document.createElement('div');
      dec.innerHTML = text;
      text = dec.textContent || dec.innerText || '';
      // Normalize whitespace and line breaks.
      text = text.replace(/\r\n|\r/g, '\n').replace(/\n{2,}/g, '\n\n').trim();
      return text;
    }

    // Use the central initializer from admincashier.js
    initializeAdminCashierPage((userData) => {
      // Page-specific data loading for Gmail notifications
      window.loadGmailData();
      window.startGmailPolling();

      // Attach Filter Listeners
      ['searchInput', 'statusFilter', 'typeFilter', 'fromDate', 'toDate'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', window.loadGmailData);
      });
      document.getElementById('refreshLogsBtn')?.addEventListener('click', () => {
        window.loadGmailData();
        showToast('Logs refreshed.', 'info');
      });
      document.getElementById('sendEmailBtn')?.addEventListener('click', () => window.openEmailModal('send'));
      document.getElementById('modalCloseBtn')?.addEventListener('click', () => document.getElementById('emailModal').classList.remove('open'));
      document.getElementById('modalCancelBtn')?.addEventListener('click', () => document.getElementById('emailModal').classList.remove('open'));
      document.getElementById('deleteModalCloseBtn')?.addEventListener('click', window.closeDeleteModal);
      document.getElementById('deleteModalCancelBtn')?.addEventListener('click', window.closeDeleteModal);
      document.getElementById('deleteModalConfirmBtn')?.addEventListener('click', () => window.deleteLog(selectedDeleteLogId));
      document.getElementById('deleteLogModal')?.addEventListener('click', (event) => {
        if (event.target.id === 'deleteLogModal') {
          window.closeDeleteModal();
        }
      });
      document.getElementById('emailModal')?.addEventListener('click', (event) => {
        if (event.target.id === 'emailModal') {
          event.target.classList.remove('open');
        }
      });
      document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        const deleteModal = document.getElementById('deleteLogModal');
        const emailModal = document.getElementById('emailModal');
        if (deleteModal?.classList.contains('open')) {
          window.closeDeleteModal();
        } else if (emailModal?.classList.contains('open')) {
          emailModal.classList.remove('open');
        }
      });

      // --- New Attachment UI Logic ---
      window.updateAttachmentUI = function() {
        const input = document.getElementById('modalAttachments');
        const container = document.getElementById('attachmentPreview');
        if (!input || !container) return;

        const allowedExts = ['jpg', 'jpeg', 'png', 'pdf', 'docx', 'zip', 'csv', 'xlsx'];
        const maxSize = 5 * 1024 * 1024; // 5MB
        
        container.innerHTML = '';
        const validFiles = new DataTransfer();
        
        Array.from(input.files || []).forEach((file) => {
          const ext = file.name.split('.').pop().toLowerCase();
          if (!allowedExts.includes(ext)) {
            showToast(`File type not allowed: ${file.name}`, 'warning');
            return;
          }
          if (file.size > maxSize) {
            showToast(`File too large: ${file.name} (Max 5MB).`, 'warning');
            return;
          }
          
          validFiles.items.add(file);
          
          const size = (file.size / 1024 / 1024).toFixed(2);
          const badge = document.createElement('div');
          badge.className = 'px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-xl text-[11px] flex items-center gap-2 shadow-sm animate-in fade-in duration-200';
          badge.innerHTML = `
            <i class="${file.type.startsWith('image/') ? 'fas fa-image text-blue-500' : 'fas fa-file-alt text-gray-400'}"></i>
            <span class="max-w-[120px] truncate font-medium text-gray-700">${file.name}</span>
            <span class="text-gray-400">${size}MB</span>
            <button onclick="window.removeAttachment('${file.name}')" class="text-gray-400 hover:text-red-500 transition-colors ml-1">
              <i class="fas fa-times-circle"></i>
            </button>
          `;
          container.appendChild(badge);
        });
        
        input.files = validFiles.files;
      };

      window.removeAttachment = function(fileName) {
        const input = document.getElementById('modalAttachments');
        if (!input) return;
        const dt = new DataTransfer();
        Array.from(input.files || []).forEach(file => {
          if (file.name !== fileName) dt.items.add(file);
        });
        input.files = dt.files;
        window.updateAttachmentUI();
      };

      // --- Manual Email Sending Logic ---
      const modalSendBtn = document.getElementById('modalSendBtn');
      if (modalSendBtn) {
        modalSendBtn.addEventListener('click', async () => {
          const recipient = document.getElementById('modalRecipient')?.value?.trim() || '';
          const subject = document.getElementById('modalSubject')?.value?.trim() || '';
          const message = document.getElementById('modalMessage')?.value?.trim() || '';
          const type = document.getElementById('modalType')?.value || 'Manual Notification';
          const fileInput = document.getElementById('modalAttachments');

          if (!recipient) {
            showToast('Recipient email is required.', 'warning');
            return;
          }

          if (!subject || !message) {
            showToast('Subject and message are required.', 'warning');
            return;
          }

          const formData = new FormData();
          formData.append('recipient', recipient);
          formData.append('subject', subject);
          formData.append('message', message);
          formData.append('type', type);
          formData.append('email_type', type);

          Array.from(fileInput?.files || []).forEach(file => {
            formData.append('attachments[]', file);
          });

          modalSendBtn.disabled = true;
          modalSendBtn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Sending...';

          try {
            const response = await fetch('../../actions/send_manual_email.php', {
              method: 'POST',
              body: formData
            });

            let result = {};
            try {
              result = await response.json();
            } catch (err) {
              result = {};
            }

            if (!response.ok) {
              let errorMessage = `Server Error (${response.status})`;
              if (response.status === 404) {
                errorMessage += ': The requested endpoint was not found. Please check the URL.';
              } else if (response.status === 500) {
                errorMessage += ': An internal server error occurred. Please try again later.';
              } else {
                errorMessage += `: ${result.message || 'Unknown error.'}`;
              }
              throw new Error(errorMessage);
            }

            if (result.success) {
              showToast(result.message || 'Email sent successfully.', 'success');
              document.getElementById('emailModal').classList.remove('open');
              if (window.loadGmailData) window.loadGmailData();
            } else {
              showToast(result.message || 'Failed to send email.', 'error');
            }
          } catch (error) {
            console.error('Manual send error:', error);
            showToast(error.message || 'Communication error while sending email.', 'error');
          } finally {
            modalSendBtn.disabled = false;
            modalSendBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Email';
          }
        });
      }
    });
  </script>
</body>
</html>