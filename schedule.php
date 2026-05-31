<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/visit_types.php';
requireNotBilling();

// Map visit type to the primary form URL (used by Start Visit and Open Forms buttons)
function firstFormUrl(string $visitType, int $patientId, int $visitId, string $visitSubtype = ''): string {
    $slug = strtolower(trim($visitType));
    
    // Base query params
    $params = '?patient_id=' . $patientId . '&visit_id=' . $visitId . '&sched_visit_type=' . urlencode($visitType);

    if (str_contains($slug, 'new')) {
        $npType = ($visitSubtype === 'primary_care') ? 'primary_care' : 'wound_care';
        return '/forms/new_patient_pocket.php' . $params . '&np_type=' . $npType;
    } elseif ($slug === 'wound_care') {
        return '/forms/wound_care.php' . $params;
    } elseif (in_array($slug, ['awv', 'medicare_awv'])) {
        return '/forms/medicare_awv.php' . $params;
    } elseif ($slug === 'ccm') {
        return '/forms/ccm_consent.php' . $params;
    } elseif (in_array($slug, ['il', 'il_disclosure'])) {
        return '/forms/il_disclosure.php' . $params;
    } elseif ($slug === 'cognitive_wellness') {
        return '/forms/cognitive_wellness.php' . $params;
    }

    // Fallback and routine
    return '/forms/vital_cs.php' . $params;
}

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
// Default to 'all' for admins when no ma_id param is present
$viewAll  = isAdmin() && (($_GET['ma_id'] ?? 'all') === 'all');
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

// Provider filter — synced with dashboard session filter
// If ?provider= is explicitly passed, update the session; otherwise fall back to session value
if (array_key_exists('provider', $_GET)) {
    $_SESSION['dash_provider_filter'] = trim($_GET['provider']);
}
$filterProvider = $_SESSION['dash_provider_filter'] ?? '';

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
    $wkSql = "
        SELECT sc.*,
               CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
               p.id AS patient_id
        FROM `schedule` sc
        JOIN patients p ON p.id = sc.patient_id
        WHERE sc.ma_id = ? AND sc.visit_date BETWEEN ? AND ?
    ";
    $wkParams = [$viewMaId, $weekStart, $weekEnd];
    if ($filterProvider !== '') { $wkSql .= " AND sc.provider_name = ?"; $wkParams[] = $filterProvider; }
    $wkSql .= " ORDER BY sc.visit_date ASC, sc.visit_order ASC, sc.visit_time ASC";
    $wkStmt = $pdo->prepare($wkSql);
    $wkStmt->execute($wkParams);
    $weekVisits = $wkStmt->fetchAll();
    foreach ($weekVisits as $wv) {
        $visitsByDate[$wv['visit_date']][] = $wv;
        $weekCounts[$wv['status']]++;
    }
}

// Fetch schedule for this MA + date, ordered by visit_order
$schedSql = "
    SELECT sc.*, 
           CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
           p.address AS patient_address,
           p.phone   AS patient_phone,
           p.id      AS patient_id
    FROM `schedule` sc
    JOIN patients p ON p.id = sc.patient_id
    WHERE sc.ma_id = ? AND sc.visit_date = ?
";
$schedParams = [$viewMaId, $date];
if ($filterProvider !== '') { $schedSql .= " AND sc.provider_name = ?"; $schedParams[] = $filterProvider; }
$schedSql .= " ORDER BY sc.visit_order ASC, sc.visit_time ASC";
$schedStmt = $pdo->prepare($schedSql);
$schedStmt->execute($schedParams);
$visits = $schedStmt->fetchAll();

// When admin views all MAs, also build grouped structure
$allMaVisits = []; // [provider_name => ['name'=>'...', 'mas'=>[], 'counts'=>[], 'visits'=>[]]]
if ($viewAll && $view === 'day') {
    $allSql = "
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
    ";
    $allParams = [$date];
    if ($filterProvider !== '') { $allSql .= " AND sc.provider_name = ?"; $allParams[] = $filterProvider; }
    $allSql .= " ORDER BY COALESCE(NULLIF(TRIM(sc.provider_name),''),'zzz'), sc.visit_order ASC, sc.visit_time ASC";
    $allStmt = $pdo->prepare($allSql);
    $allStmt->execute($allParams);
    foreach ($allStmt->fetchAll() as $av) {
        $pKey = trim($av['provider_name'] ?? '') ?: '— Unassigned —';
        if (!isset($allMaVisits[$pKey])) {
            $allMaVisits[$pKey] = [
                'name'   => $pKey,
                'mas'    => [],
                'counts' => ['pending'=>0,'en_route'=>0,'completed'=>0,'missed'=>0],
                'visits' => [],
            ];
        }
        $allMaVisits[$pKey]['visits'][] = $av;
        $allMaVisits[$pKey]['counts'][$av['status']]++;
        if (!empty($av['ma_name']) && !in_array($av['ma_name'], $allMaVisits[$pKey]['mas'], true)) {
            $allMaVisits[$pKey]['mas'][] = $av['ma_name'];
        }
    }
    // Flatten for total stats
    $visits = array_merge(...(array_column($allMaVisits, 'visits') ?: [[]]));
}

// Stats
$counts = ['pending'=>0,'en_route'=>0,'completed'=>0,'missed'=>0];
foreach ($visits as $v) $counts[$v['status']]++;

// Fetch missed-visit reasons from form_submissions for any missed visit today
$missedReasons = []; // [patient_id => reason string]
$_missedPids = array_unique(array_column(
    array_filter($visits, fn($v) => $v['status'] === 'missed'),
    'patient_id'
));
if ($_missedPids) {
    $_in = implode(',', array_fill(0, count($_missedPids), '?'));
    $_mrStmt = $pdo->prepare(
        "SELECT patient_id,
                JSON_UNQUOTE(JSON_EXTRACT(form_data, '$.missed_visit_reason')) AS missed_reason
         FROM form_submissions
         WHERE patient_id IN ($_in) AND form_type = 'vital_cs'
           AND DATE(created_at) = CURDATE()
         ORDER BY created_at DESC"
    );
    $_mrStmt->execute($_missedPids);
    foreach ($_mrStmt->fetchAll() as $_mr) {
        $pid = (int)$_mr['patient_id'];
        if (!isset($missedReasons[$pid]) && !empty($_mr['missed_reason'])) {
            $missedReasons[$pid] = $_mr['missed_reason'];
        }
    }
}

// Route Map — ordered unique addresses from today's visits (for "Open Route Map" button)
$routeAddresses = [];
foreach ($visits as $rv) {
    $a = trim($rv['patient_address'] ?? '');
    if ($a !== '' && !in_array($a, $routeAddresses, true)) $routeAddresses[] = $a;
}

// All MAs for admin switcher
$allMas = [];
if (isAdmin()) {
    $allMas = $pdo->query("SELECT id, full_name FROM staff WHERE active=1 ORDER BY full_name")->fetchAll();
}

