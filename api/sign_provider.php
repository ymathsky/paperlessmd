<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireNotBillingApi();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);

if (!verifyCsrf($body['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$formId    = (int)($body['id']        ?? 0);
$signature = $body['signature']       ?? '';
$provName  = trim($body['provider_name'] ?? '');

if (!$formId || empty($signature)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
    exit;
}

if (!preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $signature)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid signature format']);
    exit;
}

$chk = $pdo->prepare("SELECT id, status FROM form_submissions WHERE id = ?");
$chk->execute([$formId]);
$form = $chk->fetch();

if (!$form) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Form not found']);
    exit;
}

// Only admins/MAs can add provider signature; form must already be signed by patient
if (!isAdmin() && !\isMa()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not authorized']);
    exit;
}

$upd = $pdo->prepare("
    UPDATE form_submissions
    SET provider_signature = ?, provider_name = ?
    WHERE id = ?
");
$upd->execute([$signature, $provName ?: null, $formId]);

require_once __DIR__ . '/../includes/audit.php';
auditLog($pdo, 'provider_sign', 'form', $formId, $provName);

// Email notification — form countersigned by provider
require_once __DIR__ . '/../includes/mailer.php';
require_once __DIR__ . '/../includes/notifications.php';
notifyProviderSigned($pdo, $formId, $provName ?: 'Provider');

echo json_encode(['ok' => true]);
