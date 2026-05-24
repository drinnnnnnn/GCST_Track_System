﻿/**
 * GCST Track System - Super Admin Core Logic
 * Optimized for performance, security, and modularity.
 */

let currentAdminId = null;
let notificationPollInterval = null;
const BASE_PATH = '/GCST_Track_System';

/**
 * Initialize Super Admin menu and notification listeners
 */
function initializeSuperAdminUI() {
  const setupDropdown = (triggerId, menuId) => {
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
      menu.classList.toggle('show');
    });
  };

  setupDropdown('menu-icon', 'dropdown-menu');
  setupDropdown('notification-bell', 'notification-dropdown');

  // Close menu when clicking outside
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
      const allowedRoles = ['superadmin'];
      const currentId = data.admin_id;
      if (!currentId || !allowedRoles.includes(data.role)) {
        window.location.href = `${BASE_PATH}/pages/sign_in_superadmin.html`;
        return null;
      }
      currentAdminId = currentId;
      return data;
    })
    .catch(() => (window.location.href = `${BASE_PATH}/pages/sign_in_superadmin.html`));
}

/**
 * Update greeting message with user name
 */
function updateGreeting(adminName) {
  const greetingElement = document.getElementById('greeting-message');
  if (greetingElement) {
    const hour = new Date().getHours();
    const timeGreet = hour < 12 ? 'Good Morning' : hour < 18 ? 'Good Afternoon' : 'Good Evening';
    
    // If the element has specific formatting like "Dashboard • Name", preserve the prefix
    const existingText = greetingElement.textContent;
    const prefix = existingText.includes('•') ? existingText.split('•')[0].trim() + ' • ' : '';
    greetingElement.textContent = prefix ? `${prefix}${adminName}` : `${timeGreet}, ${adminName}!`;
  }
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
  fetch(`${BASE_PATH}/actions/get_notifications.php`)
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
          </div>
        `;
        notificationsList.appendChild(item);
      });
      }

      // Show badge with count
      const count = data.length;
      if (notifBadge) {
        notifBadge.textContent = count;
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
    method: 'POST'
  })
    .then(() => loadNotifications())
    .catch(err => console.error('Error clearing notifications:', err));
}

/**
 * Start polling notifications every 30 seconds
 */
function startNotifPolling() {
  if (notificationPollInterval) clearInterval(notificationPollInterval);

  const pollTask = () => {
    checkAuthentication(); // Heartbeat
    loadNotifications();
  };

  pollTask();
  notificationPollInterval = setInterval(pollTask, 30000);
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
 * Initialize page - call this in every page's DOMContentLoaded
 * @param {Function} pageCallback - Callback function to initialize page-specific content
 */
window.initializeSuperAdminPage = function(pageCallback) {
  const initSequence = async () => {
    try {
      // 1. Load sidebar first so the user sees the UI immediately
      await autoLoadSidebar();

      // 2. Start authentication check
      const userData = await checkAuthentication();
      if (!userData) return;

      // 3. Initialize UI elements and Security Features
      initializeSuperAdminUI();
      setupInactivityTimer(); // Feature: Auto-logout on idle
      setupBrowserSecurity(); // Security: Prevent back-button access & sync logout

      // Update greeting and time
      updateGreeting(userData.name || 'Admin');
      updateDateTime();
      setInterval(updateDateTime, 60000);

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
    window.addEventListener('DOMContentLoaded', initSequence);
  } else {
    initSequence();
  }

  // Clean up on page unload
  window.addEventListener('beforeunload', () => {
    stopNotifPolling();
  });
};

// Visibility API: Pause polling when tab is inactive to save resources
document.addEventListener('visibilitychange', () => {
  if (document.hidden) stopNotifPolling();
  else if (currentAdminId) startNotifPolling();
});

/**
 * Feature: Auto-logout after 15 minutes of inactivity
 */
function setupInactivityTimer() {
  let idleTimer;
  let hardLogoutTimer;

  const resetTimer = () => {
    clearTimeout(idleTimer);
    clearTimeout(hardLogoutTimer);

    // If screen is locked, don't reset the timer until unlocked
    if (document.getElementById('lock-screen-overlay')?.classList.contains('active')) return;

    idleTimer = setTimeout(() => {
      showLockScreen();

      // Hard logout after another 5 minutes of being on the lock screen
      // This triggers if the user doesn't unlock the screen
      hardLogoutTimer = setTimeout(() => {
        console.warn("Session expired due to prolonged inactivity.");
        if (typeof window.performLogout === 'function') {
          window.performLogout();
        }
      }, 300000); // 5 minutes (Total 20 minutes of inactivity)
    }, 900000); // 15 minutes of initial inactivity
  };

  // Events that signify activity
  const activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'];
  activityEvents.forEach(evt => document.addEventListener(evt, resetTimer, true));
  resetTimer();
}

/**
 * Feature: Lock the UI
 */
function showLockScreen() {
  const lockOverlay = document.getElementById('lock-screen-overlay');
  if (lockOverlay) {
    lockOverlay.classList.add('active');
    document.getElementById('lock-screen-pin')?.focus();
  }
}

/**
 * Bridge function to verify PIN without logging out
 */
window.verifyPinOnly = async function(pin) {
  try {
    const response = await fetch(`${BASE_PATH}/actions/verify_superadmin_pin.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ pin: pin })
    });
    const result = await response.json();
    return result?.success || false;
  } catch (err) {
    console.error("PIN verification failed", err);
    return false;
  }
};

