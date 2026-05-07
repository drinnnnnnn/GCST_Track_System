<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
// Allow students to access this so the system doesn't block legitimate "Cancel" actions,
// but restricted to prevent unauthorized approvals.
requireAuth(['admincashier', 'superadmin', 'student', 'user']);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';

try {
    $role = $_SESSION['role'];
    $session_student_id = $_SESSION['student_id'] ?? null;

    $data = json_decode(file_get_contents('php://input'), true);
    $rental_id = isset($data['rental_id']) ? intval($data['rental_id']) : null;
    $action = $data['action'] ?? null;
    $reason = trim($data['reason'] ?? '');

    if (!$rental_id || !in_array($action, ['approve', 'reject'])) {
        throw new Exception('Invalid request parameters.');
    }

    // Security: Students can only REJECT (Cancel) their own requests
    if (in_array($role, ['student', 'user'])) {
        if ($action === 'approve') {
            throw new Exception('Access denied: Only administrators can approve renewals.');
        }

        // Verify ownership
        $check_stmt = $conn->prepare("SELECT student_id FROM active_rentals WHERE rental_id = ? LIMIT 1");
        $check_stmt->bind_param('i', $rental_id);
        $check_stmt->execute();
        $res = $check_stmt->get_result();
        $rental = $res->fetch_assoc();
        $check_stmt->close();

        if (!$rental || $rental['student_id'] !== $session_student_id) {
            throw new Exception('Access denied: You are not authorized to modify this record.');
        }
        
        // Use a standard reason if the student is cancelling
        if (empty($reason)) $reason = "Cancelled by student.";
    }

    if ($action === 'approve') {
        // Reset status to active, accepting the new return_date set by the student
        $stmt = $conn->prepare("UPDATE active_rentals SET status = 'active', rejection_reason = NULL WHERE rental_id = ? LIMIT 1");
        $stmt->bind_param('i', $rental_id);
    } else {
        // Rejection: keep as active but log the rejection reason
        $stmt = $conn->prepare("UPDATE active_rentals SET status = 'active', rejection_reason = ? WHERE rental_id = ? LIMIT 1");
        $stmt->bind_param('si', $reason, $rental_id);
    }

    if (!$stmt->execute()) {
        throw new Exception("Database update failed: " . $stmt->error);
    }

    echo json_encode(['success' => true]);
    $stmt->close();

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();