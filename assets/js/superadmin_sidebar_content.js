export function getSidebarHTML() {
    // Define global utility functions for the sidebar
    if (!window.initSidebarGestures) {
        window.initSidebarGestures = function() {
            const sidebar = document.getElementById('main-sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            if (!sidebar || !overlay) return;

            let touchStartX = 0;
            let touchEndX = 0;

            const handleGesture = () => {
                const swipeDistance = touchEndX - touchStartX;
                // Swipe left to close (only if menu is active/open)
                if (sidebar.classList.contains('active') && swipeDistance < -60) {
                    if (typeof window.toggleSidebar === 'function') {
                        window.toggleSidebar();
                    }
                }
            };

            const addTouchEvents = (el) => {
                el.addEventListener('touchstart', e => {
                    touchStartX = e.changedTouches[0].screenX;
                }, { passive: true });

                el.addEventListener('touchend', e => {
                    touchEndX = e.changedTouches[0].screenX;
                    handleGesture();
                }, { passive: true });
            };

            addTouchEvents(sidebar);
            addTouchEvents(overlay);
        };
        setTimeout(window.initSidebarGestures, 200);
    }

    if (!window.openLogoutModal) {
        window.toggleDarkMode = function() {
            const isDark = document.body.classList.toggle('dark-mode');
            localStorage.setItem('superadmin-dark-mode', isDark ? 'true' : 'false');
            const button = document.getElementById('dark-mode-toggle');
            const icon = button?.querySelector('i');
            const text = button?.querySelector('span');
            const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';
            button?.setAttribute('title', label);
            button?.setAttribute('aria-label', label);
            if (icon) icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
            if (text) text.textContent = isDark ? 'Light Mode' : 'Dark Mode';
        };

        const savedTheme = localStorage.getItem('superadmin-dark-mode');
        if (savedTheme === 'true') document.body.classList.add('dark-mode');

        window.syncDarkModeToggle = function() {
            const isDark = document.body.classList.contains('dark-mode');
            const button = document.getElementById('dark-mode-toggle');
            const icon = button?.querySelector('i');
            const text = button?.querySelector('span');
            const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';
            button?.setAttribute('title', label);
            button?.setAttribute('aria-label', label);
            if (icon) icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
            if (text) text.textContent = isDark ? 'Light Mode' : 'Dark Mode';
        };

        window.openLogoutModal = function() {
            const modal = document.getElementById('sidebar-logout-modal');
            const trigger = document.getElementById('sidebar-logout');
            if (!modal || !trigger) return;
            if (modal.classList.contains('active')) return;

            trigger.hidden = true;
            document.getElementById('main-sidebar')?.classList.add('logout-confirming');
            modal.style.display = 'block';
            void modal.offsetHeight;
            modal.classList.add('active');
            trigger.setAttribute('aria-expanded', 'true');
            modal.querySelector('.btn-modal-cancel')?.focus();
        };

        window.closeLogoutModal = function() {
            const modal = document.getElementById('sidebar-logout-modal');
            if (!modal) return;
            modal.classList.remove('active');
            modal.style.display = 'none';
            const trigger = document.getElementById('sidebar-logout');
            trigger?.setAttribute('aria-expanded', 'false');
            if (trigger) {
                trigger.hidden = false;
                trigger.focus();
            }
            document.getElementById('main-sidebar')?.classList.remove('logout-confirming');
        };

        document.addEventListener('click', (event) => {
            const modal = document.getElementById('sidebar-logout-modal');
            const target = event.target;
            if (
                modal?.classList.contains('active') &&
                target instanceof Element &&
                !modal.contains(target) &&
                !document.getElementById('sidebar-logout')?.contains(target)
            ) {
                window.closeLogoutModal();
            }
        });

        window.performLogout = function() {
            if (typeof window.closeLogoutModal === 'function') {
                window.closeLogoutModal();
            }
            localStorage.setItem('gcst_superadmin_logout_event', Date.now());
            localStorage.removeItem('sidebar-minimized');
            sessionStorage.clear();
            window.location.replace('/GCST_Track_System/actions/sign_out.php');
        };

        window.logoutUser = function() {
            window.performLogout();
        };

        window.__logoutKeydownBound = false;
        if (!window.__logoutKeydownBound) {
            window.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    const modal = document.getElementById('sidebar-logout-modal');
                    if (modal && modal.classList.contains('active')) {
                        window.closeLogoutModal();
                    }
                }
            });
            window.__logoutKeydownBound = true;
        }

        window.attemptUnlock = async function() {
            const pinInput = document.getElementById('lock-screen-pin');
            const pin = pinInput?.value;
            
            if (!pin) return;

            const success = await window.verifyPinOnly(pin);
            if (success) {
                const overlay = document.getElementById('lock-screen-overlay');
                overlay?.classList.remove('active');
                pinInput.value = '';
            } else {
                pinInput.classList.add('shake');
                setTimeout(() => pinInput.classList.remove('shake'), 500);
            }
        };

        window.handleSidebarLinkClick = function() {
            if (window.matchMedia('(max-width: 1024px)').matches) {
                if (typeof window.toggleSidebar === 'function') {
                    window.toggleSidebar();
                }
            }
        };

        let staffRegistrationTrigger = null;
        let bodyOverflowBeforeStaffModal = '';

        window.openStaffRegistrationModal = function(event) {
            const modal = document.getElementById('staff-registration-modal');
            const frame = document.getElementById('staff-registration-frame');
            const registrationLink = document.querySelector('[data-open-staff-registration]');
            if (!modal || !frame || !registrationLink) return true;

            event?.preventDefault();
            staffRegistrationTrigger = event?.currentTarget || registrationLink;
            window.handleSidebarLinkClick();
            bodyOverflowBeforeStaffModal = document.body.style.overflow;

            const registrationUrl = new URL(registrationLink.href);
            registrationUrl.searchParams.set('popup', '1');
            frame.src = registrationUrl.toString();
            modal.hidden = false;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            modal.querySelector('.staff-registration-close')?.focus();
            return false;
        };

        window.closeStaffRegistrationModal = function() {
            const modal = document.getElementById('staff-registration-modal');
            const frame = document.getElementById('staff-registration-frame');
            if (!modal?.classList.contains('active')) return;

            modal.classList.remove('active');
            modal.hidden = true;
            if (frame) frame.src = 'about:blank';
            document.body.style.overflow = bodyOverflowBeforeStaffModal;
            staffRegistrationTrigger?.focus();
        };

        window.__staffRegistrationModalKeydownBound = false;
        if (!window.__staffRegistrationModalKeydownBound) {
            window.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && document.getElementById('staff-registration-modal')?.classList.contains('active')) {
                    window.closeStaffRegistrationModal();
                }
            });
            window.__staffRegistrationModalKeydownBound = true;
        }

        // Global utility to initialize lock screen events
        window.initLockScreenEvents = function() {
            const pinInput = document.getElementById('lock-screen-pin');
            if (pinInput) {
                pinInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') window.attemptUnlock();
                });
            }
        };
    }

    const ensureInlineLogoutModal = () => {
        const sidebar = document.getElementById('main-sidebar');
        const footer = sidebar?.querySelector('.sidebar-footer');
        if (!sidebar || !footer) return null;

        const modal = sidebar.querySelector('#sidebar-logout-modal')
            || document.querySelector('#sidebar-logout-modal');
        if (!modal) return null;

        if (!sidebar.contains(modal)) footer.appendChild(modal);
        document.querySelectorAll('#sidebar-logout-modal').forEach((duplicate) => {
            if (duplicate !== modal) duplicate.remove();
        });

        modal.querySelector('.logout-modal-icon')?.remove();
        modal.querySelector('.logout-modal-text')?.remove();
        const title = modal.querySelector('.logout-modal-title');
        if (title) title.textContent = 'Sign out?';
        const stayButton = modal.querySelector('.btn-modal-cancel');
        if (stayButton) stayButton.textContent = 'Stay';
        modal.setAttribute('aria-modal', 'false');
        return modal;
    };

    window.openLogoutModal = function() {
        const modal = ensureInlineLogoutModal();
        const trigger = document.getElementById('sidebar-logout');
        const sidebar = document.getElementById('main-sidebar');
        if (!modal || !trigger || !sidebar) return;

        trigger.hidden = true;
        sidebar.classList.add('logout-confirming');
        modal.style.display = 'block';
        modal.classList.add('active');
        trigger.setAttribute('aria-expanded', 'true');
        modal.querySelector('.btn-modal-cancel')?.focus();
    };

    window.closeLogoutModal = function() {
        const modal = ensureInlineLogoutModal();
        const trigger = document.getElementById('sidebar-logout');
        if (!modal) return;

        modal.classList.remove('active');
        modal.style.display = 'none';
        trigger?.setAttribute('aria-expanded', 'false');
        if (trigger) {
            trigger.hidden = false;
            trigger.focus();
        }
        document.getElementById('main-sidebar')?.classList.remove('logout-confirming');
    };

    if (!window.__superadminInlineLogoutEventsBound) {
        document.addEventListener('click', (event) => {
            const modal = document.querySelector('#main-sidebar #sidebar-logout-modal');
            if (
                modal?.classList.contains('active') &&
                event.target instanceof Element &&
                !modal.contains(event.target) &&
                !document.getElementById('sidebar-logout')?.contains(event.target)
            ) {
                window.closeLogoutModal();
            }
        });
        window.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && document.querySelector('#main-sidebar #sidebar-logout-modal.active')) {
                window.closeLogoutModal();
            }
        });
        window.__superadminInlineLogoutEventsBound = true;
    }

    // Ensure the lock screen modal exists
    if (!document.getElementById('lock-screen-overlay')) {
        const lockHTML = `
        <div id="lock-screen-overlay" class="lock-screen-overlay">
            <div class="lock-card">
                <div class="lock-icon"><i class="fas fa-lock"></i></div>
                <h2>Session Locked</h2>
                <p>Please enter your PIN to continue</p>
                <div class="pin-input-group">
                    <input type="password" id="lock-screen-pin" maxlength="6" placeholder="••••" inputmode="numeric" autocomplete="one-time-code">
                    <button onclick="attemptUnlock()" class="btn-unlock">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
                <button onclick="performLogout()" class="btn-lock-logout">Switch Account</button>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', lockHTML);
        // Initialize events after injection
        window.initLockScreenEvents();
    }

    if (!document.getElementById('sidebar-overlay')) {
        const overlay = document.createElement('div');
        overlay.id = 'sidebar-overlay';
        overlay.addEventListener('click', () => {
            if (typeof window.toggleSidebar === 'function') {
                window.toggleSidebar();
            }
        });
        document.body.appendChild(overlay);
    }

    const currentPage = (window.location.pathname || '').split('/').pop().split('?')[0].split('#')[0].toLowerCase();
    const getFileName = (href) => href.split('/').pop().split('?')[0].split('#')[0].toLowerCase();
    const activeClass = (href) => getFileName(href) === currentPage ? ' active' : '';

    return `
<style>
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: 280px;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        padding: 1.25rem 1rem;
        border-right: 1px solid #e5e7eb;
        background: #ffffff;
        transition: width 0.25s ease, transform 0.25s ease, background 0.2s ease;
        overflow: hidden;
    }

    #staff-registration-modal {
        position: fixed;
        inset: 0;
        z-index: 1300;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1.25rem;
        background: rgba(15, 23, 42, 0.56);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
    }

    #staff-registration-modal.active {
        display: flex;
    }

    .staff-registration-dialog {
        position: relative;
        width: min(680px, 100%);
        height: min(820px, calc(100vh - 2.5rem));
        overflow: hidden;
        border: 1px solid rgba(255, 255, 255, 0.8);
        border-radius: 22px;
        background: #ffffff;
        box-shadow: 0 28px 80px rgba(15, 23, 42, 0.28);
        animation: staffRegistrationEnter 0.2s ease-out;
    }

    .staff-registration-frame {
        display: block;
        width: 100%;
        height: 100%;
        border: 0;
        background: transparent;
    }

    .staff-registration-close {
        position: absolute;
        top: 0.85rem;
        right: 0.85rem;
        z-index: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 2.25rem;
        height: 2.25rem;
        border: 0;
        border-radius: 0;
        background: transparent;
        color: #475467;
        cursor: pointer;
        font-size: 1rem;
        transition: color 0.16s ease;
    }

    .staff-registration-close:hover {
        color: #991b1b;
    }

    body.dark-mode .staff-registration-dialog {
        border-color: #374151;
        background: #1f2937;
    }

    body.dark-mode .staff-registration-close {
        color: #e5e7eb;
    }

    @keyframes staffRegistrationEnter {
        from { opacity: 0; transform: translateY(8px) scale(0.99); }
        to { opacity: 1; transform: translateY(0) scale(1); }
    }

    @media (max-width: 640px) {
        #staff-registration-modal {
            padding: 0.5rem;
        }

        .staff-registration-dialog {
            width: 100%;
            height: calc(100vh - 1rem);
            border-radius: 16px;
        }
    }

    .mobile-sidebar-toggle {
        display: none;
        position: fixed;
        top: 14px;
        left: 14px;
        z-index: 1101;
        width: 44px;
        height: 44px;
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        background: #ffffff;
        color: var(--text);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.08);
        transition: background 0.2s ease, border-color 0.2s ease;
    }

    .mobile-sidebar-toggle:hover {
        background: #f8fafc;
    }

    .sidebar-brand {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin: 0 0 1rem;
        padding: 0 0.25rem 1.15rem;
        border-bottom: 1px solid #e5e7eb;
        overflow: visible;
        transition: padding 0.25s ease, margin 0.25s ease;
    }

    body.dark-mode .sidebar {
        background: #1f2937;
        border-right-color: #374151;
    }

    body.dark-mode .sidebar-brand {
        border-bottom-color: #374151;
    }

    body.dark-mode .sidebar-brand .brand-subtitle,
    body.dark-mode .sidebar-brand .brand-title,
    body.dark-mode .sidebar-brand .brand-role {
        color: #f1f5f9;
    }

    body.dark-mode .sidebar-brand .brand-subtitle,
    body.dark-mode .sidebar-brand .brand-role {
        color: #fca5a5;
    }

    body.dark-mode .sidebar-link {
        color: #cbd5e1;
    }

    body.dark-mode .sidebar-link i,
    body.dark-mode .nav-section-label {
        color: #9ca3af;
    }

    body.dark-mode .sidebar-link:hover {
        background: #273449;
        color: #f9fafb;
    }

    body.dark-mode .sidebar-link.active {
        background: #512b31;
        color: #fecaca;
    }

    body.dark-mode .sidebar-link.active i {
        color: #fecaca;
    }

    body.dark-mode .sidebar-footer {
        border-top-color: #374151;
    }

    body.dark-mode .dark-mode-toggle {
        background: #273449;
        border-color: #374151;
        color: #cbd5e1;
    }

    body.dark-mode .dark-mode-toggle:hover,
    body.dark-mode .dark-mode-toggle:focus-visible {
        border-color: #64748b;
        color: #f9fafb;
    }

    body.dark-mode .sidebar.minimized .sidebar-link i {
        background: transparent;
        border-color: transparent;
    }

    .brand-content {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        min-width: 0;
        flex: 1;
    }

    .brand-logo {
        width: 44px;
        height: 44px;
        object-fit: contain;
        flex-shrink: 0;
        border-radius: 50%;
        background: #ffffff;
    }

    .brand-text {
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-width: 0;
        gap: 0.1rem;
        overflow: hidden;
    }

    .brand-text > * {
        margin: 0;
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .brand-subtitle,
    .brand-title,
    .brand-role {
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .brand-subtitle {
        font-size: 0.62rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--muted);
        line-height: 1;
    }

    .brand-title {
        font-size: 0.8rem;
        font-weight: 700;
        color: var(--text);
        letter-spacing: 0;
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: font-size 0.2s ease;
    }

    .brand-role {
        font-size: 0.65rem;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: var(--primary);
        line-height: 1;
    }
    .brand-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        padding: 0;
        background: transparent;
        border: 0;
        border-radius: 0.75rem;
        cursor: pointer;
    }

    .brand-toggle:hover .brand-logo {
        transform: scale(1.06);
        filter: drop-shadow(0 4px 7px rgba(15, 23, 42, 0.14));
    }

    .brand-toggle:focus-visible {
        outline: 3px solid rgba(102, 126, 234, 0.25);
        outline-offset: 4px;
    }

    .brand-logo {
        transition: transform 0.25s ease, filter 0.25s ease;
    }

    .sidebar-nav-wrap {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        flex: 1;
        min-height: 0;
    }

    .nav-section-label {
        margin: 0.9rem 0.7rem 0.4rem;
        padding: 0;
        font-size: 0.67rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #7b8494;
        opacity: 1;
        transition: opacity 0.2s ease;
    }

    .sidebar-nav {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
        overflow-y: auto;
        padding-right: 0.2rem;
    }

    .sidebar-nav::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar-nav::-webkit-scrollbar-thumb {
        background: rgba(var(--border-rgb), 0.3);
        border-radius: 999px;
    }

    /* Base Style */
    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        min-height: 2.85rem;
        padding: 0.65rem 0.75rem;
        border-radius: 8px;
        color: #344054;
        font-weight: 500;
        font-size: 0.88rem;
        text-decoration: none;
        transition: background 0.16s ease, color 0.16s ease;
        position: relative;
        overflow: hidden;
        white-space: nowrap;
    }
    .sidebar-link i {
        width: 1.25rem;
        text-align: center;
        font-size: 0.98rem;
        color: #667085;
        flex-shrink: 0;
    }
    .sidebar-link:hover {
        background: #f5f6f8;
        color: #202939;
        transform: none;
    }
    .sidebar-link:hover i {
        color: inherit;
        transform: none;
    }
    .sidebar-link:focus-visible {
        outline: none;
        box-shadow: inset 0 0 0 2px rgba(185, 28, 28, 0.28);
    }
    .sidebar-link.active {
        background: #fce8e8;
        color: #991b1b;
        font-weight: 600;
    }
    .sidebar-link.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        width: 3px;
        height: 1.15rem;
        background: #b91c1c;
        transform: translateY(-50%);
    }

    .sidebar-footer {
        padding: 0.75rem 0 0;
        margin-top: auto;
        border-top: 1px solid #e5e7eb;
    }

    .sidebar-theme-control { padding: 0.15rem 0 0; }

    .dark-mode-toggle {
        width: 100%;
        min-height: 2.5rem;
        display: inline-flex;
        align-items: center;
        gap: 0.7rem;
        padding: 0.6rem 0.75rem;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        background: #f8f9fb;
        color: #475467;
        cursor: pointer;
        font: inherit;
        font-size: 0.8rem;
        font-weight: 500;
        text-align: left;
        transition: background 0.16s ease, border-color 0.16s ease, color 0.16s ease;
    }

    .dark-mode-toggle:hover,
    .dark-mode-toggle:focus-visible {
        color: #991b1b;
        border-color: #d1d5db;
        background: #f1f3f5;
        outline: none;
    }

    .dark-mode-toggle i { width: 1.1rem; text-align: center; color: #64748b; }

    #main-sidebar.sidebar.minimized .sidebar-theme-control { display: flex; justify-content: center; }
    #main-sidebar.sidebar.minimized .dark-mode-toggle { width: 2.8rem; height: 2.8rem; padding: 0; justify-content: center; }
    #main-sidebar.sidebar.minimized .dark-mode-toggle span { display: none; }

    .btn-logout {
        margin-top: 0.35rem;
        border: 1px solid transparent;
        color: #344054;
    }

    .btn-logout:hover {
        border-color: transparent;
        background: #f5f6f8;
        color: #991b1b;
    }

    @media (min-width: 1025px) {
        .sidebar.minimized {
            width: 92px;
            padding-left: 0.7rem;
            padding-right: 0.7rem;
        }

        .sidebar.minimized .sidebar-brand {
            justify-content: center;
            margin-bottom: 1rem;
            padding: 0.65rem 0;
            border-bottom: 0;
        }

        .sidebar.minimized .brand-content {
            justify-content: center;
        }

        .sidebar.minimized .brand-toggle {
            width: 3.25rem;
            height: 3.25rem;
        }

        .sidebar.minimized .brand-logo {
            width: 3.25rem;
            height: 3.25rem;
        }

        .sidebar.minimized .brand-text,
        .sidebar.minimized .sidebar-link span {
            opacity: 0;
            width: 0;
            overflow: hidden;
            pointer-events: none;
            transition: opacity 0.2s ease, width 0.2s ease;
        }

        .sidebar.minimized .nav-section-label {
            display: none;
        }

        .sidebar.minimized .sidebar-link {
            justify-content: center;
            min-height: 3.25rem;
            padding: 0.35rem;
            border-radius: 0.9rem;
        }

        .sidebar.minimized .sidebar-link i {
            width: 2.75rem;
            height: 2.75rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-size: 1.1rem;
            border-radius: 0.65rem;
            background: transparent;
            border: 0;
        }

        .sidebar.minimized .sidebar-link.active i {
            background: transparent;
            border-color: transparent;
            color: #991b1b;
        }

        .sidebar.minimized .sidebar-footer {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.45rem;
            width: 100%;
            padding-top: 0.75rem;
        }

        .sidebar.minimized .sidebar-theme-control {
            width: 2.8rem;
            padding: 0;
        }

        .sidebar.minimized .btn-logout {
            width: 2.8rem;
            height: 2.8rem;
            min-height: 2.8rem;
            justify-content: center;
            padding: 0;
            margin: 0;
        }

        .sidebar.minimized .btn-logout i {
            width: auto;
            height: auto;
            background: transparent;
            border: 0;
        }
    }

    @media (max-width: 1024px) {
        .sidebar {
            width: 280px;
            transform: translateX(-100%);
            background: #fff;
            border-radius: 0 1.25rem 1.25rem 0;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.12);
        }

        .sidebar.active {
            transform: translateX(0);
        }

        .mobile-sidebar-toggle {
            display: inline-flex;
        }
    }

    @media (max-width: 640px) {
        .sidebar {
            width: 100%;
            max-width: 320px;
        }

        .sidebar-brand {
            padding: 0 0.25rem 1rem;
        }

        .brand-logo {
            width: 40px;
            height: 40px;
        }

        .brand-title {
            font-size: 0.74rem;
        }

        .sidebar-link {
            padding: 0.86rem 0.9rem;
        }
    }

    .logout-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.68);
        backdrop-filter: blur(8px);
        z-index: 1200;
        display: none;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        opacity: 0;
        transition: opacity 0.22s ease;
    }

    .logout-modal-overlay.active { display: flex; opacity: 1; }

    .logout-modal-card {
        width: min(420px, 100%);
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        border-radius: 1.25rem;
        box-shadow: 0 24px 50px rgba(15, 23, 42, 0.18);
        padding: 2rem 1.5rem;
        text-align: center;
        transform: translateY(10px) scale(0.96);
        transition: transform 0.22s ease;
        border: 1px solid rgba(148, 163, 184, 0.18);
    }

    .logout-modal-overlay.active .logout-modal-card { transform: translateY(0) scale(1); }

    .logout-modal-icon {
        width: 72px;
        height: 72px;
        margin: 0 auto 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 999px;
        background: rgba(220, 38, 38, 0.1);
        color: #dc2626;
        font-size: 1.8rem;
    }

    .logout-modal-title { margin: 0; font-size: 1.4rem; font-weight: 800; color: var(--text, #0f172a); }
    .logout-modal-text { margin: 0.8rem 0 1.5rem; color: var(--muted, #64748b); font-size: 0.95rem; line-height: 1.6; }
    .logout-modal-text span { display: block; margin-top: 0.3rem; color: #64748b; }

    .logout-modal-actions { display: flex; gap: 0.75rem; justify-content: center; }

    .btn-modal {
        flex: 1;
        min-width: 0;
        border: none;
        outline: none;
        cursor: pointer;
        font-weight: 700;
        font-size: 0.95rem;
        padding: 0.9rem 1rem;
        border-radius: 0.9rem;
        transition: all 0.18s ease;
    }

    .btn-modal-cancel { background: #eef2ff; color: #1e293b; }
    .btn-modal-cancel:hover { background: #e2e8f0; transform: translateY(-1px); }

    .btn-modal-confirm {
        background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
        color: #ffffff;
        box-shadow: 0 10px 18px rgba(220, 38, 38, 0.22);
    }

    .btn-modal-confirm:hover {
        background: linear-gradient(135deg, #b91c1c 0%, #991b1b 100%);
        transform: translateY(-1px);
        box-shadow: 0 14px 22px rgba(220, 38, 38, 0.28);
    }

    @media (max-width: 480px) {
        .logout-modal-card { padding: 1.75rem 1rem; border-radius: 1rem; }
        .logout-modal-title { font-size: 1.2rem; }
        .logout-modal-text { font-size: 0.9rem; }
        .logout-modal-actions { flex-direction: column; }
    }

    @media (min-width: 1025px) {
        #main-sidebar.sidebar.minimized .sidebar-footer {
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: flex-end !important;
            gap: 0.45rem !important;
            width: 100% !important;
        }

        #main-sidebar.sidebar.minimized .sidebar-theme-control,
        #main-sidebar.sidebar.minimized .btn-logout {
            width: 2.8rem !important;
            margin: 0 !important;
        }

        #main-sidebar.sidebar.minimized .dark-mode-toggle,
        #main-sidebar.sidebar.minimized .btn-logout {
            height: 2.8rem !important;
            min-height: 2.8rem !important;
            padding: 0 !important;
            justify-content: center !important;
        }
    }

    #main-sidebar.sidebar.minimized .sidebar-footer {
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: flex-end !important;
        gap: 0.45rem !important;
        width: 100% !important;
    }

    #main-sidebar.sidebar.minimized .sidebar-theme-control,
    #main-sidebar.sidebar.minimized .btn-logout {
        width: 2.8rem !important;
        margin: 0 !important;
    }

    #main-sidebar.sidebar.minimized .dark-mode-toggle,
    #main-sidebar.sidebar.minimized .btn-logout {
        height: 2.8rem !important;
        min-height: 2.8rem !important;
        padding: 0 !important;
        justify-content: center !important;
    }

    #main-sidebar.sidebar.minimized .sidebar-brand {
        min-height: 0;
        height: 5rem;
        padding: 0 !important;
        margin-top: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #main-sidebar.sidebar.minimized .brand-content {
        width: 100%;
        height: 100%;
        padding: 0 !important;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #main-sidebar.sidebar.minimized .brand-toggle {
        width: 3.25rem;
        height: 3.25rem;
    }

    #main-sidebar.sidebar.minimized .brand-logo {
        width: 3.25rem;
        height: 3.25rem;
        margin: 0 !important;
        display: block !important;
        opacity: 1 !important;
        visibility: visible !important;
        object-fit: contain;
        background: #ffffff !important;
        padding: 0.2rem;
    }

    #main-sidebar #sidebar-logout-modal {
        position: static !important;
        inset: auto !important;
        z-index: auto !important;
        display: none;
        width: 100%;
        padding: 0 !important;
        margin: 0;
        align-items: stretch;
        justify-content: stretch;
        background: transparent !important;
        backdrop-filter: none !important;
        opacity: 1;
        transition: none;
    }

    #main-sidebar #sidebar-logout-modal.active {
        display: block !important;
    }

    #main-sidebar #sidebar-logout-modal .logout-modal-card {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        width: 100%;
        padding: 0.65rem;
        border: 1px solid #fecaca;
        border-radius: 0.85rem;
        background: #fff7f7;
        box-shadow: none;
        text-align: left;
        transform: none;
        transition: none;
    }

    #main-sidebar #sidebar-logout-modal .logout-modal-icon {
        display: none;
    }

    #main-sidebar #sidebar-logout-modal .logout-modal-title {
        flex: 1;
        font-size: 0.78rem;
        white-space: nowrap;
    }

    #main-sidebar #sidebar-logout-modal .logout-modal-actions {
        flex: 1.5;
        flex-direction: row;
        gap: 0.4rem;
    }

    #main-sidebar #sidebar-logout-modal .btn-modal {
        padding: 0.55rem 0.4rem;
        border-radius: 0.6rem;
        font-size: 0.76rem;
        white-space: nowrap;
    }

    #main-sidebar #sidebar-logout-modal .btn-modal-cancel {
        border: 1px solid #cbd5e1;
        background: #ffffff;
        color: #334155;
    }

    #main-sidebar #sidebar-logout-modal .btn-modal-confirm {
        box-shadow: none;
    }

    #main-sidebar #sidebar-logout-modal.active .logout-modal-card {
        transform: none;
    }

    #main-sidebar.logout-confirming .sidebar-footer {
        align-items: stretch !important;
    }

    #main-sidebar.logout-confirming .sidebar-theme-control,
    #main-sidebar #sidebar-logout[hidden] {
        display: none !important;
    }

    #main-sidebar.sidebar.minimized.logout-confirming {
        width: 280px !important;
    }

    body.dark-mode #main-sidebar #sidebar-logout-modal .logout-modal-card {
        border-color: #7f1d1d;
        background: #2b2024;
    }

    body.dark-mode #main-sidebar #sidebar-logout-modal .logout-modal-title {
        color: #f1f5f9;
    }

    body.dark-mode #main-sidebar #sidebar-logout-modal .btn-modal-cancel {
        border-color: #475569;
        background: #273449;
        color: #e2e8f0;
    }