// Provider options for the filter dropdown
$providerOptions = [];
try {
    $providerOptions = $pdo->query("SELECT full_name FROM staff WHERE active=1 AND role IN ('admin','provider') ORDER BY full_name")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { /* ignore */ }

// Provider staff accounts for the edit modal dropdown
$providerStaff = $pdo->query("SELECT id, full_name FROM staff WHERE active=1 AND role IN ('provider','admin') AND username != 'admin' ORDER BY full_name")->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<!-- Schedule Page Header - Clean Mobile-First V3 -->
<style>
.hide-scrollbar::-webkit-scrollbar { display: none; }
.hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
/* GPS badge row: hidden until JS reveals it */
#gpsStatusRow { display: none; }
#gpsStatusRow.gps-visible { display: flex; }
</style>
<?php $maParam = $viewAll ? 'all' : $viewMaId; $pParam = $filterProvider !== '' ? '&provider=' . urlencode($filterProvider) : ''; ?>
<div class="sticky top-[60px] z-30 bg-white/95 backdrop-blur-sm border-b border-slate-100 shadow-sm no-print -mx-4 px-4 sm:mx-0 sm:px-0 pt-3 pb-2 mb-5">
    <div class="flex flex-col gap-2">

        <!-- Row 1: Title + subtitle + Day/Week toggle -->
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-lg font-extrabold text-slate-800 tracking-tight leading-none">
                    <i class="bi bi-calendar3 text-indigo-500 mr-1 text-base"></i>Schedule
                </h2>
                <p class="text-[11px] text-slate-400 font-medium mt-0.5 truncate">
                    <?= h($ma['full_name']) ?> &middot;
                    <?= $view === 'week'
                        ? date('M j', strtotime($weekStart)) . '–' . date('M j', strtotime($weekEnd))
                        : date('D, M j, Y', strtotime($date)) ?>
                    <?php if ($isToday && $view === 'day'): ?><span class="ml-1 px-1.5 py-0.5 bg-indigo-100 text-indigo-700 rounded font-bold text-[9px] uppercase tracking-wide">Today</span><?php endif; ?>
                </p>
            </div>
            <div class="flex items-center bg-slate-100 p-0.5 rounded-xl shrink-0">
                <a href="?date=<?= $date ?>&ma_id=<?= $maParam ?>&view=day<?= $pParam ?>"
                   class="px-3 py-1 rounded-[10px] text-xs font-bold transition-all <?= $view === 'day' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-400 hover:text-slate-600' ?>">Day</a>
                <a href="?date=<?= $weekStart ?>&ma_id=<?= $maParam ?>&view=week<?= $pParam ?>"
                   class="px-3 py-1 rounded-[10px] text-xs font-bold transition-all <?= $view === 'week' ? 'bg-white text-indigo-700 shadow-sm' : 'text-slate-400 hover:text-slate-600' ?>">Week</a>
            </div>
        </div>

        <!-- Row 2: GPS status badge (own isolated row, shown only by JS) -->
        <?php if (in_array($_SESSION['role'] ?? '', ['ma', 'admin'])): ?>
        <div id="gpsStatusRow" class="items-center">
            <span id="gpsStatusBadge" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-semibold rounded-full bg-slate-100 text-slate-500 border border-slate-200">
                <i class="bi bi-geo-alt"></i> Locating…
            </span>
        </div>
        <?php endif; ?>

        <!-- Row 3: Date nav + filters + actions (horizontally scrollable) -->
        <div class="flex items-center gap-1.5 overflow-x-auto hide-scrollbar pb-0.5">

            <!-- Date navigator pill -->
            <div class="flex items-center rounded-lg border border-indigo-100 bg-indigo-50 shrink-0 overflow-hidden">
                <?php if ($view === 'week'): ?>
                <a href="?date=<?= $prevWeek ?>&ma_id=<?= $maParam ?>&view=week<?= $pParam ?>" class="px-2 py-1.5 text-indigo-600 hover:bg-indigo-100 transition-colors"><i class="bi bi-chevron-left text-[10px]"></i></a>
                <span class="px-2 py-1.5 text-[10px] font-bold text-indigo-700 whitespace-nowrap"><?= date('M j', strtotime($weekStart)) ?>–<?= date('j', strtotime($weekEnd)) ?></span>
                <a href="?date=<?= $nextWeek ?>&ma_id=<?= $maParam ?>&view=week<?= $pParam ?>" class="px-2 py-1.5 text-indigo-600 hover:bg-indigo-100 transition-colors"><i class="bi bi-chevron-right text-[10px]"></i></a>
                <?php else: ?>
                <a href="?date=<?= $prevDate ?>&ma_id=<?= $maParam ?>&view=day<?= $pParam ?>" class="px-2 py-1.5 text-indigo-600 hover:bg-indigo-100 transition-colors"><i class="bi bi-chevron-left text-[10px]"></i></a>
                <a href="?date=<?= date('Y-m-d') ?>&ma_id=<?= $maParam ?>&view=day<?= $pParam ?>"
                   class="px-2.5 py-1.5 text-[10px] font-bold whitespace-nowrap transition-colors <?= $isToday ? 'bg-indigo-600 text-white' : 'text-indigo-700 hover:bg-indigo-100' ?>">
                    <?= $isToday ? 'Today' : date('M j', strtotime($date)) ?>
                </a>
                <a href="?date=<?= $nextDate ?>&ma_id=<?= $maParam ?>&view=day<?= $pParam ?>" class="px-2 py-1.5 text-indigo-600 hover:bg-indigo-100 transition-colors"><i class="bi bi-chevron-right text-[10px]"></i></a>
                <?php endif; ?>
            </div>

            <!-- Provider filter -->
            <?php if (!empty($providerOptions)): ?>
            <form method="GET" class="shrink-0" style="width:97px">
                <input type="hidden" name="date" value="<?= h($date) ?>">
                <input type="hidden" name="view" value="<?= h($view) ?>">
                <input type="hidden" name="ma_id" value="<?= $viewAll ? 'all' : $viewMaId ?>">
                <select name="provider" onchange="this.form.submit()"
                        class="w-full px-1.5 py-1.5 border border-slate-200 rounded-lg text-[10px] font-semibold bg-white text-slate-700 shadow-sm outline-none focus:ring-2 focus:ring-indigo-300 transition">
                    <option value="">All Providers</option>
                    <?php foreach ($providerOptions as $pOpt): ?>
                    <option value="<?= h($pOpt) ?>" <?= $filterProvider === $pOpt ? 'selected' : '' ?>><?= h($pOpt) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php endif; ?>

            <!-- Route map button -->
            <?php if ($view === 'day' && count($routeAddresses) >= 1): ?>
            <button onclick="openRouteMapModal()"
                    class="shrink-0 flex items-center gap-1 px-2 py-1.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white rounded-lg text-[10px] font-bold shadow-sm transition-all">
                <i class="bi bi-map-fill text-[10px]"></i>
                <span class="bg-white/25 px-1 rounded text-[9px] leading-none py-0.5"><?= count($routeAddresses) ?></span>
            </button>
            <?php endif; ?>

            <!-- Admin: manage schedule -->
            <?php if (isAdmin()): ?>
            <a href="<?= BASE_URL ?>/admin/schedule_manage.php?date=<?= $date ?>"
               class="shrink-0 flex items-center justify-center w-7 h-7 bg-slate-800 hover:bg-slate-900 text-white rounded-lg shadow-sm transition-colors" title="Manage">
                <i class="bi bi-pencil-fill text-[10px]"></i>
            </a>
            <?php endif; ?>

            <!-- Print -->
            <button onclick="window.print()"
                    class="shrink-0 flex items-center justify-center w-7 h-7 bg-white border border-slate-200 text-slate-500 rounded-lg shadow-sm hover:bg-slate-50 transition-colors" title="Print">
                <i class="bi bi-printer-fill text-[10px]"></i>
            </button>

        </div>
    </div>
</div>
<script>
// Show GPS badge row once JS has a status to report
(function() {
    var orig = window.__pdSetGpsBadge;
    var _observer = new MutationObserver(function() {
        var badge = document.getElementById('gpsStatusBadge');
        var row   = document.getElementById('gpsStatusRow');
        if (badge && row && badge.textContent.trim() !== '') {
            row.classList.add('gps-visible');
        }
    });
    var badge = document.getElementById('gpsStatusBadge');
    if (badge) _observer.observe(badge, { childList: true, subtree: true, characterData: true, attributes: true });
})();
</script>

<style>
/* Screen: hide the dedicated print layout */

#print-layout { display: none; }

/* Hide print-only header on screen */
.print-header { display: none; }

/* Status bar: 2 cols on mobile, 4 on sm+ */
.print-stat-bar { grid-template-columns: repeat(2, 1fr) !important; }
@media (min-width: 640px) {
    .print-stat-bar { grid-template-columns: repeat(4, 1fr) !important; }
}

@media print {
    .print-header { display: block; }
    .print-stat-bar { grid-template-columns: repeat(4, 1fr) !important; }
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
<?php
// ── Visit card border colours keyed by status ─────────────────────────────
$_sbc = ['pending'=>'#94a3b8','en_route'=>'#3b82f6','completed'=>'#22c55e','missed'=>'#ef4444'];
$_vtl = ['routine'=>'Follow-Up','new_patient'=>'New Patient','wound_care'=>'Wound Care','awv'=>'Annual Wellness','ccm'=>'CCM','il'=>'IL Disc.'];
$statusDefs = [
    'pending'   => ['label'=>'Pending',   'bg'=>'bg-slate-100',   'text'=>'text-slate-600',   'border'=>'border-slate-200',   'icon'=>'bi-clock'],
    'en_route'  => ['label'=>'En Route',  'bg'=>'bg-blue-50',     'text'=>'text-blue-700',    'border'=>'border-blue-200',    'icon'=>'bi-car-front-fill'],
    'completed' => ['label'=>'Completed', 'bg'=>'bg-emerald-50',  'text'=>'text-emerald-700', 'border'=>'border-emerald-200', 'icon'=>'bi-check-circle-fill'],
    'missed'    => ['label'=>'Missed',    'bg'=>'bg-rose-50',     'text'=>'text-rose-700',    'border'=>'border-rose-200',    'icon'=>'bi-x-circle-fill'],
];

/** Renders one compact visit card. $showMaName=true → show MA; false → show Provider */
$renderVisitCard = function(array $v, int $idx, bool $showMaName) use ($statusDefs, $_sbc, $_vtl, $missedReasons): void {
    $sd      = $statusDefs[$v['status']];
    $addr    = $v['patient_address'] ? rawurlencode($v['patient_address']) : '';
    $mapsUrl = $addr ? 'https://www.google.com/maps/dir/?api=1&destination='.$addr : '#';
    $vt      = $v['visit_type'] ?? 'routine';
    $href    = in_array($v['status'], ['pending','en_route'])
        ? BASE_URL . firstFormUrl($v['visit_type'] ?? 'routine', $v['patient_id'], $v['id'], $v['visit_subtype'] ?? '')
        : BASE_URL . '/patient_view.php?id=' . $v['patient_id'];

    // Status header gradients + config
    $statusGrad = [
        'pending'   => 'linear-gradient(135deg,#334155 0%,#64748b 100%)',
        'en_route'  => 'linear-gradient(135deg,#1e3a8a 0%,#3b82f6 100%)',
        'completed' => 'linear-gradient(135deg,#064e3b 0%,#10b981 100%)',
        'missed'    => 'linear-gradient(135deg,#7f1d1d 0%,#f87171 100%)',
    ];
    $vtIcons = [
        'routine'     => 'bi-arrow-repeat',
        'new_patient' => 'bi-person-plus-fill',
        'wound_care'  => 'bi-bandaid-fill',
        'awv'         => 'bi-heart-pulse-fill',
        'ccm'         => 'bi-clipboard2-heart-fill',
        'il'          => 'bi-capsule',
    ];
    $grad    = $statusGrad[$v['status']];
    $vtIcon  = $vtIcons[$vt] ?? 'bi-calendar2-check';
    $_subtypeLabels = ['wound_care'=>'Wound Care','primary_care'=>'Primary Care'];
    $subtypeLabel = ($vt === 'new_patient' && !empty($v['visit_subtype'])) ? ($_subtypeLabels[$v['visit_subtype']] ?? null) : null;
    ?>

    <div class="rounded-2xl mb-4 overflow-hidden flex flex-col print-visit-card"
         id="visit-<?= $v['id'] ?>"
         data-status="<?= $v['status'] ?>"
         style="box-shadow:0 8px 32px rgba(0,0,0,0.18),0 2px 8px rgba(0,0,0,0.10);">

        <!-- ── HEADER: colored gradient ── -->
        <div style="background:<?= $grad ?>;padding:18px 16px 22px;position:relative;">
            <!-- top row: visit # + edit -->
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <span style="background:rgba(255,255,255,0.18);color:#fff;border:1px solid rgba(255,255,255,0.3);
                             display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border-radius:20px;
                             font-size:12px;font-weight:800;letter-spacing:0.03em;">
                    <i class="bi bi-hash" style="font-size:11px;"></i><?= $idx + 1 ?>
                </span>
                <div style="display:flex;align-items:center;gap:8px;">
                    <?php if ($v['visit_time']): ?>
                    <span style="background:rgba(255,255,255,0.18);color:#fff;border:1px solid rgba(255,255,255,0.25);
                                 display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:20px;
                                 font-size:12px;font-weight:700;">
                        <i class="bi bi-clock" style="font-size:11px;"></i><?= date('g:i A', strtotime($v['visit_time'])) ?>
                    </span>
                    <?php endif; ?>

                    <?php if (isAdmin()): ?>
                    <button onclick="openEditModal(<?= (int)$v['id'] ?>, <?= htmlspecialchars(json_encode($v['patient_name'] ?? ''), ENT_QUOTES) ?>)"
                            style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;
                                   border-radius:10px;background:rgba(255,255,255,0.18);border:1px solid rgba(255,255,255,0.3);
                                   color:#fff;cursor:pointer;transition:background 0.15s;" title="Edit visit"
                            class="no-print">
                        <i class="bi bi-pencil-fill" style="font-size:11px;"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($v['visit_started_at']) || !empty($v['visit_ended_at'])): ?>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;">
                <?php if (!empty($v['visit_started_at'])): ?>
                <span style="background:rgba(255,255,255,0.18);color:#fff;border:1px solid rgba(255,255,255,0.25);
                             display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:16px;font-size:11px;font-weight:700;">
                    <i class="bi bi-play-circle-fill"></i> <?= date('g:i A', strtotime($v['visit_started_at'])) ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($v['visit_ended_at'])): ?>
                <span style="background:rgba(255,255,255,0.18);color:#fff;border:1px solid rgba(255,255,255,0.25);
                             display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:16px;font-size:11px;font-weight:700;">
                    <i class="bi bi-stop-circle-fill"></i> <?= date('g:i A', strtotime($v['visit_ended_at'])) ?>
                </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- ── BODY: dark slate ── -->
        <div style="background:#0f172a;padding:16px 16px 4px;">

            <!-- visit type + subtype badges -->
            <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin-bottom:12px;">
                <span style="background:rgba(99,102,241,0.25);color:#a5b4fc;border:1px solid rgba(99,102,241,0.4);
                             display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;
                             font-size:12px;font-weight:800;">
                    <i class="bi <?= $vtIcon ?>" style="font-size:12px;"></i>
                    <?= h($_vtl[$vt] ?? 'Follow-Up') ?>
                </span>
                <?php if ($subtypeLabel): ?>
                <span style="background:rgba(16,185,129,0.18);color:#6ee7b7;border:1px solid rgba(16,185,129,0.35);
                             display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;
                             font-size:12px;font-weight:700;">
                    <i class="bi bi-tag-fill" style="font-size:11px;"></i>
                    <?= h($subtypeLabel) ?>
                </span>
                <?php endif; ?>
            </div>

            <!-- Patient name — main title -->
            <a href="<?= $href ?>"
               style="display:block;color:#f8fafc;font-size:22px;font-weight:900;line-height:1.2;
                      letter-spacing:-0.02em;margin-bottom:14px;word-break:break-word;text-decoration:none;">
                <?= h($v['patient_name']) ?>
            </a>

            <!-- MA / provider -->
            <?php if ($showMaName && !empty($v['ma_name'])): ?>
            <div style="display:flex;align-items:center;gap:8px;color:#94a3b8;font-size:14px;margin-bottom:8px;">
                <i class="bi bi-person-circle" style="color:#818cf8;font-size:16px;flex-shrink:0;"></i>
                <span style="font-weight:600;"><?= h($v['ma_name']) ?></span>
            </div>
            <?php elseif (!$showMaName && !empty($v['provider_name'])): ?>
            <div style="display:flex;align-items:center;gap:8px;color:#94a3b8;font-size:14px;margin-bottom:8px;">
                <i class="bi bi-person-badge" style="color:#94a3b8;font-size:16px;flex-shrink:0;"></i>
                <span style="font-weight:600;"><?= h($v['provider_name']) ?></span>
            </div>
            <?php endif; ?>

            <!-- Address -->
            <?php if ($v['patient_address']): ?>
            <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $addr ?>" target="_blank" rel="noopener"
               style="display:flex;align-items:flex-start;gap:8px;color:#94a3b8;font-size:16px;margin-bottom:8px;
                      text-decoration:none;transition:color 0.15s;"
               onmouseover="this.style.color='#60a5fa'" onmouseout="this.style.color='#94a3b8'">
                <i class="bi bi-geo-alt-fill" style="color:#64748b;font-size:16px;flex-shrink:0;margin-top:1px;"></i>
                <span style="word-break:break-word;line-height:1.4;"><?= h($v['patient_address']) ?></span>
            </a>
            <?php endif; ?>

            <!-- Phone -->
            <?php if ($v['patient_phone']): ?>
            <a href="tel:<?= h(preg_replace('/\D/','',$v['patient_phone'])) ?>"
               style="display:inline-flex;align-items:center;gap:8px;color:#94a3b8;font-size:16px;margin-bottom:8px;
                      text-decoration:none;transition:color 0.15s;"
               onmouseover="this.style.color='#60a5fa'" onmouseout="this.style.color='#94a3b8'">
                <i class="bi bi-telephone-fill" style="color:#64748b;font-size:14px;flex-shrink:0;"></i>
                <span><?= h($v['patient_phone']) ?></span>
            </a>
            <?php endif; ?>

            <!-- Scheduling notes -->
            <?php if ($v['notes']): ?>
            <div style="display:flex;align-items:flex-start;gap:8px;background:rgba(251,191,36,0.1);
                        border:1px solid rgba(251,191,36,0.25);border-radius:12px;padding:10px 12px;margin-top:4px;margin-bottom:4px;">
                <i class="bi bi-exclamation-triangle-fill" style="color:#fbbf24;font-size:13px;flex-shrink:0;margin-top:1px;"></i>
                <span style="color:#fde68a;font-size:13px;font-weight:600;line-height:1.4;word-break:break-word;"><?= h($v['notes']) ?></span>
            </div>
            <?php endif; ?>

            <!-- Missed visit reason -->
            <?php if ($v['status'] === 'missed' && !empty($missedReasons[(int)$v['patient_id']])): ?>
            <div style="display:flex;align-items:flex-start;gap:8px;background:rgba(239,68,68,0.1);
                        border:1px solid rgba(239,68,68,0.25);border-radius:12px;padding:10px 12px;margin-top:4px;margin-bottom:4px;">
                <i class="bi bi-calendar-x-fill" style="color:#f87171;font-size:13px;flex-shrink:0;margin-top:1px;"></i>
                <span style="color:#fca5a5;font-size:13px;font-weight:600;line-height:1.4;word-break:break-word;"><?= h($missedReasons[(int)$v['patient_id']]) ?></span>
            </div>
            <?php endif; ?>

            <!-- ── Primary action row ── -->
            <div style="display:flex;align-items:center;gap:10px;padding:14px 0 16px;" class="no-print">
                <?php if ($v['status'] === 'pending'): ?>
                <?php $visitJson = htmlspecialchars(json_encode(['id'=>$v['id'],'patient_id'=>$v['patient_id'],'visit_type'=>$v['visit_type'] ?? 'routine','visit_subtype'=>$v['visit_subtype'] ?? '']), ENT_QUOTES); ?>
                <button onclick="openMapPanel(<?= htmlspecialchars(json_encode($v['patient_address'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($v['patient_name']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($mapsUrl), ENT_QUOTES) ?>, <?= $visitJson ?>); if(window._pdSendLocation)window._pdSendLocation();"
                        style="flex:1;display:flex;align-items:center;justify-content:center;gap:8px;
                               padding:13px 20px;background:#2563eb;color:#fff;border:none;border-radius:50px;
                               font-size:15px;font-weight:800;cursor:pointer;transition:all 0.15s;letter-spacing:0.01em;
                               box-shadow:0 4px 16px rgba(37,99,235,0.45);"
                        onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
                    <i class="bi bi-compass-fill"></i> Navigate &nbsp;→
                </button>
                <?php elseif ($v['status'] === 'en_route'): ?>
                <a href="<?= BASE_URL . firstFormUrl($v['visit_type'] ?? 'routine', $v['patient_id'], $v['id'], $v['visit_subtype'] ?? '') ?>"
                   style="flex:1;display:flex;align-items:center;justify-content:center;gap:8px;
                          padding:13px 20px;background:#2563eb;color:#fff;border-radius:50px;
                          font-size:15px;font-weight:800;text-decoration:none;transition:background 0.15s;
                          box-shadow:0 4px 16px rgba(37,99,235,0.45);"
                   onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
                    <i class="bi bi-file-earmark-plus-fill"></i> Open Forms &nbsp;→
                </a>
                <button onclick="endVisit(<?= $v['id'] ?>, this)"
                        style="display:flex;align-items:center;gap:6px;padding:13px 16px;background:rgba(239,68,68,0.15);
                               color:#fca5a5;border:1px solid rgba(239,68,68,0.3);border-radius:50px;
                               font-size:14px;font-weight:700;cursor:pointer;transition:all 0.15s;flex-shrink:0;">
                    <i class="bi bi-stop-fill"></i> End
                </button>
                <?php elseif ($v['status'] === 'completed'): ?>
                <span style="flex:1;display:flex;align-items:center;justify-content:center;gap:8px;
                             padding:13px 20px;background:rgba(16,185,129,0.15);color:#6ee7b7;
                             border:1px solid rgba(16,185,129,0.3);border-radius:50px;font-size:15px;font-weight:800;">
                    <i class="bi bi-check-circle-fill"></i> Visit Complete
                </span>
                <?php if (isAdmin()): ?>
                <button onclick="undoEndVisit(<?= $v['id'] ?>, this)"
                        style="display:flex;align-items:center;gap:6px;padding:13px 14px;background:rgba(251,191,36,0.12);
                               color:#fde68a;border:1px solid rgba(251,191,36,0.3);border-radius:50px;
                               font-size:13px;font-weight:700;cursor:pointer;transition:all 0.15s;flex-shrink:0;">
                    <i class="bi bi-arrow-counterclockwise"></i> Undo
                </button>
                <?php endif; ?>
                <?php elseif ($v['status'] === 'missed'): ?>
                <span style="flex:1;display:flex;align-items:center;justify-content:center;gap:8px;
                             padding:13px 20px;background:rgba(239,68,68,0.10);color:#fca5a5;
                             border:1px solid rgba(239,68,68,0.2);border-radius:50px;font-size:15px;font-weight:800;">
                    <i class="bi bi-x-circle-fill"></i> Visit Missed
                </span>
                <?php if (isAdmin()): ?>
                <button onclick="updateStatus(<?= $v['id'] ?>, 'pending')"
                        style="display:flex;align-items:center;gap:6px;padding:13px 14px;background:rgba(148,163,184,0.12);
                               color:#cbd5e1;border:1px solid rgba(148,163,184,0.25);border-radius:50px;
                               font-size:13px;font-weight:700;cursor:pointer;transition:all 0.15s;flex-shrink:0;">
                    <i class="bi bi-arrow-counterclockwise"></i> Undo
                </button>
                <?php endif; ?>
                <?php endif; ?>

                <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $v['patient_id'] ?>"
                   style="width:44px;height:44px;display:flex;align-items:center;justify-content:center;
                          border-radius:50%;background:rgba(148,163,184,0.12);color:#94a3b8;
                          border:1px solid rgba(148,163,184,0.2);text-decoration:none;transition:all 0.15s;flex-shrink:0;" title="Patient chart">
                    <i class="bi bi-folder2-open" style="font-size:15px;"></i>
                </a>
            </div>
        </div>

        <!-- ── Status switcher (admin only) ── -->
        <?php if (isAdmin()): ?>
        <div style="background:#0f172a;border-top:1px solid rgba(255,255,255,0.07);padding:10px 16px;
                    display:flex;align-items:center;gap:8px;overflow-x:auto;" class="hide-scrollbar no-print">
            <?php
            $statusInlineDark = [
                'pending'   => ['active'=>'background:#334155;color:#e2e8f0;border:1.5px solid #64748b;font-weight:800;',
                                'inactive'=>'background:rgba(255,255,255,0.05);color:#475569;border:1px solid rgba(255,255,255,0.08);'],
                'en_route'  => ['active'=>'background:#1e3a8a;color:#93c5fd;border:1.5px solid #3b82f6;font-weight:800;',
                                'inactive'=>'background:rgba(255,255,255,0.05);color:#475569;border:1px solid rgba(255,255,255,0.08);'],
                'completed' => ['active'=>'background:#064e3b;color:#6ee7b7;border:1.5px solid #10b981;font-weight:800;',
                                'inactive'=>'background:rgba(255,255,255,0.05);color:#475569;border:1px solid rgba(255,255,255,0.08);'],
                'missed'    => ['active'=>'background:#7f1d1d;color:#fca5a5;border:1.5px solid #ef4444;font-weight:800;',
                                'inactive'=>'background:rgba(255,255,255,0.05);color:#475569;border:1px solid rgba(255,255,255,0.08);'],
            ];
            foreach ($statusDefs as $sKey => $sDef):
                $isCurrent = $v['status'] === $sKey;
                $btnS      = $statusInlineDark[$sKey][$isCurrent ? 'active' : 'inactive'];
            ?>
            <button onclick="updateStatus(<?= $v['id'] ?>, '<?= $sKey ?>')"
                    style="<?= $btnS ?>;display:inline-flex;align-items:center;gap:6px;padding:7px 13px;
                           border-radius:10px;font-size:12px;white-space:nowrap;cursor:pointer;
                           transition:all 0.15s;flex-shrink:0;">
                <i class="bi <?= $sDef['icon'] ?>" style="font-size:12px;"></i><?= $sDef['label'] ?>
            </button>
            <?php endforeach; ?>
        </div>
        <?php endif; // isAdmin ?>

        <!-- Quick Note Expansion -->
        <div style="background:#0f172a;border-top:1px solid rgba(255,255,255,0.07);" class="no-print">
            <button type="button" onclick="toggleNotes(this, <?= $v['id'] ?>)"
                    style="width:100%;display:flex;align-items:center;gap:8px;padding:11px 16px;
                           font-size:12px;font-weight:700;text-align:left;background:transparent;border:none;cursor:pointer;
                           color:<?= !empty($v['visit_notes']) ? '#fde68a' : '#475569' ?>;transition:color 0.15s;"
                    class="<?= !empty($v['visit_notes']) ? 'text-amber-400' : 'text-slate-500' ?>">
                <i class="bi bi-pencil-square text-[13px]"></i>
                <?php if (!empty($v['visit_notes'])): ?>
                    <span class="truncate flex-1 font-medium"><?= h(mb_strimwidth($v['visit_notes'], 0, 60, '...')) ?></span>
                    <span class="shrink-0 px-1.5 py-0.5 bg-amber-200 text-amber-800 rounded text-[9px] font-black uppercase tracking-wide">Saved</span>
                <?php else: ?>
                    <span class="flex-1 font-medium">Add clinical note...</span>
                <?php endif; ?>
                <i class="bi bi-chevron-down text-[10px] shrink-0 note-chevron transition-transform"></i>
            </button>
            <div class="note-panel hidden" style="padding:10px 16px 14px;background:#0f172a;">
                <textarea id="note-<?= $v['id'] ?>"
                    style="width:100%;padding:10px 12px;border:1px solid rgba(251,191,36,0.3);border-radius:12px;
                           font-size:13px;background:rgba(255,255,255,0.06);color:#e2e8f0;resize:none;
                           outline:none;transition:border-color 0.15s;box-sizing:border-box;"
                    rows="2" placeholder="Quick observation..."
                    onfocus="this.style.borderColor='rgba(251,191,36,0.6)'" onblur="this.style.borderColor='rgba(251,191,36,0.3)'"><?= h($v['visit_notes'] ?? '') ?></textarea>
                <div style="display:flex;align-items:center;justify-content:flex-end;gap:8px;margin-top:8px;">
                    <span class="note-saved-msg hidden" style="font-size:11px;color:#6ee7b7;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;">
                        <i class="bi bi-check-circle-fill"></i> Saved
                    </span>
                    <button type="button" onclick="saveNote(<?= $v['id'] ?>, this)"
                            style="padding:7px 16px;background:#d97706;color:#fff;font-size:12px;font-weight:700;
                                   border:none;border-radius:10px;cursor:pointer;transition:background 0.15s;"
                            onmouseover="this.style.background='#b45309'" onmouseout="this.style.background='#d97706'">
                        Save Note
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php
};
?>

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

