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

$sql = "
    SELECT p.*,
           COUNT(DISTINCT fs.id) AS form_count,
           COUNT(DISTINCT wp.id) AS photo_count
    FROM patients p
    LEFT JOIN form_submissions fs ON fs.patient_id = p.id
    LEFT JOIN wound_photos wp     ON wp.patient_id = p.id
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

<!-- Search + Filter Bar -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 mb-5">
    <form method="GET" class="flex flex-col sm:flex-row gap-3">
        <div class="relative flex-1">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400 pointer-events-none">
                <i class="bi bi-search text-base"></i>
            </span>
            <input type="text" name="q" value="<?= h($q) ?>"
                   class="w-full pl-10 pr-4 py-3 border border-slate-200 rounded-xl text-sm
                          focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition bg-slate-50"
                   placeholder="Search by name, phone, or DOB...">
        </div>
        <div class="flex gap-2 flex-wrap">
            <?php
            $statusBtns = [
                'active'     => ['label'=>'Active',     'color'=>'bg-emerald-600 text-white shadow', 'inactive'=>'bg-slate-100 text-slate-700 hover:bg-slate-200'],
                'inactive'   => ['label'=>'Inactive',   'color'=>'bg-amber-500 text-white shadow',   'inactive'=>'bg-slate-100 text-slate-700 hover:bg-slate-200'],
                'discharged' => ['label'=>'Discharged', 'color'=>'bg-red-600 text-white shadow',     'inactive'=>'bg-slate-100 text-slate-700 hover:bg-slate-200'],
                'all'        => ['label'=>'All',        'color'=>'bg-blue-600 text-white shadow',    'inactive'=>'bg-slate-100 text-slate-700 hover:bg-slate-200'],
            ];
            foreach ($statusBtns as $sv => $cfg):
                $cls = ($statusFilter === $sv) ? $cfg['color'] : $cfg['inactive'];
                $href = BASE_URL . '/patients.php?status=' . $sv . ($q ? '&q='.urlencode($q) : '') . ($filter ? '&filter='.$filter : '');
            ?>
            <a href="<?= $href ?>" class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-medium transition-all <?= $cls ?>">
                <?= $cfg['label'] ?>
            </a>
            <?php endforeach; ?>
            <a href="<?= BASE_URL ?>/patients.php?status=<?= $statusFilter ?>&filter=pending<?= $q ? '&q=' . urlencode($q) : '' ?>"
               class="inline-flex items-center gap-1.5 px-4 py-2.5 rounded-xl text-sm font-medium transition-all
                      <?= $filter === 'pending' ? 'bg-amber-500 text-white shadow' : 'bg-slate-100 text-slate-700 hover:bg-slate-200' ?>">
                <i class="bi bi-cloud-arrow-up"></i> Pending Upload
            </a>
            <button type="submit"
                    class="inline-flex items-center gap-1.5 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-medium transition-all shadow-sm">
                Search
            </button>
        </div>
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
                            <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-500 to-blue-700 grid place-items-center text-white text-xs font-bold flex-shrink-0 shadow-sm">
                                <?= strtoupper(substr($p['first_name'], 0, 1) . substr($p['last_name'], 0, 1)) ?>
                            </div>
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
                        <?= $p['dob'] ? date('M j, Y', strtotime($p['dob'])) : '—' ?>
                    </td>
                    <td class="px-4 py-4 text-slate-600 hidden md:table-cell"><?= h($p['phone'] ?: '—') ?></td>
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
