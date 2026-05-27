<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/audit.php';
auditLog($pdo, 'logout');

// Remember where the user was so they can return after re-login
// Use HTTP_REFERER (the page they clicked logout from)
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$intendedUrl = (strpos($referer, BASE_URL . '/') === 0
                && strpos($referer, BASE_URL . '/index.php') !== 0)
    ? $referer : '';

session_unset();
session_destroy();

if ($intendedUrl) {
    session_start();
    $_SESSION['intended_url'] = $intendedUrl;
}

header('Location: ' . BASE_URL . '/index.php');
exit;
