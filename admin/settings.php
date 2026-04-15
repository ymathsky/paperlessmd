<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/audit.php';
requireLogin();
requireAdmin();

$pageTitle = 'Global Settings';
$activeNav = '';

/* ── All supported timezones (grouped) ──────────────────────────────────── */
$tzGroups = [
    'United States' => [
        'America/New_York'    => 'Eastern Time (ET) — New York, Miami',
        'America/Chicago'     => 'Central Time (CT) — Chicago, Dallas ✦ Default',
        'America/Denver'      => 'Mountain Time (MT) — Denver, Phoenix',
        'America/Phoenix'     => 'Mountain Time no DST — Arizona',
        'America/Los_Angeles' => 'Pacific Time (PT) — Los Angeles, Seattle',
        'America/Anchorage'   => 'Alaska Time (AKT)',
        'Pacific/Honolulu'    => 'Hawaii Time (HST)',
    ],
    'Other' => [
        'UTC'               => 'UTC',
        'Europe/London'     => 'London (GMT/BST)',
        'Europe/Paris'      => 'Paris (CET/CEST)',
        'Asia/Manila'       => 'Manila (PST +8)',
        'Asia/Kolkata'      => 'India (IST +5:30)',
        'Australia/Sydney'  => 'Sydney (AEST/AEDT)',
    ],
];

/* ── Load current settings ───────────────────────────────────────────────── */
$settings = [];
foreach ($pdo->query("SELECT `key`, `value`, `label` FROM settings") as $row) {
    $settings[$row['key']] = $row;
}
$currentTz = $settings['timezone']['value'] ?? 'America/Chicago';

/* ── Handle POST save ────────────────────────────────────────────────────── */
$saved = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $newTz = trim($_POST['timezone'] ?? '');
    if (!$newTz || !@timezone_open($newTz)) {
        $error = 'Invalid timezone selected.';
    } else {
        $pdo->prepare("
            INSERT INTO settings (`key`, `value`, `label`)
            VALUES ('timezone', ?, 'Server / Display Timezone')
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
        ")->execute([$newTz]);
        auditLog($pdo, 'settings_update', 'settings', 0, 'timezone=' . $newTz, '');
        $currentTz = $newTz;
        // Apply immediately for the rest of this request
        date_default_timezone_set($currentTz);
        $saved = true;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-slate-400 mb-6 flex-wrap">
    <a href="<?= BASE_URL ?>/dashboard.php" class="hover:text-blue-600 font-medium">Dashboard</a>
    <i class="bi bi-chevron-right text-xs"></i>
    <span class="text-slate-700 font-semibold">Global Settings</span>
</nav>

<div class="max-w-2xl">

    <div class="flex items-center gap-3 mb-6">
        <div class="w-11 h-11 bg-blue-100 rounded-2xl grid place-items-center">
            <i class="bi bi-sliders2-vertical text-blue-600 text-xl"></i>
        </div>
        <div>
            <h2 class="text-2xl font-extrabold text-slate-800">Global Settings</h2>
            <p class="text-slate-500 text-sm">System-wide configuration for <?= h(PRACTICE_NAME) ?></p>
        </div>
    </div>

    <?php if ($saved): ?>
    <div class="mb-5 flex items-center gap-3 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm">
        <i class="bi bi-check-circle-fill flex-shrink-0"></i>
        Settings saved. Timezone is now <strong><?= h($currentTz) ?></strong>.
        Current server time: <strong><?= date('M j, Y g:i:s a T') ?></strong>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
        <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i> <?= h($error) ?>
    </div>
    <?php endif; ?>

    <form method="POST" class="space-y-5">
        <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

        <!-- Timezone -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div class="border-b border-slate-100 px-6 py-4 flex items-center gap-3">
                <i class="bi bi-clock-history text-indigo-500 text-lg"></i>
                <div>
                    <p class="font-bold text-slate-800 text-sm">Timezone</p>
                    <p class="text-xs text-slate-500">All dates and times displayed in the system will use this timezone.</p>
                </div>
            </div>
            <div class="px-6 py-5 space-y-3">
                <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1">Select Timezone</label>
                <select name="timezone"
                        class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-white
                               focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                    <?php foreach ($tzGroups as $groupLabel => $zones): ?>
                    <optgroup label="── <?= htmlspecialchars($groupLabel, ENT_QUOTES, 'UTF-8') ?>">
                        <?php foreach ($zones as $tzId => $tzLabel): ?>
                        <option value="<?= htmlspecialchars($tzId, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $currentTz === $tzId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tzLabel, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endforeach; ?>
                </select>

                <!-- Live preview -->
                <div class="flex items-center gap-2 bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 text-sm">
                    <i class="bi bi-clock text-slate-400"></i>
                    <span class="text-slate-500">Current server time in <strong><?= h($currentTz) ?></strong>:</span>
                    <span class="font-semibold text-slate-800 ml-auto"><?= date('D, M j, Y  g:i:s a T') ?></span>
                </div>
            </div>
        </div>

        <!-- Save -->
        <div class="flex items-center gap-4">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-2.5 bg-blue-600 hover:bg-blue-700
                           text-white font-bold rounded-xl transition-all shadow-sm text-sm">
                <i class="bi bi-floppy-fill"></i> Save Settings
            </button>
            <a href="<?= BASE_URL ?>/dashboard.php"
               class="text-sm text-slate-500 hover:text-slate-700 transition-colors">Cancel</a>
        </div>
    </form>

    <!-- Info panel: running migration -->
    <div class="mt-8 bg-amber-50 border border-amber-200 rounded-2xl p-5 text-sm text-amber-800">
        <p class="font-bold flex items-center gap-2 mb-1">
            <i class="bi bi-info-circle-fill"></i> First time setup?
        </p>
        <p>If you see a database error, run the
           <a href="<?= BASE_URL ?>/migrate_settings.php" class="font-bold underline hover:text-amber-900">
               settings migration
           </a> first to create the <code class="bg-amber-100 px-1 rounded">settings</code> table.
        </p>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
