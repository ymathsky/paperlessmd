<?php
/**
 * GET /api/mobile/patient.php?id=X   – single patient detail + forms + photos
 */
require_once __DIR__ . '/helpers.php';
header('Content-Type: application/json');
cors();

try {

$user = requireToken();
$id   = (int)($_GET['id'] ?? 0);
if (!$id) jsonError('id required', 422);

$pStmt = $pdo->prepare("SELECT * FROM patients WHERE id = ? LIMIT 1");
$pStmt->execute([$id]);
$patient = $pStmt->fetch(\PDO::FETCH_ASSOC);
if (!$patient) jsonError('Patient not found', 404);

// Forms list
$fStmt = $pdo->prepare("
    SELECT fs.id, fs.form_type, fs.status, fs.created_at,
           s.full_name AS ma_name
    FROM form_submissions fs
    LEFT JOIN staff s ON s.id = fs.ma_id
    WHERE fs.patient_id = ?
    ORDER BY fs.created_at DESC
");
$fStmt->execute([$id]);

// Photos
$phStmt = $pdo->prepare("
    SELECT id, file_path, note, created_at FROM wound_photos WHERE patient_id = ? ORDER BY created_at DESC
");
$phStmt->execute([$id]);

jsonOk([
    'patient' => $patient,
    'forms'   => $fStmt->fetchAll(\PDO::FETCH_ASSOC),
    'photos'  => $phStmt->fetchAll(\PDO::FETCH_ASSOC),
]);

} catch (\Throwable $e) {
    jsonError('patient.php error: ' . $e->getMessage(), 500);
}