<!-- Status summary bar (single compact row of 4 pills) -->
<?php
$_filterRings = ['pending'=>'#64748b','en_route'=>'#3b82f6','completed'=>'#10b981','missed'=>'#f87171'];
?>
<style>
.stat-filter-pill { cursor:pointer; transition:box-shadow 0.15s,transform 0.15s; user-select:none; }
.stat-filter-pill:hover { opacity:0.82; }
.stat-filter-pill.filter-active { transform:scale(1.05); }
</style>
<div class="flex items-center gap-2 mb-4 print-stat-bar">
    <?php
    $displayCounts = ($view === 'week') ? $weekCounts : $counts;
    foreach ($statusDefs as $key => $def): ?>
    <div class="flex-1 flex flex-col items-center justify-center gap-0.5 <?= $def['bg'] ?> border <?= $def['border'] ?> rounded-xl py-2 shadow-sm stat-filter-pill"
         data-filter="<?= $key ?>"
         data-ring="<?= $_filterRings[$key] ?>"
         onclick="filterByStatus('<?= $key ?>')">
        <div class="flex items-center gap-1">
            <i class="bi <?= $def['icon'] ?> <?= $def['text'] ?> text-[12px]"></i>
            <span class="text-[14px] font-extrabold <?= $def['text'] ?> leading-none"><?= $displayCounts[$key] ?></span>
        </div>
        <span class="text-[9px] font-semibold <?= $def['text'] ?> opacity-75 uppercase tracking-wide leading-none"><?= $def['label'] ?></span>
    </div>
    <?php endforeach; ?>
