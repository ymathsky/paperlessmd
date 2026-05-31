<?php
/**
 * api/upload_patient_file.php
 * Upload a PDF (or other file) and attach it to a patient.
 *
 * POST multipart/form-data:
 *   csrf        - CSRF token
 *   patient_id  - int
 *   category    - string (default: 'medication')
 *   file        - uploaded file
 *
 * Returns JSON: { ok, id, filename, original_name, url, uploaded_at }
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!verifyCsrf($_POST['csrf'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$patientId = (int)($_POST['patient_id'] ?? 0);
if ($patientId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid patient_id']);
    exit;
}

// Verify patient exists
$ps = $pdo->prepare("SELECT id FROM patients WHERE id = ?");
$ps->execute([$patientId]);
if (!$ps->fetch()) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Patient not found']);
    exit;
}

$category = preg_replace('/[^a-z0-9_]/', '', strtolower($_POST['category'] ?? 'medication'));
if ($category === '') $category = 'medication';

// File present?
if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['file']['error'] ?? -1;
    echo json_encode(['ok' => false, 'error' => 'Upload error code: ' . $code]);
    exit;
}

$file    = $_FILES['file'];
$maxSize = 20 * 1024 * 1024; // 20 MB
if ($file['size'] > $maxSize) {
    echo json_encode(['ok' => false, 'error' => 'File too large (max 20 MB)']);
    exit;
}

// Accept PDFs only
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($file['tmp_name']);
$allowedMimes = ['application/pdf', 'application/x-pdf'];
if (!in_array($mime, $allowedMimes, true)) {
    echo json_encode(['ok' => false, 'error' => 'Only PDF files are accepted']);
    exit;
}

// Storage directory
$uploadDir = dirname(__DIR__) . '/uploads/patient_files/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$originalName = basename($file['name']);
$ext      = 'pdf';
$filename = 'pt' . $patientId . '_' . $category . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destPath = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['ok' => false, 'error' => 'Failed to save file']);
    exit;
}

// Record in DB
$stmt = $pdo->prepare("
    INSERT INTO patient_files (patient_id, filename, original_name, category, uploaded_by)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->execute([$patientId, $filename, $originalName, $category, (int)$_SESSION['user_id']]);
$newId = (int)$pdo->lastInsertId();

$now = date('Y-m-d H:i:s');
echo json_encode([
    'ok'            => true,
    'id'            => $newId,
    'filename'      => $filename,
    'original_name' => $originalName,
    'url'           => BASE_URL . '/uploads/patient_files/' . rawurlencode($filename),
    'uploaded_at'   => $now,
]);
