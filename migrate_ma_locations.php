<?php
/**
 * Migration: create ma_locations table for MA location tracking
 * Safe to run multiple times.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireAdmin();

function runStep(PDO $pdo, string $label, string $sql): array {
    try {
        $pdo->exec($sql);
        return ['label' => $label, 'ok' => true, 'msg' => 'Done'];
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, 'already exists') || str_contains($msg, 'Duplicate')) {
            return ['label' => $label, 'ok' => true, 'msg' => 'Already applied'];
        }
        return ['label' => $label, 'ok' => false, 'msg' => $msg];
    }
}

$steps = [];
$steps[] = runStep($pdo, 'Create ma_locations table', "
    CREATE TABLE IF NOT EXISTS ma_locations (
        id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        staff_id     INT UNSIGNED NOT NULL,
        latitude     DECIMAL(10,8) NOT NULL,
        longitude    DECIMAL(11,8) NOT NULL,
        accuracy     FLOAT NULL COMMENT 'Accuracy in metres',
        recorded_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_staff_recorded (staff_id, recorded_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

$success = !in_array(false, array_column($steps, 'ok'));

include __DIR__ . '/includes/header.php';
?>
<div class="max-w-xl mx-auto py-10">
    <h2 class="text-2xl font-extrabold text-slate-800 mb-6">MA Locations — Database Migration</h2>
    <div class="space-y-3">
        <?php foreach ($steps as $s): ?>
        <div class="flex items-start gap-3 px-4 py-3 rounded-xl border
                    <?= $s['ok'] ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-red-50 border-red-200 text-red-700' ?>">
            <i class="bi <?= $s['ok'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?> text-lg shrink-0 mt-0.5"></i>
            <div>
                <p class="font-semibold text-sm"><?= htmlspecialchars($s['label']) ?></p>
                <p class="text-xs opacity-75 mt-0.5"><?= htmlspecialchars($s['msg']) ?></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($success): ?>
    <div class="mt-6 flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm font-semibold">
        <i class="bi bi-check2-all text-xl"></i> Migration complete — MA location tracking is ready.
    </div>
    <?php endif; ?>
    <div class="mt-4">
        <a href="<?= BASE_URL ?>/admin/ma_locations.php"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold rounded-xl transition-all shadow-sm">
            <i class="bi bi-geo-alt-fill"></i> View MA Locations Map
        </a>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
