<?php
/**
 * One-time migration: add provider_signature, provider_name, provider_signed_at
 * to form_submissions if they don't exist yet.
 * Run once, then delete this file.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireAdmin();

$existing = array_column($pdo->query('SHOW COLUMNS FROM form_submissions')->fetchAll(), 'Field');

$toAdd = [];
if (!in_array('provider_signature', $existing)) {
    $toAdd[] = 'ADD COLUMN provider_signature MEDIUMTEXT NULL DEFAULT NULL';
}
if (!in_array('provider_name', $existing)) {
    $toAdd[] = 'ADD COLUMN provider_name VARCHAR(200) NULL DEFAULT NULL';
}
if (!in_array('provider_signed_at', $existing)) {
    $toAdd[] = 'ADD COLUMN provider_signed_at DATETIME NULL DEFAULT NULL';
}

if ($toAdd) {
    $pdo->exec('ALTER TABLE form_submissions ' . implode(', ', $toAdd));
    echo '<pre>Added columns: ' . implode(', ', $toAdd) . '</pre>';
} else {
    echo '<pre>All columns already exist — nothing to do.</pre>';
}
echo '<p>Done. You can delete this file.</p>';
