export function getSidebarHTML() {
    // Define global utility functions for the sidebar
    if (!window.__GCST_SIDEBAR_INITIALIZED__) {
        window.__GCST_SIDEBAR_INITIALIZED__ = true;

        const isMobile = () => window.matchMedia("(max-width: 1024px)").matches;

        window.logoutUser = function() {
            const modal = document.getElementById('sidebar-logout-modal');
            if (modal) {
                modal.style.display = 'flex';
                // Force reflow to ensure the transition triggers
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

            // Use a one-time listener for cleaner cleanup
            const onTransitionEnd = (e) => {
                if (e.propertyName === 'opacity' || e.propertyName === 'visibility') {
                    modal.style.display = 'none';
                    modal.removeEventListener('transitionend', onTransitionEnd);
                }
            };
            modal.addEventListener('transitionend', onTransitionEnd);
            
            // Fallback for safety
            setTimeout(() => { if (modal.style.display === 'flex' && !modal.classList.contains('active')) modal.style.display = 'none'; }, 350);
        };

        window.performLogout = function() {
            const toClear = ['sidebar-minimized']; // Only clear specific keys or keep full clear if desired
            toClear.forEach(key => localStorage.removeItem(key));
            sessionStorage.clear(); 
            window.location.replace('/GCST_Track_System/actions/sign_out.php');
        };

        window.toggleSidebar = function(forceClose = false) {
            const sidebar = document.getElementById('main-sidebar');
            const overlay = document.getElementById('sidebar-overlay');
            if (sidebar && overlay) {
                const isActive = sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
                
                // Accessibility and body scroll lock
                sidebar.setAttribute('aria-hidden', !isActive);
                if (isMobile()) {
                    document.body.style.overflow = isActive ? 'hidden' : 'auto';
                }
            }
        };

        window.handleSidebarLinkClick = function() {
            if (isMobile()) {
                window.toggleSidebar();
            }
        };

        // Toggle minimized/compact mode (desktop)
        window.toggleMinimizeSidebar = function() {
            const sidebar = document.getElementById('main-sidebar');
            const contentWrapper = document.querySelector('.content-wrapper');
            const header = document.querySelector('header');

            if (sidebar) {
                const isMinimized = sidebar.classList.toggle('minimized');
                contentWrapper?.classList.toggle('minimized');
                header?.classList.toggle('minimized');
                localStorage.setItem('sidebar-minimized', isMinimized);
            }
        };

        // Global keyboard listeners
        window.addEventListener('keydown', (e) => { 
            if (e.key === 'Escape') window.closeLogoutModal(); 
        });

        // Ensure the sidebar overlay exists for mobile view
        if (!document.getElementById('sidebar-overlay')) {
            const overlayHTML = `<div id="sidebar-overlay" onclick="window.toggleSidebar()"></div>`;
            document.body.insertAdjacentHTML('beforeend', overlayHTML);
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
<aside id="main-sidebar" class="sidebar" aria-label="Main Sidebar">
    <div class="sidebar-brand" id="sidebar-brand-area">
        <img src="/GCST_Track_System/assets/images/icons/granbylogo.png" alt="GCST Logo">
        <div class="brand-text">
            <h1>GCST TRACK</h1>
            <span>Admin / Cashier</span>
        </div>
        <button onclick="toggleMinimizeSidebar()" id="sidebar-minimize-btn" class="sidebar-minimize-btn" title="Toggle Sidebar">
            <i class="fas fa-chevron-left" id="minimize-icon"></i>
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
            <span id="sidebar-gmail-badge" class="sidebar-badge hidden">0</span>
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