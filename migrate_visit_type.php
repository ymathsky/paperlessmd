<?php
/**
 * Migration: Add visit_type column to schedule table
 * Run once on production via browser (admin login required), then delete.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (!isAdmin()) { die('Admins only.'); }

$results = [];

try {
    // Check if column already exists
    $check = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schedule' AND COLUMN_NAME = 'visit_type'
    ");
    $check->execute();
    if ((int)$check->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE `schedule`
            ADD COLUMN `visit_type` VARCHAR(30) NOT NULL DEFAULT 'routine' AFTER `notes`");
        $results[] = ['ok', 'Added visit_type column to schedule table.'];
    } else {
        $results[] = ['ok', 'visit_type column already exists — nothing to do.'];
    }
} catch (PDOException $e) {
    $results[] = ['err', 'schedule: ' . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Visit Type Migration — <?= APP_NAME ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="font-sans bg-slate-50 p-8 max-w-xl mx-auto">
<h1 class="text-xl font-extrabold text-slate-800 mb-6">Visit Type Migration</h1>
<?php foreach ($results as [$type, $msg]): ?>
<div class="mb-3 px-4 py-3 rounded-xl text-sm font-medium <?= $type === 'ok' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
    <?= $type === 'ok' ? '✓' : '✗' ?> <?= htmlspecialchars($msg) ?>
</div>
<?php endforeach; ?>
<p class="mt-8 text-xs text-slate-400">⚠️ Delete this file after migration is confirmed.</p>
<a href="<?= BASE_URL ?>/dashboard.php" class="mt-4 inline-block text-sm text-blue-600 hover:underline">← Back to Dashboard</a>
</body>
</html>
