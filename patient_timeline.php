<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireNotBilling();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/patients.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) { header('Location: ' . BASE_URL . '/patients.php'); exit; }

$pageTitle = $patient['first_name'] . ' ' . $patient['last_name'] . ' — Timeline';
$activeNav = 'patients';

/* ── All form submissions (ASC for trend charts) ── */
$formsStmt = $pdo->prepare("
    SELECT fs.*, s.full_name AS ma_name
    FROM form_submissions fs
    LEFT JOIN staff s ON s.id = fs.ma_id
    WHERE fs.patient_id = ?
    ORDER BY fs.created_at ASC
");
$formsStmt->execute([$id]);
$allForms = $formsStmt->fetchAll();

/* ── All wound photos (ASC) ── */
$photosStmt = $pdo->prepare("
    SELECT wp.*, s.full_name AS ma_name
    FROM wound_photos wp
    LEFT JOIN staff s ON s.id = wp.uploaded_by
    WHERE wp.patient_id = ?
    ORDER BY wp.created_at ASC
");
$photosStmt->execute([$id]);
$allPhotos = $photosStmt->fetchAll();

/* ── Extract vitals trend data from vital_cs submissions ── */
$chartLabels    = [];
$chartSystolic  = [];
$chartDiastolic = [];
$chartWeight    = [];
$chartO2        = [];
$chartPulse     = [];

foreach ($allForms as $f) {
    if ($f['form_type'] !== 'vital_cs') continue;
    $fd = json_decode($f['form_data'], true) ?? [];

    $chartLabels[] = date('M j', strtotime($f['created_at']));

    // BP: "120/80" → systolic / diastolic
    $bp = trim($fd['bp'] ?? '');
    if (preg_match('/(\d+)\s*\/\s*(\d+)/', $bp, $m)) {
        $chartSystolic[]  = (int)$m[1];
        $chartDiastolic[] = (int)$m[2];
    } else {
        $chartSystolic[]  = null;
        $chartDiastolic[] = null;
    }

    // Weight: "185 lbs" or "185"
    preg_match('/(\d+\.?\d*)/', $fd['weight'] ?? '', $wtM);
    $chartWeight[] = isset($wtM[1]) ? (float)$wtM[1] : null;

    // O2Sat: "98%" or "98"
    preg_match('/(\d+\.?\d*)/', $fd['o2sat'] ?? '', $o2M);
    $chartO2[] = isset($o2M[1]) ? (float)$o2M[1] : null;

    // Pulse: "72 bpm" or "72"
    preg_match('/(\d+)/', $fd['pulse'] ?? '', $plsM);
    $chartPulse[] = isset($plsM[1]) ? (int)$plsM[1] : null;
}

$hasVitals      = !empty($chartLabels);
$jsChartData    = json_encode([
    'labels'    => $chartLabels,
    'systolic'  => $chartSystolic,
    'diastolic' => $chartDiastolic,
    'weight'    => $chartWeight,
    'o2sat'     => $chartO2,
    'pulse'     => $chartPulse,
]);

/* ── Build unified timeline (DESC for display) ── */
$timeline = [];

foreach ($allForms as $f) {
    $timeline[] = ['kind' => 'form', 'ts' => $f['created_at'], 'data' => $f];
}

// Group photos by calendar day → one "photos" event per day
$photosByDay = [];
foreach ($allPhotos as $ph) {
    $day = date('Y-m-d', strtotime($ph['created_at']));
    $photosByDay[$day][] = $ph;
}
foreach ($photosByDay as $day => $dayphotos) {
    $timeline[] = ['kind' => 'photos', 'ts' => $day . ' 23:59:59', 'data' => $dayphotos];
}

usort($timeline, fn($a, $b) => strcmp($b['ts'], $a['ts']));

// Group by date for display
$grouped = [];
foreach ($timeline as $item) {
    $day = date('Y-m-d', strtotime($item['ts']));
    $grouped[$day][] = $item;
}

/* ── Config maps ── */
$formDefs = [
    'vital_cs'           => ['label' => 'Visit Consent',          'icon' => 'bi-file-medical',        'bg' => 'bg-red-100',     'text' => 'text-red-700',     'dot' => 'bg-red-400',     'ring' => 'ring-red-100'],
    'new_patient'        => ['label' => 'New Patient Consent',    'icon' => 'bi-person-plus',         'bg' => 'bg-blue-100',    'text' => 'text-blue-600',    'dot' => 'bg-blue-400',    'ring' => 'ring-blue-100'],
    'abn'                => ['label' => 'ABN (CMS-R-131)',         'icon' => 'bi-file-earmark-ruled',  'bg' => 'bg-amber-100',   'text' => 'text-amber-600',   'dot' => 'bg-amber-400',   'ring' => 'ring-amber-100'],
    'pf_signup'          => ['label' => 'PF Portal Consent',      'icon' => 'bi-envelope-at',         'bg' => 'bg-cyan-100',    'text' => 'text-cyan-600',    'dot' => 'bg-cyan-400',    'ring' => 'ring-cyan-100'],
    'ccm_consent'        => ['label' => 'CCM Consent',            'icon' => 'bi-calendar2-heart',     'bg' => 'bg-emerald-100', 'text' => 'text-emerald-600', 'dot' => 'bg-emerald-400', 'ring' => 'ring-emerald-100'],
    'cognitive_wellness' => ['label' => 'Cognitive Wellness Exam','icon' => 'bi-brain',               'bg' => 'bg-violet-100',  'text' => 'text-violet-600',  'dot' => 'bg-violet-400',  'ring' => 'ring-violet-100'],
    'medicare_awv'       => ['label' => 'Medicare AWV',           'icon' => 'bi-clipboard2-pulse',    'bg' => 'bg-sky-100',     'text' => 'text-sky-600',     'dot' => 'bg-sky-400',     'ring' => 'ring-sky-100'],
    'il_disclosure'      => ['label' => 'IL Disclosure Auth.',    'icon' => 'bi-file-earmark-text',   'bg' => 'bg-slate-100',   'text' => 'text-slate-600',   'dot' => 'bg-slate-400',   'ring' => 'ring-slate-100'],
];

$statusCfg = [
    'draft'    => ['bg' => 'bg-slate-100',   'text' => 'text-slate-600',   'dot' => 'bg-slate-400',   'label' => 'Draft'],
    'signed'   => ['bg' => 'bg-blue-100',    'text' => 'text-blue-700',    'dot' => 'bg-blue-500',    'label' => 'Signed'],
    'uploaded' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'label' => 'Uploaded'],
];

$totalEvents = count($timeline);
$totalForms  = count($allForms);
$totalPhotos = count($allPhotos);

/* ── Extra JS: Chart.js ── */
ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    const cd = <?= $jsChartData ?>;
    if (!cd.labels.length) return;

    const defOpts = (title, yLabel) => ({
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12, padding: 10 } },
            tooltip: {
                backgroundColor: '#1e293b',
                titleFont: { size: 11 },
                bodyFont: { size: 12, weight: '600' },
                padding: 10,
                cornerRadius: 10,
            }
        },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#94a3b8' } },
            y: {
                grid: { color: '#f1f5f9' },
                ticks: { font: { size: 10 }, color: '#94a3b8' },
                title: { display: true, text: yLabel, font: { size: 10 }, color: '#94a3b8' }
            }
        }
    });

    /* BP chart */
    const bpCtx = document.getElementById('chartBP');
    if (bpCtx && (cd.systolic.some(v => v !== null) || cd.diastolic.some(v => v !== null))) {
        new Chart(bpCtx, {
            type: 'line',
            data: {
                labels: cd.labels,
                datasets: [
                    {
                        label: 'Systolic',
                        data: cd.systolic,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239,68,68,0.08)',
                        borderWidth: 2.5,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        fill: false,
                        tension: 0.35,
                        spanGaps: true,
                    },
                    {
                        label: 'Diastolic',
                        data: cd.diastolic,
                        borderColor: '#f97316',
                        backgroundColor: 'rgba(249,115,22,0.06)',
                        borderWidth: 2,
                        pointRadius: 3,
                        pointHoverRadius: 5,
                        fill: false,
                        tension: 0.35,
                        spanGaps: true,
                        borderDash: [4, 3],
                    }
                ]
            },
            options: defOpts('Blood Pressure', 'mmHg')
        });
    } else if (bpCtx) {
        bpCtx.closest('.chart-wrap').classList.add('hidden');
    }

    /* Weight chart */
    const wtCtx = document.getElementById('chartWeight');
    if (wtCtx && cd.weight.some(v => v !== null)) {
        new Chart(wtCtx, {
            type: 'line',
            data: {
                labels: cd.labels,
                datasets: [{
                    label: 'Weight',
                    data: cd.weight,
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139,92,246,0.08)',
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.35,
                    spanGaps: true,
                }]
            },
            options: defOpts('Weight', 'lbs')
        });
    } else if (wtCtx) {
        wtCtx.closest('.chart-wrap').classList.add('hidden');
    }

    /* O2Sat chart */
    const o2Ctx = document.getElementById('chartO2');
    if (o2Ctx && cd.o2sat.some(v => v !== null)) {
        new Chart(o2Ctx, {
            type: 'line',
            data: {
                labels: cd.labels,
                datasets: [{
                    label: 'O2 Sat',
                    data: cd.o2sat,
                    borderColor: '#0ea5e9',
                    backgroundColor: 'rgba(14,165,233,0.08)',
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointHoverRadius: 6,
                    fill: true,
                    tension: 0.35,
                    spanGaps: true,
                }]
            },
            options: {
                ...defOpts('O2 Saturation', '%'),
                scales: {
                    ...defOpts('O2 Saturation', '%').scales,
                    y: {
                        ...defOpts('O2 Saturation', '%').scales.y,
                        min: 80,
                        max: 100,
                        ticks: { font: { size: 10 }, color: '#94a3b8', callback: v => v + '%' }
                    }
                }
            }
        });
    } else if (o2Ctx) {
        o2Ctx.closest('.chart-wrap').classList.add('hidden');
    }

    /* Filter tabs */
    document.querySelectorAll('[data-filter]').forEach(btn => {
        btn.addEventListener('click', function () {
            const f = this.dataset.filter;
            document.querySelectorAll('[data-filter]').forEach(b => {
                b.classList.toggle('bg-white',    b === this);
                b.classList.toggle('text-blue-700', b === this);
                b.classList.toggle('shadow',      b === this);
                b.classList.toggle('text-slate-500', b !== this);
            });
            document.querySelectorAll('[data-kind]').forEach(el => {
                if (f === 'all' || el.dataset.kind === f) {
                    el.classList.remove('hidden');
                } else {
                    el.classList.add('hidden');
                }
            });
            // Hide empty day groups
            document.querySelectorAll('.day-group').forEach(dg => {
                const visible = [...dg.querySelectorAll('[data-kind]')].some(el => !el.classList.contains('hidden'));
                dg.style.display = visible ? '' : 'none';
            });
        });
    });
})();
</script>
<?php
$extraJs = ob_get_clean();

