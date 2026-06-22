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
        window.openLogoutModal = function() {
            const modal = document.getElementById('sidebar-logout-modal');
            if (!modal) return;
            if (modal.classList.contains('active')) return;

            modal.style.display = 'flex';
            requestAnimationFrame(() => {
                modal.classList.add('active');
            });
            document.body.style.overflow = 'hidden';
            const cancelButton = modal.querySelector('.btn-modal-cancel');
            if (cancelButton) cancelButton.focus();
        };

        window.closeLogoutModal = function() {
            const modal = document.getElementById('sidebar-logout-modal');
            if (!modal) return;
            if (!modal.classList.contains('active')) {
                modal.style.display = 'none';
                return;
            }

            modal.classList.remove('active');
            setTimeout(() => {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }, 220);
        };

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

    // Ensure the logout modal exists in the body
    if (!document.getElementById('sidebar-logout-modal')) {
        const modalHTML = `
        <div id="sidebar-logout-modal" class="logout-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="sidebar-logout-title" aria-describedby="sidebar-logout-message" onclick="if (event.target === this) closeLogoutModal()">
            <div class="logout-modal-card">
                <div class="logout-modal-icon" aria-hidden="true">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <h2 id="sidebar-logout-title" class="logout-modal-title">Confirm Sign Out</h2>
                <p id="sidebar-logout-message" class="logout-modal-text">
                    Are you sure you want to sign out of the system?
                </p>
                <div class="logout-modal-actions">
                    <button type="button" onclick="closeLogoutModal()" class="btn-modal btn-modal-cancel">Cancel</button>
                    <button type="button" onclick="logoutUser()" class="btn-modal btn-modal-confirm">Sign Out</button>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
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
        padding: 1rem 0.9rem;
        border-right: 1px solid rgba(var(--border-rgb), 0.18);
        background: rgba(255, 255, 255, 0.78);
        backdrop-filter: blur(18px) saturate(170%);
        -webkit-backdrop-filter: blur(18px) saturate(170%);
        transition: width 0.35s ease, transform 0.35s ease, background 0.25s ease;
        overflow: hidden;
    }

    .sidebar-brand {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin: 0.25rem 0 1.4rem;
        padding: 0.9rem 0.8rem;
        border-radius: 1rem;
        background: rgba(255, 255, 255, 0.65);
        border: 1px solid rgba(var(--border-rgb), 0.12);
        overflow: visible;
        transition: padding 0.25s ease, margin 0.25s ease;
    }

    .brand-content {
        display: flex;
        align-items: center;
        gap: 0.8rem;
        min-width: 0;
        flex: 1;
        padding-top: 1rem;
    }

    .brand-logo {
        width: 46px;
        height: 46px;
        object-fit: contain;
        flex-shrink: 0;
        border-radius: 0.75rem;
        
        /* Pushes the logo down from the top */
        margin-top: 1rem; 
    }

    .brand-text {
        display: flex;
        flex-direction: column;
        justify-content: center;
        min-width: 0;
        gap: 0.1rem;
        overflow: hidden;
        
        /* Adds space from the top of the container */
        padding-top: 1rem; 
    }

    /* Enhances how text behaves inside the brand container */
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
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--muted);
        line-height: 1;
    }

    .brand-title {
    font-size: clamp(0.7rem, 0.8vw, 0.85rem);
    font-weight: 800;
    color: var(--text);
    letter-spacing: -0.01em; 
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    transition: font-size 0.2s ease;
    }

    .brand-role {
        font-size: 0.67rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--primary);
        line-height: 1;
    }
    /* The button container */
    .sidebar-minimize-btn {
        position: absolute;
        top: 20px;
        right: -14px;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #ffffff;
        border: 1px solid #dadce0;
        border-radius: 50%;
        color: #5f6368;
        cursor: pointer;
        z-index: 1005;
        /* Ensure no box-shadow is present */
        box-shadow: none; 
        transition: none;
    }

    .sidebar-minimize-btn:hover {
        background: #f8f9fa;
        border-color: #d1d5da;
        color: #202124;
        box-shadow: none; 
        transition: none;
    }

    /* Icon refinement */
    .sidebar-minimize-btn i {
        font-size: 12px;
        /* This pushes it slightly to the right to visually balance the chevron */
        margin-left: 1px; 
        transition: none;
    }

    /* Instant rotation for Open/Close state */
    .sidebar.minimized .sidebar-minimize-btn i {
        transform: rotate(180deg);
    }

    .sidebar-nav-wrap {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
        flex: 1;
        min-height: 0;
    }

    .nav-section-label {
        margin: 0.75rem 0.75rem 0.35rem;
        padding: 0;
        font-size: 0.68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        color: var(--muted);
        opacity: 0.78;
        transition: opacity 0.2s ease;
    }

    .sidebar-nav {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
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
        gap: 0.95rem;
        padding: 0.9rem 0.95rem;
        border-radius: 0.95rem;
        color: var(--text-muted);
        font-weight: 600;
        font-size: 0.95rem;
        text-decoration: none;
        transition: all 0.2s ease;
        position: relative;
        overflow: hidden;
        white-space: nowrap;
    }
    .sidebar-link i {
        width: 1.2rem;
        text-align: center;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .sidebar-link:hover {
        background: var(--surface-hover);
        color: var(--primary-blue);
        transform: translateX(2px);
    }
    .sidebar-link:focus-visible {
        outline: none;
        box-shadow: inset 0 0 0 2px rgba(37, 99, 235, 0.12);
    }
    .sidebar-link.active {
        background: var(--primary-blue);
        color: #ffffff;
        box-shadow: 0 10px 18px -12px rgba(37, 99, 235, 0.45);
    }
    .sidebar-link.active::before {
        content: '';
        position: absolute;
        left: 0.4rem;
        top: 50%;
        width: 0.25rem;
        height: 1.25rem;
        background: rgba(255, 255, 255, 0.9);
        border-radius: 999px;
        transform: translateY(-50%);
    }

    .sidebar-footer {
        padding: 0.5rem 0 0;
        margin-top: auto;
    }

    .btn-logout {
        margin-top: 0.35rem;
        border: 1px solid transparent;
    }

    .btn-logout:hover {
        border-color: rgba(var(--primary-rgb), 0.12);
    }

    @media (min-width: 1025px) {
        .sidebar.minimized {
            width: 92px;
            padding-left: 0.7rem;
            padding-right: 0.7rem;
        }

        .sidebar.minimized .sidebar-brand {
            justify-content: center;
            padding: 0.9rem 0;
        }

        .sidebar.minimized .brand-content {
            justify-content: center;
        }

        .sidebar.minimized .brand-text,
        .sidebar.minimized .nav-section-label,
        .sidebar.minimized .sidebar-link span {
            opacity: 0;
            width: 0;
            overflow: hidden;
            pointer-events: none;
            transition: opacity 0.2s ease, width 0.2s ease;
        }

        .sidebar.minimized .sidebar-link {
            justify-content: center;
            padding: 0.9rem 0.7rem;
            border-radius: 0.9rem;
        }

        .sidebar.minimized .sidebar-link i {
            margin: 0;
            font-size: 1.1rem;
        }

        .sidebar.minimized .sidebar-footer {
            display: flex;
            justify-content: center;
        }
    }

    @media (max-width: 1024px) {
        .sidebar {
            width: 280px;
            transform: translateX(-100%);
            background: #fff;
            border-radius: 0 1.25rem 1.25rem 0;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12);
        }

        .sidebar.active {
            transform: translateX(0);
        }

        .sidebar-minimize-btn {
            display: none;
        }
    }

    @media (max-width: 640px) {
        .sidebar {
            width: 100%;
            max-width: 320px;
        }

        .sidebar-brand {
            padding: 0.8rem 0.7rem;
        }

        .brand-logo {
            width: 42px;
            height: 42px;
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
</style>

<aside id="main-sidebar" class="sidebar" aria-label="Main Sidebar">
    <div class="sidebar-brand" id="sidebar-brand-area">
        <div class="brand-content">
            <img src="/GCST_Track_System/assets/images/icons/granbylogo.png" alt="Granby Colleges Logo" class="brand-logo">
            <div class="brand-text">
                <span class="brand-subtitle">Granby Colleges of</span>
                <h2 class="brand-title">Science & Technologies</h2>
                <span class="brand-role">System Super Admin</span>
            </div>
        </div>

        <button onclick="toggleMinimizeSidebar()" id="sidebar-minimize-btn" class="sidebar-minimize-btn" title="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <div class="sidebar-nav-wrap">
        <p class="nav-section-label">Main Menu</p>
        <nav class="sidebar-nav">
            <a href="superadmin_dashb.html" class="sidebar-link" title="Dashboard" onclick="handleSidebarLinkClick()">
                <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
            </a>
            <a href="superadmin_admin_manage.html" class="sidebar-link" title="Manage Admins" onclick="handleSidebarLinkClick()">
                <i class="fas fa-user-shield"></i> <span>Manage Admins</span>
            </a>
            <a href="superadmin_student_manage.html" class="sidebar-link" title="Manage Students" onclick="handleSidebarLinkClick()">
                <i class="fas fa-user-graduate"></i> <span>Manage Students</span>
            </a>

            <p class="nav-section-label">System Control</p>
            <a href="register_admin_cashier.html" class="sidebar-link" title="Register Staff" onclick="handleSidebarLinkClick()">
                <i class="fas fa-user-plus"></i> <span>Register Staff</span>
            </a>
            <a href="superadmin_system_maintenance.html" class="sidebar-link" title="System Maintenance" onclick="handleSidebarLinkClick()">
                <i class="fas fa-tools"></i> <span>System Maintenance</span>
            </a>
        </nav>
    </div>

    <div class="sidebar-footer">
        <a href="javascript:void(0)" onclick="openLogoutModal()" id="sidebar-logout" class="sidebar-link btn-logout">
            <i class="fas fa-sign-out-alt"></i> <span>Sign Out</span>
        </a>
    </div>
</aside>
`;
}