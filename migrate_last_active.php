<?php
/**
 * migrate_last_active.php
 * Adds last_active_at column + index to the staff table.
 * Run once: https://docs.md-officesupport.com/migrate_last_active.php
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireAdmin();

$steps = [];

// 1. Add last_active_at column
try {
    $pdo->exec("ALTER TABLE staff ADD COLUMN last_active_at TIMESTAMP NULL DEFAULT NULL AFTER saved_sig_updated_at");
    $steps[] = ['ok', 'Added <code>last_active_at</code> column to <code>staff</code>'];
} catch (PDOException $e) {
    $steps[] = str_contains($e->getMessage(), 'Duplicate column')
        ? ['warn', '<code>last_active_at</code> column already exists — skipped']
        : ['err', 'Failed to add column: ' . htmlspecialchars($e->getMessage())];
}

// 2. Add index
try {
    $pdo->exec("ALTER TABLE staff ADD INDEX idx_staff_last_active (last_active_at)");
    $steps[] = ['ok', 'Added index <code>idx_staff_last_active</code>'];
} catch (PDOException $e) {
    $steps[] = str_contains($e->getMessage(), 'Duplicate key')
        ? ['warn', 'Index already exists — skipped']
        : ['err', 'Failed to add index: ' . htmlspecialchars($e->getMessage())];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Migrate last_active_at</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-6">
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 max-w-lg w-full">
    <h1 class="text-xl font-extrabold text-slate-800 mb-1">Migration: last_active_at</h1>
    <p class="text-sm text-slate-500 mb-6">Adds <code class="bg-slate-100 px-1 rounded">last_active_at</code> column to the <code class="bg-slate-100 px-1 rounded">staff</code> table for real-time online status tracking.</p>
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
