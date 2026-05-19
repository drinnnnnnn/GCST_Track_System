﻿﻿﻿﻿﻿﻿﻿﻿﻿﻿﻿let currentAdminId = null;
let currentTicket = null;
let emailLogs = [];
let gmailPollInterval = null; // New: For Gmail logs polling
let notificationPollInterval = null;
let queuePollInterval = null;

/**
 * Initialize menu and notification listeners
 * Call this in DOMContentLoaded of every page
 */
function initializeAdminCashierUI() {
  // Menu toggle
  const menuIcon = document.getElementById('menu-icon');
  const dropdownMenu = document.getElementById('dropdown-menu');
  
  if (menuIcon) {
    menuIcon.addEventListener('click', (e) => {
      e.preventDefault();
      dropdownMenu?.classList.toggle('show');
    });
  }

  // Close menu when clicking outside
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.menu-icon') && !e.target.closest('.dropdown-menu')) {
      dropdownMenu?.classList.remove('show');
    }
  });

  // Notification bell toggle
  const notificationBell = document.getElementById('notification-bell');
  const notificationDropdown = document.getElementById('notification-dropdown');
  
  if (notificationBell) {
    notificationBell.addEventListener('click', (e) => {
      e.preventDefault();
      notificationDropdown?.classList.toggle('show');
    });
  }

  // Close notification dropdown when clicking outside
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.notification-icon') && !e.target.closest('.notification-dropdown')) {
      notificationDropdown?.classList.remove('show');
    }
  });

  // Clear notifications button
  const clearNotifBtn = document.getElementById('clear-notifications');
  if (clearNotifBtn) {
    clearNotifBtn.addEventListener('click', clearAllNotifications);
  }
}

/**
 * Check authentication and redirect if not logged in
 */
function checkAuthentication() {
  return fetch('../../actions/get_user.php')
    .then(res => res.json())
    .then(data => {
      // Strictly enforce admin roles for admincashier pages
      const allowedRoles = ['admin', 'cashier', 'admincashier', 'superadmin'];
      const currentId = data.admin_id;
      if (!currentId || !allowedRoles.includes(data.role)) {
        window.location.href = "http://localhost/GCST_Track_System/pages/sign_in_admin_cashier.html";
        return null;
      }
      currentAdminId = currentId;
      return data;
    })
    .catch(() => {
      window.location.href = "../../pages/sign_in_admin_cashier.html";
      return null;
    });
}

/**
 * Update greeting message with user name
 */
function updateGreeting(adminName) {
  const greetingElement = document.getElementById('greeting-message');
  if (!greetingElement) return;

  const hour = new Date().getHours();
  const greet = hour < 12 ? 'Good Morning' : hour < 18 ? 'Good Afternoon' : 'Good Evening';
  
  // Preserve page-specific prefixes (like "Cashier • ") if they exist
  const prefix = greetingElement.textContent.includes('•') ? greetingElement.textContent.split('•')[0].trim() + ' • ' : '';
  greetingElement.textContent = prefix ? `${prefix}${adminName}` : `${greet}, ${adminName}!`;
}

/**
 * Update current date and time
 */
function updateDateTime() {
  const dateTimeElement = document.getElementById('current-date-time');
  if (dateTimeElement) {
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    const dateStr = now.toLocaleDateString('en-US', options);
    const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    dateTimeElement.textContent = `${dateStr} • ${timeStr}`;
  }
}

/**
 * Load notifications from server
 */
function loadNotifications() {
  if (!currentAdminId) return;
  fetch(`../../actions/get_notifications.php?admin_id=${encodeURIComponent(currentAdminId)}`)
    .then(res => res.json())
    .then(data => {
      const notificationsList = document.getElementById('notifications-list');
      const notifBadge = document.getElementById('notif-badge');
      const sidebarBadge = document.getElementById('sidebar-gmail-badge');
      
      if (!notificationsList) return;

      notificationsList.innerHTML = '';

      if (data.length === 0) {
        notificationsList.innerHTML = '<div class="empty-state"><p>No notifications</p></div>';
        if (notifBadge) notifBadge.style.display = 'none';
        if (sidebarBadge) sidebarBadge.classList.add('hidden');
        return;
      }

      data.forEach(notif => {
        const item = document.createElement('div');
        item.className = 'notification-item';
        item.innerHTML = `
          ${notif.image ? `<img src="${notif.image}" alt="notification">` : ''}
          <div>
            <div class="notification-message">${notif.message || 'New notification'}</div>
            <div class="notification-time">${notif.time || 'Just now'}</div>
          </div>
        `;
        notificationsList.appendChild(item);
      });

      // Show badge with count
      if (notifBadge && data.length > 0) {
        notifBadge.textContent = data.length;
        notifBadge.style.display = 'flex';
      }

      // Update Sidebar Badge
      if (sidebarBadge) {
        sidebarBadge.textContent = data.length;
        sidebarBadge.classList.remove('hidden');
      }
    })
    .catch(err => console.error('Error loading notifications:', err));
}

/**
 * Clear all notifications
 */
function clearAllNotifications() {
  if (!currentAdminId) return;
  fetch('../../actions/mark_notifications_read.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ admin_id: currentAdminId })
  })
    .then(() => {
      loadNotifications();
    })
    .catch(err => console.error('Error clearing notifications:', err));
}

/**
 * Start polling notifications every 30 seconds
 */
function startNotifPolling() {
  // Clear any existing interval
  if (notificationPollInterval) {
    clearInterval(notificationPollInterval);
  }

  // Poll every 30 seconds
  notificationPollInterval = setInterval(() => {
    loadNotifications();
  }, 30000);
}

/**
 * Start polling Gmail logs every minute
 */
window.startGmailPolling = function() {
  // Clear any existing interval
  if (gmailPollInterval) {
    clearInterval(gmailPollInterval);
  }
  gmailPollInterval = setInterval(window.loadGmailData, 30000); // Poll every 30 seconds
};

/**
 * Stop Gmail logs polling
 */
window.stopGmailPolling = function() {
  if (gmailPollInterval) {
    clearInterval(gmailPollInterval);
    gmailPollInterval = null;
  }
}

/**
 * Stop notification polling
 */
function stopNotifPolling() {
  if (notificationPollInterval) {
    clearInterval(notificationPollInterval);
    notificationPollInterval = null;
  }
}

/**
 * Format currency value
 */
