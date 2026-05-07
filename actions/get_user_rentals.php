<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['student', 'user']); // Only students/users can access their own rentals
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';

try {
    $session_student_id = $_SESSION['student_id'] ?? null;

    if (!$session_student_id) {
        throw new Exception('Student ID not found in session.');
    }

    // Fetch all rentals for the logged-in student
    // LEFT JOINs are used to ensure rentals are still shown even if product/user data is missing
    $sql = "SELECT ar.rental_id, ar.student_id, ar.product_id, ar.quantity, ar.rental_date, 
                   ar.return_date AS due_date, ar.status, ar.overdue_charge,
                   p.product_name AS name, p.product_image AS image, p.rent_price AS rental_fee
            FROM active_rentals ar
            LEFT JOIN users u ON ar.student_id = u.student_id
            LEFT JOIN products p ON ar.product_id = p.product_id
            WHERE ar.student_id = ?
            ORDER BY ar.rental_date DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $session_student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rentals = [];
    while ($row = $result->fetch_assoc()) {
        // Ensure numeric values are correctly typed for frontend calculations
        $row['quantity'] = (int) $row['quantity'];
        $row['overdue_charge'] = (float) $row['overdue_charge'];
        $row['rental_fee'] = (float) $row['rental_fee'];

        // The 'status' and 'overdue_charge' are already managed by the backend (e.g., send_overdue_reminders.php)
        // The frontend will use these values directly for display.

        $rentals[] = $row;
    }
    
    echo json_encode(['success' => true, 'rentals' => $rentals]);

} catch (Exception $e) {
    error_log("Error fetching user rentals: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();