<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admincashier', 'superadmin']);
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db_connect.php';

$period = $_GET['period'] ?? 'today';

// Define the date filter based on the requested period
switch ($period) {
    case 'week':
        $dateCondition = "t.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        break;
    case 'month':
        $dateCondition = "t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        break;
    case 'year':
        $dateCondition = "t.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
        break;
    case 'today':
    default:
        $dateCondition = "DATE(t.created_at) = CURDATE()";
        break;
}

try {
    // 1. Summary Totals (Total Sales, Total Transactions, Avg Transaction Value)
    $summaryQuery = "SELECT 
        COUNT(t.id) as total_transactions,
        SUM(t.total_amount) as total_sales,
        IFNULL(AVG(t.total_amount), 0) as average_transaction_value
        FROM transactions t
        WHERE $dateCondition AND t.payment_status = 'paid'";
    
    $summaryResult = $conn->query($summaryQuery);
    $summary = $summaryResult->fetch_assoc();

    // 2. Total Items Sold
    $itemsQuery = "SELECT SUM(ti.quantity) as total_items_sold 
        FROM transaction_items ti
        JOIN transactions t ON ti.transaction_id = t.id
        WHERE $dateCondition AND t.payment_status = 'paid'";
    
    $itemsResult = $conn->query($itemsQuery);
    $itemsRow = $itemsResult->fetch_assoc();

    // 3. Sales Trend Chart Data
    $chartQuery = "SELECT DATE(t.created_at) as sale_date, SUM(t.total_amount) as daily_total
        FROM transactions t
        WHERE $dateCondition AND t.payment_status = 'paid'
        GROUP BY DATE(t.created_at)
        ORDER BY sale_date ASC";
    
    $chartResult = $conn->query($chartQuery);
    $sales_labels = [];
    $sales_data = [];
    while ($row = $chartResult->fetch_assoc()) {
        $sales_labels[] = $row['sale_date'];
        $sales_data[] = (float)$row['daily_total'];
    }

    // 4. Top Selling Products
    $topQuery = "SELECT p.product_name as name, SUM(ti.quantity) as quantity
        FROM transaction_items ti
        JOIN products p ON ti.product_id = p.product_id
        JOIN transactions t ON ti.transaction_id = t.id
        WHERE $dateCondition AND t.payment_status = 'paid'
        GROUP BY ti.product_id
        ORDER BY quantity DESC
        LIMIT 5";
    
    $topResult = $conn->query($topQuery);
    $top_products = [];
    while ($row = $topResult->fetch_assoc()) {
        $top_products[] = ['name' => $row['name'], 'quantity' => (int)$row['quantity']];
    }

    // 5. Sales History
    $historyQuery = "SELECT t.created_at as date, t.transaction_number as transaction_id, 
        GROUP_CONCAT(p.product_name SEPARATOR ', ') as item, 
        SUM(ti.quantity) as quantity, t.total_amount as amount
        FROM transactions t
        JOIN transaction_items ti ON t.id = ti.transaction_id
        JOIN products p ON ti.product_id = p.product_id
        WHERE $dateCondition AND t.payment_status = 'paid'
        GROUP BY t.id
        ORDER BY t.created_at DESC
        LIMIT 20";

    $historyResult = $conn->query($historyQuery);
    $history = [];
    while ($row = $historyResult->fetch_assoc()) {
        $history[] = [
            'date' => $row['date'],
            'transaction_id' => $row['transaction_id'],
            'item' => $row['item'],
            'quantity' => (int)$row['quantity'],
            'amount' => (float)$row['amount']
        ];
    }

    echo json_encode([
        'success' => true,
        'total_sales' => (float)($summary['total_sales'] ?? 0),
        'total_transactions' => (int)($summary['total_transactions'] ?? 0),
        'average_transaction_value' => (float)($summary['average_transaction_value'] ?? 0),
        'total_items_sold' => (int)($itemsRow['total_items_sold'] ?? 0),
        'sales_labels' => $sales_labels,
        'sales_data' => $sales_data,
        'top_products' => $top_products,
        'history' => $history
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();