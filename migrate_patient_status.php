<?php
/**
 * migrate_patient_status.php
 * Adds status + discharged_at columns to patients table.
 * Run once: https://docs.md-officesupport.com/migrate_patient_status.php
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireAdmin();

$steps = [];

// Add status column
try {
    $pdo->exec("ALTER TABLE patients ADD COLUMN status ENUM('active','inactive','discharged') NOT NULL DEFAULT 'active' AFTER pcp");
    $steps[] = '✅ Added <code>status</code> column';
} catch (PDOException $e) {
    $steps[] = strpos($e->getMessage(), 'Duplicate column') !== false
        ? '⚠️ <code>status</code> column already exists'
        : '❌ Error adding status: ' . htmlspecialchars($e->getMessage());
}

// Add discharged_at column
try {
    $pdo->exec("ALTER TABLE patients ADD COLUMN discharged_at DATE NULL AFTER status");
    $steps[] = '✅ Added <code>discharged_at</code> column';
} catch (PDOException $e) {
    $steps[] = strpos($e->getMessage(), 'Duplicate column') !== false
        ? '⚠️ <code>discharged_at</code> column already exists'
        : '❌ Error adding discharged_at: ' . htmlspecialchars($e->getMessage());
}

// Backfill existing rows
$pdo->exec("UPDATE patients SET status = 'active' WHERE status IS NULL");
$steps[] = '✅ All existing patients set to <strong>active</strong>';

echo '<style>body{font-family:sans-serif;padding:2rem;max-width:600px}li{margin:.5rem 0}</style>';
echo '<h2>Patient Status Migration</h2><ul>';
foreach ($steps as $s) echo "<li>$s</li>";
echo '</ul><p><a href="' . (defined('BASE_URL') ? BASE_URL : '') . '/patients.php">← Back to Patients</a></p>';
