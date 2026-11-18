<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['admin', 'cashier', 'admincashier', 'superadmin']);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
require_once __DIR__ . '/product_return_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
requireProductReturnCsrf();

$transactionStarted = false;
try {
    $payload = json_decode(file_get_contents('php://input'), true);
    $token = trim((string)($payload['token'] ?? ''));
    if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
        jsonResponse(['success' => false, 'message' => 'This return QR code is invalid.'], 400);
    }

    $cashierId = (int)($_SESSION['admin_id'] ?? $_SESSION['admincashier_id'] ?? 0);
    if ($cashierId <= 0) {
        jsonResponse(['success' => false, 'message' => 'Cashier session is invalid.'], 401);
    }

    $conn = productReturnsDatabase();
    if (!$conn->begin_transaction()) {
        throw new RuntimeException('Unable to start return handoff transaction.');
    }
    $transactionStarted = true;
    $tokenHash = hash('sha256', strtolower($token));
    $requestStmt = $conn->prepare("
        SELECT pr.id, pr.product_name, pr.quantity, pr.unit_name, pr.request_type,
               pr.status, pr.handoff_received_at, ct.transaction_number
        FROM product_return_requests pr
        JOIN cashier_transactions ct ON ct.id = pr.cashier_transaction_id
        WHERE pr.handoff_token_hash = ?
        FOR UPDATE
    ");
    if (!$requestStmt) {
        throw new RuntimeException('Unable to prepare return QR lookup.');
    }
    $requestStmt->bind_param('s', $tokenHash);
    if (!$requestStmt->execute()) {
        throw new RuntimeException('Unable to load return QR request.');
    }
    $request = $requestStmt->get_result()->fetch_assoc();
    $requestStmt->close();
    if (!$request) {
        $conn->rollback();
        $transactionStarted = false;
        jsonResponse(['success' => false, 'message' => 'Return request was not found.'], 404);
    }
    if ($request['status'] === 'rejected') {
        $conn->rollback();
        $transactionStarted = false;
        jsonResponse(['success' => false, 'message' => 'This return request was rejected and cannot be received.'], 409);
    }

    $alreadyReceived = !empty($request['handoff_received_at']);
    if (!$alreadyReceived) {
        $updateStmt = $conn->prepare("
            UPDATE product_return_requests
            SET handoff_received_at = NOW(), handoff_received_by = ?
            WHERE id = ? AND handoff_received_at IS NULL
        ");
        if (!$updateStmt) {
            throw new RuntimeException('Unable to prepare return handoff update.');
        }
        $requestId = (int)$request['id'];
        $updateStmt->bind_param('ii', $cashierId, $requestId);
        if (!$updateStmt->execute() || $updateStmt->affected_rows !== 1) {
            throw new RuntimeException('Unable to record return item handoff.');
        }
        $updateStmt->close();

        $receivedStmt = $conn->prepare("
            SELECT pr.handoff_received_at,
                   CONCAT_WS(' ', aa.first_name, aa.last_name) AS handoff_receiver_name
            FROM product_return_requests pr
            LEFT JOIN admincashier_acc aa ON aa.id = pr.handoff_received_by
            WHERE pr.id = ?
        ");
        if (!$receivedStmt) {
            throw new RuntimeException('Unable to load return handoff details.');
        }
        $receivedStmt->bind_param('i', $requestId);
        if (!$receivedStmt->execute()) {
            throw new RuntimeException('Unable to load return handoff details.');
        }
        $received = $receivedStmt->get_result()->fetch_assoc();
        $receivedStmt->close();
        $request['handoff_received_at'] = $received['handoff_received_at'] ?? null;
        $request['handoff_receiver_name'] = $received['handoff_receiver_name'] ?? null;
    } else {
        $receiverStmt = $conn->prepare("
            SELECT CONCAT_WS(' ', aa.first_name, aa.last_name) AS handoff_receiver_name
            FROM product_return_requests pr
            LEFT JOIN admincashier_acc aa ON aa.id = pr.handoff_received_by
            WHERE pr.id = ?
        ");
        if (!$receiverStmt) {
            throw new RuntimeException('Unable to load return handoff details.');
        }
        $requestId = (int)$request['id'];
        $receiverStmt->bind_param('i', $requestId);
        if (!$receiverStmt->execute()) {
            throw new RuntimeException('Unable to load return handoff details.');
        }
        $request['handoff_receiver_name'] = $receiverStmt->get_result()->fetch_assoc()['handoff_receiver_name'] ?? null;
        $receiverStmt->close();
    }

    if (!$conn->commit()) {
        throw new RuntimeException('Unable to commit return handoff.');
    }
    $transactionStarted = false;

    jsonResponse([
        'success' => true,
        'already_received' => $alreadyReceived,
        'request' => $request,
        'csrf_token' => getCsrfToken()
    ]);
} catch (Throwable $e) {
    if (isset($conn) && $transactionStarted) {
        $conn->rollback();
    }
    error_log('Product return QR scan failed: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Unable to record this return handoff. Please try again.'], 500);
}
