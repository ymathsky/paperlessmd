<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireAdmin();

/* ── Filters ─────────────────────────────────────────────── */
$filterAction = $_GET['action']  ?? '';
$filterUser   = $_GET['user']    ?? '';
$filterFrom   = $_GET['from']    ?? '';
$filterTo     = $_GET['to']      ?? '';
$filterSearch = trim($_GET['q']  ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 50;
$offset       = ($page - 1) * $perPage;

/* ── Build WHERE ─────────────────────────────────────────── */
$where  = [];
$params = [];

if ($filterAction !== '') {
    $where[]  = 'al.action = ?';
    $params[] = $filterAction;
}
if ($filterUser !== '') {
    $where[]  = 'al.username = ?';
    $params[] = $filterUser;
}
if ($filterFrom !== '') {
    $where[]  = 'DATE(al.created_at) >= ?';
    $params[] = $filterFrom;
}
if ($filterTo !== '') {
    $where[]  = 'DATE(al.created_at) <= ?';
    $params[] = $filterTo;
}
if ($filterSearch !== '') {
    $like     = '%' . $filterSearch . '%';
    $where[]  = '(al.target_label LIKE ? OR al.username LIKE ? OR al.ip_address LIKE ? OR al.details LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ── Count ───────────────────────────────────────────────── */
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM audit_log al $whereSQL");
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

/* ── Rows ────────────────────────────────────────────────── */
$rowStmt = $pdo->prepare("
    SELECT al.*
    FROM audit_log al
    $whereSQL
    ORDER BY al.created_at DESC, al.id DESC
    LIMIT $perPage OFFSET $offset
");
$rowStmt->execute($params);
$rows = $rowStmt->fetchAll();

/* ── User list for filter dropdown ───────────────────────── */
$users = $pdo->query("SELECT DISTINCT username FROM audit_log WHERE username IS NOT NULL ORDER BY username")->fetchAll(PDO::FETCH_COLUMN);

/* ── CSV export (before HTML output) ─────────────────────── */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $expStmt = $pdo->prepare("SELECT * FROM audit_log al $whereSQL ORDER BY al.created_at DESC, al.id DESC");
    $expStmt->execute($params);
    $expRows = $expStmt->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="audit_log_' . date('Ymd_His') . '.csv"');
    header('Pragma: no-cache');
    $fh = fopen('php://output', 'w');
    fputcsv($fh, ['ID', 'Timestamp', 'User', 'Role', 'Action', 'Target Type', 'Target ID', 'Target Label', 'IP Address', 'Details']);
    foreach ($expRows as $r) {
        fputcsv($fh, [$r['id'], $r['created_at'], $r['username'], $r['user_role'],
                      $r['action'], $r['target_type'], $r['target_id'], $r['target_label'],
                      $r['ip_address'], $r['details']]);
    }
    fclose($fh);
    exit;
}

/* ── Action badge config ─────────────────────────────────── */
$actionCfg = [
    'login'        => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'icon' => 'bi-box-arrow-in-right', 'label' => 'Login'],
    'login_fail'   => ['bg' => 'bg-red-100',     'text' => 'text-red-700',     'icon' => 'bi-x-circle',          'label' => 'Login Failed'],
    'logout'       => ['bg' => 'bg-slate-100',   'text' => 'text-slate-600',   'icon' => 'bi-box-arrow-right',   'label' => 'Logout'],
    'patient_view' => ['bg' => 'bg-blue-100',    'text' => 'text-blue-700',    'icon' => 'bi-person-lines-fill', 'label' => 'Patient View'],
    'form_view'    => ['bg' => 'bg-sky-100',     'text' => 'text-sky-700',     'icon' => 'bi-file-earmark-text', 'label' => 'Form View'],
    'form_create'  => ['bg' => 'bg-violet-100',  'text' => 'text-violet-700',  'icon' => 'bi-file-earmark-plus', 'label' => 'Form Create'],
    'form_sign'    => ['bg' => 'bg-indigo-100',  'text' => 'text-indigo-700',  'icon' => 'bi-pen',               'label' => 'Form Sign'],
    'form_export'  => ['bg' => 'bg-amber-100',   'text' => 'text-amber-700',   'icon' => 'bi-file-earmark-pdf',  'label' => 'Form Export'],
];

$pageTitle = 'HIPAA Audit Log';
$activeNav = 'admin';

require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

    <!-- Page Header -->
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                <i class="bi bi-shield-lock-fill text-blue-600"></i>
                HIPAA Audit Log
            </h1>
            <p class="text-sm text-slate-500 mt-0.5"><?= number_format($totalRows) ?> event<?= $totalRows !== 1 ? 's' : '' ?> recorded</p>
        </div>
        <div class="flex items-center gap-2">
            <?php
            // Build CSV export URL preserving current filters
            $csvParams = array_merge($_GET, ['export' => 'csv']);
            unset($csvParams['page']);
            $csvUrl = BASE_URL . '/admin/audit_log.php?' . http_build_query($csvParams);
            ?>
            <a href="<?= h($csvUrl) ?>"
               class="inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium bg-white border border-slate-200 rounded-xl shadow-sm text-slate-700 hover:bg-slate-50 transition">
                <i class="bi bi-download"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- Filters -->
    <form method="get" action="" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-5">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">
            <!-- Action filter -->
            <div class="lg:col-span-1">
                <label class="block text-xs font-semibold text-slate-500 mb-1">Action</label>
                <select name="action"
                        class="w-full text-sm border border-slate-200 rounded-xl py-2 px-3 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All actions</option>
                    <?php foreach ($actionCfg as $key => $cfg): ?>
                    <option value="<?= h($key) ?>" <?= $filterAction === $key ? 'selected' : '' ?>><?= h($cfg['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- User filter -->
            <div class="lg:col-span-1">
                <label class="block text-xs font-semibold text-slate-500 mb-1">User</label>
                <select name="user"
                        class="w-full text-sm border border-slate-200 rounded-xl py-2 px-3 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All users</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?= h($u) ?>" <?= $filterUser === $u ? 'selected' : '' ?>><?= h($u) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Date from -->
            <div class="lg:col-span-1">
                <label class="block text-xs font-semibold text-slate-500 mb-1">From</label>
                <input type="date" name="from" value="<?= h($filterFrom) ?>"
                       class="w-full text-sm border border-slate-200 rounded-xl py-2 px-3 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <!-- Date to -->
            <div class="lg:col-span-1">
                <label class="block text-xs font-semibold text-slate-500 mb-1">To</label>
                <input type="date" name="to" value="<?= h($filterTo) ?>"
                       class="w-full text-sm border border-slate-200 rounded-xl py-2 px-3 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <!-- Search -->
            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold text-slate-500 mb-1">Search</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                        <i class="bi bi-search text-sm"></i>
                    </span>
                    <input type="text" name="q" value="<?= h($filterSearch) ?>" placeholder="Name, IP, details…"
                           class="w-full text-sm border border-slate-200 rounded-xl py-2 pl-8 pr-3 text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2 mt-3">
            <button type="submit"
                    class="px-4 py-2 text-sm font-semibold bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition shadow-sm">
                <i class="bi bi-funnel-fill mr-1"></i>Apply Filters
            </button>
            <a href="<?= BASE_URL ?>/admin/audit_log.php"
               class="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition">
                Clear
            </a>
        </div>
    </form>

    <!-- Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <?php if (empty($rows)): ?>
        <div class="flex flex-col items-center justify-center py-16 text-slate-400">
            <i class="bi bi-shield text-5xl mb-3"></i>
            <p class="text-lg font-medium">No audit events found</p>
            <p class="text-sm mt-1">Try adjusting the filters above.</p>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    <th class="px-4 py-3 text-left">Timestamp</th>
                    <th class="px-4 py-3 text-left">User</th>
                    <th class="px-4 py-3 text-left">Action</th>
                    <th class="px-4 py-3 text-left">Target</th>
                    <th class="px-4 py-3 text-left">IP Address</th>
                    <th class="px-4 py-3 text-left">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($rows as $row):
                    $cfg = $actionCfg[$row['action']] ?? ['bg' => 'bg-slate-100', 'text' => 'text-slate-600', 'icon' => 'bi-activity', 'label' => $row['action']];
                    $ts  = new DateTime($row['created_at']);
                ?>
                <tr class="hover:bg-slate-50/60 transition-colors">
                    <!-- Timestamp -->
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="font-medium text-slate-800"><?= h($ts->format('M j, Y')) ?></div>
                        <div class="text-xs text-slate-400"><?= h($ts->format('g:i:s A')) ?></div>
                    </td>
                    <!-- User -->
                    <td class="px-4 py-3 whitespace-nowrap">
                        <?php if ($row['username']): ?>
                        <div class="font-medium text-slate-800"><?= h($row['username']) ?></div>
                        <?php if ($row['user_role']): ?>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium
                            <?= $row['user_role'] === 'admin' ? 'bg-purple-100 text-purple-700' : 'bg-slate-100 text-slate-600' ?>">
                            <?= h($row['user_role']) ?>
                        </span>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="text-slate-400 italic">—</span>
                        <?php endif; ?>
                    </td>
                    <!-- Action badge -->
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold <?= $cfg['bg'] ?> <?= $cfg['text'] ?>">
                            <i class="bi <?= $cfg['icon'] ?>"></i>
                            <?= h($cfg['label']) ?>
                        </span>
                    </td>
                    <!-- Target -->
                    <td class="px-4 py-3">
                        <?php if ($row['target_label'] || $row['target_id']): ?>
                        <div class="text-slate-700 font-medium"><?= h($row['target_label'] ?? '') ?></div>
                        <?php if ($row['target_type'] && $row['target_id']): ?>
                        <div class="text-xs text-slate-400"><?= h($row['target_type']) ?> #<?= (int)$row['target_id'] ?></div>
                        <?php endif; ?>
                        <?php else: ?>
                        <span class="text-slate-400">—</span>
                        <?php endif; ?>
                    </td>
                    <!-- IP -->
                    <td class="px-4 py-3 whitespace-nowrap">
                        <span class="font-mono text-xs text-slate-600"><?= h($row['ip_address'] ?? '—') ?></span>
                    </td>
                    <!-- Details -->
                    <td class="px-4 py-3">
                        <span class="text-xs text-slate-500"><?= h($row['details'] ?? '') ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center justify-between px-4 py-3 border-t border-slate-200 bg-slate-50">
            <p class="text-sm text-slate-500">
                Showing <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $perPage, $totalRows)) ?> of <?= number_format($totalRows) ?>
            </p>
            <div class="flex items-center gap-1">
                <?php
                $baseQ = array_merge($_GET, []);
                unset($baseQ['page']);
                $baseUrl = BASE_URL . '/admin/audit_log.php?' . http_build_query($baseQ);
                ?>
                <?php if ($page > 1): ?>
                <a href="<?= h($baseUrl . '&page=' . ($page - 1)) ?>"
                   class="px-3 py-1.5 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <?php endif; ?>

                <?php
                $start = max(1, $page - 2);
                $end   = min($totalPages, $page + 2);
                for ($p = $start; $p <= $end; $p++):
                ?>
                <a href="<?= h($baseUrl . '&page=' . $p) ?>"
                   class="px-3 py-1.5 text-sm font-medium rounded-xl border transition
                          <?= $p === $page ? 'bg-blue-600 text-white border-blue-600 shadow-sm' : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50' ?>">
                    <?= $p ?>
                </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                <a href="<?= h($baseUrl . '&page=' . ($page + 1)) ?>"
                   class="px-3 py-1.5 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition">
                    <i class="bi bi-chevron-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
