<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$out = [];

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS patient_medications (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        patient_id   INT NOT NULL,
        med_name     VARCHAR(255) NOT NULL,
        med_frequency VARCHAR(100) NOT NULL DEFAULT '',
        status       ENUM('active','discontinued') NOT NULL DEFAULT 'active',
        sort_order   SMALLINT NOT NULL DEFAULT 0,
        added_by     INT NULL,
        updated_by   INT NULL,
        added_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (added_by)   REFERENCES staff(id) ON DELETE SET NULL,
        FOREIGN KEY (updated_by) REFERENCES staff(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $out[] = '✓ patient_medications table created (or already exists).';

    $pdo->exec("CREATE TABLE IF NOT EXISTS medication_history (
        id                 INT AUTO_INCREMENT PRIMARY KEY,
        medication_id      INT NOT NULL,
        patient_id         INT NOT NULL,
        action             ENUM('added','modified','discontinued','reactivated','removed') NOT NULL,
        prev_name          VARCHAR(255) NULL,
        prev_frequency     VARCHAR(100) NULL,
        prev_status        ENUM('active','discontinued') NULL,
        new_name           VARCHAR(255) NULL,
        new_frequency      VARCHAR(100) NULL,
        new_status         ENUM('active','discontinued') NULL,
        changed_by         INT NULL,
        form_submission_id INT NULL,
        changed_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (medication_id)      REFERENCES patient_medications(id) ON DELETE CASCADE,
        FOREIGN KEY (patient_id)         REFERENCES patients(id) ON DELETE CASCADE,
        FOREIGN KEY (changed_by)         REFERENCES staff(id) ON DELETE SET NULL,
        FOREIGN KEY (form_submission_id) REFERENCES form_submissions(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $out[] = '✓ medication_history table created (or already exists).';

} catch (PDOException $e) {
    $out[] = '✗ Error: ' . $e->getMessage();
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Migrate Medications — <?= APP_NAME ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Inter','system-ui','sans-serif']}}}}</script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
</head>
<body class="font-sans bg-slate-50 min-h-screen flex items-center justify-center p-8">
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-8 max-w-lg w-full">
    <h1 class="text-xl font-extrabold text-slate-800 mb-6">Medication Reconciliation Migration</h1>
    <ul class="space-y-2 mb-6">
        <?php foreach ($out as $line): ?>
        <li class="text-sm <?= str_starts_with($line, '✓') ? 'text-emerald-700' : 'text-red-700' ?> font-medium"><?= h($line) ?></li>
        <?php endforeach; ?>
    </ul>
    <a href="<?= BASE_URL ?>/dashboard.php"
       class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl text-sm transition-colors">
        ← Back to Dashboard
    </a>
</div>
</body>
</html>
