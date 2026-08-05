﻿<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Manage Admins | GCST Super Admin</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/superadmin.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#dc2626',
                        danger: '#ef4444',
                        success: '#10b981'
                    },
                    fontFamily: { sans: ['Plus Jakarta Sans', 'Outfit', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        #toastContainer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            pointer-events: none;
        }

        .toast {
            min-width: 280px;
            max-width: 360px;
            padding: 0.9rem 1rem;
            border-radius: 0.9rem;
            color: #fff;
            font-size: 0.92rem;
            font-weight: 600;
            box-shadow: 0 16px 35px -12px rgba(15, 23, 42, 0.35);
            opacity: 0;
            transform: translateX(18px);
            transition: opacity 0.25s ease, transform 0.25s ease;
            pointer-events: auto;
        }

        .toast.show {
            opacity: 1;
            transform: translateX(0);
        }

        .toast.hide {
            opacity: 0;
            transform: translateX(18px);
        }

        .toast-success { background: #16a34a; }
        .toast-error { background: #dc2626; }
        .toast-warning { background: #f59e0b; }
        .toast-info { background: #2563eb; }

        .signature-preview-box {
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 1rem;
            padding: 0.85rem;
            margin-top: 0.75rem;
            max-width: 100%;
        }
        .signature-preview-box .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.65rem;
        }
        .signature-preview-box .preview-header span {
            font-size: 0.75rem;
            color: #475569;
        }
        .signature-preview-box .preview-content {
            width: 100%;
            min-height: 64px;
            border: 1px solid #cbd5e1;
            border-radius: 0.85rem;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            padding: 0.45rem;
        }
        .signature-preview-box .preview-content img {
            max-width: 100%;
            max-height: 56px;
            object-fit: contain;
            border-radius: 0.75rem;
        }
        .signature-preview-box .preview-meta {
            margin-top: 0.55rem;
            font-size: 0.72rem;
            color: #64748b;
        }
        .modal-subtitle {
            font-size: 0.85rem;
            color: #475569;
            margin-top: -0.25rem;
        }
        .modal-fieldset {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }
        @media (min-width: 768px) {
            .modal-fieldset { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        .modal-section {
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1rem;
            background: #fff;
        }
        .modal-section-title {
            font-size: 0.85rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.9rem;
        }
        .filter-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.7rem 1rem;
            border-radius: 9999px;
            border: 1px solid #d1d5db;
            background: #ffffff;
            color: #334155;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
        }
        .filter-pill:hover {
            background: #f8fafc;
        }
        .filter-pill.active {
            background: #dc2626;
            border-color: #dc2626;
            color: #ffffff;
        }
        .filter-pill-count {
            display: inline-flex;
            min-width: 1.5rem;
            justify-content: center;
            border-radius: 9999px;
            background: rgba(15, 23, 42, 0.08);
            padding: 0.1rem 0.45rem;
            font-size: 0.75rem;
            font-weight: 700;
        }
    </style>
</head>

<body class="antialiased font-sans">
    <div id="sidebar-container"></div>
    <div id="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="content-wrapper">
        <main>
            
            <section class="greeting-section animate-fade-in">
                <div class="greeting-content">
                    <h1>Account Management</h1>
                    <p>Monitor administrative staff credentials, update roles, and manage system access permissions across the platform.</p>
                </div>
            </section>

            <section class="balance-section mb-8">
                <div class="balance-cards grid gap-4 sm:grid-cols-3">
                    <div class="balance-card group relative overflow-hidden bg-white border border-slate-100 rounded-2xl p-6 shadow-sm transition-all duration-200 hover:shadow-md hover:border-slate-200">
                        <div class="absolute left-0 top-0 bottom-0 w-1 bg-primary transform scale-y-0 group-hover:scale-y-100 transition-transform duration-200"></div>
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1 font-sans">
                                    Total Accounts
                                </h3>
                                <p class="text-3xl font-extrabold text-slate-800 tracking-tight font-sans" id="stat-total">0</p>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-slate-50 flex items-center justify-center text-slate-400 group-hover:bg-primary/5 group-hover:text-primary transition-colors duration-200">
                                <i class="fas fa-users text-base"></i>
                            </div>
                        </div>
                    </div>
                    <div class="balance-card group relative overflow-hidden bg-white border border-slate-100 rounded-2xl p-6 shadow-sm transition-all duration-200 hover:shadow-md hover:border-slate-200">
                        <div class="absolute left-0 top-0 bottom-0 w-1 bg-emerald-500 transform scale-y-0 group-hover:scale-y-100 transition-transform duration-200"></div>
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1 font-sans">
                                    Active Accounts
                                </h3>
                                <p class="text-3xl font-extrabold text-slate-800 tracking-tight font-sans" id="stat-active">0</p>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center text-emerald-500 transition-colors duration-200">
                                <i class="fas fa-user-check text-base"></i>
                            </div>
                        </div>
                    </div>
                    <div class="balance-card group relative overflow-hidden bg-white border border-slate-100 rounded-2xl p-6 shadow-sm transition-all duration-200 hover:shadow-md hover:border-slate-200">
                        <div class="absolute left-0 top-0 bottom-0 w-1 bg-rose-500 transform scale-y-0 group-hover:scale-y-100 transition-transform duration-200"></div>
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1 font-sans">
                                    Deactivated Accounts
                                </h3>
                                <p class="text-3xl font-extrabold text-slate-800 tracking-tight font-sans" id="stat-inactive">0</p>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-rose-50 flex items-center justify-center text-rose-500 transition-colors duration-200">
                                <i class="fas fa-user-slash text-base"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div class="relative w-full max-w-md">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none z-10">
                        <i class="fas fa-search text-slate-400"></i>
                    </div>
                    <input 
                        type="text" 
                        id="search-bar" 
                        placeholder="Search by name or email..." 
                        class="w-full pl-11 pr-4 py-2.5 bg-white border border-slate-200 rounded-xl outline-none focus:border-primary focus:ring-2 focus:ring-primary/10 shadow-sm transition-all text-slate-800 placeholder-slate-400"
                    >
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="filter-pill active" data-status="all" id="filter-all">All <span class="filter-pill-count" id="filter-count-all">0</span></button>
                    <button type="button" class="filter-pill" data-status="active" id="filter-active">Active <span class="filter-pill-count" id="filter-count-active">0</span></button>
                    <button type="button" class="filter-pill" data-status="inactive" id="filter-inactive">Deactive <span class="filter-pill-count" id="filter-count-inactive">0</span></button>
                </div>
            </section>

            <section class="products-section">
                <div class="table-responsive">
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th>Admin Info</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Access Logs</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="admin-table-body">
                            </tbody>
                    </table>
                </div>
                <div id="table-empty-state" class="empty-state hidden">
                    <i class="fas fa-user-slash"></i>
                    <h3>No accounts found</h3>
                    <p>No administrative accounts match your current search or filter criteria.</p>
                </div>
            </section>
        </main>
    </div>

    <div id="toastContainer" aria-live="polite" aria-atomic="true"></div>

    <div id="confirm-modal" class="logout-modal-overlay">
        <div class="logout-modal-card max-w-md">
            <div id="confirm-modal-icon" class="logout-modal-icon"><i class="fas fa-circle-question"></i></div>
            <h2 id="confirm-modal-title" class="logout-modal-title">Confirm Action</h2>
            <p id="confirm-modal-message" class="text-muted mb-6">Are you sure you want to continue?</p>
            <div class="logout-modal-actions pt-4">
                <button type="button" onclick="closeConfirmModal()" class="btn-modal flex-1 btn-modal-cancel">Cancel</button>
                <button type="button" id="confirm-modal-action" onclick="confirmPendingAction()" class="btn-modal flex-1 btn-modal-confirm">Confirm</button>
            </div>
        </div>
    </div>

    <div id="admin-modal" class="logout-modal-overlay">
        <div class="logout-modal-card" style="max-width: 900px; width: min(900px, calc(100vw - 64px)); margin: 0 auto;">
            <div class="logout-modal-icon"><i class="fas fa-user-pen"></i></div>
            <h2 id="modal-title" class="logout-modal-title">Edit Account</h2>
            <p class="text-muted mb-6">Update the account credentials and permissions below.</p>
            
            <form id="admin-form" class="space-y-4 text-left" enctype="multipart/form-data">
                <input type="hidden" id="form-id" name="id">
                <input type="hidden" id="form-action" name="action" value="update_account">

                <div class="modal-section">
                    <div class="modal-section-title">Account Information</div>
                    <div class="modal-subtitle">Update username, personal details, contact number, and email.</div>
                    <div class="modal-fieldset">
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold uppercase text-slate-500">Username</label>
                            <input type="text" id="form-username" name="username" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg outline-none focus:border-primary">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold uppercase text-slate-500">First Name</label>
                            <input type="text" id="form-first-name" name="first_name" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg outline-none focus:border-primary">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold uppercase text-slate-500">Middle Name</label>
                            <input type="text" id="form-middle-name" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg outline-none focus:border-primary">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold uppercase text-slate-500">Last Name</label>
                            <input type="text" id="form-last-name" name="last_name" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg outline-none focus:border-primary">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold uppercase text-slate-500">Contact Number</label>
                            <input type="text" id="form-contact-number" name="contact_number" required oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg outline-none focus:border-primary">
                        </div>
                        <div class="space-y-1">
                            <label class="text-[10px] font-bold uppercase text-slate-500">Email Address</label>
                            <input type="email" id="form-email" name="email" required class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg outline-none focus:border-primary">
                        </div>
                    </div>
                </div>

                <div class="modal-section">
                    <div class="modal-section-title">Signature Preview</div>
                    <div class="modal-subtitle">View the currently saved signature image and replace it if needed.</div>
                    <div class="space-y-1">
                        <label class="text-[10px] font-bold uppercase text-slate-500">Signature Image</label>
                        <input type="file" id="form-signature-image" name="signature_image" accept="image/png, image/jpeg" class="w-full text-sm text-slate-600 file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:rounded-lg file:text-slate-700" />
                        <p class="text-[10px] text-slate-400">Optional. Upload a new signature image to replace the saved one.</p>
                        <div id="signature-preview-box" class="signature-preview-box hidden">
                            <div class="preview-header">
                                <span id="signature-preview-title">Current saved signature</span>
                                <span id="signature-preview-status">No file selected</span>
                            </div>
                            <div id="signature-preview-content" class="preview-content">
                                <span class="text-[11px] text-slate-400">No signature image is currently saved for this account.</span>
                            </div>
                            <div id="signature-preview-meta" class="preview-meta"></div>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-2">The current signature is shown above. Choose a file to preview a replacement.</p>
                    </div>
                </div>

                <div class="logout-modal-actions pt-4">
                    <button type="button" onclick="closeModal()" class="btn-modal flex-1 btn-modal-cancel">Cancel</button>
                    <button type="submit" class="btn-modal flex-1 btn-modal-confirm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <div id="password-modal" class="logout-modal-overlay">
        <div class="logout-modal-card">
            <div class="logout-modal-icon"><i class="fas fa-key"></i></div>
            <h2 class="logout-modal-title">Reset Password</h2>
            <p class="text-muted mb-6">Assign a new secure password for this administrative account.</p>
            
            <form id="password-form" class="space-y-4 text-left">
                <input type="hidden" id="pwd-form-id">
                
                <div class="space-y-1">
                    <label class="text-[10px] font-bold uppercase text-slate-500">New Password</label>
                    <input type="password" id="new-password" required minlength="8" placeholder="••••••••" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg outline-none focus:border-primary">
                </div>
                <div class="space-y-1">
                    <label class="text-[10px] font-bold uppercase text-slate-500">Confirm New Password</label>
                    <input type="password" id="confirm-password" required minlength="8" placeholder="••••••••" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg outline-none focus:border-primary">
                </div>

                <div class="logout-modal-actions pt-4">
                    <button type="button" onclick="closePasswordModal()" class="btn-modal flex-1 btn-modal-cancel">Cancel</button>
                    <button type="submit" class="btn-modal flex-1 btn-modal-confirm">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <div id="pin-modal" class="logout-modal-overlay">
        <div class="logout-modal-card">
            <div class="logout-modal-icon"><i class="fas fa-shield-alt"></i></div>
            <h2 class="logout-modal-title">Update Security PIN</h2>
            <p class="text-muted mb-6">Enter a new 4-digit security PIN for this admin account.</p>

            <form id="pin-form" class="space-y-4 text-left">
                <input type="hidden" id="pin-form-id">

                <div class="space-y-1">
                    <label class="text-[10px] font-bold uppercase text-slate-500">New PIN</label>
                    <input type="password" id="new-pin" maxlength="4" inputmode="numeric" pattern="\d{4}" required placeholder="XXXX" class="w-full px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg outline-none focus:border-primary">
                </div>

                <div class="logout-modal-actions pt-4">
                    <button type="button" onclick="closePinModal()" class="btn-modal flex-1 btn-modal-cancel">Cancel</button>
                    <button type="submit" class="btn-modal flex-1 btn-modal-confirm">Update PIN</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../../assets/js/superadmin.js"></script>
    <script>
        let allAdmins = [];
        let pendingAction = null;
        let pendingActionBtn = null;
        let pendingActionId = null;
        let pendingStatus = null;
        const apiPath = '../../actions/process_admin_cashier.php';
        let selectedStatusFilter = 'all';

        window.initializeSuperAdminPage(() => {
            showToast('Loading admin data...', 'info');
            loadAdmins();
            setupEvents();
        });

        async function loadAdmins() {
            try {
                const res = await fetch(`${apiPath}?action=list`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });

                if (!res.ok) {
                    const errorData = await res.json().catch(() => null);
                    throw new Error(errorData?.message || 'Failed to load admin data.');
                }

                const data = await res.json();
                if (Array.isArray(data)) {
                    allAdmins = data;
                    updateAdminCounts();
                    document.getElementById('stat-total').textContent = allAdmins.length;
                    applyFilters();
                } else if (data && data.success === false) {
                    console.error('Server Error:', data.message);
                    showToast(data.message || 'System error while loading admins.', 'error');
                    allAdmins = [];
                    renderTable([]);
                } else {
                    allAdmins = [];
                    renderTable([]);
                    showToast('No admin data was returned.', 'warning');
                }
            } catch (err) {
                console.error('Load Error:', err);
                showToast(err.message || 'Failed to connect to the account database.', 'error');
                allAdmins = [];
                renderTable([]);
            }
        }

        function applyFilters() {
            const query = document.getElementById('search-bar').value.toLowerCase();
            
            const filtered = allAdmins.filter(a => {
                const username = (a.username || '').toLowerCase();
                const fullName = (a.name || `${a.first_name} ${a.middle_name || ''} ${a.last_name}`).toLowerCase();
                const email = (a.email || '').toLowerCase();
                const contactNumber = (a.contact_number || '').toLowerCase();
                const matchQuery = fullName.includes(query) || username.includes(query) || email.includes(query) || contactNumber.includes(query);
                if (!matchQuery) return false;

                if (selectedStatusFilter === 'active') {
                    return normalizeAdmin(a).isActive;
                }
                if (selectedStatusFilter === 'inactive') {
                    return !normalizeAdmin(a).isActive;
                }
                return true;
            });
            
            renderTable(filtered);
        }

        function updateAdminCounts() {
            const normalized = allAdmins.map(normalizeAdmin);
            const activeCount = normalized.filter(a => a.isActive).length;
            const inactiveCount = normalized.filter(a => !a.isActive).length;

            document.getElementById('stat-active').textContent = activeCount;
            document.getElementById('stat-inactive').textContent = inactiveCount;
            document.getElementById('filter-count-all').textContent = allAdmins.length;
            document.getElementById('filter-count-active').textContent = activeCount;
            document.getElementById('filter-count-inactive').textContent = inactiveCount;
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function normalizeAdmin(admin) {
            const rawStatus = String(admin?.status ?? '').toLowerCase();
            const isActive = rawStatus === 'active' || rawStatus === '1' || rawStatus === 'enabled';
            const displayName = (admin?.name || `${admin?.first_name || ''} ${admin?.middle_name || ''} ${admin?.last_name || ''}`).trim();
            const lastLoginValue = admin?.last_login ? new Date(admin.last_login) : null;
            const lastLogin = lastLoginValue && !Number.isNaN(lastLoginValue.getTime()) ? lastLoginValue.toLocaleString() : 'Never';

            return {
                ...admin,
                isActive,
                displayName: displayName || 'Unnamed Admin',
                lastLogin
            };
        }

        function renderTable(data) {
            const tbody = document.getElementById('admin-table-body');
            const empty = document.getElementById('table-empty-state');

            if (!data.length) {
                tbody.innerHTML = '';
                empty.classList.remove('hidden');
                return;
            }
            empty.classList.add('hidden');

            const fragment = document.createDocumentFragment();
            data.map(normalizeAdmin).forEach(a => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-slate-50/50 transition-colors group';
                row.innerHTML = `
                    <td class="px-6 py-4">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-500 font-bold mr-3 border border-slate-200">
                                ${escapeHtml(a.displayName.charAt(0).toUpperCase() || 'U')}
                            </div>
                            <div>
                                <div class="font-bold text-slate-900 leading-tight">${escapeHtml(a.displayName)}</div>
                                <div class="text-[11px] text-slate-500 font-medium">${escapeHtml(a.email || 'No email provided')}</div>
                                ${a.username ? `<div class="text-[11px] text-slate-500 font-medium">Username: ${escapeHtml(a.username)}</div>` : ''}
                                ${a.contact_number ? `<div class="text-[11px] text-slate-500 font-medium">Contact: ${escapeHtml(a.contact_number)}</div>` : ''}
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="text-[10px] font-extrabold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-lg uppercase tracking-widest border border-slate-200/50">${escapeHtml(a.role || 'Staff')}</span>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold ${a.isActive ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'}">
                            ${a.isActive ? 'Active' : 'Suspended'}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <div class="text-[11px] text-slate-500 font-semibold tracking-tight">Last Login: ${escapeHtml(a.lastLogin)}</div>
                        <div class="text-[10px] text-slate-400 mt-0.5 uppercase tracking-wider">Attempts: ${escapeHtml(a.login_attempts ?? 0)}</div>
                    </td>
                    <td class="px-6 py-4">
                        <div class="flex items-center justify-end gap-1.5">
                            <button onclick="openStatusConfirmModal(this, ${a.id}, '${a.isActive ? 'inactive' : 'active'}')"
                                    class="w-8 h-8 rounded-lg flex items-center justify-center transition-all ${a.isActive ? 'text-orange-500 bg-orange-50 hover:bg-orange-500 hover:text-white' : 'text-emerald-500 bg-emerald-50 hover:bg-emerald-500 hover:text-white'}"
                                    title="${a.isActive ? 'Suspend Account' : 'Activate Account'}">
                                <i class="fas ${a.isActive ? 'fa-user-slash' : 'fa-user-check'} text-xs"></i>
                            </button>
                            <button onclick="openEditModal(${a.id})"
                                    class="w-8 h-8 rounded-lg flex items-center justify-center text-blue-500 bg-blue-50 hover:bg-blue-500 hover:text-white transition-all shadow-sm"
                                    title="Edit Profile">
                                <i class="fas fa-user-pen text-xs"></i>
                            </button>
                            <button onclick="openPasswordModal(${a.id})"
                                    class="w-8 h-8 rounded-lg flex items-center justify-center text-amber-500 bg-amber-50 hover:bg-amber-500 hover:text-white transition-all"
                                    title="Reset Password">
                                <i class="fas fa-key text-xs"></i>
                            </button>
                            <button onclick="openPinModal(${a.id})"
                                    class="w-8 h-8 rounded-lg flex items-center justify-center text-indigo-500 bg-indigo-50 hover:bg-indigo-500 hover:text-white transition-all"
                                    title="Update Security PIN">
                                <i class="fas fa-shield-alt text-xs"></i>
                            </button>
                            <button onclick="openDeleteAdminModal(${a.id}, this)"
                                    class="w-8 h-8 rounded-lg flex items-center justify-center text-rose-500 bg-rose-50 hover:bg-rose-500 hover:text-white transition-all"
                                    title="Delete Account">
                                <i class="fas fa-trash-can text-xs"></i>
                            </button>
                        </div>
                    </td>
                `;
                fragment.appendChild(row);
            });

            tbody.replaceChildren(fragment);
        }

        let currentSignatureObjectUrl = null;

        function setupEvents() {
            document.getElementById('search-bar').oninput = applyFilters;
            document.getElementById('filter-all').addEventListener('click', () => updateStatusFilter('all'));
            document.getElementById('filter-active').addEventListener('click', () => updateStatusFilter('active'));
            document.getElementById('filter-inactive').addEventListener('click', () => updateStatusFilter('inactive'));
            document.getElementById('admin-form').onsubmit = handleFormSubmit;
            document.getElementById('password-form').onsubmit = handlePasswordSubmit;
            document.getElementById('pin-form').onsubmit = handlePinSubmit;
            const signatureInput = document.getElementById('form-signature-image');
            if (signatureInput) {
                signatureInput.onchange = handleSignatureFileChange;
            }
            document.getElementById('confirm-modal').addEventListener('click', function (e) {
                if (e.target === this) closeConfirmModal();
            });
        }

        function updateStatusFilter(status) {
            selectedStatusFilter = status;
            document.querySelectorAll('.filter-pill').forEach(btn => {
                btn.classList.toggle('active', btn.dataset.status === status);
            });
            applyFilters();
        }

        function getSignatureImageUrl(imagePath) {
            if (!imagePath) return null;
            if (/^https?:\/\//i.test(imagePath)) return imagePath;
            const normalized = imagePath.replace(/^\/+/, '');
            return new URL(`../../${normalized}`, window.location.href).href;
        }

        function handleSignatureFileChange() {
            const file = this.files[0];
            const existingPath = this.dataset.existingSignature || null;
            if (file) {
                updateSignaturePreview({ file });
            } else {
                updateSignaturePreview({ existingPath });
            }
        }

        function updateSignaturePreview({ existingPath = null, file = null }) {
            const box = document.getElementById('signature-preview-box');
            const title = document.getElementById('signature-preview-title');
            const status = document.getElementById('signature-preview-status');
            const content = document.getElementById('signature-preview-content');
            const meta = document.getElementById('signature-preview-meta');

            if (!box || !title || !status || !content || !meta) return;
            box.classList.remove('hidden');

            if (currentSignatureObjectUrl) {
                URL.revokeObjectURL(currentSignatureObjectUrl);
                currentSignatureObjectUrl = null;
            }

            if (file) {
                const objectUrl = URL.createObjectURL(file);
                currentSignatureObjectUrl = objectUrl;
                title.textContent = 'New uploaded signature';
                status.textContent = 'Selected file';
                content.innerHTML = `<img src="${escapeHtml(objectUrl)}" alt="Selected signature preview" />`;
                meta.textContent = `${escapeHtml(file.name)} · ${(file.size / 1024).toFixed(1)} KB`;
                return;
            }

            if (existingPath) {
                const imageUrl = getSignatureImageUrl(existingPath);
                title.textContent = 'Current saved signature';
                status.textContent = 'Existing file';
                content.innerHTML = `<img src="${escapeHtml(imageUrl)}" alt="Current signature preview" />`;
                meta.textContent = 'The current signature image is saved on record. Upload a new file to replace it.';
                return;
            }

            title.textContent = 'No signature on file';
            status.textContent = 'Optional';
            content.innerHTML = '<span class="text-[11px] text-slate-400 text-center">No signature image is currently saved for this admin account.</span>';
            meta.textContent = 'Upload a PNG or JPG file to add or replace a signature image.';
        }

        function resetSignaturePreview() {
            const box = document.getElementById('signature-preview-box');
            if (box) {
                box.classList.add('hidden');
            }
            if (currentSignatureObjectUrl) {
                URL.revokeObjectURL(currentSignatureObjectUrl);
                currentSignatureObjectUrl = null;
            }
        }

        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            if (!container) return;

            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.setAttribute('role', 'alert');
            toast.textContent = message;
            container.appendChild(toast);

            requestAnimationFrame(() => toast.classList.add('show'));

            setTimeout(() => {
                toast.classList.remove('show');
                toast.classList.add('hide');
                setTimeout(() => toast.remove(), 300);
            }, 4000);
        }

        function openConfirmModal(title, message, iconClass = 'fa-circle-question', confirmText = 'Confirm', confirmType = 'btn-modal-confirm') {
            document.getElementById('confirm-modal-title').textContent = title;
            document.getElementById('confirm-modal-message').textContent = message;
            document.getElementById('confirm-modal-action').textContent = confirmText;
            document.getElementById('confirm-modal-action').className = `btn-modal flex-1 ${confirmType}`;
            document.getElementById('confirm-modal-icon').innerHTML = `<i class="fas ${iconClass}"></i>`;
            document.getElementById('confirm-modal').classList.add('active');
        }

        function closeConfirmModal() {
            document.getElementById('confirm-modal').classList.remove('active');
        }

        function openDeleteAdminModal(id, btn) {
            pendingAction = 'delete';
            pendingActionId = id;
            pendingActionBtn = btn;
            pendingStatus = null;
            openConfirmModal(
                'Confirm Admin Deletion',
                'Are you sure you want to delete this admin account? This action cannot be undone and will permanently remove the admin user.',
                'fa-triangle-exclamation',
                'Delete Admin',
                'btn-modal-confirm'
            );
        }

        function openStatusConfirmModal(btn, id, status) {
            pendingAction = 'status';
            pendingActionBtn = btn;
            pendingActionId = id;
            pendingStatus = status;
            const actionText = status === 'active' ? 'activate' : 'suspend';
            openConfirmModal(
                'Confirm Status Update',
                `Are you sure you want to ${actionText} this admin account?`,
                'fa-user-shield',
                `${actionText.charAt(0).toUpperCase() + actionText.slice(1)} Admin`,
                'btn-modal-confirm'
            );
        }

        function confirmPendingAction() {
            if (!pendingAction) return;
            closeConfirmModal();

            if (pendingAction === 'delete') {
                deleteAdmin(pendingActionBtn, pendingActionId);
            } else if (pendingAction === 'status' && pendingStatus) {
                updateStatus(pendingActionBtn, pendingActionId, pendingStatus);
            }
        }

        async function updateStatus(btn, id, status) {
            const actionText = status === 'active' ? 'activate' : 'suspend';
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i>';

            try {
                const res = await fetch(apiPath, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_status', admin_id: id, status })
                });
                const result = await res.json();
                if (result.success) {
                    showToast(`Admin ${actionText}d successfully.`, 'success');
                    loadAdmins();
                } else {
                    showToast(result.message || `Failed to ${actionText} admin.`, 'error');
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                }
            } catch (err) {
                console.error(err);
                showToast('Network error while updating admin status.', 'error');
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        }

        function openEditModal(id) {
            const a = allAdmins.find(x => x.id == id);
            if (!a) return;
            document.getElementById('form-id').value = a.id;
            document.getElementById('form-username').value = a.username || '';
            document.getElementById('form-first-name').value = a.first_name;
            document.getElementById('form-middle-name').value = a.middle_name || '';
            document.getElementById('form-last-name').value = a.last_name;
            document.getElementById('form-contact-number').value = a.contact_number || '';
            document.getElementById('form-email').value = a.email;
            const signatureInput = document.getElementById('form-signature-image');
            if (signatureInput) {
                signatureInput.value = '';
                signatureInput.dataset.existingSignature = a.signature_image || '';
            }
            updateSignaturePreview({ existingPath: a.signature_image || null });
            document.getElementById('admin-modal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('admin-modal').classList.remove('active');
            resetSignaturePreview();
        }

        function openPasswordModal(id) {
            document.getElementById('pwd-form-id').value = id;
            document.getElementById('password-form').reset();
            document.getElementById('password-modal').classList.add('active');
        }
        function closePasswordModal() {
            document.getElementById('password-modal').classList.remove('active');
        }

        function openPinModal(id) {
            document.getElementById('pin-form-id').value = id;
            document.getElementById('pin-form').reset();
            document.getElementById('pin-modal').classList.add('active');
        }
        function closePinModal() {
            document.getElementById('pin-modal').classList.remove('active');
        }

        async function handlePinSubmit(e) {
            e.preventDefault();
            const id = document.getElementById('pin-form-id').value;
            const new_pin = document.getElementById('new-pin').value;

            if (!/^\d{4}$/.test(new_pin)) {
                showToast('PIN must be exactly 4 digits.', 'warning');
                return;
            }

            try {
                const res = await fetch(apiPath, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_pin', id, pin: new_pin })
                });
                const result = await res.json();
                if (result.success) {
                    showToast('Security PIN updated successfully.', 'success');
                    closePinModal();
                } else {
                    showToast(result.message || 'PIN update failed.', 'error');
                }
            } catch (err) {
                showToast('Request error while updating PIN.', 'error');
            }
        }

        async function handlePasswordSubmit(e) {
            e.preventDefault();
            const id = document.getElementById('pwd-form-id').value;
            const new_password = document.getElementById('new-password').value;
            const confirm_password = document.getElementById('confirm-password').value;

            if (new_password !== confirm_password) {
                showToast('Passwords do not match. Please try again.', 'warning');
                return;
            }

            try {
                const res = await fetch(apiPath, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_password', id, new_password, confirm_password })
                });
                const result = await res.json();
                if (result.success) {
                    showToast('Password updated successfully.', 'success');
                    closePasswordModal();
                } else {
                    showToast(result.message || 'Password update failed.', 'error');
                }
            } catch (err) {
                showToast('Request error while updating password.', 'error');
            }
        }

        async function handleFormSubmit(e) {
            e.preventDefault();
            const formData = new FormData();
            formData.append('action', 'update_account');
            formData.append('id', document.getElementById('form-id').value);
            formData.append('username', document.getElementById('form-username').value);
            formData.append('first_name', document.getElementById('form-first-name').value);
            formData.append('middle_name', document.getElementById('form-middle-name').value);
            formData.append('last_name', document.getElementById('form-last-name').value);
            formData.append('contact_number', document.getElementById('form-contact-number').value);
            formData.append('email', document.getElementById('form-email').value);

            const signatureFile = document.getElementById('form-signature-image').files[0];
            if (signatureFile) {
                formData.append('signature_image', signatureFile);
            }

            try {
                const res = await fetch(apiPath, {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();
                if (result.success) {
                    closeModal();
                    showToast('Admin updated successfully.', 'success');
                    loadAdmins();
                } else {
                    showToast(result.message || 'Admin update failed.', 'error');
                }
            } catch (err) {
                showToast('Request error while updating admin.', 'error');
            }
        }

        async function deleteAdmin(btn, id) {
            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i>';

            try {
                const res = await fetch(apiPath, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', admin_id: id })
                });
                const result = await res.json();
                if (result.success) {
                    showToast('Admin deleted successfully.', 'success');
                    loadAdmins();
                } else {
                    showToast(result.message || 'Failed to delete admin.', 'error');
                    btn.disabled = false;
                    btn.innerHTML = originalHTML;
                }
            } catch (err) {
                console.error(err);
                showToast('Network error while deleting admin.', 'error');
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        }
    </script>
</body>
</html>