include __DIR__ . '/includes/header.php';
?>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-slate-400 mb-6">
    <a href="<?= BASE_URL ?>/patients.php" class="hover:text-blue-600 transition-colors font-medium">Patients</a>
    <i class="bi bi-chevron-right text-xs"></i>
    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $id ?>" class="hover:text-blue-600 transition-colors font-medium">
        <?= h($patient['first_name'] . ' ' . $patient['last_name']) ?>
    </a>
    <i class="bi bi-chevron-right text-xs"></i>
    <span class="text-slate-700 font-semibold">Visit Timeline</span>
</nav>

<!-- Patient Header -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-700 grid place-items-center
                        text-white font-extrabold text-xl shadow-lg flex-shrink-0">
                <?= strtoupper(substr($patient['first_name'],0,1) . substr($patient['last_name'],0,1)) ?>
            </div>
            <div>
                <h2 class="text-xl font-extrabold text-slate-800">
                    <?= h($patient['first_name'] . ' ' . $patient['last_name']) ?>
                </h2>
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-sm text-slate-500">
                    <?php if ($patient['dob']): ?>
                    <span><i class="bi bi-calendar3 mr-1"></i><?= date('M j, Y', strtotime($patient['dob'])) ?></span>
                    <?php endif; ?>
                    <?php if ($patient['phone']): ?>
                    <span><i class="bi bi-telephone mr-1"></i><?= h($patient['phone']) ?></span>
                    <?php endif; ?>
                    <?php if ($patient['insurance']): ?>
                    <span><i class="bi bi-shield-plus mr-1"></i><?= h($patient['insurance']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <div class="flex gap-4 text-center mr-4">
                <div>
                    <div class="text-xl font-extrabold text-slate-800"><?= $totalForms ?></div>
                    <div class="text-xs text-slate-400 font-medium">Forms</div>
                </div>
                <div class="w-px bg-slate-100"></div>
                <div>
                    <div class="text-xl font-extrabold text-slate-800"><?= $totalPhotos ?></div>
                    <div class="text-xs text-slate-400 font-medium">Photos</div>
                </div>
                <?php if ($hasVitals): ?>
                <div class="w-px bg-slate-100"></div>
                <div>
                    <div class="text-xl font-extrabold text-slate-800"><?= count($chartLabels) ?></div>
                    <div class="text-xs text-slate-400 font-medium">Visits</div>
                </div>
                <?php endif; ?>
            </div>
            <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $id ?>"
               class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-slate-700
                      bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                <i class="bi bi-grid-3x3-gap-fill"></i> Dashboard
            </a>
        </div>
    </div>
</div>

<?php if ($hasVitals): ?>
<!-- ─── Vitals Trend Charts ──────────────────────────────────────────── -->
<div class="mb-6">
    <div class="flex items-center gap-2 mb-3">
        <i class="bi bi-graph-up-arrow text-blue-600"></i>
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Vitals Trends</h3>
        <span class="text-xs text-slate-400 font-medium ml-1">— <?= count($chartLabels) ?> visit<?= count($chartLabels) === 1 ? '' : 's' ?></span>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

        <!-- BP -->
        <div class="chart-wrap bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-8 h-8 bg-red-100 rounded-xl grid place-items-center">
                    <i class="bi bi-heart-pulse-fill text-red-600 text-sm"></i>
                </span>
                <div>
                    <p class="text-sm font-bold text-slate-700">Blood Pressure</p>
                    <p class="text-xs text-slate-400">Systolic / Diastolic</p>
                </div>
            </div>
            <div class="h-44">
                <canvas id="chartBP"></canvas>
            </div>
        </div>

        <!-- Weight -->
        <div class="chart-wrap bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-8 h-8 bg-violet-100 rounded-xl grid place-items-center">
                    <i class="bi bi-speedometer text-violet-600 text-sm"></i>
                </span>
                <div>
                    <p class="text-sm font-bold text-slate-700">Weight</p>
                    <p class="text-xs text-slate-400">Pounds (lbs)</p>
                </div>
            </div>
            <div class="h-44">
                <canvas id="chartWeight"></canvas>
            </div>
        </div>

        <!-- O2 Sat -->
        <div class="chart-wrap bg-white rounded-2xl shadow-sm border border-slate-100 p-4">
            <div class="flex items-center gap-2 mb-3">
                <span class="w-8 h-8 bg-sky-100 rounded-xl grid place-items-center">
                    <i class="bi bi-lungs-fill text-sky-600 text-sm"></i>
                </span>
                <div>
                    <p class="text-sm font-bold text-slate-700">O2 Saturation</p>
                    <p class="text-xs text-slate-400">Percent (%)</p>
                </div>
            </div>
            <div class="h-44">
                <canvas id="chartO2"></canvas>
            </div>
        </div>

    </div>
</div>
<?php endif; ?>

<!-- ─── Timeline ─────────────────────────────────────────────────────── -->
<div class="mb-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
    <div class="flex items-center gap-2">
        <i class="bi bi-clock-history text-blue-600"></i>
        <h3 class="text-sm font-bold text-slate-700 uppercase tracking-wider">Visit History</h3>
        <span class="text-xs text-slate-400 font-medium ml-1">— <?= $totalEvents ?> event<?= $totalEvents === 1 ? '' : 's' ?></span>
    </div>
    <!-- Filter tabs -->
    <div class="flex gap-1 bg-slate-100 p-1 rounded-2xl w-fit text-sm">
        <button data-filter="all"    class="px-4 py-2 rounded-xl font-semibold transition-all bg-white text-blue-700 shadow">All</button>
        <button data-filter="form"   class="px-4 py-2 rounded-xl font-semibold transition-all text-slate-500 hover:text-slate-700">
            <i class="bi bi-file-earmark-text mr-1"></i>Forms
        </button>
        <button data-filter="photos" class="px-4 py-2 rounded-xl font-semibold transition-all text-slate-500 hover:text-slate-700">
            <i class="bi bi-camera mr-1"></i>Photos
        </button>
    </div>
</div>

<?php if (empty($grouped)): ?>
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 flex flex-col items-center justify-center py-16 text-slate-400">
    <i class="bi bi-clock-history text-5xl mb-3 opacity-30"></i>
    <p class="font-semibold text-slate-500">No history yet</p>
    <p class="text-sm mt-1">Forms and photos will appear here after the first visit.</p>
</div>
<?php else: ?>

<!-- Timeline -->
<div class="space-y-8 pb-10">
<?php foreach ($grouped as $day => $items): ?>

    <div class="day-group">
        <!-- Date separator -->
        <div class="flex items-center gap-3 mb-4">
            <span class="inline-flex items-center gap-2 text-xs font-bold text-slate-600 bg-white border border-slate-200
                         px-3.5 py-1.5 rounded-full shadow-sm whitespace-nowrap">
                <i class="bi bi-calendar3 text-blue-500"></i>
                <?= date('l, F j, Y', strtotime($day)) ?>
            </span>
            <div class="flex-1 h-px bg-slate-200"></div>
            <?php
                $dayFormIds = [];
                foreach ($items as $it) {
                    if ($it['kind'] === 'form') $dayFormIds[] = $it['data']['id'];
                }
            ?>
            <?php if ($dayFormIds): ?>
            <a href="<?= BASE_URL ?>/export_pdf.php?patient_id=<?= $id ?>&date=<?= urlencode($day) ?>"
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500
                      hover:text-blue-700 bg-white border border-slate-200 hover:border-blue-300
                      px-3 py-1.5 rounded-full transition-colors whitespace-nowrap shadow-sm"
               title="Export all <?= count($dayFormIds) ?> form<?= count($dayFormIds)!==1?'s':'' ?> from this visit as PDF">
                <i class="bi bi-file-earmark-pdf-fill text-blue-400"></i>
                Export Visit PDF
            </a>
            <?php endif; ?>
            <span class="text-xs text-slate-400 whitespace-nowrap"><?= count($items) ?> event<?= count($items)===1?'':'s' ?></span>
        </div>

        <!-- Events for this day -->
        <div class="space-y-3 pl-2">
        <?php foreach ($items as $item):
            if ($item['kind'] === 'form'):
                $f  = $item['data'];
                $fd_def = $formDefs[$f['form_type']] ?? ['label'=>$f['form_type'],'icon'=>'bi-file','bg'=>'bg-slate-100','text'=>'text-slate-600','dot'=>'bg-slate-400','ring'=>'ring-slate-100'];
                $sc = $statusCfg[$f['status']] ?? $statusCfg['draft'];
                $formData = json_decode($f['form_data'], true) ?? [];
        ?>

        <!-- ── Form Event ── -->
        <div data-kind="form" class="flex gap-4">
            <!-- Dot + line -->
            <div class="flex flex-col items-center flex-shrink-0">
                <div class="w-4 h-4 rounded-full <?= $fd_def['dot'] ?> ring-4 <?= $fd_def['ring'] ?> mt-0.5 flex-shrink-0"></div>
                <div class="w-0.5 bg-slate-100 flex-1 mt-1"></div>
            </div>
            <!-- Card -->
            <div class="flex-1 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-1
                        hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between gap-3 p-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="<?= $fd_def['bg'] ?> <?= $fd_def['text'] ?> p-2.5 rounded-xl flex-shrink-0">
                            <i class="bi <?= $fd_def['icon'] ?> text-base"></i>
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-slate-800 truncate"><?= $fd_def['label'] ?></p>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-0.5">
                                <span class="text-xs text-slate-400">
                                    <i class="bi bi-clock mr-0.5"></i><?= date('g:i a', strtotime($f['created_at'])) ?>
                                </span>
                                <?php if ($f['ma_name']): ?>
                                <span class="text-xs text-slate-400">
                                    <i class="bi bi-person mr-0.5"></i><?= h($f['ma_name']) ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?= $sc['bg'] ?> <?= $sc['text'] ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= $sc['dot'] ?>"></span>
                            <?= $sc['label'] ?>
                        </span>
                        <a href="<?= BASE_URL ?>/view_document.php?id=<?= $f['id'] ?>"
                           class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 font-semibold text-xs
                                  bg-blue-50 hover:bg-blue-100 px-3 py-2 rounded-xl transition-colors">
                            <i class="bi bi-eye-fill"></i> View
                        </a>
                    </div>
                </div>

                <?php if ($f['form_type'] === 'vital_cs'): ?>
                <!-- Vitals mini-strip -->
                <?php
                    $vitalsStrip = [
                        ['key' => 'bp',     'label' => 'BP',    'icon' => 'bi-heart-pulse'],
                        ['key' => 'pulse',  'label' => 'Pulse', 'icon' => 'bi-activity'],
                        ['key' => 'temp',   'label' => 'Temp',  'icon' => 'bi-thermometer-half'],
                        ['key' => 'o2sat',  'label' => 'O2',    'icon' => 'bi-lungs'],
                        ['key' => 'weight', 'label' => 'Wt',    'icon' => 'bi-speedometer'],
                        ['key' => 'resp',   'label' => 'Resp',  'icon' => 'bi-wind'],
                    ];
                    $hasStrip = false;
                    foreach ($vitalsStrip as $vs) {
                        if (!empty($formData[$vs['key']])) { $hasStrip = true; break; }
                    }
                ?>
                <?php if ($hasStrip): ?>
                <div class="border-t border-slate-50 bg-slate-50/60 px-4 py-2.5">
                    <div class="flex flex-wrap gap-x-5 gap-y-1.5">
                        <?php foreach ($vitalsStrip as $vs):
                            $val = trim($formData[$vs['key']] ?? '');
                            if (!$val) continue;
                        ?>
                        <div class="flex items-center gap-1.5">
                            <i class="bi <?= $vs['icon'] ?> text-red-400 text-xs"></i>
                            <span class="text-xs font-bold text-slate-700"><?= h($val) ?></span>
                            <span class="text-xs text-slate-400"><?= $vs['label'] ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>

                <?php
                // Chief complaint snippet for vital_cs
                if ($f['form_type'] === 'vital_cs' && !empty($formData['chief_complaint'])):
                ?>
                <div class="border-t border-slate-50 px-4 py-2.5">
                    <p class="text-xs text-slate-500">
                        <span class="font-semibold text-slate-600">CC:</span>
                        <?= h(mb_strimwidth($formData['chief_complaint'], 0, 140, '…')) ?>
                    </p>
                </div>
                <?php endif; ?>

                <?php
                // Medications snippet: count non-empty rows
                $medCount = 0;
                for ($mi = 1; $mi <= 6; $mi++) {
                    if (!empty($formData["med_name_$mi"])) $medCount++;
                }
                if ($medCount > 0):
                ?>
                <div class="border-t border-slate-50 bg-slate-50/40 px-4 py-2.5">
                    <p class="text-xs text-slate-500 font-medium">
                        <i class="bi bi-capsule mr-1 text-slate-400"></i>
                        <?= $medCount ?> medication<?= $medCount > 1 ? 's' : '' ?> documented
                    </p>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <?php elseif ($item['kind'] === 'photos'):
            $photos = $item['data'];
            $photoCount = count($photos);
        ?>

        <!-- ── Photos Event ── -->
        <div data-kind="photos" class="flex gap-4">
            <!-- Dot + line -->
            <div class="flex flex-col items-center flex-shrink-0">
                <div class="w-4 h-4 rounded-full bg-violet-500 ring-4 ring-violet-100 mt-0.5 flex-shrink-0"></div>
                <div class="w-0.5 bg-slate-100 flex-1 mt-1"></div>
            </div>
            <!-- Card -->
            <div class="flex-1 bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-1
                        hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3 p-4 pb-3">
                    <span class="bg-violet-100 text-violet-600 p-2.5 rounded-xl flex-shrink-0">
                        <i class="bi bi-camera-fill text-base"></i>
                    </span>
                    <div>
                        <p class="text-sm font-bold text-slate-800">
                            Wound Photo<?= $photoCount > 1 ? 's' : '' ?>
                            <span class="ml-1 text-xs font-semibold bg-violet-100 text-violet-700 px-1.5 py-0.5 rounded-full">
                                <?= $photoCount ?>
                            </span>
                        </p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            <i class="bi bi-person mr-0.5"></i><?= h($photos[0]['ma_name'] ?? 'Unknown') ?>
                        </p>
                    </div>
                </div>
                <!-- Photo grid -->
                <div class="px-4 pb-4">
                    <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 gap-2">
                        <?php foreach ($photos as $ph): ?>
                        <div class="group relative aspect-square rounded-xl overflow-hidden bg-slate-100 border border-slate-200">
                            <img src="<?= BASE_URL ?>/uploads/photos/<?= h($ph['filename']) ?>"
                                 alt="<?= h($ph['location'] ?: 'Wound photo') ?>"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                 loading="lazy">
                            <?php if ($ph['location']): ?>
                            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/60 to-transparent
                                        opacity-0 group-hover:opacity-100 transition-opacity p-1">
                                <p class="text-white text-[10px] font-semibold leading-tight truncate"><?= h($ph['location']) ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php
                    // Collect unique locations / descriptions
                    $locations = array_unique(array_filter(array_column($photos, 'location')));
                    if ($locations):
                    ?>
                    <p class="text-xs text-slate-400 mt-2 truncate">
                        <i class="bi bi-geo-alt mr-1"></i><?= h(implode(' · ', $locations)) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php endif; ?>
        <?php endforeach; ?>
        </div>
    </div>

<?php endforeach; ?>
</div><!-- /timeline -->

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
