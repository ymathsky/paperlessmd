<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/visit_types.php';
requireNotBilling();

$pageTitle = 'My Schedule';
$activeNav = 'schedule';

// Date navigation
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
$isToday  = $date === date('Y-m-d');

// View mode: day | week
$view = in_array($_GET['view'] ?? '', ['day','week']) ? $_GET['view'] : 'day';

// Admins can view any MA's schedule via ?ma_id=X, or all via ?ma_id=all
$viewAll  = isAdmin() && ($_GET['ma_id'] ?? '') === 'all';
$viewMaId = (!$viewAll && isAdmin() && isset($_GET['ma_id'])) ? (int)$_GET['ma_id'] : (int)$_SESSION['user_id'];

// Fetch MA info
if ($viewAll) {
    $ma = ['id' => 0, 'full_name' => 'All MAs'];
} else {
    $maStmt = $pdo->prepare("SELECT id, full_name FROM staff WHERE id = ?");
    $maStmt->execute([$viewMaId]);
    $ma = $maStmt->fetch();
    if (!$ma) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }
}

// Week bounds (Monday–Sunday of the week containing $date)
$dow       = (int)date('N', strtotime($date));   // 1=Mon … 7=Sun
$weekStart = date('Y-m-d', strtotime($date . ' -' . ($dow - 1) . ' days'));
$weekEnd   = date('Y-m-d', strtotime($weekStart . ' +6 days'));
$prevWeek  = date('Y-m-d', strtotime($weekStart . ' -7 days'));
$nextWeek  = date('Y-m-d', strtotime($weekStart . ' +7 days'));

