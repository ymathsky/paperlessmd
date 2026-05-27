<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS care_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    parent_id INT DEFAULT NULL,
    author_id INT NOT NULL,
    body TEXT NOT NULL,
    pinned TINYINT(1) NOT NULL DEFAULT 0,
    edited_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_patient (patient_id),
    INDEX idx_parent (parent_id)
)");
echo "care_notes table created\n";
