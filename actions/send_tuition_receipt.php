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
requireAuth(['admin', 'cashier', 'admincashier', 'superadmin']);

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
$transactionNumber = trim((string)($input['transaction_number'] ?? ''));
$diagnostics = [
    'id' => bin2hex(random_bytes(6)),
    'stage' => 'request_validation',
    'receipt_found' => false,
    'png_generated' => false,
    'png_bytes' => 0,
    'attachment_registered' => false,
    'mime_verified' => false,
    'smtp_accepted' => false,
];

if (empty($recipient) || empty($message) || $transactionNumber === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Receipt, recipient, and message are required.',
        'diagnostics' => $diagnostics,
    ]);
    exit;
}

$diagnostics['stage'] = 'database_connection';
$conn = Database::getConnection();
if (!$conn || $conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed.',
        'diagnostics' => $diagnostics,
    ]);
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
    $diagnostics['stage'] = 'receipt_lookup';
    $receiptStmt = $conn->prepare("
        SELECT tr.transaction_number, tr.receipt_number, tr.student_id, tr.student_name,
               tr.student_year_level, tr.student_semester, tr.receipt_category,
               tr.amount_paid, tr.total_payment, tr.balance, tr.payment_method,
               tr.payment_status, tr.payment_type, tr.check_number, tr.created_at,
               tr.authorized_rep, tr.or_number, tr.remarks, tr.note, tr.student_email,
               COALESCE(NULLIF(TRIM(tr.student_email), ''), NULLIF(TRIM(u.email), '')) AS delivery_email,
               CONCAT_WS(' ', ac.first_name, ac.last_name) AS cashier_name,
               ac.signature_image AS cashier_signature_image
        FROM tuition_receipts tr
        LEFT JOIN users u ON tr.user_id = u.id
        LEFT JOIN admincashier_acc ac ON tr.cashier_id = ac.id
        WHERE tr.transaction_number = ?
        LIMIT 1
    ");
    if (!$receiptStmt) {
        throw new RuntimeException('Unable to prepare receipt lookup.');
    }
    $receiptStmt->bind_param('s', $transactionNumber);
    $receiptStmt->execute();
    $receipt = $receiptStmt->get_result()->fetch_assoc();
    $receiptStmt->close();
    $diagnostics['receipt_found'] = (bool)$receipt;

    if (!$receipt) {
        http_response_code(404);
        error_log('[ReceiptEmail ' . $diagnostics['id'] . '] Saved receipt not found.');
        echo json_encode([
            'success' => false,
            'message' => 'Saved receipt not found.',
            'diagnostics' => $diagnostics,
        ]);
        exit;
    }

    $diagnostics['stage'] = 'recipient_validation';
    $deliveryEmail = trim((string)($receipt['delivery_email'] ?? ''));
    if (
        $deliveryEmail === ''
        || !filter_var($deliveryEmail, FILTER_VALIDATE_EMAIL)
        || !preg_match('/@gmail\.com$/i', $deliveryEmail)
    ) {
        http_response_code(422);
        error_log('[ReceiptEmail ' . $diagnostics['id'] . '] Receipt has no valid Gmail recipient.');
        echo json_encode([
            'success' => false,
            'message' => 'A valid student Gmail address is not saved with this receipt.',
            'diagnostics' => $diagnostics,
        ]);
        exit;
    }
    if (strcasecmp($recipient, $deliveryEmail) !== 0) {
        http_response_code(403);
        error_log('[ReceiptEmail ' . $diagnostics['id'] . '] Recipient does not match saved receipt email.');
        echo json_encode([
            'success' => false,
            'message' => 'The recipient does not match the saved receipt email.',
            'diagnostics' => $diagnostics,
        ]);
        exit;
    }

    $diagnostics['stage'] = 'png_generation';
    require_once __DIR__ . '/receipt_image.php';
    $receipt['signature_image'] = $receipt['cashier_signature_image'] ?? '';
    $receiptImage = generateReceiptPng($receipt);
    if (substr($receiptImage['content'], 0, 8) !== "\x89PNG\r\n\x1a\n") {
        throw new RuntimeException('Generated receipt attachment is not a valid PNG.');
    }
    $diagnostics['png_generated'] = true;
    $diagnostics['png_bytes'] = strlen($receiptImage['content']);
    $diagnostics['attachment_name'] = $receiptImage['file_name'];
    $diagnostics['logo_included'] = (bool)($receiptImage['logo_included'] ?? false);
    $diagnostics['signature_included'] = (bool)($receiptImage['signature_included'] ?? false);
    if (!empty($receiptImage['signature_error'])) {
        $diagnostics['signature_error'] = $receiptImage['signature_error'];
    }
    $diagnostics['stage'] = 'mailer_attachment_validation';
    $attachments[] = [
        'data' => $receiptImage['content'],
        'mime_type' => 'image/png',
        'name' => $receiptImage['file_name'],
        'required' => true,
    ];
    $result = sendEmailWithLog($conn, $deliveryEmail, $subject, $message, 'Tuition Receipt', $attachments);
    foreach ($result['attachment_diagnostics'] ?? [] as $attachmentDiagnostic) {
        if (($attachmentDiagnostic['name'] ?? '') === $receiptImage['file_name']) {
            $diagnostics['attachment_registered'] = (bool)($attachmentDiagnostic['registered'] ?? false);
            $diagnostics['mime_verified'] = (bool)($attachmentDiagnostic['mime_verified'] ?? false);
            $diagnostics['attachment_mime'] = $attachmentDiagnostic['mime_type'] ?? '';
            break;
        }
    }
    $diagnostics['smtp_accepted'] = (bool)$result['success'];
    $diagnostics['stage'] = $diagnostics['smtp_accepted']
        ? 'smtp_accepted'
        : (!$diagnostics['attachment_registered']
            ? 'attachment_registration'
            : (!$diagnostics['mime_verified'] ? 'attachment_mime' : 'smtp_submission'));
    if (!$result['success']) {
        $diagnostics['error'] = $result['message'] ?? 'Mailer failed without an error message.';
    }
    error_log(
        '[ReceiptEmail ' . $diagnostics['id'] . '] ' .
        json_encode($diagnostics, JSON_UNESCAPED_SLASHES) .
        '; result=' . ($result['message'] ?? 'No mailer result.')
    );

    echo json_encode([
        'success' => $result['success'],
        'message' => $result['message'] ?? ($result['success'] ? 'Email sent.' : 'Unable to send email.'),
        'recipient' => $deliveryEmail,
        'diagnostics' => $diagnostics,
    ]);
} catch (Throwable $e) {
    $diagnostics['error'] = $e->getMessage();
    error_log(
        '[ReceiptEmail ' . $diagnostics['id'] . '] Failed at ' .
        $diagnostics['stage'] . ': ' . $e->getMessage()
    );
    echo json_encode([
        'success' => false,
        'message' => 'Receipt image email failed during ' . $diagnostics['stage'] . ': ' . $e->getMessage(),
        'diagnostics' => $diagnostics,
    ]);
}

$conn->close();
?>