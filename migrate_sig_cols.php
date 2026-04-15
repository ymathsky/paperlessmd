<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();
requireAdmin();

$pageTitle = 'Migrate — Signature Columns';
$activeNav = '';
include __DIR__ . '/includes/header.php';

$steps   = [];
$success = true;

function runStep(PDO $pdo, string $label, string $sql): array {
    try {
        $pdo->exec($sql);
        return ['ok' => true, 'label' => $label, 'msg' => ''];
    } catch (PDOException $e) {
        // "Duplicate column" (1060) is fine — column already exists
        if (str_contains($e->getMessage(), '1060') || str_contains($e->getMessage(), 'Duplicate column')) {
            return ['ok' => true, 'label' => $label, 'msg' => 'Already exists — skipped'];
        }
        return ['ok' => false, 'label' => $label, 'msg' => $e->getMessage()];
    }
}

$steps[] = runStep($pdo,
    'Add ma_signature column',
    "ALTER TABLE form_submissions ADD COLUMN ma_signature MEDIUMTEXT NULL AFTER patient_signature"
);
$steps[] = runStep($pdo,
    'Add provider_signature column',
    "ALTER TABLE form_submissions ADD COLUMN provider_signature MEDIUMTEXT NULL AFTER ma_signature"
);
$steps[] = runStep($pdo,
    'Add provider_name column',
    "ALTER TABLE form_submissions ADD COLUMN provider_name VARCHAR(120) NULL AFTER provider_signature"
);

foreach ($steps as $s) { if (!$s['ok']) { $success = false; break; } }
$color = $success ? 'from-emerald-700 to-emerald-500' : 'from-red-700 to-red-500';
$icon  = $success ? 'bi-check-circle-fill' : 'bi-x-circle-fill';
?>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-slate-400 mb-6 flex-wrap no-print">
    <a href="<?= BASE_URL ?>/dashboard.php" class="hover:text-blue-600 font-medium">Dashboard</a>
    <i class="bi bi-chevron-right text-xs"></i>
    <a href="<?= BASE_URL ?>/admin/settings.php" class="hover:text-blue-600 font-medium">Global Settings</a>
    <i class="bi bi-chevron-right text-xs"></i>
    <span class="text-slate-700 font-semibold">Signature Columns Migration</span>
</nav>

<div class="max-w-2xl">
    <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="bg-gradient-to-r <?= $color ?> px-6 py-5 flex items-center gap-3">
            <i class="bi <?= $icon ?> text-white text-2xl"></i>
            <div>
                <p class="text-white font-extrabold text-lg">Signature Columns Migration</p>
                <p class="text-white/70 text-sm mt-0.5"><?= DB_NAME ?? 'Database' ?> &mdash; <?= count($steps) ?> steps &mdash; <?= date('H:i:s') ?></p>
            </div>
        </div>
        <div class="p-6 space-y-3">
            <?php foreach ($steps as $i => $s): ?>
            <div class="flex items-start gap-3 px-4 py-2.5 rounded-xl <?= $s['ok'] ? 'bg-emerald-50 border border-emerald-100' : 'bg-red-50 border border-red-100' ?>">
                <i class="bi <?= $s['ok'] ? 'bi-check-circle-fill text-emerald-500' : 'bi-x-circle-fill text-red-500' ?> mt-0.5 flex-shrink-0"></i>
                <div>
                    <p class="text-sm font-semibold text-slate-800">Step <?= $i + 1 ?>: <?= h($s['label']) ?></p>
                    <?php if ($s['msg']): ?>
                    <p class="text-xs text-slate-500 mt-0.5"><?= h($s['msg']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if ($success): ?>
            <div class="mt-4 p-4 bg-emerald-50 border border-emerald-200 rounded-xl text-sm text-emerald-800">
                <i class="bi bi-check2-all mr-2 text-emerald-600"></i>
                Migration complete. MA &amp; Provider signature columns are ready.
            </div>
            <?php endif; ?>
        </div>
        <div class="px-6 pb-6 flex gap-3">
            <a href="<?= BASE_URL ?>/admin/settings.php"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-violet-600 hover:bg-violet-700 text-white
                      font-semibold rounded-xl transition-colors text-sm">
                <i class="bi bi-sliders2-vertical"></i> Open Global Settings
            </a>
            <a href="<?= BASE_URL ?>/dashboard.php"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-white border border-slate-200 text-slate-700
                      hover:bg-slate-50 font-semibold rounded-xl transition-colors text-sm">
                Dashboard
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
