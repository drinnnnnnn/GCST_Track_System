<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admin', 'cashier', 'admincashier', 'superadmin']);
header('Content-Type: application/json');
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/../vendor/autoload.php';
$conn = Database::getConnection();

$hasStartDate = isset($_GET['start_date']);
$hasEndDate = isset($_GET['end_date']);
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($hasStartDate !== $hasEndDate) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Both start_date and end_date are required.']);
    $conn->close();
    exit;
}

if ($hasStartDate) {
    $startDate = $_GET['start_date'];
    $endDate = $_GET['end_date'];
    $isValidDate = static function ($value) {
        if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        $parts = explode('-', $value);
        return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
    };

    if (!$isValidDate($startDate) || !$isValidDate($endDate) || $startDate > $endDate) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'A valid date range is required.']);
        $conn->close();
        exit;
    }

    $dateCondition = "t.created_at >= '$startDate 00:00:00' AND t.created_at < DATE_ADD('$endDate', INTERVAL 1 DAY)";
} else {
    // Keep existing dashboard callers working while the Sales Management page uses date ranges.
    $period = !empty($_GET['period']) ? $_GET['period'] : 'today';
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
}

$excludeReceiptCategoryCondition = "(t.receipt_category IS NULL OR TRIM(t.receipt_category) = '')";

