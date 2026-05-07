<?php
function connectAdminCashierDb() {
    require_once __DIR__ . '/../config/db_connect.php';
    $conn->set_charset('utf8mb4');
    return $conn;
}

function tableExists($conn, $table) {
    $tableName = $conn->real_escape_string($table);
    // Use SHOW TABLES to avoid 'Undefined property: mysqli::$database' warning
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}

function columnExists($conn, $table, $column) {
    if (!tableExists($conn, $table)) {
        return false;
    }
    $tableName = $conn->real_escape_string($table);
    $columnName = $conn->real_escape_string($column);
    $sql = "SHOW COLUMNS FROM `" . $tableName . "` LIKE '" . $columnName . "'";
    $result = $conn->query($sql);
    return $result && $result->num_rows > 0;
}

function sanitizeDate($date) {
    $dateTime = date_create($date);
    return $dateTime ? date_format($dateTime, 'Y-m-d') : null;
}

function findSalesTable($conn) {
    $candidates = ['sales', 'orders', 'transactions'];
    foreach ($candidates as $table) {
        if (tableExists($conn, $table)) {
            return $table;
        }
    }
    return null;
}

function getDateColumnName($conn, $table) {
    $known = [
        'sales' => 'sale_date',
        'orders' => 'order_date',
        'transactions' => 'transaction_date'
    ];
    if (isset($known[$table]) && columnExists($conn, $table, $known[$table])) {
        return $known[$table];
    }
    $fallbacks = ['sale_date', 'order_date', 'transaction_date', 'created_at', 'date'];
    foreach ($fallbacks as $column) {
        if (columnExists($conn, $table, $column)) {
            return $column;
        }
    }
    return null;
}

function getAmountColumnName($conn, $table) {
    $known = [
        'sales' => 'total_amount',
        'orders' => 'total_amount',
        'transactions' => 'amount'
    ];
    if (isset($known[$table]) && columnExists($conn, $table, $known[$table])) {
        return $known[$table];
    }
    $fallbacks = ['total_amount', 'amount', 'price', 'sale_price', 'total'];
    foreach ($fallbacks as $column) {
        if (columnExists($conn, $table, $column)) {
            return $column;
        }
    }
    return null;
}

function getQuantityColumnName($conn, $table) {
    $fallbacks = ['quantity', 'qty', 'units', 'count'];
    foreach ($fallbacks as $column) {
        if (columnExists($conn, $table, $column)) {
            return $column;
        }
    }
    return null;
}

function getProductJoinColumn($conn, $table) {
    if (columnExists($conn, $table, 'product_id')) {
        return 'product_id';
    }
    if (columnExists($conn, $table, 'product_name')) {
        return 'product_name';
    }
    return null;
}

function getPrimaryKeyColumn($conn, $table) {
    $fallbacks = ['id', 'sale_id', 'order_id', 'transaction_id'];
    foreach ($fallbacks as $column) {
        if (columnExists($conn, $table, $column)) {
            return $column;
        }
    }
    return null;
}

function buildDateFilter($conn, $table, $from, $to) {
    $dateColumn = getDateColumnName($conn, $table);
    if (!$dateColumn) {
        return '';
    }

    $clauses = [];
    if ($from) {
        $clauses[] = "DATE(`$dateColumn`) >= '" . $conn->real_escape_string($from) . "'";
    }
    if ($to) {
        $clauses[] = "DATE(`$dateColumn`) <= '" . $conn->real_escape_string($to) . "'";
    }
    return $clauses ? 'WHERE ' . implode(' AND ', $clauses) : '';
}

