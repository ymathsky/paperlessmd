<?php
/**
 * Shared helpers for mobile API endpoints.
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/db.php';

function cors(): void {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
}

function jsonOk(mixed $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode(['ok' => true,  'data' => $data]);
    exit;
}

function jsonError(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

/**
 * Validate Bearer token and return the staff row.
 * Call at the top of every protected endpoint.
 */
function requireToken(): array {
    global $pdo;
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(\S+)$/i', $auth, $m)) jsonError('Unauthorized', 401);
    $token = $m[1];

    $stmt = $pdo->prepare("
        SELECT s.*
        FROM mobile_tokens mt
        JOIN staff s ON s.id = mt.staff_id
        WHERE mt.token = ? AND mt.expires_at > NOW() AND s.active = 1
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    if (!$user) jsonError('Unauthorized', 401);
    return $user;
}
