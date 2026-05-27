<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';

$pdo->exec("ALTER TABLE messages ADD COLUMN subject VARCHAR(300) DEFAULT NULL AFTER to_user_id");
echo "subject column added\n";
