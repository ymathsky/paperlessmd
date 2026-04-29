<?php
/**
 * Migration: add email column to staff table.
 * Run once on production, then leave in place (idempotent).
 * Admin-only.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireAdmin();

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    try {
        // Check if column already exists
        $col = $pdo->query("SHOW COLUMNS FROM staff LIKE 'email'")->fetch();
        if ($col) {
            $message = 'Column <code>email</code> already exists — nothing to do.';
            $success = true;
        } else {
            $pdo->exec("ALTER TABLE staff ADD COLUMN email VARCHAR(150) NULL DEFAULT NULL AFTER full_name");
            $message = 'Migration complete. <code>staff.email</code> column added.';
            $success = true;
        }
    } catch (PDOException $e) {
        $message = 'Error: ' . htmlspecialchars($e->getMessage());
        $success = false;
    }
}

// Show current columns
$cols = [];
try {
    $rows = $pdo->query("SHOW COLUMNS FROM staff")->fetchAll();
    foreach ($rows as $r) { $cols[] = $r['Field']; }
} catch (PDOException $e) {}

$pageTitle = 'Migration: Staff Email Column';
$activeNav = '';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-xl mx-auto mt-8">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8">
        <h2 class="text-xl font-extrabold text-slate-800 mb-1">Staff Email Migration</h2>
        <p class="text-slate-500 text-sm mb-6">
            Adds an <code class="bg-slate-100 px-1 rounded">email</code> column to the
            <code class="bg-slate-100 px-1 rounded">staff</code> table.
            Required for email notifications.
        </p>

        <div class="mb-5 p-4 rounded-xl bg-slate-50 border border-slate-200 text-sm">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Current staff columns</div>
            <code class="text-slate-700"><?= h(implode(', ', $cols)) ?></code>
        </div>

        <?php if ($message): ?>
        <div class="mb-5 p-4 rounded-xl text-sm font-medium <?= $success ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
            <?= $message ?>
        </div>
        <?php endif; ?>

        <?php if (!$success): ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <button type="submit"
                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 rounded-xl
                           transition-colors text-sm">
                Run Migration
            </button>
        </form>
        <?php else: ?>
        <a href="<?= BASE_URL ?>/admin/users.php"
           class="block text-center w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-xl
                  transition-colors text-sm">
            Go to Manage Staff &rarr;
        </a>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
