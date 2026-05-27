<?php
/**
 * api/save_provider_sig.php
 * Save or clear the logged-in provider's saved RX signature + NPI.
 *
 * POST JSON body:
 *   { "csrf": "...", "action": "save", "signature": "data:image/png;base64,...", "npi": "1234567890" }
 *   { "csrf": "...", "action": "clear" }
 *
 * Only accessible by provider or admin roles.
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

// Only providers and admins may set a provider signature
if (!in_array($_SESSION['role'] ?? '', ['provider', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
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
    if (!$sig || !preg_match('/^data:image\/(png|jpeg|jpg);base64,[A-Za-z0-9+\/=]+$/', $sig)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid signature format']);
        exit;
    }
    $npi = trim($body['npi'] ?? '');
    // Validate NPI: empty or exactly 10 digits
    if ($npi !== '' && !preg_match('/^\d{10}$/', $npi)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'NPI must be exactly 10 digits']);
        exit;
    }

    $pdo->prepare("UPDATE staff SET saved_provider_signature = ?, saved_provider_npi = ? WHERE id = ?")
        ->execute([$sig, $npi ?: null, (int)$_SESSION['user_id']]);
    echo json_encode(['ok' => true, 'msg' => 'Provider signature saved']);

} elseif ($action === 'clear') {
    $pdo->prepare("UPDATE staff SET saved_provider_signature = NULL, saved_provider_npi = NULL WHERE id = ?")
        ->execute([(int)$_SESSION['user_id']]);
    echo json_encode(['ok' => true, 'msg' => 'Provider signature cleared']);

} else {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
}
