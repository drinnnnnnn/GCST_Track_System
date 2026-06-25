<?php
header('Content-Type: application/json');
include 'functions.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $backupId = isset($input['id']) ? intval($input['id']) : 0;

    if ($backupId <= 0) {
        throw new Exception('Invalid backup ID');
    }

    $success = restoreBackup($backupId);
    echo json_encode(['success' => $success, 'message' => $success ? 'Backup restored successfully' : 'Failed to restore backup']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>