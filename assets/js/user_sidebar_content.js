export function getSidebarHTML() {
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
            <div class="logout-modal-card" role="dialog" aria-modal="true">
                <div class="logout-modal-icon">
                    <i class="fas fa-door-open"></i>
                </div>
                <h2 class="logout-modal-title">End Session?</h2>
                <p class="logout-modal-text">You are about to log out of your student portal. Please ensure all your current activities are completed.</p>
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
    :root {
        --nav-height-base: 72px;
        --primary-blue: #2563eb;
        --bg-glass: rgba(255, 255, 255, 0.8);
        --nav-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        --nav-transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .content-wrapper { 
        margin-left: 0 !important; 
        padding-top: calc(var(--nav-height-base) + 2.5rem) !important;
        transition: none !important;
    }
    
    header:not(.user-top-navbar) { display: none !important; }
    #sidebar-overlay { display: none !important; }

    .user-top-navbar {
        position: fixed;
        top: 1rem;
        left: 50%;
        transform: translateX(-50%);
        width: calc(100% - 2.5rem);
        max-width: 1400px;
        min-height: var(--nav-height-base);
        height: auto;
        background: var(--bg-glass);
        backdrop-filter: blur(16px) saturate(180%);
        -webkit-backdrop-filter: blur(16px) saturate(180%);
        border: 1px solid rgba(255, 255, 255, 0.4);
        border-radius: 1.5rem;
        z-index: 1000;
        display: flex;
        justify-content: center;
        box-shadow: var(--nav-shadow);
        transition: var(--nav-transition);
    }

    .user-top-navbar:hover {
        background: rgba(255, 255, 255, 0.95);
        border-color: rgba(255, 255, 255, 0.6);
        box-shadow: 0 25px 30px -5px rgba(0, 0, 0, 0.15);
    }

    .nav-inner {
        width: 100%;
        max-width: 1400px;
        padding: 0.5rem 1.5rem;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        transition: var(--nav-transition);
    }

    .nav-brand {
        display: flex;
        align-items: center;
        flex: 0 0 auto;
        gap: 0.85rem;
        text-decoration: none;
        transition: opacity 0.2s ease;
    }

    .nav-brand:active { opacity: 0.8; }

    .nav-brand img { 
        width: 44px; 
        height: 44px; 
        object-fit: contain;
        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); 
    }

    .brand-text {
        display: flex;
        flex-direction: column;
        justify-content: center;
        line-height: 1.1;
    }
    
    .brand-text h1 {
        font-size: 1.2rem;
        font-weight: 600;
        color: #0f172a;
        margin: 0;
        letter-spacing: -0.015em;
    }

    .nav-brand:hover img {
        transform: scale(1.1) rotate(-6deg);
    }

    .brand-text span {
        font-size: 0.68rem;
        font-weight: 600;
        color: var(--primary-blue);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-top: 1px;
    }

    .nav-center {
        display: flex;
        justify-content: center;
        flex: 1;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.5rem 1rem;
        border-radius: 0.85rem;
        color: #64748b;
        font-weight: 600;
        font-size: 0.9rem;
        text-decoration: none;
        transition: all 0.25s ease;
        white-space: nowrap;
    }

    .nav-item i { width: 1.2rem; text-align: center; }
    .nav-item i { font-size: 1.1rem; transition: transform 0.2s; }
    .nav-item:hover { background: #f1f5f9; color: var(--primary-blue); }
    .nav-item:hover i { transform: translateY(-2px); }
    
    .nav-item.active {
        background: var(--primary-blue);
        color: white;
        box-shadow: 0 8px 12px -3px rgba(37, 99, 235, 0.2);
    }

    .nav-right {
        display: flex;
        justify-content: flex-end;
        flex: 0 0 auto;
        gap: 0.5rem;
    }

    .nav-logout {
        color: #ef4444;
        font-weight: 700;
    }

    .nav-logout:hover {
        background: #fef2f2 !important;
        color: #dc2626 !important;
    }

    .logout-modal-overlay {
        position: fixed; 
        inset: 0; 
        background: rgba(15, 23, 42, 0.6);
        backdrop-filter: blur(8px); z-index: 10000; display: none; align-items: center; justify-content: center;
        opacity: 0; 
        transition: opacity 0.3s ease;
        padding: 20px;
    }

    .logout-modal-overlay.active { display: flex; opacity: 1; }

    .logout-modal-card {
        background: #ffffff; 
        width: 100%; 
        max-width: 400px; 
        padding: 2.5rem 2rem;
        border-radius: 1.5rem; 
        text-align: center; 
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        transform: translateY(20px) scale(0.95); 
        transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .logout-modal-overlay.active .logout-modal-card { 
        transform: translateY(0) scale(1); 
    }

    .logout-modal-icon {
        width: 64px;
        height: 64px;
        background: #fff1f2;
        color: #e11d48;
        font-size: 1.75rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 1.25rem;
        margin: 0 auto 1.5rem;
        transition: transform 0.3s ease;
    }

    .logout-modal-card:hover .logout-modal-icon {
        transform: scale(1.1) rotate(-5deg);
    }

    .logout-modal-title {
        color: #0f172a;
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
    }

    .logout-modal-text {
        color: #64748b;
        font-size: 0.95rem;
        line-height: 1.6;
        margin-bottom: 2rem;
    }

    .logout-modal-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .btn-modal {
        padding: 0.85rem;
        border-radius: 0.85rem;
        font-weight: 700;
        font-size: 0.9rem;
        cursor: pointer;
        border: none;
        transition: all 0.2s ease;
    }

    .btn-modal-cancel {
        background: #f1f5f9;
        color: #475569;
    }

    .btn-modal-cancel:hover {
        background: #e2e8f0;
        color: #1e293b;
    }

    .btn-modal-confirm {
        background: #e11d48;
        color: #ffffff;
        box-shadow: 0 4px 12px rgba(225, 29, 72, 0.2);
    }

    .btn-modal-confirm:hover {
        background: #be123c;
        box-shadow: 0 6px 20px rgba(225, 29, 72, 0.3);
        transform: translateY(-1px);
    }

    /* Responsive Optimization */
    @media (max-width: 1024px) {
        .nav-inner { padding: 0.5rem 1rem; }
        .nav-item { padding: 0.5rem 0.85rem; font-size: 0.85rem; }
        .nav-item i { font-size: 1rem; }
    }

    @media (max-width: 820px) {
        .nav-brand .brand-text span { display: none; }
        .nav-center { order: 3; width: 100%; margin-top: 0.5rem; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 0.5rem; }
        .user-top-navbar { border-radius: 1.25rem; }
        .content-wrapper { padding-top: 140px !important; }
    }

    @media (max-width: 600px) {
        .nav-inner { padding: 0.5rem 0.75rem; }
        .nav-brand { gap: 0.6rem; }
        .nav-brand img { width: 36px; height: 36px; }
        .brand-text h1 { font-size: 1rem; }
        .brand-text span { display: none; }

        .nav-item { flex-direction: column; gap: 0.25rem; padding: 0.5rem; font-size: 0.75rem; min-width: 64px; }
        .nav-item i { font-size: 1.1rem; }
        .nav-center { justify-content: space-evenly; gap: 0; }
        .nav-right .nav-item span { display: none; }
        .nav-right .nav-item { min-width: auto; padding: 0.75rem; border-radius: 50%; aspect-ratio: 1/1; }
        .content-wrapper { padding-top: 150px !important; }
    }

    @media (max-width: 400px) {
        .user-top-navbar { width: calc(100% - 1rem); top: 0.5rem; }
        .nav-item span { font-size: 0.65rem; }
        .nav-brand img { width: 32px; height: 32px; }
        .nav-brand { gap: 0.5rem; }
        .brand-text h1 { font-size: 0.9rem; }
        .nav-center { margin-top: 0.4rem; padding-top: 0.4rem; }
        .content-wrapper { padding-top: 140px !important; }
    }
</style>

<nav class="user-top-navbar">
    <div class="nav-inner">
        <a href="InUser_home.html" class="nav-brand">
            <img src="/GCST_Track_System/assets/images/icons/granbylogo.png" alt="Logo">
            <div class="brand-text">
                <h1>GCST TRACK</h1>
                <span>Student Portal</span>
            </div>
        </a>

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
            <a href="javascript:void(0)" onclick="logoutUser()" class="nav-item nav-logout">
                <i class="fas fa-sign-out-alt"></i> <span>Log Out</span>
            </a>
        </div>
    </div>
</nav>
`;
}