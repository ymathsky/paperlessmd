<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();
if (!isAdmin() && !isMa()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}
$_isMaView = isMa();

/* ── Form type labels ─────────────────────────────────────── */
$formLabels = [
    'vital_cs'               => 'Vital CS Consent',
    'new_patient'            => 'New Patient Consent',
    'new_patient_pocket'     => 'New Patient Pocket',
    'new_patient_pocket_pc'  => 'New Patient Pocket (PC)',
    'abn'                    => 'ABN',
    'pf_signup'              => 'PF Portal Signup',
    'ccm_consent'            => 'CCM Consent',
    'wound_care_consent'     => 'Wound Care Consent',
    'informed_consent_wound' => 'Informed Consent – Wound Care',
    'rpm_consent'            => 'RPM Consent',
    'medicare_awv'           => 'Medicare AWV',
    'cognitive_wellness'     => 'Cognitive Wellness',
    'il_disclosure'          => 'IL Disclosure',
];

$statusLabels = [
    'draft'    => ['label' => 'Draft',       'bg' => 'bg-slate-100',   'text' => 'text-slate-600'],
    'signed'   => ['label' => 'Signed',      'bg' => 'bg-blue-100',    'text' => 'text-blue-700'],
    'uploaded' => ['label' => 'Uploaded',    'bg' => 'bg-emerald-100', 'text' => 'text-emerald-700'],
];

