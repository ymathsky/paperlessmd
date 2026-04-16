<?php
/**
 * api/save_care_note.php
 *
 * CRUD for internal care coordination notes (not visible to patients).
 *
 * POST JSON actions:
 *   'create' → { csrf, patient_id, body, parent_id? }
 *   'edit'   → { csrf, id, body }
 *   'delete' → { csrf, id, patient_id }
 *   'pin'    → { csrf, id, patient_id, pinned: 0|1 }
 *
 * Authorization:
 *   - All clinical roles (admin + ma) can create / reply
 *   - Authors can edit own notes; admins can edit any
 *   - Authors can delete own notes; admins can delete any
 *   - Only admins can pin/unpin
 *
 * Response: { ok, id? }
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/audit.php';

requireNotBillingApi();
if (!canAccessClinical()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

if (!verifyCsrf($body['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$action    = $body['action']    ?? '';
$userId    = (int)($_SESSION['user_id'] ?? 0);

// ── CREATE ──────────────────────────────────────────────────────────────────
if ($action === 'create') {
    $patientId = (int)($body['patient_id'] ?? 0);
    $parentId  = isset($body['parent_id']) && $body['parent_id'] ? (int)$body['parent_id'] : null;
    $noteBody  = trim($body['body'] ?? '');

    if (!$patientId || $noteBody === '') {
        echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
        exit;
    }
    if (mb_strlen($noteBody) > 5000) {
        echo json_encode(['ok' => false, 'error' => 'Note exceeds 5000 characters']);
        exit;
    }

    // Validate patient exists
    $ps = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
    $ps->execute([$patientId]);
    if (!$ps->fetch()) {
        echo json_encode(['ok' => false, 'error' => 'Patient not found']);
        exit;
    }

    // Validate parent exists and belongs to same patient
    if ($parentId !== null) {
        $pp = $pdo->prepare("SELECT id FROM care_notes WHERE id = ? AND patient_id = ?");
        $pp->execute([$parentId, $patientId]);
        if (!$pp->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Invalid parent note']);
            exit;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO care_notes (patient_id, parent_id, author_id, body)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$patientId, $parentId, $userId, $noteBody]);
    $newId = (int)$pdo->lastInsertId();

    auditLog($pdo, 'care_note_add', 'patient', $patientId, null, 'note_id=' . $newId . ($parentId ? ' reply_to=' . $parentId : ''));

    echo json_encode(['ok' => true, 'id' => $newId]);
    exit;
}

// ── EDIT ────────────────────────────────────────────────────────────────────
if ($action === 'edit') {
    $noteId   = (int)($body['id']   ?? 0);
    $noteBody = trim($body['body']  ?? '');

    if (!$noteId || $noteBody === '') {
        echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
        exit;
    }
    if (mb_strlen($noteBody) > 5000) {
        echo json_encode(['ok' => false, 'error' => 'Note exceeds 5000 characters']);
        exit;
    }

    $ns = $pdo->prepare("SELECT id, patient_id, author_id FROM care_notes WHERE id = ?");
    $ns->execute([$noteId]);
    $note = $ns->fetch();
    if (!$note) {
        echo json_encode(['ok' => false, 'error' => 'Note not found']);
        exit;
    }

    // Only author or admin may edit
    if ((int)$note['author_id'] !== $userId && !isAdmin()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'You cannot edit this note']);
        exit;
    }

    $pdo->prepare("UPDATE care_notes SET body = ?, edited_at = NOW() WHERE id = ?")
        ->execute([$noteBody, $noteId]);

    auditLog($pdo, 'care_note_edit', 'patient', (int)$note['patient_id'], null, 'note_id=' . $noteId);

    echo json_encode(['ok' => true, 'id' => $noteId]);
    exit;
}

// ── DELETE ───────────────────────────────────────────────────────────────────
if ($action === 'delete') {
    $noteId    = (int)($body['id']          ?? 0);
    $patientId = (int)($body['patient_id']  ?? 0);

    if (!$noteId) {
        echo json_encode(['ok' => false, 'error' => 'Missing note id']);
        exit;
    }

    $ns = $pdo->prepare("SELECT id, patient_id, author_id FROM care_notes WHERE id = ?");
    $ns->execute([$noteId]);
    $note = $ns->fetch();
    if (!$note) {
        echo json_encode(['ok' => false, 'error' => 'Note not found']);
        exit;
    }

    if ((int)$note['author_id'] !== $userId && !isAdmin()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'You cannot delete this note']);
        exit;
    }

    // Deletes replies via ON DELETE CASCADE
    $pdo->prepare("DELETE FROM care_notes WHERE id = ?")->execute([$noteId]);

    auditLog($pdo, 'care_note_delete', 'patient', (int)$note['patient_id'], null, 'note_id=' . $noteId);

    echo json_encode(['ok' => true]);
    exit;
}

// ── PIN / UNPIN ───────────────────────────────────────────────────────────────
if ($action === 'pin') {
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Only admins can pin notes']);
        exit;
    }

    $noteId = (int)($body['id']     ?? 0);
    $pinned = (int)(bool)($body['pinned'] ?? 0);

    if (!$noteId) {
        echo json_encode(['ok' => false, 'error' => 'Missing note id']);
        exit;
    }

    $ns = $pdo->prepare("SELECT id, patient_id FROM care_notes WHERE id = ?");
    $ns->execute([$noteId]);
    $note = $ns->fetch();
    if (!$note) {
        echo json_encode(['ok' => false, 'error' => 'Note not found']);
        exit;
    }

    $pdo->prepare("UPDATE care_notes SET pinned = ? WHERE id = ?")->execute([$pinned, $noteId]);

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action']);