</div>
<script>
var _statusFilter = null;
function filterByStatus(status) {
    var pills = document.querySelectorAll('.stat-filter-pill');
    var cards = document.querySelectorAll('.print-visit-card');
    var sections = document.querySelectorAll('.ma-section');
    // Toggle off if clicking the already-active filter
    if (_statusFilter === status) {
        _statusFilter = null;
        pills.forEach(function(p) { p.classList.remove('filter-active'); p.style.boxShadow = ''; });
        cards.forEach(function(c) { c.style.display = ''; });
        sections.forEach(function(s) { s.style.display = ''; });
        return;
    }
    _statusFilter = status;
    pills.forEach(function(p) {
        var active = p.dataset.filter === status;
        p.classList.toggle('filter-active', active);
        p.style.boxShadow = active ? '0 0 0 2.5px ' + p.dataset.ring + ', 0 0 0 4px white' : '';
    });
    cards.forEach(function(c) { c.style.display = (c.dataset.status === status) ? '' : 'none'; });
    sections.forEach(function(s) {
        var hasVisible = Array.from(s.querySelectorAll('.print-visit-card')).some(function(c) { return c.style.display !== 'none'; });
        s.style.display = hasVisible ? '' : 'none';
    });
}
<?php if ($view === 'day'): ?>
document.addEventListener('DOMContentLoaded', function() { filterByStatus('pending'); });
<?php endif; ?>
</script>

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
<!-- ── Provider filter pills ── -->
<div class="flex flex-wrap items-center gap-2 mb-4 no-print">
    <span class="text-xs font-semibold text-slate-500 mr-1">Provider:</span>
    <button onclick="filterProvider('all')" id="pill-all"
            class="ma-pill px-3 py-1.5 rounded-full text-xs font-bold border transition-colors bg-teal-600 text-white border-teal-600">
        All Providers
    </button>
    <?php foreach ($allMaVisits as $pKey => $mg): ?>
    <button onclick="filterProvider('<?= htmlspecialchars(addslashes($pKey), ENT_QUOTES) ?>')" id="pill-<?= md5($pKey) ?>"
            class="ma-pill px-3 py-1.5 rounded-full text-xs font-bold border transition-colors bg-white text-slate-600 border-slate-200 hover:border-teal-400 hover:text-teal-600">
        <?= h($pKey) ?>
        <span class="ml-1 px-1.5 py-0.5 bg-slate-100 rounded-full text-[10px]"><?= count($mg['visits']) ?></span>
    </button>
    <?php endforeach; ?>
