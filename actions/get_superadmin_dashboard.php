<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['superadmin']);

require_once __DIR__ . '/../database/connection.php';

try {
    $conn = Database::getConnection();
    
    // 1. Total Admin Accounts
    $adminQuery = "SELECT COUNT(*) as total FROM users WHERE role IN ('admin', 'admincashier', 'superadmin')";
    $adminRes = $conn->query($adminQuery);
    $totalAdmins = $adminRes->fetch_assoc()['total'];

    // 2. Active Sessions (Users active in last 15 minutes)
    // Assuming last_login is updated periodically or on access
    $activeQuery = "SELECT COUNT(*) as active FROM users WHERE last_login > NOW() - INTERVAL 15 MINUTE";
    $activeRes = $conn->query($activeQuery);
    $activeSessions = $activeRes->fetch_assoc()['active'];

    // 3. System Uptime (Mocked/Static or retrieved from system settings)
    $uptime = "99.98%";

    // 4. Pending Issues (Using failed email notifications as a metric)
    $issueQuery = "SELECT COUNT(*) as issues FROM email_notifications WHERE status = 'failed'";
    $issueRes = $conn->query($issueQuery);
    $pendingIssues = $issueRes->fetch_assoc()['issues'];

    echo json_encode([
        'success' => true,
        'data' => [
            'total_admin_accounts' => (int)$totalAdmins,
            'active_sessions' => (int)$activeSessions,
            'system_uptime' => $uptime,
            'pending_issues' => (int)$pendingIssues
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}