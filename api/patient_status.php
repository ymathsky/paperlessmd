<?php
/**
 * api/patient_status.php
 * Update a patient's status (active / inactive / discharged) and optional discharge date.
 * POST {patient_id, status, discharged_at?, csrf}
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/audit.php';
requireNotBillingApi();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw   = file_get_contents('php://input');
$input = json_decode($raw, true) ?: $_POST;

$csrf = $input['csrf'] ?? $input['csrf_token'] ?? '';
if (!verifyCsrf($csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$patientId    = (int)($input['patient_id'] ?? 0);
$status       = $input['status'] ?? '';
$dischargedAt = trim($input['discharged_at'] ?? '');
$password     = $input['password'] ?? '';

if (!$patientId) {
    echo json_encode(['ok' => false, 'error' => 'Missing patient_id']);
    exit;
}

// Verify the submitting user's password
if ($password === '') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Password is required to change status']);
    exit;
}
$userRow = $pdo->prepare("SELECT password_hash FROM staff WHERE id = ?");
$userRow->execute([(int)($_SESSION['user_id'] ?? 0)]);
$userRow = $userRow->fetch();
if (!$userRow || !password_verify($password, $userRow['password_hash'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Incorrect password']);
    exit;
}

$allowed = ['active', 'inactive', 'discharged'];
if (!in_array($status, $allowed, true)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid status value']);
    exit;
}

// Validate discharge date if provided
if ($dischargedAt && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dischargedAt)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid discharge date format']);
    exit;
}

// Only set discharged_at when status is 'discharged'; clear it otherwise
if ($status !== 'discharged') {
    $dischargedAt = null;
}

// Verify patient exists
$chk = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
$chk->execute([$patientId]);
if (!$chk->fetch()) {
    echo json_encode(['ok' => false, 'error' => 'Patient not found']);
    exit;
}

$stmt = $pdo->prepare("UPDATE patients SET status = ?, discharged_at = ? WHERE id = ?");
$stmt->execute([$status, $dischargedAt ?: null, $patientId]);

// Fetch name for audit label
$nameRow = $pdo->prepare("SELECT first_name, last_name FROM patients WHERE id = ?");
$nameRow->execute([$patientId]);
$nameRow = $nameRow->fetch();
auditLog($pdo, 'patient_status', 'patient', $patientId,
    $nameRow ? ($nameRow['first_name'] . ' ' . $nameRow['last_name']) : null,
    'status=' . $status);

echo json_encode(['ok' => true, 'status' => $status, 'discharged_at' => $dischargedAt]);