function formatCurrency(value) {
  return '₱' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/**
 * Show loading indicator
 */
function showLoading(element) {
  if (element) {
    element.innerHTML = `
      <div class="loading">
        <div class="spinner"></div>
        <span>Loading...</span>
      </div>
    `;
  }
}

/**
 * Show empty state
 */
function showEmptyState(element, message = 'No data available') {
  if (element) {
    element.innerHTML = `
      <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <h3>No Data</h3>
        <p>${message}</p>
      </div>
    `;
  }
}

/**
 * Show error message
 */
function showError(element, message = 'An error occurred') {
  if (element) {
    element.innerHTML = `
      <div class="empty-state" style="border-color: #ef4444; color: #ef4444;">
        <i class="fas fa-exclamation-circle" style="color: #ef4444;"></i>
        <h3>Error</h3>
        <p>${message}</p>
      </div>
    `;
  }
}

/**
 * Show a temporary toast notification using Tailwind classes
 * @param {string} message - Message to display
 * @param {string} type - 'success' or 'error'
 */
window.showInventoryToast = function(message, type = 'success') {
  const toast = document.createElement('div');
  // Styling using Tailwind utility classes to match the dashboard aesthetic
  toast.className = `fixed bottom-6 right-6 px-6 py-4 rounded-2xl shadow-2xl z-[9999] flex items-center gap-3 text-white font-medium transform transition-all duration-300 translate-y-20 opacity-0`;
  
  if (type === 'success') {
    toast.classList.add('bg-emerald-600');
    toast.innerHTML = `<i class="fas fa-check-circle text-xl"></i><span>${message}</span>`;
  } else {
    toast.classList.add('bg-rose-600');
    toast.innerHTML = `<i class="fas fa-exclamation-triangle text-xl"></i><span>${message}</span>`;
  }

  document.body.appendChild(toast);
  setTimeout(() => toast.classList.remove('translate-y-20', 'opacity-0'), 10);
  setTimeout(() => {
    toast.classList.add('translate-y-20', 'opacity-0');
    setTimeout(() => toast.remove(), 300);
  }, 4000);
};

/**
 * Initialize page - call this in every page's DOMContentLoaded
 * @param {Function} pageCallback - Callback function to initialize page-specific content
 */
window.initializeAdminCashierPage = function(pageCallback) {
  const init = async () => {
    try {
      // 1. Load sidebar first so the user sees the UI immediately
      await autoLoadSidebar();
      
      // 2. Start authentication check
      const userData = await checkAuthentication();
      if (!userData) return; // checkAuthentication handles the redirect

      // 3. Initialize UI elements now that sidebar is in the DOM
      initializeAdminCashierUI();

      // Update greeting and time
      updateGreeting(userData.name || 'Admin');
      updateDateTime();
      setInterval(updateDateTime, 60000); // Update every minute

      // Load notifications
      loadNotifications();
      startNotifPolling();

      // Call page-specific callback
      if (pageCallback && typeof pageCallback === 'function') {
        pageCallback(userData);
      }
    } catch (error) {
      console.error('Error initializing page:', error);
    }
  };

  if (document.readyState === 'loading') {
    window.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // Clean up on page unload
  window.addEventListener('beforeunload', () => {
    stopNotifPolling();
    stopGmailPolling(); // Stop Gmail polling on unload
    if (typeof stopQueuePolling === 'function') stopQueuePolling();
  });
}

/**
 * Fetch and handle errors
 */
function fetchWithError(url, options = {}) {
  return fetch(url, options)
    .then(res => {
      if (!res.ok) {
        throw new Error(`HTTP error! status: ${res.status}`);
      }
      return res.json();
    })
    .catch(err => {
      console.error('Fetch error:', err);
      throw err;
    });
}

/* =====================================================
  GMAIL NOTIFICATION FUNCTIONS
   ===================================================== */

const GMAIL_API_URL = '../../actions/get_admincashier_gmail_notifications.php';

window.loadGmailData = function() {
  const params = new URLSearchParams();
  const filters = { status: 'statusFilter', email_type: 'typeFilter', from_date: 'fromDate', to_date: 'toDate', search: 'searchInput' };
  
  for (const [param, id] of Object.entries(filters)) {
    const val = document.getElementById(id)?.value?.trim();
    if (val) params.set(param, val);
  }

  fetch(`${GMAIL_API_URL}?${params.toString()}`)
    .then(res => res.json())
    .then(data => {
      // Support both structured { success: true, data: { ... } } and flat responses
      const payload = data.data || data;

      ['sentToday', 'failedEmails', 'pendingEmails', 'totalEmailsSent'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = payload[id.replace(/[A-Z]/g, l => `_${l.toLowerCase()}`)] ?? 0;
      });
      emailLogs = Array.isArray(payload.email_logs) ? payload.email_logs : [];
      renderEmailLogs(emailLogs);
      populateEmailTypes(payload.email_types || []);
    })
    .catch(err => {
      console.error('Gmail data error:', err);
      if (document.getElementById('emailLogsBody')) document.getElementById('emailLogsBody').innerHTML = '<tr><td colspan="7" class="empty-state">Unable to load logs.</td></tr>';
    });
};

function renderEmailLogs(logs) {
  const body = document.getElementById('emailLogsBody');
  if (!body) return;
  body.innerHTML = logs.length ? '' : '<tr><td colspan="7" class="empty-state">No logs available.</td></tr>';
  logs.forEach(log => {
    const row = document.createElement('tr');
    const statusClass = log.status === 'sent' ? 'status-sent' : log.status === 'failed' ? 'status-failed' : 'status-pending';
    row.innerHTML = `
      <td>${log.id}</td>
      <td>${escapeHtml(log.recipient)}</td>
      <td>${escapeHtml(log.subject)}</td>
      <td>${escapeHtml(log.notification_type)}</td>
      <td><span class="status-badge ${statusClass}">${escapeHtml(log.status)}</span></td>
      <td>${new Date(log.created_at).toLocaleString()}</td>
      <td>
        <div class="action-buttons">
          <button class="action-button view" onclick="openEmailModal('view', ${log.id})">View</button>
          <button class="action-button retry" onclick="handleEmailRowAction('retry', ${log.id})">Retry</button>
          <button class="action-button delete" onclick="handleEmailRowAction('delete', ${log.id})">Delete</button>
        </div>
      </td>`;
    body.appendChild(row);
  });
}

function populateEmailTypes(types) {
  const filter = document.getElementById('typeFilter');
  if (!filter) return;
  const existing = Array.from(filter.options).map(opt => opt.value);
  types.filter(t => t && !existing.includes(t)).forEach(type => {
    const opt = document.createElement('option');
    opt.value = opt.textContent = type;
    filter.appendChild(opt);
  });
}

window.openEmailModal = function(mode, logIdOrObj) {
  const modal = document.getElementById('emailModal');
  if (!modal) return;
  const log = typeof logIdOrObj === 'object' ? logIdOrObj : emailLogs.find(e => e.id === logIdOrObj);
  modal.classList.add('open');
  document.getElementById('modalTitle').textContent = mode === 'send' ? 'Send Email Notification' : 'Email Details';
  document.getElementById('modalSendBtn').style.display = mode === 'send' ? 'inline-flex' : 'none';
  document.getElementById('modalRecipient').value = log?.recipient || '';
  document.getElementById('modalSubject').value = log?.subject || '';
  document.getElementById('modalMessage').value = log?.message || '';
  document.getElementById('modalType').value = log?.notification_type || 'System Alert';
};

window.closeEmailModal = function() { document.getElementById('emailModal')?.classList.remove('open'); };

window.submitSendEmail = function() {
  const payload = {
    action: 'send_email',
    recipient: document.getElementById('modalRecipient').value.trim(),
    subject: document.getElementById('modalSubject').value.trim(),
    message: document.getElementById('modalMessage').value.trim(),
    email_type: document.getElementById('modalType').value.trim()
  };
  fetch(GMAIL_API_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
    .then(res => res.json()).then(res => res.success ? (closeEmailModal(), loadGmailData(), showInventoryToast('Notification sent successfully!')) : showInventoryToast(res.error, 'error')).catch(() => showInventoryToast('Send failed.', 'error'));
};

window.handleEmailRowAction = function(action, id) {
  if (action === 'retry') fetch(GMAIL_API_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'retry_email', id }) }).then(res => res.json()).then(res => res.success ? loadGmailData() : alert(res.error));
  else if (action === 'delete' && confirm('Delete this log?')) fetch(GMAIL_API_URL, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'delete_log', id }) }).then(res => res.json()).then(res => res.success ? loadGmailData() : alert(res.error));
};