// Week query (only executed when needed)
$visitsByDate = [];
$weekVisits   = [];
$weekCounts   = ['pending'=>0,'en_route'=>0,'completed'=>0,'missed'=>0];
if ($view === 'week') {
    $wkStmt = $pdo->prepare("
        SELECT sc.*,
               CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
               p.id AS patient_id
        FROM `schedule` sc
        JOIN patients p ON p.id = sc.patient_id
        WHERE sc.ma_id = ? AND sc.visit_date BETWEEN ? AND ?
        ORDER BY sc.visit_date ASC, sc.visit_order ASC, sc.visit_time ASC
    ");
    $wkStmt->execute([$viewMaId, $weekStart, $weekEnd]);
    $weekVisits = $wkStmt->fetchAll();
    foreach ($weekVisits as $wv) {
        $visitsByDate[$wv['visit_date']][] = $wv;
        $weekCounts[$wv['status']]++;
    }
}

// Fetch schedule for this MA + date, ordered by visit_order
$schedStmt = $pdo->prepare("
    SELECT sc.*, 
           CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
           p.address AS patient_address,
           p.phone   AS patient_phone,
           p.id      AS patient_id
    FROM `schedule` sc
    JOIN patients p ON p.id = sc.patient_id
    WHERE sc.ma_id = ? AND sc.visit_date = ?
    ORDER BY sc.visit_order ASC, sc.visit_time ASC
");
$schedStmt->execute([$viewMaId, $date]);
$visits = $schedStmt->fetchAll();

// When admin views all MAs, also build grouped structure
$allMaVisits = []; // [ma_id => ['name'=>'...', 'counts'=>[], 'visits'=>[]]]
if ($viewAll && $view === 'day') {
    $allStmt = $pdo->prepare("
        SELECT sc.*,
               CONCAT(p.first_name,' ',p.last_name) AS patient_name,
               p.address AS patient_address,
               p.phone   AS patient_phone,
               p.id      AS patient_id,
               s.full_name AS ma_name
        FROM `schedule` sc
        JOIN patients p ON p.id = sc.patient_id
        JOIN staff s    ON s.id = sc.ma_id
        WHERE sc.visit_date = ?
        ORDER BY s.full_name, sc.visit_order ASC, sc.visit_time ASC
    ");
    $allStmt->execute([$date]);
    foreach ($allStmt->fetchAll() as $av) {
        if (!isset($allMaVisits[$av['ma_id']])) {
            $allMaVisits[$av['ma_id']] = [
                'name'   => $av['ma_name'],
                'counts' => ['pending'=>0,'en_route'=>0,'completed'=>0,'missed'=>0],
                'visits' => [],
            ];
        }
        $allMaVisits[$av['ma_id']]['visits'][] = $av;
        $allMaVisits[$av['ma_id']]['counts'][$av['status']]++;
    }
    // Flatten for total stats
    $visits = array_merge(...(array_column($allMaVisits, 'visits') ?: [[]]));
}

// Stats
$counts = ['pending'=>0,'en_route'=>0,'completed'=>0,'missed'=>0];
foreach ($visits as $v) $counts[$v['status']]++;

// All MAs for admin switcher
$allMas = [];
if (isAdmin()) {
    $allMas = $pdo->query("SELECT id, full_name FROM staff WHERE active=1 ORDER BY full_name")->fetchAll();
}

include __DIR__ . '/includes/header.php';
?>

<!-- Date nav + Title -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 no-print">
    <div>
        <h2 class="text-2xl font-extrabold text-slate-800">
            <i class="bi bi-calendar3 text-indigo-500 mr-1"></i> Daily Schedule
        </h2>
        <p class="text-slate-500 text-sm mt-0.5">
            <?= h($ma['full_name']) ?> &mdash;
            <?php if ($view === 'week'): ?>
                <?= date('M j', strtotime($weekStart)) ?> – <?= date('M j, Y', strtotime($weekEnd)) ?>
            <?php else: ?>
                <?= date('l, F j, Y', strtotime($date)) ?>
                <?php if ($isToday): ?><span class="ml-2 px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs font-bold rounded-full">TODAY</span><?php endif; ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="flex items-center gap-2">
        <!-- Admin MA switcher -->
        <?php if (isAdmin() && $allMas): ?>
        <form method="GET" class="flex items-center gap-2">
            <input type="hidden" name="date" value="<?= h($date) ?>">
            <input type="hidden" name="view" value="<?= h($view) ?>">
            <select name="ma_id" onchange="this.form.submit()"
                    class="px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-indigo-400">
                <option value="all" <?= $viewAll ? 'selected' : '' ?>>All MAs</option>
                <?php foreach ($allMas as $m): ?>
                <option value="<?= $m['id'] ?>" <?= (!$viewAll && $m['id'] == $viewMaId) ? 'selected' : '' ?>>
                    <?= h($m['full_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>

        <!-- Day / Week toggle -->
        <?php $maParam = $viewAll ? 'all' : $viewMaId; ?>
        <div class="flex items-center bg-slate-100 rounded-xl p-1 gap-0.5">
            <a href="?date=<?= $date ?>&ma_id=<?= $maParam ?>&view=day"
               class="px-3.5 py-1.5 rounded-lg text-sm font-semibold transition-all <?= $view === 'day' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-700' ?>">
                <i class="bi bi-calendar3 mr-1"></i>Day
            </a>
            <a href="?date=<?= $weekStart ?>&ma_id=<?= $maParam ?>&view=week"
               class="px-3.5 py-1.5 rounded-lg text-sm font-semibold transition-all <?= $view === 'week' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-500 hover:text-slate-700' ?>">
                <i class="bi bi-calendar-week mr-1"></i>Week
            </a>
        </div>

        <!-- Date navigation -->
        <?php if ($view === 'week'): ?>
        <a href="?date=<?= $prevWeek ?>&ma_id=<?= $maParam ?>&view=week"
           class="p-2.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors text-slate-600">
            <i class="bi bi-chevron-left text-sm"></i>
        </a>
        <a href="?date=<?= date('Y-m-d') ?>&ma_id=<?= $maParam ?>&view=week"
           class="px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors <?= (date('Y-m-d') >= $weekStart && date('Y-m-d') <= $weekEnd) ? 'border-indigo-300 text-indigo-600' : '' ?>">
            Today
        </a>
        <a href="?date=<?= $nextWeek ?>&ma_id=<?= $maParam ?>&view=week"
           class="p-2.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors text-slate-600">
            <i class="bi bi-chevron-right text-sm"></i>
        </a>
        <?php else: ?>
        <a href="?date=<?= $prevDate ?>&ma_id=<?= $maParam ?>&view=day"
           class="p-2.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors text-slate-600">
            <i class="bi bi-chevron-left text-sm"></i>
        </a>
        <a href="?date=<?= date('Y-m-d') ?>&ma_id=<?= $maParam ?>&view=day"
           class="px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-colors <?= $isToday ? 'border-indigo-300 text-indigo-600' : '' ?>">
            Today
        </a>
        <a href="?date=<?= $nextDate ?>&ma_id=<?= $maParam ?>&view=day"
           class="p-2.5 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors text-slate-600">
            <i class="bi bi-chevron-right text-sm"></i>
        </a>
        <?php endif; ?>

        <?php if (isAdmin()): ?>
        <a href="<?= BASE_URL ?>/admin/schedule_manage.php?date=<?= $date ?>"
           class="flex items-center gap-2 px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-sm font-semibold transition-colors shadow-sm">
            <i class="bi bi-pencil-fill text-xs"></i> Manage
        </a>
        <?php endif; ?>
        <button onclick="window.print()"
                class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-colors shadow-sm no-print">
            <i class="bi bi-printer-fill text-slate-500"></i> Print
        </button>
    </div>
</div>

<style>
/* Screen: hide the dedicated print layout */
#print-layout { display: none; }

@media print {
    @page { size: A4 <?= $view === 'week' ? 'landscape' : 'portrait' ?>; margin: 18mm 20mm; }

    body, html { background: #fff !important; margin: 0 !important; padding: 0 !important; }

    /* Strip ALL wrapper constraints so #print-layout fills the full page */
    .pt-20        { padding-top: 0 !important; padding-bottom: 0 !important; }
    .min-h-screen { min-height: 0 !important; }
    .page-fade    {
        animation: none !important; opacity: 1 !important;
        max-width: 100% !important;
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    /* Hide all screen UI — show only print layout */
    nav, footer, .no-print, #screen-layout { display: none !important; }
    #print-layout {
        display: block !important;
        font-family: 'Inter', Arial, sans-serif;
        font-size: 9pt;
        color: #1e293b;
        width: 100%;
    }
}
</style>

<div id="screen-layout">
<!-- Print-only header (hidden on screen) -->
<div class="print-header" style="margin-bottom:10pt; border-bottom:2pt solid #4f46e5; padding-bottom:6pt;">
    <div style="font-size:14pt; font-weight:900; color:#1e293b;">
        <?= $view === 'week'
            ? 'Weekly Schedule: ' . date('M j', strtotime($weekStart)) . ' – ' . date('M j, Y', strtotime($weekEnd))
            : 'Daily Schedule: ' . date('l, F j, Y', strtotime($date)) ?>
    </div>
    <div style="font-size:9pt; color:#64748b; margin-top:2pt;">
        <?= h($ma['full_name']) ?> &mdash; Printed <?= date('M j, Y g:i a') ?>
    </div>
</div>

<!-- Status summary bar -->
<div class="grid grid-cols-4 gap-3 mb-6 print-stat-bar"
     style="display:grid; grid-template-columns:repeat(4,1fr); gap:6pt; margin-bottom:10pt;">
    <?php
    $statusDefs = [
        'pending'   => ['label'=>'Pending',   'bg'=>'bg-slate-100',   'text'=>'text-slate-600',   'dot'=>'bg-slate-400',   'icon'=>'bi-clock'],
        'en_route'  => ['label'=>'En Route',  'bg'=>'bg-blue-100',    'text'=>'text-blue-700',    'dot'=>'bg-blue-500',    'icon'=>'bi-car-front-fill'],
        'completed' => ['label'=>'Completed', 'bg'=>'bg-emerald-100', 'text'=>'text-emerald-700', 'dot'=>'bg-emerald-500', 'icon'=>'bi-check-circle-fill'],
        'missed'    => ['label'=>'Missed',    'bg'=>'bg-red-100',     'text'=>'text-red-700',     'dot'=>'bg-red-400',     'icon'=>'bi-x-circle-fill'],
    ];
    $displayCounts = ($view === 'week') ? $weekCounts : $counts;
    foreach ($statusDefs as $key => $def): ?>
    <div class="bg-white border border-slate-100 rounded-2xl p-4 flex items-center gap-3 shadow-sm">
        <div class="<?= $def['bg'] ?> p-2.5 rounded-xl">
            <i class="bi <?= $def['icon'] ?> <?= $def['text'] ?> text-lg leading-none"></i>
        </div>
        <div>
            <div class="text-2xl font-extrabold text-slate-800"><?= $displayCounts[$key] ?></div>
            <div class="text-xs text-slate-500 font-medium"><?= $def['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($view === 'week'): ?>
<!-- ═══════════════════════ WEEKLY VIEW ═══════════════════════ -->
<div class="overflow-x-auto -mx-1 pb-2 print-week-grid-wrapper">
    <div class="inline-grid gap-3 min-w-full print-week-grid" style="grid-template-columns: repeat(7, minmax(160px, 1fr));">
        <?php
        for ($d = 0; $d < 7; $d++):
            $colDate  = date('Y-m-d', strtotime($weekStart . ' +' . $d . ' days'));
            $isColToday = $colDate === date('Y-m-d');
            $colVisits  = $visitsByDate[$colDate] ?? [];
            $colCounts  = ['pending'=>0,'en_route'=>0,'completed'=>0,'missed'=>0];
            foreach ($colVisits as $cv) $colCounts[$cv['status']]++;
        ?>
        <div class="flex flex-col rounded-2xl overflow-hidden border print-week-col <?= $isColToday ? 'border-indigo-300 shadow-md' : 'border-slate-100 shadow-sm' ?> bg-white">
            <!-- Day header -->
            <div class="px-3 py-2.5 <?= $isColToday ? 'bg-indigo-600 text-white' : 'bg-slate-50 text-slate-600' ?> border-b <?= $isColToday ? 'border-indigo-500' : 'border-slate-100' ?>">
                <div class="text-xs font-bold uppercase tracking-wide <?= $isColToday ? 'text-indigo-100' : 'text-slate-400' ?>">
                    <?= date('D', strtotime($colDate)) ?>
                </div>
                <div class="text-sm font-extrabold leading-tight <?= $isColToday ? 'text-white' : 'text-slate-700' ?>">
                    <?= date('M j', strtotime($colDate)) ?>
                </div>
                <?php if (!empty($colVisits)): ?>
                <div class="flex gap-1 mt-1.5 flex-wrap">
                    <?php foreach (['pending'=>'bg-slate-300','en_route'=>'bg-blue-400','completed'=>'bg-emerald-400','missed'=>'bg-red-400'] as $sk=>$sc): if ($colCounts[$sk]): ?>
                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded-full text-[10px] font-bold
                                 <?= $isColToday ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600' ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= $sc ?>"></span><?= $colCounts[$sk] ?>
                    </span>
                    <?php endif; endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <!-- Visit list -->
            <div class="flex flex-col gap-1.5 p-2 flex-1 <?= empty($colVisits) ? 'items-center justify-center py-6' : '' ?>">
                <?php if (empty($colVisits)): ?>
                <span class="text-xs text-slate-300 font-medium">No visits</span>
                <?php else: ?>
                <?php foreach ($colVisits as $cv):
                    $cvSd = $statusDefs[$cv['status']];
                ?>
                <div class="rounded-xl border <?= $cv['status']==='completed' ? 'border-emerald-100 bg-emerald-50' : ($cv['status']==='en_route' ? 'border-blue-100 bg-blue-50' : ($cv['status']==='missed' ? 'border-red-100 bg-red-50' : 'border-slate-100 bg-slate-50')) ?> px-2.5 py-2">
                    <div class="flex items-start gap-1.5">
                        <span class="mt-0.5 w-2 h-2 rounded-full <?= $cvSd['dot'] ?> shrink-0"></span>
                        <div class="min-w-0 flex-1">
                            <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $cv['patient_id'] ?>"
                               class="text-xs font-semibold text-slate-700 hover:text-indigo-600 leading-snug block truncate">
                                <?= h($cv['patient_name']) ?>
                            </a>
                            <?php if ($cv['visit_time']): ?>
                            <span class="text-[10px] text-slate-400"><?= date('g:i A', strtotime($cv['visit_time'])) ?></span>
                            <?php endif; ?>
                        </div>
                        <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $cv['patient_id'] ?>"
                           class="shrink-0 text-[10px] font-semibold text-slate-400 hover:text-indigo-600 mt-0.5">
                            <i class="bi bi-person-lines-fill"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <!-- Day footer: link to day view -->
            <a href="?date=<?= $colDate ?>&ma_id=<?= $viewMaId ?>&view=day"
               class="no-print block text-center text-[10px] font-semibold py-2 border-t border-slate-100
                      <?= $isColToday ? 'text-indigo-600 bg-indigo-50 hover:bg-indigo-100' : 'text-slate-400 bg-slate-50 hover:bg-slate-100' ?> transition-colors">
                View day <i class="bi bi-arrow-right-short"></i>
            </a>
        </div>
        <?php endfor; ?>
    </div>
</div>

<?php else: ?>
<!-- ═══════════════════════ DAILY VIEW ═══════════════════════ -->

<?php if ($viewAll && !empty($allMaVisits)): ?>
<!-- ── All-MAs filter pills ── -->
<div class="flex flex-wrap items-center gap-2 mb-4 no-print">
    <span class="text-xs font-semibold text-slate-500 mr-1">Filter:</span>
    <button onclick="filterMa('all')" id="pill-all"
            class="ma-pill px-3 py-1.5 rounded-full text-xs font-bold border transition-colors bg-indigo-600 text-white border-indigo-600">
        All MAs
    </button>
    <?php foreach ($allMaVisits as $mid => $mg): ?>
    <button onclick="filterMa(<?= $mid ?>)" id="pill-<?= $mid ?>"
            class="ma-pill px-3 py-1.5 rounded-full text-xs font-bold border transition-colors bg-white text-slate-600 border-slate-200 hover:border-indigo-400 hover:text-indigo-600">
        <?= h($mg['name']) ?>
        <span class="ml-1 px-1.5 py-0.5 bg-slate-100 rounded-full text-[10px]"><?= count($mg['visits']) ?></span>
    </button>
    <?php endforeach; ?>
</div>
<script>
function filterMa(maId) {
    document.querySelectorAll('.ma-section').forEach(el => {
        el.style.display = (maId === 'all' || el.dataset.maId == maId) ? '' : 'none';
    });
    document.querySelectorAll('.ma-pill').forEach(btn => {
        const active = (maId === 'all' && btn.id === 'pill-all') || (btn.id === 'pill-' + maId);
        btn.className = btn.className.replace(/bg-indigo-600 text-white border-indigo-600|bg-white text-slate-600 border-slate-200/g, '');
        btn.classList.add(...(active
            ? ['bg-indigo-600','text-white','border-indigo-600']
            : ['bg-white','text-slate-600','border-slate-200']));
    });
}
</script>

<?php foreach ($allMaVisits as $mid => $mg): ?>
<div class="ma-section mb-6" data-ma-id="<?= $mid ?>">
    <!-- MA header -->
    <div class="flex items-center gap-3 mb-3 px-1">
        <div class="w-8 h-8 bg-indigo-100 text-indigo-700 font-extrabold text-xs rounded-lg grid place-items-center shrink-0">
            <i class="bi bi-person-fill"></i>
        </div>
        <div class="flex-1">
            <p class="font-bold text-slate-800"><?= h($mg['name']) ?></p>
            <p class="text-xs text-slate-400">
                <?php foreach (['completed'=>'text-emerald-600','en_route'=>'text-blue-600','pending'=>'text-slate-500','missed'=>'text-red-500'] as $sk=>$sc): if ($mg['counts'][$sk]): ?>
                <span class="<?= $sc ?> font-semibold"><?= $mg['counts'][$sk] ?> <?= ucwords(str_replace('_',' ',$sk)) ?></span>
                <?php endif; endforeach; ?>
            </p>
        </div>
        <a href="?date=<?= $date ?>&ma_id=<?= $mid ?>&view=day"
           class="no-print text-xs text-indigo-600 font-semibold hover:underline">View only &rsaquo;</a>
    </div>
    <div class="space-y-3">
    <?php foreach ($mg['visits'] as $idx => $v):
        $sd      = $statusDefs[$v['status']];
        $addr    = $v['patient_address'] ? rawurlencode($v['patient_address']) : '';
        $mapsUrl = $addr ? 'https://www.google.com/maps/dir/?api=1&destination=' . $addr : '#'; ?>
    <div class="bg-white border border-slate-100 rounded-2xl shadow-sm hover:shadow-md transition-shadow print-visit-card"
         id="visit-<?= $v['id'] ?>">
        <div class="flex items-start gap-4 p-4">
            <div class="w-10 h-10 bg-indigo-100 text-indigo-700 font-extrabold text-sm rounded-xl grid place-items-center shrink-0">
                <?= $idx + 1 ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $v['patient_id'] ?>"
                       class="font-bold text-slate-800 hover:text-indigo-600 transition-colors text-base">
                        <?= h($v['patient_name']) ?>
                    </a>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $sd['bg'] ?> <?= $sd['text'] ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= $sd['dot'] ?>"></span>
                        <?= $sd['label'] ?>
                    </span>
                    <?php $vt = $v['visit_type'] ?? 'routine';
                    $vtLabels = ['routine'=>'Routine','new_patient'=>'New Pt','wound_care'=>'Wound Care','awv'=>'AWV','ccm'=>'CCM','il'=>'IL Disc.']; ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700">
                        <?= h($vtLabels[$vt] ?? 'Routine') ?>
                    </span>
                </div>
                <?php if ($v['visit_time']): ?>
                <div class="flex items-center gap-1.5 text-sm text-slate-500 mb-1">
                    <i class="bi bi-clock text-slate-400"></i>
                    <?= date('g:i A', strtotime($v['visit_time'])) ?>
                </div>
                <?php endif; ?>
                <?php if ($v['patient_address']): ?>
                <div class="flex items-start gap-1.5 text-sm text-slate-500 mb-1">
                    <i class="bi bi-geo-alt text-slate-400 mt-0.5 shrink-0"></i>
                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $addr ?>" target="_blank" rel="noopener"
                       class="hover:text-blue-600 underline decoration-dotted"><?= h($v['patient_address']) ?></a>
                </div>
                <?php endif; ?>
                <?php if ($v['patient_phone']): ?>
                <div class="flex items-center gap-1.5 text-sm text-slate-500">
                    <i class="bi bi-telephone text-slate-400"></i>
                    <a href="tel:<?= h(preg_replace('/\D/','',$v['patient_phone'])) ?>"
                       class="hover:text-indigo-600"><?= h($v['patient_phone']) ?></a>
                </div>
                <?php endif; ?>
                <?php if ($v['notes']): ?>
                <div class="mt-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-1.5">
                    <i class="bi bi-sticky-fill mr-1"></i><?= h($v['notes']) ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="flex flex-col gap-2 shrink-0 no-print">
                <?php if ($v['status'] === 'pending'): ?>
                <button onclick="startVisit(<?= $v['id'] ?>, <?= $v['patient_id'] ?>, this)"
                        class="flex items-center gap-1.5 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white rounded-xl text-xs font-bold shadow-sm transition-all">
                    <i class="bi bi-play-fill text-sm"></i> Start Visit
                </button>
                <?php elseif ($v['status'] === 'en_route'): ?>
                <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $v['patient_id'] ?>&tab=forms&visit=<?= $v['id'] ?>"
                   class="flex items-center gap-1.5 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all">
                    <i class="bi bi-file-earmark-plus-fill text-sm"></i> Open Forms
                </a>
                <?php endif; ?>
                <?php if ($v['patient_address']): ?>
                <a href="<?= h($mapsUrl) ?>" target="_blank" rel="noopener"
                   class="flex items-center gap-1.5 px-3 py-2 bg-blue-50 text-blue-700 border border-blue-200 rounded-xl text-xs font-semibold hover:bg-blue-100 transition-colors">
                    <i class="bi bi-navigation-fill"></i> Navigate
                </a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $v['patient_id'] ?>"
                   class="flex items-center gap-1.5 px-3 py-2 bg-slate-50 text-slate-600 border border-slate-200 rounded-xl text-xs font-semibold hover:bg-slate-100 transition-colors">
                    <i class="bi bi-person-lines-fill"></i> Chart
                </a>
            </div>
        </div>
        <div class="border-t border-slate-100 px-4 py-3 flex flex-wrap gap-2 bg-slate-50/60 no-print">
            <span class="text-xs text-slate-500 font-medium self-center mr-1">Update:</span>
            <?php foreach ($statusDefs as $sKey => $sDef): ?>
            <button onclick="updateStatus(<?= $v['id'] ?>, '<?= $sKey ?>')"
                    class="status-btn px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors
                           <?= $v['status'] === $sKey
                               ? $sDef['bg'] . ' ' . $sDef['text'] . ' border-transparent ring-2 ring-offset-1 ring-' . explode('-',$sDef['dot'])[1] . '-400'
                               : 'bg-white border-slate-200 text-slate-500 hover:border-slate-300 hover:bg-slate-100' ?>"
                    data-visit="<?= $v['id'] ?>" data-status="<?= $sKey ?>">
                <i class="bi <?= $sDef['icon'] ?> mr-0.5"></i> <?= $sDef['label'] ?>
            </button>
            <?php endforeach; ?>
        </div>
        <div class="border-t border-slate-100 rounded-b-2xl overflow-hidden no-print">
            <button type="button" onclick="toggleNotes(this, <?= $v['id'] ?>)"
                    class="w-full flex items-center gap-2 px-4 py-2.5 text-xs font-semibold text-left transition-colors
                           <?= !empty($v['visit_notes']) ? 'bg-amber-50 text-amber-700 hover:bg-amber-100' : 'bg-slate-50/80 text-slate-500 hover:bg-slate-100' ?>">
                <i class="bi bi-pencil-square text-sm"></i>
                <?php if (!empty($v['visit_notes'])): ?>
                    <span class="truncate flex-1"><?= h(mb_strimwidth($v['visit_notes'], 0, 80, '…')) ?></span>
                    <span class="shrink-0 px-2 py-0.5 bg-amber-200 text-amber-800 rounded-full text-[10px] font-bold">Note saved</span>
                <?php else: ?>
                    <span class="flex-1">Add quick note…</span>
                <?php endif; ?>
                <i class="bi bi-chevron-down text-xs shrink-0 note-chevron transition-transform"></i>
            </button>
            <div class="note-panel hidden px-4 pb-4 pt-3 bg-amber-50/60">
                <textarea id="note-<?= $v['id'] ?>"
                    class="w-full px-3 py-2.5 border border-amber-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent resize-none transition"
                    rows="3"
                    placeholder="Quick clinical observation — e.g. wound looks improved, patient reports pain 3/10…"
                    ><?= h($v['visit_notes'] ?? '') ?></textarea>
                <div class="flex items-center gap-2 mt-2">
                    <button type="button" onclick="saveNote(<?= $v['id'] ?>, this)"
                            class="px-4 py-2 bg-amber-500 hover:bg-amber-600 active:scale-95 text-white text-xs font-bold rounded-xl transition-all shadow-sm">
                        <i class="bi bi-floppy-fill mr-1"></i> Save Note
                    </button>
                    <span class="note-saved-msg hidden text-xs text-emerald-600 font-semibold">
                        <i class="bi bi-check-circle-fill mr-0.5"></i> Saved!
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; // end per-MA visits ?>
    </div><!-- /space-y-3 -->
</div><!-- /ma-section -->
<?php endforeach; // end foreach allMaVisits ?>

<?php elseif (empty($visits)): ?>
<div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-12 text-center">
    <div class="w-16 h-16 bg-indigo-50 rounded-2xl grid place-items-center mx-auto mb-4">
        <i class="bi bi-calendar-x text-indigo-400 text-3xl"></i>
    </div>
    <p class="text-slate-600 font-semibold text-lg mb-1">No visits scheduled</p>
    <p class="text-slate-400 text-sm mb-5">
        <?= isAdmin() ? 'Use "Manage" to assign patients to this MA.' : 'Check with your supervisor to get visits assigned.' ?>
    </p>
    <?php if (isAdmin()): ?>
    <a href="<?= BASE_URL ?>/admin/schedule_manage.php?date=<?= $date ?>"
       class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-semibold hover:bg-indigo-700 transition-colors">
        <i class="bi bi-plus-lg"></i> Add Visits
    </a>
    <?php endif; ?>
</div>
<?php else: ?>
<div class="space-y-3" id="visitList">
    <?php foreach ($visits as $idx => $v):
        $sd      = $statusDefs[$v['status']];
        $addr    = $v['patient_address'] ? rawurlencode($v['patient_address']) : '';
        $mapsUrl = $addr ? 'https://www.google.com/maps/dir/?api=1&destination=' . $addr : '#'; ?>
    <div class="bg-white border border-slate-100 rounded-2xl shadow-sm hover:shadow-md transition-shadow print-visit-card"
         id="visit-<?= $v['id'] ?>">
        <div class="flex items-start gap-4 p-4">
            <div class="w-10 h-10 bg-indigo-100 text-indigo-700 font-extrabold text-sm rounded-xl grid place-items-center shrink-0">
                <?= $idx + 1 ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $v['patient_id'] ?>"
                       class="font-bold text-slate-800 hover:text-indigo-600 transition-colors text-base">
                        <?= h($v['patient_name']) ?>
                    </a>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $sd['bg'] ?> <?= $sd['text'] ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= $sd['dot'] ?>"></span>
                        <?= $sd['label'] ?>
                    </span>
                    <?php $vt = $v['visit_type'] ?? 'routine';
                    $vtLabels = ['routine'=>'Routine','new_patient'=>'New Pt','wound_care'=>'Wound Care','awv'=>'AWV','ccm'=>'CCM','il'=>'IL Disc.']; ?>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700">
                        <?= h($vtLabels[$vt] ?? 'Routine') ?>
                    </span>
                </div>
                <?php if ($v['visit_time']): ?>
                <div class="flex items-center gap-1.5 text-sm text-slate-500 mb-1">
                    <i class="bi bi-clock text-slate-400"></i>
                    <?= date('g:i A', strtotime($v['visit_time'])) ?>
                </div>
                <?php endif; ?>
                <?php if ($v['patient_address']): ?>
                <div class="flex items-start gap-1.5 text-sm text-slate-500 mb-1">
                    <i class="bi bi-geo-alt text-slate-400 mt-0.5 shrink-0"></i>
                    <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $addr ?>" target="_blank" rel="noopener"
                       class="hover:text-blue-600 underline decoration-dotted"><?= h($v['patient_address']) ?></a>
                </div>
                <?php endif; ?>
                <?php if ($v['patient_phone']): ?>
                <div class="flex items-center gap-1.5 text-sm text-slate-500">
                    <i class="bi bi-telephone text-slate-400"></i>
                    <a href="tel:<?= h(preg_replace('/\D/','',$v['patient_phone'])) ?>"
                       class="hover:text-indigo-600"><?= h($v['patient_phone']) ?></a>
                </div>
                <?php endif; ?>
                <?php if ($v['notes']): ?>
                <div class="mt-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-1.5">
                    <i class="bi bi-sticky-fill mr-1"></i><?= h($v['notes']) ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="flex flex-col gap-2 shrink-0 no-print">
                <?php if ($v['status'] === 'pending'): ?>
                <button onclick="startVisit(<?= $v['id'] ?>, <?= $v['patient_id'] ?>, this)"
                        class="flex items-center gap-1.5 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white rounded-xl text-xs font-bold shadow-sm transition-all">
                    <i class="bi bi-play-fill text-sm"></i> Start Visit
                </button>
                <?php elseif ($v['status'] === 'en_route'): ?>
                <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $v['patient_id'] ?>&tab=forms&visit=<?= $v['id'] ?>"
                   class="flex items-center gap-1.5 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-sm transition-all">
                    <i class="bi bi-file-earmark-plus-fill text-sm"></i> Open Forms
                </a>
                <?php endif; ?>
                <?php if ($v['patient_address']): ?>
                <a href="<?= h($mapsUrl) ?>" target="_blank" rel="noopener"
                   class="flex items-center gap-1.5 px-3 py-2 bg-blue-50 text-blue-700 border border-blue-200 rounded-xl text-xs font-semibold hover:bg-blue-100 transition-colors">
                    <i class="bi bi-navigation-fill"></i> Navigate
                </a>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $v['patient_id'] ?>"
                   class="flex items-center gap-1.5 px-3 py-2 bg-slate-50 text-slate-600 border border-slate-200 rounded-xl text-xs font-semibold hover:bg-slate-100 transition-colors">
                    <i class="bi bi-person-lines-fill"></i> Chart
                </a>
            </div>
        </div>
        <div class="border-t border-slate-100 px-4 py-3 flex flex-wrap gap-2 bg-slate-50/60 no-print">
            <span class="text-xs text-slate-500 font-medium self-center mr-1">Update:</span>
            <?php foreach ($statusDefs as $sKey => $sDef): ?>
            <button onclick="updateStatus(<?= $v['id'] ?>, '<?= $sKey ?>')"
                    class="status-btn px-3 py-1.5 rounded-lg text-xs font-semibold border transition-colors
                           <?= $v['status'] === $sKey
                               ? $sDef['bg'] . ' ' . $sDef['text'] . ' border-transparent ring-2 ring-offset-1 ring-' . explode('-',$sDef['dot'])[1] . '-400'
                               : 'bg-white border-slate-200 text-slate-500 hover:border-slate-300 hover:bg-slate-100' ?>"
                    data-visit="<?= $v['id'] ?>" data-status="<?= $sKey ?>">
                <i class="bi <?= $sDef['icon'] ?> mr-0.5"></i> <?= $sDef['label'] ?>
            </button>
            <?php endforeach; ?>
        </div>
        <div class="border-t border-slate-100 rounded-b-2xl overflow-hidden no-print">
            <button type="button" onclick="toggleNotes(this, <?= $v['id'] ?>)"
                    class="w-full flex items-center gap-2 px-4 py-2.5 text-xs font-semibold text-left transition-colors
                           <?= !empty($v['visit_notes']) ? 'bg-amber-50 text-amber-700 hover:bg-amber-100' : 'bg-slate-50/80 text-slate-500 hover:bg-slate-100' ?>">
                <i class="bi bi-pencil-square text-sm"></i>
                <?php if (!empty($v['visit_notes'])): ?>
                    <span class="truncate flex-1"><?= h(mb_strimwidth($v['visit_notes'], 0, 80, '…')) ?></span>
                    <span class="shrink-0 px-2 py-0.5 bg-amber-200 text-amber-800 rounded-full text-[10px] font-bold">Note saved</span>
                <?php else: ?>
                    <span class="flex-1">Add quick note…</span>
                <?php endif; ?>
                <i class="bi bi-chevron-down text-xs shrink-0 note-chevron transition-transform"></i>
            </button>
            <div class="note-panel hidden px-4 pb-4 pt-3 bg-amber-50/60">
                <textarea id="note-<?= $v['id'] ?>"
                    class="w-full px-3 py-2.5 border border-amber-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-amber-400 focus:border-transparent resize-none transition"
                    rows="3"
                    placeholder="Quick clinical observation — e.g. wound looks improved, patient reports pain 3/10…"
                    ><?= h($v['visit_notes'] ?? '') ?></textarea>
                <div class="flex items-center gap-2 mt-2">
                    <button type="button" onclick="saveNote(<?= $v['id'] ?>, this)"
                            class="px-4 py-2 bg-amber-500 hover:bg-amber-600 active:scale-95 text-white text-xs font-bold rounded-xl transition-all shadow-sm">
                        <i class="bi bi-floppy-fill mr-1"></i> Save Note
                    </button>
                    <span class="note-saved-msg hidden text-xs text-emerald-600 font-semibold">
                        <i class="bi bi-check-circle-fill mr-0.5"></i> Saved!
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; // single-MA visits ?>
</div><!-- /visitList -->
<?php endif; // viewAll / empty / single-MA ?>
<?php endif; // end daily view ?>
</div><!-- /screen-layout -->

<!-- ═══════════════════════ PRINT LAYOUT ═══════════════════════ -->
<div id="print-layout">

    <!-- Header -->
    <div style="border-bottom: 2pt solid #4338ca; padding-bottom: 7pt; margin-bottom: 8pt; display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <div style="font-size: 15pt; font-weight: 900; color: #1e293b; line-height: 1.1;">
                <?= $view === 'week'
                    ? 'Weekly Schedule: ' . date('M j', strtotime($weekStart)) . ' &ndash; ' . date('M j, Y', strtotime($weekEnd))
                    : 'Daily Schedule: ' . date('l, F j, Y', strtotime($date)) ?>
            </div>
            <div style="font-size: 9pt; color: #6366f1; font-weight: 700; margin-top: 2pt;">
                <?= h($ma['full_name']) ?>
            </div>
        </div>
        <div style="text-align: right; font-size: 7.5pt; color: #64748b;">
            <div style="font-weight: 700; font-size: 9pt; color: #1e293b;"><?= h(PRACTICE_NAME) ?></div>
            <div>Printed <?= date('M j, Y') ?> &bull; <?= date('g:i a') ?></div>
        </div>
    </div>

    <!-- Stats row -->
    <?php
    $pColors = [
        'pending'   => ['bg'=>'#f1f5f9','border'=>'#cbd5e1','num'=>'#334155','lbl'=>'#64748b'],
        'en_route'  => ['bg'=>'#eff6ff','border'=>'#bfdbfe','num'=>'#1d4ed8','lbl'=>'#3b82f6'],
        'completed' => ['bg'=>'#f0fdf4','border'=>'#bbf7d0','num'=>'#15803d','lbl'=>'#22c55e'],
        'missed'    => ['bg'=>'#fef2f2','border'=>'#fecaca','num'=>'#dc2626','lbl'=>'#ef4444'],
    ];
    $pCounts = ($view === 'week') ? $weekCounts : $counts;
    ?>
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 8pt; table-layout: fixed;">
        <tr>
            <?php foreach ($statusDefs as $key => $def):
                $pc = $pColors[$key]; ?>
            <td style="padding: 0 <?= $key !== 'missed' ? '5pt' : '0' ?> 0 0;">
                <div style="background: <?= $pc['bg'] ?>; border: 1pt solid <?= $pc['border'] ?>; border-radius: 4pt; padding: 6pt 10pt;">
                    <div style="font-size: 18pt; font-weight: 900; color: <?= $pc['num'] ?>; line-height: 1;"><?= $pCounts[$key] ?></div>
                    <div style="font-size: 6.5pt; font-weight: 700; color: <?= $pc['lbl'] ?>; text-transform: uppercase; letter-spacing: 0.4pt; margin-top: 2pt;"><?= $def['label'] ?></div>
                </div>
            </td>
            <?php endforeach; ?>
        </tr>
    </table>

    <?php if ($view === 'week'): ?>
    <!-- ════ WEEK VIEW ════ -->
    <table style="width: 100%; border-collapse: collapse; table-layout: fixed;">
        <tr style="vertical-align: top;">
            <?php
            $dotC      = ['pending'=>'#94a3b8','en_route'=>'#3b82f6','completed'=>'#22c55e','missed'=>'#ef4444'];
            $pVtLabels = ['routine'=>'Routine','new_patient'=>'New Pt','wound_care'=>'Wound Care','awv'=>'AWV','ccm'=>'CCM','il'=>'IL'];
            for ($d = 0; $d < 7; $d++):
                $colDate    = date('Y-m-d', strtotime($weekStart . ' +' . $d . ' days'));
                $colIsToday = $colDate === date('Y-m-d');
                $colVisits  = $visitsByDate[$colDate] ?? [];
            ?>
            <td style="vertical-align: top; <?= $d < 6 ? 'padding-right: 3pt;' : '' ?>">
                <div style="background: <?= $colIsToday ? '#4338ca' : '#f1f5f9' ?>; border: 1pt solid <?= $colIsToday ? '#4338ca' : '#e2e8f0' ?>; border-bottom: none; border-radius: 3pt 3pt 0 0; padding: 4pt 5pt;">
                    <div style="font-size: 6.5pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5pt; color: <?= $colIsToday ? '#c7d2fe' : '#94a3b8' ?>;"><?= date('D', strtotime($colDate)) ?></div>
                    <div style="font-size: 10pt; font-weight: 900; color: <?= $colIsToday ? '#fff' : '#1e293b' ?>; line-height: 1.1;"><?= date('M j', strtotime($colDate)) ?></div>
                    <?php if (count($colVisits)): ?>
                    <div style="font-size: 6.5pt; color: <?= $colIsToday ? '#a5b4fc' : '#94a3b8' ?>; margin-top: 1pt;"><?= count($colVisits) ?> visit<?= count($colVisits) !== 1 ? 's' : '' ?></div>
                    <?php endif; ?>
                </div>
                <div style="border: 1pt solid <?= $colIsToday ? '#c7d2fe' : '#e2e8f0' ?>; border-top: none; border-radius: 0 0 3pt 3pt; padding: 3pt; min-height: 50pt;">
                    <?php if (empty($colVisits)): ?>
                    <div style="font-size: 7pt; color: #cbd5e1; text-align: center; padding: 8pt 0;">—</div>
                    <?php else: foreach ($colVisits as $cv):
                        $cvBg  = $cv['status']==='completed'?'#f0fdf4':($cv['status']==='en_route'?'#eff6ff':($cv['status']==='missed'?'#fef2f2':'#f8fafc'));
                        $cvBor = $cv['status']==='completed'?'#bbf7d0':($cv['status']==='en_route'?'#bfdbfe':($cv['status']==='missed'?'#fecaca':'#e2e8f0'));
                    ?>
                    <div style="margin-bottom: 2.5pt; padding: 3pt 4pt; background: <?= $cvBg ?>; border: 0.5pt solid <?= $cvBor ?>; border-radius: 2pt; page-break-inside: avoid;">
                        <div style="display: flex; align-items: flex-start; gap: 3pt;">
                            <span style="display: inline-block; width: 5.5pt; height: 5.5pt; border-radius: 50%; background: <?= $dotC[$cv['status']] ?>; margin-top: 1.5pt; flex-shrink: 0;"></span>
                            <div>
                                <div style="font-size: 7.5pt; font-weight: 700; color: #1e293b; line-height: 1.2; word-break: break-word;"><?= h($cv['patient_name']) ?></div>
                                <?php if ($cv['visit_time']): ?><div style="font-size: 6.5pt; color: #64748b;"><?= date('g:i A', strtotime($cv['visit_time'])) ?></div><?php endif; ?>
                                <?php $cvVt = $cv['visit_type'] ?? 'routine'; if ($cvVt !== 'routine'): ?>
                                <div style="font-size: 6pt; color: #6366f1; font-weight: 600;"><?= $pVtLabels[$cvVt] ?? '' ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </td>
            <?php endfor; ?>
        </tr>
    </table>

    <?php else: ?>
    <!-- ════ DAY VIEW ════ -->
    <?php if (empty($visits)): ?>
    <p style="text-align: center; color: #94a3b8; font-size: 9pt; padding: 20pt 0; border: 1pt dashed #e2e8f0; border-radius: 4pt;">No visits scheduled for this day.</p>
    <?php else:
        $pVtLabels2 = ['routine'=>'Routine','new_patient'=>'New Patient','wound_care'=>'Wound Care','awv'=>'Annual Wellness Visit','ccm'=>'CCM','il'=>'IL Disclosure'];
        $pSColors   = [
            'pending'   => ['bg'=>'#f1f5f9','color'=>'#475569','border'=>'#cbd5e1','dot'=>'#94a3b8'],
            'en_route'  => ['bg'=>'#eff6ff','color'=>'#1d4ed8','border'=>'#bfdbfe','dot'=>'#3b82f6'],
            'completed' => ['bg'=>'#f0fdf4','color'=>'#15803d','border'=>'#bbf7d0','dot'=>'#22c55e'],
            'missed'    => ['bg'=>'#fef2f2','color'=>'#dc2626','border'=>'#fecaca','dot'=>'#ef4444'],
        ];
    ?>

    <!-- visit count label -->
    <div style="font-size: 8pt; color: #64748b; margin-bottom: 5pt; font-weight: 600;">
        <?= count($visits) ?> visit<?= count($visits) !== 1 ? 's' : '' ?> scheduled
    </div>

    <table style="width: 100%; border-collapse: collapse; border: 1pt solid #cbd5e1; border-radius: 4pt; font-size: 8.5pt;">
        <colgroup>
            <col style="width: 18pt;">          <!-- # -->
            <col style="width: 42pt;">          <!-- time -->
            <col>                               <!-- patient + notes -->
            <col style="width: 30%;">          <!-- address / phone -->
            <col style="width: 60pt;">         <!-- visit type -->
            <col style="width: 52pt;">         <!-- status -->
            <col style="width: 44pt;">         <!-- sign-off -->
        </colgroup>
        <thead>
            <tr style="background: #1e3a8a; color: #fff;">
                <th style="padding: 6pt 5pt; text-align: center; font-size: 7pt; font-weight: 700; border-right: 0.5pt solid #3b5bdb;">#</th>
                <th style="padding: 6pt 5pt; text-align: left;   font-size: 7pt; font-weight: 700; border-right: 0.5pt solid #3b5bdb;">Time</th>
                <th style="padding: 6pt 5pt; text-align: left;   font-size: 7pt; font-weight: 700; border-right: 0.5pt solid #3b5bdb;">Patient</th>
                <th style="padding: 6pt 5pt; text-align: left;   font-size: 7pt; font-weight: 700; border-right: 0.5pt solid #3b5bdb;">Address &amp; Phone</th>
                <th style="padding: 6pt 5pt; text-align: left;   font-size: 7pt; font-weight: 700; border-right: 0.5pt solid #3b5bdb;">Visit Type</th>
                <th style="padding: 6pt 5pt; text-align: center; font-size: 7pt; font-weight: 700; border-right: 0.5pt solid #3b5bdb;">Status</th>
                <th style="padding: 6pt 5pt; text-align: center; font-size: 7pt; font-weight: 700;">MA Sign</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($visits as $idx => $v):
                $psc  = $pSColors[$v['status']];
                $vt2  = $v['visit_type'] ?? 'routine';
                $rowBg = $idx % 2 === 1 ? '#f8fafc' : '#ffffff';
            ?>
            <tr style="background: <?= $rowBg ?>; border-top: 0.75pt solid #e2e8f0; page-break-inside: avoid; vertical-align: middle;">
                <!-- # -->
                <td style="padding: 6pt 5pt; text-align: center; border-right: 0.5pt solid #e2e8f0;">
                    <span style="display: inline-block; width: 14pt; height: 14pt; background: #e0e7ff; border-radius: 3pt; font-weight: 800; color: #3730a3; font-size: 7.5pt; text-align: center; line-height: 14pt;"><?= $idx + 1 ?></span>
                </td>
                <!-- Time -->
                <td style="padding: 6pt 5pt; font-size: 8.5pt; font-weight: 700; color: #1e293b; white-space: nowrap; border-right: 0.5pt solid #e2e8f0;">
                    <?= $v['visit_time'] ? date('g:i A', strtotime($v['visit_time'])) : '<span style="color:#cbd5e1;">—</span>' ?>
                </td>
                <!-- Patient + notes -->
                <td style="padding: 6pt 5pt; border-right: 0.5pt solid #e2e8f0;">
                    <div style="font-size: 9.5pt; font-weight: 800; color: #0f172a;"><?= h($v['patient_name']) ?></div>
                    <?php if (!empty($v['notes'])): ?>
                    <div style="margin-top: 2pt; font-size: 6.5pt; color: #92400e; background: #fffbeb; border: 0.5pt solid #fde68a; border-radius: 2pt; padding: 1.5pt 4pt; display: inline-block;"><?= h($v['notes']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($v['visit_notes'])): ?>
                    <div style="margin-top: 2pt; font-size: 6.5pt; color: #374151; font-style: italic;">&#128203; <?= h($v['visit_notes']) ?></div>
                    <?php endif; ?>
                </td>
                <!-- Address / Phone -->
                <td style="padding: 6pt 5pt; border-right: 0.5pt solid #e2e8f0; font-size: 8pt; color: #334155; line-height: 1.4;">
                    <?php if ($v['patient_address']): ?><div><?= h($v['patient_address']) ?></div><?php endif; ?>
                    <?php if ($v['patient_phone']): ?><div style="color: #64748b; margin-top: 1pt;"><?= h($v['patient_phone']) ?></div><?php endif; ?>
                    <?php if (!$v['patient_address'] && !$v['patient_phone']): ?><span style="color:#cbd5e1;">—</span><?php endif; ?>
                </td>
                <!-- Visit Type -->
                <td style="padding: 6pt 5pt; font-size: 7.5pt; color: #4f46e5; font-weight: 700; border-right: 0.5pt solid #e2e8f0;">
                    <?= h($pVtLabels2[$vt2] ?? 'Routine') ?>
                </td>
                <!-- Status badge -->
                <td style="padding: 6pt 5pt; text-align: center; border-right: 0.5pt solid #e2e8f0;">
                    <span style="display: inline-flex; align-items: center; gap: 3pt; padding: 2.5pt 6pt; border-radius: 10pt; font-size: 7pt; font-weight: 700; background: <?= $psc['bg'] ?>; color: <?= $psc['color'] ?>; border: 0.5pt solid <?= $psc['border'] ?>; white-space: nowrap;">
                        <span style="display:inline-block; width:5pt; height:5pt; border-radius:50%; background:<?= $psc['dot'] ?>;"></span>
                        <?= $statusDefs[$v['status']]['label'] ?>
                    </span>
                </td>
                <!-- Sign-off line -->
                <td style="padding: 6pt 5pt; text-align: center;">
                    <div style="border-bottom: 1pt solid #94a3b8; width: 36pt; margin: 0 auto; height: 14pt;"></div>
                    <div style="font-size: 5.5pt; color: #94a3b8; margin-top: 1pt; text-transform: uppercase; letter-spacing: 0.3pt;">initials</div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <!-- Footer row -->
        <tfoot>
            <tr style="background: #f1f5f9; border-top: 1.5pt solid #cbd5e1;">
                <td colspan="5" style="padding: 5pt 7pt; font-size: 7.5pt; color: #475569; font-weight: 700;">
                    Total: <?= count($visits) ?> visit<?= count($visits) !== 1 ? 's' : '' ?> &mdash;
                    <?= $counts['completed'] ?> completed &bull;
                    <?= $counts['pending'] ?> pending &bull;
                    <?= $counts['en_route'] ?> en route &bull;
                    <?= $counts['missed'] ?> missed
                </td>
                <td colspan="2" style="padding: 5pt 7pt; font-size: 7pt; color: #64748b; text-align: right;">
                    Confirmed by: ________________________
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- Signature block at bottom -->
    <div style="margin-top: 18pt; display: flex; gap: 30pt;">
        <div style="flex: 1;">
            <div style="border-bottom: 1pt solid #334155; height: 20pt;"></div>
            <div style="font-size: 7pt; color: #64748b; margin-top: 3pt; text-transform: uppercase; letter-spacing: 0.4pt;">MA Signature</div>
        </div>
        <div style="flex: 1;">
            <div style="border-bottom: 1pt solid #334155; height: 20pt;"></div>
            <div style="font-size: 7pt; color: #64748b; margin-top: 3pt; text-transform: uppercase; letter-spacing: 0.4pt;">Supervisor Signature</div>
        </div>
        <div style="width: 70pt;">
            <div style="border-bottom: 1pt solid #334155; height: 20pt;"></div>
            <div style="font-size: 7pt; color: #64748b; margin-top: 3pt; text-transform: uppercase; letter-spacing: 0.4pt;">Date</div>
        </div>
    </div>

    <?php endif; ?>
    <?php endif; ?>

</div><!-- /print-layout -->

<script>
const CSRF   = '<?= csrfToken() ?>';
const BASE   = '<?= BASE_URL ?>';

// ── One-tap Start Visit ───────────────────────────────────────────────────────
function startVisit(visitId, patientId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split text-sm animate-spin"></i> Starting…';

    fetch(BASE + '/api/schedule_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: CSRF, id: visitId, action: 'status', status: 'en_route' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            // Navigate straight to the patient's forms page
            window.location.href = BASE + '/patient_view.php?id=' + patientId + '&tab=forms&visit=' + visitId;
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-play-fill text-sm"></i> Start Visit';
            alert('Error: ' + (data.error || 'Could not start visit.'));
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-fill text-sm"></i> Start Visit';
        alert('Network error. Please try again.');
    });
}

// ── Quick Visit Notes ────────────────────────────────────────────────────────
function toggleNotes(btn, visitId) {
    const card  = btn.closest('[id^="visit-"]');
    const panel = card.querySelector('.note-panel');
    const chev  = btn.querySelector('.note-chevron');
    const open  = panel.classList.toggle('hidden');
    chev.style.transform = open ? '' : 'rotate(180deg)';
    if (!open) {
        // focus textarea
        const ta = document.getElementById('note-' + visitId);
        if (ta) { ta.focus(); ta.selectionStart = ta.value.length; }
    }
}

async function saveNote(visitId, btn) {
    const ta  = document.getElementById('note-' + visitId);
    const msg = btn.closest('.flex').querySelector('.note-saved-msg');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split mr-1"></i> Saving…';
    try {
        const r = await fetch(BASE + '/api/schedule_update.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ csrf: CSRF, id: visitId, action: 'save_note', visit_notes: ta.value })
        });
        const d = await r.json();
        if (d.ok) {
            // Update the toggle button preview text
            const card    = ta.closest('[id^="visit-"]');
            const togBtn  = card.querySelector('button[onclick^="toggleNotes"]');
            const preview = togBtn.querySelector('span.flex-1');
            const badge   = togBtn.querySelector('span.shrink-0');
            if (ta.value.trim()) {
                preview.textContent = ta.value.length > 80 ? ta.value.slice(0,80) + '…' : ta.value;
                togBtn.classList.remove('bg-slate-50/80','text-slate-500','hover:bg-slate-100');
                togBtn.classList.add('bg-amber-50','text-amber-700','hover:bg-amber-100');
                if (!badge) {
                    const b = document.createElement('span');
                    b.className = 'shrink-0 px-2 py-0.5 bg-amber-200 text-amber-800 rounded-full text-[10px] font-bold';
                    b.textContent = 'Note saved';
                    togBtn.querySelector('.note-chevron').before(b);
                } else { badge.textContent = 'Note saved'; }
            } else {
                preview.textContent = 'Add quick note…';
                togBtn.classList.add('bg-slate-50/80','text-slate-500','hover:bg-slate-100');
                togBtn.classList.remove('bg-amber-50','text-amber-700','hover:bg-amber-100');
                if (badge) badge.remove();
            }
            msg.classList.remove('hidden');
            setTimeout(() => msg.classList.add('hidden'), 2500);
        } else { alert(d.error || 'Could not save note.'); }
    } catch { alert('Network error.'); }
    btn.disabled = false;
    btn.innerHTML = orig;
}

// ── Status update (status bar buttons) ───────────────────────────────────────
function updateStatus(visitId, status) {
    fetch(BASE + '/api/schedule_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: CSRF, id: visitId, action: 'status', status: status })
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Could not update status.'));
        }
    })
    .catch(() => alert('Network error. Please try again.'));
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
