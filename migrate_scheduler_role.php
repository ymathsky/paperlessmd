<?php
/**
 * Migration: add 'scheduler' to staff.role ENUM
 *
 * Run once on production, then delete or leave in place (it's idempotent).
 * Admin-only; safe to access via HTTPS.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireAdmin();

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    try {
        $pdo->exec("ALTER TABLE staff MODIFY COLUMN role ENUM('admin','ma','billing','scheduler') NOT NULL DEFAULT 'ma'");
        $message = 'Migration complete. The <code>scheduler</code> role is now available.';
        $success = true;
    } catch (PDOException $e) {
        $message = 'Error: ' . htmlspecialchars($e->getMessage());
        $success = false;
    }
}

// Check current ENUM values
$current = '';
try {
    $row = $pdo->query("SHOW COLUMNS FROM staff LIKE 'role'")->fetch(PDO::FETCH_ASSOC);
    $current = $row['Type'] ?? 'unknown';
} catch (PDOException $e) {
    $current = 'Could not read: ' . htmlspecialchars($e->getMessage());
}

$pageTitle  = 'Migration: Scheduler Role';
$activeNav  = '';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-xl mx-auto mt-8">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8">
        <h2 class="text-xl font-extrabold text-slate-800 mb-1">Scheduler Role Migration</h2>
        <p class="text-slate-500 text-sm mb-6">Adds <code class="bg-slate-100 px-1 rounded">scheduler</code> to the <code class="bg-slate-100 px-1 rounded">staff.role</code> ENUM column.</p>

        <div class="mb-5 p-4 rounded-xl bg-slate-50 border border-slate-200 text-sm">
            <div class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-1">Current ENUM</div>
            <code class="text-slate-700"><?= h($current) ?></code>
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
                    class="w-full bg-violet-600 hover:bg-violet-700 text-white font-semibold py-2.5 rounded-xl
                           transition-colors active:scale-95 text-sm">
                Run Migration
            </button>
        </form>
        <?php else: ?>
        <a href="<?= BASE_URL ?>/admin/roles.php"
           class="block text-center w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-xl
                  transition-colors text-sm">
            View Roles &amp; Permissions
        </a>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
