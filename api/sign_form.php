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

$formId    = (int)($body['id'] ?? 0);
$signature = $body['signature'] ?? '';

if (!$formId || empty($signature)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
    exit;
}

// Validate signature is a data URL (PNG only)
if (!preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $signature)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid signature format']);
    exit;
}

// Load form — MAs can only sign their own; admins can sign any
$chk = $pdo->prepare("SELECT id, status, ma_id FROM form_submissions WHERE id = ?");
$chk->execute([$formId]);
$form = $chk->fetch();

if (!$form) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Form not found']);
    exit;
}

if (!isAdmin() && (int)$form['ma_id'] !== (int)$_SESSION['user_id']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Not authorized to sign this form']);
    exit;
}

if ($form['status'] !== 'draft') {
    echo json_encode(['ok' => false, 'error' => 'Form is already signed']);
    exit;
}

$upd = $pdo->prepare("
    UPDATE form_submissions
    SET patient_signature = ?, status = 'signed'
    WHERE id = ?
");
$upd->execute([$signature, $formId]);

require_once __DIR__ . '/../includes/audit.php';
auditLog($pdo, 'form_sign', 'form', $formId);

echo json_encode(['ok' => true]);
