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
                    <span>You will need to log in again to access the dashboard.</span>
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
    /* Main Sidebar */
    .sidebar {
        position: fixed;
        top: 0; left: 0; bottom: 0;
        width: 280px;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        padding: 1.5rem 1rem;
        border-right: 1px solid rgba(var(--border-rgb), 0.2);
        background: rgba(255, 255, 255, 0.75);
        backdrop-filter: blur(20px) saturate(180%);
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: visible !important; /* Pinapayagan ang button na lumampas sa border */
    }

    /* Container ng Brand: Dito nakalagay ang button */
    .sidebar-brand {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 2.5rem;
        padding: 0 0.75rem 0 0.5rem;
        position: relative;
        overflow: visible !important; /* Kritikal ito para lumitaw ang button sa labas */
    }

    /* Minimize Button */
    .sidebar-minimize-btn {
        position: absolute;
        top: 50%;
        right: -32px; /* Inilabas natin nang malayo sa border */
        transform: translateY(-50%);
        background: #ffffff;
        color: var(--primary);
        border: 1px solid rgba(102, 126, 234, 0.25);
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 10px rgba(0,0,0,0.15);
        z-index: 2000; /* Mas mataas kaysa sa sidebar */
        transition: none !important;
    }

    .sidebar-minimize-btn:hover {
        background: var(--primary);
        color: white;
    }

    /* Responsive adjustments */
    @media (min-width: 1025px) {
        .sidebar.minimized { width: 88px; }
        .sidebar.minimized .brand-text,
        .sidebar.minimized .nav-section-label,
        .sidebar.minimized .sidebar-link span {
            display: none !important;
        }
    }
        
        /* FIX: Agarang tinatago ang text wrapper para hindi maabutan o maharangan ng minimize button */
        .sidebar.minimized .sidebar-brand .brand-text,
        .sidebar.minimized .nav-section-label,
        .sidebar.minimized .sidebar-link span {
            opacity: 0 !important;
            pointer-events: none !important;
            width: 0 !important;
            margin: 0 !important;
            padding: 0 !important;
            display: none !important; /* Siguradong burado sa espasyo kapag maliit */
        }

        /* SPECIFIC LOGO FIX: Pinapagitna ang logo container kapag naka-minimize ang sidebar */
        .sidebar.minimized .sidebar-brand { justify-content: center !important; padding: 0 !important; margin-bottom: 2.5rem; }
        .sidebar.minimized .brand-logo-wrapper { margin: 0 auto !important; display: flex !important; }
        
        .sidebar.minimized .sidebar-link { justify-content: center; padding: 0.8rem; border-radius: 1rem; }
        .sidebar.minimized .sidebar-link i { margin: 0; font-size: 1.25rem; }
        
        .sidebar.minimized .sidebar-footer { padding: 1rem 0; display: flex; justify-content: center; }
    }

    /* --- RESPONSIVE MOBILE VIEWPORTS --- */
    @media (max-width: 1024px) {
        .sidebar { 
            width: 280px; 
            transform: translateX(-100%); 
            background: #ffffff;
            border-radius: 0 1.5rem 1.5rem 0;
        }
        .sidebar.active { transform: translateX(0); }
        .sidebar-minimize-btn { display: none; }
    }

    .sidebar-brand {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        margin-bottom: 2.5rem;
        padding: 0 0.75rem 0 0.5rem; /* Dinagdagan ang kanang padding para lumayo sa button */
        position: relative;
        transition: all 0.3s ease;
    }

    /* BRAND LOGO WRAPPER STYLE */
    .brand-logo-wrapper {
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: all 0.3s ease;
    }

    .sidebar-brand h1 {
        font-size: 1.15rem;
        font-weight: 800;
        color: var(--text);
        letter-spacing: -0.02em;
        white-space: nowrap;
    }

    .sidebar-brand span {
        font-size: 0.7rem;
        font-weight: 700;
        color: var(--primary);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        display: block;
    }

    .nav-section-label {
        font-size: 0.65rem;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--muted);
        margin: 1.5rem 0 0.5rem 1.25rem;
        opacity: 0.6;
        white-space: nowrap;
        transition: opacity 0.3s ease;
    }

    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.85rem 1.25rem;
        border-radius: 1rem;
        color: var(--muted);
        font-weight: 600;
        font-size: 0.92rem;
        text-decoration: none;
        margin-bottom: 0.25rem;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .sidebar-link i { font-size: 1.1rem; width: 24px; text-align: center; }
    .sidebar-link:hover { background: rgba(var(--primary-rgb), 0.05); color: var(--primary); transform: translateX(4px); }
    .sidebar-link.active { 
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%) !important;
        color: white !important; 
        box-shadow: 0 8px 16px rgba(var(--primary-rgb), 0.25);
    }
    .sidebar.minimized .sidebar-link:hover { transform: scale(1.05); }

    /* --- SIDEBAR MINIMIZE BUTTON (FIXED POSITION & NO ANIMATIONS) --- */
