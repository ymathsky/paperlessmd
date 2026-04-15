<?php
/**
 * Migration: Create settings table and seed default timezone
 * Run once: https://docs.md-officesupport.com/migrate_settings.php
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();
requireAdmin();

$pageTitle = 'Migration: Settings Table';
$activeNav = '';

$steps  = [];
$failed = false;

/* ── Run migration ───────────────────────────────────────────────────────── */
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            `key`        VARCHAR(64)  NOT NULL PRIMARY KEY,
            `value`      TEXT         NOT NULL DEFAULT '',
            `label`      VARCHAR(120) NOT NULL DEFAULT '',
            `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    $steps[] = ['ok' => true,  'msg' => 'Table <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs font-mono">settings</code> created (or already existed).'];

    $seeds = [
        ['timezone', 'America/Chicago', 'Server / Display Timezone'],
    ];
    $ins = $pdo->prepare("INSERT IGNORE INTO settings (`key`, `value`, `label`) VALUES (?, ?, ?)");
    foreach ($seeds as $s) {
        $ins->execute($s);
        $steps[] = ['ok' => true, 'msg' => 'Seeded default: <strong>' . htmlspecialchars($s[0]) . '</strong> = <strong>' . htmlspecialchars($s[1]) . '</strong>'];
    }

    $steps[] = ['ok' => true, 'msg' => 'Migration completed successfully.'];
} catch (PDOException $e) {
    $steps[] = ['ok' => false, 'msg' => 'Database error: ' . htmlspecialchars($e->getMessage())];
    $failed  = true;
}

include __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-slate-400 mb-6 flex-wrap">
    <a href="<?= BASE_URL ?>/dashboard.php" class="hover:text-blue-600 font-medium">Dashboard</a>
    <i class="bi bi-chevron-right text-xs"></i>
    <a href="<?= BASE_URL ?>/admin/settings.php" class="hover:text-blue-600 font-medium">Global Settings</a>
    <i class="bi bi-chevron-right text-xs"></i>
    <span class="text-slate-700 font-semibold">Settings Migration</span>
</nav>

<!-- Page heading -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-extrabold text-slate-800">Settings Migration</h2>
        <p class="text-slate-500 text-sm mt-0.5">One-time setup — creates the <code class="bg-slate-100 px-1.5 rounded text-xs font-mono">settings</code> table.</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/settings.php"
       class="inline-flex items-center gap-2 bg-violet-600 hover:bg-violet-700 text-white font-semibold
              px-5 py-2.5 rounded-xl transition-all shadow-sm text-sm">
        <i class="bi bi-sliders2-vertical"></i> Go to Settings
    </a>
</div>

<!-- Result card -->
<div class="max-w-2xl bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">

    <!-- Gradient header bar -->
    <div class="bg-gradient-to-r <?= $failed ? 'from-red-700 to-red-500' : 'from-emerald-700 to-emerald-500' ?> px-6 py-4 flex items-center gap-3">
        <div class="bg-white/20 p-2.5 rounded-xl">
            <i class="bi <?= $failed ? 'bi-x-circle-fill' : 'bi-database-fill-check' ?> text-white text-xl"></i>
        </div>
        <div>
            <h3 class="text-white font-extrabold text-base"><?= $failed ? 'Migration Failed' : 'Migration Successful' ?></h3>
            <p class="text-white/75 text-xs mt-0.5"><?= h(PRACTICE_NAME) ?> &mdash; <?= h(DB_NAME) ?></p>
        </div>
        <div class="ml-auto">
            <span class="bg-white/20 text-white text-xs font-bold px-3 py-1.5 rounded-full">
                <?= $failed ? 'ERROR' : 'DONE' ?>
            </span>
        </div>
    </div>

    <!-- Meta strip -->
    <div class="px-6 py-3 bg-slate-50 border-b border-slate-100 flex flex-wrap gap-x-6 gap-y-1 text-sm">
        <div>
            <span class="text-slate-500 text-xs font-semibold uppercase tracking-wide">Database</span>
            <div class="font-semibold text-slate-700"><?= h(DB_NAME) ?></div>
        </div>
        <div>
            <span class="text-slate-500 text-xs font-semibold uppercase tracking-wide">Run at</span>
            <div class="font-semibold text-slate-700"><?= date('M j, Y g:i a') ?></div>
        </div>
        <div>
            <span class="text-slate-500 text-xs font-semibold uppercase tracking-wide">Steps</span>
            <div class="font-semibold text-slate-700"><?= count($steps) ?></div>
        </div>
    </div>

    <!-- Steps list -->
    <div class="p-6 space-y-2">
        <?php foreach ($steps as $step): ?>
        <div class="flex items-start gap-3 px-4 py-3 rounded-xl
                    <?= $step['ok'] ? 'bg-emerald-50 border border-emerald-100' : 'bg-red-50 border border-red-100' ?>">
            <i class="bi <?= $step['ok'] ? 'bi-check-circle-fill text-emerald-500' : 'bi-x-circle-fill text-red-500' ?> mt-0.5 flex-shrink-0"></i>
            <span class="text-sm <?= $step['ok'] ? 'text-emerald-800' : 'text-red-800' ?>"><?= $step['msg'] ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Footer action -->
    <?php if (!$failed): ?>
    <div class="px-6 pb-6 flex items-center gap-4">
        <a href="<?= BASE_URL ?>/admin/settings.php"
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-violet-600 hover:bg-violet-700
                  text-white font-bold rounded-xl transition-all shadow-sm text-sm">
            <i class="bi bi-arrow-right-circle-fill"></i> Open Global Settings
        </a>
        <a href="<?= BASE_URL ?>/dashboard.php"
           class="text-sm text-slate-500 hover:text-slate-700 transition-colors">Back to Dashboard</a>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

