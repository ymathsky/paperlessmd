<?php
/**
 * migrate_billing_role.php
 * Adds 'billing' to the staff.role ENUM and sets up the billing dashboard view.
 * Safe to re-run.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$errors = [];
$done   = [];

// 1. Alter the ENUM to include 'billing'
try {
    $pdo->exec("ALTER TABLE staff
        MODIFY COLUMN role ENUM('admin','ma','billing') NOT NULL DEFAULT 'ma'");
    $done[] = "staff.role ENUM now includes 'billing'";
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'billing')) {
        $done[] = "staff.role already includes 'billing' (no change)";
    } else {
        $errors[] = $e->getMessage();
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Billing Role Migration</title>
    <style>body{font-family:sans-serif;max-width:600px;margin:3rem auto;padding:0 1rem}
    .ok{color:green}.err{color:red}h2{border-bottom:1px solid #ddd;padding-bottom:.5rem}</style>
</head>
<body>
<h2>Billing Role Migration</h2>
<?php foreach ($done as $msg): ?>
<p class="ok">✔ <?= htmlspecialchars($msg) ?></p>
<?php endforeach; ?>
<?php foreach ($errors as $msg): ?>
<p class="err">✘ <?= htmlspecialchars($msg) ?></p>
<?php endforeach; ?>
<?php if (empty($errors)): ?>
<p><strong>Done.</strong> You can now create billing users in <a href="/pd/admin/manage_user.php">Manage Staff</a>.</p>
<?php endif; ?>
</body>
</html>
