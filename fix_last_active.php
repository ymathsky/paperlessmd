<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';

$pdo->exec("ALTER TABLE staff ADD COLUMN last_active_at TIMESTAMP NULL DEFAULT NULL");
echo "last_active_at column added to staff\n";
