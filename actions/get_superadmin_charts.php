<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['superadmin']);

require_once __DIR__ . '/../database/connection.php';

try {
    $conn = Database::getConnection();

    // 1. Admin Activities (Last 7 days trend)
    $labels = [];
    $dataValues = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('M d', strtotime($date));
        
        $query = "SELECT COUNT(*) as count FROM email_notifications WHERE DATE(created_at) = '$date'";
        $res = $conn->query($query);
        $dataValues[] = (int)$res->fetch_assoc()['count'];
    }

    // 2. System Health Metrics
    $healthLabels = ['Database', 'Storage', 'Email Service', 'Network'];
    
    // Calculate Email Service Health based on recent success rate
    $emailHealthQuery = "SELECT 
        (COUNT(CASE WHEN status = 'sent' THEN 1 END) * 100 / COUNT(*)) as rate 
        FROM (SELECT status FROM email_notifications ORDER BY created_at DESC LIMIT 50) as last_emails";
    $healthRes = $conn->query($emailHealthQuery);
    $emailRate = ($healthRes && $healthRes->num_rows > 0) ? (int)$healthRes->fetch_assoc()['rate'] : 100;

    $healthData = [
        100, // Database
        85,  // Storage (mocked)
        $emailRate, // Email
        98   // Network (mocked)
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