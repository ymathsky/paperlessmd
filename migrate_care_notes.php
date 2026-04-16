<?php
/**
 * migrate_care_notes.php — Run once to create the care_notes table.
 * Visit: https://yourdomain.com/migrate_care_notes.php
 */
require_once __DIR__ . '/includes/config.php';

$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
$pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$steps = [];

// care_notes table
$pdo->exec("CREATE TABLE IF NOT EXISTS care_notes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    patient_id  INT NOT NULL,
    parent_id   INT NULL COMMENT 'NULL = top-level note; set = reply',
    author_id   INT NOT NULL,
    body        TEXT NOT NULL,
    pinned      TINYINT(1) NOT NULL DEFAULT 0,
    edited_at   TIMESTAMP NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id)  REFERENCES staff(id)    ON DELETE RESTRICT,
    FOREIGN KEY (parent_id)  REFERENCES care_notes(id) ON DELETE CASCADE,
    INDEX idx_patient_created (patient_id, created_at),
    INDEX idx_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$steps[] = '✅ care_notes table created (or already exists)';

echo '<pre style="font-family:monospace;padding:20px">';
echo implode("\n", $steps);
echo "\n\n✅ Migration complete. You may delete this file.\n";
echo '</pre>';
