<?php
/**
 * POST /api/mobile/auth.php
 * Body: { "username": "...", "password": "..." }
 * Returns: { "token": "...", "user": { id, full_name, role } }
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/helpers.php';

header('Content-Type: application/json');
cors();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonError('Method not allowed', 405);

$body = json_decode(file_get_contents('php://input'), true);
$username = trim($body['username'] ?? '');
$password  = $body['password']  ?? '';

if (!$username || !$password) jsonError('username and password required', 422);

$stmt = $pdo->prepare("SELECT * FROM staff WHERE username = ? AND active = 1 LIMIT 1");
$stmt->execute([$username]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    jsonError('Invalid credentials', 401);
}

// Generate a secure API token and store it
$token = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+30 days'));
$pdo->prepare("INSERT INTO mobile_tokens (staff_id, token, expires_at) VALUES (?, ?, ?)")
    ->execute([$user['id'], $token, $expires]);

jsonOk([
    'token' => $token,
    'user'  => [
        'id'        => $user['id'],
        'full_name' => $user['full_name'],
        'role'      => $user['role'],
        'username'  => $user['username'],
    ]
]);
