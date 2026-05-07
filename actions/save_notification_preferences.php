<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/db_connect.php';

secureSessionStart();
requireAuth(['student', 'admincashier', 'superadmin']);
header('Content-Type: application/json');

$student_id = $_SESSION['student_id'] ?? null;
if (!$student_id) {
    jsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$preferences = [
    'email_notifications' => (bool)($payload['email_notifications'] ?? true),
    'rental_reminders' => (bool)($payload['rental_reminders'] ?? true),
    'payment_reminders' => (bool)($payload['payment_reminders'] ?? true),
    'queue_notifications' => (bool)($payload['queue_notifications'] ?? true),
    'system_updates' => (bool)($payload['system_updates'] ?? true),
];

$encodedPrefs = json_encode($preferences, JSON_UNESCAPED_UNICODE);

$stmt = $conn->prepare('INSERT INTO notification_preferences (student_id, preferences) VALUES (?, ?) ON DUPLICATE KEY UPDATE preferences = VALUES(preferences)');
$stmt->bind_param('ss', $student_id, $encodedPrefs);
$success = $stmt->execute();
$stmt->close();

if (!$success) {
    jsonResponse(['success' => false, 'message' => 'Unable to save preferences.'], 500);
}

logAudit($conn, 'student', $student_id, 'save_notification_preferences', 'Updated notification preferences.');
jsonResponse(['success' => true, 'message' => 'Notification preferences saved successfully.']);
