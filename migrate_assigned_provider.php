<?php
// Minimal CLI migration — run via: php migrate_assigned_provider.php
require_once __DIR__ . '/includes/db.php';
try {
    $cols = $pdo->query("SHOW COLUMNS FROM patients LIKE 'assigned_provider'")->fetchAll();
    if (empty($cols)) {
        $pdo->exec("ALTER TABLE patients ADD COLUMN assigned_provider VARCHAR(150) NULL DEFAULT NULL AFTER assigned_ma");
        echo "added\n";
    } else {
        echo "exists\n";
    }
} catch (PDOException $e) {
    echo "error: " . $e->getMessage() . "\n";
}
