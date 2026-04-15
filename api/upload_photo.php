<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireNotBillingApi();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$patientId    = (int)($_POST['patient_id'] ?? 0);
$woundLocation = trim($_POST['wound_location'] ?? '');
$description  = trim($_POST['description'] ?? '');

if (!$patientId) {
    echo json_encode(['success' => false, 'error' => 'Invalid patient']);
    exit;
}

// Verify patient
$chk = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
$chk->execute([$patientId]);
if (!$chk->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Patient not found']);
    exit;
}

$file = $_FILES['photo'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No valid file uploaded']);
    exit;
}

// Validate file type by MIME (not just extension)
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$finfo        = new finfo(FILEINFO_MIME_TYPE);
$mime         = $finfo->file($file['tmp_name']);
if (!in_array($mime, $allowedMimes, true)) {
    echo json_encode(['success' => false, 'error' => 'Only JPEG, PNG, WebP, or GIF images are allowed']);
    exit;
}

// Validate file size (max 15MB)
if ($file['size'] > 15 * 1024 * 1024) {
    echo json_encode(['success' => false, 'error' => 'File exceeds 15 MB limit']);
    exit;
}

// Generate safe filename
$ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime];
$filename = 'p' . $patientId . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destPath = UPLOAD_DIR . $filename;

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save file']);
    exit;
}

// Save to DB
$stmt = $pdo->prepare("INSERT INTO wound_photos
    (patient_id, filename, original_name, description, wound_location, uploaded_by)
    VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([
    $patientId,
    $filename,
    basename($file['name']),
    $description ?: null,
    $woundLocation ?: null,
    $_SESSION['user_id'],
]);

echo json_encode([
    'success'  => true,
    'filename' => $filename,
    'url'      => BASE_URL . '/uploads/photos/' . $filename,
]);
