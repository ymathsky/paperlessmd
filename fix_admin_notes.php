<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS admin_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    author_id INT NOT NULL,
    body TEXT NOT NULL,
    pinned TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_author (author_id)
)");
echo "admin_notes table created\n";
