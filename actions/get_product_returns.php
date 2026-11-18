<?php
require_once __DIR__ . '/security.php';
secureSessionStart();
requireAuth(['student', 'user', 'admin', 'cashier', 'admincashier', 'superadmin']);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
require_once __DIR__ . '/product_return_helpers.php';

try {
    $conn = productReturnsDatabase();
    $role = (string)$_SESSION['role'];
    $isCashier = in_array($role, ['admin', 'cashier', 'admincashier', 'superadmin'], true);
    $allowedStatuses = ['pending', 'refund_pending', 'refunded', 'replacement_pending', 'completed', 'rejected'];
    $status = trim((string)($_GET['status'] ?? 'all'));
    $search = trim((string)($_GET['search'] ?? ''));
    if (mb_strlen($search) > 100) {
        $search = mb_substr($search, 0, 100);
    }
    if ($status !== 'all' && !in_array($status, $allowedStatuses, true)) {
        jsonResponse(['success' => false, 'message' => 'Invalid return status filter.'], 400);
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
    if (isset($_GET['locate_id'])) {
        if (!$isCashier || $status !== 'all' || $search !== '') {
            jsonResponse(['success' => false, 'message' => 'Invalid return location request.'], 400);
        }
        $locateId = filter_var($_GET['locate_id'], FILTER_VALIDATE_INT);
        if ($locateId === false || $locateId <= 0) {
            jsonResponse(['success' => false, 'message' => 'Invalid return request ID.'], 400);
        }
        $locationStmt = $conn->prepare("
            SELECT located_request.id,
                   (
                       SELECT COUNT(*)
                       FROM product_return_requests AS candidate_return
                       WHERE candidate_return.created_at > located_request.created_at
                          OR (candidate_return.created_at = located_request.created_at AND candidate_return.id > located_request.id)
                   ) AS rows_before
            FROM product_return_requests AS located_request
            WHERE located_request.id = ?
        ");
        if (!$locationStmt) {
            throw new RuntimeException('Unable to prepare product return location lookup.');
        }
        $locationStmt->bind_param('i', $locateId);
        if (!$locationStmt->execute()) {
            throw new RuntimeException('Unable to locate product return.');
        }
        $location = $locationStmt->get_result()->fetch_assoc();
        $locationStmt->close();
        if ($location) {
            $page = intdiv((int)$location['rows_before'], $limit) + 1;
        }
    }
    $offset = ($page - 1) * $limit;
    $where = [];
    $types = '';
    $values = [];

    if (!$isCashier) {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            jsonResponse(['success' => false, 'message' => 'User session is invalid.'], 401);
        }
        $where[] = 'pr.user_id = ?';
        $types .= 'i';
        $values[] = $userId;
    }
    if ($status !== 'all') {
        $where[] = 'pr.status = ?';
        $types .= 's';
        $values[] = $status;
    }
    if ($search !== '') {
        $where[] = 'pr.product_name LIKE ?';
        $types .= 's';
        $values[] = '%' . $search . '%';
    }
    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM product_return_requests pr $whereSql");
    if (!$countStmt) {
        throw new RuntimeException('Unable to prepare product returns count.');
    }
    if ($types !== '') {
        $countStmt->bind_param($types, ...$values);
    }
    if (!$countStmt->execute()) {
        throw new RuntimeException('Unable to count product returns.');
    }
    $total = (int)$countStmt->get_result()->fetch_assoc()['total'];
    $countStmt->close();

    $sql = "
        SELECT pr.id, pr.user_id, pr.cashier_transaction_id, pr.transaction_item_id,
               pr.product_id, pr.product_name, pr.quantity, pr.unit_name, pr.unit_price,
               pr.refund_amount, pr.request_type, pr.damage_description,
               pr.status, pr.rejection_reason, pr.reviewed_at, pr.fulfilled_at, pr.created_at,
               pr.handoff_received_at,
               ct.transaction_number,
               u.student_id,
               CONCAT_WS(' ', u.first_name, u.last_name) AS customer_name,
               CONCAT_WS(' ', aa.first_name, aa.last_name) AS reviewer_name,
               CONCAT_WS(' ', receiver.first_name, receiver.last_name) AS handoff_receiver_name
        FROM product_return_requests pr
        JOIN cashier_transactions ct ON ct.id = pr.cashier_transaction_id
        LEFT JOIN users u ON u.id = pr.user_id
        LEFT JOIN admincashier_acc aa ON aa.id = pr.reviewed_by
        LEFT JOIN admincashier_acc receiver ON receiver.id = pr.handoff_received_by
        $whereSql
        ORDER BY pr.created_at DESC, pr.id DESC
        LIMIT ? OFFSET ?
    ";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare product returns list.');
    }
    $queryValues = array_merge($values, [$limit, $offset]);
    $stmt->bind_param($types . 'ii', ...$queryValues);
    if (!$stmt->execute()) {
        throw new RuntimeException('Unable to load product returns.');
    }
    $requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    jsonResponse([
        'success' => true,
        'requests' => $requests,
        'total' => $total,
        'total_pages' => max(1, (int)ceil($total / $limit)),
        'current_page' => $page,
        'limit' => $limit,
        'csrf_token' => getCsrfToken()
    ]);
} catch (Throwable $e) {
    error_log('Product returns list failed: ' . $e->getMessage());
    jsonResponse(['success' => false, 'message' => 'Unable to load product returns.'], 500);
}
