<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
// Allow students to access this so they can see their own renewal status
requireAuth(['admincashier', 'superadmin', 'student', 'user']);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';

try {
    $role = $_SESSION['role'];
    $session_student_id = $_SESSION['student_id'] ?? null;

    // Base query to fetch pending renewals
    $sql = "SELECT ar.rental_id, ar.student_id, ar.product_id, ar.quantity, ar.return_date, ar.rental_date, ar.status,
                   u.first_name, u.last_name,
                   p.product_name
            FROM active_rentals ar
            LEFT JOIN users u ON ar.student_id = u.student_id
            LEFT JOIN products p ON ar.product_id = p.product_id
            WHERE ar.status = 'pending_renewal'";

    // If the user is a student, restrict them to their own records only
    if (in_array($role, ['student', 'user'])) {
        $sql .= " AND ar.student_id = ?";
    }
    $sql .= " ORDER BY ar.rental_date DESC";

    $stmt = $conn->prepare($sql);
    if (in_array($role, ['student', 'user'])) {
        $stmt->bind_param('s', $session_student_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $renewals = [];
    if (!$result) {
        throw new Exception("Failed to retrieve results: " . $conn->error);
    }

    while ($row = $result->fetch_assoc()) {
        $renewals[] = $row;
    }
    
    // Return a flat array of renewal objects as expected by the frontend
    echo json_encode($renewals);

} catch (Exception $e) {
    error_log("Error fetching pending renewals: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

$conn->close();