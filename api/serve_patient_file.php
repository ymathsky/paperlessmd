<?php
/**
 * api/serve_patient_file.php
 * Serve a patient file inline for browser rendering.
 *
 * GET ?id=<file_id>
 * Sends Content-Disposition: inline so the browser renders rather than downloads.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid file ID');
}

$row = $pdo->prepare("SELECT * FROM patient_files WHERE id = ?");
$row->execute([$id]);
$file = $row->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    http_response_code(404);
    exit('File not found');
}

$path = dirname(__DIR__) . '/uploads/patient_files/' . $file['filename'];
if (!file_exists($path)) {
    http_response_code(404);
    exit('File missing from disk');
}

$safeName = preg_replace('/[^\w\.\-]/', '_', $file['original_name']);
$disposition = isset($_GET['dl']) ? 'attachment' : 'inline';

header('Content-Type: application/pdf');
header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

readfile($path);
exit;