function getSalesSummary($conn, $salesTable, $from = null, $to = null) {
    if (!$salesTable) {
        return [
            'total_sales' => 0,
            'total_transactions' => 0,
            'books_sold' => 0
        ];
    }

    $amountColumn = getAmountColumnName($conn, $salesTable);
    $dateColumn = getDateColumnName($conn, $salesTable);
    $quantityColumn = getQuantityColumnName($conn, $salesTable);

    if (!$amountColumn || !$dateColumn) {
        return [
            'total_sales' => 0,
            'total_transactions' => 0,
            'books_sold' => 0
        ];
    }

    $dateFilter = buildDateFilter($conn, $salesTable, $from, $to);
    $quantityExpression = $quantityColumn ? "SUM(`$quantityColumn`)" : 'COUNT(*)';

    $sql = "SELECT COALESCE(SUM(`$amountColumn`), 0) AS total_sales, COUNT(*) AS total_transactions, COALESCE($quantityExpression, 0) AS books_sold FROM `$salesTable` $dateFilter";
    $result = $conn->query($sql);
    if (!$result) {
        return [
            'total_sales' => 0,
            'total_transactions' => 0,
            'books_sold' => 0
        ];
    }

    $row = $result->fetch_assoc();
    return [
        'total_sales' => (float)($row['total_sales'] ?? 0),
        'total_transactions' => (int)($row['total_transactions'] ?? 0),
        'books_sold' => (int)($row['books_sold'] ?? 0)
    ];
}

function getSalesToday($conn, $salesTable) {
    if (!$salesTable) {
        return 0;
    }

    $amountColumn = getAmountColumnName($conn, $salesTable);
    $dateColumn = getDateColumnName($conn, $salesTable);
    if (!$amountColumn || !$dateColumn) {
        return 0;
    }

    $sql = "SELECT COALESCE(SUM(`$amountColumn`), 0) AS total_sales_today FROM `$salesTable` WHERE DATE(`$dateColumn`) = CURDATE()";
    $result = $conn->query($sql);
    if (!$result) {
        return 0;
    }
    $row = $result->fetch_assoc();
    return (float)($row['total_sales_today'] ?? 0);
}

function getSalesTrend($conn, $salesTable, $days = 7, $from = null, $to = null) {
    $labels = [];
    $data = [];
    $today = new DateTime();
    $interval = new DateInterval('P1D');

    for ($i = $days - 1; $i >= 0; $i--) {
        $day = clone $today;
        $day->sub(new DateInterval('P' . $i . 'D'));
        $labels[] = $day->format('M d');
        $data[$day->format('Y-m-d')] = 0;
    }

    if (!$salesTable) {
        return ['labels' => $labels, 'data' => array_values($data)];
    }

    $amountColumn = getAmountColumnName($conn, $salesTable);
    $dateColumn = getDateColumnName($conn, $salesTable);
    if (!$amountColumn || !$dateColumn) {
        return ['labels' => $labels, 'data' => array_values($data)];
    }

    $dateFilter = buildDateFilter($conn, $salesTable, $from, $to);
    $sql = "SELECT DATE(`$dateColumn`) AS day, COALESCE(SUM(`$amountColumn`), 0) AS total FROM `$salesTable` $dateFilter GROUP BY day ORDER BY day ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $dayKey = $row['day'];
            if (isset($data[$dayKey])) {
                $data[$dayKey] = (float)$row['total'];
            }
        }
    }

    return ['labels' => $labels, 'data' => array_values($data)];
}

function getSalesPeriodDateRange($period) {
    $today = new DateTime('today');
    $from = null;
    $to = $today->format('Y-m-d');

    switch (strtolower($period)) {
        case 'today':
            $from = $today->format('Y-m-d');
            break;
        case 'week':
            $from = (clone $today)->sub(new DateInterval('P6D'))->format('Y-m-d');
            break;
        case 'month':
            $from = (clone $today)->modify('first day of this month')->format('Y-m-d');
            break;
        case 'year':
            $from = (clone $today)->modify('first day of January this year')->format('Y-m-d');
            break;
    }

    return [$from, $to];
}

