<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../database/connection.php';
$conn = Database::getConnection();

$current_time = date('H:i:s');

// Use today's queues only to avoid mixing days
$sql_serving = "SELECT queue_number FROM queue WHERE DATE(created_at) = CURDATE() AND status = 'serving' ORDER BY created_at ASC LIMIT 1";
$result_serving = $conn->query($sql_serving);
$now_serving = ($result_serving && $result_serving->num_rows > 0) ? $result_serving->fetch_assoc()['queue_number'] : null;

$sql_next = "SELECT queue_number FROM queue WHERE DATE(created_at) = CURDATE() AND status = 'waiting' ORDER BY created_at ASC LIMIT 1";
$result_next = $conn->query($sql_next);
$next_queue = ($result_next && $result_next->num_rows > 0) ? $result_next->fetch_assoc()['queue_number'] : null;

$counts = [
    'waiting' => 0,
    'serving' => 0,
    'completed' => 0,
    'cancelled' => 0
];
$sql_counts = "SELECT status, COUNT(*) AS total FROM queue WHERE DATE(created_at) = CURDATE() GROUP BY status";
$result_counts = $conn->query($sql_counts);
if ($result_counts) {
    while ($row = $result_counts->fetch_assoc()) {
        $status = $row['status'];
        if (array_key_exists($status, $counts)) {
            $counts[$status] = (int) $row['total'];
        }
    }
}

echo json_encode([
    'current_time' => $current_time,
    'now_serving' => $now_serving,
    'next_queue' => $next_queue,
    'counts' => $counts,
    'total_waiting' => $counts['waiting']
]);
?>