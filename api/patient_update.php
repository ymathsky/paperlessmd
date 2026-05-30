<?php
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
$input = json_decode($raw, true);
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

// CSRF check
$csrfField = $input['csrf'] ?? $input['csrf_token'] ?? '';
if (!verifyCsrf($csrfField)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$patientId = (int)($input['patient_id'] ?? 0);
if (!$patientId) {
    echo json_encode(['ok' => false, 'error' => 'Missing patient_id']);
    exit;
}

// Verify patient exists and fetch current photos
$chk = $pdo->prepare("SELECT id, insurance_photo, insurance_photo_back, sss_photo FROM patients WHERE id = ?");
$chk->execute([$patientId]);
$existing = $chk->fetch();
if (!$existing) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Patient not found']);
    exit;
}

// Validate required fields
$firstName = trim($input['first_name'] ?? '');
$lastName  = trim($input['last_name']  ?? '');
if (!$firstName || !$lastName) {
    echo json_encode(['ok' => false, 'error' => 'First and last name are required.']);
    exit;
}

// Sanitize/validate scalar fields
$allowedStatuses = ['active', 'inactive', 'discharged'];
$status = in_array($input['status'] ?? '', $allowedStatuses, true) ? $input['status'] : 'active';

$dischargedAt = null;
if ($status === 'discharged' && !empty($input['discharged_at'])) {
    $da = trim($input['discharged_at']);
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $da)) $dischargedAt = $da;
}

$dob = trim($input['dob'] ?? '');
if ($dob && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) $dob = '';

$allowedRaces = [
    '', 'American Indian or Alaska Native', 'Asian', 'Black or African American',
    'Hispanic or Latino', 'Native Hawaiian or Other Pacific Islander',
    'White / Caucasian', 'Two or More Races', 'Other', 'Unknown / Declined to State',
];
$race = in_array($input['race'] ?? '', $allowedRaces, true) ? ($input['race'] ?? '') : '';

// Assigned MA / Provider (admin only)
$assignedMa       = isAdmin() ? ((int)($input['assigned_ma'] ?? 0) ?: null) : null;
$assignedProvider = isAdmin() ? trim($input['assigned_provider'] ?? '') : null;

// Photo handling — keep existing unless new base64 supplied or explicit removal
function resolvePhoto(array $input, string $field, ?string $existing): ?string
{
    if (!empty($input['remove_' . $field])) return null;
    $raw = trim($input[$field] ?? '');
    if ($raw && preg_match('/^data:image\/(jpeg|png|webp|gif);base64,[A-Za-z0-9+\/=]+$/', $raw)) {
        return $raw;
    }
    return $existing; // keep existing
}

$insPhoto     = resolvePhoto($input, 'insurance_photo',      $existing['insurance_photo']);
$insPhotoBack = resolvePhoto($input, 'insurance_photo_back', $existing['insurance_photo_back']);
$sssPhoto     = resolvePhoto($input, 'sss_photo',            $existing['sss_photo']);

// Build SET clause — only update assigned_ma/provider if admin
$sql = "UPDATE patients
        SET first_name=?, last_name=?, dob=?, phone=?, email=?, address=?,
            insurance=?, insurance_id=?, pcp=?, race=?,
            pharmacy_name=?, pharmacy_phone=?, pharmacy_address=?,
            insurance_photo=?, insurance_photo_back=?, sss_photo=?,
            status=?, discharged_at=?";
$params = [
    $firstName,
    $lastName,
    $dob ?: null,
    trim($input['phone'] ?? ''),
    trim($input['email'] ?? ''),
    trim($input['address'] ?? ''),
    trim($input['insurance'] ?? ''),
    trim($input['insurance_id'] ?? ''),
    trim($input['pcp'] ?? ''),
    $race,
    trim($input['pharmacy_name'] ?? ''),
    trim($input['pharmacy_phone'] ?? ''),
    trim($input['pharmacy_address'] ?? ''),
    $insPhoto,
    $insPhotoBack,
    $sssPhoto,
    $status,
    $dischargedAt,
];

if (isAdmin()) {
    $sql .= ', assigned_ma=?, assigned_provider=?';
    $params[] = $assignedMa;
    $params[] = $assignedProvider ?: null;
}

$sql .= ' WHERE id=?';
$params[] = $patientId;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

auditLog($pdo, 'patient_edit', 'patient', $patientId, $firstName . ' ' . $lastName);

// Fetch updated row (with assigned MA name) to return to client
$updated = $pdo->prepare("
    SELECT p.*, ma.full_name AS assigned_ma_name
    FROM patients p
    LEFT JOIN staff ma ON ma.id = p.assigned_ma
    WHERE p.id = ?
");
$updated->execute([$patientId]);
$pt = $updated->fetch(PDO::FETCH_ASSOC);

echo json_encode(['ok' => true, 'patient' => $pt]);
