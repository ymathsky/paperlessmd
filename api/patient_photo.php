<?php
/**
 * API: upload or remove a patient profile photo.
 * POST (multipart): action=upload, patient_id=N, csrf, photo file
 * POST (JSON):      action=remove, patient_id=N, csrf
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/audit.php';

header('Content-Type: application/json');

requireNotBilling();

$action = $_POST['action'] ?? (json_decode(file_get_contents('php://input'), true)['action'] ?? '');

// For JSON-decoded remove action
if ($action === '') {
    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? '';
    $csrf   = $input['csrf'] ?? '';
    $pid    = (int)($input['patient_id'] ?? 0);
} else {
    $csrf   = $_POST['csrf'] ?? '';
    $pid    = (int)($_POST['patient_id'] ?? 0);
}

if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}
if (!$pid) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'patient_id required']);
    exit;
}

// Verify patient exists
$patient = $pdo->prepare("SELECT id, photo_url FROM patients WHERE id = ?");
$patient->execute([$pid]);
$patient = $patient->fetch();
if (!$patient) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Patient not found']);
    exit;
}

$uploadDir = __DIR__ . '/../uploads/patient_photos/';

// ── Helper: delete old photo file ────────────────────────────────────────────
function deleteOldPhoto(?string $url, string $baseDir): void {
    if (!$url) return;
    // Extract filename from URL
    $filename = basename(parse_url($url, PHP_URL_PATH));
    $path = $baseDir . $filename;
    if (file_exists($path) && is_file($path)) {
        @unlink($path);
    }
}

// ── UPLOAD ───────────────────────────────────────────────────────────────────
if ($action === 'upload') {
    if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'No file uploaded or upload error']);
        exit;
    }

    $file     = $_FILES['photo'];
    $maxBytes = 5 * 1024 * 1024; // 5 MB

    if ($file['size'] > $maxBytes) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'File too large (max 5 MB)']);
        exit;
    }

    // Validate MIME by reading magic bytes
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!in_array($mimeType, $allowed, true)) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Invalid file type — JPG, PNG, WebP or GIF only']);
        exit;
    }

    $ext      = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mimeType];
    $filename = 'pt_' . $pid . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destPath = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not save file']);
        exit;
    }

    // Remove old photo
    deleteOldPhoto($patient['photo_url'], $uploadDir);

    $photoUrl = BASE_URL . '/uploads/patient_photos/' . $filename;

    $pdo->prepare("UPDATE patients SET photo_url = ? WHERE id = ?")->execute([$photoUrl, $pid]);
    auditLog($pdo, 'patient_photo_upload', 'patient', $pid);

    echo json_encode(['ok' => true, 'url' => $photoUrl]);
    exit;
}

// ── REMOVE ───────────────────────────────────────────────────────────────────
if ($action === 'remove') {
    deleteOldPhoto($patient['photo_url'], $uploadDir);
    $pdo->prepare("UPDATE patients SET photo_url = NULL WHERE id = ?")->execute([$pid]);
    auditLog($pdo, 'patient_photo_remove', 'patient', $pid);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action']);
