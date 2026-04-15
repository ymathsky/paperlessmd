<?php
/**
 * Migration: Ensure all columns exist in the `schedule` table.
 * Safe to run multiple times — existing columns are silently skipped.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireAdmin();

$pageTitle = 'DB Migration — Schedule Table';
include __DIR__ . '/includes/header.php';

// Step 1: Create table if it doesn't exist (full current schema)
$pdo->exec("CREATE TABLE IF NOT EXISTS `schedule` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visit_date DATE NOT NULL,
    ma_id INT NOT NULL,
    patient_id INT NOT NULL,
    visit_time TIME NULL,
    visit_order SMALLINT NOT NULL DEFAULT 0,
    status ENUM('pending','en_route','completed','missed') NOT NULL DEFAULT 'pending',
    visit_type VARCHAR(30) NOT NULL DEFAULT 'routine',
    notes TEXT NULL,
    visit_notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ma_id) REFERENCES staff(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (created_by) REFERENCES staff(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Step 2: Add any columns that might be missing in older deployments
$alterSteps = [
    'visit_order' => "ALTER TABLE `schedule` ADD COLUMN `visit_order` SMALLINT NOT NULL DEFAULT 0 AFTER `visit_time`",
    'visit_type'  => "ALTER TABLE `schedule` ADD COLUMN `visit_type`  VARCHAR(30) NOT NULL DEFAULT 'routine' AFTER `status`",
    'visit_notes' => "ALTER TABLE `schedule` ADD COLUMN `visit_notes` TEXT NULL AFTER `notes`",
    'created_by'  => "ALTER TABLE `schedule` ADD COLUMN `created_by`  INT NULL AFTER `visit_notes`",
    'updated_at'  => "ALTER TABLE `schedule` ADD COLUMN `updated_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`",
];

$results = [['label' => 'schedule table', 'status' => 'created or already exists', 'ok' => true]];
foreach ($alterSteps as $col => $sql) {
    try {
        $pdo->exec($sql);
        $results[] = ['label' => $col, 'status' => 'column added ✓', 'ok' => true];
    } catch (PDOException $e) {
        $dup = str_contains($e->getMessage(), '1060') || str_contains($e->getMessage(), 'Duplicate column');
        $results[] = ['label' => $col, 'status' => $dup ? 'already exists (skipped)' : 'ERROR: ' . $e->getMessage(), 'ok' => $dup];
    }
}
?>
<div class="max-w-2xl mx-auto mt-8">
    <div class="bg-white border border-slate-100 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-6 py-5 bg-gradient-to-r from-indigo-600 to-violet-600 text-white">
            <h2 class="text-xl font-extrabold flex items-center gap-2">
                <i class="bi bi-database-fill-gear"></i> Schedule Table Migration
            </h2>
            <p class="text-indigo-100 text-sm mt-1">Ensures all required columns exist in the <code class="bg-white/20 px-1 rounded">schedule</code> table.</p>
        </div>
        <div class="divide-y divide-slate-100">
            <?php foreach ($results as $r): ?>
            <div class="flex items-center gap-4 px-6 py-4">
                <div class="w-8 h-8 rounded-xl flex items-center justify-center flex-shrink-0
                            <?= $r['ok'] ? 'bg-emerald-100 text-emerald-600' : 'bg-rose-100 text-rose-600' ?>">
                    <i class="bi <?= $r['ok'] ? 'bi-check-lg' : 'bi-x-lg' ?>"></i>
                </div>
                <div>
                    <div class="font-semibold text-slate-800 text-sm font-mono"><?= h($r['label']) ?></div>
                    <div class="text-xs text-slate-500 mt-0.5"><?= h($r['status']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="px-6 py-4 bg-slate-50 border-t border-slate-100 flex gap-3 flex-wrap">
            <a href="<?= BASE_URL ?>/admin/schedule_manage.php"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-bold rounded-xl transition-colors">
                <i class="bi bi-calendar-week-fill"></i> Go to Schedule Manage
            </a>
            <a href="<?= BASE_URL ?>/schedule.php"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-sm font-semibold rounded-xl transition-colors">
                <i class="bi bi-calendar3"></i> My Schedule
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

