<?php
/**
 * POST /api/mobile/upload_photo.php
 * Multipart form-data: patient_id, note, photo (file)
 */
require_once __DIR__ . '/helpers.php';
cors();

header('Content-Type: application/json');
$user = requireToken();

$patientId = (int)($_POST['patient_id'] ?? 0);
if (!$patientId) jsonError('patient_id required', 422);

if (empty($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    jsonError('photo file required', 422);
}

$allowed = ['image/jpeg', 'image/png', 'image/webp'];
$finfo   = new finfo(FILEINFO_MIME_TYPE);
$mime    = $finfo->file($_FILES['photo']['tmp_name']);
if (!in_array($mime, $allowed, true)) jsonError('Only JPEG/PNG/WEBP allowed', 422);

$ext     = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
$dir     = __DIR__ . '/../../uploads/photos/';
if (!is_dir($dir)) mkdir($dir, 0775, true);
$filename = 'ph_' . $patientId . '_' . uniqid() . '.' . $ext;
$dest    = $dir . $filename;

if (!move_uploaded_file($_FILES['photo']['tmp_name'], $dest)) jsonError('Upload failed', 500);

$note = trim($_POST['note'] ?? '');
$stmt = $pdo->prepare("INSERT INTO wound_photos (patient_id, ma_id, file_path, note, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->execute([$patientId, $user['id'], 'uploads/photos/' . $filename, $note]);

jsonOk(['photo_id' => $pdo->lastInsertId(), 'file_path' => 'uploads/photos/' . $filename], 201);
