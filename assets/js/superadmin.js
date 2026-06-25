﻿/**
 * GCST Track System - Super Admin Core Logic
 * Refactored for modularity, performance, and maintainability.
 */

const SuperAdmin = (function() {
    // Core Configuration & State
    const CONFIG = {
        BASE_PATH: '/GCST_Track_System',
        POLL_INTERVAL: 30000,
        INACTIVITY_LIMIT: 900000, // 15 mins
        HARD_LOGOUT_LIMIT: 300000, // 5 mins on lock screen
        ALLOWED_ROLES: ['superadmin']
    };

    let state = {
        currentAdminId: null,
        pollInterval: null,
        idleTimer: null,
        hardLogoutTimer: null
    };

    // DOM Cache
    const elements = {
        get greeting() { return document.getElementById('greeting-message'); },
        get dateTime() { return document.getElementById('current-date-time'); },
        get notifList() { return document.getElementById('notifications-list'); },
        get notifBadge() { return document.getElementById('notif-badge'); },
        get sidebarBadge() { return document.getElementById('sidebar-gmail-badge'); },
        get sidebarContainer() { return document.getElementById('sidebar-container'); },
        get lockOverlay() { return document.getElementById('lock-screen-overlay'); }
    };

    /**
     * Unified Fetch Helper
     */
    async function apiFetch(endpoint, options = {}) {
        const url = endpoint.startsWith('http') ? endpoint : `${CONFIG.BASE_PATH}${endpoint}`;
        const defaultOptions = {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        };
        
        try {
            const response = await fetch(url, { ...defaultOptions, ...options });
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            return await response.json();
        } catch (err) {
            console.error(`API Fetch Error [${endpoint}]:`, err);
            throw err;
        }
    }

    /* =====================================================
       AUTHENTICATION & SECURITY
       ===================================================== */

    async function checkAuthentication() {
        try {
            const data = await apiFetch('/actions/get_user.php');
            if (!data.admin_id || !CONFIG.ALLOWED_ROLES.includes(data.role)) {
                window.location.replace(`${CONFIG.BASE_PATH}/pages/sign_in_superadmin.html`);
                return null;
            }
            state.currentAdminId = data.admin_id;
            return data;
        } catch {
            window.location.replace(`${CONFIG.BASE_PATH}/pages/sign_in_superadmin.html`);
            return null;
        }
    }

    function setupInactivityTimer() {
        const resetTimer = () => {
            clearTimeout(state.idleTimer);
            clearTimeout(state.hardLogoutTimer);

            if (elements.lockOverlay?.classList.contains('active')) return;

            state.idleTimer = setTimeout(() => {
                showLockScreen();
                state.hardLogoutTimer = setTimeout(() => {
                    console.warn("Session expired due to inactivity.");
                    if (typeof window.performLogout === 'function') window.performLogout();
                }, CONFIG.HARD_LOGOUT_LIMIT);
            }, CONFIG.INACTIVITY_LIMIT);
        };

        ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(evt => 
            document.addEventListener(evt, resetTimer, { passive: true })
        );
        resetTimer();
    }

    function showLockScreen() {
        if (elements.lockOverlay) {
            elements.lockOverlay.classList.add('active');
            document.getElementById('lock-screen-pin')?.focus();
        }
    }

    function setupBrowserSecurity() {
        window.addEventListener('pageshow', (e) => {
            if (e.persisted) checkAuthentication();
        });

        window.addEventListener('storage', (e) => {
            if (e.key === 'gcst_superadmin_logout_event') {
                window.location.replace(`${CONFIG.BASE_PATH}/pages/sign_in_superadmin.html`);
            }
        });
    }

    /* =====================================================
       UI COMPONENTS
       ===================================================== */

    function initializeUI() {
        const setupDropdown = (triggerId, menuId) => {
            const trigger = document.getElementById(triggerId);
            const menu = document.getElementById(menuId);
            if (!trigger || !menu) return;

            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                document.querySelectorAll('.dropdown-menu.show, .notification-dropdown.show').forEach(m => {
                    if (m !== menu) m.classList.remove('show');
                });
                menu.classList.toggle('show');
            });
        };

        setupDropdown('menu-icon', 'dropdown-menu');
        setupDropdown('notification-bell', 'notification-dropdown');

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.menu-icon') && !e.target.closest('.dropdown-menu') &&
                !e.target.closest('.notification-icon') && !e.target.closest('.notification-dropdown')) {
                document.querySelectorAll('.dropdown-menu.show, .notification-dropdown.show').forEach(m => m.classList.remove('show'));
            }
        });

        document.getElementById('clear-notifications')?.addEventListener('click', clearAllNotifications);
    }

    function updateGreeting(name) {
        if (!elements.greeting) return;
        const hour = new Date().getHours();
        const greet = hour < 12 ? 'Good Morning' : hour < 18 ? 'Good Afternoon' : 'Good Evening';
        const existing = elements.greeting.textContent;
        const prefix = existing.includes('•') ? existing.split('•')[0].trim() + ' • ' : '';
        elements.greeting.textContent = prefix ? `${prefix}${name}` : `${greet}, ${name}!`;
    }

    function updateDateTime() {
        if (!elements.dateTime) return;
        const now = new Date();
        const dateStr = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
        const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        elements.dateTime.textContent = `${dateStr} • ${timeStr}`;
    }

    /* =====================================================
       NOTIFICATIONS
       ===================================================== */

    async function loadNotifications() {
        if (!state.currentAdminId) return;
        try {
            const data = await apiFetch('/actions/get_notifications.php');
            if (elements.notifList) {
                elements.notifList.innerHTML = '';
                if (!data || data.length === 0) {
                    elements.notifList.innerHTML = '<div class="empty-state"><p>No notifications</p></div>';
                } else {
                    data.forEach(notif => {
                        const div = document.createElement('div');
                        div.className = 'notification-item';
                        div.innerHTML = `
                            ${notif.image ? `<img src="${notif.image}" alt="notif">` : ''}
                            <div>
                                <div class="notification-message">${notif.message || 'New notification'}</div>
                                <div class="notification-time">${notif.time || 'Just now'}</div>
                            </div>`;
                        elements.notifList.appendChild(div);
                    });
                }
            }

            const count = data?.length || 0;
            if (elements.notifBadge) {
                elements.notifBadge.textContent = count;
                elements.notifBadge.style.display = count > 0 ? 'flex' : 'none';
            }
            if (elements.sidebarBadge) {
                elements.sidebarBadge.textContent = count;
                elements.sidebarBadge.classList.toggle('hidden', count === 0);
            }
        } catch (err) {
            console.warn('Failed to load notifications');
        }
    }

    async function clearAllNotifications() {
        try {
            await apiFetch('/actions/mark_notifications_read.php', { method: 'POST' });
            loadNotifications();
        } catch (err) {
            console.error('Error clearing notifications');
        }
    }

    function startPolling() {
        stopPolling();
        const task = () => {
            checkAuthentication();
            loadNotifications();
        };
        task();
        state.pollInterval = setInterval(task, CONFIG.POLL_INTERVAL);
    }

    function stopPolling() {
        if (state.pollInterval) clearInterval(state.pollInterval);
        state.pollInterval = null;
    }

    /* =====================================================
       SIDEBAR & NAVIGATION
       ===================================================== */

    async function autoLoadSidebar() {
        if (!elements.sidebarContainer) return;
        try {
            const { getSidebarHTML } = await import('./superadmin_sidebar_content.js');
            elements.sidebarContainer.innerHTML = getSidebarHTML();

            const isMinimized = localStorage.getItem('sidebar-minimized') === 'true';
            if (isMinimized) {
                document.getElementById('main-sidebar')?.classList.add('minimized');
                document.querySelector('.content-wrapper')?.classList.add('minimized');
                document.querySelector('header')?.classList.add('minimized');
            }

            const currentFile = window.location.pathname.split('/').pop() || 'superadmin_dashb.html';
            elements.sidebarContainer.querySelectorAll('.sidebar-link').forEach(link => {
                const linkFile = link.pathname.split('/').pop();
                link.classList.toggle('active', linkFile === currentFile && !link.href.startsWith('javascript'));
            });
        } catch (err) {
            console.warn('Sidebar load failed:', err);
        }
    }

    // Public API
    return {
        initPage: async function(pageCallback) {
            try {
                await autoLoadSidebar();
                const userData = await checkAuthentication();
                if (!userData) return;

                initializeUI();
                setupInactivityTimer();
                setupBrowserSecurity();
                updateGreeting(userData.name || 'Admin');
                updateDateTime();
                setInterval(updateDateTime, 60000);

                loadNotifications();
                startPolling();

                if (typeof pageCallback === 'function') pageCallback(userData);
            } catch (err) {
                console.error('Initialization failed:', err);
            }

            window.addEventListener('beforeunload', stopPolling);
            document.addEventListener('visibilitychange', () => {
                document.hidden ? stopPolling() : startPolling();
            });
        },
        
        verifyPinOnly: async function(pin) {
            try {
                const res = await apiFetch('/actions/verify_superadmin_pin.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ pin })
                });
                return res?.success || false;
            } catch { return false; }
        },

        changePin: async function(data) {
            return await apiFetch('/actions/change_superadmin_pin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
        },

        // Global utility exposure
        utils: {
            formatCurrency: (v) => '₱' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
            showLoading: (el) => el && (el.innerHTML = '<div class="loading"><div class="spinner"></div><span>Loading...</span></div>'),
            showEmpty: (el, msg = 'No data available') => el && (el.innerHTML = `<div class="empty-state"><i class="fas fa-inbox"></i><h3>No Data</h3><p>${msg}</p></div>`),
            showError: (el, msg = 'An error occurred') => el && (el.innerHTML = `<div class="empty-state" style="border-color:#ef4444;color:#ef4444;"><i class="fas fa-exclamation-circle"></i><h3>Error</h3><p>${msg}</p></div>`)
        }
    };
})();