window.attachEmailFilters = function() {
  ['searchInput', 'statusFilter', 'typeFilter', 'fromDate', 'toDate'].forEach(id => {
      const el = document.getElementById(id);
      if (el) {
          el.addEventListener('change', loadGmailData);
          if (id === 'searchInput') el.addEventListener('keyup', e => { if (e.key === 'Enter') loadGmailData(); });
      }
  });
  document.getElementById('refreshLogsBtn')?.addEventListener('click', loadGmailData);
  document.getElementById('sendEmailBtn')?.addEventListener('click', () => openEmailModal('send'));
  document.getElementById('modalCloseBtn')?.addEventListener('click', closeEmailModal);
  document.getElementById('modalCancelBtn')?.addEventListener('click', closeEmailModal);
  document.getElementById('modalSendBtn')?.addEventListener('click', submitSendEmail);
};

function escapeHtml(value) {
  return String(value || '').replace(/[&"'<>]/g, tag => ({ '&': '&amp;', '"': '&quot;', "'": '&#39;', '<': '&lt;', '>': '&gt;' })[tag]);
}

/* =====================================================
  QUEUE MANAGEMENT FUNCTIONS
   ===================================================== */

window.openQueueDisplay = function() {
  const panel = document.getElementById('queue-display-panel');
  if (panel) panel.style.display = 'flex';
  window.loadQueueStatus();
}

window.closeQueueDisplay = function() {
  const panel = document.getElementById('queue-display-panel');
  if (panel) panel.style.display = 'none';
}

window.loadQueueStatus = function() {
  fetch('../../actions/get_queue_status.php')
    .then(res => res.json())
    .then(data => {
      const timeEl = document.getElementById('display-current-time');
      const servingEl = document.getElementById('display-now-serving');
      const nextEl = document.getElementById('display-next-queue');
      if (timeEl) timeEl.textContent = data.current_time;
      if (servingEl) servingEl.textContent = data.now_serving || 'None';
      if (nextEl) nextEl.textContent = data.next_queue || 'None';
    })
    .catch(err => console.error('Error loading queue status:', err));
}

window.generateQueue = function() {
  fetch('../../actions/generate_queue.php', { method: 'POST' })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        currentTicket = data.ticket || {};
        window.showTicketPreview(currentTicket);
        window.loadActiveQueues();
        window.loadQueueStatus();
        showInventoryToast('New queue number generated!');
      } else {
        showInventoryToast('Failed to generate queue: ' + data.error, 'error');
      }
    })
    .catch(err => console.error('Error generating queue:', err));
}

window.showTicketPreview = function(ticket) {
  const panel = document.getElementById('ticket-preview-panel');
  const numEl = document.getElementById('preview-queue-number');
  const timeEl = document.getElementById('preview-generated-at');
  if (panel) panel.style.display = 'block';
  if (numEl) numEl.textContent = ticket.queue_number;
  if (timeEl) timeEl.textContent = new Date(ticket.created_at).toLocaleString();
}

window.saveTicket = function() {
  if (!currentTicket?.queue_number) return;
  const ticketContent = `Queue Ticket\nNumber: ${currentTicket.queue_number}\nGenerated: ${new Date(currentTicket.created_at).toLocaleString()}`;
  const blob = new Blob([ticketContent], { type: 'text/plain' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = `queue-${currentTicket.queue_number}.txt`;
  link.click();
}

window.printTicket = function() {
  if (!currentTicket?.queue_number) return;
  const printWindow = window.open('', '_blank');
  printWindow.document.write(`<html><body><div style="text-align:center;border:2px dashed #000;padding:20px;"><h1>Queue Ticket</h1><h2>${currentTicket.queue_number}</h2><p>${new Date(currentTicket.created_at).toLocaleString()}</p></div></body></html>`);
  printWindow.document.close();
  printWindow.print();
}

window.loadActiveQueues = function() {
  fetch('../../actions/get_active_queues.php')
    .then(res => res.json())
    .then(data => {
      const queueList = document.getElementById('queue-list');
      if (!queueList) return;
      queueList.innerHTML = '';
      const queues = data.queues || [];
      if (queues.length === 0) {
        queueList.innerHTML = '<div class="empty-state"><p>No active queues at the moment.</p></div>';
        return;
      }
      queues.forEach(queue => {
        const item = document.createElement('div');
        item.className = 'queue-item';
        item.innerHTML = `
          <div class="queue-details">
            <div class="queue-number">${queue.queue_number}</div>
            <div class="queue-status">
              <span class="status-badge status-${queue.status}">${queue.status.toUpperCase()}</span>
              <span>${new Date(queue.created_at).toLocaleTimeString()}</span>
            </div>
          </div>
          <div class="queue-actions">
            ${queue.status === 'waiting' ? `<button class="queue-btn btn-serve" onclick="updateQueueStatus(${queue.id}, 'serving')">Serve</button>` : ''}
            ${queue.status === 'serving' ? `<button class="queue-btn btn-complete" onclick="updateQueueStatus(${queue.id}, 'completed')">Complete</button>` : ''}
            ${queue.status !== 'completed' ? `<button class="queue-btn btn-remove" onclick="updateQueueStatus(${queue.id}, 'cancelled')">Cancel</button>` : ''}
          </div>
        `;
        queueList.appendChild(item);
      });
    })
    .catch(err => console.error('Error loading active queues:', err));
}

window.updateQueueStatus = function(queueId, status) {
  fetch('../../actions/update_queue_status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ queue_id: queueId, status: status })
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      window.loadActiveQueues();
      window.loadQueueStatus();
      showInventoryToast(`Queue status updated to ${status}.`);
    } else {
      showInventoryToast('Error: ' + data.error, 'error');
    }
  })
  .catch(err => console.error('Error updating status:', err));
}

/**
 * Polling logic for Queue System Dashboard
 */
window.startQueuePolling = function() {
  if (queuePollInterval) clearInterval(queuePollInterval);
  queuePollInterval = setInterval(() => {
    if (document.getElementById('queue-list')) {
      window.loadActiveQueues();
      window.loadQueueStatus();
    }
  }, 10000); // 10s sync
};

window.stopQueuePolling = function() {
  if (queuePollInterval) { clearInterval(queuePollInterval); queuePollInterval = null; }
};

/* =====================================================
  SIDEBAR AUTO-LOADER & LOGIC
   ===================================================== */

