<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admincashier', 'superadmin']);
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/audit_helpers.php';

$adminId = $_SESSION['admin_id'] ?? null;

if (!$adminId) {
    echo json_encode(['success' => false, 'message' => 'Admin ID not found in session.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);

// Validate and sanitize input
$emailNotifications = filter_var($payload['email_notifications'] ?? false, FILTER_VALIDATE_BOOLEAN);
$rentalReminders = filter_var($payload['rental_reminders'] ?? false, FILTER_VALIDATE_BOOLEAN);
$paymentReminders = filter_var($payload['payment_reminders'] ?? false, FILTER_VALIDATE_BOOLEAN);
$queueNotifications = filter_var($payload['queue_notifications'] ?? false, FILTER_VALIDATE_BOOLEAN);
$systemUpdates = filter_var($payload['system_updates'] ?? false, FILTER_VALIDATE_BOOLEAN);

$preferences = [
    'email_notifications' => $emailNotifications,
    'rental_reminders' => $rentalReminders,
    'payment_reminders' => $paymentReminders,
    'queue_notifications' => $queueNotifications,
    'system_updates' => $systemUpdates,
];

$preferencesJson = json_encode($preferences);

try {
    $stmt = $conn->prepare("UPDATE admin_cashier SET notification_preferences = ? WHERE id = ?");
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }
    $stmt->bind_param('si', $preferencesJson, $adminId);
    $stmt->execute();
    $stmt->close();

    logAudit($conn, 'admincashier', $adminId, 'notification_preferences_update', 'Admin cashier notification preferences updated.');
    echo json_encode(['success' => true, 'message' => 'Notification preferences saved successfully.']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
$conn->close();
?>