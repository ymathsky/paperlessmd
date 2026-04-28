<?php
/**
 * api/save_signature.php
 * Save or clear the logged-in user's pre-saved signature.
 *
 * POST JSON body:
 *   { "csrf": "...", "action": "save", "signature": "data:image/png;base64,..." }
 *   { "csrf": "...", "action": "clear" }
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';

if (!verifyCsrf($body['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

if ($action === 'save') {
    $sig = $body['signature'] ?? '';
    if (!$sig || !preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $sig)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid signature format']);
        exit;
    }
    $pdo->prepare("UPDATE staff SET saved_signature = ?, saved_sig_updated_at = NOW() WHERE id = ?")
        ->execute([$sig, (int)$_SESSION['user_id']]);
    echo json_encode(['ok' => true, 'msg' => 'Signature saved']);

} elseif ($action === 'clear') {
    $pdo->prepare("UPDATE staff SET saved_signature = NULL, saved_sig_updated_at = NULL WHERE id = ?")
        ->execute([(int)$_SESSION['user_id']]);
    echo json_encode(['ok' => true, 'msg' => 'Signature cleared']);

} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
}
