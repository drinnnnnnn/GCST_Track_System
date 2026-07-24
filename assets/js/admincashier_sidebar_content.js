export function getSidebarHTML() {
    // Define global utility functions for the sidebar
    if (!window.__GCST_SIDEBAR_INITIALIZED__) {
        window.__GCST_SIDEBAR_INITIALIZED__ = true;

        const isMobile = () => window.matchMedia("(max-width: 1024px)").matches;

        window.logoutUser = function() {
            const modal = document.getElementById('sidebar-logout-modal');
            if (modal) {
                modal.style.display = 'flex';
                void modal.offsetHeight;
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        };

        window.closeLogoutModal = function() {
            const modal = document.getElementById('sidebar-logout-modal');
            if (!modal) return;
            modal.classList.remove('active');
            document.body.style.overflow = '';
            const onTransitionEnd = (e) => {
                if (e.propertyName === 'opacity' || e.propertyName === 'visibility') {
                    modal.style.display = 'none';
                    modal.removeEventListener('transitionend', onTransitionEnd);
                }
            };
            modal.addEventListener('transitionend', onTransitionEnd);
            setTimeout(() => { if (modal.style.display === 'flex' && !modal.classList.contains('active')) modal.style.display = 'none'; }, 350);
        };

        window.performLogout = function() {
            const toClear = ['sidebar-minimized'];
            toClear.forEach(key => localStorage.removeItem(key));
            sessionStorage.clear(); 
            window.location.replace('/GCST_Track_System/actions/sign_out.php');
        };

        window.handleSidebarLinkClick = function() {
            if (isMobile()) {
                if (typeof window.toggleSidebar === 'function') window.toggleSidebar();
            }
        };

        window.addEventListener('keydown', (e) => { 
            if (e.key === 'Escape') window.closeLogoutModal(); 
        });

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
    }

    // Ensure the logout modal exists in the body
    if (!document.getElementById('sidebar-logout-modal')) {
        const modalHTML = `
        <div id="sidebar-logout-modal" class="logout-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="logout-title">
            <div class="logout-modal-card">
                <div class="logout-modal-icon"><i class="fas fa-sign-out-alt"></i></div>
                <h2 class="logout-modal-title" id="logout-title">Confirm Logout</h2>
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
    :root {
        --sidebar-width: 280px;
        --sidebar-min-width: 88px;
        --primary-blue: #2563eb;
        --bg-sidebar: rgba(255, 255, 255, 0.88);
        --text-main: #0f172a;
        --text-muted: #64748b;
        --border-color: rgba(226, 232, 240, 0.75);
        --nav-transition: all 0.3s ease;
        --surface-hover: #f8fafc;
        --shadow-soft: 0 18px 45px -24px rgba(15, 23, 42, 0.35);
    }

    #main-sidebar.sidebar {
        position: fixed;
        top: 0; left: 0; bottom: 0;
        width: var(--sidebar-width);
        background: linear-gradient(180deg, rgba(255, 255, 255, 0.94), rgba(248, 250, 252, 0.9));
        backdrop-filter: blur(16px) saturate(180%);
        -webkit-backdrop-filter: blur(16px) saturate(180%);
        border-right: 1px solid var(--border-color);
        box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.2), var(--shadow-soft);
        z-index: 1000;
        display: flex;
        flex-direction: column;
        padding: 1rem 0.85rem;
        transition: var(--nav-transition);
        overflow-y: auto;
        overflow-x: hidden;
    }

    #main-sidebar.sidebar::-webkit-scrollbar {
        width: 7px;
    }
    #main-sidebar.sidebar::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.35);
        border-radius: 999px;
    }

    @media (min-width: 1025px) {
        #main-sidebar.sidebar.minimized {
            width: var(--sidebar-min-width);
            padding: 1rem 0.6rem;
        }
        #main-sidebar.sidebar.minimized .brand-text,
        #main-sidebar.sidebar.minimized .nav-section-label,
        #main-sidebar.sidebar.minimized .sidebar-link span {
            opacity: 0; pointer-events: none; width: 0; margin: 0; display: none;
        }
        #main-sidebar.sidebar.minimized .sidebar-brand {
            justify-content: center;
            padding: 0.5rem 0 1rem;
            margin-bottom: 1rem;
        }
        .sidebar.minimized .brand-content {
            justify-content: center;
        }
        #main-sidebar.sidebar.minimized .sidebar-link {
            justify-content: center;
            padding: 0.9rem;
            border-radius: 1rem;
        }
        #main-sidebar.sidebar.minimized .sidebar-link i {
            margin: 0;
            font-size: 1.1rem;
        }
        #main-sidebar.sidebar.minimized .sidebar-minimize-btn { 
            right: -12px;
            transform: translateY(-50%) rotate(180deg);
        }
        #main-sidebar.sidebar.minimized .sidebar-minimize-btn:hover {
            transform: translateY(-50%) rotate(180deg) scale(1.05);
        }
    }

    @media (max-width: 1024px) {
        #main-sidebar.sidebar {
            transform: translateX(-110%);
            background: #ffffff;
            border-radius: 0 1.5rem 1.5rem 0;
            box-shadow: 18px 0 40px rgba(0,0,0,0.08);
        }
        #main-sidebar.sidebar.active { transform: translateX(0); }
        .sidebar-minimize-btn { display: none; }
    }

    .sidebar-brand {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.9rem;
        margin-bottom: 1rem;
        padding: 0.7rem 0.65rem 1rem;
        position: relative;
        border-radius: 1rem;
        transition: background 0.2s ease;
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

    .sidebar-brand:hover {
        background: rgba(248, 250, 252, 0.75);
    }
    .sidebar-brand img {
        width: 46px;
        height: 46px;
        object-fit: contain;
        flex-shrink: 0;
    }
    
    /* Shared layout logic for all brand text elements */
    .brand-subtitle,
    .brand-title,
    .brand-role {
        display: block;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        /* Added line-height to ensure text doesn't 'touch' when stacked */
        line-height: 1.3; 
    }

    /* Brand Subtitle - High contrast, small and clean */
    /* Container: Manages layout and spacing */
    .brand-text {
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 0.15rem; /* Slightly increased for better breathing room */
        padding-top: 1rem;
        min-width: 0;
        overflow: hidden;
    }

    /* Base styles for all text elements inside the container */
    .brand-text > * {
        margin: 0;
        line-height: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    /* Typography Hierarchy */
    .brand-subtitle {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: var(--muted, #64748b);
    }

    .brand-title {
        font-size: 0.85rem;
        font-weight: 600;
        color: var(--text-main, #0f172a);
        letter-spacing: -0.02em;
    }

    .brand-role {
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--muted, #64748b); /* Added color consistency */
        opacity: 0.8; /* Subtle visual separation */
    }


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
}

.sidebar-minimize-btn i {
    font-size: 12px;
    margin-left: 1px; 
    transition: none;
}

.sidebar.minimized .sidebar-minimize-btn i {
    transform: rotate(180deg);
}

    .nav-section-label {
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        color: var(--text-muted);
        margin: 0.9rem 0 0.45rem 0.95rem;
        letter-spacing: 0.1em;
        opacity: 0.82;
    }

    .sidebar-nav {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.3rem;
    }

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

    .sidebar-badge.hidden { display: none; }

    .sidebar-footer {
        margin-top: auto;
        padding-top: 0.8rem;
        border-top: 1px solid var(--border-color);
    }
    .btn-logout {
        color: #ef4444;
        font-weight: 700;
    }
    .btn-logout:hover {
        background: #fef2f2;
        color: #dc2626;
        transform: translateX(2px);
    }

    .logout-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.45);
        backdrop-filter: blur(8px);
        z-index: 10000;
        display: none;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.25s ease;
    }
    .logout-modal-overlay.active { display: flex; opacity: 1; }

    .logout-modal-card {
        background: #ffffff;
        width: min(92%, 420px);
        padding: 2rem 1.75rem;
        border-radius: 1.25rem;
        text-align: center;
        box-shadow: 0 24px 50px -18px rgba(15, 23, 42, 0.4);
        transform: translateY(14px);
        transition: all 0.3s cubic-bezier(0.22, 1, 0.36, 1);
    }
    .logout-modal-overlay.active .logout-modal-card { transform: translateY(0); }

    .logout-modal-icon {
        width: 64px;
        height: 64px;
        background: #fff1f2;
        color: #e11d48;
        font-size: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 1rem;
        margin: 0 auto 1.15rem;
    }

    .logout-modal-title {
        font-weight: 700;
        font-size: 1.4rem;
        color: var(--text-main);
        margin-bottom: 0.65rem;
    }
    .logout-modal-text {
        font-size: 0.95rem;
        color: var(--text-muted);
        line-height: 1.6;
        margin-bottom: 1.5rem;
    }

    .logout-modal-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.85rem;
    }
    .btn-modal {
        padding: 0.85rem;
        border-radius: 0.9rem;
        font-weight: 700;
        cursor: pointer;
        border: none;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .btn-modal:hover {
        transform: translateY(-1px);
    }
    .btn-modal-cancel {
        background: #f1f5f9;
        color: #475569;
    }
    .btn-modal-confirm {
        background: #e11d48;
        color: #ffffff;
    }

    #sidebar-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.18);
        z-index: 999;
        display: none;
        transition: opacity 0.2s ease;
    }
    #sidebar-overlay.active { display: block; }
</style>

<aside id="main-sidebar" class="sidebar" aria-label="Main Sidebar">
    <div class="sidebar-brand" id="sidebar-brand-area">
        <div class="brand-content">
            <img src="/GCST_Track_System/assets/images/icons/granby_logo.png" alt="Granby Colleges Logo" class="brand-logo">
            <div class="brand-text">
                <span class="brand-subtitle">Granby Colleges of</span>
                <h2 class="brand-title">Science & Technology</h2>
                <span class="brand-role">System Cashier Admin</span>
            </div>
        </div>

        <button onclick="toggleMinimizeSidebar()" id="sidebar-minimize-btn" class="sidebar-minimize-btn" title="Toggle Sidebar">
            <i class="fas fa-bars"></i>
        </button>
    </div>

    <p class="nav-section-label">Main Menu</p>
    <nav class="sidebar-nav">
        <a href="/GCST_Track_System/pages/admincashier/admincashier_dashb.html" class="sidebar-link" title="Dashboard" onclick="handleSidebarLinkClick()">
            <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
        <a href="/GCST_Track_System/pages/admincashier/admincashier_cashier.html" class="sidebar-link" title="Cashier" onclick="handleSidebarLinkClick()">
            <i class="fas fa-cash-register"></i> <span>Cashier</span>
        </a>
        <a href="/GCST_Track_System/pages/admincashier/admincashier_sale.html" class="sidebar-link" title="Sales Report" onclick="handleSidebarLinkClick()">
            <i class="fas fa-chart-line"></i> <span>Sales Report</span>
        </a>
        <a href="/GCST_Track_System/pages/admincashier/admincashier_inventorys.html" class="sidebar-link" title="Inventory" onclick="handleSidebarLinkClick()">
            <i class="fas fa-boxes"></i> <span>Inventory</span>
        </a>
        
        <p class="nav-section-label">System Services</p>
        <a href="/GCST_Track_System/pages/admincashier/admincashier_queuing_system.html" class="sidebar-link" title="Queuing System" onclick="handleSidebarLinkClick()">
            <i class="fas fa-users-cog"></i> <span>Queuing System</span>
        </a>
        <a href="/GCST_Track_System/pages/admincashier/admincashier_gmail_notification.html" id="sidebar-gmail-link" class="sidebar-link" title="Gmail Notification" onclick="handleSidebarLinkClick()">
            <i class="fas fa-envelope"></i> <span>Gmail Notification</span>
        </a>
        
        <p class="nav-section-label">Account</p>
        <a href="/GCST_Track_System/pages/admincashier/admincashier_profile.html" class="sidebar-link" title="Profile Settings" onclick="handleSidebarLinkClick()">
            <i class="fas fa-user-circle"></i> <span>Profile Settings</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="javascript:void(0)" onclick="logoutUser()" id="sidebar-logout" class="sidebar-link btn-logout" title="Sign Out">
            <i class="fas fa-sign-out-alt"></i> <span>Sign Out</span>
        </a>
    </div>
</aside>
`;
}