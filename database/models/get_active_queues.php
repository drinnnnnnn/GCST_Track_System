<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../database/models/QueueModel.php';

secureSessionStart();
requireAuth(['admincashier', 'superadmin']);

try {
    $model = new QueueModel();
    $tickets = $model->getAllActive();
    
    $nowServing = null;
    $nextQueue = null;
    
    foreach($tickets as $t) {
        if ($t['status'] === 'serving' && !$nowServing) {
            $nowServing = $t['queue_number'];
        } elseif ($t['status'] === 'waiting' && !$nextQueue) {
            $nextQueue = $t['queue_number'];
        }
    }

    echo json_encode([
        'success' => true, 
        'tickets' => $tickets,
        'status' => [
            'nowServing' => $nowServing ?? 'None',
            'nextQueue' => $nextQueue ?? 'None'
        ]
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>