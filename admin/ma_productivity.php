<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireAdmin();

$pageTitle = 'MA Productivity Report';
$activeNav = 'ma_report';

// ─── Date range ───────────────────────────────────────────────────────────────
$period = $_GET['period'] ?? 'month';
$today  = date('Y-m-d');

switch ($period) {
    case 'today':
        $from = $today; $to = $today; break;
    case 'week':
        $from = date('Y-m-d', strtotime('monday this week'));
        $to   = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'last7':
        $from = date('Y-m-d', strtotime('-6 days'));
        $to   = $today; break;
    case 'last30':
        $from = date('Y-m-d', strtotime('-29 days'));
        $to   = $today; break;
    case 'lastmonth':
        $from = date('Y-m-01', strtotime('first day of last month'));
        $to   = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'custom':
        $from = $_GET['from'] ?? date('Y-m-01');
        $to   = $_GET['to']   ?? $today;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = $today;
        if ($to < $from) [$from, $to] = [$to, $from];
        break;
    default:
        $period = 'month';
        $from = date('Y-m-01');
        $to   = $today;
}

// ─── Visit stats per active MA ────────────────────────────────────────────────
$visitStmt = $pdo->prepare("
    SELECT
        s.id                                       AS ma_id,
        s.full_name                                AS ma_name,
        COUNT(sc.id)                               AS total_visits,
        COALESCE(SUM(sc.status = 'completed'),0)   AS completed,
        COALESCE(SUM(sc.status = 'missed'),0)      AS missed,
        COALESCE(SUM(sc.status = 'pending'),0)     AS pending_visits,
        COALESCE(SUM(sc.status = 'en_route'),0)    AS en_route,
        COUNT(DISTINCT sc.patient_id)              AS unique_patients,
        COUNT(DISTINCT sc.visit_date)              AS active_days
    FROM staff s
    LEFT JOIN schedule sc
           ON sc.ma_id = s.id
          AND sc.visit_date BETWEEN ? AND ?
    WHERE s.role = 'ma' AND s.active = 1
    GROUP BY s.id, s.full_name
    ORDER BY completed DESC, total_visits DESC
");
$visitStmt->execute([$from, $to]);
$maStats = $visitStmt->fetchAll();

// ─── Form stats per MA ─────────────────────────────────────────────────────────
$formStmt = $pdo->prepare("
    SELECT
        ma_id,
        COUNT(*)                                   AS total_forms,
        COALESCE(SUM(status IN ('signed','uploaded')),0) AS signed_forms,
        COALESCE(SUM(status = 'uploaded'),0)       AS uploaded_forms,
        COUNT(DISTINCT patient_id)                 AS patients_with_forms
    FROM form_submissions
    WHERE DATE(created_at) BETWEEN ? AND ?
      AND ma_id IS NOT NULL
    GROUP BY ma_id
");
$formStmt->execute([$from, $to]);
$formStats = [];
foreach ($formStmt->fetchAll() as $r) $formStats[$r['ma_id']] = $r;

// ─── Visit-type breakdown per MA (all statuses) ───────────────────────────────
$typeStmt = $pdo->prepare("
    SELECT ma_id, visit_type, COUNT(*) AS cnt
    FROM schedule
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY ma_id, visit_type
");
$typeStmt->execute([$from, $to]);
$typeStats = [];
foreach ($typeStmt->fetchAll() as $r) {
    $typeStats[$r['ma_id']][$r['visit_type']] = (int)$r['cnt'];
}

// ─── Day-by-day completed visits per MA (for sparkline) ──────────────────────
$dailyStmt = $pdo->prepare("
    SELECT ma_id, visit_date,
           COALESCE(SUM(status='completed'),0) AS completed,
           COUNT(*) AS total
    FROM schedule
    WHERE visit_date BETWEEN ? AND ?
    GROUP BY ma_id, visit_date
    ORDER BY ma_id, visit_date
");
$dailyStmt->execute([$from, $to]);
$dailyStats = [];
foreach ($dailyStmt->fetchAll() as $r) {
    $dailyStats[$r['ma_id']][$r['visit_date']] = $r;
}

// ─── Aggregate totals across all MAs ─────────────────────────────────────────
$totals = ['total' => 0, 'completed' => 0, 'missed' => 0, 'forms' => 0, 'patients' => 0];
foreach ($maStats as $ma) {
    $totals['total']     += (int)$ma['total_visits'];
    $totals['completed'] += (int)$ma['completed'];
    $totals['missed']    += (int)$ma['missed'];
    $totals['forms']     += (int)($formStats[$ma['ma_id']]['signed_forms'] ?? 0);
    $totals['patients']  += (int)$ma['unique_patients'];
}
$totals['rate'] = $totals['total'] > 0 ? round($totals['completed'] / $totals['total'] * 100) : 0;

// ─── Drill-down: individual visits for a selected MA ─────────────────────────
$drillMaId   = isset($_GET['ma_id']) ? (int)$_GET['ma_id'] : 0;
$drillMaName = '';
$drillVisits = [];
if ($drillMaId) {
    foreach ($maStats as $ma) {
        if ((int)$ma['ma_id'] === $drillMaId) { $drillMaName = $ma['ma_name']; break; }
    }
    $dvStmt = $pdo->prepare("
        SELECT sc.id, sc.visit_date, sc.visit_time, sc.status, sc.visit_type,
               sc.notes, sc.visit_notes,
               CONCAT(p.first_name,' ',p.last_name) AS patient_name,
               p.address                             AS patient_address
        FROM schedule sc
        JOIN patients p ON p.id = sc.patient_id
        WHERE sc.ma_id = ? AND sc.visit_date BETWEEN ? AND ?
        ORDER BY sc.visit_date ASC, sc.visit_order ASC
    ");
    $dvStmt->execute([$drillMaId, $from, $to]);
    $drillVisits = $dvStmt->fetchAll();
}

// ─── Chart JSON ───────────────────────────────────────────────────────────────
$chartLabels   = [];
$chartComplete = [];
$chartMissed   = [];
$chartPending  = [];
foreach ($maStats as $ma) {
    // Short name for chart labels
    $nameParts = explode(' ', $ma['ma_name']);
    $chartLabels[]   = $nameParts[0] . (isset($nameParts[1]) ? ' ' . mb_substr($nameParts[1], 0, 1) . '.' : '');
    $chartComplete[] = (int)$ma['completed'];
    $chartMissed[]   = (int)$ma['missed'];
    $chartPending[]  = (int)$ma['pending_visits'] + (int)$ma['en_route'];
}
$chartJson = json_encode([
    'labels'    => $chartLabels,
    'completed' => $chartComplete,
    'missed'    => $chartMissed,
    'pending'   => $chartPending,
]);

// ─── Constants ────────────────────────────────────────────────────────────────
$VT_LABELS = [
    'routine'     => 'Routine',
    'new_patient' => 'New Patient',
    'wound_care'  => 'Wound Care',
    'awv'         => 'AWV',
    'ccm'         => 'CCM',
    'il'          => 'IL Disc.',
];
$VT_COLORS = [
    'routine'     => 'bg-slate-100 text-slate-600',
    'new_patient' => 'bg-teal-100 text-teal-700',
    'wound_care'  => 'bg-rose-100 text-rose-700',
    'awv'         => 'bg-amber-100 text-amber-700',
    'ccm'         => 'bg-blue-100 text-blue-700',
    'il'          => 'bg-violet-100 text-violet-700',
];
$STATUS_DEF = [
    'pending'   => ['bg-slate-100',   'text-slate-600',   'bi-clock',              'Pending'],
    'en_route'  => ['bg-blue-100',    'text-blue-700',    'bi-car-front-fill',     'En Route'],
    'completed' => ['bg-emerald-100', 'text-emerald-700', 'bi-check-circle-fill',  'Completed'],
    'missed'    => ['bg-red-100',     'text-red-700',     'bi-x-circle-fill',      'Missed'],
];
$PERIOD_LABELS = [
    'today'     => 'Today',
    'week'      => 'This Week',
    'month'     => 'This Month',
    'last7'     => 'Last 7 Days',
    'last30'    => 'Last 30 Days',
    'lastmonth' => 'Last Month',
    'custom'    => 'Custom Range',
];

// Build base URL for period links
$baseUrl = BASE_URL . '/admin/ma_productivity.php';
$fromLabel = date('M j, Y', strtotime($from));
$toLabel   = date('M j, Y', strtotime($to));
$rangeLabel = ($from === $to) ? $fromLabel : "$fromLabel – $toLabel";

// Helper: completion rate badge class
function rateClass(int $rate): string {
    if ($rate >= 90) return 'bg-emerald-100 text-emerald-700';
    if ($rate >= 70) return 'bg-amber-100 text-amber-700';
    if ($rate >= 50) return 'bg-orange-100 text-orange-700';
    return 'bg-red-100 text-red-700';
}

include __DIR__ . '/../includes/header.php';
?>

<!-- ── Page header ──────────────────────────────────────────────────────────── -->
<div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-extrabold text-slate-800">
            <i class="bi bi-graph-up-arrow text-indigo-500 mr-1"></i> MA Productivity Report
        </h2>
        <p class="text-slate-500 text-sm mt-0.5">
            <?= h($PERIOD_LABELS[$period] ?? 'Custom') ?> &mdash;
            <span class="font-semibold text-slate-700"><?= h($rangeLabel) ?></span>
        </p>
    </div>
    <div class="flex items-center gap-2 shrink-0">
        <a href="<?= $baseUrl ?>?period=<?= $period ?>&from=<?= $from ?>&to=<?= $to ?>"
           onclick="window.print();return false;"
           class="flex items-center gap-1.5 px-4 py-2.5 bg-white border border-slate-200 text-slate-600 rounded-xl text-sm font-semibold hover:bg-slate-50 transition-colors no-print">
            <i class="bi bi-printer text-slate-400"></i> Print
        </a>
        <a href="<?= BASE_URL ?>/admin/schedule_manage.php"
           class="flex items-center gap-1.5 px-4 py-2.5 bg-white border border-slate-200 text-slate-700 rounded-xl text-sm font-semibold hover:bg-slate-50 transition-colors no-print">
            <i class="bi bi-calendar-week-fill text-indigo-400"></i> Manage Schedule
        </a>
    </div>
</div>

<!-- ── Period filter bar ─────────────────────────────────────────────────────── -->
<div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-4 mb-5 no-print">
    <div class="flex flex-wrap items-center gap-2 mb-3">
        <?php foreach (['today'=>'Today','week'=>'This Week','month'=>'This Month','last7'=>'Last 7d','last30'=>'Last 30d','lastmonth'=>'Last Month'] as $pk => $pl): ?>
        <a href="<?= $baseUrl ?>?period=<?= $pk ?>"
           class="px-4 py-2 rounded-xl text-sm font-semibold transition-colors
                  <?= $period === $pk ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
            <?= $pl ?>
        </a>
        <?php endforeach; ?>
        <span class="text-slate-300 hidden sm:inline">|</span>
        <button type="button" onclick="document.getElementById('customRange').classList.toggle('hidden')"
                class="px-4 py-2 rounded-xl text-sm font-semibold transition-colors
                       <?= $period === 'custom' ? 'bg-indigo-600 text-white shadow-sm' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' ?>">
            <i class="bi bi-calendar-range mr-1"></i>Custom
        </button>
    </div>
    <form id="customRange" method="GET" action="<?= $baseUrl ?>"
          class="<?= $period === 'custom' ? '' : 'hidden' ?> flex flex-wrap items-end gap-3">
        <input type="hidden" name="period" value="custom">
        <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1">From</label>
            <input type="date" name="from" value="<?= h($from) ?>"
                   class="px-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-400">
        </div>
        <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1">To</label>
            <input type="date" name="to" value="<?= h($to) ?>"
                   class="px-3 py-2 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-indigo-400">
        </div>
        <?php if ($drillMaId): ?>
        <input type="hidden" name="ma_id" value="<?= $drillMaId ?>">
        <?php endif; ?>
        <button type="submit"
                class="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-xl text-sm transition-colors">
            <i class="bi bi-funnel-fill"></i> Apply
        </button>
    </form>
</div>

<!-- ── Summary stat chips ────────────────────────────────────────────────────── -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-5">

    <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-5">
        <div class="flex items-center gap-3 mb-1">
            <div class="w-9 h-9 bg-indigo-50 rounded-xl grid place-items-center shrink-0">
                <i class="bi bi-calendar-check-fill text-indigo-500 text-base"></i>
            </div>
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Total Visits</span>
        </div>
        <div class="text-3xl font-extrabold text-slate-800"><?= number_format($totals['total']) ?></div>
    </div>

    <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-5">
        <div class="flex items-center gap-3 mb-1">
            <div class="w-9 h-9 bg-emerald-50 rounded-xl grid place-items-center shrink-0">
                <i class="bi bi-check-circle-fill text-emerald-500 text-base"></i>
            </div>
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Completed</span>
        </div>
        <div class="text-3xl font-extrabold text-slate-800"><?= number_format($totals['completed']) ?></div>
        <div class="text-xs text-slate-400 mt-0.5">
            <?php if ($totals['missed'] > 0): ?>
            <span class="text-red-500 font-semibold"><?= $totals['missed'] ?> missed</span>
            <?php else: ?>
            0 missed
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-5">
        <div class="flex items-center gap-3 mb-1">
            <div class="w-9 h-9 bg-amber-50 rounded-xl grid place-items-center shrink-0">
                <i class="bi bi-percent text-amber-500 text-base"></i>
            </div>
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Completion Rate</span>
        </div>
        <div class="text-3xl font-extrabold <?= $totals['rate'] >= 80 ? 'text-emerald-600' : ($totals['rate'] >= 60 ? 'text-amber-600' : 'text-red-600') ?>">
            <?= $totals['rate'] ?>%
        </div>
        <div class="w-full bg-slate-100 rounded-full h-1.5 mt-2">
            <div class="<?= $totals['rate'] >= 80 ? 'bg-emerald-500' : ($totals['rate'] >= 60 ? 'bg-amber-500' : 'bg-red-500') ?> h-1.5 rounded-full transition-all"
                 style="width:<?= min(100,$totals['rate']) ?>%"></div>
        </div>
    </div>

    <div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-5">
        <div class="flex items-center gap-3 mb-1">
            <div class="w-9 h-9 bg-violet-50 rounded-xl grid place-items-center shrink-0">
                <i class="bi bi-file-earmark-check-fill text-violet-500 text-base"></i>
            </div>
            <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Forms Signed</span>
        </div>
        <div class="text-3xl font-extrabold text-slate-800"><?= number_format($totals['forms']) ?></div>
        <div class="text-xs text-slate-400 mt-0.5">
            <?= number_format($totals['patients']) ?> unique patient<?= $totals['patients'] !== 1 ? 's' : '' ?> seen
        </div>
    </div>

</div>

<!-- ── Chart ─────────────────────────────────────────────────────────────────── -->
<?php if (!empty($maStats) && $totals['total'] > 0): ?>
<div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-6 mb-5">
    <h3 class="font-bold text-slate-700 mb-4 flex items-center gap-2">
        <i class="bi bi-bar-chart-fill text-indigo-400"></i> Visit Breakdown by MA
    </h3>
    <div style="position:relative;max-height:280px">
        <canvas id="maProductivityChart"></canvas>
    </div>
</div>
<?php endif; ?>

<!-- ── Comparison table ──────────────────────────────────────────────────────── -->
<?php if (empty($maStats)): ?>
<div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-12 text-center">
    <div class="w-16 h-16 bg-slate-50 rounded-2xl grid place-items-center mx-auto mb-4">
        <i class="bi bi-people text-slate-400 text-3xl"></i>
    </div>
    <p class="text-slate-600 font-semibold text-lg mb-1">No active MAs found</p>
    <p class="text-slate-400 text-sm">Add MAs in <a href="<?= BASE_URL ?>/admin/users.php" class="text-indigo-600 hover:underline">Manage Staff</a>.</p>
</div>
<?php else: ?>

<div class="bg-white border border-slate-100 rounded-2xl shadow-sm mb-5 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-bold text-slate-700 flex items-center gap-2">
            <i class="bi bi-people-fill text-indigo-400"></i> MA Comparison
        </h3>
        <span class="text-xs text-slate-400"><?= count($maStats) ?> MA<?= count($maStats) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    <th class="text-left px-5 py-3">MA Name</th>
                    <th class="text-center px-4 py-3">Total</th>
                    <th class="text-center px-4 py-3">Completed</th>
                    <th class="text-center px-4 py-3">Missed</th>
                    <th class="text-center px-4 py-3">Rate</th>
                    <th class="text-center px-4 py-3">Patients</th>
                    <th class="text-center px-4 py-3">Forms</th>
                    <th class="text-center px-4 py-3">Active Days</th>
                    <th class="text-left px-4 py-3">Visit Types</th>
                    <th class="text-right px-5 py-3 no-print">Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php foreach ($maStats as $idx => $ma):
                $maId      = (int)$ma['ma_id'];
                $total     = (int)$ma['total_visits'];
                $completed = (int)$ma['completed'];
                $missed    = (int)$ma['missed'];
                $rate      = $total > 0 ? round($completed / $total * 100) : 0;
                $forms     = (int)($formStats[$maId]['signed_forms'] ?? 0);
                $isDrill   = ($drillMaId === $maId);
                $types     = $typeStats[$maId] ?? [];
                arsort($types);
                $topTypes  = array_slice($types, 0, 3, true);
            ?>
            <tr class="hover:bg-slate-50/80 transition-colors <?= $isDrill ? 'bg-indigo-50/60' : '' ?>">
                <td class="px-5 py-3.5">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-xl bg-indigo-100 text-indigo-700 grid place-items-center font-bold text-xs shrink-0">
                            <?= strtoupper(mb_substr($ma['ma_name'], 0, 2)) ?>
                        </div>
                        <div>
                            <div class="font-semibold text-slate-800"><?= h($ma['ma_name']) ?></div>
                            <?php if ($total === 0): ?>
                            <div class="text-xs text-slate-400">No visits this period</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
                <td class="text-center px-4 py-3.5">
                    <span class="font-bold text-slate-700"><?= $total ?></span>
                </td>
                <td class="text-center px-4 py-3.5">
                    <span class="font-bold text-emerald-600"><?= $completed ?></span>
                </td>
                <td class="text-center px-4 py-3.5">
                    <span class="font-bold <?= $missed > 0 ? 'text-red-600' : 'text-slate-400' ?>"><?= $missed ?></span>
                </td>
                <td class="text-center px-4 py-3.5">
                    <?php if ($total > 0): ?>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-xl text-xs font-bold <?= rateClass($rate) ?>">
                        <?= $rate ?>%
                    </span>
                    <div class="w-16 mx-auto bg-slate-100 rounded-full h-1 mt-1">
                        <div class="<?= $rate >= 80 ? 'bg-emerald-500' : ($rate >= 60 ? 'bg-amber-500' : 'bg-red-500') ?> h-1 rounded-full"
                             style="width:<?= $rate ?>%"></div>
                    </div>
                    <?php else: ?>
                    <span class="text-slate-300 text-xs">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-center px-4 py-3.5 font-semibold text-slate-700"><?= (int)$ma['unique_patients'] ?></td>
                <td class="text-center px-4 py-3.5 font-semibold text-violet-700"><?= $forms ?></td>
                <td class="text-center px-4 py-3.5 text-slate-500 text-xs"><?= (int)$ma['active_days'] ?> day<?= (int)$ma['active_days'] !== 1 ? 's' : '' ?></td>
                <td class="px-4 py-3.5">
                    <div class="flex flex-wrap gap-1">
                        <?php foreach ($topTypes as $vt => $cnt): ?>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg text-xs font-semibold <?= $VT_COLORS[$vt] ?? 'bg-slate-100 text-slate-600' ?>">
                            <?= h($VT_LABELS[$vt] ?? $vt) ?>
                            <span class="font-bold"><?= $cnt ?></span>
                        </span>
                        <?php endforeach; ?>
                        <?php if (empty($topTypes)): ?>
                        <span class="text-slate-300 text-xs">—</span>
                        <?php endif; ?>
                    </div>
                </td>
                <td class="text-right px-5 py-3.5 no-print">
                    <?php
                    $drillUrl = $baseUrl . '?period=' . $period
                        . ($period === 'custom' ? "&from={$from}&to={$to}" : '')
                        . '&ma_id=' . $maId;
                    $clearUrl = $baseUrl . '?period=' . $period
                        . ($period === 'custom' ? "&from={$from}&to={$to}" : '');
                    ?>
                    <?php if ($isDrill): ?>
                    <a href="<?= h($clearUrl) ?>"
                       class="inline-flex items-center gap-1 px-3 py-1.5 bg-indigo-600 text-white rounded-xl text-xs font-bold hover:bg-indigo-700 transition-colors">
                        <i class="bi bi-x-lg"></i> Close
                    </a>
                    <?php elseif ($total > 0): ?>
                    <a href="<?= h($drillUrl) ?>#drilldown"
                       class="inline-flex items-center gap-1 px-3 py-1.5 bg-slate-100 text-slate-700 rounded-xl text-xs font-semibold hover:bg-indigo-50 hover:text-indigo-700 transition-colors">
                        <i class="bi bi-list-ul"></i> View Visits
                    </a>
                    <?php else: ?>
                    <span class="text-slate-200 text-xs">—</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php endif; // end !empty($maStats) ?>

<!-- ── Drill-down: individual visit list ─────────────────────────────────────── -->
<?php if ($drillMaId && !empty($drillVisits)): ?>
<div id="drilldown" class="bg-white border border-indigo-100 rounded-2xl shadow-sm overflow-hidden scroll-mt-20">

    <!-- Header -->
    <div class="bg-gradient-to-r from-indigo-600 to-violet-600 px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-white/20 rounded-xl grid place-items-center font-bold text-white">
                <?= strtoupper(mb_substr($drillMaName, 0, 2)) ?>
            </div>
            <div>
                <h3 class="text-white font-bold text-base"><?= h($drillMaName) ?></h3>
                <p class="text-indigo-200 text-xs"><?= count($drillVisits) ?> visit<?= count($drillVisits) !== 1 ? 's' : '' ?> &mdash; <?= h($rangeLabel) ?></p>
            </div>
        </div>
        <a href="<?= h($baseUrl . '?period=' . $period . ($period === 'custom' ? "&from={$from}&to={$to}" : '')) ?>"
           class="text-white/70 hover:text-white p-2 rounded-xl hover:bg-white/10 transition-colors no-print">
            <i class="bi bi-x-lg"></i>
        </a>
    </div>

    <!-- Visit type summary pills for this MA -->
    <?php $drillTypes = $typeStats[$drillMaId] ?? []; arsort($drillTypes); ?>
    <?php if (!empty($drillTypes)): ?>
    <div class="px-6 py-3 border-b border-slate-100 flex flex-wrap gap-2">
        <?php foreach ($drillTypes as $vt => $cnt): ?>
        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-bold <?= $VT_COLORS[$vt] ?? 'bg-slate-100 text-slate-600' ?>">
            <?= h($VT_LABELS[$vt] ?? $vt) ?> <span class="bg-white/60 px-1.5 py-0.5 rounded-md"><?= $cnt ?></span>
        </span>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Visit list -->
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                    <th class="text-left px-5 py-3">Date</th>
                    <th class="text-left px-4 py-3">Patient</th>
                    <th class="text-center px-4 py-3">Type</th>
                    <th class="text-center px-4 py-3">Status</th>
                    <th class="text-center px-4 py-3">Time</th>
                    <th class="text-left px-4 py-3">Visit Notes</th>
                    <th class="text-right px-5 py-3 no-print">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            <?php
            $prevDate = '';
            foreach ($drillVisits as $v):
                $sd  = $STATUS_DEF[$v['status']] ?? $STATUS_DEF['pending'];
                $vt  = $v['visit_type'] ?? 'routine';
                $dateChanged = $v['visit_date'] !== $prevDate;
                $prevDate    = $v['visit_date'];
            ?>
            <?php if ($dateChanged): ?>
            <tr class="bg-slate-50/80">
                <td colspan="7" class="px-5 py-1.5 text-xs font-bold text-slate-500 uppercase tracking-wide">
                    <?= date('l, M j, Y', strtotime($v['visit_date'])) ?>
                </td>
            </tr>
            <?php endif; ?>
            <tr class="hover:bg-slate-50 transition-colors">
                <td class="px-5 py-3 text-xs text-slate-400 font-medium whitespace-nowrap">
                    <?= date('M j', strtotime($v['visit_date'])) ?>
                </td>
                <td class="px-4 py-3">
                    <div class="font-semibold text-slate-800"><?= h($v['patient_name']) ?></div>
                    <?php if ($v['patient_address']): ?>
                    <div class="text-xs text-slate-400 truncate max-w-[200px]"><?= h($v['patient_address']) ?></div>
                    <?php endif; ?>
                </td>
                <td class="text-center px-4 py-3">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold <?= $VT_COLORS[$vt] ?? 'bg-slate-100 text-slate-600' ?>">
                        <?= h($VT_LABELS[$vt] ?? $vt) ?>
                    </span>
                </td>
                <td class="text-center px-4 py-3">
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold <?= $sd[0] ?> <?= $sd[1] ?>">
                        <i class="bi <?= $sd[2] ?> text-xs"></i> <?= $sd[3] ?>
                    </span>
                </td>
                <td class="text-center px-4 py-3 text-xs text-slate-500">
                    <?= $v['visit_time'] ? date('g:i A', strtotime($v['visit_time'])) : '—' ?>
                </td>
                <td class="px-4 py-3 max-w-xs">
                    <?php if (!empty($v['visit_notes'])): ?>
                    <p class="text-xs text-slate-600 line-clamp-2"><?= h(mb_substr($v['visit_notes'], 0, 160)) ?><?= mb_strlen($v['visit_notes']) > 160 ? '…' : '' ?></p>
                    <?php elseif (!empty($v['notes'])): ?>
                    <p class="text-xs text-slate-400 italic line-clamp-1"><?= h(mb_substr($v['notes'], 0, 100)) ?></p>
                    <?php else: ?>
                    <span class="text-slate-300 text-xs">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-right px-5 py-3 no-print">
                    <a href="<?= BASE_URL ?>/admin/schedule_manage.php?date=<?= $v['visit_date'] ?>"
                       class="text-xs text-indigo-500 hover:text-indigo-700 font-semibold hover:underline">
                        <i class="bi bi-pencil"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php elseif ($drillMaId && empty($drillVisits)): ?>
<div id="drilldown" class="bg-white border border-slate-100 rounded-2xl shadow-sm p-8 text-center scroll-mt-20">
    <p class="text-slate-500 font-semibold">No visits found for this MA in the selected period.</p>
    <a href="<?= h($baseUrl . '?period=' . $period) ?>" class="text-indigo-600 text-sm hover:underline mt-2 inline-block">Clear selection</a>
</div>
<?php endif; ?>

<!-- ── Chart.js ────────────────────────────────────────────────────────────── -->
<?php if (!empty($maStats) && $totals['total'] > 0): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    const data = <?= $chartJson ?>;
    const ctx  = document.getElementById('maProductivityChart');
    if (!ctx || !data.labels.length) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Completed',
                    data: data.completed,
                    backgroundColor: 'rgba(16,185,129,0.85)',
                    borderRadius: 4,
                    borderSkipped: false,
                },
                {
                    label: 'Missed',
                    data: data.missed,
                    backgroundColor: 'rgba(239,68,68,0.75)',
                    borderRadius: 4,
                    borderSkipped: false,
                },
                {
                    label: 'Pending/En Route',
                    data: data.pending,
                    backgroundColor: 'rgba(148,163,184,0.6)',
                    borderRadius: 4,
                    borderSkipped: false,
                },
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            indexAxis: 'y',
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: { size: 12, family: 'Inter, sans-serif' },
                        color: '#64748b',
                        usePointStyle: true,
                        pointStyleWidth: 10,
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ' ' + ctx.dataset.label + ': ' + ctx.parsed.x;
                        }
                    }
                }
            },
            scales: {
                x: {
                    stacked: true,
                    grid:  { color: 'rgba(226,232,240,0.8)' },
                    ticks: { color: '#94a3b8', font: { size: 11 }, precision: 0 }
                },
                y: {
                    stacked: true,
                    grid:  { display: false },
                    ticks: { color: '#475569', font: { size: 12, weight: '600' } }
                }
            }
        }
    });
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
