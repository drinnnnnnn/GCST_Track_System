/**
 * GCST Track System - Admin/Cashier Core Logic
 * Refactored for performance and UI/UX consistency
 */

let currentAdminId = null;
let currentTicket = null;
let notificationPollInterval = null;
const BASE_PATH = '/GCST_Track_System';
/**
 * Initialize menu and notification listeners
 * Call this in DOMContentLoaded of every page
 */
function initializeAdminCashierUI() {
    const setupDropdown = (triggerId, menuId, containerClass) => {
        const trigger = document.getElementById(triggerId);
        const menu = document.getElementById(menuId);
        if (!trigger || !menu) return;

        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            // Close other open dropdowns first
            document.querySelectorAll('.dropdown-menu.show, .notification-dropdown.show').forEach(m => {
                if (m !== menu) m.classList.remove('show');
            });
            menu?.classList.toggle('show');
        });
    };

    setupDropdown('menu-icon', 'dropdown-menu');
    setupDropdown('notification-bell', 'notification-dropdown');

    // Global click listener to close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.menu-icon') && !e.target.closest('.dropdown-menu') &&
            !e.target.closest('.notification-icon') && !e.target.closest('.notification-dropdown')) {
            document.querySelectorAll('.dropdown-menu.show, .notification-dropdown.show').forEach(m => m.classList.remove('show'));
        }
    });

    document.getElementById('clear-notifications')?.addEventListener('click', clearAllNotifications);
}

/**
 * Check authentication and redirect if not logged in
 */
function checkAuthentication() {
    return fetch(`${BASE_PATH}/actions/get_user.php`)
    .then(res => res.json())
    .then(data => {
        const allowedRoles = ['admin', 'cashier', 'admincashier', 'superadmin'];
        const currentId = data.admin_id;
        if (!currentId || !allowedRoles.includes(data.role)) {
            window.location.href = `${BASE_PATH}/pages/sign_in_admin_cashier.html`;
            return null;
        }
        currentAdminId = currentId;
        return data;
    })
    .catch(() => {
        window.location.href = `${BASE_PATH}/pages/sign_in_admin_cashier.html`;
        return null;
    });
}

/**
 * Update greeting message with user name
 */
