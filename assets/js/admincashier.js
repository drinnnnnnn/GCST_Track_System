﻿let currentAdminId = null;
let notificationPollInterval = null;

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
  return fetch('/GCST_Track_System/actions/get_user.php')
    .then(res => res.json())
    .then(data => {
      // Strictly enforce admin roles for admincashier pages
      const allowedRoles = ['admin', 'admincashier', 'superadmin'];
      const currentId = data.admin_id;
      if (!currentId || !allowedRoles.includes(data.role)) {
        window.location.href = "/GCST_Track_System/pages/sign_in_admin_cashier.html";
        return null;
      }
      currentAdminId = currentId;
      return data;
    })
    .catch(() => {
      window.location.href = "/GCST_Track_System/pages/sign_in_admin_cashier.html";
      return null;
    });
}

/**
 * Update greeting message with user name
 */
function updateGreeting(adminName) {
  const greetingElement = document.getElementById('greeting-message');
  if (greetingElement) {
    // Keep the page-specific greeting if it exists
    if (!greetingElement.textContent.includes('-')) {
      greetingElement.textContent = `Welcome, ${adminName}!`;
    }
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
  fetch(`/GCST_Track_System/actions/get_notifications.php?admin_id=${encodeURIComponent(currentAdminId)}`)
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
  fetch('/GCST_Track_System/actions/mark_notifications_read.php', {
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
  return '₱' + parseFloat(value || 0).toFixed(2);
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
  const container = document.getElementById('sidebar-container');
  // If the container is empty, it means initializeAdminCashierPage hasn't run yet.
  if (container && container.innerHTML.trim() === "") {
    autoLoadSidebar();
  }
});