﻿<?php
/**
 * Always return JSON, even on fatal errors.
 */
header('Content-Type: application/json');
ini_set('display_errors', '0');

// Buffer output to prevent accidental leaks/warnings from corrupting JSON
if (ob_get_level() === 0) ob_start();

/**
 * Shutdown handler to catch fatal errors that try-catch might miss.
 */
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (ob_get_length()) ob_clean();
        http_response_code(500);
        echo json_encode([
            'success' => false, 
            'error' => 'Fatal Server Error: ' . $error['message']
        ]);
    }
});

require_once __DIR__ . '/security.php';
secureSessionStart();
// Allow students and management roles to view rentals
requireAuth(['student', 'user', 'admin', 'admincashier', 'superadmin']); 
require_once __DIR__ . '/../database/connection.php';

try {
    $conn = Database::getConnection();
    if (!$conn) throw new Exception('Database connection failed.');

    // Default to the logged-in student's ID
    $target_student_id = $_SESSION['student_id'] ?? null;
    
    // Allow admins/cashiers to view rentals for a specific student if requested via GET
    if (isset($_GET['student_id']) && in_array($_SESSION['role'], ['admin', 'admincashier', 'superadmin'])) {
        $target_student_id = trim($_GET['student_id']);
    }

    if (!$target_student_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'User session expired or Student ID missing.']);
        exit;
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
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $conn->error);
    }

    $stmt->bind_param('s', $target_student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rentals = [];
    $now = new DateTime(); // Get current time for comparison

    while ($row = $result->fetch_assoc()) {
        // Ensure numeric values are correctly typed for frontend calculations
        $row['quantity'] = (int) $row['quantity'];
        $row['overdue_charge'] = (float) $row['overdue_charge'];
        $row['rental_fee'] = (float) $row['rental_fee'];

        // --- Automatic Overdue Penalty Calculation Logic ---
        // Only apply this logic if the rental is not already returned or pending renewal
        if ($row['status'] !== 'returned' && $row['status'] !== 'pending_renewal' && !empty($row['due_date'])) {
            $dueDate = new DateTime($row['due_date']);

            if ($dueDate < $now) {
                // Rental is overdue
                $row['status'] = 'overdue';

                // Calculate hours overdue (round up to the nearest hour)
                $interval = $now->diff($dueDate);
                $hoursOverdue = $interval->days * 24 + $interval->h;
                if ($interval->i > 0 || $interval->s > 0) { // If there are any minutes or seconds, round up to next hour
                    $hoursOverdue++;
                }

                // Apply penalty: ₱2 per hour
                $penalty = $hoursOverdue * 2;
                $row['overdue_charge'] = (float) $penalty;

                // Note: This script calculates penalties for display purposes only.
                // To persist these changes to the database, a dedicated background process
                // (like send_overdue_reminders.php) should be used to avoid write contention
                // on every read request.
            } else {
                // If it's not overdue, ensure overdue_charge is 0
                // This handles cases where a rental might have been overdue but then its due_date was extended
                // or the status was manually changed without resetting the charge.
                if ($row['overdue_charge'] > 0) {
                    $row['overdue_charge'] = 0.00;
                }
            }
        }
        // --- End Automatic Overdue Penalty Calculation Logic ---

        $rentals[] = $row;
    }
    
    // Ensure all data is UTF-8 encoded to prevent json_encode failures
    array_walk_recursive($rentals, function(&$item) {
        if (is_string($item)) {
            $item = mb_convert_encoding($item, 'UTF-8', 'UTF-8');
        }
    });

    $responseData = ['success' => true, 'rentals' => $rentals];
    $jsonOutput = json_encode($responseData);

    if ($jsonOutput === false) {
        throw new Exception("JSON encoding failed: " . json_last_error_msg());
    }

    if (ob_get_length()) ob_clean(); 
    echo $jsonOutput;

} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    error_log("Error fetching user rentals: " . $e->getMessage());
    http_response_code($e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}