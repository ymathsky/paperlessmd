<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireNotBillingApi();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

// CSRF
if (!verifyCsrf($body['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$action = $body['action'] ?? '';

if ($action === 'status') {
    $id     = (int)($body['id'] ?? 0);
    $status = $body['status'] ?? '';
    $allowed = ['pending', 'en_route', 'completed', 'missed'];

    if (!$id || !in_array($status, $allowed, true)) {
        echo json_encode(['ok' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    // MAs can only update their own schedule entries; admins can update any
    if (isAdmin()) {
        $stmt = $pdo->prepare("UPDATE `schedule` SET status=? WHERE id=?");
        $stmt->execute([$status, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE `schedule` SET status=? WHERE id=? AND ma_id=?");
        $stmt->execute([$status, $id, $_SESSION['user_id']]);
    }

    if ($stmt->rowCount() === 0) {
        echo json_encode(['ok' => false, 'error' => 'Entry not found or not authorized']);
        exit;
    }

    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'save_note') {
    $id   = (int)($body['id'] ?? 0);
    $note = trim($body['visit_notes'] ?? '');

    if (!$id) {
        echo json_encode(['ok' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    if (isAdmin()) {
        $stmt = $pdo->prepare("UPDATE `schedule` SET visit_notes=? WHERE id=?");
        $stmt->execute([$note ?: null, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE `schedule` SET visit_notes=? WHERE id=? AND ma_id=?");
        $stmt->execute([$note ?: null, $id, $_SESSION['user_id']]);
    }

    if ($stmt->rowCount() === 0 && !$note) {
        // rowCount is 0 when value didn't change — still OK
    }

    echo json_encode(['ok' => true]);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Unknown action']);
