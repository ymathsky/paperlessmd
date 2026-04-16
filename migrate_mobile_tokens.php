<?php
/**
 * Creates the mobile_tokens table needed by the mobile API.
 * Visit once in browser: https://yourdomain.com/migrate_mobile_tokens.php
 * Then delete this file.
 */
header('Content-Type: text/plain');
try {
    require_once __DIR__ . '/includes/db.php';
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
    echo "OK: mobile_tokens table created (or already exists).\n";
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
