export function getSidebarHTML() {
    // Define global utility functions for the sidebar
    if (!window.logoutUser) {
        window.logoutUser = function() {
            const modal = document.getElementById('sidebar-logout-modal');
            if (modal) {
                modal.style.display = 'flex';
                setTimeout(() => modal.classList.add('active'), 10);
                document.body.style.overflow = 'hidden';
            }
        };

        window.closeLogoutModal = function() {
            const modal = document.getElementById('sidebar-logout-modal');
            if (modal) {
                modal.classList.remove('active');
                setTimeout(() => modal.style.display = 'none', 200);
                document.body.style.overflow = 'auto';
            }
        };

        window.performLogout = function() {
            // Trigger storage event for other tabs to sync logout
            localStorage.setItem('gcst_superadmin_logout_event', Date.now());
            localStorage.removeItem('sidebar-minimized');
            sessionStorage.clear(); 
            window.location.replace('/GCST_Track_System/actions/log_out_superadmin.php');
        };

        // Global listener to close modal on Escape key
        window.addEventListener('keydown', (e) => { 
            if(e.key === 'Escape') window.closeLogoutModal(); 
        });

        window.attemptUnlock = async function() {
            const pinInput = document.getElementById('lock-screen-pin');
            const pin = pinInput.value;
            
            if (!pin) return;

            const success = await window.verifyPinOnly(pin);
            if (success) {
                document.getElementById('lock-screen-overlay').classList.remove('active');
                pinInput.value = '';
            } else {
                pinInput.classList.add('shake');
                setTimeout(() => pinInput.classList.remove('shake'), 500);
            }
        };
    }

    // Ensure the logout modal exists in the body
    if (!document.getElementById('sidebar-logout-modal')) {
        const modalHTML = `
        <div id="sidebar-logout-modal" class="logout-modal-overlay" style="display:none;">
            <div class="logout-modal-card">
                <div class="logout-modal-icon"><i class="fas fa-sign-out-alt"></i></div>
                <h2 class="logout-modal-title">Confirm Logout</h2>
                <p class="logout-modal-text">Are you sure you want to end your session? Make sure all transaction data has been saved.</p>
                <div class="logout-modal-actions">
                    <button onclick="closeLogoutModal()" class="btn-modal btn-modal-cancel">Stay</button>
                    <button onclick="performLogout()" class="btn-modal btn-modal-confirm">Log Out</button>
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
                    <input type="password" id="lock-screen-pin" maxlength="6" placeholder="••••" inputmode="numeric">
                    <button onclick="attemptUnlock()" class="btn-unlock">
                        <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
                <button onclick="performLogout()" class="btn-lock-logout">Switch Account</button>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', lockHTML);
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
        padding: 2rem 1.5rem;
        border-right: 1px solid rgba(0, 0, 0, 0.05);
        background: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(16px);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-sizing: border-box;
    }

    /* Desktop Layout Synchronization */
    @media (min-width: 1025px) {
        .content-wrapper { margin-left: 280px; transition: margin-left 0.3s ease; }
        header { left: 280px !important; width: calc(100% - 280px) !important; transition: all 0.3s ease; }

        .sidebar.minimized { width: 85px; padding: 2rem 0.75rem; }
        .content-wrapper.minimized { margin-left: 85px !important; }
        header.minimized { left: 85px !important; width: calc(100% - 85px) !important; }
    }

    /* Mobile Transition */
    @media (max-width: 1024px) {
        .sidebar { 
            transform: translateX(-100%); 
            width: 280px; 
            border-radius: 0 2rem 2rem 0;
            background: #ffffff;
            border-right: none;
        }
        .sidebar.active { 
            transform: translateX(0); 
            box-shadow: 25px 0 60px -15px rgba(15, 23, 42, 0.3); 
        }

        #sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(4px);
            z-index: 999;
            display: none;
        }

        #sidebar-overlay.active { display: block; }
        .sidebar-minimize-btn { display: none; }
    }

    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        margin-bottom: 2.5rem;
        padding: 0 0.5rem;
        position: relative;
    }

    .sidebar-brand img {
        width: 40px;
        height: 40px;
        flex-shrink: 0;
        object-fit: contain;
    }

    .sidebar.minimized .sidebar-brand { justify-content: center; padding: 0; }

    .sidebar-minimize-btn {
        position: absolute;
        top: 50%;
        right: -28px;
        transform: translateY(-50%);
        background: #2563eb;
        color: white;
        border: none;
        border-radius: 50%;
        width: 26px;
        height: 26px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        transition: all 0.3s ease;
        z-index: 1001;
    }

    .sidebar.minimized .sidebar-minimize-btn { right: 50%; transform: translate(50%, -50%) rotate(180deg); }
    .sidebar.minimized .sidebar-brand h1, 
    .sidebar.minimized .sidebar-brand span, 
    .sidebar.minimized .nav-section-label, 
    .sidebar.minimized .sidebar-link span {
        display: none;
    }

    .nav-section-label {
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        color: #94a3b8;
        margin: 1.5rem 0 0.75rem 1rem;
    }

    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.85rem 1.25rem;
        border-radius: 1.25rem;
        color: #475569;
        font-weight: 600;
        font-size: 0.95rem;
        text-decoration: none;
        transition: all 0.2s ease;
        margin-bottom: 0.35rem;
    }

    .sidebar-link i { font-size: 1.15rem; width: 22px; text-align: center; }
    .sidebar-link:hover { background: #f1f5f9; color: #1e40af; transform: translateX(4px); }
    .sidebar.minimized .sidebar-link:hover { transform: none; }
    .sidebar-link.active { background: #2563eb !important; color: white !important; box-shadow: 0 10px 20px -5px rgba(37, 99, 235, 0.4); }

    /* Sidebar Badge Styles */
    .sidebar-badge {
        background: #ef4444;
        color: white;
        font-size: 0.65rem;
        font-weight: 800;
        padding: 0.2rem 0.5rem;
        border-radius: 99px;
        line-height: 1;
        margin-left: auto;
        box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
        transition: all 0.3s ease;
    }
    .sidebar.minimized .sidebar-badge {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        padding: 0.15rem 0.4rem;
        display: block !important; /* Ensure it stays visible when minimized */
    }
    .hidden { display: none !important; }

    .sidebar-footer { margin-top: auto; padding-top: 1.5rem; border-top: 1px solid rgba(0,0,0,0.05); }
    .btn-logout { background: #fff1f2; color: #e11d48; margin-top: 0.5rem; }
    .btn-logout:hover { background: #e11d48; color: white; }

    /* Lock Screen Styles */
    .lock-screen-overlay {
        position: fixed; inset: 0; background: #0f172a; z-index: 20000;
        display: none; align-items: center; justify-content: center;
        backdrop-filter: blur(20px);
    }
    .lock-screen-overlay.active { display: flex; }
    .lock-card { background: white; padding: 3rem; border-radius: 2.5rem; text-align: center; width: 350px; }
    .lock-icon { font-size: 2.5rem; color: #2563eb; margin-bottom: 1.5rem; }
    .pin-input-group { display: flex; gap: 0.5rem; margin: 1.5rem 0; }
    .pin-input-group input {
        flex: 1; padding: 1rem; border-radius: 1rem; border: 2px solid #e2e8f0;
        text-align: center; font-size: 1.5rem; letter-spacing: 0.5rem; outline: none;
    }
    .btn-unlock {
        width: 55px; background: #2563eb; color: white; border: none;
        border-radius: 1rem; cursor: pointer; transition: 0.2s;
    }
    .btn-unlock:hover { background: #1d4ed8; }
    .btn-lock-logout {
        background: transparent; color: #64748b; border: none;
        font-weight: 600; cursor: pointer; font-size: 0.85rem;
    }
    .shake { animation: shake 0.4s; border-color: #ef4444 !important; }
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-8px); }
        75% { transform: translateX(8px); }
    }

    /* Modal Overlay Styles */
    .logout-modal-overlay {
        position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(8px); z-index: 10000; display: none; align-items: center; justify-content: center;
        opacity: 0; transition: all 0.3s ease; font-family: 'Outfit', sans-serif;
    }
    .logout-modal-overlay.active { display: flex; opacity: 1; }
    .logout-modal-card {
        background: white; width: 90%; max-width: 400px; padding: 2.5rem;
        border-radius: 2rem; text-align: center; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        transform: scale(0.9); transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .logout-modal-overlay.active .logout-modal-card { transform: scale(1); }
    .logout-modal-icon {
        width: 70px; height: 70px; background: #fff1f2; color: #e11d48;
        border-radius: 1.25rem; display: flex; align-items: center; justify-content: center;
        margin: 0 auto 1.5rem; font-size: 1.75rem;
    }
    .logout-modal-title { margin: 0 0 0.5rem; color: #0f172a; font-size: 1.5rem; font-weight: 800; }
    .logout-modal-text { color: #64748b; font-size: 0.95rem; margin-bottom: 2rem; }
    .logout-modal-actions { display: flex; gap: 0.75rem; }
    .btn-modal { flex: 1; padding: 0.85rem; border-radius: 1rem; font-weight: 700; cursor: pointer; border: none; font-family: inherit; transition: all 0.2s; }
    .btn-modal-cancel { background: #f1f5f9; color: #475569; }
    .btn-modal-confirm { background: #2563eb; color: white; }
    .btn-modal-confirm:hover { background: #1d4ed8; transform: translateY(-2px); }
</style>

<aside id="main-sidebar" class="sidebar" aria-label="Main Sidebar">
    <div class="sidebar-brand">
        <img src="/GCST_Track_System/assets/images/icons/granbylogo.png" alt="Logo" class="w-10 h-10">
        <div>
            <h1 class="text-sm font-extrabold text-slate-800 leading-none tracking-tight">GCST TRACK</h1>
            <span class="text-[10px] font-bold text-red-600 uppercase tracking-widest">System Super Admin</span>
        </div>
        <button onclick="toggleMinimizeSidebar()" id="sidebar-minimize-btn" class="sidebar-minimize-btn" title="Toggle Sidebar">
            <i class="fas fa-chevron-left text-[10px]"></i>
        </button>
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
        <a href="javascript:void(0)" onclick="logoutUser()" id="sidebar-logout" class="sidebar-link btn-logout">
            <i class="fas fa-sign-out-alt"></i> <span>Sign Out</span>
        </a>
    </div>
</aside>
`;
}