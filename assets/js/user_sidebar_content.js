export function getSidebarHTML() {
    // Define functions once
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
        // Initialize after DOM injection
        setTimeout(window.initSidebarGestures, 200);
    }

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
                <div class="logout-modal-icon"><i class="fas fa-door-open"></i></div>
                <h2 class="logout-modal-title">Confirm Logout</h2>
                <p class="logout-modal-text">Are you sure you want to end your session? Make sure all your work has been saved.</p>
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

    /* Sidebar Layout Logic - Controls main page structure */
    @media (min-width: 1025px) {
        .content-wrapper { margin-left: 280px; transition: margin-left 0.4s ease; }
        header { left: 280px !important; width: calc(100% - 280px) !important; transition: all 0.4s ease; }

      /* Adjust content and header when sidebar is minimized */
        .sidebar.minimized { width: 90px; padding: 2rem 0.75rem; }
        .content-wrapper.minimized { margin-left: 90px !important; }
        header.minimized { left: 90px !important; width: calc(100% - 90px) !important; }
    }

    /* Integrated Logout Modal Styles */
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
        .sidebar { 
            transform: translateX(-100%); 
            transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
            border-radius: 0 1.5rem 1.5rem 0;
            width: 300px;
            box-shadow: 10px 0 30px rgba(15, 23, 42, 0.15);
            border-right: none;
        }
        .sidebar.active { transform: translateX(0); }

        /* Enhanced Touch Targets */
        .sidebar-link {
            padding: 1.1rem 1.5rem;
            margin-bottom: 0.8rem;
            font-size: 1rem;
        }
        .sidebar-footer { padding-bottom: 2rem; }
        .nav-section-label { margin-top: 2rem; font-size: 0.75rem; }
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
        width: 42px;
        height: 42px;
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

    @media (max-width: 1024px) {
        .sidebar-minimize-btn { display: none; }
    }

    .sidebar.minimized .sidebar-minimize-btn { right: 50%; transform: translate(50%, -50%) rotate(180deg); }
    .sidebar.minimized .sidebar-brand img { transform: scale(1.1); }
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
        color: white;
        box-shadow: 0 8px 16px -4px rgba(102, 126, 234, 0.4); 
    }
    .sidebar-link.active i { color: white; }

    .sidebar-footer { margin-top: auto; padding-top: 1.5rem; border-top: 1px solid var(--border-soft); }
    .btn-logout { background: transparent; color: var(--danger); }
    .btn-logout:hover { background: var(--danger); color: white; box-shadow: 0 8px 16px -4px rgba(239, 68, 68, 0.4); }
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