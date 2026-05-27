<?php
/**
 * api/push_subscribe.php
 *
 * Save or remove a Web Push subscription for the logged-in user.
 *
 * POST JSON:
 *   { "csrf": "...", "action": "subscribe",   "endpoint": "...", "keys": { "p256dh": "...", "auth": "..." } }
 *   { "csrf": "...", "action": "unsubscribe", "endpoint": "..." }
 *
 * GET:
 *   ?vapid  → returns { "publicKey": "<base64url>" }  (no auth required, public)
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

// ── GET: return VAPID public key (unauthenticated — needed before login) ──────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['vapid'])) {
    $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = 'vapid_public' LIMIT 1");
    $stmt->execute();
    $pub = (string)($stmt->fetchColumn() ?: '');
    echo json_encode(['publicKey' => $pub]);
    exit;
}

// ── POST: require login ───────────────────────────────────────────────────────
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? 'subscribe';

if (!verifyCsrf($body['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$staff_id = (int)$_SESSION['user_id'];

// ── Unsubscribe ───────────────────────────────────────────────────────────────
if ($action === 'unsubscribe') {
    $endpoint = $body['endpoint'] ?? '';
    if ($endpoint) {
        $pdo->prepare(
            "DELETE FROM push_subscriptions WHERE staff_id = ? AND endpoint = ?"
        )->execute([$staff_id, $endpoint]);
    }
    echo json_encode(['ok' => true]);
    exit;
}

// ── Subscribe ─────────────────────────────────────────────────────────────────
$endpoint = trim($body['endpoint'] ?? '');
$p256dh   = trim($body['keys']['p256dh'] ?? '');
$auth     = trim($body['keys']['auth']   ?? '');

if (!$endpoint || !$p256dh || !$auth) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing subscription fields']);
    exit;
}

// Basic sanity: endpoint must be https
if (!str_starts_with($endpoint, 'https://')) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid endpoint']);
    exit;
}

$stmt = $pdo->prepare(
    "INSERT INTO push_subscriptions (staff_id, endpoint, p256dh, auth)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
         staff_id   = VALUES(staff_id),
         p256dh     = VALUES(p256dh),
         auth       = VALUES(auth),
         updated_at = NOW()"
);
$stmt->execute([$staff_id, $endpoint, $p256dh, $auth]);

echo json_encode(['ok' => true]);