// Global Exports for legacy HTML attributes
window.initializeSuperAdminPage = SuperAdmin.initPage;
window.verifyPinOnly = SuperAdmin.verifyPinOnly;
window.changeSuperadminPin = SuperAdmin.changePin;
window.formatCurrency = SuperAdmin.utils.formatCurrency;
window.showLoading = SuperAdmin.utils.showLoading;
window.showEmptyState = SuperAdmin.utils.showEmpty;
window.showError = SuperAdmin.utils.showError;

window.toggleSidebar = function() {
    const sidebar = document.getElementById('main-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    const isSidebarActive = sidebar?.classList.toggle('active') ?? false;
    overlay?.classList.toggle('active');

    if (window.matchMedia('(max-width: 1024px)').matches) {
        document.body.style.overflow = isSidebarActive ? 'hidden' : '';
    }
};

window.toggleMinimizeSidebar = function() {
    const sidebar = document.getElementById('main-sidebar');
    const content = document.querySelector('.content-wrapper');
    const header = document.querySelector('header');
    const isMin = sidebar?.classList.toggle('minimized');
    content?.classList.toggle('minimized');
    header?.classList.toggle('minimized');
    localStorage.setItem('sidebar-minimized', isMin ? 'true' : 'false');
};

/**
 * Initialization helper: Ensure sidebar loads if the page script doesn't call initPage immediately.
 */
document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('sidebar-container');
    if (container && container.innerHTML.trim() === "") {
        // Fallback for pages that might not use SuperAdmin.initPage but still need the sidebar
        // (e.g. static error pages or simple views)
        const sidebarFile = window.location.pathname.includes('superadmin') ? 'superadmin_sidebar_content.js' : null;
        if (sidebarFile) {
            import(`./${sidebarFile}`).then(m => {
                container.innerHTML = m.getSidebarHTML();
            }).catch(() => {});
        }
    }
});