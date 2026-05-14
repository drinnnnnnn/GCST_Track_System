<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security.php';
secureSessionStart();

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../database/models/QueueModel.php';

$data = json_decode(file_get_contents('php://input'), true);
$student_name = trim($data['student_name'] ?? 'Walk-in Student');
$purpose = trim($data['purpose'] ?? '');

$model = new QueueModel();
try {
    $res = $model->create(null, null, $student_name, $purpose);
    if ($res === false) {
        throw new Exception('Failed to create queue ticket');
    }

    $ticket = $model->getById((int)$res['id']);
    if (!$ticket) throw new Exception('Unable to fetch created ticket');

    echo json_encode(['success' => true, 'ticket' => $ticket]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>