<?php
/**
 * API: admin-only CRUD for dashboard pinned notes.
 * Actions: create | delete
 * Body: { csrf, action, body?, note_id? }
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthenticated']);
    exit;
}
if (!isAdmin()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Admin only']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';

// CSRF
$inputCsrf = $input['csrf'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $inputCsrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

if ($action === 'create') {
    $body = trim($input['body'] ?? '');
    if ($body === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Note body is required']);
        exit;
    }
    $body = mb_substr($body, 0, 1000);
    $stmt = $pdo->prepare("INSERT INTO admin_notes (author_id, body, pinned) VALUES (?, ?, 1)");
    $stmt->execute([(int)$_SESSION['user_id'], $body]);
    $id = (int)$pdo->lastInsertId();
    auditLog($pdo, 'admin_note_add', 'admin_note', $id);
    echo json_encode(['ok' => true, 'id' => $id]);
    exit;
}

if ($action === 'delete') {
    $noteId = (int)($input['note_id'] ?? 0);
    if (!$noteId) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'note_id required']);
        exit;
    }
    $pdo->prepare("DELETE FROM admin_notes WHERE id = ?")->execute([$noteId]);
    auditLog($pdo, 'admin_note_delete', 'admin_note', $noteId);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action']);