</div>
<script>
function filterProvider(key) {
    document.querySelectorAll('.ma-section').forEach(el => {
        el.style.display = (key === 'all' || el.dataset.provKey === key) ? '' : 'none';
    });
    document.querySelectorAll('.ma-pill').forEach(btn => {
        const active = (key === 'all' && btn.id === 'pill-all') || (btn.dataset.provKey === key);
        btn.className = btn.className.replace(/bg-teal-600 text-white border-teal-600|bg-white text-slate-600 border-slate-200/g, '');
        btn.classList.add(...(active
            ? ['bg-teal-600','text-white','border-teal-600']
            : ['bg-white','text-slate-600','border-slate-200']));
    });
}
document.querySelectorAll('.ma-pill[id^="pill-"]:not(#pill-all)').forEach(btn => {
    btn.dataset.provKey = btn.getAttribute('onclick').match(/filterProvider\('(.*?)'\)/)?.[1] || '';
});
</script>

<?php foreach ($allMaVisits as $pKey => $mg):
    $isUnassigned = $pKey === '— Unassigned —';
?>
<div class="ma-section mb-6" data-prov-key="<?= h($pKey) ?>">
    <!-- Provider header -->
    <div class="flex items-center gap-3 mb-3 px-1">
        <div class="w-8 h-8 <?= $isUnassigned ? 'bg-slate-200 text-slate-500' : 'bg-teal-100 text-teal-700' ?> font-extrabold text-xs rounded-lg grid place-items-center shrink-0">
            <?php if ($isUnassigned): ?><i class="bi bi-question-lg"></i><?php else: ?><i class="bi bi-person-badge-fill"></i><?php endif; ?>
        </div>
        <div class="flex-1">
            <p class="font-bold text-slate-800"><?= h($pKey) ?></p>
            <p class="text-xs text-slate-400">
                <?php foreach (['completed'=>'text-emerald-600','en_route'=>'text-blue-600','pending'=>'text-slate-500','missed'=>'text-red-500'] as $sk=>$sc): if ($mg['counts'][$sk]): ?>
                <span class="<?= $sc ?> font-semibold"><?= $mg['counts'][$sk] ?> <?= ucwords(str_replace('_',' ',$sk)) ?></span>
                <?php endif; endforeach; ?>
                <?php if (!empty($mg['mas'])): ?>
                &bull; <span class="text-slate-400">MAs: <?= h(implode(', ', $mg['mas'])) ?></span>
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="space-y-2">
    <?php foreach ($mg['visits'] as $idx => $v): $renderVisitCard($v, $idx, true); endforeach; ?>
    </div><!-- /space-y-2 -->
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
<div class="space-y-2" id="visitList">
    <?php foreach ($visits as $idx => $v): $renderVisitCard($v, $idx, false); endforeach; ?>
