<?php
/**
 * api/diagnoses.php
 * CRUD for patient diagnoses (ICD-10 codes).
 *
 * GET  ?patient_id=N            → list diagnoses for patient
 * POST {action:'add',    patient_id, icd_code, icd_desc, notes, csrf}
 * POST {action:'remove', patient_id, id, csrf}
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireNotBillingApi();

header('Content-Type: application/json');

$userId = (int)$_SESSION['user_id'];

// ── GET ────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $patientId = (int)($_GET['patient_id'] ?? 0);
    if (!$patientId) { echo json_encode(['ok'=>false,'error'=>'Missing patient_id']); exit; }

    $stmt = $pdo->prepare("
        SELECT pd.*, s.full_name AS added_by_name
        FROM patient_diagnoses pd
        LEFT JOIN staff s ON s.id = pd.added_by
        WHERE pd.patient_id = ?
        ORDER BY pd.added_at DESC
    ");
    $stmt->execute([$patientId]);
    echo json_encode(['ok'=>true,'diagnoses'=>$stmt->fetchAll()]);
    exit;
}

// ── POST ───────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
    exit;
}

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true) ?: $_POST;

$csrf = $input['csrf'] ?? $input['csrf_token'] ?? '';
if (!verifyCsrf($csrf)) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Invalid CSRF token']);
    exit;
}

$action    = $input['action'] ?? '';
$patientId = (int)($input['patient_id'] ?? 0);

if (!$patientId) { echo json_encode(['ok'=>false,'error'=>'Missing patient_id']); exit; }

// Verify patient exists
$chk = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
$chk->execute([$patientId]);
if (!$chk->fetch()) { echo json_encode(['ok'=>false,'error'=>'Patient not found']); exit; }

if ($action === 'add') {
    $code  = strtoupper(trim($input['icd_code'] ?? ''));
    $desc  = trim($input['icd_desc'] ?? '');
    $notes = substr(trim($input['notes'] ?? ''), 0, 500);

    if (!$code || !$desc) { echo json_encode(['ok'=>false,'error'=>'Code and description required']); exit; }
    if (!preg_match('/^[A-Z][0-9A-Z]{2,6}(\.[0-9A-Z]{1,4})?$/i', $code)) {
        echo json_encode(['ok'=>false,'error'=>'Invalid ICD-10 code format']); exit;
    }

    // Prevent duplicate code for same patient
    $dup = $pdo->prepare("SELECT id FROM patient_diagnoses WHERE patient_id=? AND icd_code=?");
    $dup->execute([$patientId, $code]);
    if ($dup->fetch()) { echo json_encode(['ok'=>false,'error'=>'This code is already on file for this patient']); exit; }

    $ins = $pdo->prepare("
        INSERT INTO patient_diagnoses (patient_id, icd_code, icd_desc, added_by, notes)
        VALUES (?, ?, ?, ?, ?)
    ");
    $ins->execute([$patientId, $code, $desc, $userId, $notes]);
    $newId = $pdo->lastInsertId();

    $row = $pdo->prepare("
        SELECT pd.*, s.full_name AS added_by_name
        FROM patient_diagnoses pd LEFT JOIN staff s ON s.id = pd.added_by
        WHERE pd.id = ?
    ");
    $row->execute([$newId]);
    echo json_encode(['ok'=>true,'diagnosis'=>$row->fetch()]);
    exit;
}

if ($action === 'remove') {
    $diagId = (int)($input['id'] ?? 0);
    if (!$diagId) { echo json_encode(['ok'=>false,'error'=>'Missing id']); exit; }

    // Only admin/provider can remove; MA can remove their own additions
    $del = $pdo->prepare("SELECT added_by FROM patient_diagnoses WHERE id=? AND patient_id=?");
    $del->execute([$diagId, $patientId]);
    $row = $del->fetch();
    if (!$row) { echo json_encode(['ok'=>false,'error'=>'Not found']); exit; }

    if (!isAdmin() && (int)$row['added_by'] !== $userId) {
        echo json_encode(['ok'=>false,'error'=>'Permission denied']); exit;
    }

    $pdo->prepare("DELETE FROM patient_diagnoses WHERE id=?")->execute([$diagId]);
    echo json_encode(['ok'=>true]);
    exit;
}

echo json_encode(['ok'=>false,'error'=>'Unknown action']);
