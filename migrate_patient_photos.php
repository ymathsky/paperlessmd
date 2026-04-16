<?php
/**
 * Migration: add photo_url column to patients table.
 * Run once on every environment.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

$steps = [];

try {
    $col = $pdo->query("SHOW COLUMNS FROM patients LIKE 'photo_url'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE patients ADD COLUMN photo_url VARCHAR(500) NULL DEFAULT NULL AFTER pcp");
        $steps[] = ['ok', 'Added column: patients.photo_url'];
    } else {
        $steps[] = ['skip', 'Column patients.photo_url already exists'];
    }
} catch (PDOException $e) {
    $steps[] = ['err', 'DB error: ' . $e->getMessage()];
}

// Create upload directory
$dir = __DIR__ . '/uploads/patient_photos/';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
    $steps[] = ['ok', 'Created directory: uploads/patient_photos/'];
} else {
    $steps[] = ['skip', 'Directory uploads/patient_photos/ already exists'];
}

// Write .htaccess to restrict direct execution of scripts in that dir
$htaccess = $dir . '.htaccess';
if (!file_exists($htaccess)) {
    file_put_contents($htaccess, "php_flag engine off\nOptions -ExecCGI\n");
    $steps[] = ['ok', 'Written .htaccess to uploads/patient_photos/'];
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<title>Migration — Patient Photos</title>
<script src="https://cdn.tailwindcss.com"></script></head>
<body class="font-sans p-8 bg-slate-50">
<div class="max-w-xl mx-auto bg-white rounded-2xl shadow p-8">
  <h1 class="text-lg font-bold mb-6">Migration: Patient Photos</h1>
  <ul class="space-y-2 text-sm">
  <?php foreach ($steps as [$status, $msg]): ?>
    <li class="flex items-start gap-2 <?= $status === 'err' ? 'text-red-600' : ($status === 'skip' ? 'text-slate-400' : 'text-emerald-700') ?>">
      <span class="font-bold"><?= $status === 'err' ? '✗' : ($status === 'skip' ? '–' : '✓') ?></span>
      <?= htmlspecialchars($msg) ?>
    </li>
  <?php endforeach; ?>
  </ul>
  <p class="mt-6 text-xs text-slate-400">Migration complete. You can delete this file.</p>
</div></body></html>
