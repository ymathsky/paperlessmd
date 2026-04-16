<?php
/**
 * api/amend_form.php
 *
 * Admin-only endpoint to amend (correct) form_data on an existing signed/uploaded
 * form submission. Before updating, the current form_data is snapshotted into
 * form_versions so no history is ever lost.
 *
 * POST JSON: { csrf, id, form_data (object), amendment_note (string) }
 * Response:  { ok, version_num }
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/audit.php';
requireAdmin();

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

$submissionId   = (int)($body['id']        ?? 0);
$newData        = $body['form_data']        ?? null;
$amendmentNote  = trim($body['amendment_note'] ?? '');

if (!$submissionId || !is_array($newData)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
    exit;
}

// Load existing submission
$chk = $pdo->prepare("SELECT id, form_type, form_data, status FROM form_submissions WHERE id = ?");
$chk->execute([$submissionId]);
$sub = $chk->fetch();

if (!$sub) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Submission not found']);
    exit;
}

if ($sub['status'] === 'draft') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Cannot amend a draft form']);
    exit;
}

// Determine next version number for this submission
$vChk = $pdo->prepare("SELECT COALESCE(MAX(version_num), 0) FROM form_versions WHERE submission_id = ?");
$vChk->execute([$submissionId]);
$nextVersion = (int)$vChk->fetchColumn() + 1;

$pdo->beginTransaction();
try {
    // Snapshot current form_data into form_versions
    $snap = $pdo->prepare("
        INSERT INTO form_versions (submission_id, version_num, form_data, amended_by, amendment_note)
        VALUES (?, ?, ?, ?, ?)
    ");
    $snap->execute([
        $submissionId,
        $nextVersion,
        $sub['form_data'],          // current (pre-amendment) data
        (int)$_SESSION['user_id'],
        $amendmentNote ?: null,
    ]);

    // Sanitise new data — same exclusion list as save_form.php
    $excludeKeys = ['csrf_token', 'patient_id', 'form_type', 'patient_signature', 'ma_signature', 'poa_name', 'poa_relationship'];
    $clean = [];
    foreach ($newData as $k => $v) {
        if (!in_array($k, $excludeKeys, true)) {
            $clean[$k] = is_array($v) ? $v : trim((string)$v);
        }
    }

    // Update the main row with amended data
    $upd = $pdo->prepare("UPDATE form_submissions SET form_data = ?, updated_at = NOW() WHERE id = ?");
    $upd->execute([json_encode($clean, JSON_UNESCAPED_UNICODE), $submissionId]);

    $pdo->commit();

    auditLog($pdo, 'form_amend', 'form', $submissionId,
        'v' . $nextVersion . ' — ' . $sub['form_type'] .
        ($amendmentNote ? ' (' . $amendmentNote . ')' : ''));

    echo json_encode(['ok' => true, 'version_num' => $nextVersion]);

} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Database error']);
}
