<?php
/**
 * Migration: Wound Measurements Table
 * Run once on production via browser, then delete this file.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (!isAdmin()) { die('Admins only.'); }

$results = [];

// Create wound_measurements table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS wound_measurements (
        id          INT AUTO_INCREMENT PRIMARY KEY,
        patient_id  INT NOT NULL,
        visit_id    INT NULL,
        measured_at DATE NOT NULL,
        wound_site  VARCHAR(150) NOT NULL DEFAULT 'Unspecified',
        length_cm   DECIMAL(5,1) NOT NULL DEFAULT 0.0,
        width_cm    DECIMAL(5,1) NOT NULL DEFAULT 0.0,
        depth_cm    DECIMAL(5,1) NOT NULL DEFAULT 0.0,
        notes       TEXT NULL,
        recorded_by INT NULL,
        created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id),
        FOREIGN KEY (recorded_by) REFERENCES staff(id),
        INDEX idx_patient_date (patient_id, measured_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $results[] = ['ok', 'wound_measurements table created (or already exists).'];
} catch (PDOException $e) {
    $results[] = ['err', 'wound_measurements: ' . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Wounds Migration — <?= APP_NAME ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="font-sans bg-slate-50 p-8 max-w-xl mx-auto">
<h1 class="text-xl font-extrabold text-slate-800 mb-6">Wound Measurements Migration</h1>
<?php foreach ($results as [$type, $msg]): ?>
<div class="mb-3 px-4 py-3 rounded-xl text-sm font-medium <?= $type === 'ok' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
    <i class="<?= $type === 'ok' ? 'text-emerald-500' : 'text-red-500' ?> mr-2">
        <?= $type === 'ok' ? '✓' : '✗' ?>
    </i><?= htmlspecialchars($msg) ?>
</div>
<?php endforeach; ?>
<p class="mt-8 text-xs text-slate-400">⚠️ Delete this file after migration is confirmed.</p>
<a href="<?= BASE_URL ?>/dashboard.php" class="mt-4 inline-block text-sm text-blue-600 hover:underline">← Back to Dashboard</a>
</body>
</html>
