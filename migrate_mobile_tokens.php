<?php
/**
 * Creates the mobile_tokens table needed by the mobile API.
 * Run once: php c:/xampp/htdocs/pd/migrate_mobile_tokens.php
 */
$pdo = new PDO('mysql:host=localhost;dbname=pd_paperless;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$pdo->exec("
    CREATE TABLE IF NOT EXISTS mobile_tokens (
        id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        staff_id   INT UNSIGNED NOT NULL,
        token      VARCHAR(64)  NOT NULL UNIQUE,
        expires_at DATETIME     NOT NULL,
        created_at DATETIME     DEFAULT NOW(),
        INDEX idx_token (token),
        INDEX idx_staff (staff_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

echo "mobile_tokens table created (or already exists).\n";
