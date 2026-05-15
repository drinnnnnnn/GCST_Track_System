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
  return fetch('../../actions/get_user.php')
    .then(res => res.json())
    .then(data => {
      const currentId = data.student_id || data.admin_id;
      if (!currentId) {
        window.location.href = "../../pages/sign_in.html";
        return null;
      }
      currentAdminId = currentId;
      return data;
    })
    .catch(() => {
      window.location.href = "../../pages/sign_in.html";
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
  fetch('../../actions/get_notifications.php')
    .then(res => res.json())
    .then(data => {
      const notificationsList = document.getElementById('notifications-list');
      const notifBadge = document.getElementById('notif-badge');
      
      if (!notificationsList) return;

      notificationsList.innerHTML = '';

      if (data.length === 0) {
        notificationsList.innerHTML = '<div class="empty-state"><p>No notifications</p></div>';
        if (notifBadge) notifBadge.style.display = 'none';
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
      if (notifBadge) {
        notifBadge.textContent = data.length;
        notifBadge.style.display = 'flex';
      }
    })
    .catch(err => console.error('Error loading notifications:', err));
}

/**
 * Clear all notifications
 */
function clearAllNotifications() {
  fetch('http://localhost/GCST_Track_System/actions/mark_notifications_read.php', {
    method: 'POST'
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
 * @param {HTMLElement} element - The target container to display the loader in
 * @param {string} message - Optional text to display below the spinner
 */
function showLoading(element, message = 'Loading...') {
  if (!element) return;
  
  element.innerHTML = `
    <div class="loading">
      <div class="spinner"></div>
      <span>${message}</span>
    </div>
  `;
}

/**
 * Show empty state UI
 * @param {HTMLElement} element - The target container
 * @param {string} message - Descriptive text explaining why the view is empty
 * @param {string} title - Optional heading title
 * @param {string} iconClass - FontAwesome icon class (e.g., 'fas fa-inbox')
 */
function showEmptyState(element, message = 'No data available', title = 'No Data', iconClass = 'fas fa-inbox') {
  if (!element) return;

  element.innerHTML = `
    <div class="empty-state">
      <i class="${iconClass}"></i>
      <h3>${title}</h3>
      <p>${message}</p>
    </div>
  `;
}

/**
 * Show error message state
 * @param {HTMLElement} element - The target container
 * @param {string} message - Error details or user-friendly error message
 */
function showError(element, message = 'An error occurred') {
  if (!element) return;

  element.innerHTML = `
    <div class="empty-state" style="border-color: #ef4444; color: #ef4444;">
      <i class="fas fa-exclamation-circle" style="color: #ef4444;"></i>
      <h3>Oops! Something went wrong</h3>
      <p>${message}</p>
    </div>
  `;
}

/**
 * Initialize page - call this in every page's DOMContentLoaded
 * @param {Function} pageCallback - Callback function to initialize page-specific content
 */
function initializeAdminCashierPage(pageCallback) {
  window.addEventListener('DOMContentLoaded', async () => {
    try {
      // Initialize UI elements
      initializeAdminCashierUI();

      // Check authentication
      const userData = await checkAuthentication();
      if (!userData) return;

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
  });

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

function toggleSidebar() {
  const sidebar = document.getElementById('main-sidebar');
  const overlay = document.getElementById('sidebar-overlay');
  sidebar?.classList.toggle('active');
  overlay?.classList.toggle('active');
}

function toggleMinimizeSidebar() {
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
    const { getSidebarHTML } = await import('../../assets/js/user_sidebar_content.js');
    if (container) {
      container.innerHTML = getSidebarHTML();

      // Apply the saved state from localStorage immediately after injection
      const isMinimized = localStorage.getItem('sidebar-minimized') === 'true';
      if (isMinimized) {
        document.getElementById('main-sidebar')?.classList.add('minimized');
        document.querySelector('.content-wrapper')?.classList.add('minimized');
        document.querySelector('header')?.classList.add('minimized');
      }
      
      // Automatically highlight the active link based on the current URL
      const getFileName = (path) => path.split('/').pop() || 'InUser_home.html';
      const currentFile = getFileName(window.location.pathname);

      const sidebarLinks = container.querySelectorAll('.sidebar-link');
      sidebarLinks.forEach(link => {
        const linkFile = getFileName(link.pathname);
        if (linkFile && linkFile === currentFile && !link.href.startsWith('javascript')) {
          link.classList.add('active');
        } else {
          link.classList.remove('active');
        }
      });
      
      // Attach event listeners to all logout buttons found on the page
      const logoutTriggers = [
        document.getElementById('sidebar-logout'),
        document.getElementById('logout-trigger')
      ];

      logoutTriggers.forEach(btn => {
        if (btn) {
          btn.onclick = (e) => {
            e.preventDefault();
            if (typeof window.logoutUser === 'function') window.logoutUser();
          };
        }
      });
    }
  } catch (err) {
    console.error('Sidebar auto-load failed:', err);
  }
}

// Initialize sidebar on every page load
document.addEventListener('DOMContentLoaded', autoLoadSidebar);
