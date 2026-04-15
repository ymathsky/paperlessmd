<?php
/**
 * Migration: Create settings table and seed default timezone
 * Run once: http://localhost/pd/migrate_settings.php
 */
require_once __DIR__ . '/includes/config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('DB connection failed: ' . $e->getMessage());
}

$pdo->exec("
    CREATE TABLE IF NOT EXISTS settings (
        `key`        VARCHAR(64)  NOT NULL PRIMARY KEY,
        `value`      TEXT         NOT NULL DEFAULT '',
        `label`      VARCHAR(120) NOT NULL DEFAULT '',
        `updated_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Seed defaults — INSERT IGNORE so re-running is safe
$seeds = [
    ['timezone', 'America/Chicago', 'Server / Display Timezone'],
];
$ins = $pdo->prepare("INSERT IGNORE INTO settings (`key`, `value`, `label`) VALUES (?, ?, ?)");
foreach ($seeds as $s) {
    $ins->execute($s);
}

echo '<p style="font-family:sans-serif;color:green;padding:2rem;">
      ✅ <strong>settings</strong> table created and seeded successfully.<br>
      Default timezone: <strong>America/Chicago</strong><br><br>
      <a href="' . BASE_URL . '/admin/settings.php">Go to Settings →</a>
      </p>';
