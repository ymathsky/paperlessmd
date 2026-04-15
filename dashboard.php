<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$today     = date('Y-m-d');
$hour      = (int)date('H');
$greeting  = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
$firstName = explode(' ', $_SESSION['full_name'] ?? 'Staff')[0];

$totalPatients = (int)$pdo->query("SELECT COUNT(*) FROM patients")->fetchColumn();
$formsToday    = (int)$pdo->query("SELECT COUNT(*) FROM form_submissions WHERE DATE(created_at) = '$today'")->fetchColumn();
$photosToday   = (int)$pdo->query("SELECT COUNT(*) FROM wound_photos WHERE DATE(created_at) = '$today'")->fetchColumn();
$pendingUpload = (int)$pdo->query("SELECT COUNT(*) FROM form_submissions WHERE status = 'signed'")->fetchColumn();

// Billing-specific stats
$billingSignedForms   = (int)$pdo->query("SELECT COUNT(*) FROM form_submissions WHERE status IN ('signed','uploaded')")->fetchColumn();
$billingSignedToday   = (int)$pdo->query("SELECT COUNT(*) FROM form_submissions WHERE status IN ('signed','uploaded') AND DATE(created_at) = '$today'")->fetchColumn();
$billingPendingUpload = $pendingUpload;

// Today's schedule for current user (MAs see own; admins see all)
$myScheduleStmt = $pdo->prepare("
    SELECT sc.*,
           CONCAT(p.first_name,' ',p.last_name) AS patient_name,
           p.address AS patient_address,
           p.id AS patient_id
    FROM `schedule` sc
    JOIN patients p ON p.id = sc.patient_id
    WHERE sc.visit_date = ? AND sc.ma_id = ?
    ORDER BY sc.visit_order ASC, sc.visit_time ASC
    LIMIT 6
");
$myScheduleStmt->execute([$today, $_SESSION['user_id']]);
$mySchedule = $myScheduleStmt->fetchAll();
$scheduleTotalToday = (int)$pdo->prepare("SELECT COUNT(*) FROM `schedule` WHERE visit_date=? AND ma_id=?")->execute([$today,$_SESSION['user_id']]) ? $pdo->prepare("SELECT COUNT(*) FROM `schedule` WHERE visit_date=? AND ma_id=?")->execute([$today,$_SESSION['user_id']]) : 0;
// simpler count
$scCountStmt = $pdo->prepare("SELECT COUNT(*) FROM `schedule` WHERE visit_date=? AND ma_id=?");
$scCountStmt->execute([$today, $_SESSION['user_id']]);
$scheduleTotalToday = (int)$scCountStmt->fetchColumn();

// Unsigned (draft) forms — admins see all, MAs see only their own
if (isAdmin()) {
    $draftStmt = $pdo->query("
        SELECT fs.id, fs.form_type, fs.created_at,
               CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.id AS patient_id,
               s.full_name AS ma_name
        FROM form_submissions fs
        JOIN patients p ON p.id = fs.patient_id
        LEFT JOIN staff s ON s.id = fs.ma_id
        WHERE fs.status = 'draft'
        ORDER BY fs.created_at ASC
        LIMIT 100
    ");
    $draftForms = $draftStmt->fetchAll();
} else {
    $draftStmt = $pdo->prepare("
        SELECT fs.id, fs.form_type, fs.created_at,
               CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.id AS patient_id,
               s.full_name AS ma_name
        FROM form_submissions fs
        JOIN patients p ON p.id = fs.patient_id
        LEFT JOIN staff s ON s.id = fs.ma_id
        WHERE fs.status = 'draft' AND fs.ma_id = ?
        ORDER BY fs.created_at ASC
        LIMIT 100
    ");
    $draftStmt->execute([$_SESSION['user_id']]);
    $draftForms = $draftStmt->fetchAll();
}
$draftCount = count($draftForms);

function draftAge(string $ts): array {
    $diff = time() - strtotime($ts);
    if ($diff < 3600)   return [(int)($diff/60).'m ago',  'text-amber-600',  'bg-amber-50'];
    if ($diff < 86400)  return [(int)($diff/3600).'h ago', 'text-orange-600', 'bg-orange-50'];
    return [(int)($diff/86400).'d ago',  'text-red-600',    'bg-red-50'];
}

$stmt = $pdo->query("
    SELECT fs.id, fs.form_type, fs.status, fs.created_at,
           CONCAT(p.first_name,' ',p.last_name) AS patient_name, p.id AS patient_id,
           s.full_name AS ma_name
    FROM form_submissions fs
    JOIN patients p ON p.id = fs.patient_id
    LEFT JOIN staff s ON s.id = fs.ma_id
    ORDER BY fs.created_at DESC LIMIT 12
");
$recent = $stmt->fetchAll();

$formMeta = [
    'vital_cs'           => ['label' => 'Visit Consent',           'icon' => 'bi-file-medical',        'bg' => 'bg-red-100',     'text' => 'text-red-700'],
    'new_patient'        => ['label' => 'New Patient Consent',     'icon' => 'bi-person-plus',         'bg' => 'bg-blue-100',    'text' => 'text-blue-600'],
    'abn'                => ['label' => 'ABN (CMS-R-131)',          'icon' => 'bi-file-earmark-ruled',  'bg' => 'bg-amber-100',   'text' => 'text-amber-600'],
    'pf_signup'          => ['label' => 'PF Portal Consent',        'icon' => 'bi-envelope-at',         'bg' => 'bg-cyan-100',    'text' => 'text-cyan-600'],
    'ccm_consent'        => ['label' => 'CCM Consent',              'icon' => 'bi-calendar2-heart',     'bg' => 'bg-emerald-100', 'text' => 'text-emerald-600'],
    'cognitive_wellness' => ['label' => 'Cognitive Wellness Exam',  'icon' => 'bi-brain',               'bg' => 'bg-violet-100',  'text' => 'text-violet-600'],
    'medicare_awv'       => ['label' => 'Medicare AWV',             'icon' => 'bi-clipboard2-pulse',    'bg' => 'bg-sky-100',     'text' => 'text-sky-600'],
    'il_disclosure'      => ['label' => 'IL Disclosure Auth.',       'icon' => 'bi-file-earmark-text',   'bg' => 'bg-slate-100',   'text' => 'text-slate-600'],
];

include __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="mb-7">
    <h2 class="text-2xl font-extrabold text-slate-800"><?= $greeting ?>, <?= h($firstName) ?> 👋</h2>
    <p class="text-slate-500 text-sm mt-1"><?= date('l, F j, Y') ?></p>
</div>

<!-- Stats -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-7">
    <?php if (canAccessClinical()):
    $stats = [
        ['val' => $totalPatients, 'label' => 'Total Patients',  'icon' => 'bi-people-fill',          'bg' => 'bg-blue-500',    'ring' => 'bg-blue-100',   'txt' => 'text-blue-600',  'alert' => 0],
        ['val' => $formsToday,    'label' => 'Forms Today',      'icon' => 'bi-file-earmark-check',   'bg' => 'bg-emerald-500', 'ring' => 'bg-emerald-100','txt' => 'text-emerald-600','alert' => 0],
        ['val' => $draftCount,    'label' => 'Needs Signature',  'icon' => 'bi-pen-fill',             'bg' => 'bg-rose-500',    'ring' => 'bg-rose-100',   'txt' => 'text-rose-600',  'alert' => $draftCount],
        ['val' => $pendingUpload, 'label' => 'Pending Upload',   'icon' => 'bi-cloud-arrow-up-fill',  'bg' => 'bg-amber-500',   'ring' => 'bg-amber-100',  'txt' => 'text-amber-600', 'alert' => 0],
    ];
    else:
    $stats = [
        ['val' => $totalPatients,       'label' => 'Total Patients',        'icon' => 'bi-people-fill',         'bg' => 'bg-blue-500',    'ring' => 'bg-blue-100',   'txt' => 'text-blue-600',  'alert' => 0],
        ['val' => $billingSignedForms,  'label' => 'Signed Forms',          'icon' => 'bi-file-earmark-check',  'bg' => 'bg-emerald-500', 'ring' => 'bg-emerald-100','txt' => 'text-emerald-600','alert' => 0],
        ['val' => $billingPendingUpload,'label' => 'Pending Upload to PF',  'icon' => 'bi-cloud-arrow-up-fill', 'bg' => 'bg-amber-500',   'ring' => 'bg-amber-100',  'txt' => 'text-amber-600', 'alert' => $billingPendingUpload],
        ['val' => $billingSignedToday,  'label' => 'Signed Today',          'icon' => 'bi-calendar-check-fill', 'bg' => 'bg-indigo-500',  'ring' => 'bg-indigo-100', 'txt' => 'text-indigo-600','alert' => 0],
    ];
    endif;
    foreach ($stats as $s): ?>
    <div class="bg-white rounded-2xl shadow-sm border <?= ($s['alert'] ?? 0) > 0 ? 'border-rose-200 shadow-rose-50' : 'border-slate-100' ?> p-5 hover:shadow-md transition-shadow">
        <div class="flex items-start justify-between mb-4">
            <div class="<?= $s['ring'] ?> p-3 rounded-xl">
                <i class="bi <?= $s['icon'] ?> <?= $s['txt'] ?> text-xl leading-none"></i>
            </div>
            <?php if (($s['alert'] ?? 0) > 0): ?>
            <span class="w-2.5 h-2.5 bg-rose-500 rounded-full mt-1 animate-pulse"></span>
            <?php endif; ?>
        </div>
        <div class="text-3xl font-extrabold <?= ($s['alert'] ?? 0) > 0 ? 'text-rose-600' : 'text-slate-800' ?>"><?= number_format((int)$s['val']) ?></div>
        <div class="text-sm text-slate-500 mt-1 font-medium"><?= $s['label'] ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ── Offline Pending Forms Widget (populated by offline.js) ──────── -->
<div id="offlineDashWidget" class="hidden bg-amber-50 border-2 border-amber-300 rounded-2xl px-5 py-4 mb-7">
    <div class="flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 bg-amber-100 rounded-xl grid place-items-center flex-shrink-0">
                <i class="bi bi-cloud-slash-fill text-amber-600 text-lg"></i>
            </div>
            <div>
                <p class="font-bold text-amber-900 text-sm">
                    <span id="offlineDashCount">0</span> form<span id="offlineDashPlural">s</span> saved offline
                </p>
                <p class="text-xs text-amber-700 mt-0.5">These will sync automatically when you reconnect.</p>
            </div>
        </div>
        <button id="dashSyncBtn"
                class="hidden shrink-0 bg-amber-500 hover:bg-amber-600 text-white text-xs font-bold px-4 py-2 rounded-xl transition flex items-center gap-1.5">
            <i class="bi bi-cloud-upload-fill"></i> Sync Now
        </button>
    </div>
</div>

<!-- Quick Actions -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 mb-7">
    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-4">
        <i class="bi bi-lightning-charge-fill text-amber-400 mr-1"></i> Quick Actions
    </h3>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <?php if (canAccessClinical()): ?>
        <a href="<?= BASE_URL ?>/patient_add.php"
           class="flex flex-col items-center gap-2 p-4 rounded-2xl border-2 border-blue-100 hover:border-blue-400 hover:bg-blue-50 transition-all group">
            <div class="w-12 h-12 bg-blue-100 group-hover:bg-blue-200 rounded-xl grid place-items-center transition-colors">
                <i class="bi bi-person-plus-fill text-blue-600 text-xl"></i>
            </div>
            <span class="text-sm font-semibold text-slate-700">New Patient</span>
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/patients.php"
           class="flex flex-col items-center gap-2 p-4 rounded-2xl border-2 border-slate-100 hover:border-slate-300 hover:bg-slate-50 transition-all group">
            <div class="w-12 h-12 bg-slate-100 group-hover:bg-slate-200 rounded-xl grid place-items-center transition-colors">
                <i class="bi bi-search text-slate-600 text-xl"></i>
            </div>
            <span class="text-sm font-semibold text-slate-700">Find Patient</span>
        </a>
        <a href="<?= BASE_URL ?>/patients.php?filter=pending"
           class="flex flex-col items-center gap-2 p-4 rounded-2xl border-2 border-amber-100 hover:border-amber-400 hover:bg-amber-50 transition-all group">
            <div class="w-12 h-12 bg-amber-100 group-hover:bg-amber-200 rounded-xl grid place-items-center transition-colors">
                <i class="bi bi-cloud-upload-fill text-amber-600 text-xl"></i>
            </div>
            <span class="text-sm font-semibold text-slate-700">Pending Upload</span>
        </a>
        <a href="<?= BASE_URL ?>/patients.php"
           class="flex flex-col items-center gap-2 p-4 rounded-2xl border-2 border-emerald-100 hover:border-emerald-400 hover:bg-emerald-50 transition-all group">
            <div class="w-12 h-12 bg-emerald-100 group-hover:bg-emerald-200 rounded-xl grid place-items-center transition-colors">
                <i class="bi bi-people-fill text-emerald-600 text-xl"></i>
            </div>
            <span class="text-sm font-semibold text-slate-700">All Patients</span>
        </a>
        <a href="<?= BASE_URL ?>/user_manual.html" target="_blank"
           class="flex flex-col items-center gap-2 p-4 rounded-2xl border-2 border-violet-100 hover:border-violet-400 hover:bg-violet-50 transition-all group">
            <div class="w-12 h-12 bg-violet-100 group-hover:bg-violet-200 rounded-xl grid place-items-center transition-colors">
                <i class="bi bi-book-half text-violet-600 text-xl"></i>
            </div>
            <span class="text-sm font-semibold text-slate-700">User Manual</span>
        </a>
    </div>
</div>

<!-- Today's Schedule Widget -->
<?php if (canAccessClinical()): ?>
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-7">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-bold text-slate-700 flex items-center gap-2">
            <i class="bi bi-calendar3 text-indigo-500"></i> Today's Route
            <?php if ($scheduleTotalToday): ?>
            <span class="ml-1 px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs font-bold rounded-full"><?= $scheduleTotalToday ?></span>
            <?php endif; ?>
        </h3>
        <a href="<?= BASE_URL ?>/schedule.php" class="text-xs text-indigo-600 hover:text-indigo-700 font-semibold">Full schedule →</a>
    </div>
    <?php if (empty($mySchedule)): ?>
    <div class="flex items-center gap-4 px-6 py-5">
        <div class="w-10 h-10 bg-indigo-50 rounded-xl grid place-items-center shrink-0">
            <i class="bi bi-calendar-check text-indigo-400 text-lg"></i>
        </div>
        <div>
            <p class="text-sm font-semibold text-slate-600">No visits assigned today</p>
            <?php if (isAdmin()): ?>
            <a href="<?= BASE_URL ?>/admin/schedule_manage.php" class="text-xs text-indigo-600 hover:underline">Assign visits →</a>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <?php
    $scStatusColors = [
        'pending'   => ['bg'=>'bg-slate-100',   'text'=>'text-slate-600',   'dot'=>'bg-slate-400'],
        'en_route'  => ['bg'=>'bg-blue-100',    'text'=>'text-blue-700',    'dot'=>'bg-blue-500'],
        'completed' => ['bg'=>'bg-emerald-100', 'text'=>'text-emerald-700', 'dot'=>'bg-emerald-500'],
        'missed'    => ['bg'=>'bg-red-100',     'text'=>'text-red-700',     'dot'=>'bg-red-400'],
    ];
    ?>
    <div class="divide-y divide-slate-50">
        <?php foreach ($mySchedule as $idx => $sv):
            $sc = $scStatusColors[$sv['status']] ?? $scStatusColors['pending'];
        ?>
        <div class="flex items-center gap-3 px-5 py-3 hover:bg-slate-50 transition-colors">
            <div class="w-7 h-7 bg-indigo-50 text-indigo-600 font-bold text-xs rounded-lg grid place-items-center shrink-0">
                <?= $idx + 1 ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $sv['patient_id'] ?>"
                       class="font-semibold text-slate-800 hover:text-indigo-600 text-sm transition-colors">
                        <?= h($sv['patient_name']) ?>
                    </a>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold <?= $sc['bg'] ?> <?= $sc['text'] ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?= $sc['dot'] ?>"></span>
                        <?= ucfirst(str_replace('_',' ',$sv['status'])) ?>
                    </span>
                </div>
                <?php if ($sv['patient_address']): ?>
                <div class="text-xs text-slate-400 truncate"><?= h($sv['patient_address']) ?></div>
                <?php endif; ?>
            </div>
            <?php if ($sv['visit_time']): ?>
            <div class="text-xs font-medium text-slate-500 shrink-0"><?= date('g:i A', strtotime($sv['visit_time'])) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($scheduleTotalToday > 6): ?>
    <div class="px-6 py-3 bg-slate-50 border-t border-slate-100 text-center">
        <a href="<?= BASE_URL ?>/schedule.php" class="text-xs font-semibold text-indigo-600 hover:text-indigo-700">
            +<?= $scheduleTotalToday - 6 ?> more visit<?= ($scheduleTotalToday-6)!==1?'s':'' ?> — View full schedule
        </a>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; // canAccessClinical schedule widget ?>

<!-- ─── Unsigned Forms Alert ────────────────────────────────────── -->
<?php if (canAccessClinical()): ?>
<?php if ($draftCount === 0): ?>
<div class="flex items-center gap-4 bg-emerald-50 border border-emerald-200 rounded-2xl px-6 py-4 mb-7">
    <div class="w-10 h-10 bg-emerald-100 rounded-xl grid place-items-center flex-shrink-0">
        <i class="bi bi-check-circle-fill text-emerald-600 text-xl"></i>
    </div>
    <div>
        <p class="text-sm font-bold text-emerald-800">All Clear — No Unsigned Forms</p>
        <p class="text-xs text-emerald-600 mt-0.5">Every submitted form has a captured signature. Nothing blocked before billing.</p>
    </div>
</div>
<?php else: ?>
<div class="bg-white rounded-2xl shadow-sm border-2 border-rose-200 overflow-hidden mb-7">

    <!-- Header -->
    <div class="flex items-center justify-between gap-3 px-5 py-4 bg-gradient-to-r from-rose-50 to-orange-50 border-b border-rose-100">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-rose-100 rounded-xl grid place-items-center flex-shrink-0">
                <i class="bi bi-pen-fill text-rose-600 text-base"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h3 class="font-bold text-slate-800 text-sm">Unsigned Forms</h3>
                    <span class="inline-flex items-center justify-center w-6 h-6 bg-rose-500 text-white text-xs font-extrabold rounded-full animate-pulse"><?= $draftCount ?></span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Draft<?= $draftCount !== 1 ? 's' : '' ?> missing signature — must be signed before billing</p>
            </div>
        </div>
        <?php if ($draftCount > 5): ?>
        <button id="draftToggleAll"
                class="text-xs font-semibold text-rose-600 hover:text-rose-800 bg-rose-100 hover:bg-rose-200 px-3.5 py-2 rounded-xl transition-colors flex-shrink-0">
            Show all <?= $draftCount ?>
        </button>
        <?php endif; ?>
    </div>

    <!-- Drafts List -->
    <div class="divide-y divide-slate-50" id="draftList">
        <?php foreach ($draftForms as $idx => $dr):
            $fm  = $formMeta[$dr['form_type']] ?? ['label' => $dr['form_type'], 'icon' => 'bi-file', 'bg' => 'bg-slate-100', 'text' => 'text-slate-600'];
            [$ageLabel, $ageText, $ageBg] = draftAge($dr['created_at']);
            $hidden = $idx >= 5 ? 'draft-extra hidden' : '';
        ?>
        <div class="draft-row <?= $hidden ?> flex items-center gap-3 px-5 py-3.5 hover:bg-slate-50/80 transition-colors">
            <!-- Form icon -->
            <span class="<?= $fm['bg'] ?> <?= $fm['text'] ?> p-2 rounded-xl flex-shrink-0">
                <i class="bi <?= $fm['icon'] ?> text-sm"></i>
            </span>

            <!-- Patient + form -->
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $dr['patient_id'] ?>"
                       class="font-bold text-slate-800 hover:text-rose-600 text-sm transition-colors">
                        <?= h($dr['patient_name']) ?>
                    </a>
                    <span class="text-xs text-slate-500"><?= $fm['label'] ?></span>
                </div>
                <div class="flex items-center gap-x-3 gap-y-0.5 flex-wrap mt-0.5">
                    <?php if ($dr['ma_name']): ?>
                    <span class="text-xs text-slate-400"><i class="bi bi-person mr-0.5"></i><?= h($dr['ma_name']) ?></span>
                    <?php endif; ?>
                    <span class="text-xs text-slate-400"><?= date('M j, g:ia', strtotime($dr['created_at'])) ?></span>
                </div>
            </div>

            <!-- Age badge -->
            <span class="<?= $ageBg ?> <?= $ageText ?> text-xs font-bold px-2.5 py-1 rounded-full flex-shrink-0">
                <?= $ageLabel ?>
            </span>

            <!-- Action -->
            <a href="<?= BASE_URL ?>/view_document.php?id=<?= $dr['id'] ?>"
               class="inline-flex items-center gap-1.5 text-xs font-bold text-rose-600 hover:text-rose-800
                      bg-rose-50 hover:bg-rose-100 px-3.5 py-2 rounded-xl transition-colors flex-shrink-0">
                <i class="bi bi-pencil-square"></i> Sign
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($draftCount > 5): ?>
    <!-- Collapsed overflow indicator -->
    <div id="draftSeeMore" class="px-5 py-3 bg-slate-50 border-t border-slate-100 text-center">
        <button onclick="document.querySelectorAll('.draft-extra').forEach(el=>el.classList.remove('hidden'));this.closest('#draftSeeMore').classList.add('hidden');document.getElementById('draftToggleAll').classList.add('hidden');"
                class="text-xs font-semibold text-rose-600 hover:text-rose-800">
            +<?= $draftCount - 5 ?> more unsigned form<?= ($draftCount - 5) !== 1 ? 's' : '' ?> — Show all
        </button>
    </div>
    <?php endif; ?>

</div>
<?php endif; ?>
<?php endif; // canAccessClinical unsigned forms ?>

<!-- Recent Forms -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-bold text-slate-700 flex items-center gap-2">
            <i class="bi bi-clock-history text-blue-500"></i> Recent Forms
        </h3>
        <a href="<?= BASE_URL ?>/patients.php" class="text-xs text-blue-600 hover:text-blue-700 font-semibold">View all →</a>
    </div>
    <?php if (empty($recent)): ?>
    <div class="flex flex-col items-center justify-center py-16 text-slate-400">
        <i class="bi bi-file-earmark-x text-5xl mb-3 opacity-30"></i>
        <p class="text-sm">No forms yet — start by adding a patient.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 text-left">
                    <th class="px-6 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide">Patient</th>
                    <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide">Form</th>
                    <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide hidden sm:table-cell">MA</th>
                    <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide hidden md:table-cell">Date</th>
                    <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($recent as $row):
                    $fm = $formMeta[$row['form_type']] ?? ['label' => $row['form_type'], 'icon' => 'bi-file', 'bg' => 'bg-slate-100', 'text' => 'text-slate-600'];
                    $statusCfg = ['draft' => ['bg' => 'bg-slate-100', 'text' => 'text-slate-600', 'label' => 'Draft'],
                                  'signed' => ['bg' => 'bg-blue-100', 'text' => 'text-blue-700', 'label' => 'Signed'],
                                  'uploaded' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'label' => 'Uploaded']];
                    $sc = $statusCfg[$row['status']] ?? $statusCfg['draft'];
                ?>
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-6 py-4">
                        <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $row['patient_id'] ?>"
                           class="font-semibold text-slate-800 hover:text-blue-600 transition-colors">
                            <?= h($row['patient_name']) ?>
                        </a>
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex items-center gap-2">
                            <span class="<?= $fm['bg'] ?> <?= $fm['text'] ?> p-1.5 rounded-lg">
                                <i class="bi <?= $fm['icon'] ?> text-sm"></i>
                            </span>
                            <span class="text-slate-600 hidden lg:inline"><?= $fm['label'] ?></span>
                        </div>
                    </td>
                    <td class="px-4 py-4 text-slate-500 hidden sm:table-cell"><?= h($row['ma_name'] ?? '—') ?></td>
                    <td class="px-4 py-4 text-slate-500 hidden md:table-cell">
                        <?= date('M j, g:ia', strtotime($row['created_at'])) ?>
                    </td>
                    <td class="px-4 py-4">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold <?= $sc['bg'] ?> <?= $sc['text'] ?>">
                            <?= $sc['label'] ?>
                        </span>
                    </td>
                    <td class="px-4 py-4">
                        <a href="<?= BASE_URL ?>/view_document.php?id=<?= $row['id'] ?>"
                           class="text-blue-600 hover:text-blue-800 font-medium text-xs bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition-colors">
                            View
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php
$extraJs = <<<'JS'
<script>
/* ── Dashboard offline widget ─────────────────────────────── */
(function () {
    function updateWidget(count) {
        var widget    = document.getElementById('offlineDashWidget');
        var countEl   = document.getElementById('offlineDashCount');
        var pluralEl  = document.getElementById('offlineDashPlural');
        var syncBtn   = document.getElementById('dashSyncBtn');
        if (!widget) return;
        if (count > 0) {
            widget.classList.remove('hidden');
            if (countEl)  countEl.textContent  = count;
            if (pluralEl) pluralEl.textContent  = count === 1 ? '' : 's';
            if (syncBtn && navigator.onLine) syncBtn.classList.remove('hidden');
        } else {
            widget.classList.add('hidden');
        }
    }

    function getPendingCount() {
        return new Promise(function (resolve) {
            try {
                var req = indexedDB.open('pd-offline', 1);
                req.onsuccess = function (e) {
                    var db = e.target.result;
                    if (!db.objectStoreNames.contains('form_queue')) { resolve(0); return; }
                    var r = db.transaction('form_queue', 'readonly')
                               .objectStore('form_queue').index('status')
                               .count(IDBKeyRange.only('pending'));
                    r.onsuccess = function () { resolve(r.result); };
                    r.onerror   = function () { resolve(0); };
                };
                req.onerror = function () { resolve(0); };
            } catch (_) { resolve(0); }
        });
    }

    /* Wire the dashboard sync button to offlineSyncBtn in the banner */
    var dashBtn = document.getElementById('dashSyncBtn');
    if (dashBtn) {
        dashBtn.addEventListener('click', function () {
            var bannerBtn = document.getElementById('offlineSyncBtn');
            if (bannerBtn) bannerBtn.click();
            else if (navigator.onLine) location.reload();
        });
    }

    /* Update widget on load and whenever online/offline changes */
    getPendingCount().then(updateWidget);
    window.addEventListener('online',  function () { getPendingCount().then(updateWidget); });
    window.addEventListener('offline', function () { getPendingCount().then(updateWidget); });
    /* Re-check after a sync completes (offline.js dispatches this) */
    window.addEventListener('pd:synced', function () { getPendingCount().then(updateWidget); });
})();
</script>
JS;
include __DIR__ . '/includes/footer.php'; ?>
