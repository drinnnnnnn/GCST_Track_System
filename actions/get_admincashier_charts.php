<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admin', 'cashier', 'admincashier', 'superadmin']);
require_once 'admincashier_report_helpers.php';

$conn = connectAdminCashierDb();
if (!$conn) {
    echo json_encode([ 
        'sales_labels' => [],
        'sales_data' => [],
        'inventory_labels' => [],
        'inventory_data' => [],
        'products_labels' => [],
        'products_data' => []
    ]);
    exit;
}

$salesTable = findSalesTable($conn);
$salesTrend = getSalesTrend($conn, $salesTable, 7);
$inventoryDistribution = getInventoryDistribution($conn);
$topProducts = getTopProducts($conn, $salesTable, 5);

$response = [
    'sales_labels' => $salesTrend['labels'],
    'sales_data' => $salesTrend['data'],
    'inventory_labels' => $inventoryDistribution['labels'],
    'inventory_data' => $inventoryDistribution['data'],
    'products_labels' => array_map(fn($product) => $product['name'], $topProducts),
    'products_data' => array_map(fn($product) => $product['quantity'], $topProducts)
];

echo json_encode($response);
$conn->close();
