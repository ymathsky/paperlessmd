<?php
// Temporary upload diagnostic — DELETE after use
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

header('Content-Type: text/plain; charset=utf-8');

$dir = __DIR__ . '/uploads/message_files';

echo "=== Upload Directory ===\n";
echo "Path: $dir\n";
echo "Exists: " . (is_dir($dir) ? 'YES' : 'NO') . "\n";
echo "Writable: " . (is_writable($dir) ? 'YES' : 'NO') . "\n";
echo "\n";

echo "=== PHP Upload Limits ===\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "file_uploads: " . ini_get('file_uploads') . "\n";
echo "\n";

echo "=== Extensions ===\n";
echo "fileinfo: " . (extension_loaded('fileinfo') ? 'YES' : 'NO') . "\n";
echo "\n";

if (!is_dir($dir)) {
    echo "Trying to create directory...\n";
    if (mkdir($dir, 0755, true)) {
        echo "Created OK\n";
    } else {
        echo "FAILED to create\n";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['f'])) {
    $f = $_FILES['f'];
    echo "=== Upload Test ===\n";
    echo "Error code: " . $f['error'] . "\n";
    echo "Size: " . $f['size'] . "\n";
    echo "Tmp: " . $f['tmp_name'] . "\n";
    if ($f['error'] === 0) {
        $dest = $dir . '/test_' . time() . '.tmp';
        echo "Move result: " . (move_uploaded_file($f['tmp_name'], $dest) ? 'OK' : 'FAILED') . "\n";
        if (file_exists($dest)) unlink($dest);
    }
}
?>
<form method="POST" enctype="multipart/form-data">
  <input type="file" name="f">
  <button type="submit">Test Upload</button>
</form>