function getSalesTrendForPeriod($conn, $salesTable, $period) {
    $labels = [];
    $data = [];
    if (!$salesTable) {
        return ['labels' => $labels, 'data' => $data];
    }

    $amountColumn = getAmountColumnName($conn, $salesTable);
    $dateColumn = getDateColumnName($conn, $salesTable);
    if (!$amountColumn || !$dateColumn) {
        return ['labels' => $labels, 'data' => $data];
    }

    list($from, $to) = getSalesPeriodDateRange($period);
    if (!$from || !$to) {
        return ['labels' => $labels, 'data' => $data];
    }

    if ($period === 'year') {
        $start = new DateTime($from);
        $end = new DateTime($to);
        while ($start <= $end) {
            $labels[] = $start->format('M');
            $data[$start->format('Y-m')] = 0;
            $start->modify('+1 month');
        }

        $dateFilter = buildDateFilter($conn, $salesTable, $from, $to);
        $sql = "SELECT DATE_FORMAT(`$dateColumn`, '%Y-%m') AS period_key, COALESCE(SUM(`$amountColumn`), 0) AS total FROM `$salesTable` $dateFilter GROUP BY period_key ORDER BY period_key ASC";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $key = $row['period_key'];
                if (array_key_exists($key, $data)) {
                    $data[$key] = (float)$row['total'];
                }
            }
        }

        return ['labels' => $labels, 'data' => array_values($data)];
    }

    $start = new DateTime($from);
    $end = new DateTime($to);
    $periodIterator = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));
    foreach ($periodIterator as $date) {
        $labels[] = $date->format('M d');
        $data[$date->format('Y-m-d')] = 0;
    }

    $dateFilter = buildDateFilter($conn, $salesTable, $from, $to);
    $sql = "SELECT DATE(`$dateColumn`) AS day, COALESCE(SUM(`$amountColumn`), 0) AS total FROM `$salesTable` $dateFilter GROUP BY day ORDER BY day ASC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $key = $row['day'];
            if (array_key_exists($key, $data)) {
                $data[$key] = (float)$row['total'];
            }
        }
    }

    return ['labels' => $labels, 'data' => array_values($data)];
}

function getSalesHistory($conn, $salesTable, $period = 'week', $limit = 50) {
    $history = [];
    if (!$salesTable) {
        return $history;
    }

    $dateColumn = getDateColumnName($conn, $salesTable);
    $amountColumn = getAmountColumnName($conn, $salesTable);
    $quantityColumn = getQuantityColumnName($conn, $salesTable);
    $productColumn = getProductJoinColumn($conn, $salesTable);
    $primaryKey = getPrimaryKeyColumn($conn, $salesTable);

    if (!$dateColumn) {
        return $history;
    }

    list($from, $to) = getSalesPeriodDateRange($period);
    $dateFilter = buildDateFilter($conn, $salesTable, $from, $to);

    $select = [];
    $join = '';
    if ($productColumn === 'product_id' && tableExists($conn, 'products')) {
        $select[] = "COALESCE(p.product_name, CONCAT('Product #', s.product_id)) AS item_name";
        $join = "LEFT JOIN products p ON p.product_id = s.product_id";
    } elseif ($productColumn === 'product_name') {
        $select[] = "s.product_name AS item_name";
    } elseif ($primaryKey) {
        $select[] = "CONCAT('Record #', COALESCE(s.`$primaryKey`, '')) AS item_name";
    } else {
        $select[] = "'Sale Record' AS item_name";
    }

    $select[] = $amountColumn ? "COALESCE(s.`$amountColumn`, 0) AS amount" : '0 AS amount';
    $select[] = $quantityColumn ? "COALESCE(s.`$quantityColumn`, 0) AS quantity" : '1 AS quantity';
    $select[] = "s.`$dateColumn` AS activity_date";

    if ($primaryKey) {
        $select[] = "s.`$primaryKey` AS transaction_id";
    } else {
        $select[] = "NULL AS transaction_id";
    }

    $sql = "SELECT " . implode(', ', $select) . " FROM `$salesTable` s $join $dateFilter ORDER BY activity_date DESC LIMIT $limit";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $history[] = [
                'transaction_id' => $row['transaction_id'] ?? null,
                'item' => $row['item_name'] ?? 'Sale',
                'quantity' => (int)($row['quantity'] ?? 0),
                'amount' => (float)($row['amount'] ?? 0),
                'date' => $row['activity_date'] ?? ''
            ];
        }
    }

    return $history;
}

function getInventoryDistribution($conn) {
    $labels = [];
    $data = [];
    if (!tableExists($conn, 'products')) {
        return ['labels' => $labels, 'data' => $data];
    }

    $sql = "SELECT product_category AS category, COALESCE(SUM(stock_count), 0) AS stock_total FROM products GROUP BY product_category ORDER BY stock_total DESC";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $labels[] = $row['category'] ?: 'Uncategorized';
            $data[] = (int)$row['stock_total'];
        }
    }

    return ['labels' => $labels, 'data' => $data];
}

