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
            sessionStorage.clear();
            localStorage.clear();
            window.location.replace('/GCST_Track_System/actions/log_out.php');
        };

        // Global listener to close modal on Escape key
        window.addEventListener('keydown', (e) => { 
            if(e.key === 'Escape') window.closeLogoutModal(); 
        });
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

    return `
<style>
    .sidebar {
        position: fixed;
        top: 0; left: 0; bottom: 0;
        width: 280px;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        padding: 2rem 1.25rem;
        border-right: 1px solid var(--border-soft);
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(20px) saturate(180%);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.1);
        box-sizing: border-box;
    }

    /* Desktop Layout Synchronization */
    @media (min-width: 1025px) {
        .content-wrapper { margin-left: 280px; transition: margin-left 0.4s ease; }
        header { left: 280px !important; width: calc(100% - 280px) !important; transition: all 0.4s ease; }

        .sidebar.minimized { width: 90px; padding: 2rem 0.75rem; }
        .content-wrapper.minimized { margin-left: 90px !important; }
        header.minimized { left: 90px !important; width: calc(100% - 90px) !important; }
    }

    /* Mobile Transition */
    @media (max-width: 1024px) {
        /* Sidebar mobile styles are now managed in admincashier.css */
        #sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.3);
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
        gap: 1rem;
        margin-bottom: 3rem;
        padding: 0 0.5rem;
        position: relative;
    }

    .sidebar-brand img {
        width: 48px;
        height: 48px;
        transition: transform 0.3s ease;
    }

    .sidebar-minimize-btn {
        position: absolute;
        top: 50%;
        right: -25px;
        transform: translateY(-50%);
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 50%;
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        z-index: 1001;
    }

    .sidebar.minimized .sidebar-minimize-btn { right: 50%; transform: translate(50%, -50%) rotate(180deg); }
    .sidebar.minimized .sidebar-brand img { transform: scale(1.1); }
    .sidebar.minimized .sidebar-brand h1, 
    .sidebar.minimized .sidebar-brand span, 
    .sidebar.minimized .nav-section-label, 
    .sidebar.minimized .sidebar-link span {
        display: none;
    }

    .sidebar-brand h1 {
        font-size: 1.3rem; /* Increased font size for brand name */
    }

    .sidebar-brand span {
        font-size: 0.75rem; /* Increased font size for role text (12px) */
    }

    .sidebar.minimized .nav-section-label, 
    .sidebar.minimized .sidebar-link span {
        display: none;
    }

    .nav-section-label {
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.15em;
        color: var(--muted);
        margin: 1.5rem 0 0.75rem 1rem;
        opacity: 0.7;
    }

    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 0.9rem 1.25rem;
        border-radius: 1.25rem;
        color: var(--muted);
        font-weight: 600;
        font-size: 0.95rem;
        text-decoration: none;
        transition: all 0.3s ease;
        margin-bottom: 0.5rem;
        position: relative;
    }

    .sidebar-link i { font-size: 1.2rem; width: 24px; text-align: center; transition: transform 0.3s ease; }
    .sidebar-link:hover { background: var(--surface-soft); color: var(--primary); transform: translateX(5px); }
    .sidebar-link:hover i { transform: scale(1.1); }
    .sidebar.minimized .sidebar-link:hover { transform: none; }
    .sidebar-link.active { 
        background: var(--primary) !important; 
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%) !important;
        color: white !important; 
        box-shadow: 0 8px 16px -4px rgba(102, 126, 234, 0.4); 
    }
    .sidebar-link.active i { color: white; }

    /* Sidebar Badge Styles */
    .sidebar-badge {
        background: var(--danger);
        color: white;
        font-size: 0.7rem;
        font-weight: 800;
        padding: 0.2rem 0.5rem;
        border-radius: 99px;
        line-height: 1;
        margin-left: auto;
        box-shadow: 0 4px 8px rgba(239, 68, 68, 0.3);
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

    .sidebar-footer { margin-top: auto; padding-top: 1.5rem; border-top: 1px solid var(--border-soft); }
    .btn-logout { background: transparent; color: var(--danger); }
    .btn-logout:hover { background: var(--danger); color: white; box-shadow: 0 8px 16px -4px rgba(239, 68, 68, 0.4); }

    /* Modal Overlay Styles */
    .logout-modal-overlay {
        position: fixed; inset: 0; background: rgba(15, 23, 42, 0.5);
        backdrop-filter: blur(12px); z-index: 10000; display: none; align-items: center; justify-content: center;
        opacity: 0; transition: all 0.3s ease; font-family: 'Poppins', sans-serif;
    }
    .logout-modal-overlay.active { display: flex; opacity: 1; }
    .logout-modal-card {
        background: white; width: 90%; max-width: 420px; padding: 3rem 2.5rem;
        border-radius: 2.5rem; text-align: center; box-shadow: var(--shadow-lg);
        transform: scale(0.9); transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    .logout-modal-overlay.active .logout-modal-card { transform: scale(1); }
    .logout-modal-icon {
        width: 80px; height: 80px; background: #fef2f2; color: var(--danger);
        border-radius: 1.5rem; display: flex; align-items: center; justify-content: center;
        margin: 0 auto 1.5rem; font-size: 2rem; transform: rotate(-5deg);
    }
    .logout-modal-title { margin: 0 0 0.5rem; color: var(--text); font-size: 1.75rem; font-weight: 800; }
    .logout-modal-text { color: var(--muted); font-size: 1rem; margin-bottom: 2.5rem; line-height: 1.6; }
    .logout-modal-actions { display: flex; gap: 1rem; }
    .btn-modal { flex: 1; padding: 1rem; border-radius: 1.25rem; font-weight: 700; cursor: pointer; border: none; transition: all 0.3s; }
    .btn-modal-cancel { background: var(--surface-soft); color: var(--text); }
    .btn-modal-confirm { background: var(--primary); color: white; box-shadow: 0 10px 15px -3px rgba(102, 126, 234, 0.3); }
    .btn-modal-confirm:hover { transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(102, 126, 234, 0.5); }
</style>

<aside id="main-sidebar" class="sidebar" aria-label="Main Sidebar">
    <div class="sidebar-brand">
        <img src="/GCST_Track_System/assets/images/icons/granbylogo.png" alt="Logo" class="w-10 h-10">
        <div>
            <h1 class="text-sm font-extrabold text-slate-800 leading-none tracking-tight">GCST TRACK</h1>
            <span class="text-[10px] font-bold text-blue-600 uppercase tracking-widest">Admin / Cashier</span>
        </div>
        <button onclick="toggleMinimizeSidebar()" id="sidebar-minimize-btn" class="sidebar-minimize-btn" title="Toggle Sidebar">
            <i class="fas fa-chevron-left text-[10px]"></i>
        </button>
    </div>

    <p class="nav-section-label">Main Menu</p>
    <nav class="flex-1 overflow-y-auto pr-1">
        <a href="/GCST_Track_System/pages/admincashier/admincashier_dashb.html" class="sidebar-link">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
        <a href="/GCST_Track_System/pages/admincashier/admincashier_cashier.html" class="sidebar-link">
            <i class="fas fa-cash-register"></i> <span>Cashier</span>
        </a>
        <a href="/GCST_Track_System/pages/admincashier/admincashier_sale.html" class="sidebar-link">
            <i class="fas fa-chart-line"></i> <span>Sales Report</span>
        </a>
        <a href="/GCST_Track_System/pages/admincashier/admincashier_inventorys.html" class="sidebar-link">
            <i class="fas fa-boxes"></i> <span>Inventory</span>
        </a>
        
        <p class="nav-section-label">System Services</p>
        <a href="/GCST_Track_System/pages/admincashier/admincashier_queuing_system.html" class="sidebar-link">
            <i class="fas fa-users-cog"></i> <span>Queuing System</span>
        </a>
        <a href="/GCST_Track_System/pages/admincashier/admincashier_gmail_notification.html" class="sidebar-link">
            <i class="fas fa-envelope"></i> <span>Gmail Notification</span>
            <span id="sidebar-gmail-badge" class="sidebar-badge hidden">0</span>
        </a>
        
        <p class="nav-section-label">Account</p>
        <a href="/GCST_Track_System/pages/admincashier/admincashier_profile.html" class="sidebar-link">
            <i class="fas fa-user-circle"></i> <span>Profile Settings</span>
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