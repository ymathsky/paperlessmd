<?php
/**
 * migrate_diagnoses.php
 * Creates the patient_diagnoses table.
 * Run once: https://docs.md-officesupport.com/migrate_diagnoses.php
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireAdmin();

$pdo->exec("
    CREATE TABLE IF NOT EXISTS patient_diagnoses (
        id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        patient_id  INT UNSIGNED NOT NULL,
        icd_code    VARCHAR(20) NOT NULL,
        icd_desc    VARCHAR(255) NOT NULL,
        added_by    INT UNSIGNED NOT NULL,
        added_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        notes       VARCHAR(500) NOT NULL DEFAULT '',
        INDEX idx_patient (patient_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

echo '<p style="font-family:sans-serif;color:green;padding:2rem">✅ patient_diagnoses table created (or already exists).</p>';
