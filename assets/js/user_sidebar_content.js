export function getSidebarHTML() {
    let activeLogoutTrigger = null;
    let logoutTriggerVisibility = '';
    let logoutCloseTimer = null;

    // Initialize Navigation Logic
    if (!window.__GCST_NAV_INITIALIZED__) {
        window.__GCST_NAV_INITIALIZED__ = true;

        window.toggleMobileMenu = function() {
            const menuWrapper = document.getElementById('nav-menu-wrapper');
            const hamburger = document.querySelector('.hamburger-toggle i');
            const backdrop = document.getElementById('nav-backdrop');
            
            const isOpen = menuWrapper?.classList.toggle('active');
            backdrop?.classList.toggle('active');
            
            if (hamburger) {
                hamburger.className = isOpen ? 'fas fa-times' : 'fas fa-bars';
            }
            
            document.body.style.overflow = isOpen ? 'hidden' : '';
        };

        // Close menu on backdrop click
        window.closeMobileMenu = function() {
            const menuWrapper = document.getElementById('nav-menu-wrapper');
            if (menuWrapper?.classList.contains('active')) window.toggleMobileMenu();
        };

        window.logoutUser = function(trigger) {
            const modal = document.getElementById('sidebar-logout-modal');
            if (modal) {
                if (logoutCloseTimer) {
                    clearTimeout(logoutCloseTimer);
                    logoutCloseTimer = null;
                }
                const logoutTrigger = trigger || document.querySelector('[data-action="logout"]');
                const triggerRect = logoutTrigger?.getBoundingClientRect();
                activeLogoutTrigger = logoutTrigger;
                modal.style.display = 'block';
                // Trigger reflow to ensure the entry transition runs
                void modal.offsetWidth;
                modal.classList.add('active');

                if (triggerRect) {
                    const card = modal.querySelector('.logout-modal-card');
                    const cardRect = card.getBoundingClientRect();
                    const margin = 12;
                    const viewport = window.visualViewport;
                    const viewportLeft = viewport?.offsetLeft || 0;
                    const viewportTop = viewport?.offsetTop || 0;
                    const viewportWidth = viewport?.width || window.innerWidth;
                    const viewportHeight = viewport?.height || window.innerHeight;
                    const availableWidth = viewportWidth - margin * 2;
                    const cardWidth = Math.min(Math.max(triggerRect.width, cardRect.width), availableWidth);
                    const left = Math.max(viewportLeft + margin, Math.min(
                        triggerRect.right - cardWidth,
                        viewportLeft + viewportWidth - cardWidth - margin
                    ));
                    const top = Math.max(
                        viewportTop + margin,
                        Math.min(
                            triggerRect.top + (triggerRect.height - cardRect.height) / 2,
                            viewportTop + viewportHeight - cardRect.height - margin
                        )
                    );

                    modal.style.left = `${left}px`;
                    modal.style.top = `${top}px`;
                    card.style.width = `${cardWidth}px`;
                    logoutTriggerVisibility = logoutTrigger.style.visibility;
                    logoutTrigger.style.visibility = 'hidden';
                }
                
                // Accessibility: Set focus on the safe option
                modal.querySelector('.btn-modal-cancel')?.focus();
            }
        };

        window.closeLogoutModal = function() {
            const modal = document.getElementById('sidebar-logout-modal');
            if (modal) {
                modal.classList.remove('active');

                const cleanup = (event) => {
                    if (event && (event.target !== modal || event.propertyName !== 'opacity')) return;
                    modal.removeEventListener('transitionend', cleanup);
                    if (!modal.classList.contains('active')) {
                        modal.style.display = 'none';
                        modal.style.left = '';
                        modal.style.top = '';

                        const card = modal.querySelector('.logout-modal-card');
                        card.style.width = '';
                        if (activeLogoutTrigger) {
                            activeLogoutTrigger.style.visibility = logoutTriggerVisibility;
                            activeLogoutTrigger = null;
                            logoutTriggerVisibility = '';
                        }
                    }
                    if (logoutCloseTimer) {
                        clearTimeout(logoutCloseTimer);
                        logoutCloseTimer = null;
                    }
                };
                modal.addEventListener('transitionend', cleanup);
                logoutCloseTimer = setTimeout(() => cleanup(), 250);
            }
        };

        window.performLogout = function() {
            sessionStorage.clear();
            localStorage.clear();
            window.location.replace('/GCST_Track_System/actions/sign_out.php');
        };

        window.toggleUserDarkMode = function() {
            const isDark = document.body.classList.toggle('dark-mode');
            document.documentElement.classList.toggle('dark-mode', isDark);
            localStorage.setItem('user-dark-mode', isDark ? 'true' : 'false');
            window.syncUserDarkModeToggle();
        };

        window.syncUserDarkModeToggle = function() {
            const button = document.getElementById('user-dark-mode-toggle');
            const icon = button?.querySelector('i');
            const text = button?.querySelector('span');
            const isDark = document.body.classList.contains('dark-mode');
            const label = isDark ? 'Switch to light mode' : 'Switch to dark mode';
            button?.setAttribute('title', label);
            button?.setAttribute('aria-label', label);
            if (icon) icon.className = isDark ? 'fas fa-sun' : 'fas fa-moon';
            if (text) text.textContent = isDark ? 'Light Mode' : 'Dark Mode';
        };

        if (localStorage.getItem('user-dark-mode') === 'true') {
            document.body.classList.add('dark-mode');
            document.documentElement.classList.add('dark-mode');
        }

        document.addEventListener('click', (e) => {
            const target = e.target;
            if (target && target.id === 'nav-backdrop') {
                window.closeMobileMenu();
                return;
            }

            const logoutTrigger = target.closest && target.closest('[data-action="logout"]');
            if (logoutTrigger) {
                window.logoutUser(logoutTrigger);
                return;
            }

            const modal = document.getElementById('sidebar-logout-modal');
            if (modal?.classList.contains('active') && !modal.contains(target)) {
                window.closeLogoutModal();
            }

            if (target && target.id === 'sidebar-logout-modal') {
                window.closeLogoutModal();
            }
        });

        window.addEventListener('keydown', (e) => { 
            if (e.key === 'Escape') window.closeLogoutModal(); 
        });
    }

    // Define the logout handler globally so the sidebar's onclick can access it
    // Ensure modal exists in body, not just in the sidebar string
    if (!document.getElementById('sidebar-logout-modal')) {
        const modalHTML = `
        <div id="sidebar-logout-modal" class="logout-modal-overlay" style="display:none;">
            <div class="logout-modal-card" role="dialog" aria-modal="false" aria-labelledby="logout-title">
                <h2 class="logout-modal-title" id="logout-title">Sign out?</h2>
                <div class="logout-modal-actions">
                    <button type="button" onclick="closeLogoutModal()" class="btn-modal btn-modal-cancel">Stay</button>
                    <button type="button" onclick="performLogout()" class="btn-modal btn-modal-confirm">Sign out</button>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    return `
<style>
    :root {
        --nav-height-base: 72px;
        --primary-blue: #2563eb;
        --primary-dark: #1d4ed8;
        --nav-transition: background-color 0.18s ease, color 0.18s ease, border-color 0.18s ease;
    }

    .user-top-navbar {
        position: fixed;
        top: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 100%;
        height: var(--nav-height-base);
        background: #ffffff;
        border-bottom: 1px solid #e5e7eb;
        z-index: 1000;
        display: flex;
        justify-content: center;
    }

    .nav-inner {
        width: 100%;
        max-width: 1400px;
        padding: 0 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        height: 100%;
    }

    .nav-brand {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        text-decoration: none;
        padding: 0;
        transition: color 0.18s ease;
        flex-shrink: 0;
        max-width: 60%;
    }

    .nav-brand img { 
        width: 46px;
        height: 46px;
        object-fit: contain;
        background: #ffffff;
        padding: 3px;
        border-radius: 50%;
        flex-shrink: 0;
    }

    .brand-text {
        display: flex;
        flex-direction: column;
        justify-content: center;
        line-height: 1.1;
        min-width: 0;
    }
    
    .brand-title {
        display: flex;
        flex-direction: column;
        margin: 0;
    }

    .brand-college {
        font-size: 0.68rem;
        font-weight: 600;
        color: #667085;
        text-transform: uppercase;
        letter-spacing: 0.045em;
        line-height: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 2px;
    }

    .brand-system {
        font-size: 1.2rem;
        font-weight: 700;
        color: #0f172a;
        letter-spacing: -0.015em;
        line-height: 1;
        white-space: nowrap;
    }

    .brand-portal {
        font-size: 0.65rem;
        font-weight: 700;
        color: var(--primary-blue);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-top: 3px;
    }

    .nav-menu-wrapper {
        display: flex;
        align-items: center;
        gap: 1.25rem;
        flex: 1;
        justify-content: space-between;
        margin-left: 2rem;
    }

    .nav-center, .nav-right {
        display: flex;
        align-items: center;
        gap: 0.35rem;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding: 0.65rem 0.85rem;
        border-radius: 7px;
        color: #475569;
        font-weight: 500;
        font-size: 0.9rem;
        text-decoration: none;
        transition: var(--nav-transition);
        white-space: nowrap;
        position: relative;
        border: 1px solid transparent;
    }

    .nav-item i { 
        font-size: 1rem;
        width: 20px;
        text-align: center;
    }

    .nav-item:hover { 
        background: #f3f4f6;
        color: var(--primary-blue);
    }

    .nav-item.active { 
        background: #eff6ff;
        border-color: #dbeafe;
        color: var(--primary-dark);
        font-weight: 600;
    }

    .nav-item.active i { color: var(--primary-dark); }

    .nav-theme-toggle {
        border: 0;
        background: transparent;
        font: inherit;
        cursor: pointer;
    }

    .hamburger-toggle {
        display: none;
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        width: 42px;
        height: 42px;
        border-radius: 8px;
        color: #334155;
        cursor: pointer;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        transition: background-color 0.18s ease, border-color 0.18s ease;
    }

    .hamburger-toggle:hover {
        background: #e2e8f0;
    }

    .nav-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(4px);
        z-index: 999;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }
    .nav-backdrop.active { opacity: 1; visibility: visible; }

    @media (max-width: 991px) {
        .hamburger-toggle { display: flex; }
        
        .nav-menu-wrapper {
            position: fixed;
            top: var(--nav-height-base);
            left: 0;
            right: 0;
            background: #ffffff;
            flex-direction: column;
            padding: 1rem;
            gap: 0.75rem;
            margin: 0;
            border-bottom: 1px solid #e5e7eb;
            box-shadow: 0 8px 16px rgba(15, 23, 42, 0.08);
            transform: translateY(-6px);
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.18s ease, transform 0.18s ease, visibility 0.18s ease;
        }

        .nav-menu-wrapper.active {
            transform: translateY(0);
            opacity: 1;
            visibility: visible;
        }
        
        .nav-center, .nav-right { width: 100%; flex-direction: column; gap: 0.35rem; }
        .nav-item { width: 100%; padding: 0.75rem 0.85rem; font-size: 0.95rem; }
        .nav-right { padding-top: 0.75rem; border-top: 1px solid #e5e7eb; }
    }

    @media (max-width: 640px) {
        .nav-brand { max-width: 80%; gap: 0.65rem; }
        .nav-brand img { width: 40px; height: 40px; }
        .brand-college { font-size: 0.53rem; letter-spacing: 0.035em; }
        .brand-system { font-size: 1.05rem; }
        .brand-portal { font-size: 0.6rem; letter-spacing: 0.05em; }
        .nav-inner { padding: 0 1rem; }
    }

    /* MODAL DESIGN SYSTEM */
    .logout-modal-overlay {
        position: fixed; inset: auto; z-index: 10000; display: none;
        opacity: 0; transition: opacity 0.15s ease; padding: 0;
        pointer-events: none;
    }
    .logout-modal-overlay.active { display: block; opacity: 1; }

    .logout-modal-card {
        display: flex; align-items: center; justify-content: space-between; gap: 0.7rem;
        background: #fff8f8; width: max-content; max-width: calc(100vw - 24px); padding: 0.6rem;
        border-radius: 12px; text-align: left;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        transform: translateY(4px);
        transition: transform 0.15s ease;
        border: 1px solid #fecaca;
        pointer-events: auto;
    }
    .logout-modal-overlay.active .logout-modal-card { transform: translateY(0); }

    @media (min-width: 641px) {
        .logout-modal-card { min-width: 220px; }
    }

    .logout-modal-title { flex: 1; white-space: nowrap; font-weight: 600; font-size: 0.8rem; color: #111827; margin: 0 0.1rem; }

    .logout-modal-actions { display: flex; flex-shrink: 0; gap: 0.4rem; }
    .btn-modal {
        min-height: 38px; padding: 0.5rem 0.75rem; border-radius: 9px; font-weight: 600; cursor: pointer;
        border: 1px solid transparent; transition: background-color 0.15s ease, border-color 0.15s ease;
        font-family: inherit; font-size: 0.78rem; white-space: nowrap;
    }
    .btn-modal:focus-visible { outline: 3px solid rgba(37, 99, 235, 0.35); outline-offset: 2px; }
    .btn-modal-cancel { background: #ffffff; color: #344054; border-color: #d0d5dd; }
    .btn-modal-cancel:hover { background: #f9fafb; }
    .btn-modal-confirm { background: #dc2626; color: #ffffff; }
    .btn-modal-confirm:hover { background: #b91c1c; }

    @media (max-width: 640px) {
        .logout-modal-card { gap: 0.45rem; padding: 0.45rem; }
        .btn-modal { min-height: 36px; padding: 0.45rem 0.65rem; }
    }

    body.dark-mode .logout-modal-card {
        background: #261b20;
        border-color: #7f1d1d;
    }
    body.dark-mode .logout-modal-title { color: #e5edf8; }
    body.dark-mode .btn-modal-cancel { background: #1e293b; color: #e5edf8; border-color: #475569; }
    body.dark-mode .btn-modal-cancel:hover { background: #334155; }

    .nav-logout { color: #ef4444; font-weight: 700; }
    .nav-logout:hover { background: #fff1f2 !important; color: #e11d48 !important; }
</style>

<div id="nav-backdrop" class="nav-backdrop" onclick="closeMobileMenu()"></div>
<nav class="user-top-navbar">
    <div class="nav-inner">
        <a href="InUser_home.php" class="nav-brand">
            <img src="/GCST_Track_System/assets/images/icons/granby_logo.png" alt="Logo">
            <div class="brand-text">
                <h1 class="brand-title">
                    <span class="brand-college">Granby Colleges of Science and Technology</span>
                    <span class="brand-system">Track System</span>
                </h1>
                <span class="brand-portal">Student Portal</span>
            </div>
        </a>

        <div class="nav-menu-wrapper" id="nav-menu-wrapper">
            <div class="nav-center">
                <a href="InUser_home.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
                </a>
                <a href="user_browse_products.php" class="nav-item">
                    <i class="fas fa-shopping-bag"></i> <span>Browse Products</span>
                </a>
                <a href="user_queue_tickets.php" class="nav-item">
                    <i class="fas fa-ticket-alt"></i> <span>Queue Tickets</span>
                </a>
            </div>

            <div class="nav-right">
                <a href="user_profile.php" class="nav-item">
                    <i class="fas fa-user-circle"></i> <span>Profile</span>
                </a>
                <button type="button" id="user-dark-mode-toggle" class="nav-item nav-theme-toggle" onclick="toggleUserDarkMode()" aria-label="Switch to dark mode" title="Switch to dark mode">
                    <i class="fas fa-moon"></i> <span>Dark Mode</span>
                </button>
                <a href="javascript:void(0)" data-action="logout" class="nav-item nav-logout">
                    <i class="fas fa-sign-out-alt"></i> <span>Sign Out</span>
                </a>
            </div>
        </div>

        <button class="hamburger-toggle" onclick="toggleMobileMenu()" aria-label="Toggle Menu">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</nav>
`;
}