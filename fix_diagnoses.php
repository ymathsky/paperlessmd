<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS patient_diagnoses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    icd_code VARCHAR(20) NOT NULL,
    icd_desc VARCHAR(500) NOT NULL,
    notes VARCHAR(500) DEFAULT NULL,
    added_by INT DEFAULT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_patient (patient_id),
    INDEX idx_icd (icd_code)
)");
echo "patient_diagnoses table created\n";