if (isset($_GET['cashier_options']) || isset($_GET['export_data'])) {
    if (!$hasStartDate || !$hasEndDate) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'A date range is required.']);
        $conn->close();
        exit;
    }

    try {
        if (isset($_GET['cashier_options'])) {
            $cashierQuery = "SELECT
                    cashier_options.id,
                    COALESCE(
                        NULLIF(TRIM(CONCAT_WS(' ', ac.first_name, ac.middle_name, ac.last_name)), ''),
                        MAX(NULLIF(TRIM(t.cashier_name), '')),
                        CONCAT('Cashier #', cashier_options.id)
                    ) AS name
                FROM (
                    SELECT id
                    FROM admincashier_acc
                    WHERE status = 'active' AND role IN ('cashier', 'admincashier')
                    UNION
                    SELECT t.cashier_id AS id
                    FROM cashier_transactions t
                    JOIN transaction_items ti ON ti.cashier_transaction_id = t.id AND ti.product_id > 0
                    WHERE $dateCondition
                      AND t.payment_status = 'paid'
                      AND $excludeReceiptCategoryCondition
                ) AS cashier_options
                LEFT JOIN admincashier_acc ac ON ac.id = cashier_options.id
                LEFT JOIN cashier_transactions t
                    ON t.cashier_id = cashier_options.id
                    AND $dateCondition
                    AND t.payment_status = 'paid'
                    AND $excludeReceiptCategoryCondition
                GROUP BY cashier_options.id, ac.first_name, ac.middle_name, ac.last_name
                ORDER BY name ASC";
            $cashierResult = $conn->query($cashierQuery);
            $cashiers = [];
            while ($cashierResult && $row = $cashierResult->fetch_assoc()) {
                $cashiers[] = ['id' => (int)$row['id'], 'name' => $row['name']];
            }

            echo json_encode(['success' => true, 'cashiers' => $cashiers]);
            $conn->close();
            exit;
        }

        $cashierId = null;
        if (isset($_GET['cashier_id']) && $_GET['cashier_id'] !== '') {
            $cashierId = filter_var($_GET['cashier_id'], FILTER_VALIDATE_INT);
            if ($cashierId === false || $cashierId < 1) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid cashier selection.']);
                $conn->close();
                exit;
            }
        }

        $category = isset($_GET['category']) && is_string($_GET['category'])
            ? strtolower($_GET['category'])
            : 'all';
        $categoryConditions = [
            'uniforms' => "LOWER(TRIM(COALESCE(p.product_category, ''))) IN ('uniform fabrics', 'uniform', 'uniforms', 'fabric uniform')",
            'books' => "LOWER(TRIM(COALESCE(p.product_category, ''))) = 'books'",
            'accessories' => "LOWER(TRIM(COALESCE(p.product_category, ''))) = 'accessories'"
        ];
        if ($category !== 'all' && !isset($categoryConditions[$category])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid product category.']);
            $conn->close();
            exit;
        }

        $cashierCondition = $cashierId === null ? '' : ' AND t.cashier_id = ' . (int)$cashierId;
        $categoryCondition = $category === 'all' ? '' : ' AND ' . $categoryConditions[$category];
        $exportQuery = "SELECT
                t.id,
                t.created_at AS date,
                t.transaction_number,
                t.cashier_id,
                COALESCE(
                    NULLIF(TRIM(t.cashier_name), ''),
                    NULLIF(TRIM(CONCAT_WS(' ', ac.first_name, ac.middle_name, ac.last_name)), ''),
                    CONCAT('Cashier #', t.cashier_id)
                ) AS cashier_name,
                COALESCE(u.student_id, t.guest_school_id) AS student_id,
                t.student_name,
                COALESCE(NULLIF(TRIM(ti.product_name), ''), 'Deleted Product') AS item,
                COALESCE(NULLIF(TRIM(p.product_category), ''), 'Uncategorized') AS category,
                ti.quantity,
                ti.unit_price,
                COALESCE(ti.total_item_amount, ti.quantity * ti.unit_price) AS amount,
                COALESCE(NULLIF(t.payment_method, ''), 'Cash') AS payment_method
            FROM cashier_transactions t
            JOIN transaction_items ti ON ti.cashier_transaction_id = t.id AND ti.product_id > 0
            LEFT JOIN products p ON p.product_id = ti.product_id
            LEFT JOIN users u ON t.user_id = u.id
            LEFT JOIN admincashier_acc ac ON ac.id = t.cashier_id
            WHERE $dateCondition
              AND t.payment_status = 'paid'
              AND $excludeReceiptCategoryCondition
              $cashierCondition
              $categoryCondition
            ORDER BY t.created_at DESC, t.id DESC";
        $exportResult = $conn->query($exportQuery);
        $exportRows = [];
        while ($exportResult && $row = $exportResult->fetch_assoc()) {
            $row['id'] = (int)$row['id'];
            $row['cashier_id'] = (int)$row['cashier_id'];
            $row['quantity'] = (int)$row['quantity'];
            $row['unit_price'] = (float)$row['unit_price'];
            $row['amount'] = (float)$row['amount'];
            $exportRows[] = $row;
        }

        if (isset($_GET['download_excel']) && $_GET['download_excel'] === '1') {
            if (!$exportRows) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'No sales match the selected export options.']);
                $conn->close();
                exit;
            }

            $signerId = $cashierId ?? (int)($_SESSION['admin_id'] ?? 0);
            $signerName = '';
            $signaturePath = '';

            if ($signerId > 0) {
                $signerStmt = $conn->prepare("SELECT
                        COALESCE(NULLIF(TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)), ''), username) AS name,
                        signature_image
                    FROM admincashier_acc
                    WHERE id = ?");
                if ($signerStmt) {
                    $signerStmt->bind_param('i', $signerId);
                    $signerStmt->execute();
                    $signerResult = $signerStmt->get_result();
                    if ($signer = $signerResult->fetch_assoc()) {
                        $signerName = trim((string)$signer['name']);
                        $signaturePath = trim((string)($signer['signature_image'] ?? ''));
                    }
                    $signerStmt->close();
                }
            }

            if ($signerName === '') {
                $signerName = $cashierId ? 'Cashier #' . $cashierId : 'Unknown Admin';
            }

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Sales Report');

            $title = 'GRANBY COLLEGES OF SCIENCE AND TECHNOLOGY - SALES PERFORMANCE REPORT';
            $sheet->setCellValue('A1', $title);
            $sheet->mergeCells('A1:K1');
            $sheet->setCellValue('A2', 'SYSTEM: GCST TRACK SYSTEM (POS MODULE)');
            $sheet->mergeCells('A2:K2');
            $sheet->setCellValue('A3', 'REPORT TYPE');
            $sheet->setCellValue('B3', 'Detailed Transaction Ledger');
            $sheet->setCellValue('A4', 'REPORTING PERIOD');
            $sheet->setCellValue('B4', $startDate . ' to ' . $endDate);
            $sheet->setCellValue('A5', 'CATEGORY');
            $sheet->setCellValue('B5', ucwords(str_replace('_', ' ', $category)));

            $headerRow = 7;
            $headers = [
                'Category', 'Reference ID', 'Cashier', 'Process Date',
                'Products & Specifications', 'Qty', 'Unit Price', 'Discount',
                'Total Amount', 'Payment Method'
            ];
            $sheet->fromArray($headers, null, 'A' . $headerRow);

            $row = $headerRow + 1;
            $grandTotal = 0.0;
            foreach ($exportRows as $item) {
                $quantity = (int)$item['quantity'];
                $amount = (float)$item['amount'];
                $unitPrice = (float)$item['unit_price'];
                $grandTotal += $amount;
                $sheet->fromArray([
                    $item['category'] ?: 'Uncategorized',
                    $item['transaction_number'] ?: '---',
                    $item['cashier_name'] ?: 'Unknown Cashier',
                    date('M j, Y', strtotime($item['date'])),
                    $item['item'] ?: 'N/A',
                    $quantity,
                    $unitPrice,
                    0,
                    $amount,
                    $item['payment_method'] ?: 'Cash'
                ], null, 'A' . $row);
                $row++;
            }

            $row++;
            $sheet->setCellValue('A' . $row, 'END OF REPORT');
            $sheet->mergeCells('A' . $row . ':K' . $row);
            $row++;
            $sheet->setCellValue('A' . $row, 'Grand Total Revenue');
            $sheet->setCellValue('B' . $row, $grandTotal);
            $row++;
            $sheet->setCellValue('A' . $row, 'Verified By');
            $sheet->setCellValue('B' . $row, $signerName);
            $row++;
            $sheet->setCellValue('A' . $row, 'Audit Notice');
            $sheet->setCellValue('B' . $row, 'This report is an official system-generated document. Unauthorized alteration is prohibited.');
            $sheet->mergeCells('B' . $row . ':K' . $row);
            $row += 2;
            $sheet->setCellValue('A' . $row, 'CASHIER APPROVAL');
            $sheet->mergeCells('A' . $row . ':K' . $row);
            $approvalRow = $row + 1;
            $signatureRow = $approvalRow;
            $sheet->setCellValue('A' . $signatureRow, 'Signature:');
            $signerNameRow = $signatureRow + 1;
            $sheet->setCellValue('A' . $signerNameRow, $cashierId ? 'Cashier Name:' : 'Exported By:');
            $sheet->setCellValue('B' . $signerNameRow, $signerName);

            $absoluteSignaturePath = '';
            if ($signaturePath !== '') {
                $absoluteSignaturePath = $signaturePath[0] === '/'
                    ? $_SERVER['DOCUMENT_ROOT'] . $signaturePath
                    : dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $signaturePath);
            }

            if ($absoluteSignaturePath !== '' && is_readable($absoluteSignaturePath)) {
                $sheet->getStyle('B' . $signatureRow)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('FFFFFF');
                $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
                $drawing->setName('Official Signature');
                $drawing->setDescription('Uploaded cashier signature');
                $drawing->setPath($absoluteSignaturePath);
                $drawing->setHeight(50);
                $drawing->setCoordinates('B' . $signatureRow);
                $drawing->setOffsetX(4);
                $drawing->setOffsetY(2);
                $drawing->setWorksheet($sheet);
                $sheet->getRowDimension($signatureRow)->setRowHeight(40);
            }

            $sheet->setCellValue('A' . ($signerNameRow + 1), 'Reporting Date Range');
            $sheet->setCellValue('B' . ($signerNameRow + 1), $startDate . ' to ' . $endDate);
            $sheet->setCellValue('A' . ($signerNameRow + 2), 'Date Signed');
            $sheet->setCellValue('B' . ($signerNameRow + 2), date('Y-m-d'));

            $sheet->getStyle('A1:K1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A1:K1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
            $sheet->getStyle('A2:K2')->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFF'));
            $sheet->getStyle('A2:K2')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');
            $sheet->getStyle('A' . $headerRow . ':K' . $headerRow)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A' . $headerRow . ':K' . $headerRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('4472C4');

            $approvalTitleRow = $approvalRow - 1;
            $sheet->getStyle('A' . $approvalTitleRow . ':K' . $approvalTitleRow)->getFont()->setBold(true);
            $sheet->getStyle('A' . $approvalTitleRow . ':K' . $approvalTitleRow)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('D9E1F2');
            $sheet->getStyle('A' . $headerRow . ':K' . ($signerNameRow + 2))->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');
            $sheet->getStyle('B' . $signatureRow . ':B' . ($signerNameRow + 2))->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

            foreach (range('A', 'K') as $column) {
                $sheet->getColumnDimension($column)->setAutoSize(true);
            }
            $sheet->getColumnDimension('E')->setWidth(34);
            $sheet->getColumnDimension('K')->setWidth(22);
            $sheet->freezePane('A' . ($headerRow + 1));
            $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);

            $fileName = 'Sales_Report_' . $startDate . '_to_' . $endDate . '_' . $category . '_' . date('Y-m-d') . '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Cache-Control: max-age=0');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            $spreadsheet->disconnectWorksheets();
            $conn->close();
            exit;
        }

        echo json_encode(['success' => true, 'export_data' => $exportRows]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    $conn->close();
    exit;
}

