<?php
require_once __DIR__ . '/functions.php';

$backupId = 1;
$backup = getBackupRecord($backupId);
var_export($backup);
