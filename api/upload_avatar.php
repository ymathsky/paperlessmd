<?php
/**
 * api/upload_avatar.php
 * Upload or delete the logged-in user's profile avatar.
 *
 * POST multipart/form-data:
 *   action=upload  + avatar (file, image/jpeg|png|gif|webp, max 5MB)
 *   action=delete
 *
 * CSRF token must be in POST field: csrf
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

$userId = (int)$_SESSION['user_id'];
$action = $_POST['action'] ?? 'upload';

// ── DELETE ───────────────────────────────────────────────────────────────────
if ($action === 'delete') {
    $row = $pdo->prepare("SELECT avatar_url FROM staff WHERE id = ?");
    $row->execute([$userId]);
    $existing = $row->fetchColumn();
    if ($existing) {
        $serverPath = __DIR__ . '/../' . ltrim($existing, '/');
        if (file_exists($serverPath)) {
            @unlink($serverPath);
        }
    }
    $pdo->prepare("UPDATE staff SET avatar_url = NULL WHERE id = ?")->execute([$userId]);
    echo json_encode(['ok' => true]);
    exit;
}

// ── UPLOAD ───────────────────────────────────────────────────────────────────
$file = $_FILES['avatar'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    $errMsg = [
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form limit.',
        UPLOAD_ERR_PARTIAL    => 'File only partially uploaded.',
        UPLOAD_ERR_NO_FILE    => 'No file uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temp folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file.',
        UPLOAD_ERR_EXTENSION  => 'Upload blocked by extension.',
    ];
    $code = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $errMsg[$code] ?? 'Upload error.']);
    exit;
}

// Size limit: 5 MB
if ($file['size'] > 5 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Image must be under 5 MB.']);
    exit;
}

// Verify it is a real image and get MIME type
$imageInfo = @getimagesize($file['tmp_name']);
if (!$imageInfo) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid image file.']);
    exit;
}

$allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$mime = $imageInfo['mime'];
if (!in_array($mime, $allowedMimes, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Only JPEG, PNG, GIF and WebP allowed.']);
    exit;
}

// Load source image
switch ($mime) {
    case 'image/jpeg': $src = @imagecreatefromjpeg($file['tmp_name']); break;
    case 'image/png':  $src = @imagecreatefrompng($file['tmp_name']);  break;
    case 'image/gif':  $src = @imagecreatefromgif($file['tmp_name']);  break;
    case 'image/webp': $src = @imagecreatefromwebp($file['tmp_name']); break;
    default:           $src = false;
}

if (!$src) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Could not process image.']);
    exit;
}

$origW = imagesx($src);
$origH = imagesy($src);

// Center-crop to square then scale to 240×240
$size   = min($origW, $origH);
$cropX  = (int)(($origW - $size) / 2);
$cropY  = (int)(($origH - $size) / 2);
$target = 240;

$dst = imagecreatetruecolor($target, $target);
// Preserve transparency for PNG
imagealphablending($dst, false);
imagesavealpha($dst, true);
$transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
imagefilledrectangle($dst, 0, 0, $target, $target, $transparent);

imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $target, $target, $size, $size);
imagedestroy($src);

// Delete old avatar if exists
$oldRow = $pdo->prepare("SELECT avatar_url FROM staff WHERE id = ?");
$oldRow->execute([$userId]);
$oldUrl = $oldRow->fetchColumn();
if ($oldUrl) {
    $oldPath = __DIR__ . '/../' . ltrim($oldUrl, '/');
    if (file_exists($oldPath)) {
        @unlink($oldPath);
    }
}

// Save as JPEG
$uploadDir = __DIR__ . '/../uploads/avatars/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$filename = $userId . '_' . time() . '.jpg';
$savePath  = $uploadDir . $filename;

if (!imagejpeg($dst, $savePath, 90)) {
    imagedestroy($dst);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Failed to save image.']);
    exit;
}
imagedestroy($dst);

$url = '/uploads/avatars/' . $filename;
$pdo->prepare("UPDATE staff SET avatar_url = ? WHERE id = ?")->execute([$url, $userId]);

echo json_encode(['ok' => true, 'url' => $url]);