/* ── Filters ─────────────────────────────────────────────── */
$fType   = $_GET['type']   ?? '';
$fStatus = $_GET['status'] ?? '';
$fFrom   = $_GET['from']   ?? '';
$fTo     = $_GET['to']     ?? '';
$fMa     = $_GET['ma']     ?? '';
$fQ      = trim($_GET['q'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

/* ── Build WHERE ─────────────────────────────────────────── */
$where  = [];
$params = [];

if ($fType !== '') {
    $where[]  = 'fs.form_type = ?';
    $params[] = $fType;
}
if ($fStatus !== '') {
    $where[]  = 'fs.status = ?';
    $params[] = $fStatus;
}
if ($fFrom !== '') {
    $where[]  = 'DATE(fs.created_at) >= ?';
    $params[] = $fFrom;
}
if ($fTo !== '') {
    $where[]  = 'DATE(fs.created_at) <= ?';
    $params[] = $fTo;
}
if ($fMa !== '') {
    $where[]  = 'fs.ma_id = ?';
    $params[] = (int)$fMa;
}
if ($fQ !== '') {
    $like     = '%' . $fQ . '%';
    $where[]  = '(p.first_name LIKE ? OR p.last_name LIKE ? OR CONCAT(p.first_name," ",p.last_name) LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

// MA users: restrict to forms for their assigned patients only
if ($_isMaView) {
    $where[]  = 'p.assigned_ma = ?';
    $params[] = (int)$_SESSION['user_id'];
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ── Count ───────────────────────────────────────────────── */
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM form_submissions fs
    JOIN patients p ON p.id = fs.patient_id
    LEFT JOIN staff s ON s.id = fs.ma_id
    $whereSQL
");
$countStmt->execute($params);
$totalRows  = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

/* ── CSV Export (before HTML) ────────────────────────────── */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $expStmt = $pdo->prepare("
        SELECT fs.id, fs.created_at, fs.form_type, fs.status,
               p.first_name, p.last_name, p.dob,
               s.full_name AS ma_name,
               fs.provider_name,
               CASE WHEN fs.patient_signature IS NOT NULL AND fs.patient_signature != '' THEN 'Yes' ELSE 'No' END AS patient_signed,
               CASE WHEN fs.provider_signature IS NOT NULL AND fs.provider_signature != '' THEN 'Yes' ELSE 'No' END AS provider_signed
        FROM form_submissions fs
        JOIN patients p ON p.id = fs.patient_id
        LEFT JOIN staff s ON s.id = fs.ma_id
        $whereSQL
        ORDER BY fs.created_at DESC, fs.id DESC
    ");
    $expStmt->execute($params);
    $expRows = $expStmt->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="all_forms_' . date('Ymd_His') . '.csv"');
    header('Pragma: no-cache');
    $fh = fopen('php://output', 'w');
    fputcsv($fh, ['ID', 'Date', 'Patient', 'DOB', 'Form Type', 'Status', 'Collected By', 'Provider', 'Patient Signed', 'Provider Signed']);
    foreach ($expRows as $r) {
        fputcsv($fh, [
            $r['id'],
            $r['created_at'],
            $r['first_name'] . ' ' . $r['last_name'],
            $r['dob'],
            $formLabels[$r['form_type']] ?? $r['form_type'],
            $r['status'],
            $r['ma_name'] ?? '',
            $r['provider_name'] ?? '',
            $r['patient_signed'],
            $r['provider_signed'],
        ]);
    }
    fclose($fh);
    exit;
}

/* ── Rows ────────────────────────────────────────────────── */
$rowStmt = $pdo->prepare("
    SELECT fs.id, fs.created_at, fs.form_type, fs.status,
           p.id AS patient_id, p.first_name, p.last_name, p.dob,
           s.full_name AS ma_name,
           fs.provider_name,
           CASE WHEN fs.patient_signature IS NOT NULL AND fs.patient_signature != '' THEN 1 ELSE 0 END AS has_patient_sig,
           CASE WHEN fs.provider_signature IS NOT NULL AND fs.provider_signature != '' THEN 1 ELSE 0 END AS has_provider_sig
    FROM form_submissions fs
    JOIN patients p ON p.id = fs.patient_id
    LEFT JOIN staff s ON s.id = fs.ma_id
    $whereSQL
    ORDER BY fs.created_at DESC, fs.id DESC
    LIMIT $perPage OFFSET $offset
");
$rowStmt->execute($params);
$rows = $rowStmt->fetchAll();

/* ── MA list for filter dropdown ─────────────────────────── */
$maList = $pdo->query("
    SELECT DISTINCT s.id, s.full_name
    FROM staff s
    INNER JOIN form_submissions fs ON fs.ma_id = s.id
    ORDER BY s.full_name
")->fetchAll();

/* ── Form types present in DB ────────────────────────────── */
$typesInDb = $pdo->query("SELECT DISTINCT form_type FROM form_submissions ORDER BY form_type")->fetchAll(PDO::FETCH_COLUMN);

/* ── Pagination helper ───────────────────────────────────── */
function pgUrl(array $params, int $pg): string {
    $p = array_merge($params, ['page' => $pg]);
    unset($p['export']);
    return '?' . http_build_query(array_filter($p, fn($v) => $v !== ''));
}

$pageTitle = 'All Forms';
$activeNav = 'all_forms';
include __DIR__ . '/../includes/header.php';
?>

<!-- Header -->
<div class="flex flex-wrap items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-slate-800 dark:text-slate-100 flex items-center gap-3">
            <i class="bi bi-folder2-open text-indigo-600"></i> All Forms
        </h1>
        <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">
            <?= $_isMaView ? 'Forms for your assigned patients' : 'All submitted forms across all patients' ?>
        </p>
    </div>
    <a href="<?= '?' . http_build_query(array_filter(['type'=>$fType,'status'=>$fStatus,'from'=>$fFrom,'to'=>$fTo,'ma'=>$fMa,'q'=>$fQ], fn($v)=>$v!=='')) . '&export=csv' ?>"
       class="inline-flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white
              text-sm font-semibold rounded-xl transition-all shadow-sm">
        <i class="bi bi-download"></i> Export CSV
    </a>
</div>

<!-- Filters -->
<form method="get" class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700
                           shadow-sm px-5 py-4 mb-6 flex flex-wrap gap-3 items-end">
    <!-- Patient search -->
    <div class="flex-1 min-w-[160px]">
        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Patient</label>
        <input type="text" name="q" value="<?= htmlspecialchars($fQ) ?>"
               placeholder="Search patient name…"
               class="w-full px-3 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-sm
                      bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100
                      focus:outline-none focus:ring-2 focus:ring-indigo-400">
    </div>
    <!-- Form type -->
    <div class="min-w-[170px]">
        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Form Type</label>
        <select name="type"
                class="w-full px-3 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-sm
                       bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100
                       focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <option value="">All Types</option>
            <?php foreach ($typesInDb as $t): ?>
            <option value="<?= h($t) ?>" <?= $fType === $t ? 'selected' : '' ?>>
                <?= h($formLabels[$t] ?? $t) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <!-- Status -->
    <div class="min-w-[130px]">
        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Status</label>
        <select name="status"
                class="w-full px-3 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-sm
                       bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100
                       focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <option value="">All Statuses</option>
            <option value="draft"    <?= $fStatus==='draft'    ? 'selected':'' ?>>Draft</option>
            <option value="signed"   <?= $fStatus==='signed'   ? 'selected':'' ?>>Signed</option>
            <option value="uploaded" <?= $fStatus==='uploaded' ? 'selected':'' ?>>Uploaded to PF</option>
        </select>
    </div>
    <!-- Collected by (MA) — admin only -->
    <?php if (!$_isMaView): ?>
    <div class="min-w-[160px]">
        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">Collected By</label>
        <select name="ma"
                class="w-full px-3 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-sm
                       bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100
                       focus:outline-none focus:ring-2 focus:ring-indigo-400">
            <option value="">All Staff</option>
            <?php foreach ($maList as $m): ?>
            <option value="<?= (int)$m['id'] ?>" <?= (int)$fMa === (int)$m['id'] ? 'selected' : '' ?>>
                <?= h($m['full_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <!-- Date from -->
    <div class="min-w-[140px]">
        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">From</label>
        <input type="date" name="from" value="<?= h($fFrom) ?>"
               class="w-full px-3 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-sm
                      bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100
                      focus:outline-none focus:ring-2 focus:ring-indigo-400">
    </div>
    <!-- Date to -->
    <div class="min-w-[140px]">
        <label class="block text-xs font-semibold text-slate-500 dark:text-slate-400 mb-1">To</label>
        <input type="date" name="to" value="<?= h($fTo) ?>"
               class="w-full px-3 py-2 border border-slate-200 dark:border-slate-600 rounded-xl text-sm
                      bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-100
                      focus:outline-none focus:ring-2 focus:ring-indigo-400">
    </div>
    <!-- Buttons -->
    <div class="flex gap-2">
        <button type="submit"
                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-xl transition-all">
            <i class="bi bi-funnel-fill mr-1"></i> Filter
        </button>
        <a href="?" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600
                           text-slate-700 dark:text-slate-200 text-sm font-semibold rounded-xl transition-all">
            Clear
        </a>
    </div>
</form>

<!-- Results count -->
<div class="flex items-center justify-between mb-3 text-sm text-slate-500 dark:text-slate-400">
    <span>
        <?php if ($totalRows === 0): ?>
            No forms found
        <?php else: ?>
            Showing <?= number_format(($page-1)*$perPage + 1) ?>–<?= number_format(min($page*$perPage, $totalRows)) ?>
            of <strong class="text-slate-700 dark:text-slate-200"><?= number_format($totalRows) ?></strong> form<?= $totalRows !== 1 ? 's' : '' ?>
        <?php endif; ?>
    </span>
</div>

<!-- Table -->
<div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
<?php if (empty($rows)): ?>
    <div class="flex flex-col items-center justify-center py-20 text-slate-400">
        <i class="bi bi-folder2-open text-5xl mb-3 opacity-30"></i>
        <p class="font-medium">No forms match your filters</p>
    </div>
<?php else: ?>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40">
                <th class="text-left px-4 py-3 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Date</th>
                <th class="text-left px-4 py-3 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Patient</th>
                <th class="text-left px-4 py-3 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Form Type</th>
                <th class="text-left px-4 py-3 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Collected By</th>
                <th class="text-left px-4 py-3 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Provider</th>
                <th class="text-left px-4 py-3 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Status</th>
                <th class="text-left px-4 py-3 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wide">Signatures</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
        <?php foreach ($rows as $r):
            $sc  = $statusLabels[$r['status']] ?? $statusLabels['draft'];
            $lbl = $formLabels[$r['form_type']] ?? $r['form_type'];
            $dt  = new DateTime($r['created_at']);
            $isToday = $dt->format('Y-m-d') === date('Y-m-d');
        ?>
        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition-colors">
            <!-- Date -->
            <td class="px-4 py-3 whitespace-nowrap">
                <div class="font-medium text-slate-800 dark:text-slate-200">
                    <?= $dt->format('M j, Y') ?>
                </div>
                <?php if ($isToday): ?>
                <div class="text-xs text-emerald-600 font-semibold">Today</div>
                <?php else: ?>
                <div class="text-xs text-slate-400"><?= $dt->format('g:i a') ?></div>
                <?php endif; ?>
            </td>
            <!-- Patient -->
            <td class="px-4 py-3">
                <a href="<?= BASE_URL ?>/patient_view.php?id=<?= (int)$r['patient_id'] ?>"
                   class="font-semibold text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                    <?= h($r['first_name'] . ' ' . $r['last_name']) ?>
                </a>
                <?php if ($r['dob']): ?>
                <div class="text-xs text-slate-400">DOB: <?= h(date('m/d/Y', strtotime($r['dob']))) ?></div>
                <?php endif; ?>
            </td>
            <!-- Form type -->
            <td class="px-4 py-3">
                <span class="text-slate-700 dark:text-slate-300 font-medium"><?= h($lbl) ?></span>
            </td>
            <!-- MA -->
            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                <?= h($r['ma_name'] ?? '—') ?>
            </td>
            <!-- Provider -->
            <td class="px-4 py-3 text-slate-600 dark:text-slate-400">
                <?= $r['provider_name'] ? h($r['provider_name']) : '<span class="text-slate-300 dark:text-slate-600">—</span>' ?>
            </td>
            <!-- Status -->
            <td class="px-4 py-3">
                <span class="<?= $sc['bg'] ?> <?= $sc['text'] ?> text-xs font-bold px-2.5 py-1 rounded-full">
                    <?= $sc['label'] ?>
                </span>
            </td>
            <!-- Signatures -->
            <td class="px-4 py-3">
                <div class="flex items-center gap-1.5">
                    <span title="Patient signature"
                          class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full
                                 <?= $r['has_patient_sig'] ? 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-500' ?>">
                        <i class="bi bi-person-fill text-[10px]"></i>
                        <?= $r['has_patient_sig'] ? 'Pt' : 'Pt' ?>
                    </span>
                    <span title="Provider signature"
                          class="inline-flex items-center gap-1 text-xs font-medium px-2 py-0.5 rounded-full
                                 <?= $r['has_provider_sig'] ? 'bg-violet-50 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400' : 'bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-500' ?>">
                        <i class="bi bi-person-badge-fill text-[10px]"></i>
                        MD
                    </span>
                </div>
            </td>
            <!-- Actions -->
            <td class="px-4 py-3 text-right whitespace-nowrap">
                <a href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$r['id'] ?>"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold
                          bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-400
                          rounded-lg transition-colors">
                    <i class="bi bi-eye-fill"></i> View
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
<?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="flex items-center justify-center gap-2 mt-6">
    <?php if ($page > 1): ?>
    <a href="<?= pgUrl(['type'=>$fType,'status'=>$fStatus,'from'=>$fFrom,'to'=>$fTo,'ma'=>$fMa,'q'=>$fQ], $page-1) ?>"
       class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700
              text-slate-600 dark:text-slate-300 text-sm font-medium rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
        ← Prev
    </a>
    <?php endif; ?>

    <span class="text-sm text-slate-500 dark:text-slate-400 px-3">
        Page <?= $page ?> of <?= $totalPages ?>
    </span>

    <?php if ($page < $totalPages): ?>
    <a href="<?= pgUrl(['type'=>$fType,'status'=>$fStatus,'from'=>$fFrom,'to'=>$fTo,'ma'=>$fMa,'q'=>$fQ], $page+1) ?>"
       class="px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700
              text-slate-600 dark:text-slate-300 text-sm font-medium rounded-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
        Next →
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