function updateGreeting(name) {
  const greetingElement = document.getElementById('greeting-message');
  if (!greetingElement) return;

  const hour = new Date().getHours();
  const timeGreet = hour < 12 ? 'Good Morning' : hour < 18 ? 'Good Afternoon' : 'Good Evening';
  
  const existingText = greetingElement.textContent;
  const prefix = existingText.includes('•') ? existingText.split('•')[0].trim() + ' • ' : '';
  greetingElement.textContent = prefix ? `${prefix}${name}` : `${timeGreet}, ${name}!`;
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
    fetch(`${BASE_PATH}/actions/get_notifications.php?admin_id=${encodeURIComponent(currentAdminId)}`)
    .then(res => res.json())
    .then(data => {
      const notificationsList = document.getElementById('notifications-list');
      const notifBadge = document.getElementById('notif-badge');
      const sidebarBadge = document.getElementById('sidebar-gmail-badge');
      
      if (notificationsList) {
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
          </div>`;
        notificationsList.appendChild(item);
      });
      }

      // Show badge with count
      const count = data.length;
      if (notifBadge) {
        notifBadge.textContent = data.length;
        notifBadge.style.display = count > 0 ? 'flex' : 'none';
      }

      // Update Sidebar Badge
      if (sidebarBadge) {
        sidebarBadge.textContent = count;
        sidebarBadge.classList.toggle('hidden', count === 0);
      }
    })
    .catch(err => console.error('Error loading notifications:', err));
}

/**
 * Clear all notifications
 */
function clearAllNotifications() {
  if (!currentAdminId) return;
    fetch(`${BASE_PATH}/actions/mark_notifications_read.php`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ admin_id: currentAdminId })
    })
    .then(() => loadNotifications())
    .catch(err => console.error('Error clearing notifications:', err));
}

/**
 * Start polling notifications every 30 seconds
 * Optimized with Page Visibility API
 */
function startNotifPolling() {
    if (notificationPollInterval) clearInterval(notificationPollInterval);
    
    const pollTask = () => {
        checkAuthentication(); // Heartbeat check to keep session alive
        loadNotifications();
    };

    pollTask();
    notificationPollInterval = setInterval(pollTask, 30000);
}
/**
 * Stop notification polling
 */
function stopNotifPolling() {
    clearInterval(notificationPollInterval);
    notificationPollInterval = null;
}

// Visibility API: Pause polling when tab is inactive to save resources
document.addEventListener('visibilitychange', () => {
    if (document.hidden) stopNotifPolling();
    else if (!window.location.pathname.includes('admincashier_inventorys.html')) {
        startNotifPolling();
    }
});

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
 * Initialize page - call this in every page's DOMContentLoaded
 * @param {Function} pageCallback - Callback function to initialize page-specific content
 */
window.initializeAdminCashierPage = function(pageCallback) {
    const initSequence = async () => {
        try {
            // 1. Load sidebar first so the user sees the UI immediately
            await autoLoadSidebar();
            
            // 2. Authentication
            const userData = await checkAuthentication();
            if (!userData) return;

            // 3. Initialize UI elements now that sidebar is in the DOM
            initializeAdminCashierUI();
            updateGreeting(userData.name || 'Admin');
            updateDateTime();
            setInterval(updateDateTime, 60000);

            if (!window.location.pathname.includes('admincashier_inventorys.html')) {
                startNotifPolling();
            }

            if (typeof pageCallback === 'function') pageCallback(userData);
        } catch (error) {
            console.error('Error initializing page:', error);
        }
    };

    if (document.readyState === 'loading') {
        window.addEventListener('DOMContentLoaded', initSequence);
    } else {
        initSequence();
    }

    // Clean up on page unload
    window.addEventListener('beforeunload', () => {
        stopNotifPolling();
        if (typeof stopSalesPolling === 'function') stopSalesPolling();
    if (typeof stopQueuePolling === 'function') stopQueuePolling();
    if (typeof stopInventoryPolling === 'function') stopInventoryPolling();
  });
}

/**
 * Fetch and handle errors
 */
function fetchWithError(url, options = {}) {
    return fetch(url, options)
        .then(res => res.ok ? res.json() : Promise.reject(`HTTP error! status: ${res.status}`))
        .catch(err => {
            console.error('Fetch error:', err);
            throw err;
        });
}

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

  localStorage.setItem('sidebar-minimized', isMinimized ? 'true' : 'false');
}
async function autoLoadSidebar() {
  const container = document.getElementById('sidebar-container');
  if (!container) return;

  try {
    // Use dynamic import instead of relying on global window object
    const { getSidebarHTML } = await import('./admincashier_sidebar_content.js');
    if (container && typeof getSidebarHTML === 'function') {
        container.innerHTML = getSidebarHTML();
        const isMinimized = localStorage.getItem('sidebar-minimized') === 'true';
        if (isMinimized) {
            document.getElementById('main-sidebar')?.classList.add('minimized');
            document.querySelector('.content-wrapper')?.classList.add('minimized');
            document.querySelector('header')?.classList.add('minimized');
        }
      
        // Automatically highlight the active link based on the current URL
        const getFileName = (path) => path.split('/').pop() || 'admincashier_dashb.html';
        const currentFile = getFileName(window.location.pathname);
      
        container.querySelectorAll('.sidebar-link').forEach(link => {
            const linkFile = getFileName(link.pathname);
            const isActive = linkFile && linkFile === currentFile && !link.href.startsWith('javascript');
            link.classList.toggle('active', isActive);
        });
    }
  } catch (err) {
    console.warn('Sidebar auto-load failed:', err);
  }
}