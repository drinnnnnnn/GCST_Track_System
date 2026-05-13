<?php
header('Content-Type: application/json');
require_once __DIR__ . '/security.php';

secureSessionStart();
requireAuth(['admin','admincashier','superadmin']);

require_once __DIR__ . '/../database/connection.php';
$conn = Database::getConnection();

$data = json_decode(file_get_contents('php://input'), true);
$request_id = intval($data['request_id'] ?? 0);
$status = strtolower(trim($data['status'] ?? ''));

// Only allow system or admin roles to change statuses
if (!in_array($_SESSION['role'] ?? '', ['admin','admincashier','superadmin'], true)) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Valid workflow statuses
$validStatuses = ['pending','approved','declined','processing','completed'];
if (!in_array($status, $validStatuses, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Enforce transitions: pending -> approved/declined, approved -> processing, processing -> completed
$stmt = $conn->prepare("SELECT status FROM request WHERE request_id = ?");
$stmt->bind_param('i', $request_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Request not found']);
    exit;
}
$current = $res->fetch_assoc()['status'];

$allowed = false;
switch ($current) {
    case 'pending':
        $allowed = in_array($status, ['approved','declined'], true);
        break;
    case 'approved':
        $allowed = $status === 'processing';
        break;
    case 'processing':
        $allowed = $status === 'completed';
        break;
    default:
        $allowed = false;
}

if (!$allowed) {
    echo json_encode(['success' => false, 'message' => 'Invalid transition from ' . $current . ' to ' . $status]);
    exit;
}

$update = $conn->prepare("UPDATE request SET status=? WHERE request_id=?");
$update->bind_param('si', $status, $request_id);
$update->execute();

// Create notification entries for students on key transitions
if (in_array($status, ['approved','declined','processing','completed'], true)) {
    $stmt = $conn->prepare("SELECT student_id, book_id FROM request WHERE request_id = ?");
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    $r = $stmt->get_result();
    if ($row = $r->fetch_assoc()) {
        $student_id = $row['student_id'];
        $book_id = $row['book_id'] ?? null;
        $notif = $conn->prepare("INSERT INTO student_notification (student_id, book_id, status) VALUES (?, ?, ?)");
        $notif->bind_param('sis', $student_id, $book_id, $status);
        $notif->execute();
    }
}

updatePendingRequestsCount($conn);

echo json_encode(['success' => true]);

function updatePendingRequestsCount($conn) {
    $result = $conn->query("SELECT COUNT(*) AS cnt FROM request WHERE status = 'pending'");
    $row = $result->fetch_assoc();
    $total = (int)$row['cnt'];
    $conn->query("INSERT INTO count_items (pending_requests) SELECT $total WHERE NOT EXISTS (SELECT 1 FROM count_items)");
    $conn->query("UPDATE count_items SET pending_requests = $total");
}

?>
