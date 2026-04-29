<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireAdmin();

// ── Accounts to seed ─────────────────────────────────────────────────────────
// Default password for all new accounts (users should change after first login)
const SEED_PASSWORD = 'Welcome1!';

$accounts = [
    // MAs
    ['username' => 'mkhan',        'full_name' => 'Mohammed Khan',       'role' => 'ma',    'phone' => '224.386.6731', 'email' => 'Khandaniyal6118@icloud.com'],
    ['username' => 'mhassan',      'full_name' => 'Mahdi Hassan',        'role' => 'ma',    'phone' => '224.578.1781', 'email' => 'mahdihassan721@gmail.com'],
    ['username' => 'agutierrez',   'full_name' => 'Arnel Gutierrez',     'role' => 'ma',    'phone' => '815.814.8428', 'email' => 'arnelg571@gmail.com'],
    ['username' => 'rdelacruz',    'full_name' => 'Raymond De La Cruz',  'role' => 'ma',    'phone' => '630.344.2115', 'email' => 'raymond.d100@yahoo.com'],
    ['username' => 'shassan',      'full_name' => 'Shariff Hassan',      'role' => 'ma',    'phone' => '224.578.9485', 'email' => 'Sharif.Hassan19@gmail.com'],
    ['username' => 'sirfranullah', 'full_name' => 'Saiyed Irfranullah',  'role' => 'ma',    'phone' => '630.943.8473', 'email' => 'Tirfan786@hotmail.com'],
    // Admin
    ['username' => 'acasten',      'full_name' => 'Ashley Casten',       'role' => 'admin', 'phone' => '847.262.6906', 'email' => 'ACasten@BeyondWoundCare.com'],
];

