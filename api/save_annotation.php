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

$body = json_decode(file_get_contents('php://input'), true) ?? [];
if (!verifyCsrf($body['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$photoId   = (int)($body['photo_id']   ?? 0);
$imageData = $body['image_data'] ?? '';

if (!$photoId || !$imageData) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Missing parameters']);
    exit;
}

// Verify photo access
if (isAdmin()) {
    $stmt = $pdo->prepare("SELECT patient_id FROM wound_photos WHERE id = ?");
    $stmt->execute([$photoId]);
} else {
    $stmt = $pdo->prepare("SELECT patient_id FROM wound_photos WHERE id = ? AND uploaded_by = ?");
    $stmt->execute([$photoId, (int)$_SESSION['user_id']]);
}
$photo = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$photo) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Photo not found or not authorized']);
    exit;
}

// Decode and validate PNG
$base64 = preg_replace('/^data:image\/\w+;base64,/', '', $imageData);
$bytes  = base64_decode($base64, true);
if (!$bytes || strlen($bytes) < 8 || substr($bytes, 0, 4) !== "\x89PNG") {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Expected valid PNG image data']);
    exit;
}

$filename  = 'annotated_manual_p' . $photoId . '_' . time() . '.png';
$uploadDir = __DIR__ . '/../uploads/photos/';
$filepath  = $uploadDir . $filename;

if (file_put_contents($filepath, $bytes) === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'File write failed']);
    exit;
}

$relPath   = 'uploads/photos/' . $filename;
$staffId   = (int)($_SESSION['user_id'] ?? 0);
$patientId = (int)$photo['patient_id'];

// Upsert wound_measurements: update latest or insert new
$check = $pdo->prepare("SELECT id FROM wound_measurements WHERE photo_id = ? ORDER BY id DESC LIMIT 1");
$check->execute([$photoId]);
$existing = $check->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $pdo->prepare("UPDATE wound_measurements SET annotated_photo_path = ? WHERE id = ?")
        ->execute([$relPath, $existing['id']]);
} else {
    $pdo->prepare(
        "INSERT INTO wound_measurements (patient_id, photo_id, area_cm2, ruler_detected, annotated_photo_path, measured_at, wound_site, recorded_by)
         VALUES (?, ?, NULL, 0, ?, CURDATE(), 'Unspecified', ?)"
    )->execute([$patientId, $photoId, $relPath, $staffId]);
}

echo json_encode(['ok' => true, 'annotated_url' => $relPath]);