function getTopProducts($conn, $salesTable = null, $limit = 5) {
    if ($salesTable && tableExists($conn, $salesTable)) {
        $productColumn = getProductJoinColumn($conn, $salesTable);
        $quantityColumn = getQuantityColumnName($conn, $salesTable);
        $quantityExpression = $quantityColumn ? "SUM(`$quantityColumn`)" : 'COUNT(*)';

        if ($productColumn === 'product_id' && tableExists($conn, 'products')) {
            $sql = "SELECT COALESCE(p.product_name, CONCAT('Product #', s.product_id)) AS name, $quantityExpression AS quantity FROM `$salesTable` s LEFT JOIN products p ON p.product_id = s.product_id GROUP BY s.product_id ORDER BY quantity DESC LIMIT $limit";
        } elseif ($productColumn === 'product_name') {
            $sql = "SELECT s.product_name AS name, $quantityExpression AS quantity FROM `$salesTable` s GROUP BY s.product_name ORDER BY quantity DESC LIMIT $limit";
        } else {
            $primaryKey = getPrimaryKeyColumn($conn, $salesTable);
            if ($primaryKey) {
                $sql = "SELECT CONCAT('Record #', COALESCE(s.`$primaryKey`, '')) AS name, $quantityExpression AS quantity FROM `$salesTable` s GROUP BY s.`$primaryKey` ORDER BY quantity DESC LIMIT $limit";
            } else {
                $sql = "SELECT 'Sales record' AS name, $quantityExpression AS quantity FROM `$salesTable` s LIMIT $limit";
            }
        }
        $result = $conn->query($sql);
        $topProducts = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $topProducts[] = [
                    'name' => $row['name'] ?? 'Product',
                    'quantity' => (int)($row['quantity'] ?? 0)
                ];
            }
        }
        if (!empty($topProducts)) {
            return $topProducts;
        }
    }

    if (tableExists($conn, 'products')) {
        $sql = "SELECT product_name AS name, COALESCE(stock_count, 0) AS quantity FROM products ORDER BY stock_count DESC LIMIT $limit";
        $result = $conn->query($sql);
        $topProducts = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $topProducts[] = [
                    'name' => $row['name'] ?? 'Product',
                    'quantity' => (int)($row['quantity'] ?? 0)
                ];
            }
        }
        return $topProducts;
    }

    return [];
}

function getRecentActivity($conn, $salesTable = null, $limit = 5) {
    $activities = [];
    if ($salesTable && tableExists($conn, $salesTable)) {
        $dateColumn = getDateColumnName($conn, $salesTable);
        $amountColumn = getAmountColumnName($conn, $salesTable);
        $productColumn = getProductJoinColumn($conn, $salesTable);
        $quantityColumn = getQuantityColumnName($conn, $salesTable);

        $selectParts = [];
        if ($productColumn === 'product_id' && tableExists($conn, 'products')) {
            $selectParts[] = "COALESCE(p.product_name, CONCAT('Product #', s.product_id)) AS item_name";
            $join = "LEFT JOIN products p ON p.product_id = s.product_id";
        } elseif ($productColumn === 'product_name') {
            $selectParts[] = "s.product_name AS item_name";
            $join = '';
        } else {
            $primaryKey = getPrimaryKeyColumn($conn, $salesTable);
            if ($primaryKey) {
                $selectParts[] = "CONCAT('Record #', COALESCE(s.`$primaryKey`, '')) AS item_name";
            } else {
                $selectParts[] = "'Sales record' AS item_name";
            }
            $join = '';
        }

        $selectParts[] = $amountColumn ? "COALESCE(s.`$amountColumn`, 0) AS amount" : "0 AS amount";
        $selectParts[] = $quantityColumn ? "COALESCE(s.`$quantityColumn`, 0) AS quantity" : "1 AS quantity";
        $selectParts[] = $dateColumn ? "s.`$dateColumn` AS activity_date" : "NOW() AS activity_date";

        $sql = "SELECT " . implode(', ', $selectParts) . " FROM `$salesTable` s $join ORDER BY activity_date DESC LIMIT $limit";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $activities[] = [
                    'type' => 'Sale',
                    'item' => $row['item_name'] ?? 'Product',
                    'amount' => (float)($row['amount'] ?? 0),
                    'quantity' => (int)($row['quantity'] ?? 0),
                    'date' => $row['activity_date'] ?? ''
                ];
            }
            return $activities;
        }
    }

    if (tableExists($conn, 'active_rentals')) {
        $sql = "SELECT product_id, student_id, date_student_received_book AS activity_date, return_date, status FROM active_rentals ORDER BY date_student_received_book DESC LIMIT $limit";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $item = 'Rental';
                if (tableExists($conn, 'products') && !empty($row['product_id'])) {
                    $productResult = $conn->query("SELECT product_name FROM products WHERE product_id = '" . $conn->real_escape_string($row['product_id']) . "' LIMIT 1");
                    if ($productResult && ($productRow = $productResult->fetch_assoc())) {
                        $item = $productRow['product_name'];
                    }
                }
                $activities[] = [
                    'type' => 'Rental',
                    'item' => $item,
                    'amount' => 0,
                    'quantity' => 1,
                    'date' => $row['activity_date'] ?? ''
                ];
            }
            return $activities;
        }
    }

    return [];
}