</style>

<aside id="main-sidebar" class="sidebar" aria-label="Main Sidebar">
    <div class="sidebar-brand" id="sidebar-brand-area">
        <div class="brand-content">
            <button onclick="toggleMinimizeSidebar()" id="brand-toggle" class="brand-toggle" title="Collapse sidebar" aria-label="Collapse sidebar" type="button">
                <img src="/GCST_Track_System/assets/images/icons/granby_logo.png" alt="Granby Colleges Logo" class="brand-logo">
            </button>
            <div class="brand-text">
                <span class="brand-subtitle">Granby Colleges of</span>
                <h2 class="brand-title">Science & Technology</h2>
                <span class="brand-role">System Super Admin</span>
            </div>
        </div>

    </div>

    <div class="sidebar-nav-wrap">
        <p class="nav-section-label">Main Menu</p>
        <nav class="sidebar-nav">
            <a href="superadmin_dashb.php" class="sidebar-link${activeClass('superadmin_dashb.php')}" title="Dashboard" onclick="handleSidebarLinkClick()">
                <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
            </a>
            <a href="superadmin_admin_manage.php" class="sidebar-link${activeClass('superadmin_admin_manage.php')}" title="Manage Admins" onclick="handleSidebarLinkClick()">
                <i class="fas fa-user-shield"></i> <span>Manage Admins</span>
            </a>
            <a href="superadmin_student_manage.php" class="sidebar-link${activeClass('superadmin_student_manage.php')}" title="Manage Students" onclick="handleSidebarLinkClick()">
                <i class="fas fa-user-graduate"></i> <span>Manage Students</span>
            </a>
            <a href="register_admin_cashier.php" data-open-staff-registration class="sidebar-link${activeClass('register_admin_cashier.php')}" title="Register Staff" onclick="return openStaffRegistrationModal(event)">
                <i class="fas fa-user-plus"></i> <span>Register Staff</span>
            </a>

            <p class="nav-section-label">System Control</p>
            <a href="superadmin_system_maintenance.php" class="sidebar-link${activeClass('superadmin_system_maintenance.php')}" title="System Maintenance" onclick="handleSidebarLinkClick()">
                <i class="fas fa-tools"></i> <span>System Maintenance</span>
            </a>
        </nav>
    </div>

    <div class="sidebar-footer">
        <div class="sidebar-theme-control">
            <button type="button" id="dark-mode-toggle" class="dark-mode-toggle" title="Switch to dark mode" aria-label="Switch to dark mode" onclick="toggleDarkMode()">
                <i class="fas fa-moon"></i><span>Dark Mode</span>
            </button>
        </div>
        <a href="javascript:void(0)" onclick="openLogoutModal()" id="sidebar-logout" class="sidebar-link btn-logout" aria-controls="sidebar-logout-modal" aria-expanded="false">
            <i class="fas fa-sign-out-alt"></i> <span>Sign Out</span>
        </a>
        <div id="sidebar-logout-modal" class="logout-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="sidebar-logout-title">
            <div class="logout-modal-card">
                <h2 id="sidebar-logout-title" class="logout-modal-title">Sign out?</h2>
                <div class="logout-modal-actions">
                    <button type="button" onclick="closeLogoutModal()" class="btn-modal btn-modal-cancel">Stay</button>
                    <button type="button" onclick="logoutUser()" class="btn-modal btn-modal-confirm">Sign Out</button>
                </div>
            </div>
        </div>
    </div>
</aside>
<div id="staff-registration-modal" hidden onclick="if (event.target === this) closeStaffRegistrationModal()">
    <div class="staff-registration-dialog" role="dialog" aria-modal="true" aria-label="Create staff account">
        <button type="button" class="staff-registration-close" aria-label="Close staff registration" onclick="closeStaffRegistrationModal()">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
        <iframe id="staff-registration-frame" class="staff-registration-frame" title="Create staff account"></iframe>
    </div>
</div>
`;
}