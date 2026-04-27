<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$pageTitle = 'Patients';
$activeNav = 'patients';

$q       = trim($_GET['q'] ?? '');
$filter  = $_GET['filter'] ?? '';
$status  = $_GET['status'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = ['1=1'];
$params = [];
if ($q !== '') {
    $where[]  = "(p.first_name LIKE ? OR p.last_name LIKE ? OR p.phone LIKE ? OR p.dob LIKE ?)";
    $like     = "%$q%";
    $params   = array_merge($params, [$like, $like, $like, $like]);
}
if ($filter === 'pending') {
    $where[] = "EXISTS (SELECT 1 FROM form_submissions fs WHERE fs.patient_id = p.id AND fs.status = 'signed')";
}
// Status filter — default to 'active'
$statusFilter = in_array($status, ['active','inactive','discharged','all'], true) ? $status : 'active';
if ($statusFilter !== 'all') {
    $where[]  = 'p.status = ?';
    $params[] = $statusFilter;
}

// MA filter — non-admin users only see their own assigned patients
$maFilter = '';
if (!isAdmin()) {
    $where[]  = 'p.assigned_ma = ?';
    $params[] = (int)$_SESSION['user_id'];
} elseif (isset($_GET['ma']) && (int)$_GET['ma'] > 0) {
    $maFilter = (int)$_GET['ma'];
    $where[]  = 'p.assigned_ma = ?';
    $params[] = $maFilter;
}

// Load staff list for admin MA filter dropdown
$staffList = [];
if (isAdmin()) {
    $staffList = $pdo->query("SELECT id, full_name, role FROM staff WHERE active=1 ORDER BY full_name")->fetchAll();
}

$sql = "
    SELECT p.*,
           COUNT(DISTINCT fs.id) AS form_count,
           COUNT(DISTINCT wp.id) AS photo_count,
           ma.full_name AS assigned_ma_name
    FROM patients p
    LEFT JOIN form_submissions fs ON fs.patient_id = p.id
    LEFT JOIN wound_photos wp     ON wp.patient_id = p.id
    LEFT JOIN staff ma            ON ma.id = p.assigned_ma
    WHERE " . implode(' AND ', $where) . "
    GROUP BY p.id
    ORDER BY p.last_name, p.first_name
    LIMIT $perPage OFFSET $offset
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
// clone params for count query before we reference $params again
$countParams = $params;
$patients = $stmt->fetchAll();

$countSql = "SELECT COUNT(DISTINCT p.id) FROM patients p WHERE " . implode(' AND ', $where);
$cs = $pdo->prepare($countSql);
$cs->execute($countParams);
$total = (int)$cs->fetchColumn();
$pages = (int)ceil($total / $perPage);

/* ── Analytics (always across ALL patients, not filtered) ─── */
$analytics = $pdo->query("
    SELECT
        p.company,
        COUNT(*)                                                            AS total,
        SUM(p.status = 'active')                                           AS active,
        SUM(p.status = 'inactive')                                         AS inactive,
        SUM(p.status = 'discharged')                                       AS discharged,
        COUNT(DISTINCT fs.id)                                              AS forms,
        SUM(fs.status = 'signed')                                          AS pending_upload,
        COUNT(DISTINCT wp.id)                                              AS photos
    FROM patients p
    LEFT JOIN form_submissions fs ON fs.patient_id = p.id
    LEFT JOIN wound_photos wp     ON wp.patient_id = p.id
    GROUP BY p.company
")->fetchAll(PDO::FETCH_GROUP | PDO::FETCH_UNIQUE);

// Normalise so both companies always have a row
$companies = [
    'Beyond Wound Care Inc.'          => ['label'=>'Beyond Wound Care',       'color'=>'blue',  'icon'=>'bi-hospital'],
    'Visiting Medical Physician Inc.' => ['label'=>'Visiting Medical Physician','color'=>'teal', 'icon'=>'bi-heart-pulse'],
];
foreach ($companies as $name => $_) {
    if (!isset($analytics[$name])) {
        $analytics[$name] = ['total'=>0,'active'=>0,'inactive'=>0,'discharged'=>0,'forms'=>0,'pending_upload'=>0,'photos'=>0];
    }
}

include __DIR__ . '/includes/header.php';
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-extrabold text-slate-800">Patients</h2>
        <p class="text-slate-500 text-sm mt-0.5"><?= number_format($total) ?> patient<?= $total !== 1 ? 's' : '' ?> found</p>
    </div>
    <a href="<?= BASE_URL ?>/patient_add.php"
       class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-2.5 rounded-xl transition-all shadow-sm hover:shadow-md active:scale-95">
        <i class="bi bi-person-plus-fill"></i> Add Patient
    </a>
</div>

<!-- ── Analytics ─────────────────────────────────────────── -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mb-6">
<?php
$colorMap = [
    'blue' => ['card'=>'border-blue-200 bg-blue-50/40',  'title'=>'text-blue-800',  'icon'=>'bg-blue-600',  'badge_active'=>'bg-blue-100 text-blue-700',   'badge_inactive'=>'bg-amber-100 text-amber-700',   'badge_dis'=>'bg-red-100 text-red-700',   'stat'=>'text-blue-700',  'bar'=>'bg-blue-500',  'pending'=>'bg-amber-100 text-amber-700', 'photos'=>'bg-violet-100 text-violet-700'],
    'teal' => ['card'=>'border-teal-200 bg-teal-50/40',  'title'=>'text-teal-800',  'icon'=>'bg-teal-600',  'badge_active'=>'bg-teal-100 text-teal-700',   'badge_inactive'=>'bg-amber-100 text-amber-700',   'badge_dis'=>'bg-red-100 text-red-700',   'stat'=>'text-teal-700',  'bar'=>'bg-teal-500',  'pending'=>'bg-amber-100 text-amber-700', 'photos'=>'bg-violet-100 text-violet-700'],
];
foreach ($companies as $coName => $coCfg):
    $row = $analytics[$coName];
    $c   = $colorMap[$coCfg['color']];
    $activeRatio = $row['total'] > 0 ? round(($row['active'] / $row['total']) * 100) : 0;
?>
<div class="bg-white rounded-2xl border <?= $c['card'] ?> shadow-sm p-5">
    <!-- Company header -->
    <div class="flex items-center gap-3 mb-4 pb-3 border-b border-slate-100">
        <div class="w-9 h-9 rounded-xl <?= $c['icon'] ?> grid place-items-center text-white flex-shrink-0 shadow-sm">
            <i class="bi <?= $coCfg['icon'] ?> text-base"></i>
        </div>
        <div>
            <p class="font-bold <?= $c['title'] ?> text-sm leading-tight"><?= $coCfg['label'] ?></p>
            <p class="text-xs text-slate-400"><?= $coName ?></p>
        </div>
        <span class="ml-auto text-2xl font-extrabold <?= $c['stat'] ?>"><?= $row['total'] ?></span>
    </div>

    <!-- Status breakdown -->
    <div class="flex gap-2 flex-wrap mb-4">
        <a href="<?= BASE_URL ?>/patients.php?status=active" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold <?= $c['badge_active'] ?> hover:opacity-80 transition">
            <i class="bi bi-circle-fill text-[8px]"></i> <?= $row['active'] ?> Active
        </a>
        <a href="<?= BASE_URL ?>/patients.php?status=inactive" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold <?= $c['badge_inactive'] ?> hover:opacity-80 transition">
            <i class="bi bi-circle-fill text-[8px]"></i> <?= $row['inactive'] ?> Inactive
        </a>
        <a href="<?= BASE_URL ?>/patients.php?status=discharged" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold <?= $c['badge_dis'] ?> hover:opacity-80 transition">
            <i class="bi bi-circle-fill text-[8px]"></i> <?= $row['discharged'] ?> Discharged
        </a>
    </div>

    <!-- Activity bar -->
    <?php if ($row['total'] > 0): ?>
    <div class="mb-4">
        <div class="flex justify-between text-xs text-slate-500 mb-1">
            <span>Active rate</span><span><?= $activeRatio ?>%</span>
        </div>
        <div class="w-full bg-slate-100 rounded-full h-2">
            <div class="<?= $c['bar'] ?> h-2 rounded-full transition-all" style="width:<?= $activeRatio ?>%"></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Forms / Photos / Pending -->
    <div class="grid grid-cols-3 gap-3">
        <div class="bg-slate-50 rounded-xl p-3 text-center border border-slate-100">
            <p class="text-xl font-extrabold text-slate-700"><?= number_format($row['forms']) ?></p>
            <p class="text-xs text-slate-400 mt-0.5">Total Forms</p>
        </div>
        <div class="bg-slate-50 rounded-xl p-3 text-center border border-slate-100">
            <p class="text-xl font-extrabold <?= $row['pending_upload'] > 0 ? 'text-amber-600' : 'text-slate-700' ?>"><?= $row['pending_upload'] ?></p>
            <p class="text-xs text-slate-400 mt-0.5">Pending Upload</p>
        </div>
        <div class="bg-slate-50 rounded-xl p-3 text-center border border-slate-100">
            <p class="text-xl font-extrabold text-violet-600"><?= number_format($row['photos']) ?></p>
            <p class="text-xs text-slate-400 mt-0.5">Photos</p>
        </div>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Search + Filter Bar -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 mb-5">
    <form method="GET" class="flex flex-col sm:flex-row gap-3 flex-wrap">
        <div class="relative flex-1 min-w-[180px]">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">
                <i class="bi bi-search text-base"></i>
            </span>
            <input type="text" name="q" value="<?= h($q) ?>"
                   class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-xl text-sm
                          focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition bg-slate-50"
                   placeholder="Search by name, phone, or DOB...">
        </div>
        <?php if (isAdmin() && !empty($staffList)): ?>
        <div>
            <select name="ma"
                    class="h-full px-3 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                           focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                <option value="">All Staff</option>
                <?php foreach ($staffList as $sf): ?>
                <option value="<?= $sf['id'] ?>" <?= ((int)$maFilter === (int)$sf['id']) ? 'selected' : '' ?>>
                    <?= h($sf['full_name']) ?> (<?= h($sf['role']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="flex gap-2 flex-wrap">
            <?php
            $maPart = isAdmin() && $maFilter ? '&ma='.(int)$maFilter : '';
            $statusBtns = [
                'active'     => ['label'=>'Active',     'color'=>'bg-emerald-600 text-white shadow', 'inactive'=>'bg-slate-100 text-slate-700 hover:bg-slate-200'],
                'inactive'   => ['label'=>'Inactive',   'color'=>'bg-amber-500 text-white shadow',   'inactive'=>'bg-slate-100 text-slate-700 hover:bg-slate-200'],
                'discharged' => ['label'=>'Discharged', 'color'=>'bg-red-600 text-white shadow',     'inactive'=>'bg-slate-100 text-slate-700 hover:bg-slate-200'],
                'all'        => ['label'=>'All',        'color'=>'bg-blue-600 text-white shadow',    'inactive'=>'bg-slate-100 text-slate-700 hover:bg-slate-200'],
            ];
            foreach ($statusBtns as $sv => $cfg):
                $cls = ($statusFilter === $sv) ? $cfg['color'] : $cfg['inactive'];
                $href = BASE_URL . '/patients.php?status=' . $sv . ($q ? '&q='.urlencode($q) : '') . ($filter ? '&filter='.$filter : '') . $maPart;
            ?>
            <a href="<?= $href ?>" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-medium transition-all <?= $cls ?>">
                <?= $cfg['label'] ?>
            </a>
            <?php endforeach; ?>
            <a href="<?= BASE_URL ?>/patients.php?status=<?= $statusFilter ?>&filter=pending<?= $q ? '&q=' . urlencode($q) : '' ?><?= $maPart ?>"
               class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-medium transition-all
                      <?= $filter === 'pending' ? 'bg-amber-500 text-white shadow' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">
                <i class="bi bi-cloud-arrow-up"></i> Pending Upload
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-medium transition-all shadow-sm">
                Search
            </button>
        </div>
        <input type="hidden" name="status" value="<?= h($statusFilter) ?>">
        <?php if ($filter): ?><input type="hidden" name="filter" value="<?= h($filter) ?>"><?php endif; ?>
    </form>
</div>

<!-- Patient List -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <?php if (empty($patients)): ?>
    <div class="flex flex-col items-center justify-center py-20 text-slate-400">
        <i class="bi bi-person-x text-5xl mb-3 opacity-30"></i>
        <p class="font-semibold text-slate-500">No patients found</p>
        <p class="text-sm mt-1">Try adjusting your search or <a href="<?= BASE_URL ?>/patient_add.php" class="text-blue-600 hover:underline">add a new patient</a>.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 text-left border-b border-slate-100">
                    <th class="px-6 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Patient</th>
                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide hidden sm:table-cell">DOB</th>
                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide hidden md:table-cell">Phone</th>
                    <?php if (isAdmin()): ?>
                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide hidden lg:table-cell">Assigned MA</th>
                    <?php endif; ?>
                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Status</th>
                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide text-center">Forms</th>
                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide text-center hidden sm:table-cell">Photos</th>
                    <th class="px-4 py-3.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($patients as $p): ?>
                <tr class="hover:bg-slate-50/70 transition-colors">
                    <td class="px-6 py-4">
                        <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $p['id'] ?>"
                           class="flex items-center gap-3 group">
                            <?php if (!empty($p['photo_url'])): ?>
                            <img src="<?= h($p['photo_url']) ?>"
                                 alt=""
                                 class="w-9 h-9 rounded-xl object-cover flex-shrink-0 shadow-sm border border-slate-100">
                            <?php else: ?>
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 grid place-items-center text-white text-xs font-bold flex-shrink-0 shadow-sm">
                                <?= strtoupper(substr($p['first_name'], 0, 1) . substr($p['last_name'], 0, 1)) ?>
                            </div>
                            <?php endif; ?>
                            <div>
                                <div class="font-semibold text-slate-800 group-hover:text-blue-600 transition-colors">
                                    <?= h($p['last_name'] . ', ' . $p['first_name']) ?>
                                </div>
                                <?php if ($p['email']): ?>
                                <div class="text-xs text-slate-400 truncate max-w-[180px]"><?= h($p['email']) ?></div>
                                <?php endif; ?>
                            </div>
                        </a>
                    </td>
                    <td class="px-4 py-4 text-slate-600 hidden sm:table-cell">
                        <?php if ($p['dob']):
                            $ptAge = (int)(new DateTime($p['dob']))->diff(new DateTime('today'))->y;
                        ?>
                        <?= date('M j, Y', strtotime($p['dob'])) ?> <span class="text-slate-400 text-xs">(<?= $ptAge ?> yrs)</span>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td class="px-4 py-4 text-slate-600 hidden md:table-cell"><?= h($p['phone'] ?: '—') ?></td>
                    <?php if (isAdmin()): ?>
                    <td class="px-4 py-4 text-slate-600 hidden lg:table-cell">
                        <?php if (!empty($p['assigned_ma_name'])): ?>
                        <span class="inline-flex items-center gap-1 text-xs font-medium text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full">
                            <i class="bi bi-person-fill"></i> <?= h($p['assigned_ma_name']) ?>
                        </span>
                        <?php else: ?>
                        <span class="text-slate-300 text-sm">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td class="px-4 py-4">
                        <?php
                        $stMap = [
                            'active'     => ['bg-emerald-100 text-emerald-700', 'Active'],
                            'inactive'   => ['bg-amber-100 text-amber-700',     'Inactive'],
                            'discharged' => ['bg-red-100 text-red-700',         'Discharged'],
                        ];
                        [$stCls, $stLabel] = $stMap[$p['status'] ?? 'active'] ?? $stMap['active'];
                        ?>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $stCls ?>">
                            <?= $stLabel ?>
                        </span>
                        <?php if (!empty($p['discharged_at']) && $p['status'] === 'discharged'): ?>
                        <div class="text-xs text-slate-400 mt-0.5"><?= date('M j, Y', strtotime($p['discharged_at'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-4 text-center">
                        <?php if ($p['form_count'] > 0): ?>
                        <span class="inline-flex items-center justify-center min-w-[28px] h-7 px-2
                                     bg-blue-100 text-blue-700 rounded-full text-xs font-bold">
                            <?= $p['form_count'] ?>
                        </span>
                        <?php else: ?>
                        <span class="text-slate-300 text-sm">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-4 text-center hidden sm:table-cell">
                        <?php if ($p['photo_count'] > 0): ?>
                        <span class="inline-flex items-center justify-center min-w-[28px] h-7 px-2
                                     bg-violet-100 text-violet-700 rounded-full text-xs font-bold">
                            <?= $p['photo_count'] ?>
                        </span>
                        <?php else: ?>
                        <span class="text-slate-300 text-sm">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-4">
                        <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $p['id'] ?>"
                           class="inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-800 font-semibold text-xs bg-blue-50 hover:bg-blue-100 px-3.5 py-2 rounded-xl transition-colors whitespace-nowrap">
                            Open <i class="bi bi-arrow-right"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
    <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-between">
        <p class="text-sm text-slate-500">
            Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $total) ?> of <?= $total ?>
        </p>
        <div class="flex gap-2">
            <?php if ($page > 1): ?>
            <a href="?page=<?= $page-1 ?><?= $q ? '&q='.urlencode($q) : '' ?><?= $filter ? '&filter='.$filter : '' ?>"
               class="px-4 py-2 text-sm font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                ← Prev
            </a>
            <?php endif; ?>
            <?php if ($page < $pages): ?>
            <a href="?page=<?= $page+1 ?><?= $q ? '&q='.urlencode($q) : '' ?><?= $filter ? '&filter='.$filter : '' ?>"
               class="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-xl transition-colors shadow-sm">
                Next →
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
