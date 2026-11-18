<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../config/db_connect.php';

secureSessionStart();
requireAuth(['student']);
header('Content-Type: application/json');

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}
$student_id = $_SESSION['student_id'] ?? null;

if (!$student_id || !is_array($payload)) {
    jsonResponse(['success' => false, 'message' => 'Invalid request data.'], 400);
}

$items = [];
if (isset($payload['items']) && is_array($payload['items'])) {
    $items = $payload['items'];
} elseif (isset($payload['product_id'])) {
    $items[] = ['product_id' => $payload['product_id']];
}

if (count($items) === 0) {
    jsonResponse(['success' => false, 'message' => 'No products were selected.'], 400);
}

if (isset($payload['type']) && strtolower(trim((string)$payload['type'])) === 'rent') {
    jsonResponse(['success' => false, 'message' => 'Rental requests are no longer supported.'], 400);
}
foreach ($items as $item) {
    if (is_array($item) && strtolower(trim((string)($item['type'] ?? ''))) === 'rent') {
        jsonResponse(['success' => false, 'message' => 'Rental requests are no longer supported.'], 400);
    }
}

$insertStmt = $conn->prepare("INSERT INTO requests (student_id, product_id, status) VALUES (?, ?, 'pending')");
$notifStmt = $conn->prepare('INSERT INTO notifications (student_id, product_name, status, is_read, notified_at) VALUES (?, ?, ?, 0, NOW())');

$insertedCount = 0;
foreach ($items as $item) {
    $product_id = filter_var($item['product_id'] ?? 0, FILTER_VALIDATE_INT);
    if ($product_id === false || $product_id <= 0) {
        continue;
    }

    $productName = null;
    $lookupStmt = $conn->prepare('SELECT product_name FROM products WHERE product_id = ? LIMIT 1');
    $lookupStmt->bind_param('i', $product_id);
    $lookupStmt->execute();
    $lookupStmt->bind_result($productName);
    if (!$lookupStmt->fetch() || !$productName) {
        $lookupStmt->close();
        continue;
    }
    $lookupStmt->close();

    $insertStmt->bind_param('si', $student_id, $product_id);
    if ($insertStmt->execute()) {
        $notifStatus = 'Buy Request Pending Approval';
        $notifStmt->bind_param('iss', $student_id, $productName, $notifStatus);
        $notifStmt->execute();
        $insertedCount++;
    }
}

$insertStmt->close();
$notifStmt->close();

if ($insertedCount === 0) {
    jsonResponse(['success' => false, 'message' => 'Unable to submit any requests.']);
}

updatePendingRequestCount($conn);
logAudit('student', $student_id, 'submit_request', 'Submitted ' . $insertedCount . ' product request(s).');
jsonResponse(['success' => true, 'message' => 'Request submitted successfully.', 'items_submitted' => $insertedCount]);

function updatePendingRequestCount($conn) {
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM requests WHERE status = 'pending'");
    $count = $result ? intval($result->fetch_assoc()['cnt'] ?? 0) : 0;
    $conn->query("INSERT INTO count_items (pending_requests) SELECT $count WHERE NOT EXISTS (SELECT 1 FROM count_items)");
    $conn->query("UPDATE count_items SET pending_requests = $count");
}
?>
