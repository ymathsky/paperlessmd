<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireAdmin();

$results = [];

// Add provider_name column to schedule table
try {
    $pdo->exec("ALTER TABLE `schedule` ADD COLUMN `provider_name` VARCHAR(100) NULL DEFAULT NULL AFTER `notes`");
    $results[] = ['ok', 'Added provider_name column to schedule table.'];
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate column')) {
        $results[] = ['skip', 'provider_name column already exists — skipped.'];
    } else {
        $results[] = ['err', 'Error: ' . $e->getMessage()];
    }
}

// Add index for faster filtering
try {
    $pdo->exec("ALTER TABLE `schedule` ADD INDEX idx_provider_name (`provider_name`)");
    $results[] = ['ok', 'Added index on provider_name.'];
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate key name')) {
        $results[] = ['skip', 'Index already exists — skipped.'];
    } else {
        $results[] = ['err', 'Index error: ' . $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Migration: Schedule Provider</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-6">
<div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8 w-full max-w-lg">
    <h1 class="text-xl font-bold text-slate-800 mb-2">Migration: Schedule Provider Name</h1>
    <p class="text-slate-500 text-sm mb-6">Adds <code class="bg-slate-100 px-1 rounded">provider_name</code> column to the <code class="bg-slate-100 px-1 rounded">schedule</code> table.</p>
    <ul class="space-y-3">
        <?php foreach ($results as [$status, $msg]): ?>
        <li class="flex items-start gap-3 text-sm">
            <?php if ($status === 'ok'): ?>
                <span class="text-emerald-600 font-bold mt-0.5">✓</span>
                <span class="text-emerald-700"><?= htmlspecialchars($msg) ?></span>
            <?php elseif ($status === 'skip'): ?>
                <span class="text-amber-500 font-bold mt-0.5">–</span>
                <span class="text-amber-700"><?= htmlspecialchars($msg) ?></span>
            <?php else: ?>
                <span class="text-red-600 font-bold mt-0.5">✗</span>
                <span class="text-red-700"><?= htmlspecialchars($msg) ?></span>
            <?php endif; ?>
        </li>
        <?php endforeach; ?>
    </ul>
    <p class="mt-6 text-xs text-slate-400">Migration complete. You can now delete this file from the server.</p>
    <a href="/dashboard.php" class="mt-4 inline-block text-sm text-indigo-600 hover:underline">← Back to Dashboard</a>
</div>
</body>
</html>
