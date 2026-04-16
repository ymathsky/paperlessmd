<?php
/**
 * Migration: add failed_attempts + locked_until to the staff table.
 * Run once on every environment (local + production).
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

$steps = [];

try {
    // failed_attempts --------------------------------------------------------
    $col = $pdo->query("SHOW COLUMNS FROM staff LIKE 'failed_attempts'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE staff
                    ADD COLUMN failed_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0
                    AFTER active");
        $steps[] = ['ok', 'Added column: staff.failed_attempts'];
    } else {
        $steps[] = ['skip', 'Column staff.failed_attempts already exists'];
    }

    // locked_until -----------------------------------------------------------
    $col = $pdo->query("SHOW COLUMNS FROM staff LIKE 'locked_until'")->fetch();
    if (!$col) {
        $pdo->exec("ALTER TABLE staff
                    ADD COLUMN locked_until DATETIME NULL DEFAULT NULL
                    AFTER failed_attempts");
        $steps[] = ['ok', 'Added column: staff.locked_until'];
    } else {
        $steps[] = ['skip', 'Column staff.locked_until already exists'];
    }

} catch (PDOException $e) {
    $steps[] = ['err', 'DB error: ' . $e->getMessage()];
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<title>Migration — Login Lockout</title>
<script src="https://cdn.tailwindcss.com"></script></head>
<body class="font-sans p-8 bg-slate-50">
<div class="max-w-xl mx-auto bg-white rounded-2xl shadow p-8">
  <h1 class="text-lg font-bold mb-6">Migration: Login Lockout</h1>
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
