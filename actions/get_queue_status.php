<?php
/**
 * GCST Track System - Real-time Queue Status
 * Optimized with Server-Sent Events (SSE) to reduce polling overhead.
 */

// Prevent execution timeout for SSE stream
set_time_limit(0);

// Clear any previous output buffers to ensure clean stream delivery
if (ob_get_level()) ob_end_clean();

// Determine if request is for Server-Sent Events
$isSSE = (isset($_GET['stream']) && $_GET['stream'] == '1') || 
         (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/event-stream') !== false);

if ($isSSE) {
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no'); // Recommended for Nginx/Apache proxy environments
} else {
    header('Content-Type: application/json');
}

require_once __DIR__ . '/../database/connection.php';
$conn = Database::getConnection();

/**
 * Fetches current queue statistics and status
 */
function fetchQueueStatus($conn) {
    // Using queue_tickets for system-wide consistency
    $sql_serving = "SELECT queue_number FROM queue_tickets WHERE DATE(created_at) = CURDATE() AND status = 'serving' ORDER BY served_at DESC LIMIT 1";
    $result_serving = $conn->query($sql_serving);
    $now_serving = ($result_serving && $result_serving->num_rows > 0) ? $result_serving->fetch_assoc()['queue_number'] : null;

    $sql_next = "SELECT queue_number FROM queue_tickets WHERE DATE(created_at) = CURDATE() AND status = 'waiting' ORDER BY created_at ASC LIMIT 1";
    $result_next = $conn->query($sql_next);
    $next_queue = ($result_next && $result_next->num_rows > 0) ? $result_next->fetch_assoc()['queue_number'] : null;

    $counts = ['waiting' => 0, 'serving' => 0, 'completed' => 0, 'cancelled' => 0];
    $sql_counts = "SELECT status, COUNT(*) AS total FROM queue_tickets WHERE DATE(created_at) = CURDATE() GROUP BY status";
    $result_counts = $conn->query($sql_counts);
    if ($result_counts) {
        while ($row = $result_counts->fetch_assoc()) {
            $status = $row['status'];
            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) $row['total'];
            }
        }
    }

    return [
        'current_time' => date('H:i:s'),
        'now_serving' => $now_serving,
        'next_queue' => $next_queue,
        'counts' => $counts,
        'total_waiting' => $counts['waiting']
    ];
}

if (!$isSSE) {
    echo json_encode(fetchQueueStatus($conn));
    exit;
}

// SSE Persistent Connection Loop
$lastHash = "";
while (true) {
    if (connection_aborted()) break;

    $status = fetchQueueStatus($conn);
    // Efficient change detection: only send data if specific queue values change (ignore current_time)
    $hashData = $status; unset($hashData['current_time']);
    $currentHash = md5(serialize($hashData));

    if ($currentHash !== $lastHash) {
        echo "data: " . json_encode($status) . "\n\n";
        $lastHash = $currentHash;
    } else {
        echo ": heartbeat\n\n"; // Keep-alive heartbeat
    }

    flush();
    sleep(1); // Server-side internal poll interval
}
?>