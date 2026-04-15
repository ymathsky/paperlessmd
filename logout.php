<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/audit.php';
auditLog($pdo, 'logout');
session_unset();
session_destroy();
header('Location: ' . BASE_URL . '/index.php');
exit;
