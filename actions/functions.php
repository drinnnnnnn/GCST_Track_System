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

// Function to backup database
function backupDatabase() {
    global $conn;
    // Dummy backup logic
    $fileSize = rand(100, 500) . ' MB'; // Random size for demo
    $stmt = $conn->prepare("INSERT INTO system_backups (file_size, status, file_path) VALUES (?, 'success', ?)");
    $path = '/backups/backup_' . time() . '.sql';
    $stmt->bind_param("ss", $fileSize, $path);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

// Function to test email service
function testEmailService() {
    // Dummy implementation
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