.sidebar-minimize-btn {
    position: absolute;
    top: 50%;
    right: -20px; /* Dito natin inusog pa lalo sa labas (mula -14px) */
    transform: translateY(-50%) !important;
    background: #ffffff;
    color: var(--primary);
    border: 1px solid rgba(102, 126, 234, 0.25);
    border-radius: 50%;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.12);
    z-index: 1005;
    font-size: 0.8rem;
    transition: none !important;
    animation: none !important;
}

/* Siguraduhing kahit naka-minimized, naka-lock siya sa posisyong ito */
.sidebar.minimized .sidebar-minimize-btn {
    right: -20px; 
    transform: translateY(-50%) !important;
}

    /* --- MODALS AND OVERLAYS --- */
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
    <div class="sidebar-brand p-5 border-b border-slate-100 flex items-center justify-between gap-3 relative w-full" id="sidebar-brand-area">
    <div class="flex items-center gap-2.5 min-w-0">
        <img src="/GCST_Track_System/assets/images/icons/granbylogo.png" alt="Granby Colleges Logo" class="w-10 h-10 object-contain shrink-0">
        
        <div class="brand-text flex flex-col justify-center font-sans min-w-0">
            <span class="text-[8px] font-bold uppercase tracking-wider text-slate-400 leading-none mb-0.5">
                Granby Colleges of
            </span>
            <h1 class="text-[10px] font-extrabold text-slate-100 tracking-tight leading-none">
                Science & Technologies
            </h1>
            <span class="text-[8px] font-bold text-amber-600 mt-1 leading-none tracking-wide uppercase">
                System Super Admin
            </span>
        </div>
    </div>
        
    <button onclick="toggleMinimizeSidebar()" id="sidebar-minimize-btn" class="sidebar-minimize-btn" title="Toggle Sidebar">
        <i class="fas fa-bars"></i>
    </button>
    </div>

</div>
    <p class="nav-section-label">Main Menu</p>
    <nav class="flex-1 overflow-y-auto pr-1">
        <a href="superadmin_dashb.html" class="sidebar-link">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
        <a href="superadmin_admin_manage.html" class="sidebar-link">
            <i class="fas fa-user-shield"></i> <span>Manage Admins</span>
        </a>
        <a href="superadmin_student_manage.html" class="sidebar-link">
            <i class="fas fa-user-graduate"></i> <span>Manage Students</span>
        </a>

        <p class="nav-section-label">System Control</p>
        <a href="register_admin_cashier.html" class="sidebar-link">
            <i class="fas fa-user-plus"></i> <span>Register Staff</span>
        </a>
        <a href="superadmin_system_maintenance.html" class="sidebar-link">
            <i class="fas fa-tools"></i> <span>System Maintenance</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="javascript:void(0)" onclick="openLogoutModal()" id="sidebar-logout" class="sidebar-link btn-logout">
            <i class="fas fa-sign-out-alt"></i> <span>Sign Out</span>
        </a>
    </div>
</aside>
`;
}