function getProductInventoryMetrics($conn) {
    if (!tableExists($conn, 'products')) {
        return [
            'total_inventory' => 0,
            'product_count' => 0,
            'inventory_value' => 0,
            'low_stock_count' => 0
        ];
    }

    $sql = "SELECT COALESCE(SUM(stock_count), 0) AS total_inventory, COUNT(*) AS product_count, COALESCE(SUM(stock_count * buy_price), 0) AS inventory_value, SUM(CASE WHEN stock_count < 10 THEN 1 ELSE 0 END) AS low_stock_count FROM products";
    $result = $conn->query($sql);
    $row = $result ? $result->fetch_assoc() : [];
    return [
        'total_inventory' => (int)($row['total_inventory'] ?? 0),
        'product_count' => (int)($row['product_count'] ?? 0),
        'inventory_value' => (float)($row['inventory_value'] ?? 0),
        'low_stock_count' => (int)($row['low_stock_count'] ?? 0)
    ];
}

function getActiveRentalMetrics($conn) {
    $activeRentals = 0;
    $overdueCount = 0;
    if (tableExists($conn, 'active_rentals')) {
        $activeRes = $conn->query("SELECT COUNT(*) AS total_active FROM active_rentals WHERE status != 'returned'");
        $activeRow = $activeRes ? $activeRes->fetch_assoc() : null;
        $activeRentals = (int)($activeRow['total_active'] ?? 0);

        $overdueRes = $conn->query("SELECT COUNT(*) AS total_overdue FROM active_rentals WHERE status = 'overdue' OR (return_date IS NOT NULL AND DATE(return_date) < CURDATE() AND status != 'returned')");
        $overdueRow = $overdueRes ? $overdueRes->fetch_assoc() : null;
        $overdueCount = (int)($overdueRow['total_overdue'] ?? 0);
    }
    return ['active_rentals' => $activeRentals, 'overdue_items' => $overdueCount];
}

function getQueueMetrics($conn) {
    $queueTables = ['queue', 'ticket_queue', 'queue_numbers'];
    foreach ($queueTables as $table) {
        if (tableExists($conn, $table)) {
            $sql = "SELECT COUNT(*) AS pending FROM `$table` WHERE status = 'waiting'";
            $result = $conn->query($sql);
            $row = $result ? $result->fetch_assoc() : null;
            return (int)($row['pending'] ?? 0);
        }
    }
    return 0;
}

function getRecentProducts($conn, $limit = 5) {
    if (!tableExists($conn, 'products')) {
        return [];
    }
    $sql = "SELECT product_name AS name, stock_count, buy_price, rent_price, product_category FROM products ORDER BY stock_count DESC LIMIT $limit";
    $result = $conn->query($sql);
    $top = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $top[] = [
                'name' => $row['name'] ?? 'Product',
                'stock' => (int)($row['stock_count'] ?? 0),
                'buy_price' => (float)($row['buy_price'] ?? 0),
                'rent_price' => (float)($row['rent_price'] ?? 0),
                'category' => $row['product_category'] ?? 'Uncategorized'
            ];
        }
    }
    return $top;
}

function getQueueCount($conn) {
    if (tableExists($conn, 'queue')) {
        $result = $conn->query("SELECT COUNT(*) AS total FROM queue WHERE status = 'waiting'");
        $row = $result ? $result->fetch_assoc() : null;
        return (int)($row['total'] ?? 0);
    }
    return 0;
}
