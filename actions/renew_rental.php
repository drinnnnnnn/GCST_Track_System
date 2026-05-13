<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
// Broaden roles to ensure students (or admins testing the portal) aren't blocked by 403
requireAuth(['student', 'admin', 'admincashier', 'superadmin']);
header('Content-Type: application/json');

require_once __DIR__ . '/../database/connection.php';
$conn = Database::getConnection();

$payload = json_decode(file_get_contents('php://input'), true) ?: [];
$rental_id = isset($payload['rental_id']) ? intval($payload['rental_id']) : 0;
$duration = isset($payload['duration']) ? intval($payload['duration']) : 0;
$unit = isset($payload['unit']) ? trim(strtolower($payload['unit'])) : 'days';

if (!$rental_id) {
    echo json_encode(['success' => false, 'error' => 'Missing rental_id']);
    exit;
}

$student_id = $_SESSION['student_id'] ?? null;
$role = $_SESSION['role'];

// Fetch current rental details to verify ownership and get the existing due date
$sql = "SELECT return_date, student_id FROM active_rentals WHERE rental_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $rental_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$stmt->close();

// Verify that the rental exists and belongs to the student (if logged in as a student)
if (!$row || ($role === 'student' && $row['student_id'] !== $student_id)) {
    echo json_encode(['success' => false, 'error' => 'Rental not found']);
    exit;
}

$target_student_id = $row['student_id'];
$currentDate = $row['return_date'] ?: date('Y-m-d');
$interval = ($unit === 'hours') ? " + $duration hours" : " + $duration days";
$newDueDate = date('Y-m-d H:i:s', strtotime($currentDate . $interval));

// Set status to pending_renewal so admin can approve it
$update = $conn->prepare("UPDATE active_rentals SET return_date = ?, status = 'pending_renewal' WHERE rental_id = ? AND student_id = ? LIMIT 1");
$update->bind_param('sis', $newDueDate, $rental_id, $target_student_id);
$success = $update->execute();

if ($success) {
    // Generate QR Code and Send Email
    try {
        require_once __DIR__ . '/email_helpers.php';
        require_once __DIR__ . '/qr_code_generator.php';

        // Fetch Student Details for the email
        $uStmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE student_id = ? LIMIT 1");
        $uStmt->bind_param('s', $target_student_id);
        $uStmt->execute();
        $user = $uStmt->get_result()->fetch_assoc();
        $uStmt->close();

        if ($user && filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
            $renewal_ref = 'RENEW-' . $rental_id;
            $tempDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'temp';
            if (!is_dir($tempDir)) @mkdir($tempDir, 0777, true);
            
            $tempQrPath = $tempDir . DIRECTORY_SEPARATOR . uniqid('qr_r_') . '.png';

            if (function_exists('generateLocalQrCode') && generateLocalQrCode($renewal_ref, $tempQrPath, 'H', 10, 4)) {
                $attachments = [['path' => $tempQrPath, 'name' => 'renewal_qr.png', 'cid' => 'renewal_qr']];
                
                $emailBody = "<div style='font-family: sans-serif; max-width: 600px; margin: auto; border: 1px solid #eee; padding: 20px; border-radius: 15px;'>
                    <h2 style='color: #4f46e5;'>Book Renewal Request</h2>
                    <p>Hi " . htmlspecialchars($user['first_name']) . ",</p>
                    <p>Your request to renew your book has been received. Please present the QR code below to the cashier to finalize the renewal.</p>
                    <div style='text-align: center; margin: 30px 0; padding: 20px; border: 2px dashed #4f46e5; border-radius: 12px; background: #f8fafc;'>
                        <div style='font-weight: bold; color: #4f46e5; margin-bottom: 10px;'>PRESENT TO CASHIER</div>
                        <img src='cid:renewal_qr' alt='Renewal QR' style='width: 200px; height: auto;' />
                        <div style='margin-top: 10px; font-size: 0.8rem; color: #64748b;'>Reference: $renewal_ref</div>
                    </div>
                    <p><strong>New Requested Due Date:</strong> " . date('M d, Y h:i A', strtotime($newDueDate)) . "</p>
                    <p>Thank you for using GCST Tracking System.</p>
                </div>";

                sendEmailWithLog($conn, $user['email'], 'Your Book Renewal QR Code', $emailBody, 'Renewal Confirmation', $attachments);
                
                if (file_exists($tempQrPath)) unlink($tempQrPath);
            }
        }
    } catch (Throwable $e) {
        error_log("Renewal Email/QR Error: " . $e->getMessage());
    }

    echo json_encode(['success' => true, 'new_due_date' => $newDueDate, 'renewal_ref' => 'RENEW-' . $rental_id]);
} else {
    echo json_encode(['success' => false, 'error' => 'Unable to renew rental']);
}

$update->close();
$conn->close();
