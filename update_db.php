<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireAdmin();

$steps  = [];
$errors = [];

try {
    // 1. Add company column to patients table
    $stmt = $pdo->query("SHOW COLUMNS FROM patients LIKE 'company'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE patients ADD COLUMN company VARCHAR(100) NOT NULL DEFAULT 'Beyond Wound Care Inc.' AFTER address");
        $steps[] = "Added `company` column to `patients` table.";
    } else {
        $steps[] = "`company` column already exists in `patients`.";
    }

} catch (PDOException $e) {
    $errors[] = "Database error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Database Migration</title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-slate-50 p-10 font-sans">
    <div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
        <h1 class="text-2xl font-bold text-slate-800 mb-6 border-b pb-4"><i class="bi bi-database-check text-blue-600 mr-2"></i> Database Migration Status</h1>
        
        <?php if ($errors): ?>
            <div class="bg-red-50 text-red-700 p-4 rounded-xl border border-red-200 mb-6">
                <h3 class="font-bold flex items-center gap-2 mb-2"><i class="bi bi-exclamation-triangle"></i> Errors occurred</h3>
                <ul class="list-disc pl-5 space-y-1 text-sm">
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($steps): ?>
            <div class="bg-emerald-50 text-emerald-800 p-4 rounded-xl border border-emerald-200 mb-6">
                <h3 class="font-bold flex items-center gap-2 mb-2"><i class="bi bi-check-circle"></i> Success Steps</h3>
                <ul class="list-disc pl-5 space-y-1 text-sm">
                    <?php foreach ($steps as $s): ?>
                        <li><?= htmlspecialchars($s) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="pt-4 border-t border-slate-100 flex gap-4">
            <a href="dashboard.php" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-xl transition shadow-sm">
                Return to Dashboard
            </a>
            <a href="migrate_messages.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold px-6 py-2.5 rounded-xl transition shadow-sm">
                Run Messages Migration Also
            </a>
        </div>
    </div>
</body>
</html>