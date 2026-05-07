<?php
/**
 * Overdue Rental Reminder Script
 * This script identifies overdue book rentals, calculates the current fine (₱2/hour),
 * updates the database status, and sends a reminder email to the student.
 */

require_once __DIR__ . '/../config/db_connect.php';
require_once __DIR__ . '/email_helpers.php';

header('Content-Type: application/json');

try {
    // 1. Fetch all rentals that are past their due date and not yet returned
    $sql = "SELECT ar.rental_id, ar.student_id, ar.return_date, ar.quantity,
                   u.first_name, u.last_name, u.email,
                   p.product_name
            FROM active_rentals ar
            JOIN users u ON ar.student_id = u.student_id
            JOIN products p ON ar.product_id = p.product_id
            WHERE ar.status != 'returned' 
              AND ar.return_date < NOW()";

    $result = $conn->query($sql);
    if (!$result) throw new Exception("Database query failed: " . $conn->error);

    $remindersSent = 0;
    while ($row = $result->fetch_assoc()) {
        $dueTimestamp = strtotime($row['return_date']);
        $nowTimestamp = time();
        
        // Calculate fine: 2 pesos per hour overdue (rounded up)
        $hoursOverdue = ceil(($nowTimestamp - $dueTimestamp) / 3600);
        $penalty = $hoursOverdue * 2;

        // Update the rental status and current charge in the database
        $updateStmt = $conn->prepare("UPDATE active_rentals SET status = 'overdue', overdue_charge = ? WHERE rental_id = ?");
        $updateStmt->bind_param('di', $penalty, $row['rental_id']);
        $updateStmt->execute();
        $updateStmt->close();

        // Send Email Reminder
        if (filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $subject = "URGENT: Overdue Book Reminder - " . $row['product_name'];
            $emailBody = "
                <div style='font-family: sans-serif; max-width: 600px; margin: auto; border: 1px solid #eee; padding: 20px; border-radius: 15px;'>
                    <h2 style='color: #dc2626;'>Overdue Book Reminder</h2>
                    <p>Hi " . htmlspecialchars($row['first_name']) . ",</p>
                    <p>This is a reminder that the book <strong>" . htmlspecialchars($row['product_name']) . "</strong> was due on " . date('M d, Y h:i A', $dueTimestamp) . ".</p>
                    <div style='text-align: center; margin: 30px 0; padding: 25px; border: 1px solid #fee2e2; border-radius: 16px; background: #fef2f2;'>
                        <div style='font-size: 1rem; color: #991b1b; margin-bottom: 8px;'>Current Total Fine:</div>
                        <div style='font-size: 2.2rem; font-weight: 800; color: #b91c1c;'>₱" . number_format($penalty, 2) . "</div>
                        <div style='font-size: 0.85rem; color: #b91c1c; margin-top: 10px;'>Fines accumulate at a rate of ₱2.00 per hour</div>
                    </div>
                    <p>Please return the item to the library immediately to stop further accumulation of fines.</p>
                    <p>Thank you,<br>GCST Tracking System</p>
                </div>";

            sendEmailWithLog($conn, $row['email'], $subject, $emailBody, 'Overdue Reminder');
            $remindersSent++;
        }
    }

    echo json_encode(['success' => true, 'reminders_sent' => $remindersSent]);

} catch (Exception $e) {
    error_log("Overdue Reminder Script Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();