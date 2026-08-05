<?php
header('Content-Type: application/json');
include 'functions.php';

try {
    $metrics = getSystemMetrics();
    echo json_encode(array_merge(['success' => true], $metrics));
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>