<?php
/**
 * migrate_audit.php  —  Run once to create the audit_log table.
 * Safe to re-run; uses CREATE TABLE IF NOT EXISTS.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$sql = "
CREATE TABLE IF NOT EXISTS audit_log (
    id           BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id      INT           NULL,
    username     VARCHAR(100)  NULL,
    user_role    VARCHAR(20)   NULL,
    action       VARCHAR(50)   NOT NULL,
    target_type  VARCHAR(50)   NULL,
    target_id    INT           NULL,
    target_label VARCHAR(255)  NULL,
    ip_address   VARCHAR(45)   NULL,
    details      TEXT          NULL,
    created_at   TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action  (action),
    INDEX idx_target  (target_type, target_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

try {
    $pdo->exec($sql);
    echo "<p style='font-family:sans-serif;color:green;'>✔ audit_log table is ready.</p>";
} catch (PDOException $e) {
    echo "<p style='font-family:sans-serif;color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
