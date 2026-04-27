<?php
/**
 * migrate_assigned_ma.php
 * Adds assigned_ma column to patients table (FK → staff.id).
 * Run once: https://docs.md-officesupport.com/migrate_assigned_ma.php
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireAdmin();

$steps = [];

// 1. Add assigned_ma column
try {
    $pdo->exec("ALTER TABLE patients ADD COLUMN assigned_ma INT NULL DEFAULT NULL AFTER pcp");
    $steps[] = ['ok', 'Added <code>assigned_ma</code> column to <code>patients</code>'];
} catch (PDOException $e) {
    $steps[] = strpos($e->getMessage(), 'Duplicate column') !== false
        ? ['warn', '<code>assigned_ma</code> column already exists — skipped']
        : ['err',  'Error adding column: ' . htmlspecialchars($e->getMessage())];
}

// 2. Add foreign key constraint (separate step so column add doesn't fail silently)
try {
    // Check if FK already exists before adding
    $fkCheck = $pdo->query("
        SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'patients'
          AND CONSTRAINT_NAME = 'fk_patients_assigned_ma'
    ")->fetchColumn();

    if (!$fkCheck) {
        $pdo->exec("ALTER TABLE patients ADD CONSTRAINT fk_patients_assigned_ma
                    FOREIGN KEY (assigned_ma) REFERENCES staff(id) ON DELETE SET NULL");
        $steps[] = ['ok', 'Added foreign key <code>fk_patients_assigned_ma</code> → <code>staff.id</code>'];
    } else {
        $steps[] = ['warn', 'Foreign key already exists — skipped'];
    }
} catch (PDOException $e) {
    $steps[] = ['warn', 'FK not added (may require InnoDB or already exists): ' . htmlspecialchars($e->getMessage())];
}

$steps[] = ['ok', 'Migration complete — all done!'];
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Migrate assigned_ma</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','system-ui','sans-serif']}}}}</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="font-sans bg-slate-50 min-h-screen flex items-center justify-center p-8">
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 max-w-lg w-full">
    <h1 class="text-xl font-extrabold text-slate-800 mb-2">Assigned MA Migration</h1>
    <p class="text-sm text-slate-500 mb-6">Adds <code class="bg-slate-100 px-1 rounded">assigned_ma</code> column to the <code class="bg-slate-100 px-1 rounded">patients</code> table.</p>
    <ul class="space-y-2 mb-6">
        <?php foreach ($steps as [$type, $msg]): ?>
        <?php $cls = $type === 'ok' ? 'text-emerald-700' : ($type === 'warn' ? 'text-amber-600' : 'text-red-700'); ?>
        <li class="text-sm font-medium <?= $cls ?> flex items-start gap-2">
            <span class="mt-0.5"><?= $type === 'ok' ? '✓' : ($type === 'warn' ? '⚠' : '✗') ?></span>
            <span><?= $msg ?></span>
        </li>
        <?php endforeach; ?>
    </ul>
    <a href="<?= BASE_URL ?>/patients.php"
       class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl text-sm transition-colors">
        ← Back to Patients
    </a>
</div>
</body>
</html>