</div><!-- /visitList -->
<?php endif; // viewAll / empty / single-MA ?>
<?php endif; // end daily view ?>
</div><!-- /screen-layout -->

<!-- ═══════════════════════ EDIT VISIT MODAL ═══════════════════════ -->
<div id="editModalBackdrop" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4 no-print" onclick="closeEditModal()">
</div>
<div id="editModal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 pointer-events-none no-print">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg pointer-events-auto flex flex-col max-h-[90vh]" onclick="event.stopPropagation()">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200 bg-violet-600 rounded-t-2xl flex-shrink-0">
            <h3 id="editModalTitle" class="font-bold text-white text-base truncate pr-4"></h3>
            <button onclick="closeEditModal()" class="w-8 h-8 flex items-center justify-center rounded-lg text-white/70 hover:text-white hover:bg-white/10 transition">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <!-- Body (scrollable) -->
        <div id="editModalBody" class="px-6 py-5 space-y-5 overflow-y-auto">

            <!-- 1. Practice / Company -->
            <div>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <i class="bi bi-building-fill text-slate-400"></i> Practice
                </p>
                <div class="grid grid-cols-2 gap-2.5">
                    <label class="flex items-center gap-3 p-3 border-2 rounded-xl cursor-pointer transition-all
                                  has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50
                                  [&:not(:has(:checked))]:border-slate-200 [&:not(:has(:checked))]:bg-white">
                        <input type="radio" name="editCompany" value="Beyond Wound Care Inc."
                               class="w-4 h-4 text-blue-600 border-slate-300 flex-shrink-0">
                        <div class="leading-tight min-w-0">
                            <div class="font-semibold text-sm text-slate-800">Beyond Wound Care Inc.</div>
                            <div class="text-xs text-slate-500">BWC</div>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3 border-2 rounded-xl cursor-pointer transition-all
                                  has-[:checked]:border-teal-500 has-[:checked]:bg-teal-50
                                  [&:not(:has(:checked))]:border-slate-200 [&:not(:has(:checked))]:bg-white">
                        <input type="radio" name="editCompany" value="Visiting Medical Physician Inc."
                               class="w-4 h-4 text-teal-600 border-slate-300 flex-shrink-0">
                        <div class="leading-tight min-w-0">
                            <div class="font-semibold text-sm text-slate-800">Visiting Medical Physician Inc.</div>
                            <div class="text-xs text-slate-500">VMP</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- 2. Attending Provider -->
            <div class="p-4 bg-teal-50 border border-teal-200 rounded-2xl">
                <p class="text-xs font-bold text-teal-700 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <i class="bi bi-person-badge-fill text-teal-500"></i> Attending Provider
                </p>
                <select id="editProvider"
                        class="w-full px-3 py-2.5 border-2 border-teal-300 rounded-xl text-sm bg-white font-semibold
                               focus:outline-none focus:ring-2 focus:ring-teal-400 focus:border-teal-400 transition">
                    <option value="">— None —</option>
                    <?php foreach ($providerStaff as $ps): ?>
                    <option value="<?= h($ps['full_name']) ?>"><?= h($ps['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- 3. Visit Time + Order -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5"><i class="bi bi-clock mr-1 text-slate-400"></i>Visit Time</label>
                    <input type="time" id="editVisitTime"
                           class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1.5"><i class="bi bi-list-ol mr-1 text-slate-400"></i>Visit Order</label>
                    <input type="number" id="editOrder" min="1" max="99"
                           class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-transparent">
                </div>
            </div>

            <!-- 4. Visit Type pills -->
            <div>
                <p class="text-xs font-bold text-slate-500 uppercase tracking-widest mb-3 flex items-center gap-2">
                    <i class="bi bi-tag-fill text-slate-400"></i> Visit Type
                </p>
                <input type="hidden" id="editVtHidden" value="routine">
                <input type="hidden" id="editSubtypeHidden" value="wound_care">
                <div class="grid grid-cols-2 gap-2" id="editVtPills">
                    <button type="button" onclick="editSetVt('routine',this)" data-val="routine"
                            class="vt-pill-edit inline-flex flex-col items-center gap-1 px-2 py-3 rounded-xl text-xs font-semibold border transition-all
                                   bg-white text-slate-600 border-slate-200 hover:border-indigo-300 hover:text-indigo-600">
                        <i class="bi bi-activity text-sm"></i> Follow-Up
                    </button>
                    <button type="button" onclick="editSetVt('new_patient',this)" data-val="new_patient"
                            class="vt-pill-edit inline-flex flex-col items-center gap-1 px-2 py-3 rounded-xl text-xs font-semibold border transition-all
                                   bg-white text-slate-600 border-slate-200 hover:border-emerald-300 hover:text-emerald-600">
                        <i class="bi bi-person-plus-fill text-sm"></i> New Patient
                    </button>
                </div>
                <!-- New Patient subtype -->
                <div id="editSubtypeRow" class="hidden mt-3 p-3 bg-emerald-50 border border-emerald-200 rounded-xl">
                    <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-2">New Patient Type</label>
                    <div class="flex gap-2">
                        <button type="button" onclick="editSetSubtype('wound_care',this)" data-val="wound_care"
                                class="np-subtype-edit flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold border transition-all
                                       bg-white text-slate-600 border-slate-200 hover:border-emerald-300 hover:text-emerald-600">
                            <i class="bi bi-bandaid-fill"></i> Wound Care
                        </button>
                        <button type="button" onclick="editSetSubtype('primary_care',this)" data-val="primary_care"
                                class="np-subtype-edit flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold border transition-all
                                       bg-white text-slate-600 border-slate-200 hover:border-emerald-300 hover:text-emerald-600">
                            <i class="bi bi-heart-pulse-fill"></i> Primary Care
                        </button>
                    </div>
                </div>
            </div>

            <!-- 5. Schedule Notes -->
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1.5"><i class="bi bi-sticky mr-1 text-slate-400"></i>Schedule Notes <span class="text-slate-400 font-normal">(instructions / pre-visit)</span></label>
                <textarea id="editNotes" rows="2" placeholder="e.g. Bring blood pressure log, fasting required…"
                          class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-transparent resize-none"></textarea>
            </div>

            <?php if (isAdmin()): ?>
            <!-- Admin: Date + MA -->
            <div class="grid grid-cols-2 gap-4 p-4 bg-violet-50 border border-violet-200 rounded-xl">
                <div id="editDateRow">
                    <label class="block text-xs font-semibold text-violet-700 mb-1.5"><i class="bi bi-calendar3 mr-1"></i>Visit Date <span class="text-violet-500 font-normal">(Admin)</span></label>
                    <input type="date" id="editVisitDate"
                           class="w-full px-3 py-2 border border-violet-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-transparent bg-white">
                </div>
                <div id="editMaRow">
                    <label class="block text-xs font-semibold text-violet-700 mb-1.5"><i class="bi bi-person-fill mr-1"></i>Assigned MA <span class="text-violet-500 font-normal">(Admin)</span></label>
                    <select id="editMaId" class="w-full px-3 py-2 border border-violet-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-transparent bg-white">
                        <?php foreach ($allMas as $m): ?>
                        <option value="<?= $m['id'] ?>"><?= h($m['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <!-- Footer -->
        <div class="px-6 py-4 border-t border-slate-100 flex items-center justify-end gap-3 bg-slate-50 rounded-b-2xl flex-shrink-0">
            <button onclick="closeEditModal()" class="px-4 py-2 text-sm font-semibold text-slate-600 hover:text-slate-800 transition">Cancel</button>
            <button id="editSaveBtn" onclick="saveEdit()"
                    class="flex items-center gap-2 px-5 py-2 bg-violet-600 hover:bg-violet-700 active:scale-95 text-white text-sm font-bold rounded-xl shadow-sm transition-all">
                <i class="bi bi-floppy-fill"></i> Save Changes
            </button>
        </div>
    </div>
</div>

<!-- ═══════════════════════ ROUTE MAP MODAL ══════════════════════ -->
<?php if ($view === 'day' && count($routeAddresses) >= 1):
    // Build Google Maps URL: 2+ stops use dir/; single stop uses search
    if (count($routeAddresses) === 1) {
        $routeMapUrl = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($routeAddresses[0]);
    } else {
        $routeMapUrl = 'https://www.google.com/maps/dir/' . implode('/', array_map('rawurlencode', $routeAddresses));
    }
?>
<div id="routeMapModal" class="hidden no-print" onclick="if(event.target===this)closeRouteMapModal()"
     style="position:fixed;inset:0;z-index:10000;background:rgba(15,23,42,.6);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;padding:1rem">
    <div style="background:#fff;border-radius:20px;max-width:460px;width:100%;box-shadow:0 24px 60px rgba(0,0,0,.25);overflow:hidden">
        <!-- Header -->
        <div style="background:linear-gradient(135deg,#059669,#10b981);padding:20px 24px 16px;color:#fff">
            <div style="display:flex;align-items:center;justify-content:space-between">
                <div style="display:flex;align-items:center;gap:10px">
                    <div style="width:40px;height:40px;background:rgba(255,255,255,.2);border-radius:12px;display:flex;align-items:center;justify-content:center">
                        <i class="bi bi-map-fill" style="font-size:18px"></i>
                    </div>
                    <div>
                        <div style="font-size:16px;font-weight:800">Today's Route</div>
                        <div style="font-size:12px;opacity:.85"><?= date('l, F j, Y', strtotime($date)) ?></div>
                    </div>
                </div>
                <button onclick="closeRouteMapModal()" style="background:rgba(255,255,255,.2);border:none;color:#fff;width:32px;height:32px;border-radius:8px;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        <!-- Stop list -->
        <div style="padding:16px 24px;max-height:320px;overflow-y:auto">
            <p style="font-size:12px;color:#64748b;margin:0 0 12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px">
                <?= count($routeAddresses) ?> Stop<?= count($routeAddresses) !== 1 ? 's' : '' ?> &nbsp;&bull;&nbsp; in visit order
            </p>
            <?php foreach ($routeAddresses as $stopIdx => $stopAddr): ?>
            <div style="display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid #f1f5f9">
                <div style="width:24px;height:24px;background:<?= $stopIdx === 0 ? '#dcfce7' : ($stopIdx === count($routeAddresses)-1 ? '#fee2e2' : '#eff6ff') ?>;color:<?= $stopIdx === 0 ? '#166534' : ($stopIdx === count($routeAddresses)-1 ? '#991b1b' : '#1e40af') ?>;border-radius:50%;font-size:11px;font-weight:800;display:flex;align-items:center;justify-content:center;shrink:0;flex-shrink:0">
                    <?php if ($stopIdx === 0): ?><i class="bi bi-geo-alt-fill" style="font-size:11px"></i>
                    <?php elseif ($stopIdx === count($routeAddresses)-1): ?><i class="bi bi-flag-fill" style="font-size:10px"></i>
                    <?php else: ?><?= $stopIdx + 1 ?><?php endif; ?>
                </div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:13px;color:#1e293b;line-height:1.4"><?= h($stopAddr) ?></div>
                    <?php
                    // Find patients at this address
                    $patsHere = array_filter($visits, fn($v) => trim($v['patient_address'] ?? '') === $stopAddr);
                    foreach ($patsHere as $pv): ?>
                    <div style="font-size:11px;color:#64748b;margin-top:1px">
                        <?= h($pv['patient_name']) ?>
                        <?php if ($pv['visit_time']): ?>&nbsp;<span style="color:#94a3b8"><?= date('g:i A', strtotime($pv['visit_time'])) ?></span><?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <!-- Actions -->
        <div style="padding:16px 24px;border-top:1px solid #f1f5f9;display:flex;gap:10px">
            <a href="<?= h($routeMapUrl) ?>" target="_blank" rel="noopener noreferrer"
               style="flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:12px;background:#059669;color:#fff;border:none;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;text-decoration:none;transition:background .15s"
               onmouseover="this.style.background='#047857'" onmouseout="this.style.background='#059669'">
                <i class="bi bi-map-fill"></i> Open in Google Maps
            </a>
            <button onclick="closeRouteMapModal()"
                    style="padding:12px 18px;background:#f1f5f9;color:#475569;border:none;border-radius:12px;font-size:14px;font-weight:600;cursor:pointer">
                Close
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

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
            $pVtLabels = ['routine'=>'Follow-Up','new_patient'=>'New Patient','wound_care'=>'Wound Care','awv'=>'Annual Wellness','ccm'=>'CCM','il'=>'IL'];
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
        $pVtLabels2 = ['routine'=>'Follow-Up','new_patient'=>'New Patient','wound_care'=>'Wound Care','awv'=>'Annual Wellness Visit','ccm'=>'CCM','il'=>'IL Disclosure'];
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
                    <?= h($pVtLabels2[$vt2] ?? 'Follow-Up') ?>
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

// ── Route Map Modal ───────────────────────────────────────────────────────────
function openRouteMapModal() {
    const m = document.getElementById('routeMapModal');
    if (!m) return;
    m.style.display = 'flex';
}
function closeRouteMapModal() {
    const m = document.getElementById('routeMapModal');
    if (m) m.style.display = 'none';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeRouteMapModal(); });

// ── One-tap Start Visit ───────────────────────────────────────────────────────
async function startVisit(visitId, patientId, visitType, visitSubtype, btn) {
    const ok = await pdConfirm({
        message:      'Start this visit?',
        subtext:      'Time-in will be recorded now and cannot be undone. You can navigate freely — a floating chip will keep you connected to the visit.',
        confirmLabel: 'Start Visit',
        confirmIcon:  'bi bi-play-fill',
        confirmStyle: 'background:linear-gradient(135deg,#2563eb,#0ea5e9);',
        iconBg:       '#eff6ff',
        iconColor:    '#2563eb',
    });
    if (!ok) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split text-sm animate-spin"></i> Starting…';

    // Capture exact click time once and derive time_in in the app's configured timezone
    const _clickedAt = new Date();
    const _appTz = window._pdTimezone || 'America/Chicago';
    const _tiParts = new Intl.DateTimeFormat('en-US', { timeZone: _appTz, hour: '2-digit', minute: '2-digit', hour12: false }).formatToParts(_clickedAt);
    const _ti = _tiParts.find(p => p.type === 'hour').value.padStart(2,'0') + ':' + _tiParts.find(p => p.type === 'minute').value.padStart(2,'0');

    const vType = visitType.toLowerCase();
    const npType = (visitSubtype === 'primary_care') ? 'primary_care' : 'wound_care';
    
    let formPath = '/forms/vital_cs.php'; // default fallback for 'routine'
    let npParam = '';

    if (vType.includes('new')) {
        formPath = '/forms/new_patient_pocket.php';
        npParam = '&np_type=' + npType;
    } else if (vType === 'wound_care') {
        formPath = '/forms/wound_care.php';
    } else if (vType === 'awv' || vType === 'medicare_awv') {
        formPath = '/forms/medicare_awv.php';
    } else if (vType === 'ccm') {
        formPath = '/forms/ccm_consent.php';
    } else if (vType === 'il' || vType === 'il_disclosure') {
        formPath = '/forms/il_disclosure.php';
    } else if (vType === 'cognitive_wellness') {
        formPath = '/forms/cognitive_wellness.php';
    }

    fetch(BASE + '/api/schedule_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: CSRF, id: visitId, action: 'status', status: 'en_route', started_at: _clickedAt.toISOString() })
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            if (window.closeMapPanel) window.closeMapPanel();
            window.location.href = BASE + formPath + '?patient_id=' + patientId + '&visit_id=' + visitId + '&sched_visit_type=' + encodeURIComponent(visitType) + npParam + '&time_in=' + _ti;
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-play-fill text-sm"></i> Start Visit &nbsp;→';
            pdToast(data.error || 'Could not start visit.', 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-play-fill text-sm"></i> Start Visit &nbsp;→';
        pdToast('Network error. Please try again.', 'error');
    });
}

// ── End Visit ────────────────────────────────────────────────────────────────
async function endVisit(visitId, btn) {
    const ok = await pdConfirm({
        message: 'Mark this visit as completed?',
        confirmLabel: 'Complete Visit',
        confirmIcon:  'bi bi-check-circle-fill',
        confirmStyle: 'background:linear-gradient(135deg,#059669,#10b981);',
        iconBg:       '#f0fdf4',
        iconColor:    '#059669',
    });
    if (!ok) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split text-sm animate-spin"></i> Ending…';

    fetch(BASE + '/api/schedule_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: CSRF, id: visitId, action: 'status', status: 'completed' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            location.reload();
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-stop-fill"></i> End';
            pdToast(data.error || 'Could not end visit.', 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-stop-fill"></i> End';
        pdToast('Network error. Please try again.', 'error');
    });
}

// ── Reset Visit ───────────────────────────────────────────────────────────────
async function resetVisit(visitId, btn) {
    const ok = await pdConfirm({
        message: 'Reset this visit to Pending?',
        subtext: 'This will clear the start time.',
        confirmLabel: 'Reset',
        confirmIcon:  'bi bi-arrow-counterclockwise',
        confirmStyle: 'background:#d97706;',
    });
    if (!ok) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split text-sm animate-spin"></i>';
    fetch(BASE + '/api/schedule_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: CSRF, id: visitId, action: 'reset_visit' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            location.reload();
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-counterclockwise text-sm"></i> Reset';
            pdToast(data.error || 'Could not reset visit.', 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-counterclockwise text-sm"></i> Reset';
        pdToast('Network error. Please try again.', 'error');
    });
}

// ── Undo End Visit ────────────────────────────────────────────────────────────
async function undoEndVisit(visitId, btn) {
    const ok = await pdConfirm({
        message: 'Undo End Visit?',
        subtext: 'This will set the visit back to In Progress.',
        confirmLabel: 'Undo',
        confirmIcon:  'bi bi-arrow-counterclockwise',
        confirmStyle: 'background:#d97706;',
    });
    if (!ok) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split text-sm animate-spin"></i>';
    fetch(BASE + '/api/schedule_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: CSRF, id: visitId, action: 'undo_end' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            location.reload();
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Undo';
            pdToast(data.error || 'Could not undo end visit.', 'error');
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-arrow-counterclockwise"></i> Undo';
        pdToast('Network error. Please try again.', 'error');
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
        } else { pdToast(d.error || 'Could not save note.', 'error'); }
    } catch { pdToast('Network error.', 'error'); }
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
            pdToast(data.error || 'Could not update status.', 'error');
        }
    })
    .catch(() => pdToast('Network error. Please try again.', 'error'));
}

