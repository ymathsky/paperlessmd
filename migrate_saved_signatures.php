<?php
/**
 * Migration: add saved_signature + saved_sig_updated_at columns to staff table
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
        // "Duplicate column" = already applied, treat as OK
        if (str_contains($msg, 'Duplicate column')) {
            return ['label' => $label, 'ok' => true, 'msg' => 'Already applied'];
        }
        return ['label' => $label, 'ok' => false, 'msg' => $msg];
    }
}

$steps = [];
$steps[] = runStep($pdo, 'Add saved_signature column',
    "ALTER TABLE staff ADD COLUMN saved_signature MEDIUMTEXT NULL AFTER active");
$steps[] = runStep($pdo, 'Add saved_sig_updated_at column',
    "ALTER TABLE staff ADD COLUMN saved_sig_updated_at TIMESTAMP NULL AFTER saved_signature");

$success = !in_array(false, array_column($steps, 'ok'));
include __DIR__ . '/includes/header.php';
?>
<div class="max-w-xl mx-auto mt-10 p-6 bg-white rounded-2xl shadow border border-slate-200">
  <h2 class="text-xl font-bold mb-4">Migration: Saved Signatures</h2>
  <?php foreach ($steps as $s): ?>
  <div class="flex items-center gap-3 py-2 border-b border-slate-100 last:border-0">
    <i class="bi <?= $s['ok'] ? 'bi-check-circle-fill text-emerald-500' : 'bi-x-circle-fill text-red-500' ?>"></i>
    <span class="text-sm font-medium flex-1"><?= htmlspecialchars($s['label']) ?></span>
    <span class="text-xs text-slate-500"><?= htmlspecialchars($s['msg']) ?></span>
  </div>
  <?php endforeach; ?>
  <div class="mt-4 text-sm <?= $success ? 'text-emerald-600' : 'text-red-600' ?> font-semibold">
    <?= $success ? '✅ Migration complete.' : '❌ Some steps failed — see details above.' ?>
  </div>
  <a href="<?= BASE_URL ?>/dashboard.php" class="mt-4 inline-block text-sm text-blue-600 hover:underline">← Back to Dashboard</a>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