$results  = [];
$executed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'seed') {
    verifyCsrf();
    $executed = true;
    $stmt = $pdo->prepare("INSERT INTO staff (username, full_name, role, password_hash, active)
                           VALUES (?, ?, ?, ?, 1)
                           ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), role=VALUES(role)");
    foreach ($accounts as $a) {
        try {
            $hash  = password_hash(SEED_PASSWORD, PASSWORD_DEFAULT);
            $stmt->execute([$a['username'], $a['full_name'], $a['role'], $hash]);
            $rowCount = $stmt->rowCount();
            // rowCount: 1 = inserted, 2 = updated (duplicate), 0 = identical row (no change)
            if ($rowCount === 1) {
                $results[$a['username']] = ['ok', 'Created'];
            } elseif ($rowCount === 2) {
                $results[$a['username']] = ['warn', 'Already existed — name/role updated, password reset to default'];
            } else {
                $results[$a['username']] = ['skip', 'No change (identical record already exists)'];
            }
        } catch (PDOException $e) {
            $results[$a['username']] = ['err', $e->getMessage()];
        }
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bulk Staff Registration</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-slate-50 min-h-screen p-6">
<div class="max-w-3xl mx-auto">

    <div class="flex items-center gap-3 mb-6">
        <a href="<?= BASE_URL ?>/admin/users.php" class="text-slate-400 hover:text-indigo-600 transition-colors">
            <i class="bi bi-arrow-left text-lg"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-slate-800">Bulk Staff Registration</h1>
            <p class="text-slate-500 text-sm">One-time seed — <?= count($accounts) ?> accounts (<?= count(array_filter($accounts, fn($a) => $a['role']==='ma')) ?> MAs + <?= count(array_filter($accounts, fn($a) => $a['role']==='admin')) ?> Admin)</p>
        </div>
    </div>

    <?php if (!$executed): ?>
    <!-- Preview -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-slate-100 bg-slate-50">
            <p class="text-sm font-semibold text-slate-700">Accounts to be created</p>
            <p class="text-xs text-slate-500 mt-0.5">Default password for all: <code class="bg-amber-50 text-amber-700 px-1.5 py-0.5 rounded font-mono font-bold"><?= SEED_PASSWORD ?></code> — users should change this after first login.</p>
        </div>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100 text-left">
                    <th class="px-5 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide">Full Name</th>
                    <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide">Username</th>
                    <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide">Role</th>
                    <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide">Phone</th>
                    <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide">Email</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($accounts as $a): ?>
                <tr class="hover:bg-slate-50/60">
                    <td class="px-5 py-3 font-semibold text-slate-800"><?= h($a['full_name']) ?></td>
                    <td class="px-4 py-3 font-mono text-indigo-600 text-xs"><?= h($a['username']) ?></td>
                    <td class="px-4 py-3">
                        <?php if ($a['role'] === 'admin'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">
                            <i class="bi bi-shield-fill mr-1"></i>Admin
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                            <i class="bi bi-person-fill mr-1"></i>MA
                        </span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-slate-500"><?= h($a['phone']) ?></td>
                    <td class="px-4 py-3 text-slate-500"><?= h($a['email']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <form method="POST">
        <?php $tok = csrfToken(); ?>
        <input type="hidden" name="csrf_token" value="<?= h($tok) ?>">
        <input type="hidden" name="action" value="seed">
        <div class="flex items-center gap-3">
            <button type="submit"
                    class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white font-bold rounded-xl transition-all shadow-sm">
                <i class="bi bi-people-fill"></i> Create All Accounts
            </button>
            <a href="<?= BASE_URL ?>/admin/users.php"
               class="px-5 py-3 text-slate-600 hover:text-slate-800 font-semibold rounded-xl transition-colors">
                Cancel
            </a>
        </div>
    </form>

    <?php else: ?>
    <!-- Results -->
    <?php
    $okCount   = count(array_filter($results, fn($r) => $r[0] === 'ok'));
    $warnCount = count(array_filter($results, fn($r) => $r[0] === 'warn'));
    $errCount  = count(array_filter($results, fn($r) => $r[0] === 'err'));
    ?>
    <div class="mb-5 flex items-center gap-3 <?= $errCount ? 'bg-red-50 border-red-200 text-red-700' : 'bg-emerald-50 border-emerald-200 text-emerald-700' ?> border px-4 py-3 rounded-xl text-sm">
        <i class="bi bi-<?= $errCount ? 'x-circle-fill' : 'check-circle-fill' ?> flex-shrink-0"></i>
        <?= $okCount ?> account<?= $okCount !== 1 ? 's' : '' ?> created<?= $warnCount ? ", $warnCount updated" : '' ?><?= $errCount ? ", $errCount error(s)" : '' ?>.
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-6">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100 text-left">
                    <th class="px-5 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide">Full Name</th>
                    <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide">Username</th>
                    <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide">Role</th>
                    <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide">Result</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($accounts as $a):
                    $r = $results[$a['username']] ?? ['skip', 'Not processed'];
                    [$status, $msg] = $r;
                ?>
                <tr>
                    <td class="px-5 py-3 font-semibold text-slate-800"><?= h($a['full_name']) ?></td>
                    <td class="px-4 py-3 font-mono text-indigo-600 text-xs"><?= h($a['username']) ?></td>
                    <td class="px-4 py-3">
                        <?php if ($a['role'] === 'admin'): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">Admin</span>
                        <?php else: ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">MA</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <?php if ($status === 'ok'): ?>
                        <span class="inline-flex items-center gap-1 text-emerald-700 font-semibold"><i class="bi bi-check-circle-fill"></i> <?= h($msg) ?></span>
                        <?php elseif ($status === 'warn'): ?>
                        <span class="inline-flex items-center gap-1 text-amber-600 font-semibold"><i class="bi bi-exclamation-circle-fill"></i> <?= h($msg) ?></span>
                        <?php elseif ($status === 'skip'): ?>
                        <span class="inline-flex items-center gap-1 text-slate-400"><i class="bi bi-dash-circle"></i> <?= h($msg) ?></span>
                        <?php else: ?>
                        <span class="inline-flex items-center gap-1 text-red-600 font-semibold"><i class="bi bi-x-circle-fill"></i> <?= h($msg) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <?php if (!$errCount): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl px-5 py-4 mb-6 text-sm text-amber-800">
        <p class="font-semibold mb-1"><i class="bi bi-info-circle-fill mr-1"></i>Default Password</p>
        <p>All newly created accounts have the password: <code class="bg-white border border-amber-200 px-2 py-0.5 rounded font-mono font-bold"><?= SEED_PASSWORD ?></code></p>
        <p class="mt-1 text-amber-700">Share this with each staff member and ask them to change it via their Profile page after first login.</p>
    </div>
    <?php endif; ?>

    <div class="flex items-center gap-3">
        <a href="<?= BASE_URL ?>/admin/users.php"
           class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl transition-all shadow-sm">
            <i class="bi bi-people-fill"></i> View All Staff
        </a>
        <?php if ($errCount): ?>
        <form method="POST">
            <?php $tok = csrfToken(); ?>
            <input type="hidden" name="csrf_token" value="<?= h($tok) ?>">
            <input type="hidden" name="action" value="seed">
            <button type="submit" class="px-5 py-3 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl transition-colors">
                <i class="bi bi-arrow-repeat"></i> Retry
            </button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>
</body>
</html>
