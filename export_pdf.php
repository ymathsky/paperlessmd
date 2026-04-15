<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/audit.php';
requireLogin();

/* ── Parameters ──────────────────────────────────────────── */
$patientId = (int)($_GET['patient_id'] ?? 0);
$date      = $_GET['date'] ?? '';          // YYYY-MM-DD  — filter to a single visit day
$idsRaw    = $_GET['ids']  ?? '';          // comma-list of form_submission IDs

if (!$patientId) { header('Location: ' . BASE_URL . '/patients.php'); exit; }

/* ── Patient ─────────────────────────────────────────────── */
$pStmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$pStmt->execute([$patientId]);
$patient = $pStmt->fetch();
if (!$patient) { header('Location: ' . BASE_URL . '/patients.php'); exit; }

auditLog($pdo, 'form_export', 'patient', $patientId,
    $patient['first_name'] . ' ' . $patient['last_name'],
    'date=' . $date . ' ids=' . $idsRaw);

/* ── Forms ───────────────────────────────────────────────── */
if ($idsRaw !== '') {
    // Specific IDs — split, sanitise, query
    $ids = array_filter(array_map('intval', explode(',', $idsRaw)));
    if (empty($ids)) { header('Location: ' . BASE_URL . '/patient_view.php?id=' . $patientId); exit; }
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $fStmt = $pdo->prepare("
        SELECT fs.*, s.full_name AS ma_name
        FROM form_submissions fs
        LEFT JOIN staff s ON s.id = fs.ma_id
        WHERE fs.id IN ($placeholders) AND fs.patient_id = ?
        ORDER BY fs.created_at ASC
    ");
    $fStmt->execute([...$ids, $patientId]);
} elseif ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $fStmt = $pdo->prepare("
        SELECT fs.*, s.full_name AS ma_name
        FROM form_submissions fs
        LEFT JOIN staff s ON s.id = fs.ma_id
        WHERE fs.patient_id = ? AND DATE(fs.created_at) = ?
        ORDER BY fs.created_at ASC
    ");
    $fStmt->execute([$patientId, $date]);
} else {
    $fStmt = $pdo->prepare("
        SELECT fs.*, s.full_name AS ma_name
        FROM form_submissions fs
        LEFT JOIN staff s ON s.id = fs.ma_id
        WHERE fs.patient_id = ?
        ORDER BY fs.created_at ASC
    ");
    $fStmt->execute([$patientId]);
}

$forms = $fStmt->fetchAll();

if (empty($forms)) {
    header('Location: ' . BASE_URL . '/patient_view.php?id=' . $patientId . '&msg=no_forms');
    exit;
}

/* ── Metadata ────────────────────────────────────────────── */
$patientName = $patient['first_name'] . ' ' . $patient['last_name'];
$exportDate  = $date ?: date('Y-m-d', strtotime($forms[0]['created_at']));
$exportLabel = $date
    ? date('F j, Y', strtotime($date)) . ' Visit'
    : 'All Forms';
$formCount   = count($forms);
$backUrl     = $date
    ? BASE_URL . '/patient_timeline.php?id=' . $patientId
    : BASE_URL . '/patient_view.php?id=' . $patientId;
$pdfFilename = preg_replace('/[^A-Za-z0-9_-]/', '_', $patientName)
             . '_' . $exportDate . '.pdf';

/* ── Form definitions ────────────────────────────────────── */
$formDefs = [
    'vital_cs'           => ['label' => 'Visit Consent',           'color' => '#dc2626'],
    'new_patient'        => ['label' => 'New Patient Consent',     'color' => '#2563eb'],
    'abn'                => ['label' => 'ABN (CMS-R-131)',          'color' => '#d97706'],
    'pf_signup'          => ['label' => 'PF Portal Consent',        'color' => '#0891b2'],
    'ccm_consent'        => ['label' => 'CCM Consent',              'color' => '#059669'],
    'cognitive_wellness' => ['label' => 'Cognitive Wellness Exam',  'color' => '#7c3aed'],
    'medicare_awv'       => ['label' => 'Medicare AWV',             'color' => '#0284c7'],
    'il_disclosure'      => ['label' => 'IL Disclosure Auth.',       'color' => '#475569'],
];
$statusLabel = ['draft' => 'Draft', 'signed' => 'Signed', 'uploaded' => 'Uploaded to PF'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($patientName) ?> — <?= h($exportLabel) ?> — <?= APP_NAME ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter','system-ui','sans-serif'] } } } }</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<style>
  @media print {
    .no-print { display: none !important; }
    .page-break { page-break-after: always; break-after: page; }
    body { background: white !important; }
    .form-card { box-shadow: none !important; border: 1px solid #e2e8f0 !important; border-radius: 8px !important; }
  }
  @media screen {
    .page-break { border-bottom: 3px dashed #e2e8f0; margin-bottom: 2.5rem; padding-bottom: 2.5rem; }
    .page-break:last-child { border-bottom: none; }
  }
</style>
</head>
<body class="bg-slate-100 font-sans min-h-screen">

<!-- ── Action Bar (no-print) ─────────────────────────────── -->
<div class="no-print sticky top-0 z-50 bg-white border-b border-slate-200 shadow-sm">
    <div class="max-w-4xl mx-auto px-4 py-3 flex flex-wrap items-center gap-3">
        <!-- Back -->
        <a href="<?= $backUrl ?>"
           class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-slate-700
                  bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
            <i class="bi bi-arrow-left"></i> Back
        </a>

        <!-- Info -->
        <div class="flex-1 min-w-0">
            <p class="text-sm font-bold text-slate-800 truncate"><?= h($patientName) ?></p>
            <p class="text-xs text-slate-500"><?= h($exportLabel) ?> &mdash; <?= $formCount ?> form<?= $formCount !== 1 ? 's' : '' ?></p>
        </div>

        <!-- Print -->
        <button onclick="window.print()"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold text-slate-700
                       bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
            <i class="bi bi-printer-fill"></i> Print / Fax
        </button>

        <!-- Download PDF -->
        <button id="downloadBtn"
                class="inline-flex items-center gap-2 px-5 py-2 text-sm font-bold text-white
                       bg-blue-600 hover:bg-blue-700 rounded-xl transition-colors shadow-sm">
            <i class="bi bi-file-earmark-pdf-fill"></i>
            <span id="downloadLabel">Download PDF</span>
        </button>
    </div>
</div>

<!-- ── Document Content ───────────────────────────────────── -->
<div class="max-w-4xl mx-auto px-4 py-8">

    <!-- Cover banner -->
    <div id="exportContent">

    <div class="bg-gradient-to-r from-blue-900 to-blue-700 rounded-2xl p-6 mb-8 text-white form-card">
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <p class="text-blue-300 text-xs font-semibold uppercase tracking-widest mb-1"><?= h(APP_NAME) ?></p>
                <h1 class="text-2xl font-extrabold"><?= h($patientName) ?></h1>
                <p class="text-blue-200 mt-1 text-sm"><?= h($exportLabel) ?></p>
            </div>
            <div class="text-right text-sm text-blue-200 space-y-1">
                <p class="font-bold text-white"><?= h(PRACTICE_NAME) ?></p>
                <p><?= h(PRACTICE_ADDRESS) ?></p>
                <p>Ph: <?= h(PRACTICE_PHONE) ?> &nbsp;|&nbsp; Fax: <?= h(PRACTICE_FAX) ?></p>
                <p class="text-blue-300 text-xs mt-2">Generated <?= date('F j, Y \a\t g:i a') ?></p>
            </div>
        </div>

        <!-- Patient meta strip -->
        <div class="mt-5 pt-5 border-t border-blue-600/50 flex flex-wrap gap-x-6 gap-y-2 text-sm">
            <?php if ($patient['dob']): ?>
            <span><span class="text-blue-300">DOB </span><?= date('M j, Y', strtotime($patient['dob'])) ?></span>
            <?php endif; ?>
            <?php if ($patient['phone']): ?>
            <span><span class="text-blue-300">Phone </span><?= h($patient['phone']) ?></span>
            <?php endif; ?>
            <?php if ($patient['insurance']): ?>
            <span><span class="text-blue-300">Insurance </span><?= h($patient['insurance']) ?></span>
            <?php endif; ?>
            <?php if ($patient['address']): ?>
            <span><span class="text-blue-300">Address </span><?= h($patient['address']) ?></span>
            <?php endif; ?>
            <span class="ml-auto">
                <span class="text-blue-300"><?= $formCount ?> form<?= $formCount !== 1 ? 's' : '' ?></span>
            </span>
        </div>
    </div>

    <!-- ── Forms loop ── -->
    <?php foreach ($forms as $idx => $f):
        $fd    = $formDefs[$f['form_type']] ?? ['label' => $f['form_type'], 'color' => '#475569'];
        $data  = json_decode($f['form_data'] ?? '{}', true) ?: [];
        $stLbl = $statusLabel[$f['status']] ?? 'Draft';
        $isLast = $idx === count($forms) - 1;
    ?>

    <div class="form-card bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-8 <?= $isLast ? '' : 'page-break' ?>">

        <!-- Form header bar -->
        <div style="background:<?= $fd['color'] ?>;" class="px-6 py-4 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-white font-extrabold text-lg"><?= h($fd['label']) ?></h2>
                <p class="text-white/70 text-xs mt-0.5"><?= h(PRACTICE_NAME) ?></p>
            </div>
            <div class="flex items-center gap-3 flex-shrink-0">
                <span class="bg-white/20 text-white text-xs font-bold px-3 py-1.5 rounded-full">
                    <?= h($stLbl) ?>
                </span>
                <span class="text-white/70 text-xs">
                    Form <?= $idx + 1 ?> of <?= $formCount ?>
                </span>
            </div>
        </div>

        <!-- Meta row -->
        <div class="px-6 py-3 bg-slate-50 border-b border-slate-100 flex flex-wrap gap-x-6 gap-y-1 text-sm">
            <span class="text-slate-500"><span class="font-semibold text-slate-700">Patient</span> <?= h($patientName) ?></span>
            <span class="text-slate-500"><span class="font-semibold text-slate-700">Date</span> <?= date('M j, Y g:i a', strtotime($f['created_at'])) ?></span>
            <?php if ($f['ma_name']): ?>
            <span class="text-slate-500"><span class="font-semibold text-slate-700">MA</span> <?= h($f['ma_name']) ?></span>
            <?php endif; ?>
        </div>

        <!-- Form body -->
        <div class="p-6">
            <?php if (!empty($data)): ?>
            <div class="space-y-3">
            <?php foreach ($data as $key => $value):
                if (in_array($key, ['csrf_token','patient_id','form_type'], true)) continue;
                // Skip empty
                if ($value === '' || $value === null) continue;
                if (is_array($value) && empty($value)) continue;
                $label = ucwords(str_replace(['_', 'ack'], [' ', 'Acknowledged: '], $key));
            ?>
            <?php if (is_array($value)): ?>
            <div class="pb-3 border-b border-slate-100">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-1.5"><?= h($label) ?></div>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($value as $v): ?>
                    <span class="inline-flex items-center px-2.5 py-1 bg-blue-50 text-blue-700 text-xs font-medium rounded-lg border border-blue-100">
                        <?= h((string)$v) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php elseif ($value === '1' || $value === 1): ?>
            <div class="flex items-center gap-2 text-sm text-slate-700 bg-emerald-50 px-4 py-2.5 rounded-xl border border-emerald-100">
                <i class="bi bi-check-circle-fill text-emerald-500 flex-shrink-0"></i>
                <span class="font-medium"><?= h($label) ?></span>
            </div>
            <?php else: ?>
            <div class="pb-3 border-b border-slate-100">
                <div class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-1"><?= h($label) ?></div>
                <div class="text-sm text-slate-800 font-medium"><?= nl2br(h((string)$value)) ?></div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-slate-400 text-sm italic">No form data.</p>
            <?php endif; ?>

            <!-- Signature -->
            <?php if ($f['patient_signature']): ?>
            <div class="mt-6 pt-5 border-t border-slate-200 flex items-end gap-6 flex-wrap">
                <div>
                    <div class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">Patient Signature</div>
                    <div class="border-2 border-slate-200 rounded-xl p-3 inline-block bg-white">
                        <img src="<?= h($f['patient_signature']) ?>" alt="Signature"
                             class="max-h-20 max-w-[240px] object-contain block">
                    </div>
                </div>
                <div class="text-xs text-slate-400 pb-2">
                    <i class="bi bi-shield-check mr-1 text-emerald-500"></i>
                    Signed electronically<br><?= date('F j, Y \a\t g:i a', strtotime($f['created_at'])) ?>
                </div>
                <!-- Signature line for print/fax copies without captured sig -->
                <div class="flex-1 min-w-[180px] pb-2">
                    <div class="border-b-2 border-slate-300 h-12"></div>
                    <div class="text-xs text-slate-400 mt-1">Authorised signature / Date</div>
                </div>
            </div>
            <?php else: ?>
            <div class="mt-6 pt-5 border-t border-slate-200 flex items-end gap-6 flex-wrap">
                <div class="flex-1 min-w-[200px] pb-2">
                    <div class="border-b-2 border-slate-300 h-12"></div>
                    <div class="text-xs text-slate-400 mt-1">Patient Signature / Date</div>
                </div>
                <div class="flex-1 min-w-[200px] pb-2">
                    <div class="border-b-2 border-slate-300 h-12"></div>
                    <div class="text-xs text-slate-400 mt-1">Witness / Relationship / Date</div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php endforeach; ?>

    <!-- Footer stamp -->
    <div class="text-center text-xs text-slate-400 pb-6 no-print">
        <?= h(APP_NAME) ?> &mdash; <?= h(PRACTICE_NAME) ?> &mdash; <?= h(PRACTICE_ADDRESS) ?>
        &mdash; Generated <?= date('F j, Y g:i a') ?>
    </div>

    </div><!-- /#exportContent -->
</div>

<!-- html2pdf.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
(function () {
    var btn   = document.getElementById('downloadBtn');
    var label = document.getElementById('downloadLabel');

    btn.addEventListener('click', function () {
        btn.disabled = true;
        label.textContent = 'Generating…';

        var opt = {
            margin:       [10, 10, 10, 10],
            filename:     '<?= addslashes($pdfFilename) ?>',
            image:        { type: 'jpeg', quality: 0.96 },
            html2canvas:  { scale: 2, useCORS: true, logging: false },
            jsPDF:        { unit: 'mm', format: 'letter', orientation: 'portrait' },
            pagebreak:    { mode: ['avoid-all', 'css'], before: '.page-break' },
        };

        html2pdf()
            .set(opt)
            .from(document.getElementById('exportContent'))
            .save()
            .then(function () {
                btn.disabled  = false;
                label.textContent = 'Download PDF';
            });
    });
})();
</script>
</body>
</html>
