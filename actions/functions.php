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
    $stmt = $conn->prepare("SELECT id, username, CONCAT_WS(' ', first_name, middle_name, last_name) AS name, email, status, created_at, last_login, login_attempts FROM admincashier_acc ORDER BY created_at DESC");
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

// Function to format raw bytes into human-readable values
function formatBytes(int $bytes): string {
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = floor(log($bytes, 1024));
    return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
}

function formatUptime(int $seconds): string {
    if ($seconds < 0) {
        return 'Unknown';
    }
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    if ($days > 0) {
        return sprintf('%dd %02dh %02dm', $days, $hours, $minutes);
    }
    return sprintf('%02dh %02dm', $hours, $minutes);
}

function getRecentActiveAdminLogins(int $minutes = 15): int {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM admincashier_acc WHERE status = 'active' AND last_login >= DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $minutes);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return isset($row['count']) ? (int)$row['count'] : 0;
}

function getSecurityFlagCount(): int {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) AS count FROM admincashier_acc WHERE status <> 'active' OR login_attempts >= 4");
    if (!$stmt) {
        return 0;
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return isset($row['count']) ? (int)$row['count'] : 0;
}

function getStudentAccountCounts(): array {
    global $conn;
    $stmt = $conn->prepare("SELECT
            COUNT(*) AS total_students,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_students
        FROM users
        WHERE student_id IS NOT NULL AND student_id != ''");
    if (!$stmt) {
        return ['total_students' => 0, 'pending_students' => 0];
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    return [
        'total_students' => isset($row['total_students']) ? (int)$row['total_students'] : 0,
        'pending_students' => isset($row['pending_students']) ? (int)$row['pending_students'] : 0,
    ];
}

function getDatabaseSizeInfo(): array {
    global $conn;
    $query = "SELECT SUM(data_length + index_length) AS total_size, SUM(data_free) AS free_size, COUNT(*) AS table_count FROM information_schema.tables WHERE table_schema = DATABASE()";
    $result = $conn->query($query);
    if (!$result) {
        return ['total_size' => 0, 'free_size' => 0, 'table_count' => 0];
    }
    $row = $result->fetch_assoc();
    return [
        'total_size' => (int)($row['total_size'] ?? 0),
        'free_size' => (int)($row['free_size'] ?? 0),
        'table_count' => (int)($row['table_count'] ?? 0),
    ];
}

function getMySqlUptimeSeconds(): int {
    global $conn;
    $result = $conn->query("SHOW GLOBAL STATUS LIKE 'Uptime'");
    if (!$result || $result->num_rows === 0) {
        return -1;
    }
    $row = $result->fetch_assoc();
    return isset($row['Value']) ? (int)$row['Value'] : -1;
}

function getServerLoadInfo(): string {
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        if (is_array($load) && count($load) > 0 && is_numeric($load[0])) {
            return round($load[0], 2) . '%';
        }
    }

    if (stripos(PHP_OS, 'WIN') === 0 && function_exists('shell_exec')) {
        $output = trim(shell_exec('wmic cpu get loadpercentage /value 2>&1'));
        if (preg_match('/LoadPercentage=(\d+)/i', $output, $matches)) {
            return $matches[1] . '%';
        }
    }

    return 'N/A';
}

// Function to get system stats
function getSystemStats() {
    return [
        'total_admin_accounts' => count(getAdminAccounts()),
        'active_sessions' => getRecentActiveAdminLogins(),
        'system_uptime' => formatUptime(getMySqlUptimeSeconds()),
        'pending_issues' => getSecurityFlagCount()
    ];
}

// Function to get chart data
function getChartData() {
    // Dummy data for charts
    return [
        'activities_labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        'activities_data' => [10, 15, 8, 12, 20, 5, 18],
        'health_labels' => ['Database', 'Storage', 'Disk', 'Network'],
        'health_data' => [70, 60, 80, 50]
    ];
}

// Function to get system metrics
function getSystemMetrics() {
    $dbInfo = getDatabaseSizeInfo();
    $dbSize = $dbInfo['total_size'];
    $freeSize = $dbInfo['free_size'];
    $studentMetrics = getStudentAccountCounts();

    return [
        'database_size' => formatBytes($dbSize),
        'storage_used' => formatBytes($freeSize),
        'active_connections' => getRecentActiveAdminLogins(),
        'server_load' => getServerLoadInfo(),
        'total_admin_accounts' => count(getAdminAccounts()),
        'total_student_accounts' => $studentMetrics['total_students'],
        'pending_student_accounts' => $studentMetrics['pending_students'],
        'system_uptime' => formatUptime(getMySqlUptimeSeconds()),
        'pending_issues' => getSecurityFlagCount()
    ];
}

// Resolve a backup file path using stored path and backups directory fallback
function resolveBackupPath($filePath) {
    $relativePath = ltrim($filePath ?? '', '/\\');
    $candidates = [
        __DIR__ . '/../' . $relativePath,
        __DIR__ . '/../backups/' . basename($relativePath),
        __DIR__ . '/../' . basename($relativePath)
    ];

    foreach ($candidates as $candidate) {
        if (is_readable($candidate)) {
            return realpath($candidate);
        }
    }

    $backupDir = realpath(__DIR__ . '/../backups');
    if ($backupDir) {
        $basename = basename($relativePath);
        if ($basename) {
            $matches = glob($backupDir . '/*' . $basename);
            foreach ($matches as $match) {
                if (is_readable($match)) {
                    return realpath($match);
                }
            }
        }
    }

    return null;
}

// Function to get a single backup record
function getBackupRecord($backupId) {
    global $conn;
    $stmt = $conn->prepare("SELECT id, backup_date, file_size, status, file_path FROM system_backups WHERE id = ?");
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $backupId);
    $stmt->execute();
    $result = $stmt->get_result();
    $backup = $result->fetch_assoc();
    $stmt->close();

    return $backup ?: null;
}

// Function to get recent backups
function getRecentBackups() {
    global $conn;
    $stmt = $conn->prepare("SELECT id, backup_date, file_size, status, file_path FROM system_backups ORDER BY backup_date DESC LIMIT 15");
    if (!$stmt) {
        return [];
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $backups = [];
    while ($row = $result->fetch_assoc()) {
        $resolvedPath = resolveBackupPath($row['file_path'] ?? '');
        $row['file_exists'] = (bool) $resolvedPath;
        $row['file_name'] = basename($row['file_path'] ?? '') ?: ('backup_' . ($row['id'] ?? 'unknown'));
        if (!$row['file_exists'] && $row['status'] === 'success') {
            $row['status'] = 'missing';
        }
        $backups[] = $row;
    }
    $stmt->close();
    return $backups;
}

// Function to delete a backup record and file
function deleteBackup($backupId) {
    $backup = getBackupRecord($backupId);
    if (!$backup) {
        return false;
    }

    $relativePath = ltrim($backup['file_path'] ?? '', '/\\');
    $backupPath = realpath(__DIR__ . '/../' . $relativePath);
    if ($backupPath && is_file($backupPath)) {
        @unlink($backupPath);
    }

    global $conn;
    $stmt = $conn->prepare("DELETE FROM system_backups WHERE id = ?");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('i', $backupId);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
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

    fwrite($handle, "SET foreign_key_checks = 0;\n\n");

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

        if (strtolower($table) === 'system_backups') {
            // Do not dump the backup metadata table into backup files.
            continue;
        }

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

    $backupPath = resolveBackupPath($backup['file_path']);
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
    $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'GRANBY COLLEGES OF SCIENCE AND TECHNOLOGY Support';
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
