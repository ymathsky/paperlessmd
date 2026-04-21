<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireAdmin();

$uploadDir = __DIR__ . '/uploads/message_files';
$result    = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['testfile'])) {
    $f = $_FILES['testfile'];
    if ($f['error'] === UPLOAD_ERR_OK) {
        $dest = $uploadDir . '/test_' . time() . '_' . basename($f['name']);
        if (move_uploaded_file($f['tmp_name'], $dest)) {
            $result = ['ok' => true, 'msg' => 'Upload succeeded. Saved to: ' . $dest . ' (' . number_format($f['size']) . ' bytes)'];
            unlink($dest); // clean up immediately
        } else {
            $result = ['ok' => false, 'msg' => 'move_uploaded_file() failed. Dir writable: ' . (is_writable($uploadDir) ? 'yes' : 'no')];
        }
    } else {
        $codes = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds upload_max_filesize (' . ini_get('upload_max_filesize') . ')',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds MAX_FILE_SIZE in form',
            UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'No temp directory',
            UPLOAD_ERR_CANT_WRITE => 'Cannot write to disk',
            UPLOAD_ERR_EXTENSION  => 'Blocked by PHP extension',
        ];
        $result = ['ok' => false, 'msg' => 'Upload error ' . $f['error'] . ': ' . ($codes[$f['error']] ?? 'Unknown')];
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Upload Test</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8 font-mono text-sm">
<div class="max-w-xl mx-auto bg-white rounded-xl shadow p-6 space-y-4">
  <h1 class="text-lg font-bold">Upload Diagnostic</h1>

  <table class="w-full text-left border-collapse">
    <tr><td class="py-1 pr-4 text-gray-500">upload_max_filesize</td><td class="font-semibold"><?= ini_get('upload_max_filesize') ?></td></tr>
    <tr><td class="py-1 pr-4 text-gray-500">post_max_size</td><td class="font-semibold"><?= ini_get('post_max_size') ?></td></tr>
    <tr><td class="py-1 pr-4 text-gray-500">file_uploads</td><td class="font-semibold"><?= ini_get('file_uploads') ? 'ON' : 'OFF' ?></td></tr>
    <tr><td class="py-1 pr-4 text-gray-500">fileinfo ext</td><td class="font-semibold"><?= extension_loaded('fileinfo') ? 'YES' : 'NO' ?></td></tr>
    <tr><td class="py-1 pr-4 text-gray-500">Upload dir</td><td class="font-semibold"><?= htmlspecialchars($uploadDir) ?></td></tr>
    <tr><td class="py-1 pr-4 text-gray-500">Dir exists</td><td class="font-semibold"><?= is_dir($uploadDir) ? 'YES' : 'NO' ?></td></tr>
    <tr><td class="py-1 pr-4 text-gray-500">Dir writable</td><td class="font-semibold"><?= is_writable($uploadDir) ? 'YES' : 'NO' ?></td></tr>
    <tr><td class="py-1 pr-4 text-gray-500">php.ini path</td><td class="font-semibold"><?= php_ini_loaded_file() ?: 'none' ?></td></tr>
    <tr><td class="py-1 pr-4 text-gray-500">user.ini path</td><td class="font-semibold"><?= php_ini_scanned_files() ?: 'none' ?></td></tr>
  </table>

  <?php if ($result): ?>
  <div class="p-3 rounded <?= $result['ok'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
    <?= htmlspecialchars($result['msg']) ?>
  </div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="space-y-3">
    <input type="hidden" name="MAX_FILE_SIZE" value="<?= 25 * 1024 * 1024 ?>">
    <div>
      <label class="block text-gray-600 mb-1">Pick a file to test:</label>
      <input type="file" name="testfile" class="block w-full border rounded px-2 py-1">
    </div>
    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Test Upload</button>
  </form>

  <p class="text-xs text-gray-400">Admin only. File is deleted immediately after upload. <a href="messages.php" class="underline">Back to Messages</a></p>
</div>
</body>
</html>
