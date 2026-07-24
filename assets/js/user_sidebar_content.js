export function getSidebarHTML() {
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

        window.logoutUser = function() {
            const modal = document.getElementById('sidebar-logout-modal');
            if (modal) {
                // Close mobile menu if open to avoid UI stacking issues
                const menuWrapper = document.getElementById('nav-menu-wrapper');
                if (menuWrapper?.classList.contains('active')) window.toggleMobileMenu();

                modal.style.display = 'flex';
                // Trigger reflow to ensure the entry transition runs
                void modal.offsetWidth;
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                
                // Accessibility: Set focus on the safe option
                modal.querySelector('.btn-modal-cancel')?.focus();
            }
        };

        window.closeLogoutModal = function() {
            const modal = document.getElementById('sidebar-logout-modal');
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';

                // Handle cleanup after transition finishes
                const cleanup = () => {
                    if (!modal.classList.contains('active')) modal.style.display = 'none';
                    modal.removeEventListener('transitionend', cleanup);
                };
                modal.addEventListener('transitionend', cleanup);
                // Fallback for safety
                setTimeout(() => { if (!modal.classList.contains('active')) modal.style.display = 'none'; }, 400);
            }
        };

        window.performLogout = function() {
            sessionStorage.clear();
            localStorage.clear();
            window.location.replace('/GCST_Track_System/actions/sign_out.php');
        };

        document.addEventListener('click', (e) => {
            const target = e.target;
            if (target && target.id === 'nav-backdrop') {
                window.closeMobileMenu();
                return;
            }

            if (target && target.dataset && target.dataset.action === 'logout') {
                window.logoutUser();
                return;
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
            <div class="logout-modal-card" role="dialog" aria-modal="true" aria-labelledby="logout-title">
                <div class="logout-modal-icon">
                    <i class="fas fa-sign-out-alt"></i>
                </div>
                <h2 class="logout-modal-title" id="logout-title">Confirm Logout</h2>
                <p class="logout-modal-text">Are you sure you want to end your session? Make sure all your activities are saved.</p>
                <div class="logout-modal-actions">
                    <button onclick="closeLogoutModal()" class="btn-modal btn-modal-cancel">Stay</button>
                    <button onclick="performLogout()" class="btn-modal btn-modal-confirm">Confirm</button>
                </div>
            </div>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }

    return `
<style>
    :root {
        --nav-height-base: 80px;
        --primary-blue: #4f46e5;
        --primary-dark: #3730a3;
        --bg-glass: rgba(255, 255, 255, 0.85);
        --nav-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.02);
        --nav-transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .user-top-navbar {
        position: fixed;
        top: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 100%;
        height: var(--nav-height-base);
        background: var(--bg-glass);
        backdrop-filter: blur(16px) saturate(180%);
        -webkit-backdrop-filter: blur(16px) saturate(180%);
        border-bottom: 1px solid rgba(0, 0, 0, 0.08);
        z-index: 1000;
        display: flex;
        justify-content: center;
        box-shadow: var(--nav-shadow);
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
        gap: 1.15rem;
        text-decoration: none;
        padding: 0.6rem 1rem;
        border-radius: 1.25rem;
        transition: var(--nav-transition);
        flex-shrink: 0;
        max-width: 60%;
    }

    .nav-brand:hover {
        background: rgba(79, 70, 229, 0.08);
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.05);
    }

    .nav-brand:active { transform: scale(0.98); }

    .nav-brand img { 
        width: 52px; 
        height: 52px; 
        object-fit: contain;
        filter: drop-shadow(0 6px 12px rgba(0, 0, 0, 0.12));
        transition: transform 0.6s cubic-bezier(0.34, 1.56, 0.64, 1);
        flex-shrink: 0;
    }

    .nav-brand:hover img {
        transform: scale(1.1) rotate(-4deg);
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
        font-weight: 500;
        color: #71717a;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        line-height: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-bottom: 2px;
    }

    .brand-system {
        font-size: 1.35rem;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.02em;
        line-height: 1;
        white-space: nowrap;
        transition: color 0.3s ease;
        text-shadow: 0 1px 2px rgba(0,0,0,0.02);
    }

    .nav-brand:hover .brand-system {
        color: var(--primary-blue);
    }

    .brand-portal {
        font-size: 0.72rem;
        font-weight: 600;
        color: var(--primary-blue);
        text-transform: uppercase;
        letter-spacing: 0.1em;
        margin-top: 3px;
        opacity: 0.85;
    }

    .nav-menu-wrapper {
        display: flex;
        align-items: center;
        gap: 2rem;
        flex: 1;
        justify-content: space-between;
        margin-left: 3rem;
    }

    .nav-center, .nav-right {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        padding: 0.75rem 1.25rem;
        border-radius: 1.15rem;
        color: #64748b;
        font-weight: 600;
        font-size: 0.92rem;
        text-decoration: none;
        transition: var(--nav-transition);
        white-space: nowrap;
        position: relative;
    }

    .nav-item i { 
        font-size: 1.25rem; 
        width: 24px;
        text-align: center;
        transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .nav-item:hover { 
        background: rgba(79, 70, 229, 0.06); 
        color: var(--primary-blue);
        transform: translateY(-2px);
    }

    .nav-item:hover i { transform: scale(1.15) rotate(-5deg); }

    .nav-item.active { 
        background: var(--primary-blue); 
        color: white; 
        box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.3);
    }

    .nav-item.active i { color: white; }

    .nav-item:active { transform: scale(0.97); }

    .hamburger-toggle {
        display: none;
        background: #f1f5f9;
        border: none;
        width: 46px;
        height: 46px;
        border-radius: 12px;
        color: #0f172a;
        cursor: pointer;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        transition: var(--nav-transition);
    }

    .hamburger-toggle:hover {
        background: #e2e8f0;
        transform: scale(1.05);
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
            background: white;
            flex-direction: column;
            padding: 1.5rem;
            gap: 1.5rem;
            margin: 0;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            transform: translateY(-10px);
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .nav-menu-wrapper.active {
            transform: translateY(0);
            opacity: 1;
            visibility: visible;
        }
        
        .nav-center, .nav-right { width: 100%; flex-direction: column; gap: 0.5rem; }
        .nav-item { width: 100%; padding: 0.85rem 1.25rem; font-size: 1rem; }
        .nav-right { padding-top: 1rem; border-top: 1px solid #f1f5f9; }
    }

    @media (max-width: 640px) {
        .nav-brand { max-width: 80%; gap: 0.85rem; padding: 0.4rem 0.6rem; }
        .nav-brand img { width: 44px; height: 44px; }
        .brand-college { font-size: 0.58rem; letter-spacing: 0.05em; }
        .brand-system { font-size: 1.15rem; }
        .brand-portal { font-size: 0.65rem; letter-spacing: 0.06em; }
        .nav-inner { padding: 0 1rem; }
    }

    /* MODAL DESIGN SYSTEM */
    .logout-modal-overlay {
        position: fixed; inset: 0; background: rgba(15, 23, 42, 0.45); 
        backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
        z-index: 10000; display: none; align-items: center; justify-content: center;
        opacity: 0; transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        padding: 1.25rem;
    }
    .logout-modal-overlay.active { display: flex; opacity: 1; }

    .logout-modal-card {
        background: #ffffff; width: 100%; max-width: 420px; padding: 2.5rem 2rem; 
        border-radius: 2rem; text-align: center; 
        box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25);
        transform: scale(0.9) translateY(20px); 
        transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .logout-modal-overlay.active .logout-modal-card { transform: scale(1) translateY(0); }

    .logout-modal-icon {
        width: 72px; height: 72px; background: #fff1f2; color: #ef4444; 
        font-size: 1.75rem; display: flex; align-items: center; justify-content: center; 
        border-radius: 1.25rem; margin: 0 auto 1.5rem; transform: rotate(-5deg);
    }
    .logout-modal-title { font-weight: 800; font-size: 1.65rem; color: #0f172a; margin-bottom: 0.75rem; letter-spacing: -0.02em; }
    .logout-modal-text { font-size: 0.95rem; color: #64748b; line-height: 1.6; margin-bottom: 2.5rem; }

    .logout-modal-actions { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .btn-modal { 
        padding: 0.9rem; border-radius: 1.15rem; font-weight: 700; cursor: pointer; 
        border: none; transition: all 0.25s ease; font-family: inherit;
    }
    .btn-modal-cancel { background: #f1f5f9; color: #475569; }
    .btn-modal-cancel:hover { background: #e2e8f0; transform: translateY(-1px); }
    .btn-modal-confirm { background: #ef4444; color: white; }
    .btn-modal-confirm:hover { background: #dc2626; box-shadow: 0 8px 20px -4px rgba(239, 68, 68, 0.4); transform: translateY(-2px); }
    .btn-modal:active { transform: translateY(0); }

    .nav-logout { color: #ef4444; font-weight: 700; }
    .nav-logout:hover { background: #fff1f2 !important; color: #e11d48 !important; }
</style>

<div id="nav-backdrop" class="nav-backdrop" onclick="closeMobileMenu()"></div>
<nav class="user-top-navbar">
    <div class="nav-inner">
        <a href="InUser_home.html" class="nav-brand">
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
                <a href="InUser_home.html" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i> <span>Dashboard</span>
                </a>
                <a href="user_browse_products.html" class="nav-item">
                    <i class="fas fa-shopping-bag"></i> <span>Browse Products</span>
                </a>
                <a href="user_queue_tickets.html" class="nav-item">
                    <i class="fas fa-ticket-alt"></i> <span>Queue Tickets</span>
                </a>
            </div>

            <div class="nav-right">
                <a href="user_profile.html" class="nav-item">
                    <i class="fas fa-user-circle"></i> <span>Profile</span>
                </a>
                <a href="javascript:void(0)" data-action="logout" class="nav-item nav-logout">
                    <i class="fas fa-sign-out-alt"></i> <span>Log Out</span>
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