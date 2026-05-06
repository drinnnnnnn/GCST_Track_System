<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admincashier', 'superadmin']);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/email_helpers.php'; // Include the new email helper

$payload = json_decode(file_get_contents('php://input'), true);
$rentalId = $payload['rental_id'] ?? null;
$action = $payload['action'] ?? null; // 'approve' or 'reject'
$reason = $payload['reason'] ?? null; // Only for rejection

if (!$rentalId || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request parameters.']);
    exit;
}

$conn->begin_transaction();

try {
    // Fetch rental details
    $stmt = $conn->prepare("SELECT ar.rental_id, ar.student_id, ar.product_id, ar.quantity, ar.return_date, ar.status,
                                   u.first_name, u.last_name, u.email,
                                   p.product_name
                            FROM active_rentals ar
                            JOIN users u ON ar.student_id = u.student_id
                            JOIN products p ON ar.product_id = p.product_id
                            WHERE ar.rental_id = ? AND ar.status = 'pending_renewal'");
    $stmt->bind_param('i', $rentalId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rental = $result->fetch_assoc();
    $stmt->close();

    if (!$rental) {
        throw new Exception('Rental or pending renewal request not found.');
    }

    $studentFullName = trim($rental['first_name'] . ' ' . $rental['last_name']);
    $studentEmail = $rental['email'];
    $productName = $rental['product_name'];
    $oldReturnDate = new DateTime($rental['return_date']);

    $emailSubject = '';
    $emailBody = '';
    $emailType = 'Renewal Notification';

    if ($action === 'approve') {
        // Assuming renewal logic extends the return_date by a default period (e.g., 7 days)
        // In a real system, the renewal request would specify the new duration.
        // For this example, we'll extend by 7 days.
        $newReturnDate = $oldReturnDate->modify('+7 days')->format('Y-m-d H:i:s');

        $updateStmt = $conn->prepare("UPDATE active_rentals SET return_date = ?, status = 'active' WHERE rental_id = ?");
        $updateStmt->bind_param('si', $newReturnDate, $rentalId);
        if (!$updateStmt->execute()) {
            throw new Exception('Failed to approve renewal: ' . $updateStmt->error);
        }
        $updateStmt->close();

        $emailSubject = "Your Rental Renewal for '{$productName}' Approved!";
        $emailBody = "<p>Hi {$studentFullName},</p>" .
                     "<p>Your request to renew the rental for '<strong>{$productName}</strong>' has been <strong>approved</strong>.</p>" .
                     "<p>Your new return date is: <strong>" . (new DateTime($newReturnDate))->format('F j, Y, g:i a') . "</strong>.</p>" .
                     "<p>Thank you for using GCST Tracking System.</p>";

    } elseif ($action === 'reject') {
        // Revert status to active (or overdue if it was overdue before pending_renewal)
        // For simplicity, we'll set it back to active. A more robust system would check original status.
        $updateStmt = $conn->prepare("UPDATE active_rentals SET status = 'active', rejection_reason = ? WHERE rental_id = ?");
        $updateStmt->bind_param('si', $reason, $rentalId);
        if (!$updateStmt->execute()) {
            throw new Exception('Failed to reject renewal: ' . $updateStmt->error);
        }
        $updateStmt->close();

        $emailSubject = "Your Rental Renewal for '{$productName}' Rejected";
        $emailBody = "<p>Hi {$studentFullName},</p>" .
                     "<p>Your request to renew the rental for '<strong>{$productName}</strong>' has been <strong>rejected</strong>.</p>" .
                     "<p><strong>Reason:</strong> " . htmlspecialchars($reason, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "</p>" .
                     "<p>Your original return date was: " . $oldReturnDate->format('F j, Y, g:i a') . ".</p>" .
                     "<p>Please return the item by the original due date to avoid overdue fees.</p>" .
                     "<p>Thank you for using GCST Tracking System.</p>";
    }

    $conn->commit();

    // Send email notification
    $emailStatus = 'skipped';
    if (filter_var($studentEmail, FILTER_VALIDATE_EMAIL)) {
        $sendResult = sendEmailWithLog($conn, $studentEmail, $emailSubject, $emailBody, $emailType);
        $emailStatus = $sendResult['status'];
    }

    echo json_encode(['success' => true, 'message' => "Renewal {$action}ed successfully. Email status: {$emailStatus}.", 'email_status' => $emailStatus]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Renewal action failed: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>