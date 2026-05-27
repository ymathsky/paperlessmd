<?php
/**
 * migrate_dark_mode.php
 * Adds dark_mode column to the staff table (used by sidebar dark/light toggle).
 * Run once: https://ecpaperlessmd.com/migrate_dark_mode.php
 */
// When run via CLI deploy script, bypass HTTP auth
if (PHP_SAPI !== 'cli') {
    require_once __DIR__ . '/includes/auth.php';
    require_once __DIR__ . '/includes/db.php';
    requireAdmin();
} else {
    require_once __DIR__ . '/includes/db.php';
}

$steps = [];

try {
    $pdo->exec("ALTER TABLE staff ADD COLUMN dark_mode TINYINT(1) NOT NULL DEFAULT 0 AFTER last_active_at");
    $steps[] = ['ok', 'Added <code>dark_mode</code> column to <code>staff</code>'];
} catch (PDOException $e) {
    $steps[] = str_contains($e->getMessage(), 'Duplicate column')
        ? ['warn', '<code>dark_mode</code> column already exists — skipped']
        : ['err', 'Failed to add column: ' . htmlspecialchars($e->getMessage())];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Migrate dark_mode</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-6">
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 max-w-lg w-full">
    <h1 class="text-xl font-extrabold text-slate-800 mb-1">Migration: dark_mode</h1>
    <p class="text-sm text-slate-500 mb-6">Adds <code class="bg-slate-100 px-1 rounded">dark_mode</code> column to the <code class="bg-slate-100 px-1 rounded">staff</code> table for per-user dark/light mode preference.</p>
    <ul class="space-y-2">
        <?php foreach ($steps as [$status, $msg]): ?>
        <li class="flex items-start gap-2 text-sm <?= $status === 'ok' ? 'text-emerald-700' : ($status === 'warn' ? 'text-amber-600' : 'text-red-600') ?>">
            <span class="text-lg leading-5"><?= $status === 'ok' ? '✅' : ($status === 'warn' ? '⚠️' : '❌') ?></span>
            <span><?= $msg ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
    <a href="/dashboard.php" class="mt-6 inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-xl text-sm font-semibold hover:bg-blue-700">← Back to Dashboard</a>
</div>
</body>
</html>
