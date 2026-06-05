<?php
/**
 * get_admincashier_dashboard.php
 * Aggregates real-time statistics for the Admin Cashier Dashboard.
 * Resolves "Undefined array key" warnings by ensuring robust data fallback.
 */

// Force JSON output to prevent HTML errors from corrupting the response
header('Content-Type: application/json');

// Buffer output to clear any accidental whitespace or BOM characters
ini_set('display_errors', '0');
ob_start();

require_once __DIR__ . '/security.php';
secureSessionStart();

// Access control for authorized personnel
requireAuth(['admin', 'admincashier', 'superadmin', 'cashier']);

try {
    require_once __DIR__ . '/../database/connection.php';
    $conn = Database::getConnection();

    if ($conn->connect_error) {
        throw new Exception('System Error: Unable to establish database connection.');
    }

    // Synchronize database session with system timezone for accurate "Today" metrics
    $conn->query("SET time_zone = '+08:00'");

    /**
     * Aggregate Dashboard Metrics in a single pass.
     * COALESCE ensures that if no records are found, 0 is returned instead of NULL.
     */
    $sql = "SELECT 
                (SELECT COALESCE(SUM(total_amount), 0) 
                 FROM cashier_transactions 
                 WHERE DATE(created_at) = CURDATE() 
                 AND payment_status = 'paid') as total_sales_today,
                
                (SELECT COALESCE(SUM(stock_count), 0) 
                 FROM products) as total_inventory,
                
                (SELECT COUNT(*) 
                 FROM cashier_transactions 
                 WHERE payment_status = 'pending' 
                 AND is_expired = 0) as pending_queue,
                 
                (SELECT COALESCE(SUM(ti.quantity), 0) 
                 FROM transaction_items ti
                 INNER JOIN products p ON ti.product_id = p.product_id
                 INNER JOIN cashier_transactions ct ON ti.cashier_transaction_id = ct.id
                 WHERE p.product_category = 'Books' 
                 AND ct.payment_status = 'paid') as books_sold,

                (SELECT COUNT(*) FROM cashier_transactions WHERE payment_status = 'paid') as total_transactions";

    $result = $conn->query($sql);
    $row = ($result && $result->num_rows > 0) ? $result->fetch_assoc() : [];

    /**
     * Final Data Assembly.
     * The null coalescing operator (??) ensures that if an index is missing 
     * from the query result, it defaults to 0, preventing "Undefined array key" warnings.
     */
    $response = [
        'success'           => true,
        'total_sales_today' => (float)($row['total_sales_today'] ?? 0),
        'total_inventory'   => (float)($row['total_inventory'] ?? 0),
        'pending_queue'     => (int)($row['pending_queue'] ?? 0),
        'books_sold'        => (int)($row['books_sold'] ?? 0),
        'total_transactions' => (int)($row['total_transactions'] ?? 0),
        'books_rented'      => 0 // Standardized placeholder for consistency
    ];

    if (ob_get_length()) ob_clean();
    echo json_encode($response);

} catch (Throwable $e) {
    if (ob_get_length()) ob_clean();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} finally {
    if (isset($conn)) $conn->close();
}
