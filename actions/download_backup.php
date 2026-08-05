<?php
include 'functions.php';

try {
    if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
        throw new Exception('Invalid backup ID');
    }

    $backupId = intval($_GET['id']);
    $backup = getBackupRecord($backupId);
    if (!$backup || empty($backup['file_path'])) {
        throw new Exception('Backup record not found');
    }

    $relativePath = ltrim($backup['file_path'], '/\\');
    $backupPath = realpath(__DIR__ . '/../' . $relativePath);
    if (!$backupPath || !is_readable($backupPath)) {
        throw new Exception('Backup file not found or unreadable');
    }

    $fileName = basename($backupPath);
    header('Content-Description: File Transfer');
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($backupPath));
    readfile($backupPath);
    exit;
} catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}
