<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script must be run from the command line.\n");
}

require_once __DIR__ . '/../database/connection.php';
require_once __DIR__ . '/email_helpers.php';

function runDailyLowStockNotification(): int
{
    global $conn;

    $lockAcquired = false;

    try {
        if (!isset($conn) || !($conn instanceof mysqli)) {
            throw new RuntimeException('Database connection is unavailable.');
        }

        date_default_timezone_set('Asia/Manila');
        $conn->query("SET time_zone = '+08:00'");

        $lockResult = $conn->query("SELECT GET_LOCK('gcst_daily_low_stock_notification', 0) AS lock_acquired");
        if (!$lockResult) {
            throw new RuntimeException('Unable to acquire the low-stock notification lock.');
        }
        $lockAcquired = (int)$lockResult->fetch_assoc()['lock_acquired'] === 1;
        $lockResult->free();

        if (!$lockAcquired) {
            echo "Skipped: another low-stock notification run is in progress.\n";
            return 0;
        }

        $stockColumnResult = $conn->query("SHOW COLUMNS FROM `products` LIKE 'stock_count'");
        if (!$stockColumnResult) {
            throw new RuntimeException('Unable to determine the product stock column.');
        }
        $stockColumn = $stockColumnResult->num_rows > 0 ? 'stock_count' : 'stock';
        $stockColumnResult->free();

        $productsResult = $conn->query(
            "SELECT product_name, COALESCE(`$stockColumn`, 0) AS stock_quantity
             FROM products
             WHERE COALESCE(`$stockColumn`, 0) < 10
             ORDER BY product_name"
        );
        if (!$productsResult) {
            throw new RuntimeException('Unable to load low-stock inventory: ' . $conn->error);
        }
        $products = $productsResult->fetch_all(MYSQLI_ASSOC);
        $productsResult->free();

        if (!$products) {
            echo "No low-stock inventory found; no email sent.\n";
            return 0;
        }

        $adminsResult = $conn->query(
            "SELECT email
             FROM admincashier_acc
             WHERE role = 'admin' AND status = 'active' AND email <> ''
             ORDER BY id"
        );
        if (!$adminsResult) {
            throw new RuntimeException('Unable to load active admin recipients: ' . $conn->error);
        }
        $admins = $adminsResult->fetch_all(MYSQLI_ASSOC);
        $adminsResult->free();

        if (!$admins) {
            throw new RuntimeException('No active admin email recipients were found.');
        }

        $today = date('Y-m-d');
        $dayStart = $today . ' 00:00:00';
        $dayEnd = date('Y-m-d 00:00:00', strtotime($today . ' +1 day'));
        $notificationType = 'Daily Low Stock Inventory';
        $subject = "Daily Low Stock Inventory Report - $today";

        $rows = '';
        foreach ($products as $product) {
            $name = htmlspecialchars((string)$product['product_name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $quantity = number_format((float)$product['stock_quantity'], 2);
            $rows .= "<tr><td style='padding:8px;border:1px solid #cbd5e1;'>{$name}</td>"
                . "<td style='padding:8px;border:1px solid #cbd5e1;text-align:right;'>{$quantity}</td></tr>";
        }

        $body = "
            <div style='font-family:Arial,Helvetica,sans-serif;color:#0f172a;line-height:1.6;'>
                <h2 style='color:#b45309;'>Low-stock inventory alert</h2>
                <p>The following items are below the low-stock threshold of 10:</p>
                <table style='border-collapse:collapse;width:100%;max-width:640px;'>
                    <thead><tr>
                        <th style='padding:8px;border:1px solid #cbd5e1;text-align:left;'>Product</th>
                        <th style='padding:8px;border:1px solid #cbd5e1;text-align:right;'>Current stock</th>
                    </tr></thead>
                    <tbody>{$rows}</tbody>
                </table>
                <p style='font-size:12px;color:#64748b;'>GCST Track System - {$today}</p>
            </div>";

        $sentCount = 0;
        $failedCount = 0;

        foreach ($admins as $admin) {
            $recipient = trim((string)$admin['email']);
            $sentCheck = $conn->prepare(
                "SELECT 1 FROM email_notifications
                 WHERE recipient = ? AND notification_type = ? AND status = 'sent'
                   AND sent_at >= ? AND sent_at < ?
                 LIMIT 1"
            );
            if (!$sentCheck) {
                throw new RuntimeException('Unable to check today\'s low-stock notification history.');
            }
            $sentCheck->bind_param('ssss', $recipient, $notificationType, $dayStart, $dayEnd);
            $sentCheck->execute();
            $alreadySent = $sentCheck->get_result()->num_rows > 0;
            $sentCheck->close();

            if ($alreadySent) {
                echo "Skipped: today's report was already sent to an admin account.\n";
                continue;
            }

            $result = sendEmailWithLog(
                $conn,
                $recipient,
                $subject,
                $body,
                $notificationType
            );

            if (!empty($result['success'])) {
                $sentCount++;
                echo "Sent low-stock report to an admin account.\n";
            } else {
                $failedCount++;
                error_log(
                    "Low-stock notification failed for an admin account: "
                    . (string)($result['message'] ?? 'Unknown email error.')
                );
            }
        }

        echo "Low-stock notification complete: $sentCount sent, $failedCount failed.\n";
        return $failedCount > 0 ? 1 : 0;
    } finally {
        if ($lockAcquired) {
            $releaseResult = $conn->query("SELECT RELEASE_LOCK('gcst_daily_low_stock_notification')");
            if (!$releaseResult) {
                throw new RuntimeException('Unable to release the low-stock notification lock.');
            }
            $releaseResult->free();
        }
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->close();
        }
    }
}

try {
    exit(runDailyLowStockNotification());
} catch (Throwable $e) {
    error_log('Daily low-stock notification error: ' . $e->getMessage());
    fwrite(STDERR, 'Daily low-stock notification failed: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
