<?php
// actions/find_logs.php
header('Content-Type: text/plain');

$logPath = ini_get('error_log');
if (empty($logPath)) {
    echo "No specific PHP error log defined in php.ini.\nThis usually means errors are sent to the Apache error log:\nC:\\xampp\\apache\\logs\\error.log";
} else {
    echo "Your PHP error log is located at:\n" . $logPath;
}
?>