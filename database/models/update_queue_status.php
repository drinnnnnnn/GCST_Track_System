<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../database/models/QueueModel.php';

secureSessionStart();
requireAuth(['admincashier', 'superadmin']);

$id = $_POST['id'] ?? null;
$status = $_POST['status'] ?? null;

if (!$id || !$status) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

try {
    $model = new QueueModel();
    $success = $model->updateStatus($id, $status);
    echo json_encode(['success' => $success]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>