window.toggleSidebar = function() {
  const sidebar = document.getElementById('main-sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  sidebar?.classList.toggle('active');
  overlay?.classList.toggle('active');
}

window.toggleMinimizeSidebar = function() {
  const sidebar = document.getElementById('main-sidebar');
  const contentWrapper = document.querySelector('.content-wrapper');
  const header = document.querySelector('header');

  const isMinimized = sidebar?.classList.toggle('minimized');
  contentWrapper?.classList.toggle('minimized');
  header?.classList.toggle('minimized');

  // Persist the state in localStorage
  localStorage.setItem('sidebar-minimized', isMinimized ? 'true' : 'false');
}

async function autoLoadSidebar() {
  const container = document.getElementById('sidebar-container');
  if (!container) return;

  try {
    // Ensure Chart.js is loaded before attempting to use it
    if (typeof Chart === 'undefined') {
      console.warn('Chart.js is not loaded. Charts functionality may be limited.');
    }

    // Use dynamic import instead of relying on global window object
    const { getSidebarHTML } = await import('./admincashier_sidebar_content.js');
    if (container && typeof getSidebarHTML === 'function') {
      container.innerHTML = getSidebarHTML();

      // Apply the saved state from localStorage immediately after injection
      const isMinimized = localStorage.getItem('sidebar-minimized') === 'true';
      if (isMinimized) {
        document.getElementById('main-sidebar')?.classList.add('minimized');
        document.querySelector('.content-wrapper')?.classList.add('minimized');
        document.querySelector('header')?.classList.add('minimized');
      }
      
      // Automatically highlight the active link based on the current URL
      const getFileName = (path) => path.split('/').pop() || 'admincashier_dashb.html';
      const currentFile = getFileName(window.location.pathname);
      
      const sidebarLinks = container.querySelectorAll('.sidebar-link');
      sidebarLinks.forEach(link => {
        // Use link.pathname property to get the resolved path without query strings or hashes
        const linkFile = getFileName(link.pathname);
        if (linkFile && linkFile === currentFile && !link.href.startsWith('javascript')) {
          link.classList.add('active');
        } else {
          link.classList.remove('active');
        }
      });
    }
  } catch (err) {
    console.error('Sidebar auto-load failed:', err);
  }
}

/**
 * Initialization helper:
 * Only auto-loads the sidebar if initializeAdminCashierPage hasn't been called.
 */
document.addEventListener('DOMContentLoaded', () => {
  // Centralized backend connection check
  fetch('../../actions/get_user.php')
    .then(res => res.json())
    .then(data => {
      if (data.admin_id) {
        currentAdminId = data.admin_id;
        updateGreeting(data.name || data.username || 'Admin');
        loadNotifications();
        startNotifPolling();
        // Auto-init Gmail features if the container exists
        if (document.getElementById('emailLogsBody')) { 
          loadGmailData(); 
          attachEmailFilters(); 
          window.startGmailPolling();
        }
      }
    });

  const container = document.getElementById('sidebar-container');
  if (container && container.innerHTML.trim() === "") {
    autoLoadSidebar();
  }
});

// =====================================================
// SALES PAGE FUNCTIONS (Moved from admincashier_sale.html)
// =====================================================
let currentSalesPeriod = 'today';
let salesTrendChartInstance = null;
let topProductsChartInstance = null;
let salesPollInterval = null;
let lastFetchedSalesHistory = [];

function formatDate(value) {
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? '-' : date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function setActivePeriodButton(period) {
  document.querySelectorAll('.period-button').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.period === period);
  });
}

async function loadSalesData(period) {
  try {
    const response = await fetch(`../../actions/get_admincashier_sales.php?period=${encodeURIComponent(period)}&limit=100`);
    const data = await response.json();

    if (!data.success) {
        console.error('Backend error:', data.message);
        return;
    }

    const totalSales = Number(data.total_sales ?? data.total_sales_today ?? 0);
    const totalTransactions = Number(data.total_transactions ?? 0);
    const avgValue = Number(data.average_transaction_value ?? (totalTransactions > 0 ? (totalSales / totalTransactions) : 0));
    const itemsSold = Number(data.total_items_sold ?? data.books_sold ?? 0);

    document.getElementById('totalSales').textContent = formatCurrency(totalSales);
    document.getElementById('totalTransactions').textContent = totalTransactions;
    document.getElementById('avgTransaction').textContent = formatCurrency(avgValue);
    document.getElementById('itemsSold').textContent = itemsSold;

    if (!salesTrendChartInstance) {
      salesTrendChartInstance = createChart('salesTrendChart', 'line', data.sales_labels || [], data.sales_data || [], 'Daily Sales (₱)');
    } else {
      updateChart(salesTrendChartInstance, data.sales_labels || [], data.sales_data || [], 'Daily Sales (₱)', 'line', 'rgba(69, 88, 255, 0.16)', '#4558ff');
    }

    if (!topProductsChartInstance) {
      topProductsChartInstance = createChart('topProductsChart', 'bar', data.top_products?.map(item => item.name) || [], data.top_products?.map(item => item.quantity) || [], 'Units Sold');
    } else {
      updateChart(topProductsChartInstance, data.top_products?.map(item => item.name) || [], data.top_products?.map(item => item.quantity) || [], 'Units Sold', 'bar', 'rgba(69, 88, 255, 0.16)', '#4558ff');
    }

    lastFetchedSalesHistory = Array.isArray(data.history) ? data.history : [];
    renderSalesHistory(lastFetchedSalesHistory);

  } catch (error) {
    console.error('Unable to load sales data:', error);
    document.getElementById('historyBody').innerHTML = '<tr><td colspan="6" class="empty-state">Unable to load sales history.</td></tr>';
  }
}

/**
 * Render the sales history table rows
 */
