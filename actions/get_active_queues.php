<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security.php';
secureSessionStart();

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../database/models/QueueModel.php';

$model = new QueueModel();
try {
    $queues = $model->getActiveQueues();
    $counts = $model->getQueueCounts();
    echo json_encode(['success' => true, 'queues' => $queues, 'counts' => $counts]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'queues' => [], 'counts' => []]);
}
?>