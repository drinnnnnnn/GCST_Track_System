<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admin', 'cashier', 'admincashier', 'superadmin']);
require_once 'admincashier_report_helpers.php';

$conn = connectAdminCashierDb();
if (!$conn) {
    echo json_encode(['error' => 'Connection failed']);
    exit;
}

$from = isset($_GET['from']) ? sanitizeDate($_GET['from']) : null;
$to = isset($_GET['to']) ? sanitizeDate($_GET['to']) : null;

$salesTable = findSalesTable($conn);
$salesSummary = getSalesSummary($conn, $salesTable, $from, $to);
$salesTrend = getSalesTrend($conn, $salesTable, 7, $from, $to);
$inventoryDistribution = getInventoryDistribution($conn);
$topProducts = getTopProducts($conn, $salesTable, 5);
$recentActivity = getRecentActivity($conn, $salesTable, 5);
$inventoryMetrics = getProductInventoryMetrics($conn);
$queueCount = getQueueCount($conn);

$response = [
    'total_sales_today' => getSalesToday($conn, $salesTable),
    'total_inventory' => $inventoryMetrics['total_inventory'],
    'total_products' => $inventoryMetrics['product_count'],
    'inventory_value' => $inventoryMetrics['inventory_value'],
    'pending_queue' => $queueCount,
    'total_transactions' => $salesSummary['total_transactions'],
    'books_sold' => $salesSummary['books_sold'],
    'total_sales' => $salesSummary['total_sales'],
    'selected_range' => ['from' => $from, 'to' => $to],
    'sales_labels' => $salesTrend['labels'],
    'sales_data' => $salesTrend['data'],
    'inventory_labels' => $inventoryDistribution['labels'],
    'inventory_data' => $inventoryDistribution['data'],
    'top_products' => $topProducts,
    'recent_activity' => $recentActivity
];

echo json_encode($response);
$conn->close();
