<?php
require_once __DIR__ . '/../config/db_connect.php';

/**
 * Universal helper to retrieve the active student ID.
 * Allows admincashier_acc/cashiers to override via GET parameter when viewing student portals.
 * @return string|null
 */
function get_student_id() {
    $role = $_SESSION['role'] ?? '';
    
    // If staff is logged in, allow them to view a specific student via GET
    if (in_array($role, ['admincashier', 'superadmin'])) {
        if (!empty($_GET['student_id'])) {
            return $_GET['student_id'];
        }
    }
    
    // Default to the logged-in student's ID from session
    return $_SESSION['student_id'] ?? null;
}

// Function to get all admin accounts
function getAdminAccounts() {
    global $conn;
    $stmt = $conn->prepare("SELECT id, name, email, status, created_at, last_login, login_attempts FROM admincashier_acc ORDER BY created_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    $admincashier_acc = [];
    while ($row = $result->fetch_assoc()) {
        $admincashier_acc[] = $row;
    }
    $stmt->close();
    return $admincashier_acc;
}

// Function to update admin status
function updateadmincashier_acctatus($adminId, $status) {
    global $conn;
    $stmt = $conn->prepare("UPDATE admincashier_acc SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $adminId);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

// Function to delete admin
function deleteAdmin($adminId) {
    global $conn;
    $stmt = $conn->prepare("DELETE FROM admincashier_acc WHERE id = ?");
    $stmt->bind_param("i", $adminId);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

// Function to get system stats
function getSystemStats() {
    // This would typically get system info, but for simplicity, return dummy data
    return [
        'total_admin_accounts' => count(getAdminAccounts()),
        'active_sessions' => 5, // Dummy
        'system_uptime' => '99.9%', // Dummy
        'pending_issues' => 2 // Dummy
    ];
}

// Function to get chart data
function getChartData() {
    // Dummy data for charts
    return [
        'activities_labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        'activities_data' => [10, 15, 8, 12, 20, 5, 18],
        'health_labels' => ['CPU', 'Memory', 'Disk', 'Network'],
        'health_data' => [70, 60, 80, 50]
    ];
}

// Function to get system metrics
function getSystemMetrics() {
    // Dummy data for demonstration
    return [
        'database_size' => '256 MB',
        'storage_used' => '1.2 GB',
        'active_connections' => 5,
        'server_load' => '45%'
    ];
}

// Function to get recent backups
function getRecentBackups() {
    global $conn;
    $stmt = $conn->prepare("SELECT id, backup_date, file_size, status FROM system_backups ORDER BY backup_date DESC LIMIT 10");
    $stmt->execute();
    $result = $stmt->get_result();
    $backups = [];
    while ($row = $result->fetch_assoc()) {
        $backups[] = $row;
    }
    $stmt->close();
    return $backups;
}

// Function to clear cache
function clearCache() {
    // Dummy implementation
    return true;
}

// Function to generate a SQL dump file for the current database
function generateDatabaseDump($filePath) {
    global $conn;

    $handle = fopen($filePath, 'w');
    if (!$handle) {
        return false;
    }

    $dbName = mysqli_real_escape_string($conn, $conn->query('select database()')->fetch_row()[0] ?? '');
    fwrite($handle, "SET foreign_key_checks = 0;\n");
    fwrite($handle, "DROP DATABASE IF EXISTS `{$dbName}`;\n");
    fwrite($handle, "CREATE DATABASE IF NOT EXISTS `{$dbName}`;\n");
    fwrite($handle, "USE `{$dbName}`;\n\n");

    $tablesResult = $conn->query('SHOW TABLES');
    if (!$tablesResult) {
        fclose($handle);
        return false;
    }

    while ($tableRow = $tablesResult->fetch_array(MYSQLI_NUM)) {
        $table = $tableRow[0];
        $createResult = $conn->query("SHOW CREATE TABLE `{$table}`");
        if (!$createResult) {
            continue;
        }
        $createRow = $createResult->fetch_assoc();
        fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
        fwrite($handle, $createRow['Create Table'] . ";\n\n");

        $rowsResult = $conn->query("SELECT * FROM `{$table}`");
        if ($rowsResult && $rowsResult->num_rows > 0) {
            while ($row = $rowsResult->fetch_assoc()) {
                $columns = array_map(function($col) use ($conn) {
                    return '`' . str_replace('`', '``', $col) . '`';
                }, array_keys($row));
                $values = array_map(function($value) use ($conn) {
                    if ($value === null) {
                        return 'NULL';
                    }
                    return "'" . $conn->real_escape_string($value) . "'";
                }, array_values($row));
                fwrite($handle, "INSERT INTO `{$table}` (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $values) . ");\n");
            }
            fwrite($handle, "\n");
        }
    }

    fwrite($handle, "SET foreign_key_checks = 1;\n");
    fclose($handle);
    return true;
}

// Function to backup database
function backupDatabase() {
    global $conn;
    $backupDir = __DIR__ . '/../backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $filename = 'backup_' . time() . '.sql';
    $relativePath = 'backups/' . $filename;
    $fullPath = $backupDir . '/' . $filename;

    $dumpCreated = generateDatabaseDump($fullPath);
    if (!$dumpCreated) {
        return false;
    }

    $fileSize = filesize($fullPath);
    $fileSizeText = $fileSize !== false ? round($fileSize / 1024 / 1024, 2) . ' MB' : '0 MB';

    $stmt = $conn->prepare("INSERT INTO system_backups (file_size, status, file_path) VALUES (?, 'success', ?)");
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param("ss", $fileSizeText, $relativePath);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

// Function to restore a backup
function restoreBackup($backupId) {
    global $conn;

    $stmt = $conn->prepare("SELECT file_path FROM system_backups WHERE id = ?");
    if (!$stmt) {
        throw new Exception('Failed to prepare restore query: ' . $conn->error);
    }

    $stmt->bind_param('i', $backupId);
    $stmt->execute();
    $result = $stmt->get_result();
    $backup = $result->fetch_assoc();
    $stmt->close();

    if (!$backup || empty($backup['file_path'])) {
        throw new Exception('Backup record not found');
    }

    $relativePath = ltrim($backup['file_path'], '/\\');
    $candidates = [
        __DIR__ . '/../' . $relativePath,
        __DIR__ . '/../backups/' . basename($relativePath),
        __DIR__ . '/../' . basename($relativePath)
    ];

    $backupPath = null;
    foreach ($candidates as $candidate) {
        if (is_readable($candidate)) {
            $backupPath = realpath($candidate);
            break;
        }
    }

    if (!$backupPath) {
        throw new Exception('Backup file not found or not readable');
    }

    $sql = file_get_contents($backupPath);
    if ($sql === false) {
        throw new Exception('Unable to read backup file');
    }

    if (!$conn->multi_query($sql)) {
        throw new Exception('Database restore failed: ' . $conn->error);
    }

    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());

    return true;
}

// Function to test email service
function testEmailService() {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../vendor/autoload.php';

    $smtpHost = defined('SMTP_HOST') ? trim(SMTP_HOST) : '';
    $smtpUser = defined('SMTP_USER') ? trim(SMTP_USER) : '';
    $smtpPass = defined('SMTP_PASS') ? trim(SMTP_PASS) : '';
    $smtpPort = defined('SMTP_PORT') ? SMTP_PORT : 587;
    $smtpSecure = defined('SMTP_SECURE') ? strtolower(trim(SMTP_SECURE)) : 'tls';

    if (empty($smtpHost) || empty($smtpUser) || empty($smtpPass)) {
        throw new Exception('SMTP configuration is incomplete. Please verify SMTP_HOST, SMTP_USER, and SMTP_PASS.');
    }

    $fromEmail = filter_var(defined('MAIL_FROM') ? MAIL_FROM : '', FILTER_VALIDATE_EMAIL) ? MAIL_FROM : $smtpUser;
    $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'GCST Support';
    $toEmail = filter_var($smtpUser, FILTER_VALIDATE_EMAIL) ? $smtpUser : $fromEmail;
    if (empty($toEmail)) {
        throw new Exception('No valid recipient email available for SMTP test.');
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = defined('SMTP_AUTH') ? SMTP_AUTH : true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPass;
    $mail->Port = $smtpPort;

    if ($smtpSecure === 'ssl') {
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
    } else {
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
    }

    $mail->SMTPOptions = [
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true,
        ],
    ];

    $mail->setFrom($fromEmail, $fromName);
    $mail->addAddress($toEmail);
    $mail->isHTML(true);
    $mail->Subject = 'GCST Track System SMTP Test Email';
    $mail->Body = '<p>This is a test email from GCST Track System.</p>'
        . '<p>If you received this message, SMTP is configured correctly.</p>';
    $mail->AltBody = 'This is a test email from GCST Track System. If you received this message, SMTP is configured correctly.';

    if (!$mail->send()) {
        throw new Exception('SMTP test failed: ' . $mail->ErrorInfo);
    }

    return true;
}

// Function to optimize database
function optimizeDatabase() {
    global $conn;
    $tables = ['admincashier_acc', 'products', 'sales', 'system_backups']; // Add more tables as needed
    foreach ($tables as $table) {
        $conn->query("OPTIMIZE TABLE $table");
    }
    return true;
}
?>
