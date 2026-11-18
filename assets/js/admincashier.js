/**
 * GCST Track System - Admin/Cashier Core Logic
 * Refactored for performance and UI/UX consistency
 */

let currentAdminId = null;
let currentTicket = null;
let notificationPollInterval = null;
const BASE_PATH = '/GCST_Track_System';

function normalizePagePath(targetPath) {
    if (!targetPath || typeof targetPath !== 'string') return targetPath;

    const value = targetPath.trim();
    if (!value || value.startsWith('javascript:') || value.startsWith('#')) {
        return value;
    }

    const [pathOnly] = value.split(/[?#]/, 1);
    let normalized = pathOnly.replace(/\\/g, '/');

    if (normalized.endsWith('.html')) {
        normalized = normalized.replace(/\.html$/i, '.php');
    } else if (!/\.[a-z0-9]+$/i.test(normalized)) {
        normalized = `${normalized}.php`;
    }

    const suffix = value.substring(pathOnly.length);
    return normalized + suffix;
}

function redirectToPage(targetPath) {
    window.location.assign(normalizePagePath(targetPath));
}

function redirectHtmlToPhp() {
    const currentPath = window.location.pathname || '';
    if (!/\.html$/i.test(currentPath)) return false;

    const targetPath = `${currentPath.replace(/\.html$/i, '.php')}${window.location.search}${window.location.hash}`;
    window.location.replace(targetPath);
    return true;
}

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
            redirectToPage(`${BASE_PATH}/pages/sign_in_admin_cashier.php`);
            return null;
        }
        currentAdminId = currentId;
        return data;
    })
    .catch(() => {
        redirectToPage(`${BASE_PATH}/pages/sign_in_admin_cashier.php`);
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

function resolveUserDisplayName(userData = {}) {
  const candidates = [
    userData.name,
    userData.full_name,
    userData.fullName,
    userData.admin_name,
    userData.display_name,
    userData.username
  ];

  return candidates.find(value => typeof value === 'string' && value.trim()) || 'Admin';
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
    else if (!window.location.pathname.includes('admincashier_inventorys.php')) {
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
    if (redirectHtmlToPhp()) return;

    const initSequence = async () => {
        try {
            // 1. Load sidebar first so the user sees the UI immediately
            await autoLoadSidebar();
            
            // 2. Authentication
            const userData = await checkAuthentication();
            if (!userData) return;

            if (userData.role === 'cashier') {
                const inventoryLink = document.getElementById('inventory-management-link');
                if (inventoryLink) {
                    inventoryLink.href = `${BASE_PATH}/pages/admincashier/admincashier_sale.php#inventory-details`;
                    inventoryLink.title = 'Inventory Report';
                    const label = inventoryLink.querySelector('span');
                    if (label) label.textContent = 'Inventory Report';
                }
            }

            // 3. Initialize UI elements now that sidebar is in the DOM
            initializeAdminCashierUI();
            updateGreeting(resolveUserDisplayName(userData));
            updateDateTime();
            setInterval(updateDateTime, 60000);

            if (!window.location.pathname.includes('admincashier_inventorys.php')) {
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
        .then(async res => {
            const contentType = res.headers.get('content-type') || '';
            let payload = null;

            if (contentType.includes('application/json')) {
                payload = await res.json();
            } else {
                const text = await res.text();
                payload = text ? text : null;
            }

            if (!res.ok) {
                const message = payload && typeof payload === 'object' && payload.message
                    ? payload.message
                    : `HTTP error! status: ${res.status}`;
                throw new Error(message);
            }

            return payload;
        })
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

  if (!sidebar && !overlay) {
    return;
  }

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

  const toggleButton = document.getElementById('brand-toggle');
  const toggleLabel = isMinimized ? 'Expand sidebar' : 'Collapse sidebar';
  toggleButton?.setAttribute('title', toggleLabel);
  toggleButton?.setAttribute('aria-label', toggleLabel);

  localStorage.setItem('sidebar-minimized', isMinimized ? 'true' : 'false');
}

  function ensureAdminCashierDarkModeOverrides() {
    const styleId = 'admincashier-dark-mode-final-overrides';
    let style = document.getElementById(styleId);
    if (!style) {
      style = document.createElement('style');
      style.id = styleId;
      document.head.appendChild(style);
    }

    style.textContent = `
      body.dark-mode .content-wrapper #product-grid,
      body.dark-mode .content-wrapper #cart-content,
      body.dark-mode .content-wrapper #txn-history-content {
        scrollbar-color: #64748b #111827 !important;
      }

      body.dark-mode .content-wrapper #product-grid::-webkit-scrollbar,
      body.dark-mode .content-wrapper #cart-content::-webkit-scrollbar,
      body.dark-mode .content-wrapper #txn-history-content::-webkit-scrollbar {
        width: 8px !important;
      }

      body.dark-mode .content-wrapper #product-grid::-webkit-scrollbar-track,
      body.dark-mode .content-wrapper #cart-content::-webkit-scrollbar-track,
      body.dark-mode .content-wrapper #txn-history-content::-webkit-scrollbar-track {
        background: #111827 !important;
        border-radius: 999px !important;
      }

      body.dark-mode .content-wrapper #product-grid::-webkit-scrollbar-thumb,
      body.dark-mode .content-wrapper #cart-content::-webkit-scrollbar-thumb,
      body.dark-mode .content-wrapper #txn-history-content::-webkit-scrollbar-thumb {
        background: #475569 !important;
        border: 2px solid #111827 !important;
        border-radius: 999px !important;
      }

      body.dark-mode .content-wrapper #cart-footer {
        background: #172033 !important;
        border-color: #334155 !important;
        box-shadow: 0 -10px 20px rgba(15, 23, 42, 0.7) !important;
      }

      body.dark-mode .content-wrapper .stock-badge.stock-available {
        background: #123b2d !important;
        border: 1px solid #276749 !important;
        color: #a7f3d0 !important;
      }

      body.dark-mode .content-wrapper .stock-badge.stock-low {
        background: #3d2f12 !important;
        border: 1px solid #8a6418 !important;
        color: #fde68a !important;
      }

      body.dark-mode .content-wrapper .stock-badge.stock-out {
        background: #451a1a !important;
        border: 1px solid #8f3030 !important;
        color: #fecaca !important;
      }

      body.dark-mode .content-wrapper #txn-status-filter,
      body.dark-mode .content-wrapper #txn-history-search {
        background: #0f172a !important;
        border: 1px solid #53627a !important;
        color: #e5edf8 !important;
        box-shadow: none !important;
      }

      body.dark-mode .content-wrapper #txn-status-filter option {
        background: #172033 !important;
        color: #e5edf8 !important;
      }

      body.dark-mode .content-wrapper #txn-history-search::placeholder {
        color: #94a3b8 !important;
      }

      body.dark-mode .content-wrapper #txn-status-filter:focus,
      body.dark-mode .content-wrapper #txn-history-search:focus {
        border-color: #818cf8 !important;
        box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.18) !important;
      }

      body.dark-mode .content-wrapper #clear-txn-search {
        background: #334155 !important;
        color: #dbe5f2 !important;
      }
    `;
  }

async function autoLoadSidebar() {
  const container = document.getElementById('sidebar-container');
  if (!container) return;

  try {
    // Use dynamic import instead of relying on global window object
    const { getSidebarHTML } = await import('./admincashier_sidebar_content.js');
    if (container && typeof getSidebarHTML === 'function') {
        container.innerHTML = getSidebarHTML();
        const darkModeEnabled = localStorage.getItem('admincashier-dark-mode') === 'true';
        document.body.classList.toggle('dark-mode', darkModeEnabled);
        ensureAdminCashierDarkModeOverrides();
        const darkModeButton = document.getElementById('dark-mode-toggle');
        const darkModeIcon = darkModeButton?.querySelector('i');
        const darkModeText = darkModeButton?.querySelector('span');
        const darkModeLabel = darkModeEnabled ? 'Switch to light mode' : 'Switch to dark mode';
        darkModeButton?.setAttribute('title', darkModeLabel);
        darkModeButton?.setAttribute('aria-label', darkModeLabel);
        if (darkModeIcon) darkModeIcon.className = darkModeEnabled ? 'fas fa-sun' : 'fas fa-moon';
        if (darkModeText) darkModeText.textContent = darkModeEnabled ? 'Light Mode' : 'Dark Mode';
        darkModeButton?.addEventListener('click', window.toggleDarkMode);
        const isMinimized = localStorage.getItem('sidebar-minimized') === 'true';
        if (isMinimized) {
            document.getElementById('main-sidebar')?.classList.add('minimized');
            document.querySelector('.content-wrapper')?.classList.add('minimized');
            document.querySelector('header')?.classList.add('minimized');
        }
        const toggleButton = document.getElementById('brand-toggle');
        const toggleLabel = isMinimized ? 'Expand sidebar' : 'Collapse sidebar';
        toggleButton?.setAttribute('title', toggleLabel);
        toggleButton?.setAttribute('aria-label', toggleLabel);
      
        // Automatically highlight the active link based on the current URL
        const getFileName = (path) => path.split('/').pop() || 'admincashier_dashb.php';
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