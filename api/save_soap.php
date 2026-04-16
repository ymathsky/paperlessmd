<?php
/**
 * api/save_soap.php
 *
 * Create, update, or delete a SOAP note.
 *
 * POST JSON:
 *   action  = 'save'   → { csrf, id?, patient_id, visit_id?, note_date, subjective, objective, assessment, plan, status }
 *   action  = 'delete' → { csrf, id, patient_id }
 *
 * Response: { ok, id }
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
$noteId    = (int)($body['id']  ?? 0);
$patientId = (int)($body['patient_id'] ?? 0);

if (!$patientId) {
    echo json_encode(['ok' => false, 'error' => 'Missing patient_id']);
    exit;
}

// Verify patient exists
$chk = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
$chk->execute([$patientId]);
if (!$chk->fetch()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Patient not found']);
    exit;
}

/* ── DELETE ──────────────────────────────────────────────────────────────── */
if ($action === 'delete') {
    if (!$noteId) {
        echo json_encode(['ok' => false, 'error' => 'Missing note id']);
        exit;
    }

    // Load note to verify ownership / finalized status
    $nChk = $pdo->prepare("SELECT id, patient_id, author_id, status FROM soap_notes WHERE id = ?");
    $nChk->execute([$noteId]);
    $existing = $nChk->fetch();

    if (!$existing || (int)$existing['patient_id'] !== $patientId) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Note not found']);
        exit;
    }

    // Only admin can delete finalized notes; non-admin can only delete their own drafts
    if ($existing['status'] === 'final' && !isAdmin()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Cannot delete a finalized note']);
        exit;
    }
    if (!isAdmin() && (int)$existing['author_id'] !== (int)$_SESSION['user_id']) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Not authorized to delete this note']);
        exit;
    }

    $pdo->prepare("DELETE FROM soap_notes WHERE id = ?")->execute([$noteId]);
    auditLog($pdo, 'soap_delete', 'patient', $patientId, null, 'note_id=' . $noteId);

    echo json_encode(['ok' => true]);
    exit;
}

/* ── SAVE (create or update) ─────────────────────────────────────────────── */
if ($action !== 'save') {
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    exit;
}

// Validate date
$noteDate = trim($body['note_date'] ?? '');
if (!$noteDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $noteDate)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid note date']);
    exit;
}

$status   = in_array($body['status'] ?? '', ['draft', 'final'], true) ? $body['status'] : 'draft';
$visitId  = ($body['visit_id'] ?? null) ? (int)$body['visit_id'] : null;

// Sanitise text fields (strip nulls, trim)
$subjective = trim($body['subjective'] ?? '') ?: null;
$objective  = trim($body['objective']  ?? '') ?: null;
$assessment = trim($body['assessment'] ?? '') ?: null;
$plan       = trim($body['plan']       ?? '') ?: null;

$finalizedAt = ($status === 'final') ? date('Y-m-d H:i:s') : null;

if ($noteId) {
    // ── Update existing note ─────────────────────────────────────────────────
    $nChk = $pdo->prepare("SELECT id, patient_id, author_id, status FROM soap_notes WHERE id = ?");
    $nChk->execute([$noteId]);
    $existing = $nChk->fetch();

    if (!$existing || (int)$existing['patient_id'] !== $patientId) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Note not found']);
        exit;
    }

    // Non-admin cannot edit finalized notes
    if ($existing['status'] === 'final' && !isAdmin()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Note is finalized and cannot be edited']);
        exit;
    }

    // Preserve original finalized_at when re-finalizing
    if ($status === 'final' && !$existing['finalized_at']) {
        $finalizedAt = date('Y-m-d H:i:s');
    } elseif ($status === 'final') {
        $finalizedAt = $existing['finalized_at'];
    } else {
        $finalizedAt = null; // re-opened draft
    }

    $stmt = $pdo->prepare("
        UPDATE soap_notes
        SET note_date    = ?,
            subjective   = ?,
            objective    = ?,
            assessment   = ?,
            plan         = ?,
            status       = ?,
            finalized_at = ?,
            visit_id     = ?,
            updated_at   = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        $noteDate, $subjective, $objective, $assessment, $plan,
        $status, $finalizedAt, $visitId,
        $noteId,
    ]);

    auditLog($pdo, $status === 'final' ? 'soap_finalize' : 'soap_edit', 'patient', $patientId, null, 'note_id=' . $noteId);
    echo json_encode(['ok' => true, 'id' => $noteId]);

} else {
    // ── Insert new note ──────────────────────────────────────────────────────
    $stmt = $pdo->prepare("
        INSERT INTO soap_notes
            (patient_id, visit_id, note_date, subjective, objective, assessment, plan, author_id, status, finalized_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $patientId, $visitId, $noteDate,
        $subjective, $objective, $assessment, $plan,
        (int)$_SESSION['user_id'],
        $status, $finalizedAt,
    ]);
    $newId = (int)$pdo->lastInsertId();

    auditLog($pdo, $status === 'final' ? 'soap_finalize' : 'soap_create', 'patient', $patientId, null, 'note_id=' . $newId);
    echo json_encode(['ok' => true, 'id' => $newId]);
}
