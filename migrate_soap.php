<?php
/**
 * migrate_soap.php — Run once to create the soap_notes table.
 * Safe to re-run; uses CREATE TABLE IF NOT EXISTS + safe ALTER checks.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$results = [];

// 1. Main soap_notes table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS soap_notes (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            patient_id   INT    NOT NULL,
            visit_id     INT    NULL,
            note_date    DATE   NOT NULL,
            subjective   TEXT   NULL  COMMENT 'S — Chief complaint & patient-reported symptoms',
            objective    TEXT   NULL  COMMENT 'O — Exam findings, vitals, objective data',
            assessment   TEXT   NULL  COMMENT 'A — Diagnosis / clinical impression',
            plan         TEXT   NULL  COMMENT 'P — Treatment orders, follow-up, referrals',
            author_id    INT    NOT NULL,
            status       ENUM('draft','final') NOT NULL DEFAULT 'draft',
            finalized_at TIMESTAMP NULL,
            created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (patient_id) REFERENCES patients(id)  ON DELETE CASCADE,
            FOREIGN KEY (author_id)  REFERENCES staff(id)     ON DELETE RESTRICT,
            FOREIGN KEY (visit_id)   REFERENCES schedule(id)  ON DELETE SET NULL,
            INDEX idx_patient_date (patient_id, note_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $results[] = ['ok', 'soap_notes table is ready.'];
} catch (PDOException $e) {
    $results[] = ['err', 'soap_notes: ' . $e->getMessage()];
}

// 2. Add soap_note_id FK to schedule (optional — links a visit to its SOAP note)
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `schedule` LIKE 'soap_note_id'")->fetchAll();
    if (!$cols) {
        $pdo->exec("ALTER TABLE `schedule` ADD COLUMN soap_note_id INT NULL AFTER visit_notes,
                    ADD FOREIGN KEY fk_soap_note (soap_note_id) REFERENCES soap_notes(id) ON DELETE SET NULL");
        $results[] = ['ok', 'Added soap_note_id column to schedule table.'];
    } else {
        $results[] = ['ok', 'soap_note_id already exists — skipped.'];
    }
} catch (PDOException $e) {
    $results[] = ['warn', 'soap_note_id (non-critical): ' . $e->getMessage()];
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Migrate: SOAP Notes</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="font-sans p-8 bg-slate-50">
    <div class="max-w-xl mx-auto bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <h1 class="text-xl font-bold text-slate-800 mb-4">Migration: SOAP Notes</h1>
        <?php foreach ($results as [$type, $msg]): ?>
        <div class="flex items-center gap-2 py-2 text-sm <?= $type === 'err' ? 'text-red-600' : ($type === 'warn' ? 'text-amber-600' : 'text-emerald-700') ?>">
            <i class="<?= $type === 'err' ? '✗' : ($type === 'warn' ? '⚠' : '✔') ?>"></i>
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endforeach; ?>
        <a href="dashboard.php" class="mt-4 inline-block text-sm text-blue-600 hover:underline">← Back to Dashboard</a>
    </div>
</body>
</html>
