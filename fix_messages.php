<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';

// Recreate messages table with correct column names matching api/messages.php
$pdo->exec("DROP TABLE IF EXISTS message_reads");
$pdo->exec("DROP TABLE IF EXISTS message_attachments");
$pdo->exec("DROP TABLE IF EXISTS messages");

$pdo->exec("CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id INT NOT NULL,
    to_user_id INT NULL DEFAULT NULL,
    body TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_from (from_user_id),
    INDEX idx_to (to_user_id)
)");

$pdo->exec("CREATE TABLE message_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    user_id INT NOT NULL,
    read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_read (message_id, user_id)
)");

$pdo->exec("CREATE TABLE message_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    message_id INT NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NULL,
    file_size INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

echo "Messages tables recreated with correct schema.\n";
