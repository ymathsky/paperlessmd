<?php
/**
 * Session keep-alive ping.
 * Called by the client-side timeout warning to extend the session.
 * requireLogin() resets $_SESSION['last_active'] = time() on every call.
 */
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Check session manually (without redirect) so JS gets clean JSON on expiry
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Not authenticated']);
    exit;
}

if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active']) > SESSION_TIMEOUT) {
    session_unset();
    session_destroy();
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Session expired']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

if (!verifyCsrf($body['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Refresh the session activity timestamp
$_SESSION['last_active'] = time();

// Persist last_active_at to DB so admin can see online status (graceful if column missing)
try {
    $pdo->prepare("UPDATE staff SET last_active_at = NOW() WHERE id = ?")
        ->execute([(int)$_SESSION['user_id']]);
} catch (PDOException $e) { /* column not yet migrated on this server — safe to ignore */ }

echo json_encode(['ok' => true, 'lastActive' => $_SESSION['last_active']]);
