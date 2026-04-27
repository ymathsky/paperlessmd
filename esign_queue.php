<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

if (isBilling()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$pageTitle = 'Provider Sign Queue';
$activeNav = 'esign';

/* ── Filters ─────────────────────────────────────────────────────────── */
$allowedTypes = ['all','vital_cs','medicare_awv','cognitive_wellness','new_patient','abn','ccm_consent','pf_signup','il_disclosure'];
$allowedDates = ['all','today','week'];

$typeFilter = in_array($_GET['type'] ?? '', $allowedTypes, true) ? $_GET['type'] : 'all';
$dateFilter = in_array($_GET['date'] ?? '', $allowedDates, true) ? $_GET['date'] : 'all';

/* ── Query ───────────────────────────────────────────────────────────── */
$where  = ["fs.status IN ('signed','uploaded')", "(fs.provider_signature IS NULL OR fs.provider_signature = '')"];
$params = [];

// Non-admin users only see forms they collected
if (!isAdmin()) {
    $where[] = 'fs.ma_id = ?';
    $params[] = (int)$_SESSION['user_id'];
}

if ($typeFilter !== 'all') {
    $where[] = 'fs.form_type = ?';
    $params[] = $typeFilter;
}
if ($dateFilter === 'today') {
    $where[] = 'DATE(fs.created_at) = CURDATE()';
} elseif ($dateFilter === 'week') {
    $where[] = 'fs.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
}

$sql = "
    SELECT fs.id, fs.form_type, fs.status, fs.created_at,
           p.id AS patient_id,
           CONCAT(p.first_name,' ',p.last_name) AS patient_name,
           s.full_name AS ma_name,
           s.id AS ma_id_val,
           fs.ma_id
    FROM form_submissions fs
    JOIN patients p ON p.id = fs.patient_id
    LEFT JOIN staff s ON s.id = fs.ma_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY fs.created_at ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$forms = $stmt->fetchAll();

/* ── Per-type counts (for filter badges) ────────────────────────────── */
$cntRows = $pdo->query("
    SELECT form_type, COUNT(*) AS cnt
    FROM form_submissions
    WHERE status IN ('signed','uploaded')
      AND (provider_signature IS NULL OR provider_signature = '')
    GROUP BY form_type
")->fetchAll();
$countByType = [];
$totalCount  = 0;
foreach ($cntRows as $row) {
    $countByType[$row['form_type']] = (int)$row['cnt'];
    $totalCount += (int)$row['cnt'];
}

/* ── Meta ────────────────────────────────────────────────────────────── */
$formMeta = [
    'vital_cs'           => ['label' => 'Vital CS',          'icon' => 'bi-heart-pulse-fill',       'color' => 'text-red-500',    'bg' => 'bg-red-50',    'ring' => 'ring-red-200'],
    'new_patient'        => ['label' => 'New Patient',        'icon' => 'bi-person-plus-fill',        'color' => 'text-blue-500',   'bg' => 'bg-blue-50',   'ring' => 'ring-blue-200'],
    'abn'                => ['label' => 'ABN',                'icon' => 'bi-file-earmark-ruled-fill', 'color' => 'text-amber-500',  'bg' => 'bg-amber-50',  'ring' => 'ring-amber-200'],
    'pf_signup'          => ['label' => 'PF Portal Signup',   'icon' => 'bi-envelope-at-fill',        'color' => 'text-cyan-500',   'bg' => 'bg-cyan-50',   'ring' => 'ring-cyan-200'],
    'ccm_consent'        => ['label' => 'CCM Consent',        'icon' => 'bi-calendar2-heart-fill',    'color' => 'text-emerald-500','bg' => 'bg-emerald-50','ring' => 'ring-emerald-200'],
    'medicare_awv'       => ['label' => 'Medicare AWV',       'icon' => 'bi-clipboard2-pulse-fill',   'color' => 'text-indigo-500', 'bg' => 'bg-indigo-50', 'ring' => 'ring-indigo-200'],
    'il_disclosure'      => ['label' => 'IL Disclosure',      'icon' => 'bi-shield-check',            'color' => 'text-slate-500',  'bg' => 'bg-slate-50',  'ring' => 'ring-slate-200'],
    'cognitive_wellness' => ['label' => 'Cognitive Wellness', 'icon' => 'bi-activity',                'color' => 'text-purple-500', 'bg' => 'bg-purple-50', 'ring' => 'ring-purple-200'],
];

function fmLabel(string $type, array $meta): string {
    return $meta[$type]['label'] ?? ucwords(str_replace('_', ' ', $type));
}
function fmIcon(string $type, array $meta): string {
    return $meta[$type]['icon'] ?? 'bi-file-earmark-text-fill';
}
function fmColor(string $type, array $meta): string {
    return $meta[$type]['color'] ?? 'text-slate-500';
}

include __DIR__ . '/includes/header.php';
?>

<!-- Page header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-3">
            <span class="w-10 h-10 bg-violet-100 rounded-2xl grid place-items-center shrink-0">
                <i class="bi bi-pen-fill text-violet-600 text-lg"></i>
            </span>
            Provider Sign Queue
        </h1>
        <p class="text-slate-500 text-sm mt-1 ml-14">Forms awaiting provider countersignature</p>
    </div>

    <!-- Total badge -->
    <?php if ($totalCount > 0): ?>
    <div class="flex items-center gap-2 px-5 py-3 bg-violet-50 border border-violet-200 rounded-2xl">
        <i class="bi bi-hourglass-split text-violet-500 text-lg"></i>
        <div>
            <div class="text-2xl font-bold text-violet-700 leading-none"><?= $totalCount ?></div>
            <div class="text-xs text-violet-500 font-medium">Pending</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Filters row -->
<div class="flex flex-wrap gap-3 mb-5">

    <!-- Type filter tabs -->
    <div class="flex flex-wrap items-center gap-2 bg-white border border-slate-200 rounded-2xl p-1.5 shadow-sm">
        <?php
        $typeTabs = [
            ['value' => 'all',                'label' => 'All'],
            ['value' => 'vital_cs',           'label' => 'Vital CS'],
            ['value' => 'medicare_awv',        'label' => 'Medicare AWV'],
            ['value' => 'cognitive_wellness',  'label' => 'Cognitive Wellness'],
            ['value' => 'ccm_consent',         'label' => 'CCM Consent'],
            ['value' => 'new_patient',         'label' => 'New Patient'],
            ['value' => 'abn',                 'label' => 'ABN'],
            ['value' => 'il_disclosure',       'label' => 'IL Disclosure'],
        ];
        foreach ($typeTabs as $tab):
            $tabCount  = $tab['value'] === 'all' ? $totalCount : ($countByType[$tab['value']] ?? 0);
            $isActive  = $typeFilter === $tab['value'];
            $href      = '?type=' . urlencode($tab['value']) . '&date=' . urlencode($dateFilter);
        ?>
        <a href="<?= $href ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold transition-all
                  <?= $isActive ? 'bg-violet-600 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' ?>">
            <?= htmlspecialchars($tab['label'], ENT_QUOTES, 'UTF-8') ?>
            <?php if ($tabCount > 0): ?>
            <span class="<?= $isActive ? 'bg-white/25 text-white' : 'bg-slate-200 text-slate-600' ?> text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none">
                <?= $tabCount ?>
            </span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Date filter -->
    <div class="flex items-center gap-1.5 bg-white border border-slate-200 rounded-2xl p-1.5 shadow-sm ml-auto">
        <?php foreach ([['all','All Time'],['today','Today'],['week','Last 7 Days']] as [$dv,$dl]):
            $dActive = $dateFilter === $dv;
            $dhref   = '?type=' . urlencode($typeFilter) . '&date=' . urlencode($dv);
        ?>
        <a href="<?= $dhref ?>"
           class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-all
                  <?= $dActive ? 'bg-slate-700 text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100' ?>">
            <?= htmlspecialchars($dl, ENT_QUOTES, 'UTF-8') ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Table / Empty state -->
<?php if (empty($forms)): ?>
<div class="flex flex-col items-center justify-center py-20 bg-white rounded-2xl border border-slate-200 shadow-sm">
    <div class="w-16 h-16 bg-emerald-50 rounded-2xl grid place-items-center mb-4">
        <i class="bi bi-check2-all text-emerald-500 text-3xl"></i>
    </div>
    <p class="text-slate-800 font-bold text-lg">All caught up!</p>
    <p class="text-slate-500 text-sm mt-1">No forms are awaiting provider countersignature<?= $typeFilter !== 'all' || $dateFilter !== 'all' ? ' for the selected filter' : '' ?>.</p>
    <?php if ($typeFilter !== 'all' || $dateFilter !== 'all'): ?>
    <a href="esign_queue.php"
       class="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-xl transition-colors">
        <i class="bi bi-x-circle"></i> Clear Filters
    </a>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
        <span class="text-sm font-semibold text-slate-700">
            <?= count($forms) ?> form<?= count($forms) !== 1 ? 's' : '' ?> pending
        </span>
        <span class="text-xs text-slate-400">Oldest first</span>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-100 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">
                    <th class="px-5 py-3">Date</th>
                    <th class="px-5 py-3">Patient</th>
                    <th class="px-5 py-3">Form Type</th>
                    <th class="px-5 py-3">Collected By</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($forms as $i => $row):
                    $fm   = $formMeta[$row['form_type']] ?? ['label' => ucwords(str_replace('_',' ',$row['form_type'])), 'icon' => 'bi-file-earmark-text-fill', 'color' => 'text-slate-500', 'bg' => 'bg-slate-50'];
                    $age  = (int)floor((time() - strtotime($row['created_at'])) / 86400);
                    $ageLabel = $age === 0 ? 'Today' : ($age === 1 ? 'Yesterday' : $age . 'd ago');
                    $ageClass = $age >= 7 ? 'text-red-500 font-bold' : ($age >= 3 ? 'text-amber-600 font-semibold' : 'text-slate-400');
                    $statusBg = $row['status'] === 'uploaded' ? 'bg-indigo-100 text-indigo-700' : 'bg-blue-100 text-blue-700';
                ?>
                <tr class="hover:bg-slate-50/70 transition-colors <?= $i % 2 === 0 ? '' : 'bg-slate-50/30' ?>">
                    <!-- Date -->
                    <td class="px-5 py-3.5 whitespace-nowrap">
                        <div class="font-medium text-slate-700"><?= date('M j, Y', strtotime($row['created_at'])) ?></div>
                        <div class="<?= $ageClass ?> text-xs mt-0.5"><?= $ageLabel ?></div>
                    </td>

                    <!-- Patient -->
                    <td class="px-5 py-3.5">
                        <a href="<?= BASE_URL ?>/patient_view.php?id=<?= (int)$row['patient_id'] ?>"
                           class="font-semibold text-slate-800 hover:text-blue-600 transition-colors">
                            <?= htmlspecialchars($row['patient_name'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </td>

                    <!-- Form Type -->
                    <td class="px-5 py-3.5 whitespace-nowrap">
                        <div class="inline-flex items-center gap-2 px-2.5 py-1 <?= $fm['bg'] ?> rounded-lg">
                            <i class="bi <?= $fm['icon'] ?> <?= $fm['color'] ?> text-sm"></i>
                            <span class="text-xs font-semibold text-slate-700"><?= htmlspecialchars($fm['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                    </td>

                    <!-- MA -->
                    <td class="px-5 py-3.5">
                        <?php if ($row['ma_name']): ?>
                        <div class="font-medium text-slate-700"><?= htmlspecialchars($row['ma_name'], ENT_QUOTES, 'UTF-8') ?></div>
                        <div class="text-xs text-slate-400 mt-0.5">
                            <i class="bi bi-clock mr-1"></i>Assigned <?= date('M j, Y g:i A', strtotime($row['created_at'])) ?>
                        </div>
                        <?php else: ?>
                        <span class="text-slate-300">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- Status -->
                    <td class="px-5 py-3.5">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold <?= $statusBg ?>">
                            <?= ucfirst($row['status']) ?>
                        </span>
                    </td>

                    <!-- Action -->
                    <td class="px-5 py-3.5 text-right whitespace-nowrap">
                        <a href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$row['id'] ?>#provPanel"
                           class="inline-flex items-center gap-2 px-4 py-2 bg-violet-600 hover:bg-violet-700
                                  text-white text-xs font-bold rounded-xl transition-colors shadow-sm">
                            <i class="bi bi-pen-fill"></i> Sign Now
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
