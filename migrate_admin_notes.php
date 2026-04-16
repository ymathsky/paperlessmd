<?php
/**
 * Migration: create admin_notes table for pinned dashboard notes.
 * Run once on every environment.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

$steps = [];

try {
    $exists = $pdo->query("SHOW TABLES LIKE 'admin_notes'")->fetch();
    if (!$exists) {
        $pdo->exec("CREATE TABLE admin_notes (
            id          INT AUTO_INCREMENT PRIMARY KEY,
            author_id   INT NOT NULL,
            body        TEXT NOT NULL,
            pinned      TINYINT(1) NOT NULL DEFAULT 1,
            created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_pinned (pinned),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $steps[] = ['ok', 'Created table: admin_notes'];
    } else {
        $steps[] = ['skip', 'Table admin_notes already exists'];
    }
} catch (PDOException $e) {
    $steps[] = ['err', 'DB error: ' . $e->getMessage()];
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<title>Migration — Admin Notes</title>
<script src="https://cdn.tailwindcss.com"></script></head>
<body class="font-sans p-8 bg-slate-50">
<div class="max-w-xl mx-auto bg-white rounded-2xl shadow p-8">
  <h1 class="text-lg font-bold mb-6">Migration: Admin Notes</h1>
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
