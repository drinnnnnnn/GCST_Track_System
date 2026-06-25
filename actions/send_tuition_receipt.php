<?php
header('Content-Type: application/json');
// Prevent HTML error output from corrupting JSON responses
ini_set('display_errors', '0');
if (ob_get_level() === 0) {
    ob_start();
}

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/email_helpers.php';

secureSessionStart();
requireAuth(['admincashier', 'superadmin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

$recipient = trim($input['recipient'] ?? '');
$subject = trim($input['subject'] ?? 'Tuition Fee Receipt');
$message = trim($input['message'] ?? '');
$signatureImage = trim($input['signature_image'] ?? '');

if (empty($recipient) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Recipient and message are required.']);
    exit;
}

if (!filter_var($recipient, FILTER_VALIDATE_EMAIL) || !preg_match('/@gmail\.com$/i', $recipient)) {
    echo json_encode(['success' => false, 'message' => 'A valid Gmail address is required.']);
    exit;
}

$conn = Database::getConnection();
if (!$conn || $conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$attachments = [];
if ($signatureImage !== '') {
    $parsedPath = ltrim(parse_url($signatureImage, PHP_URL_PATH) ?? '', '/\\');
    $parsedPath = preg_replace('#^(?:GCST_Track_System/|/GCST_Track_System/)#', '', $parsedPath);
    $allowedPath = __DIR__ . '/../' . $parsedPath;
    $fullPath = realpath($allowedPath);
    $projectRoot = realpath(__DIR__ . '/../');
    if ($fullPath && $projectRoot && strpos($fullPath, $projectRoot) === 0 && file_exists($fullPath)) {
        $attachments[] = [
            'path' => $fullPath,
            'cid' => 'admin_signature',
            'name' => 'signature.png'
        ];
    }
}

try {
    $result = sendEmailWithLog($conn, $recipient, $subject, $message, 'Tuition Receipt', $attachments);
    echo json_encode([
        'success' => $result['success'],
        'message' => $result['message'] ?? ($result['success'] ? 'Email sent.' : 'Unable to send email.')
    ]);
} catch (Throwable $e) {
    error_log('Tuition Email Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'There was an error sending the email.']);
}

$conn->close();
?>