// ── Edit Modal ────────────────────────────────────────────────────────────────
let _editVisitId = null;
let _editLoading  = false;

const _editVtColors = {
    routine:     'indigo',
    new_patient: 'emerald',
    wound_care:  'rose',
    awv:         'violet',
    ccm:         'blue',
    il:          'amber',
};

function editSetVt(val, btn) {
    document.getElementById('editVtHidden').value = val;
    document.querySelectorAll('#editVtPills .vt-pill-edit').forEach(p => {
        const c = _editVtColors[p.dataset.val] || 'indigo';
        if (p.dataset.val === val) {
            p.className = p.className.replace(/bg-\S+ text-\S+ border-\S+( shadow-sm)?/g, '').trim();
            p.classList.add('bg-'+c+'-600', 'text-white', 'border-'+c+'-600', 'shadow-sm');
        } else {
            p.className = p.className.replace(/bg-\S+-600 text-white border-\S+-600 shadow-sm/g, '').trim();
            p.classList.add('bg-white', 'text-slate-600', 'border-slate-200');
        }
    });
    document.getElementById('editSubtypeRow').classList.toggle('hidden', val !== 'new_patient');
}

function editSetSubtype(val, btn) {
    document.getElementById('editSubtypeHidden').value = val;
    document.querySelectorAll('#editSubtypeRow .np-subtype-edit').forEach(p => {
        if (p.dataset.val === val) {
            p.className = p.className.replace(/bg-\S+ text-\S+ border-\S+( shadow-sm)?/g, '').trim();
            p.classList.add('bg-emerald-600', 'text-white', 'border-emerald-600', 'shadow-sm');
        } else {
            p.className = p.className.replace(/bg-emerald-600 text-white border-emerald-600 shadow-sm/g, '').trim();
            p.classList.add('bg-white', 'text-slate-600', 'border-slate-200');
        }
    });
}

