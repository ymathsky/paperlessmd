<?php
/**
 * Migration: add 'pcc' (Patient Care Coordinator) to staff.role ENUM
 * and create the PCC staff account if it doesn't already exist.
 *
 * Run once on production, then leave in place (idempotent).
 * Admin-only.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireAdmin();

$messages = [];
$success  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    try {
        // Step 1 — Extend ENUM
        $pdo->exec("ALTER TABLE staff MODIFY COLUMN role
                    ENUM('admin','ma','billing','scheduler','provider','pcc')
                    NOT NULL DEFAULT 'ma'");
        $messages[] = ['ok' => true,  'text' => "staff.role ENUM now includes 'pcc'."];

        // Step 2 — Create PCC account (skip if username already exists)
        $exists = (int)$pdo->query("SELECT COUNT(*) FROM staff WHERE username = 'pcc'")->fetchColumn();
        if ($exists === 0) {
            $hash = password_hash('paperless2026', PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO staff (username, full_name, email, role, password_hash, active)
                           VALUES (?, ?, ?, 'pcc', ?, 1)")
                ->execute(['pcc', 'Patient Care Coordinator', null, $hash]);
            $messages[] = ['ok' => true,  'text' => "PCC account created — username: <strong>pcc</strong>, temporary password: <strong>paperless2026</strong>. Please change after first login."];
        } else {
            $messages[] = ['ok' => false, 'text' => "Account with username 'pcc' already exists — skipped creation."];
        }

        $success = true;
    } catch (PDOException $e) {
        $messages[] = ['ok' => false, 'text' => 'Database error: ' . htmlspecialchars($e->getMessage())];
    }
}

// Show current ENUM
$current = 'unknown';
try {
    $row     = $pdo->query("SHOW COLUMNS FROM staff LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
    $current = $row['Type'] ?? 'unknown';
} catch (PDOException $e) {
    $current = 'Could not read: ' . htmlspecialchars($e->getMessage());
}

$pageTitle = 'Migration: PCC Role';
$activeNav = '';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-xl mx-auto mt-8">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8">
        <h2 class="text-xl font-extrabold text-slate-800 mb-1">PCC Role Migration</h2>
        <p class="text-slate-500 text-sm mb-6">
            Adds <code class="bg-slate-100 px-1 rounded">pcc</code> to the
            <code class="bg-slate-100 px-1 rounded">staff.role</code> ENUM and creates the
            <strong>Patient Care Coordinator</strong> account.
        </p>

        <div class="mb-5 p-4 rounded-xl bg-slate-50 border border-slate-200 text-sm">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Current ENUM</div>
            <code class="text-slate-700"><?= h($current) ?></code>
        </div>

        <?php foreach ($messages as $msg): ?>
        <div class="mb-3 p-3 rounded-xl text-sm font-medium
                    <?= $msg['ok'] ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                                   : 'bg-amber-50 text-amber-700 border border-amber-200' ?>">
            <?= $msg['text'] ?>
        </div>
        <?php endforeach; ?>

        <?php if (!$success): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <button type="submit"
                    class="w-full bg-teal-600 hover:bg-teal-700 text-white font-semibold py-2.5 rounded-xl
                           transition-colors active:scale-95 text-sm">
                Run Migration
            </button>
        </form>
        <?php else: ?>
        <a href="<?= BASE_URL ?>/admin/users.php"
           class="block text-center w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-xl
                  transition-colors text-sm mt-2">
            View Staff List
        </a>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
