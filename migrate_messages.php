<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireAdmin();

$steps  = [];
$errors = [];

// ── 1. messages table ────────────────────────────────────────────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS messages (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            from_user_id INT NOT NULL,
            to_user_id   INT NULL    COMMENT 'NULL = all-staff broadcast',
            subject      VARCHAR(255) NOT NULL DEFAULT '',
            body         TEXT         NOT NULL,
            parent_id    INT NULL     COMMENT 'NULL = root/thread start',
            created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_from   (from_user_id),
            INDEX idx_to     (to_user_id),
            INDEX idx_parent (parent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $steps[] = '✓ <code>messages</code> table ready';
} catch (PDOException $e) {
    $errors[] = 'messages: ' . $e->getMessage();
}

// ── 2. message_reads table ───────────────────────────────────────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS message_reads (
            id         INT AUTO_INCREMENT PRIMARY KEY,
            message_id INT NOT NULL,
            user_id    INT NOT NULL,
            read_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_msg_user (message_id, user_id),
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $steps[] = '✓ <code>message_reads</code> table ready';
} catch (PDOException $e) {
    $errors[] = 'message_reads: ' . $e->getMessage();
}

// ── 3. message_attachments table ─────────────────────────────────────────────
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS message_attachments (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            message_id    INT          NOT NULL,
            original_name VARCHAR(255) NOT NULL,
            stored_name   VARCHAR(255) NOT NULL,
            file_size     INT UNSIGNED NOT NULL DEFAULT 0,
            mime_type     VARCHAR(100) NOT NULL DEFAULT '',
            created_at    DATETIME     DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_msg (message_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $steps[] = '✓ <code>message_attachments</code> table ready';
} catch (PDOException $e) {
    $errors[] = 'message_attachments: ' . $e->getMessage();
}

// ── 4. Upload directory ──────────────────────────────────────────────────────
$dir = __DIR__ . '/uploads/message_files';
if (!is_dir($dir)) {
    if (mkdir($dir, 0755, true)) {
        $steps[] = '✓ Created <code>uploads/message_files/</code>';
    } else {
        $errors[] = 'Could not create uploads/message_files/';
    }
} else {
    $steps[] = '✓ <code>uploads/message_files/</code> already exists';
}

// ── 5. .htaccess (block script execution in uploads dir) ─────────────────────
$ht = $dir . '/.htaccess';
if (!file_exists($ht)) {
    file_put_contents($ht, "Options -Indexes\nphp_flag engine off\n");
    $steps[] = '✓ Created <code>.htaccess</code> in uploads/message_files/';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Migrate: Messaging — <?= APP_NAME ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-6">
<div class="bg-white rounded-2xl shadow-xl p-8 w-full max-w-lg space-y-3">
    <h1 class="text-xl font-bold text-slate-800 mb-4 flex items-center gap-2">
        <span class="text-2xl">💬</span> Migration: Messaging System
    </h1>
    <?php foreach ($steps as $s): ?>
    <p class="text-sm text-emerald-700 bg-emerald-50 rounded-lg px-3 py-2"><?= $s ?></p>
    <?php endforeach; ?>
    <?php foreach ($errors as $e): ?>
    <p class="text-sm text-red-700 bg-red-50 rounded-lg px-3 py-2">✗ <?= h($e) ?></p>
    <?php endforeach; ?>
    <?php if (!$errors): ?>
    <p class="text-sm font-semibold text-slate-700 bg-blue-50 rounded-lg px-3 py-2 mt-4">
        All done!
        <a href="<?= BASE_URL ?>/messages.php" class="text-blue-600 underline ml-1">Go to Messages →</a>
    </p>
    <?php endif; ?>
</div>
</body>
</html>
