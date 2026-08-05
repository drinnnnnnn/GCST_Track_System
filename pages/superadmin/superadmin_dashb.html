﻿<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Dashboard | GCST Super Admin</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link rel="stylesheet" href="../../assets/css/superadmin.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#4f46e5',
                        danger: '#dc2626',
                        success: '#10b981'
                    },
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'Outfit', 'sans-serif'],
                    }
                }
            }
        }
    </script>
</head>

<body class="antialiased font-sans">
    <!-- Shared Sidebar Component -->
    <div id="sidebar-container"></div>
    <div id="sidebar-overlay" onclick="toggleSidebar()"></div>

    <div class="content-wrapper">
        <main>
            
            <!-- Modern Greeting Section -->
            <section class="greeting-section animate-fade-in">
                <div class="greeting-content">
                    <h1 id="greeting-message">Welcome back, Admin</h1>
                    <p class="flex items-center mt-2">
                        <i class="far fa-calendar-alt mr-2 text-red-600"></i>
                        <span id="current-date-time">Loading date and time...</span>
                    </p>
                </div>
            </section>

            <!-- Overview Statistics Section -->
            <section class="balance-section">
                <div class="flex items-center justify-between mb-4">
                    <h2>System Overview</h2>
                </div>

                <div class="balance-cards">
                    <!-- Staff Accounts Card -->
                    <div class="balance-card group">
                        <div class="flex justify-between items-start mb-4">
                            <h3>Staff Accounts</h3>
                            <i class="fas fa-users-cog text-red-600 bg-red-50 p-3 rounded-xl transition-all group-hover:bg-red-600 group-hover:text-white"></i>
                        </div>
                        <p class="amount" id="totalAdminAccounts">0</p>
                        <span class="text-xs font-semibold text-emerald-500 mt-2 block"><i class="fas fa-arrow-up"></i> Registered</span>
                    </div>

                    <!-- Student Accounts Card -->
                    <div class="balance-card group">
                        <div class="flex justify-between items-start mb-4">
                            <h3>Student Accounts</h3>
                            <i class="fas fa-user-graduate text-sky-600 bg-sky-50 p-3 rounded-xl transition-all group-hover:bg-sky-600 group-hover:text-white"></i>
                        </div>
                        <p class="amount" id="totalStudentAccounts">0</p>
                        <span class="text-xs font-semibold text-sky-500 mt-2 block"><i class="fas fa-user-check"></i> Total Students</span>
                    </div>

                    <!-- Active Sessions Card -->
                    <div class="balance-card group">
                        <div class="flex justify-between items-start mb-4">
                            <h3>Active Sessions</h3>
                            <i class="fas fa-signal text-emerald-600 bg-emerald-50 p-3 rounded-xl transition-all group-hover:bg-emerald-600 group-hover:text-white"></i>
                        </div>
                        <p class="amount" id="activeSessions">0</p>
                        <span class="text-xs font-semibold text-emerald-500 mt-2 block"><i class="fas fa-circle text-[8px]"></i> Online Now</span>
                    </div>

                    <!-- Pending Students Card -->
                    <div class="balance-card group">
                        <div class="flex justify-between items-start mb-4">
                            <h3>Pending Students</h3>
                            <i class="fas fa-user-clock text-amber-600 bg-amber-50 p-3 rounded-xl transition-all group-hover:bg-amber-600 group-hover:text-white"></i>
                        </div>
                        <p class="amount danger" id="pendingStudentAccounts">0</p>
                        <span class="text-xs font-semibold text-amber-500 mt-2 block"><i class="fas fa-hourglass-half"></i> Awaiting Approval</span>
                    </div>

                    <!-- System Uptime Card -->
                    <div class="balance-card group">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h3>Server Uptime</h3>
                            </div>
                            <div class="flex items-center gap-2">
                                <span id="serverLoadBadge" class="status bg-sky-50 text-sky-600 px-2 py-0.5 rounded text-[10px] uppercase tracking-tighter font-bold">Loading</span>
                                <i class="fas fa-server text-sky-600 bg-sky-50 p-3 rounded-xl transition-all group-hover:bg-sky-600 group-hover:text-white"></i>
                            </div>
                        </div>
                        <p class="amount" id="systemUptime">99.9%</p>
                        <span class="text-xs font-semibold text-sky-500 mt-2 block" id="serverLoad">Server load: N/A</span>
                    </div>

                    <!-- Security Alerts Card -->
                    <div class="balance-card group" id="securityFlagCard">
                        <div class="flex justify-between items-start mb-4">
                            <h3>Security Flags</h3>
                            <i class="fas fa-shield-virus text-rose-600 bg-rose-50 p-3 rounded-xl transition-all group-hover:bg-rose-600 group-hover:text-white" id="securityFlagIcon"></i>
                        </div>
                        <p class="amount danger" id="pendingIssues">0</p>
                        <span class="text-xs font-semibold text-slate-400 mt-2 block" id="securityStatus">Requires Attention</span>
                    </div>
                </div>
            </section>

            <!-- Analytics Section -->
            <section class="activity-section">
                <div class="charts-grid">
                    <div class="chart-card">
                        <div class="flex items-center justify-between mb-6 border-b border-slate-100 pb-4">
                            <h3>Admin Activity Trends</h3>
                            <span class="status bg-red-50 text-red-600 px-2 py-0.5 rounded text-[10px] uppercase tracking-tighter font-bold">Live Data</span>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="adminActivitiesChart"></canvas>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div class="flex items-center justify-between mb-6 border-b border-slate-100 pb-4">
                            <h3>System Performance</h3>
                            <span class="status bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded text-[10px] uppercase tracking-tighter font-bold">Stable</span>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="systemHealthChart"></canvas>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <!-- Application Logic -->
    <script src="../../assets/js/superadmin.js"></script>
    <script>
        // Initialize the page once DOM is ready using the core JS module
        window.initializeSuperAdminPage(userData => {
            loadDashboardStats();
            initDashboardCharts();
        });

        /**
         * Fetch real-time statistics from the backend
         */
        async function loadDashboardStats() {
            try {
                const metricsRes = await fetch('../../actions/get_system_metrics.php');
                const metrics = await metricsRes.json();

                if (!metrics.success) {
                    throw new Error(metrics.error || 'Failed to load dashboard metrics');
                }

                document.getElementById('totalAdminAccounts').textContent = metrics.total_admin_accounts ?? '0';
                document.getElementById('totalStudentAccounts').textContent = metrics.total_student_accounts ?? '0';
                document.getElementById('pendingStudentAccounts').textContent = metrics.pending_student_accounts ?? '0';
                document.getElementById('activeSessions').textContent = metrics.active_connections ?? '0';
                document.getElementById('systemUptime').textContent = metrics.system_uptime || 'N/A';
                document.getElementById('pendingIssues').textContent = metrics.pending_issues ?? '0';

                const serverLoadText = metrics.server_load || 'N/A';
                document.getElementById('serverLoad').textContent = `Server load: ${serverLoadText}`;

                const loadValue = parseInt(serverLoadText, 10);
                const serverLoadBadge = document.getElementById('serverLoadBadge');
                let badgeText = 'Unknown';
                let badgeClasses = 'status px-2 py-0.5 rounded text-[10px] uppercase tracking-tighter font-bold bg-slate-100 text-slate-600';

                if (!isNaN(loadValue)) {
                    if (loadValue < 50) {
                        badgeText = 'Normal';
                        badgeClasses = 'status bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded text-[10px] uppercase tracking-tighter font-bold';
                    } else if (loadValue < 80) {
                        badgeText = 'Moderate';
                        badgeClasses = 'status bg-amber-50 text-amber-600 px-2 py-0.5 rounded text-[10px] uppercase tracking-tighter font-bold';
                    } else {
                        badgeText = 'High';
                        badgeClasses = 'status bg-rose-50 text-rose-600 px-2 py-0.5 rounded text-[10px] uppercase tracking-tighter font-bold';
                    }
                } else if (serverLoadText === 'N/A') {
                    badgeText = 'Unavailable';
                }

                if (serverLoadBadge) {
                    serverLoadBadge.textContent = badgeText;
                    serverLoadBadge.className = badgeClasses;
                }

                const statusLabel = document.getElementById('securityStatus');
                const pendingCount = Number(metrics.pending_issues || 0);
                const pendingElement = document.getElementById('pendingIssues');
                const cardElement = document.getElementById('securityFlagCard');
                const iconElement = document.getElementById('securityFlagIcon');

                if (statusLabel) {
                    statusLabel.textContent = pendingCount > 0 ? 'Issues detected' : 'No active flags';
                    statusLabel.classList.toggle('text-rose-500', pendingCount > 0);
                    statusLabel.classList.toggle('text-emerald-500', pendingCount === 0);
                    statusLabel.classList.toggle('text-slate-400', pendingCount !== 0);
                }

                if (pendingElement) {
                    pendingElement.classList.toggle('danger', pendingCount > 0);
                    pendingElement.classList.toggle('success', pendingCount === 0);
                }

                if (cardElement) {
                    cardElement.classList.toggle('border-rose-200', pendingCount > 0);
                    cardElement.classList.toggle('border-emerald-200', pendingCount === 0);
                }

                if (iconElement) {
                    iconElement.classList.toggle('text-rose-600', pendingCount > 0);
                    iconElement.classList.toggle('bg-rose-50', pendingCount > 0);
                    iconElement.classList.toggle('text-emerald-600', pendingCount === 0);
                    iconElement.classList.toggle('bg-emerald-50', pendingCount === 0);
                }
            } catch (error) {
                console.warn('Could not load latest dashboard stats.', error);
            }
        }

        /**
         * Initialize Chart.js with real backend data
         */
        async function initDashboardCharts() {
            const activityData = {
                labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                values: [12, 19, 15, 25, 22, 10, 8]
            };
            const healthData = {
                labels: ['Database', 'Storage', 'Email', 'Network'],
                values: [85, 85, 90, 95]
            };

            try {
                const res = await fetch('../../actions/get_superadmin_charts.php');
                const apiData = await res.json();
                if (res.ok && apiData.success && apiData.data) {
                    activityData.labels = apiData.data.activities_labels || activityData.labels;
                    activityData.values = apiData.data.activities_data || activityData.values;
                    healthData.labels = apiData.data.health_labels || healthData.labels;
                    healthData.values = apiData.data.health_data || healthData.values;
                }
            } catch (error) {
                console.warn('Could not load chart metrics, using fallback data.', error);
            }

            const ctxActivity = document.getElementById('adminActivitiesChart').getContext('2d');
            new Chart(ctxActivity, {
                type: 'line',
                data: {
                    labels: activityData.labels,
                    datasets: [{
                        label: 'Admin activity',
                        data: activityData.values,
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.12)',
                        borderWidth: 3,
                        tension: 0.35,
                        fill: true,
                        pointRadius: 4,
                        pointBackgroundColor: '#4f46e5'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { borderDash: [5, 5], color: '#f1f5f9' } },
                        x: { grid: { display: false } }
                    }
                }
            });

            const ctxHealth = document.getElementById('systemHealthChart').getContext('2d');
            new Chart(ctxHealth, {
                type: 'doughnut',
                data: {
                    labels: healthData.labels,
                    datasets: [{
                        data: healthData.values,
                        backgroundColor: ['#6366f1', '#10b981', '#f59e0b', '#8b5cf6'],
                        borderWidth: 0,
                        hoverOffset: 12
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '68%',
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { weight: '600' } } }
                    }
                }
            });
        }
    </script>
</body>
</html>