<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['superadmin']);

require_once __DIR__ . '/../database/connection.php';

try {
    $conn = Database::getConnection();

    // 1. Admin Activity Trend (Last 7 days)
    $labels = [];
    $dataValues = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('M d', strtotime($date));

        $query = "SELECT COUNT(*) as count FROM audit_logs WHERE action LIKE '%login%' AND DATE(created_at) = '$date'";
        $res = $conn->query($query);
        $dataValues[] = ($res && $row = $res->fetch_assoc()) ? (int)$row['count'] : 0;
    }

    // 2. System Health Metrics
    $healthLabels = ['Database', 'Backups', 'Email Service', 'Session Load'];

    $dbInfoQuery = "SELECT SUM(data_length + index_length) AS total_size FROM information_schema.tables WHERE table_schema = DATABASE()";
    $dbInfoRes = $conn->query($dbInfoQuery);
    $dbSize = ($dbInfoRes && $row = $dbInfoRes->fetch_assoc()) ? (int)$row['total_size'] : 0;
    $dbHealth = $dbSize > 0 ? max(20, min(100, 100 - round($dbSize / (1024 * 1024 * 1024) * 100))) : 100;

    $backupQuery = "SELECT COUNT(*) AS total, SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) AS success_count FROM system_backups";
    $backupRes = $conn->query($backupQuery);
    $backupHealth = 100;
    if ($backupRes && $row = $backupRes->fetch_assoc()) {
        $total = (int)$row['total'];
        $successCount = (int)$row['success_count'];
        $backupHealth = $total > 0 ? round(($successCount / $total) * 100) : 100;
    }

    $emailHealthQuery = "SELECT (COUNT(CASE WHEN status = 'sent' THEN 1 END) * 100 / COUNT(*)) AS rate FROM (SELECT status FROM email_notifications ORDER BY created_at DESC LIMIT 50) AS last_emails";
    $healthRes = $conn->query($emailHealthQuery);
    $emailRate = ($healthRes && $healthRes->num_rows > 0) ? (int)$healthRes->fetch_assoc()['rate'] : 100;

    $activeSessionsQuery = "SELECT COUNT(*) AS count FROM admincashier_acc WHERE status = 'active' AND last_login >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)";
    $sessionRes = $conn->query($activeSessionsQuery);
    $activeSessions = ($sessionRes && $row = $sessionRes->fetch_assoc()) ? (int)$row['count'] : 0;

    $totalAdminsQuery = "SELECT COUNT(*) AS count FROM admincashier_acc";
    $totalAdminsRes = $conn->query($totalAdminsQuery);
    $totalAdmins = ($totalAdminsRes && $row = $totalAdminsRes->fetch_assoc()) ? (int)$row['count'] : 1;
    $sessionLoad = min(100, $totalAdmins > 0 ? round(($activeSessions / $totalAdmins) * 100) : 0);

    $healthData = [
        $dbHealth,
        $backupHealth,
        $emailRate,
        $sessionLoad
    ];

    echo json_encode([
        'success' => true,
        'data' => [
            'activities_labels' => $labels,
            'activities_data' => $dataValues,
            'health_labels' => $healthLabels,
            'health_data' => $healthData
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}