function renderSalesHistory(history) {
  const historyBody = document.getElementById('historyBody');
  if (!historyBody) return;
  historyBody.innerHTML = '';
  if (history.length) {
    history.forEach(entry => {
      const items = entry.item ? entry.item.split(', ') : [];
      const itemsHtml = items.map(i => `<span class="product-tag">${i}</span>`).join('');
      
      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${formatDate(entry.date)}</td>
        <td>${entry.transaction_id ?? '—'}</td>
        <td><div class="product-tag-list">${itemsHtml}</div></td>
        <td>${entry.quantity}</td>
        <td>${formatCurrency(entry.amount)}</td>
        <td style="text-align: center;">
          <button onclick="window.viewReceiptDetails('${entry.transaction_id}')" class="view-details-btn" title="View Details">
            <i class="fas fa-eye"></i>
          </button>
        </td>
      `;
      historyBody.appendChild(row);
    });
  } else {
    historyBody.innerHTML = '<tr><td colspan="6" class="empty-state">No sales history found.</td></tr>';
  }
}

function createChart(elementId, type, labels, data, label, backgroundColor, borderColor) {
  const ctx = document.getElementById(elementId).getContext('2d');
  return new Chart(ctx, {
    type,
    data: {
      labels,
      datasets: [{
        label,
        data,
        borderColor: borderColor || '#4558ff',
        backgroundColor: backgroundColor || 'rgba(69, 88, 255, 0.16)',
        fill: type === 'line',
        tension: 0.4,
        borderWidth: 2,
        borderRadius: 12,
        barThickness: 24
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: true } },
      scales: {
        x: { grid: { display: false } },
        y: { grid: { color: '#eef2f7' }, ticks: { beginAtZero: true } }
      }
    }
  });
}

function updateChart(chartRef, labels, data, label, type, backgroundColor, borderColor) {
  if (!chartRef) return;
  chartRef.data = {
    labels,
    datasets: [{
      label,
      data,
      borderColor: borderColor || '#4558ff',
      backgroundColor: backgroundColor || 'rgba(69, 88, 255, 0.16)',
      fill: type === 'line',
      tension: 0.4,
      borderWidth: 2,
      borderRadius: 12,
      barThickness: 24
    }]
  };
  chartRef.update();
}

window.viewReceiptDetails = async function(transactionId) {
  const modal = document.getElementById('receiptModal');
  const content = document.getElementById('receiptContent');
  
  modal.style.display = 'block';
  content.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin fa-2x text-blue-500"></i><p class="mt-2 text-gray-500">Fetching receipt...</p></div>';

  try {
    const response = await fetch(`../../actions/get_transaction_details.php?id=${encodeURIComponent(transactionId)}`);
    const data = await response.json();

    if (data.success) {
      const t = data.transaction;
      content.innerHTML = `
        <div class="bg-gray-50 p-4 rounded-2xl mb-4">
          <div class="flex justify-between text-sm text-gray-500 mb-1"><span>Ref No:</span><span class="font-mono font-bold text-gray-800">${t.transaction_number}</span></div>
          <div class="flex justify-between text-sm text-gray-500"><span>Date:</span><span class="text-gray-800">${new Date(t.created_at).toLocaleString()}</span></div>
        </div>
        <div class="space-y-2">
          <h4 class="font-bold text-gray-700 text-sm uppercase tracking-wider">Items</h4>
          <div class="border-y border-dashed border-gray-200 py-3">
            ${t.items.map(item => `
              <div class="flex justify-between py-1">
                <span class="text-gray-700">${item.product_name} x ${item.quantity}</span>
                <span class="font-semibold">${formatCurrency(item.total_item_amount)}</span>
              </div>
            `).join('')}
          </div>
          <div class="flex justify-between pt-2 text-lg font-bold text-blue-600">
            <span>Total Amount</span>
            <span>${formatCurrency(t.total_amount)}</span>
          </div>
        </div>
      `;
    } else {
      content.innerHTML = `<div class="text-center text-red-500 p-4">Failed to load transaction details: ${data.message || 'Unknown error'}.</div>`;
    }
  } catch (error) {
    console.error('Error fetching transaction details:', error);
    content.innerHTML = '<div class="text-center text-red-500 p-4">Failed to load transaction details due to network error.</div>';
  }
}

window.closeReceiptModal = function() {
  document.getElementById('receiptModal').style.display = 'none';
}

function startSalesPolling() {
  if (salesPollInterval) clearInterval(salesPollInterval);
  salesPollInterval = setInterval(() => {
    loadSalesData(currentSalesPeriod);
  }, 10000);
}

function stopSalesPolling() {
  if (salesPollInterval) clearInterval(salesPollInterval);
  salesPollInterval = null;
}

window.exportSalesToCSV = function() {
  if (!lastFetchedSalesHistory || lastFetchedSalesHistory.length === 0) {
    alert('No sales data available to export for this period.');
    return;
  }
  const headers = ['Date', 'Transaction ID', 'Product(s)', 'Quantity', 'Amount'];
  const rows = lastFetchedSalesHistory.map(item => [
    `"${formatDate(item.date)}"`,
    `"${item.transaction_id || ''}"`,
    `"${item.item.replace(/"/g, '""')}"`,
    item.quantity,
    item.amount
  ]);
  const csvContent = headers.join(",") + "\n" + rows.map(r => r.join(",")).join("\n");
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.setAttribute("href", url);
  link.setAttribute("download", `sales_report_${currentSalesPeriod}_${new Date().toISOString().split('T')[0]}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}

// Main initialization for the sales page
window.initAdminCashierSalesPage = function(userData) {
  // Set initial period and load data
  currentSalesPeriod = 'today';
  setActivePeriodButton(currentSalesPeriod); // Ensure initial button state is set
  loadSalesData(currentSalesPeriod);
  startSalesPolling();

  // Attach event listeners for period buttons
  document.querySelectorAll('.period-button[data-period]').forEach(button => {
    button.addEventListener('click', () => {
      currentSalesPeriod = button.dataset.period;
      setActivePeriodButton(currentSalesPeriod);
      loadSalesData(currentSalesPeriod);
      startSalesPolling(); // Reset interval timer on manual change
    });
  });

  // Attach event listener for export button
  document.getElementById('export-csv-btn').addEventListener('click', window.exportSalesToCSV);

  // Attach event listener for history search
  const searchInput = document.getElementById('historySearch');
  if (searchInput) {
    searchInput.addEventListener('input', (e) => {
      const query = e.target.value.toLowerCase();
      const filtered = lastFetchedSalesHistory.filter(entry => {
        const text = `${entry.transaction_id || ''} ${entry.item || ''} ${entry.date || ''}`.toLowerCase();
        return text.includes(query);
      });
      renderSalesHistory(filtered);
    });
  }

  // Clean up sales polling on page unload
  window.addEventListener('beforeunload', () => {
    stopSalesPolling();
  });
};

// =====================================================
// PROFILE PAGE FUNCTIONS (Moved from admincashier_profile.html)
// =====================================================
window.switchTab = function(tab) {
  document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  document.querySelector(`.tab-btn[onclick="switchTab('${tab}')"]`).classList.add('active');
};

window.saveChanges = async function(event) {
  event.preventDefault();
  if (!confirm('Are you sure you want to save new information?')) {
    return;
  }

  const payload = {
    full_name: document.getElementById('full-name').value.trim(),
    email: document.getElementById('email').value.trim(),
    contact_number: document.getElementById('phone').value.trim()
  };

  try {
    const result = await fetchWithError('../../actions/update_admincashier_profile.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    if (result.success) {
      alert('Profile updated successfully.');
      // Update the profile name in the UI card immediately
      const profileName = document.getElementById('profile-name');
      if (profileName) profileName.textContent = payload.full_name;
    } else {
      throw new Error(result.message || 'Unable to update profile.');
    }
  } catch (error) {
    alert(error.message || 'Unable to update profile.');
  }
};

window.changePassword = async function(event) {
  event.preventDefault();

  if (!confirm('Are you sure you want to change your password?')) {
    return;
  }

  const currentPassword = document.getElementById('current-password').value.trim();
  const newPassword = document.getElementById('new-password').value.trim();
  const confirmPassword = document.getElementById('confirm-password').value.trim();

  if (!currentPassword || !newPassword || !confirmPassword) {
    alert('Please fill all password fields.');
    return;
  }
  if (newPassword !== confirmPassword) {
    alert('New password and confirmation do not match.');
    return;
  }
  if (newPassword.length < 8) {
    alert('New password must be at least 8 characters long.');
    return;
  }

  try {
    const result = await fetchWithError('../../actions/change_admincashier_password.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ current_password: currentPassword, new_password: newPassword, confirm_password: confirmPassword })
    });

    if (result.success) {
      document.getElementById('current-password').value = '';
      document.getElementById('new-password').value = '';
      document.getElementById('confirm-password').value = '';
      alert('Password changed successfully.');
    } else {
      throw new Error(result.message || 'Unable to change password.');
    }
  } catch (error) {
    alert(error.message || 'Unable to change password.');
  }
};

window.saveNotifications = async function() {
  if (!confirm('Are you sure you want to save notification preferences?')) {
    return;
  }

  const payload = {
    email_notifications: document.getElementById('email-notif').checked,
    rental_reminders: document.getElementById('rental-notif').checked,
    payment_reminders: document.getElementById('payment-notif').checked,
    queue_notifications: document.getElementById('queue-notif').checked,
    system_updates: document.getElementById('system-notif').checked,
  };

  try {
    const result = await fetchWithError('../../actions/save_admincashier_notification_preferences.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });

    if (!result.success) {
      throw new Error(result.message || 'Unable to save notification preferences.');
    }

    alert('Notification preferences saved successfully.');
  } catch (error) {
    alert(error.message || 'Unable to save notification preferences.');
  }
};

window.initAdminCashierProfilePage = async function(userData) {
  try {
    const response = await fetchWithError('../../actions/get_admincashier_profile_data.php');
    const admin = response.admin || {};

    // Update profile card and forms
    const profileName = document.getElementById('profile-name');
    if (profileName) profileName.textContent = admin.full_name || admin.username || 'Admin Cashier';
    if (document.getElementById('profile-admin-id')) document.getElementById('profile-admin-id').textContent = 'Admin ID: ' + (admin.admin_id || '---');
    if (document.getElementById('profile-role')) document.getElementById('profile-role').textContent = 'Role: ' + (admin.role || '---');
    if (document.getElementById('username')) document.getElementById('username').value = admin.username || '';
    if (document.getElementById('full-name')) document.getElementById('full-name').value = admin.full_name || '';
    if (document.getElementById('email')) document.getElementById('email').value = admin.email || '';
    if (document.getElementById('phone')) document.getElementById('phone').value = admin.contact_number || '';
    if (document.getElementById('role')) document.getElementById('role').value = admin.role || '';
    if (document.getElementById('admin-id')) document.getElementById('admin-id').value = admin.admin_id || '';
    if (document.getElementById('account-created')) document.getElementById('account-created').textContent = admin.created_at ? new Date(admin.created_at).toLocaleDateString() : 'N/A';
    if (document.getElementById('last-login')) document.getElementById('last-login').textContent = admin.last_login ? new Date(admin.last_login).toLocaleDateString() : 'N/A';

    const prefs = admin.notification_preferences || {};
    if (document.getElementById('email-notif')) document.getElementById('email-notif').checked = prefs.email_notifications ?? true;
    if (document.getElementById('rental-notif')) document.getElementById('rental-notif').checked = prefs.rental_reminders ?? true;
    if (document.getElementById('payment-notif')) document.getElementById('payment-notif').checked = prefs.payment_reminders ?? true;
    if (document.getElementById('queue-notif')) document.getElementById('queue-notif').checked = prefs.queue_notifications ?? true;
    if (document.getElementById('system-notif')) document.getElementById('system-notif').checked = prefs.system_updates ?? true;
  } catch (error) { console.error('Profile initialization failed:', error); }
};

// =====================================================
// INVENTORY PAGE FUNCTIONS (Moved from admincashier_inventorys.html)
// =====================================================
let inventoryProducts = [];
let filteredInventoryProducts = [];
let selectedInventoryProduct = null;
const inventoryFilterState = {
  query: '',
  category: 'All'
};

const INVENTORY_API = '../../actions/get_admincashier_products.php';
const INVENTORY_UPDATE_API = '../../actions/admincashier_update_inventory.php';
const PRODUCT_CREATE_API = '../../actions/admincashier_create_product.php';
const PRODUCT_DELETE_API = '../../actions/admincashier_delete_product.php';

function getInventoryStockStatus(stock) {
  if (stock <= 0) return 'Out of Stock';
  if (stock < 10) return 'Low Stock';
  return 'In Stock';
}

function getInventoryStatusClass(stock) {
  if (stock <= 0) return 'status-out';
  if (stock < 10) return 'status-lowstock';
  return 'status-instock';
}

function resolveInventoryImagePath(path) {
  const IMAGE_FALLBACK = `${window.location.origin}/GCST_Track_System/assets/images/icons/granbylogo.png`;
  if (!path) return IMAGE_FALLBACK;
  if (path.startsWith('http://') || path.startsWith('https://')) return path;
  const cleanPath = path.replace(/^\/+/, '');
  return `${window.location.origin}/GCST_Track_System/${cleanPath}`;
}

function isInventoryBookCategory(category) {
  return String(category || '').trim().toLowerCase() === 'books';
}

function renderInventoryViews() {
  const cardContainer = document.getElementById('products-cards');
  
  if (cardContainer) {
    cardContainer.innerHTML = '';
    if (filteredInventoryProducts.length === 0) {
      cardContainer.innerHTML = '<div class="empty-state">No products match the current search or filters.</div>';
    } else {
      filteredInventoryProducts.forEach(product => {
        const isSelected = selectedInventoryProduct && Number(selectedInventoryProduct.product_id) === Number(product.product_id);
        const card = document.createElement('article');
        card.className = `inventory-card ${isSelected ? 'selected' : ''}`;
        const image = resolveInventoryImagePath(product.product_image);
        const stock = Number(product.stock_count) || 0;
        const description = (product.product_description || '').trim();
        const snippet = description.length > 100 ? `${description.slice(0, 100)}...` : description;
        card.innerHTML = `
          <img src="${image}" alt="${product.product_name}" />
          <div class="inventory-card-body">
            <h3 class="inventory-card-title">${product.product_name || 'Untitled'}</h3>
            ${snippet ? `<p class="product-description">${snippet}</p>` : ''}
            <div class="inventory-card-meta">
              <span>${product.product_category || 'Other'}</span>
            </div>
            <div class="inventory-card-meta">
              <span>${formatCurrency(product.buy_price)}</span>
              <span class="status-pill ${getInventoryStatusClass(stock)}">${product.product_status === 'unavailable' ? 'Unavailable' : getInventoryStockStatus(stock)}</span>
            </div>
            <div class="inventory-card-meta">
              <span>${stock} item${stock === 1 ? '' : 's'}</span>
            </div>
            <div class="inventory-card-footer">
              <button class="btn btn-primary" type="button" onclick="window.selectInventoryProduct(${product.product_id})">Manage</button>
              <button class="btn btn-danger" type="button" onclick="window.deleteInventoryProduct(${product.product_id})">Delete</button>
            </div>
          </div>
        `;
        cardContainer.appendChild(card);
      });
    }
  }
}

function updateInventorySummary() {
  const total = filteredInventoryProducts.length;
  const lowStock = filteredInventoryProducts.filter(p => Number(p.stock_count || 0) < 10).length;
  const available = filteredInventoryProducts.filter(p => Number(p.stock_count || 0) > 0).length;
  const value = filteredInventoryProducts.reduce((sum, p) => sum + ((Number(p.buy_price) || 0) * (Number(p.stock_count) || 0)), 0);
  
  if (document.getElementById('summary-total')) document.getElementById('summary-total').textContent = total;
  if (document.getElementById('summary-low')) document.getElementById('summary-low').textContent = lowStock;
  if (document.getElementById('summary-available')) document.getElementById('summary-available').textContent = available;

  const valueEl = document.getElementById('summary-value');
  if (valueEl) {
    const formatted = formatCurrency(value);
    valueEl.textContent = formatted;

    // Automatic Font Scaling Logic: 
    // Dynamically adjusts the font-size clamp based on character length 
    // to prevent layout overflow when reaching millions or billions.
    const len = formatted.length;
    let dynamicSize = ''; // Reverts to CSS default if value is small

    if (len > 15) {
      dynamicSize = 'clamp(0.9rem, 2.2vw, 1.05rem)';
    } else if (len > 12) {
      dynamicSize = 'clamp(1.0rem, 2.6vw, 1.25rem)';
    } else if (len > 10) {
      dynamicSize = 'clamp(1.1rem, 3vw, 1.45rem)';
    }
    valueEl.style.fontSize = dynamicSize;
  }
}

function applyInventoryFilters() {
  const query = inventoryFilterState.query.trim().toLowerCase();
  const cat = inventoryFilterState.category;
  filteredInventoryProducts = inventoryProducts.filter(p => {
    const matchesCat = cat === 'All' || (p.product_category || 'Other') === cat;
    const text = `${p.product_name || ''} ${p.product_category || ''}`.toLowerCase();
    return matchesCat && (!query || text.includes(query));
  });
  renderInventoryViews();
  updateInventorySummary();
}

function buildInventoryCategoryFilters() {
  const container = document.getElementById('category-filters');
  if (!container) return;
  const base = ['All', 'Uniform', 'Accessories'];
  const extra = Array.from(new Set(inventoryProducts.map(p => p.product_category || 'Other'))).filter(c => c && !base.includes(c));
  container.innerHTML = '';
  [...base, ...extra].forEach(category => {
    const pill = document.createElement('button');
    pill.className = 'filter-pill' + (inventoryFilterState.category === category ? ' active' : '');
    pill.textContent = category;
    pill.onclick = () => {
      inventoryFilterState.category = category;
      buildInventoryCategoryFilters();
      applyInventoryFilters();
    };
    container.appendChild(pill);
  });
}

function updateInventoryDetailPanel() {
  const detailSection = document.getElementById('product-details');
  const emptySection = document.getElementById('detail-empty');
  if (!selectedInventoryProduct) {
    detailSection?.classList.add('hidden');
    emptySection?.classList.remove('hidden');
    return;
  }
  detailSection?.classList.remove('hidden');
  emptySection?.classList.add('hidden');

  const stock = Number(selectedInventoryProduct.stock_count) || 0;

  if (document.getElementById('detail-image')) document.getElementById('detail-image').src = resolveInventoryImagePath(selectedInventoryProduct.product_image);
  if (document.getElementById('detail-name')) document.getElementById('detail-name').textContent = selectedInventoryProduct.product_name || 'Unnamed';
  if (document.getElementById('detail-category')) document.getElementById('detail-category').textContent = selectedInventoryProduct.product_category || 'Other';
  
  const stockLabel = document.getElementById('detail-stock');
  if (stockLabel) {
    stockLabel.textContent = `${stock} item${stock === 1 ? '' : 's'}`;
    stockLabel.className = `status-pill ${getInventoryStatusClass(stock)}`;
  }

  if (document.getElementById('detail-name-input')) document.getElementById('detail-name-input').value = selectedInventoryProduct.product_name || '';
  if (document.getElementById('detail-category-input')) document.getElementById('detail-category-input').value = selectedInventoryProduct.product_category || 'Other';
  if (document.getElementById('detail-buy-input')) document.getElementById('detail-buy-input').value = Number(selectedInventoryProduct.buy_price || 0).toFixed(2);
  if (document.getElementById('detail-status-input')) document.getElementById('detail-status-input').value = selectedInventoryProduct.product_status || 'available';
  if (document.getElementById('detail-stock-input')) document.getElementById('detail-stock-input').value = stock;
}

window.selectInventoryProduct = function(productId) {
  selectedInventoryProduct = inventoryProducts.find(p => Number(p.product_id) === Number(productId)) || null;
  updateInventoryDetailPanel();
  renderInventoryViews();

  // Smooth scroll to details on mobile/tablet for better visibility of the "expansion"
  if (window.innerWidth < 1200 && selectedInventoryProduct) {
    const detailPanel = document.querySelector('.inventory-panel');
    detailPanel?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

window.deleteInventoryProduct = function(productId) {
  if (!productId || !confirm('Are you sure you want to delete this product?')) return;
  (async () => {
    try {
      const response = await fetch(PRODUCT_DELETE_API, {
        method: 'POST',
        headers: { 
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ product_id: productId })
      });

      if (!response.ok) throw new Error(`HTTP Error ${response.status}: ${response.statusText}`);
      
      const text = await response.text();
      let data;
      try { data = JSON.parse(text); } catch (e) { throw new Error('Invalid server response format.'); }

      if (!data.success) throw new Error(data.message || 'Unable to delete product.');

      inventoryProducts = inventoryProducts.filter(p => Number(p.product_id) !== Number(productId));
      if (selectedInventoryProduct && Number(selectedInventoryProduct.product_id) === Number(productId)) {
        selectedInventoryProduct = null;
      }
      applyInventoryFilters();
      updateInventoryDetailPanel();
      showInventoryToast(data.message || 'Product deleted successfully.');
    } catch (err) {
      console.error('Delete operation failed:', err);
      showInventoryToast(err.message || 'A network error occurred while deleting the product.', 'error');
    }
  })();
};

async function refreshInventoryData() {
  const btn = document.getElementById('refresh-button');
  const icon = btn?.querySelector('i');
  const cards = document.getElementById('products-cards');
  
  if (btn) btn.disabled = true;
  if (icon) icon.classList.add('fa-spin');
  if (cards) cards.innerHTML = '<div class="empty-state">Loading inventory...</div>';

  try {
    const data = await fetchWithError(INVENTORY_API);
    inventoryProducts = Array.isArray(data) ? data : [];
    inventoryFilterState.query = document.getElementById('inventory-search')?.value.trim() || '';
    buildInventoryCategoryFilters();
    applyInventoryFilters();
  } catch (e) {
    if (cards) cards.innerHTML = '<div class="empty-state">Unable to load inventory.</div>';
  } finally {
    if (btn) btn.disabled = false;
    if (icon) icon.classList.remove('fa-spin');
  }
}

window.initAdminCashierInventoryPage = function() {
  refreshInventoryData();
  
  document.getElementById('inventory-search')?.addEventListener('input', (e) => {
    inventoryFilterState.query = e.target.value;
    applyInventoryFilters();
  });
  document.getElementById('refresh-button')?.addEventListener('click', refreshInventoryData);

  const togglePanel = (show) => {
    const p = document.getElementById('add-product-panel');
    if (!p) return;
    p.classList.toggle('hidden', !show);
    if (show) {
      p.scrollIntoView({ behavior: 'smooth' });
    } else {
      document.getElementById('add-product-form')?.reset();
    }
  };

  document.getElementById('btn-open-add-product')?.addEventListener('click', () => togglePanel(true));
  document.getElementById('btn-close-add-product')?.addEventListener('click', () => togglePanel(false));
  
  document.getElementById('new-product-image')?.addEventListener('change', (e) => {
    const file = e.target.files?.[0];
    const preview = document.getElementById('new-product-image-preview');
    if (file && preview) preview.innerHTML = `<img src="${URL.createObjectURL(file)}" style="height:100%;width:100%;object-fit:cover;" />`;
  });

  document.getElementById('detail-product-image')?.addEventListener('change', (e) => {
    const file = e.target.files?.[0];
    const preview = document.getElementById('detail-image');
    if (file && preview) preview.src = URL.createObjectURL(file);
  });

  document.getElementById('add-product-form')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    const btn = form.querySelector('button[type="submit"]');
    const originalContent = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

    try {
      const response = await fetch(PRODUCT_CREATE_API, { 
        method: 'POST', 
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      
      if (!response.ok) throw new Error(`HTTP Error ${response.status}`);
      
      const text = await response.text();
      let data;
      try { data = JSON.parse(text); } catch (e) { throw new Error('Malformed JSON from server.'); }

      if (!data.success) throw new Error(data.message || 'Failed to add product.');

      inventoryProducts.unshift(data.product);
      applyInventoryFilters();
      togglePanel(false);
      showInventoryToast(data.message || 'New product added to inventory successfully!');
    } catch (err) {
      console.error('Create operation failed:', err);
      showInventoryToast(err.message || 'A network error occurred while adding the product.', 'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = originalContent;
    }
  });

  document.getElementById('detail-category-input')?.addEventListener('change', (e) => {
    if (selectedInventoryProduct) {
       selectedInventoryProduct.product_category = e.target.value;
       updateInventoryDetailPanel();
    }
  });

  document.getElementById('btn-add-stock')?.addEventListener('click', () => {
    const i = document.getElementById('detail-stock-input');
    if (i) i.value = (parseInt(i.value) || 0) + 1;
  });
  document.getElementById('btn-reduce-stock')?.addEventListener('click', () => {
    const i = document.getElementById('detail-stock-input');
    if (i) i.value = Math.max(0, (parseInt(i.value) || 0) - 1);
  });

  document.getElementById('btn-save-product')?.addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    if (!selectedInventoryProduct || btn.disabled) return;

    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Saving...</span>';

    const fd = new FormData();
    fd.append('product_id', selectedInventoryProduct.product_id);
    fd.append('product_name', document.getElementById('detail-name-input').value.trim());
    fd.append('product_category', document.getElementById('detail-category-input').value);
    fd.append('buy_price', document.getElementById('detail-buy-input').value);
    fd.append('product_status', document.getElementById('detail-status-input').value);
    fd.append('stock_count', document.getElementById('detail-stock-input').value);
    
    const img = document.getElementById('detail-product-image').files[0];
    if (img) {
      if (img.size > 2 * 1024 * 1024) { // 2MB limit check
        showInventoryToast('Image file is too large (max 2MB)', 'error');
        btn.disabled = false;
        btn.innerHTML = originalContent;
        return;
      }
      fd.append('product_image', img);
    }

    try {
      const response = await fetch(INVENTORY_UPDATE_API, { 
        method: 'POST', 
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      
      if (!response.ok) throw new Error(`Server returned error code ${response.status}`);

      const text = await response.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch (jsonErr) {
        console.error('Server returned non-JSON response:', text);
        throw new Error('The server returned an invalid response. Check the console for details.');
      }

      if (!data.success) {
        throw new Error(data.message || 'Failed to update product data.');
      }

      selectedInventoryProduct = data.product;
      const idx = inventoryProducts.findIndex(p => Number(p.product_id) === Number(selectedInventoryProduct.product_id));
      if (idx !== -1) inventoryProducts[idx] = selectedInventoryProduct;
      
      applyInventoryFilters();
      updateInventoryDetailPanel();
      showInventoryToast(data.message || 'Product changes have been saved successfully!');
    } catch (err) {
      console.error('Update operation failed:', err);
      showInventoryToast(err.message || 'A network error occurred while saving product changes.', 'error');
    } finally {
      btn.disabled = false;
      btn.innerHTML = originalContent;
    }
  });

  document.getElementById('btn-clear-selection')?.addEventListener('click', () => {
    selectedInventoryProduct = null;
    updateInventoryDetailPanel();
  });

  document.getElementById('btn-close-details')?.addEventListener('click', () => {
    selectedInventoryProduct = null;
    updateInventoryDetailPanel();
    renderInventoryViews();
  });
};

// =====================================================
// DASHBOARD PAGE FUNCTIONS (Moved from admincashier_dashb.html)
// =====================================================
let dashSalesChartInstance = null;
let dashInventoryChartInstance = null;
let dashTopProductsChartInstance = null;

async function fetchTopSellingTable() {
  try {
    const response = await fetch('../../actions/get_admincashier_sales.php?period=month');
    const data = await response.json();
    const tableBody = document.getElementById('topSellingTableBody');
    
    if (!tableBody) return;
    tableBody.innerHTML = '';

    // Ensure we handle both structured and flat API responses
    const payload = data.data || data;

    if (payload.top_products && payload.top_products.length > 0) {
      payload.top_products.forEach(item => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td style="font-weight: 600; color: var(--text);">${item.name}</td>
          <td style="text-align: right;">
            <span class="badge" style="background: var(--primary-soft); color: var(--primary); font-weight: 700; padding: 4px 10px; border-radius: 8px;">${item.quantity} sold</span>
          </td>
        `;
        tableBody.appendChild(tr);
      });
    } else {
      tableBody.innerHTML = '<tr><td colspan="2" class="empty-state">No sales recorded this month.</td></tr>';
    }
  } catch (error) {
    console.error('Error fetching top products table:', error);
  }
}

