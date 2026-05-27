<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';

// Rename staff_id → author_id
$pdo->exec("ALTER TABLE soap_notes CHANGE COLUMN staff_id author_id INT NOT NULL");

// Rename visit_date → note_date
$pdo->exec("ALTER TABLE soap_notes CHANGE COLUMN visit_date note_date DATE NOT NULL");

// Add missing columns
$pdo->exec("ALTER TABLE soap_notes
    ADD COLUMN visit_id INT DEFAULT NULL AFTER patient_id,
    ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'draft' AFTER plan,
    ADD COLUMN finalized_at TIMESTAMP NULL DEFAULT NULL AFTER status,
    ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL AFTER finalized_at
");

echo "soap_notes table updated\n";
