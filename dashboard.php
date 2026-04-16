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

// ── Right-sidebar data ────────────────────────────────────────────────────────

// Activity feed: last 18 audit entries (admin sees all, others see own)
if (isAdmin()) {
    $actStmt = $pdo->query("
        SELECT action, target_type, target_id, target_label, username, user_role, created_at, details
        FROM audit_log
        ORDER BY created_at DESC
        LIMIT 18
    ");
} else {
    $actStmt = $pdo->prepare("
        SELECT action, target_type, target_id, target_label, username, user_role, created_at, details
        FROM audit_log
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 18
    ");
    $actStmt->execute([(int)$_SESSION['user_id']]);
}
$activityFeed = $actStmt->fetchAll();

// Staff online: sessions active in last 15 min — approximate via audit_log recent entries
// We track last_active in session but can't query PHP sessions; use audit_log as proxy —
// show staff who have ANY audit event in the last 15 minutes.
$onlineStmt = $pdo->query("
    SELECT a.user_id, a.username, a.user_role, MAX(a.created_at) AS last_seen
    FROM audit_log a
    WHERE a.user_id IS NOT NULL
      AND a.created_at >= NOW() - INTERVAL 15 MINUTE
    GROUP BY a.user_id, a.username, a.user_role
    ORDER BY last_seen DESC
");
$staffOnline = $onlineStmt->fetchAll();

// Admin notes (graceful if table missing)
$adminNotes = [];
try {
    $adminNotes = $pdo->query("
        SELECT n.id, n.body, n.created_at,
               s.full_name AS author_name
        FROM admin_notes n
        LEFT JOIN staff s ON s.id = n.author_id
        WHERE n.pinned = 1
        ORDER BY n.created_at DESC
        LIMIT 20
    ")->fetchAll();
} catch (PDOException $e) { /* table not yet migrated */ }

// CSRF for admin note API calls
$noteCsrf = csrfToken();

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

<div class="flex gap-6 items-start">
<!-- ═══════════════ LEFT / MAIN COLUMN ═══════════════════════════════════ -->
<div class="flex-1 min-w-0">

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

</div><!-- /main column -->

<!-- ═══════════════ RIGHT SIDEBAR ══════════════════════════════════════════ -->
<div class="hidden lg:block w-[300px] shrink-0 space-y-5">

    <?php
    // ── Action icons for audit entries ────────────────────────────────────
    $actIconMap = [
        'login'              => ['bi-box-arrow-in-right', 'bg-emerald-100', 'text-emerald-600'],
        'login_fail'         => ['bi-shield-exclamation', 'bg-red-100',     'text-red-600'],
        'logout'             => ['bi-box-arrow-right',    'bg-slate-100',   'text-slate-500'],
        'patient_view'       => ['bi-person-lines-fill',  'bg-blue-100',    'text-blue-600'],
        'patient_add'        => ['bi-person-plus-fill',   'bg-indigo-100',  'text-indigo-600'],
        'patient_edit'       => ['bi-pencil-square',      'bg-violet-100',  'text-violet-600'],
        'patient_status'     => ['bi-toggle2-on',         'bg-amber-100',   'text-amber-600'],
        'form_create'        => ['bi-file-earmark-plus',  'bg-sky-100',     'text-sky-600'],
        'form_sign'          => ['bi-pen-fill',           'bg-teal-100',    'text-teal-600'],
        'form_upload'        => ['bi-cloud-arrow-up-fill','bg-cyan-100',    'text-cyan-600'],
        'photo_upload'       => ['bi-camera-fill',        'bg-pink-100',    'text-pink-600'],
        'care_note_add'      => ['bi-chat-square-text',   'bg-teal-100',    'text-teal-600'],
        'care_note_delete'   => ['bi-chat-square-x',      'bg-red-100',     'text-red-500'],
        'admin_note_add'     => ['bi-sticky-fill',        'bg-yellow-100',  'text-yellow-600'],
        'admin_note_delete'  => ['bi-trash3',             'bg-slate-100',   'text-slate-500'],
        'soap_save'          => ['bi-file-medical',       'bg-emerald-100', 'text-emerald-600'],
    ];
    function actIcon(string $action, array $map): array {
        if (isset($map[$action])) return $map[$action];
        // fuzzy prefix match
        foreach ($map as $k => $v) {
            if (str_starts_with($action, $k)) return $v;
        }
        return ['bi-activity', 'bg-slate-100', 'text-slate-500'];
    }
    function actLabel(string $action): string {
        static $labels = [
            'login' => 'Signed in', 'login_fail' => 'Failed login',
            'logout' => 'Signed out', 'patient_view' => 'Viewed patient',
            'patient_add' => 'Added patient', 'patient_edit' => 'Edited patient',
            'patient_status' => 'Status change', 'form_create' => 'Form created',
            'form_sign' => 'Form signed', 'form_upload' => 'Form uploaded',
            'photo_upload' => 'Photo uploaded', 'care_note_add' => 'Care note posted',
            'care_note_delete' => 'Care note deleted', 'admin_note_add' => 'Admin note posted',
            'admin_note_delete' => 'Admin note deleted', 'soap_save' => 'SOAP note saved',
        ];
        return $labels[$action] ?? ucwords(str_replace('_', ' ', $action));
    }
    function shortAgo(string $ts): string {
        $d = time() - strtotime($ts);
        if ($d < 60)    return 'just now';
        if ($d < 3600)  return (int)($d/60).'m ago';
        if ($d < 86400) return (int)($d/3600).'h ago';
        return (int)($d/86400).'d ago';
    }
    ?>

    <!-- ── Activity Feed ─────────────────────────────────────────────── -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-4 py-3.5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-700 text-sm flex items-center gap-2">
                <span class="w-6 h-6 bg-blue-100 rounded-lg grid place-items-center flex-shrink-0">
                    <i class="bi bi-activity text-blue-600 text-xs"></i>
                </span>
                Activity Feed
            </h3>
            <?php if (isAdmin()): ?>
            <span class="text-xs text-slate-400">All staff</span>
            <?php endif; ?>
        </div>
        <?php if (empty($activityFeed)): ?>
        <div class="px-4 py-6 text-center text-slate-400 text-xs">No recent activity</div>
        <?php else: ?>
        <ul class="divide-y divide-slate-50 max-h-[420px] overflow-y-auto">
            <?php foreach ($activityFeed as $ev):
                [$ico, $ibg, $itxt] = actIcon($ev['action'], $actIconMap);
            ?>
            <li class="flex items-start gap-2.5 px-4 py-3 hover:bg-slate-50/60 transition-colors">
                <span class="<?= $ibg ?> <?= $itxt ?> w-7 h-7 rounded-lg grid place-items-center flex-shrink-0 mt-0.5">
                    <i class="bi <?= $ico ?> text-xs"></i>
                </span>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-slate-700 leading-tight">
                        <?= actLabel($ev['action']) ?>
                        <?php if ($ev['target_label']): ?>
                        <span class="font-normal text-slate-500">— <?= h(mb_strimwidth($ev['target_label'], 0, 28, '…')) ?></span>
                        <?php endif; ?>
                    </p>
                    <p class="text-[10px] text-slate-400 mt-0.5">
                        <?= h($ev['username'] ?? 'system') ?>
                        <span class="mx-1">·</span>
                        <?= shortAgo($ev['created_at']) ?>
                    </p>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>

    <!-- ── Staff Online ─────────────────────────────────────────────── -->
    <?php if (isAdmin()): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-4 py-3.5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-700 text-sm flex items-center gap-2">
                <span class="w-6 h-6 bg-emerald-100 rounded-lg grid place-items-center flex-shrink-0">
                    <i class="bi bi-circle-fill text-emerald-500 text-[8px]"></i>
                </span>
                Staff Online
            </h3>
            <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full">
                last 15 min
            </span>
        </div>
        <?php
        $roleColors = [
            'admin'   => ['bg-indigo-100', 'text-indigo-700'],
            'ma'      => ['bg-blue-100',   'text-blue-700'],
            'billing' => ['bg-amber-100',  'text-amber-700'],
        ];
        ?>
        <?php if (empty($staffOnline)): ?>
        <div class="px-4 py-5 text-center text-slate-400 text-xs">No recent activity</div>
        <?php else: ?>
        <ul class="divide-y divide-slate-50">
            <?php foreach ($staffOnline as $su):
                [$rbg, $rtxt] = $roleColors[$su['user_role']] ?? ['bg-slate-100', 'text-slate-600'];
                $initials = strtoupper(substr($su['username'] ?? '?', 0, 2));
            ?>
            <li class="flex items-center gap-3 px-4 py-3">
                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-400 to-blue-500
                            flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                    <?= h($initials) ?>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-slate-700 truncate"><?= h($su['username']) ?></p>
                    <p class="text-[10px] text-slate-400">last seen <?= shortAgo($su['last_seen']) ?></p>
                </div>
                <span class="<?= $rbg ?> <?= $rtxt ?> text-[10px] font-bold px-2 py-0.5 rounded-full capitalize flex-shrink-0">
                    <?= h($su['user_role']) ?>
                </span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── Pinned Admin Notes ────────────────────────────────────────── -->
    <?php if (isAdmin()): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden" id="adminNotesWidget">
        <div class="px-4 py-3.5 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-700 text-sm flex items-center gap-2">
                <span class="w-6 h-6 bg-yellow-100 rounded-lg grid place-items-center flex-shrink-0">
                    <i class="bi bi-sticky-fill text-yellow-500 text-xs"></i>
                </span>
                Team Notes
            </h3>
            <button onclick="document.getElementById('noteCompose').classList.toggle('hidden')"
                    class="w-6 h-6 rounded-lg bg-slate-100 hover:bg-slate-200 grid place-items-center transition-colors">
                <i class="bi bi-plus-lg text-slate-600 text-xs"></i>
            </button>
        </div>

        <!-- Compose -->
        <div id="noteCompose" class="hidden px-4 py-3 border-b border-slate-100 bg-yellow-50/60">
            <textarea id="noteBody" rows="3" maxlength="1000" placeholder="Write a note for the team…"
                      class="w-full text-xs border border-yellow-200 rounded-xl px-3 py-2.5 bg-white
                             focus:outline-none focus:ring-2 focus:ring-yellow-300 resize-none placeholder-slate-400"></textarea>
            <div class="flex items-center justify-between mt-2">
                <span class="text-[10px] text-slate-400"><span id="noteCharCount">0</span>/1000</span>
                <div class="flex gap-2">
                    <button onclick="document.getElementById('noteCompose').classList.add('hidden')"
                            class="text-xs text-slate-500 hover:text-slate-700 px-3 py-1.5 rounded-lg hover:bg-slate-100 transition-colors">
                        Cancel
                    </button>
                    <button id="notePostBtn" onclick="postAdminNote()"
                            class="text-xs font-bold text-white bg-yellow-500 hover:bg-yellow-600 px-3 py-1.5 rounded-lg transition-colors flex items-center gap-1.5">
                        <i class="bi bi-sticky-fill"></i> Post
                    </button>
                </div>
            </div>
        </div>

        <!-- Notes list -->
        <ul id="notesList" class="divide-y divide-slate-50 max-h-[360px] overflow-y-auto">
            <?php if (empty($adminNotes)): ?>
            <li class="px-4 py-6 text-center text-slate-400 text-xs" id="notesEmpty">
                No notes yet — post the first one above.
            </li>
            <?php else: ?>
            <?php foreach ($adminNotes as $note): ?>
            <li class="note-item group flex items-start gap-2.5 px-4 py-3 hover:bg-yellow-50/40 transition-colors"
                data-id="<?= (int)$note['id'] ?>">
                <i class="bi bi-sticky-fill text-yellow-400 text-xs mt-0.5 flex-shrink-0"></i>
                <div class="flex-1 min-w-0">
                    <p class="text-xs text-slate-700 whitespace-pre-wrap break-words"><?= h($note['body']) ?></p>
                    <p class="text-[10px] text-slate-400 mt-1">
                        <?= h($note['author_name'] ?? '—') ?> · <?= shortAgo($note['created_at']) ?>
                    </p>
                </div>
                <button onclick="deleteAdminNote(<?= (int)$note['id'] ?>, this)"
                        class="opacity-0 group-hover:opacity-100 shrink-0 text-slate-300 hover:text-red-500 transition-all mt-0.5">
                    <i class="bi bi-x-lg text-[10px]"></i>
                </button>
            </li>
            <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>
    <?php endif; // isAdmin admin notes ?>

</div><!-- /sidebar -->
</div><!-- /flex grid -->

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

// Admin Notes JS (only for admins — note: PHP is evaluated before heredoc content, so we append inline)
if (isAdmin()):
$noteCsrfJs = json_encode($noteCsrf);
$baseUrlJs  = json_encode(BASE_URL);
$extraJs .= <<<NOTEJS
<script>
(function () {
    var CSRF    = {$noteCsrfJs};
    var BASE    = {$baseUrlJs};

    /* Char counter */
    var bodyEl = document.getElementById('noteBody');
    if (bodyEl) {
        bodyEl.addEventListener('input', function () {
            var el = document.getElementById('noteCharCount');
            if (el) el.textContent = bodyEl.value.length;
        });
    }

    window.postAdminNote = async function () {
        var body = (bodyEl ? bodyEl.value.trim() : '');
        if (!body) return;
        var btn = document.getElementById('notePostBtn');
        if (btn) { btn.disabled = true; btn.innerHTML = '…'; }
        try {
            var r    = await fetch(BASE + '/api/save_admin_note.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({csrf: CSRF, action: 'create', body: body}),
            });
            var data = await r.json();
            if (!data.ok) { alert(data.error || 'Error'); return; }

            /* Prepend new note to list */
            var list  = document.getElementById('notesList');
            var empty = document.getElementById('notesEmpty');
            if (empty) empty.remove();
            var li = document.createElement('li');
            li.className = 'note-item group flex items-start gap-2.5 px-4 py-3 hover:bg-yellow-50/40 transition-colors';
            li.dataset.id = data.id;
            li.innerHTML =
                '<i class="bi bi-sticky-fill text-yellow-400 text-xs mt-0.5 flex-shrink-0"></i>' +
                '<div class="flex-1 min-w-0">' +
                  '<p class="text-xs text-slate-700 whitespace-pre-wrap break-words">' + escHtml(body) + '</p>' +
                  '<p class="text-[10px] text-slate-400 mt-1">you · just now</p>' +
                '</div>' +
                '<button onclick="deleteAdminNote(' + data.id + ', this)" ' +
                  'class="opacity-0 group-hover:opacity-100 shrink-0 text-slate-300 hover:text-red-500 transition-all mt-0.5">' +
                  '<i class="bi bi-x-lg text-[10px]"></i></button>';
            list.insertBefore(li, list.firstChild);

            /* Reset compose */
            if (bodyEl) bodyEl.value = '';
            var cc = document.getElementById('noteCharCount');
            if (cc) cc.textContent = '0';
            document.getElementById('noteCompose').classList.add('hidden');
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-sticky-fill"></i> Post'; }
        }
    };

    window.deleteAdminNote = async function (id, btn) {
        if (!confirm('Delete this note?')) return;
        btn.disabled = true;
        try {
            var r    = await fetch(BASE + '/api/save_admin_note.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({csrf: CSRF, action: 'delete', note_id: id}),
            });
            var data = await r.json();
            if (!data.ok) { alert(data.error || 'Error'); return; }
            var li = document.querySelector('.note-item[data-id="' + id + '"]');
            if (li) li.remove();
            /* If list is now empty, show placeholder */
            var list = document.getElementById('notesList');
            if (list && list.querySelectorAll('.note-item').length === 0) {
                var empty = document.createElement('li');
                empty.id = 'notesEmpty';
                empty.className = 'px-4 py-6 text-center text-slate-400 text-xs';
                empty.textContent = 'No notes yet — post the first one above.';
                list.appendChild(empty);
            }
        } finally { btn.disabled = false; }
    };

    function escHtml(s) {
        return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    }
})();
</script>
NOTEJS;
endif;

include __DIR__ . '/includes/footer.php'; ?>
