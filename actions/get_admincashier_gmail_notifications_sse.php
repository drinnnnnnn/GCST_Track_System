<?php
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

require_once __DIR__ . '/../config/db_connect.php';

// Increase execution time for persistent connection
set_time_limit(0);

// Filter inputs from URL
$status = $_GET['status'] ?? '';
$email_type = $_GET['email_type'] ?? '';
$from_date = $_GET['from_date'] ?? '';
$to_date = $_GET['to_date'] ?? '';
$search = $_GET['search'] ?? '';

while (true) {
    if (connection_aborted()) break;

    // 1. Fetch Metrics
    $today = date('Y-m-d');
    $metrics = [];
    
    // Sent Today
    $stmt = $conn->prepare("SELECT COUNT(*) FROM email_notifications WHERE status = 'sent' AND DATE(created_at) = ?");
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $stmt->bind_result($metrics['sent_today']);
    $stmt->fetch();
    $stmt->close();
    
    // Failed Emails
    $res = $conn->query("SELECT COUNT(*) FROM email_notifications WHERE status = 'failed'");
    $metrics['failed_emails'] = $res->fetch_row()[0] ?? 0;
    
    // Pending Emails
    $res = $conn->query("SELECT COUNT(*) FROM email_notifications WHERE status = 'pending'");
    $metrics['pending_emails'] = $res->fetch_row()[0] ?? 0;
    
    // Total Sent
    $res = $conn->query("SELECT COUNT(*) FROM email_notifications WHERE status = 'sent'");
    $metrics['total_emails_sent'] = $res->fetch_row()[0] ?? 0;

    // 2. Fetch Logs with active filters
    $where = ["1=1"];
    if ($status) $where[] = "status = '" . $conn->real_escape_string($status) . "'";
    if ($email_type) $where[] = "notification_type = '" . $conn->real_escape_string($email_type) . "'";
    if ($from_date) $where[] = "created_at >= '" . $conn->real_escape_string($from_date) . " 00:00:00'";
    if ($to_date) $where[] = "created_at <= '" . $conn->real_escape_string($to_date) . " 23:59:59'";
    if ($search) {
        $s = $conn->real_escape_string($search);
        $where[] = "(recipient LIKE '%$s%' OR subject LIKE '%$s%' OR notification_type LIKE '%$s%')";
    }
    $whereClause = implode(" AND ", $where);
    
    $logRes = $conn->query("SELECT id, recipient, subject, notification_type, status, created_at FROM email_notifications WHERE $whereClause ORDER BY created_at DESC LIMIT 100");
    $logs = [];
    if ($logRes) {
        while ($row = $logRes->fetch_assoc()) {
            $logs[] = $row;
        }
    }

    // 3. Unique notification types for filter dropdown sync
    $typeRes = $conn->query("SELECT DISTINCT notification_type FROM email_notifications WHERE notification_type IS NOT NULL AND notification_type != ''");
    $types = [];
    if ($typeRes) {
        while ($tRow = $typeRes->fetch_assoc()) {
            $types[] = $tRow['notification_type'];
        }
    }

    echo "data: " . json_encode(['metrics' => $metrics, 'logs' => $logs, 'types' => $types]) . "\n\n";
    
    if (ob_get_level() > 0) ob_end_flush();
    flush();

    sleep(3); // Update interval
}