function _populateEditModal(visit) {
    document.getElementById('editVisitTime').value = visit.visit_time ? visit.visit_time.substring(0,5) : '';
    document.getElementById('editNotes').value     = visit.notes || '';
    document.getElementById('editProvider').value  = visit.provider_name || '';
    document.getElementById('editOrder').value     = visit.visit_order || 1;
    // Company radio
    const company = visit.company || 'Beyond Wound Care Inc.';
    document.querySelectorAll('input[name="editCompany"]').forEach(r => { r.checked = r.value === company; });
    // Visit type pills + subtype
    editSetVt(visit.visit_type || 'routine', null);
    editSetSubtype(visit.visit_subtype || 'wound_care', null);
    // Admin-only fields
    const dateEl = document.getElementById('editVisitDate');
    const maEl   = document.getElementById('editMaId');
    if (dateEl) dateEl.value = visit.visit_date || '';
    if (maEl)   maEl.value   = visit.ma_id || '';
}

async function openEditModal(visitId, patientName) {
    _editVisitId = visitId;
    document.getElementById('editModalTitle').textContent = 'Edit Visit — ' + patientName;
    // Show modal with loading state
    const body = document.getElementById('editModalBody');
    body.innerHTML = '<div class="flex items-center justify-center py-12 text-slate-400"><i class="bi bi-hourglass-split animate-spin text-2xl mr-3"></i><span class="text-sm">Loading visit data…</span></div>';
    document.getElementById('editSaveBtn').disabled = true;
    document.getElementById('editModal').classList.remove('hidden');
    document.getElementById('editModalBackdrop').classList.remove('hidden');

    try {
        const r = await fetch(BASE + '/api/schedule_update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ csrf: CSRF, action: 'get', id: visitId })
        });
        const d = await r.json();
        if (!d.ok) { pdToast(d.error || 'Could not load visit.', 'error'); closeEditModal(); return; }
        // Restore body and populate
        body.innerHTML = _editModalBodyHTML;
        document.getElementById('editSaveBtn').disabled = false;
        _populateEditModal(d.visit);
        document.getElementById('editVisitTime').focus();
    } catch {
        pdToast('Network error. Please try again.', 'error');
        closeEditModal();
    }
}

function closeEditModal() {
    document.getElementById('editModal').classList.add('hidden');
    document.getElementById('editModalBackdrop').classList.add('hidden');
    _editVisitId = null;
}

async function saveEdit() {
    if (!_editVisitId) return;
    const btn = document.getElementById('editSaveBtn');
    const orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split animate-spin mr-1"></i> Saving…';

    const payload = {
        csrf:          CSRF,
        action:        'edit',
        id:            _editVisitId,
        visit_time:    document.getElementById('editVisitTime').value,
        visit_type:    document.getElementById('editVtHidden').value,
        visit_subtype: document.getElementById('editSubtypeHidden').value,
        notes:         document.getElementById('editNotes').value,
        provider_name: document.getElementById('editProvider').value,
        visit_order:   parseInt(document.getElementById('editOrder').value) || 1,
        company:       document.querySelector('input[name="editCompany"]:checked')?.value || 'Beyond Wound Care Inc.',
    };
    const dateEl = document.getElementById('editVisitDate');
    const maEl   = document.getElementById('editMaId');
    if (dateEl) payload.visit_date = dateEl.value;
    if (maEl)   payload.ma_id      = parseInt(maEl.value) || 0;

    try {
        const r = await fetch(BASE + '/api/schedule_update.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const d = await r.json();
        if (d.ok) {
            closeEditModal();
            location.reload();
        } else {
            pdToast(d.error || 'Could not save changes.', 'error');
        }
    } catch { pdToast('Network error. Please try again.', 'error'); }
    btn.disabled = false;
    btn.innerHTML = orig;
}

// Snapshot the body template once DOM is ready so we can restore it after loading
let _editModalBodyHTML = '';
document.addEventListener('DOMContentLoaded', () => {
    _editModalBodyHTML = document.getElementById('editModalBody').innerHTML;
});

// Close on Escape key
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeEditModal(); });

</script>

<?php include __DIR__ . '/includes/map_panel.php'; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>

