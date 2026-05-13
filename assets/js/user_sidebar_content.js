export function getSidebarHTML() {
    // Define functions once
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

        window.addEventListener('keydown', (e) => { 
            if(e.key === 'Escape') window.closeLogoutModal(); 
        });
    }

    // Define the logout handler globally so the sidebar's onclick can access it
    // Ensure modal exists in body, not just in the sidebar string
    if (!document.getElementById('sidebar-logout-modal')) {
        const modalHTML = `
        <div id="sidebar-logout-modal" class="logout-modal-overlay" style="display:none;">
            <div class="logout-modal-card">
                <div class="logout-modal-icon"><i class="fas fa-sign-out-alt"></i></div>
                <h2 class="logout-modal-title">Ready to leave?</h2>
                <p class="logout-modal-text">You are about to sign out. Make sure you've finished any active tasks before leaving.</p>
                <div class="logout-modal-actions">
                    <button onclick="closeLogoutModal()" class="btn-modal btn-modal-cancel">Cancel</button>
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
        top: 0;
        left: 0;
        bottom: 0;
        width: 280px;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        padding: 2rem 1.5rem;
        border-right: 1px solid rgba(255, 255, 255, 0.3);
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(12px);
        transition: transform 0.3s ease;
        box-sizing: border-box; /* Ensure padding doesn't increase width */
    }

    /* Sidebar Layout Logic - Controls main page structure */
    @media (min-width: 1025px) {
        .content-wrapper { margin-left: 280px; transition: margin-left 0.3s ease; }
        header { left: 280px; width: calc(100% - 280px); transition: all 0.3s ease; }

      /* Adjust content and header when sidebar is minimized */
        .content-wrapper.minimized { margin-left: 80px; }
        header.minimized { left: 80px; width: calc(100% - 80px); }
    }

    /* Integrated Logout Modal Styles */
    .logout-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(10px);
        z-index: 10000;
        display: none;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: all 0.2s ease;
        font-family: 'Outfit', sans-serif;
    }
    .logout-modal-overlay.active { display: flex; opacity: 1; }
    .logout-modal-card {
        background: white;
        width: 92%;
        max-width: 420px;
        padding: 3rem 2rem;
        border-radius: 2.5rem;
        text-align: center;
        box-shadow: 0 25px 50px -12px rgba(0,0,0,0.15);
        transform: translateY(20px);
        transition: all 0.2s ease-out;
    }
    .logout-modal-overlay.active .logout-modal-card { transform: translateY(0); }
    .logout-modal-icon {
        width: 80px;
        height: 80px;
        background: #fff1f2;
        color: #f43f5e;
        border-radius: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2rem;
        transform: rotate(-10deg);
    }
    .logout-modal-title { margin: 0 0 0.75rem; color: #0f172a; font-size: 1.75rem; font-weight: 800; letter-spacing: -0.025em; }
    .logout-modal-text { color: #64748b; font-size: 1rem; line-height: 1.6; margin-bottom: 2.5rem; }
    .logout-modal-actions { display: flex; gap: 1rem; }
    .btn-modal { flex: 1; padding: 1rem; border-radius: 1.25rem; font-weight: 700; cursor: pointer; transition: all 0.3s ease; border: none; font-size: 1rem; font-family: inherit; }
    .btn-modal-cancel { background: #f1f5f9; color: #475569; }
    .btn-modal-cancel:hover { background: #e2e8f0; }
    .btn-modal-confirm { background: #2563eb; color: white; text-decoration: none; display: flex; align-items: center; justify-content: center; box-shadow: 0 10px 15px -3px rgba(29, 78, 216, 0.2); }
    .btn-modal-confirm:hover { background: #1d4ed8; transform: translateY(-2px); box-shadow: 0 10px 15px -3px rgba(29, 78, 216, 0.4); }

    #sidebar-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(4px);
        z-index: 900;
        display: none;
    }
    #sidebar-overlay.active { display: block; }

    @media (max-width: 1024px) {
        .sidebar { transform: translateX(-100%); }
        .sidebar.active { transform: translateX(0); }
    }

    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 2.5rem;
        padding: 0 0.5rem;
    }

    .sidebar-minimize-btn {
        position: absolute;
        top: 2rem;
        right: -16px; /* Position outside the sidebar slightly */
        background: var(--primary);
        color: white;
        border: none;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        transition: all 0.3s ease;
        z-index: 1001; /* Above sidebar */
    }
    .sidebar-minimize-btn:hover {
        background: var(--primary-dark);
        transform: scale(1.05);
    }
    /* Hide on small screens where the mobile toggle is used */
    @media (max-width: 1024px) {
        .sidebar-minimize-btn { display: none; }
    }

    /* Minimized Sidebar Styles */
    .sidebar.minimized {
        width: 80px;
        padding: 2rem 0.5rem;
    }
    .sidebar.minimized .sidebar-minimize-btn i {
        transform: rotate(180deg); /* Flip chevron */
    }
    .sidebar.minimized .sidebar-brand {
        justify-content: center;
        margin-bottom: 2rem;
    }
    .sidebar.minimized .sidebar-brand h1,
    .sidebar.minimized .sidebar-brand span,
    .sidebar.minimized .nav-section-label {
        display: none;
    }
    .sidebar.minimized .sidebar-link {
        justify-content: center;
        padding: 0.85rem 0.5rem;
        gap: 0; /* Remove gap when text is hidden */
    }
    .sidebar.minimized .sidebar-link i {
        margin-right: 0;
    }
    .sidebar.minimized .sidebar-link span {
        display: none;
    }
    .sidebar.minimized .sidebar-footer {
        padding-top: 1rem;
    }

    .nav-section-label {
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.1em;
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
        transition: all 0.3s ease;
        margin-bottom: 0.25rem;
    }

    .sidebar-link i { font-size: 1.1rem; width: 20px; text-align: center; flex-shrink: 0; }

    .sidebar-link:hover {
        background: #eff6ff;
        color: #2563eb;
        transform: translateX(5px);
    }

    .sidebar-link.active {
        background: #2563eb;
        color: white;
        box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.3);
    }

    .sidebar-footer { margin-top: auto; padding-top: 2rem; }
    .btn-logout { background: #fee2e2; color: #dc2626; }
    .btn-logout:hover { background: #dc2626; color: white; }
</style>

<aside id="main-sidebar" class="sidebar">
    <div class="sidebar-brand">
        <img src="/GCST_Track_System/assets/images/icons/granbylogo.png" alt="Logo" class="w-10 h-10">
        <div>
            <h1 class="text-sm font-extrabold text-slate-800 leading-none">GCST TRACK</h1>
            <span class="text-[10px] font-bold text-blue-600 uppercase tracking-widest">Student Portal</span>
        </div>
        <button onclick="toggleMinimizeSidebar()" id="sidebar-minimize-btn" class="sidebar-minimize-btn" title="Toggle Sidebar">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <p class="nav-section-label">Main Navigation</p>
    <nav class="flex-1">
        <a href="InUser_home.html" class="sidebar-link"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a>
        <a href="user_browse_products.html" class="sidebar-link"><i class="fas fa-shopping-bag"></i> <span>Browse Products</span></a>
        <a href="user_rentals.html" class="sidebar-link"><i class="fas fa-book"></i> <span>My Rentals</span></a>
        <a href="user_queue_tickets.html" class="sidebar-link"><i class="fas fa-ticket-alt"></i> <span>Queue Tickets</span></a>
        
        <p class="nav-section-label">Account</p>
        <a href="user_profile.html" class="sidebar-link"><i class="fas fa-user-circle"></i> <span>Profile</span></a>
    </nav>

    <div class="sidebar-footer">
        <a href="javascript:void(0)" onclick="logoutUser()" id="sidebar-logout" class="sidebar-link btn-logout">
            <i class="fas fa-sign-out-alt"></i> <span>Log Out</span>
        </a>
    </div>
</aside>
`;
}