try {
    // 1. Summary Totals (Total Sales, Total Transactions, Avg Transaction Value)
    $summaryQuery = "SELECT
        IFNULL(COUNT(DISTINCT t.id), 0) as total_transactions,
        IFNULL(SUM(COALESCE(ti.total_item_amount, (ti.quantity * ti.unit_price), 0)), 0) as total_sales,
        IFNULL(SUM(COALESCE(ti.total_item_amount, (ti.quantity * ti.unit_price), 0)) / NULLIF(COUNT(DISTINCT t.id), 0), 0) as average_transaction_value
        FROM cashier_transactions t
        JOIN transaction_items ti ON ti.cashier_transaction_id = t.id
        WHERE $dateCondition
          AND t.payment_status = 'paid'
          AND $excludeReceiptCategoryCondition
          AND ti.product_id > 0";
    
    $summaryResult = $conn->query($summaryQuery);
    $summary = $summaryResult ? $summaryResult->fetch_assoc() : null;

    // 2. Total Items Sold
    $itemsQuery = "SELECT IFNULL(SUM(ti.quantity), 0) as total_items_sold
        FROM transaction_items ti
        JOIN cashier_transactions t ON ti.cashier_transaction_id = t.id
        WHERE $dateCondition
          AND t.payment_status = 'paid'
          AND $excludeReceiptCategoryCondition
          AND ti.product_id > 0";
    
    $itemsResult = $conn->query($itemsQuery);
    $itemsRow = $itemsResult ? $itemsResult->fetch_assoc() : null;

    // 3. Sales Trend Chart Data
    $chartQuery = "SELECT DATE(t.created_at) as sale_date, SUM(COALESCE(ti.total_item_amount, (ti.quantity * ti.unit_price), 0)) as daily_total
        FROM cashier_transactions t
        JOIN transaction_items ti ON ti.cashier_transaction_id = t.id
        WHERE $dateCondition
          AND t.payment_status = 'paid'
          AND $excludeReceiptCategoryCondition
          AND ti.product_id > 0
        GROUP BY DATE(t.created_at)
        ORDER BY sale_date ASC";
    
    $chartResult = $conn->query($chartQuery);
    $sales_labels = [];
    $sales_data = [];
    while ($chartResult && $row = $chartResult->fetch_assoc()) {
        $sales_labels[] = $row['sale_date'];
        $sales_data[] = (float)$row['daily_total'];
    }

    // 4. Top Selling Products
    $topQuery = "SELECT p.product_name as name, SUM(ti.quantity) as quantity
        FROM transaction_items ti
        JOIN products p ON ti.product_id = p.product_id
        JOIN cashier_transactions t ON ti.cashier_transaction_id = t.id
        WHERE $dateCondition
          AND t.payment_status = 'paid'
          AND $excludeReceiptCategoryCondition
          AND ti.product_id > 0
        GROUP BY ti.product_id
        ORDER BY quantity DESC
        LIMIT 5";
    
    $topResult = $conn->query($topQuery);
    $top_products = [];
    while ($topResult && $row = $topResult->fetch_assoc()) {
        $top_products[] = ['name' => $row['name'], 'quantity' => (int)$row['quantity']];
    }

    $normalizedProductCategory = "CASE
        WHEN LOWER(TRIM(COALESCE(p.product_category, ''))) IN ('uniform fabrics', 'uniform', 'uniforms', 'fabric uniform') THEN 'Uniforms'
        WHEN LOWER(TRIM(COALESCE(p.product_category, ''))) = 'books' THEN 'Books'
        WHEN LOWER(TRIM(COALESCE(p.product_category, ''))) = 'accessories' THEN 'Accessories'
        ELSE 'Uncategorized'
    END";

    // Category totals aggregate individual product lines, even when a transaction contains mixed categories.
    $categoryTotalsQuery = "SELECT
            $normalizedProductCategory AS category,
            IFNULL(SUM(COALESCE(ti.total_item_amount, ti.quantity * ti.unit_price)), 0) AS total_sales,
            IFNULL(SUM(ti.quantity), 0) AS total_items_sold,
            COUNT(DISTINCT t.id) AS total_transactions
        FROM cashier_transactions t
        JOIN transaction_items ti ON ti.cashier_transaction_id = t.id AND ti.product_id > 0
        LEFT JOIN products p ON p.product_id = ti.product_id
        WHERE $dateCondition
          AND t.payment_status = 'paid'
          AND $excludeReceiptCategoryCondition
        GROUP BY category
        ORDER BY FIELD(category, 'Uniforms', 'Books', 'Accessories', 'Uncategorized')";

    $categoryTotalsResult = $conn->query($categoryTotalsQuery);
    $category_totals = [];
    while ($categoryTotalsResult && $row = $categoryTotalsResult->fetch_assoc()) {
        $category_totals[] = [
            'category' => $row['category'],
            'total_sales' => (float)$row['total_sales'],
            'total_items_sold' => (int)$row['total_items_sold'],
            'total_transactions' => (int)$row['total_transactions']
        ];
    }

    $categoryHistoryQuery = "SELECT
            t.id,
            t.created_at AS date,
            t.transaction_number,
            COALESCE(u.student_id, t.guest_school_id) AS student_id,
            t.student_name,
            $normalizedProductCategory AS category,
            GROUP_CONCAT(IFNULL(NULLIF(ti.product_name, ''), 'Deleted Product') SEPARATOR ', ') AS item,
            SUM(ti.quantity) AS quantity,
            ROUND(SUM(COALESCE(ti.total_item_amount, ti.quantity * ti.unit_price)), 2) AS amount
        FROM cashier_transactions t
        JOIN transaction_items ti ON ti.cashier_transaction_id = t.id AND ti.product_id > 0
        LEFT JOIN products p ON p.product_id = ti.product_id
        LEFT JOIN users u ON t.user_id = u.id
        WHERE $dateCondition
          AND t.payment_status = 'paid'
          AND $excludeReceiptCategoryCondition
        GROUP BY t.id, t.created_at, t.transaction_number, u.student_id, t.guest_school_id, t.student_name, category
        ORDER BY t.created_at DESC
        LIMIT $limit";

    $categoryHistoryResult = $conn->query($categoryHistoryQuery);
    $category_history = [];
    while ($categoryHistoryResult && $row = $categoryHistoryResult->fetch_assoc()) {
        $category_history[] = [
            'id' => (int)$row['id'],
            'date' => $row['date'],
            'transaction_id' => $row['transaction_number'],
            'student_id' => $row['student_id'] ?? null,
            'student_name' => $row['student_name'] ?? null,
            'category' => $row['category'],
            'item' => $row['item'],
            'quantity' => (int)$row['quantity'],
            'amount' => (float)$row['amount'],
            'display_amount' => (float)$row['amount']
        ];
    }

    // 5. Sales History
    $historyQuery = "SELECT t.id, t.created_at as date, t.transaction_number,
        GROUP_CONCAT(IFNULL(NULLIF(ti.product_name, ''), 'Deleted Product') SEPARATOR ', ') as item,
        SUM(ti.quantity) as quantity,
        ROUND(SUM(COALESCE(ti.total_item_amount, (ti.quantity * ti.unit_price), 0)), 2) as amount,
        t.student_name,
        COALESCE(u.student_id, t.guest_school_id) as student_id,
        COALESCE(NULLIF(t.receipt_category, ''), 'Product Sale') as category,
        COALESCE(NULLIF(t.payment_method, ''), 'Cash') as payment_method
        FROM cashier_transactions t
        JOIN transaction_items ti ON t.id = ti.cashier_transaction_id AND ti.product_id > 0
        LEFT JOIN users u ON t.user_id = u.id
        WHERE $dateCondition
          AND t.payment_status = 'paid'
          AND $excludeReceiptCategoryCondition
        GROUP BY t.id, t.created_at, t.transaction_number, t.student_name, u.student_id, t.guest_school_id, t.receipt_category, t.payment_method
        ORDER BY t.created_at DESC
        LIMIT $limit";

    $historyResult = $conn->query($historyQuery);
    $history = [];
    while ($historyResult && $row = $historyResult->fetch_assoc()) {
        $amount = (float)($row['amount'] ?? 0);
        if ($amount <= 0) {
            $amount = (float)($row['item_total_amount'] ?? 0);
        }

        $history[] = [
            'id' => (int)$row['id'],
            'date' => $row['date'],
            'transaction_id' => $row['transaction_number'],
            'student_id' => $row['student_id'] ?? null,
            'student_name' => $row['student_name'] ?? null,
            'item' => $row['item'],
            'quantity' => (int)$row['quantity'],
            'amount' => $amount,
            'display_amount' => round($amount, 2)
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
        'category_totals' => $category_totals,
        'category_history' => $category_history,
        'history' => $history
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();