async function fetchSummaryMetrics() {
  try {
    const dashboardData = await fetchWithError('../../actions/get_admincashier_dashboard.php');
    
    // Extract metrics from the 'data' wrapper if it exists (standard for this API)
    const metrics = dashboardData.data || dashboardData;

    // Use fallback property names to ensure compatibility with different backend implementations
    const totalSales = Number(metrics.total_sales_today ?? metrics.total_sales ?? 0);
    const totalInv = metrics.total_inventory ?? metrics.inventory_count ?? metrics.total_items ?? 0;
    const pendingQ = metrics.pending_queue ?? metrics.queue_count ?? metrics.active_queue ?? 0;

    if (document.getElementById('totalSalesToday')) document.getElementById('totalSalesToday').textContent = formatCurrency(totalSales);
    if (document.getElementById('totalInventory')) document.getElementById('totalInventory').textContent = totalInv;
    if (document.getElementById('pendingQueue')) document.getElementById('pendingQueue').textContent = pendingQ;
  } catch (error) {
    console.error('Error fetching dashboard data:', error);
  }
}

async function fetchAnalyticsCharts() {
  try {
    const chartData = await fetchWithError('../../actions/get_admincashier_charts.php');
    // Support structured response
    const data = chartData.data || chartData;
    
    // Sales Chart
    const salesCtx = document.getElementById('salesChart')?.getContext('2d');
    if (salesCtx && !dashSalesChartInstance) {
      dashSalesChartInstance = new Chart(salesCtx, {
        type: 'line',
        data: {
          labels: data.sales_labels || [],
          datasets: [{
            label: 'Daily Sales (₱)',
            data: data.sales_data || [],
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.4,
            fill: true
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: true } }
        }
      });
    } else if (dashSalesChartInstance) {
      dashSalesChartInstance.data.labels = data.sales_labels || [];
      dashSalesChartInstance.data.datasets[0].data = data.sales_data || [];
      dashSalesChartInstance.update();
    }

    // Inventory Chart
    const inventoryCtx = document.getElementById('inventoryChart')?.getContext('2d');
    if (inventoryCtx && !dashInventoryChartInstance) {
      dashInventoryChartInstance = new Chart(inventoryCtx, {
        type: 'doughnut',
        data: {
          labels: data.inventory_labels || [],
          datasets: [{
            data: data.inventory_data || [],
            backgroundColor: ['#667eea', '#764ba2', '#f093fb', '#4facfe']
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { display: true } }
        }
      });
    } else if (dashInventoryChartInstance) {
      dashInventoryChartInstance.data.labels = data.inventory_labels || [];
      dashInventoryChartInstance.data.datasets[0].data = data.inventory_data || [];
      dashInventoryChartInstance.update();
    }

    // Top Products Chart
    const productsCtx = document.getElementById('topProductsChart')?.getContext('2d');
    if (productsCtx && !dashTopProductsChartInstance) {
      dashTopProductsChartInstance = new Chart(productsCtx, {
        type: 'bar',
        data: {
          labels: data.products_labels || [],
          datasets: [{
            label: 'Units Sold',
            data: data.products_data || [],
            backgroundColor: '#667eea'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          indexAxis: 'y',
          plugins: { legend: { display: true } }
        }
      });
    } else if (dashTopProductsChartInstance) {
      dashTopProductsChartInstance.data.labels = data.products_labels || [];
      dashTopProductsChartInstance.data.datasets[0].data = data.products_data || [];
      dashTopProductsChartInstance.update();
    }
  } catch (error) {
    console.error('Error fetching chart data:', error);
  }
}

async function refreshAllDashboardData() {
  await fetchSummaryMetrics();
  await fetchAnalyticsCharts();
  await fetchTopSellingTable();
}

window.initAdminCashierDashboardPage = function(userData) {
  refreshAllDashboardData();
  const dashInterval = setInterval(refreshAllDashboardData, 20000); // Sync dashboard metrics every 20 seconds

  // Clean up interval on page unload
  window.addEventListener('beforeunload', () => {
    clearInterval(dashInterval);
  });
};