/**
 * Security: Browser behavior protection
 */
function setupBrowserSecurity() {
  // 1. Prevent access to cached pages via Back/Forward button (BFCache protection)
  window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
      // If page is loaded from cache, force a fresh authentication check
      checkAuthentication();
    }
  });

  // 2. Multi-Tab Logout Synchronization
  window.addEventListener('storage', (event) => {
    if (event.key === 'gcst_superadmin_logout_event') {
      // If a logout happened in another tab, redirect this tab as well
      window.location.replace(`${BASE_PATH}/pages/sign_in_superadmin.html`);
    }
  });
}

/**
 * Fetch and handle errors
 */
function fetchWithError(url, options = {}) {
  return fetch(url, options)
    .then(res => {
      if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);
      return res.json();
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

/**
 * Handles the PIN change request
 * @param {Object} pinData - { current_pin, new_pin, confirm_pin }
 */
window.changeSuperadminPin = async function(pinData) {
  try {
    const response = await fetch(`${BASE_PATH}/actions/change_superadmin_pin.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(pinData)
    });
    
    const result = await response.json();
    if (result.success) {
      console.log('PIN updated successfully.');
    }
    return result;
  } catch (error) {
    console.error('Error changing PIN:', error);
  }
}

async function autoLoadSidebar() {
  const container = document.getElementById('sidebar-container');
  if (!container) return;

  try {
    const { getSidebarHTML } = await import('./superadmin_sidebar_content.js');
    if (container && typeof getSidebarHTML === 'function') {
      container.innerHTML = getSidebarHTML();

      const isMinimized = localStorage.getItem('sidebar-minimized') === 'true';
      if (isMinimized) {
        document.getElementById('main-sidebar')?.classList.add('minimized');
        document.querySelector('.content-wrapper')?.classList.add('minimized');
        document.querySelector('header')?.classList.add('minimized');
      }
      
      const getFileName = (path) => path.split('/').pop() || 'superadmin_dashb.html';
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

/**
 * Initialization helper:
 * Only auto-loads the sidebar if initializeSuperAdminPage hasn't been called.
 */
document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('sidebar-container');
  // If the container is empty, it means initializeSuperAdminPage hasn't run yet.
  if (container && container.innerHTML.trim() === "") {
    autoLoadSidebar();
  }
});