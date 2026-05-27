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

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$patientId = (int)($_POST['patient_id'] ?? 0);
$formType = (string)($_POST['form_type'] ?? '');
$visitId = (int)($_POST['visit_id'] ?? 0);
$draftSubmissionId = (int)($_POST['draft_submission_id'] ?? 0);
$formDataRaw = (string)($_POST['form_data'] ?? '');

$allowed = ['vital_cs', 'new_patient', 'abn', 'pf_signup', 'ccm_consent', 'cognitive_wellness', 'medicare_awv', 'il_disclosure', 'wound_care_consent', 'informed_consent_wound', 'rpm_consent', 'new_patient_pocket', 'new_patient_pocket_pc'];
if ($patientId <= 0 || !in_array($formType, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid draft payload']);
    exit;
}

$decoded = json_decode($formDataRaw, true);
if (!is_array($decoded)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Malformed form_data payload']);
    exit;
}

$patientCheck = $pdo->prepare('SELECT id FROM patients WHERE id = ? LIMIT 1');
$patientCheck->execute([$patientId]);
if (!$patientCheck->fetchColumn()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Patient not found']);
    exit;
}

$excludeKeys = ['csrf_token', 'patient_id', 'form_type', 'patient_signature', 'ma_signature', 'poa_name', 'poa_relationship', 'med_count', '__ts', 'draft_submission_id'];
$formData = [];
foreach ($decoded as $key => $value) {
    if (in_array($key, $excludeKeys, true)) {
        continue;
    }
    $formData[$key] = is_array($value) ? $value : trim((string)$value);
}

$staffId = (int)$_SESSION['user_id'];
$poaName = trim((string)($decoded['poa_name'] ?? ''));
$poaRel = trim((string)($decoded['poa_relationship'] ?? ''));

$patientSig = (string)($decoded['patient_signature'] ?? '');
$maSig = (string)($decoded['ma_signature'] ?? '');
if ($patientSig && !preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $patientSig)) {
    $patientSig = '';
}
if ($maSig && !preg_match('/^data:image\/png;base64,[A-Za-z0-9+\/=]+$/', $maSig)) {
    $maSig = '';
}

$targetId = 0;

if ($draftSubmissionId > 0) {
    $ownDraft = $pdo->prepare('SELECT id FROM form_submissions WHERE id = ? AND patient_id = ? AND form_type = ? AND ma_id = ? AND status = ? LIMIT 1');
    $ownDraft->execute([$draftSubmissionId, $patientId, $formType, $staffId, 'draft']);
    $targetId = (int)($ownDraft->fetchColumn() ?: 0);
}

if ($targetId === 0 && $visitId > 0) {
    try {
        $q = $pdo->prepare("SELECT id
                            FROM form_submissions
                            WHERE patient_id = ?
                              AND form_type = ?
                              AND ma_id = ?
                              AND status = 'draft'
                              AND JSON_UNQUOTE(JSON_EXTRACT(form_data, '$.visit_id')) = ?
                            ORDER BY id DESC
                            LIMIT 1");
        $q->execute([$patientId, $formType, $staffId, (string)$visitId]);
        $targetId = (int)($q->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        $targetId = 0;
    }
}

if ($targetId === 0) {
    $q = $pdo->prepare("SELECT id
                        FROM form_submissions
                        WHERE patient_id = ? AND form_type = ? AND ma_id = ? AND status = 'draft'
                        ORDER BY id DESC
                        LIMIT 1");
    $q->execute([$patientId, $formType, $staffId]);
    $targetId = (int)($q->fetchColumn() ?: 0);
}

if ($targetId > 0) {
    $upd = $pdo->prepare('UPDATE form_submissions
                          SET form_data = ?,
                              patient_signature = ?,
                              ma_signature = ?,
                              poa_name = ?,
                              poa_relationship = ?
                          WHERE id = ?');
    $upd->execute([
        json_encode($formData, JSON_UNESCAPED_UNICODE),
        $patientSig ?: null,
        $maSig ?: null,
        $poaName ?: null,
        $poaRel ?: null,
        $targetId,
    ]);
} else {
    $ins = $pdo->prepare('INSERT INTO form_submissions
                          (patient_id, form_type, form_data, patient_signature, ma_signature, poa_name, poa_relationship, ma_id, status, signed_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)');
    $ins->execute([
        $patientId,
        $formType,
        json_encode($formData, JSON_UNESCAPED_UNICODE),
        $patientSig ?: null,
        $maSig ?: null,
        $poaName ?: null,
        $poaRel ?: null,
        $staffId,
        'draft',
    ]);
    $targetId = (int)$pdo->lastInsertId();
}

echo json_encode([
    'ok' => true,
    'draft_id' => $targetId,
    'saved_at' => date('c'),
]);
