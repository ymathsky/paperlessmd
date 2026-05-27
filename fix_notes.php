<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS soap_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    visit_id INT DEFAULT NULL,
    note_date DATE NOT NULL,
    subjective TEXT DEFAULT NULL,
    objective TEXT DEFAULT NULL,
    assessment TEXT DEFAULT NULL,
    plan TEXT DEFAULT NULL,
    author_id INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    finalized_at TIMESTAMP NULL DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_patient (patient_id),
    INDEX idx_note_date (note_date)
)");
echo "soap_notes table created\n";
