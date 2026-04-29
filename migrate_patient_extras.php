<?php
/**
 * Migration: add extra patient fields
 *   - race, insurance_id, insurance_photo (front), insurance_photo_back
 *   - sss_photo (SSS / gov-ID card photo)
 *   - pharmacy_name, pharmacy_phone, pharmacy_address
 *
 * Run once on production (idempotent — skips columns that already exist).
 * Admin-only.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireAdmin();

$messages = [];
$success  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf($_POST['csrf_token'] ?? '')) {
    $cols = [
        ['race',                 "VARCHAR(120) NULL DEFAULT NULL AFTER pcp"],
        ['insurance_id',         "VARCHAR(120) NULL DEFAULT NULL AFTER insurance"],
        ['insurance_photo',      "MEDIUMTEXT   NULL DEFAULT NULL AFTER insurance_id"],
        ['insurance_photo_back', "MEDIUMTEXT   NULL DEFAULT NULL AFTER insurance_photo"],
        ['sss_photo',            "MEDIUMTEXT   NULL DEFAULT NULL AFTER insurance_photo_back"],
        ['pharmacy_name',        "VARCHAR(200) NULL DEFAULT NULL AFTER sss_photo"],
        ['pharmacy_phone',       "VARCHAR(50)  NULL DEFAULT NULL AFTER pharmacy_name"],
        ['pharmacy_address',     "VARCHAR(300) NULL DEFAULT NULL AFTER pharmacy_phone"],
    ];

    try {
        // Fetch existing columns once
        $existing = [];
        foreach ($pdo->query("SHOW COLUMNS FROM patients") as $r) {
            $existing[] = $r['Field'];
        }

        foreach ($cols as [$col, $def]) {
            if (in_array($col, $existing, true)) {
                $messages[] = ['ok' => false, 'text' => "Column <code>$col</code> already exists — skipped."];
            } else {
                $pdo->exec("ALTER TABLE patients ADD COLUMN `$col` $def");
                $messages[] = ['ok' => true,  'text' => "Added column <code>$col</code>."];
            }
        }
        $success = true;
    } catch (PDOException $e) {
        $messages[] = ['ok' => false, 'text' => 'Database error: ' . htmlspecialchars($e->getMessage())];
    }
}

$pageTitle = 'Migration: Patient Extra Fields';
$activeNav = '';
include __DIR__ . '/includes/header.php';
?>

<div class="max-w-xl mx-auto mt-8">
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-8">
        <h2 class="text-xl font-extrabold text-slate-800 mb-1">Patient Extra Fields Migration</h2>
        <p class="text-slate-500 text-sm mb-6">
            Adds <code class="bg-slate-100 px-1 rounded">race</code>,
            <code class="bg-slate-100 px-1 rounded">insurance_id</code>,
            insurance &amp; SSS card photos, and
            <code class="bg-slate-100 px-1 rounded">pharmacy_*</code> columns to the <code>patients</code> table.
            Safe to run multiple times.
        </p>

        <?php if ($messages): ?>
        <div class="mb-6 space-y-2">
            <?php foreach ($messages as $m): ?>
            <div class="flex items-start gap-2 text-sm <?= $m['ok'] ? 'text-emerald-700' : 'text-amber-700' ?>">
                <i class="bi <?= $m['ok'] ? 'bi-check-circle-fill text-emerald-500' : 'bi-info-circle text-amber-500' ?> mt-0.5 shrink-0"></i>
                <span><?= $m['text'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if ($success): ?>
        <div class="flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl mb-6 text-sm font-semibold">
            <i class="bi bi-check2-circle text-emerald-600"></i> Migration complete.
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <button type="submit"
                    class="w-full flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700
                           text-white font-bold px-6 py-3 rounded-xl transition-all shadow-sm">
                <i class="bi bi-database-add"></i> Run Migration
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
