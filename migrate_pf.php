<?php
/**
 * migrate_pf.php  — Run once to add Practice Fusion columns.
 * Visit: http://localhost/pd/migrate_pf.php
 * DELETE this file after running.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Protect: only admin session can run
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

$log = [];

$migrations = [
    "ALTER TABLE patients
        ADD COLUMN IF NOT EXISTS pf_patient_id VARCHAR(100) NULL DEFAULT NULL
        COMMENT 'Practice Fusion FHIR Patient ID'",

    "ALTER TABLE form_submissions
        ADD COLUMN IF NOT EXISTS pf_patient_id VARCHAR(100) NULL DEFAULT NULL
        COMMENT 'PF Patient FHIR ID used during upload'",

    "ALTER TABLE form_submissions
        ADD COLUMN IF NOT EXISTS pf_doc_id VARCHAR(100) NULL DEFAULT NULL
        COMMENT 'PF DocumentReference FHIR ID returned after upload'",
];

foreach ($migrations as $sql) {
    try {
        $pdo->exec($sql);
        $log[] = ['ok', substr($sql, 0, 80) . '…'];
    } catch (PDOException $e) {
        $log[] = ['err', $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>PF Migration | <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container" style="max-width:700px;margin-top:60px">
    <h4 class="fw-bold mb-4"><i class="bi bi-database-gear me-2"></i>Practice Fusion DB Migration</h4>
    <ul class="list-group mb-4">
        <?php foreach ($log as [$status, $msg]): ?>
        <li class="list-group-item d-flex align-items-start gap-2">
            <i class="bi <?= $status === 'ok' ? 'bi-check-circle-fill text-success' : 'bi-x-circle-fill text-danger' ?>"></i>
            <code class="small"><?= h($msg) ?></code>
        </li>
        <?php endforeach; ?>
    </ul>
    <div class="alert alert-warning">
        <strong>Done.</strong> Delete <code>migrate_pf.php</code> now.
    </div>
    <a href="<?= BASE_URL ?>/dashboard.php" class="btn btn-primary">Back to Dashboard</a>
</div>
</body>
</html>
