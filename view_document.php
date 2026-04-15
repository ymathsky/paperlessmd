<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/audit.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }

$stmt = $pdo->prepare("
    SELECT fs.*, p.first_name, p.last_name, p.dob, p.insurance, s.full_name AS ma_name
    FROM form_submissions fs
    JOIN patients p ON p.id = fs.patient_id
    LEFT JOIN staff s ON s.id = fs.ma_id
    WHERE fs.id = ?
");
$stmt->execute([$id]);
$doc = $stmt->fetch();
if (!$doc) { header('Location: ' . BASE_URL . '/dashboard.php'); exit; }

auditLog($pdo, 'form_view', 'form', $id, $doc['form_type'] . ' — ' . $doc['first_name'] . ' ' . $doc['last_name']);

$data = json_decode($doc['form_data'] ?? '{}', true) ?: [];

// Fields billing users must NOT see (vitals, clinical notes, PHI not needed for coding)
define('CLINICAL_ONLY_FIELDS', [
    'chief_complaint', 'bp', 'pulse', 'temp', 'o2sat', 'glucose',
    'height', 'weight', 'resp', 'pharmacy_name', 'pharmacy_phone',
    'allergies', 'race', 'assistive_device', 'missed_visit_reason',
    'homebound_reason', 'current_medications', 'medication_list',
]);

$pageTitle = 'View Document';
$activeNav = 'patients';

$formMeta = [
    'vital_cs'    => ['label' => 'Vital CS Consent',    'icon' => 'bi-heart-pulse-fill',       'color' => 'text-red-600',     'bg' => 'from-red-600 to-red-500'],
    'new_patient' => ['label' => 'New Patient Consent', 'icon' => 'bi-person-plus-fill',        'color' => 'text-blue-600',    'bg' => 'from-blue-600 to-blue-700'],
    'abn'         => ['label' => 'ABN',                 'icon' => 'bi-file-earmark-ruled-fill', 'color' => 'text-amber-600',   'bg' => 'from-amber-500 to-amber-600'],
    'pf_signup'   => ['label' => 'PF Portal Signup',    'icon' => 'bi-envelope-at-fill',        'color' => 'text-cyan-600',    'bg' => 'from-cyan-600 to-cyan-500'],
    'ccm_consent' => ['label' => 'CCM Consent',         'icon' => 'bi-calendar2-heart-fill',    'color' => 'text-emerald-600', 'bg' => 'from-emerald-600 to-emerald-500'],
];
$fm  = $formMeta[$doc['form_type']] ?? ['label' => $doc['form_type'], 'icon' => 'bi-file', 'color' => 'text-slate-600', 'bg' => 'from-slate-600 to-slate-500'];

$statusCfg = [
    'draft'    => ['bg' => 'bg-slate-100',   'text' => 'text-slate-600',   'label' => 'Draft'],
    'signed'   => ['bg' => 'bg-blue-100',    'text' => 'text-blue-700',    'label' => 'Signed'],
    'uploaded' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'label' => 'Uploaded to PF'],
];
$sc = $statusCfg[$doc['status']] ?? $statusCfg['draft'];

/* ── Aliases expected by print templates ── */
$patient = [
    'first_name' => $doc['first_name'],
    'last_name'  => $doc['last_name'],
    'dob'        => $doc['dob'],
    'insurance'  => $doc['insurance'],
    'phone'      => '',
    'address'    => '',
    'email'      => '',
];
$f = $doc; // templates use $f for the submission row

if (!function_exists('vd')) {
    function vd(array $d, string $k): string {
        return isset($d[$k]) ? htmlspecialchars((string)$d[$k], ENT_QUOTES, 'UTF-8') : '';
    }
}
if (!function_exists('vdArr')) {
    function vdArr(array $d, string $k): array {
        if (!isset($d[$k])) return [];
        return is_array($d[$k]) ? $d[$k] : array_filter(array_map('trim', explode(',', (string)$d[$k])));
    }
}

include __DIR__ . '/includes/header.php';
?>
<style>
  @page { size: letter; margin: 0.5in; }
  @media print {
    .no-print  { display: none !important; }
    .sign-panel{ display: none !important; }
    body       { background: white !important; }
    #printDoc  { box-shadow: none !important; border: none !important; max-width: 100% !important; overflow: visible !important; border-radius: 0 !important; }
    .screen-strip { display: none !important; }
  }
  /* BWC paper form classes */
  .bwc-form            { max-width: 100%; font-family: Arial, sans-serif; font-size: 10pt; color: #000; }
  .bwc-header          { text-align: center; margin-bottom: 10pt; line-height: 1.5; }
  .bwc-header p        { margin: 1pt 0; }
  .bwc-practice-name   { font-size: 14pt; font-weight: bold; margin: 0 !important; }
  .bwc-form-title      { font-size: 12pt; font-weight: bold; text-decoration: underline; margin: 8pt 0 4pt !important; }
  .bwc-patient-line,
  .bwc-provider-line,
  .bwc-visit-row,
  .bwc-homebound-row,
  .bwc-row             { margin: 4pt 0; line-height: 1.8; font-size: 10pt; }
  .bwc-fill            { display: inline-block; min-width: 120pt; border-bottom: 1px solid #000; vertical-align: bottom; }
  .bwc-fill-sm         { display: inline-block; min-width: 40pt;  border-bottom: 1px solid #000; vertical-align: bottom; }
  .bwc-vitals-table    { width: 100%; border-collapse: collapse; margin: 6pt 0; }
  .bwc-vitals-table td { border: 1px solid #000; padding: 4pt 6pt; min-height: 28pt; vertical-align: top; width: 25%; font-size: 9.5pt; }
  .bwc-med-table       { width: 100%; border-collapse: collapse; margin: 6pt 0; }
  .bwc-med-table td,
  .bwc-med-table th    { border: 1px solid #000; padding: 3pt 5pt; font-size: 9.5pt; }
  .bwc-med-header th   { background: #f0f0f0; font-weight: bold; text-align: left; }
  .bwc-race-chip       { display: inline; margin-right: 6pt; }
  .bwc-checked         { font-weight: bold; text-decoration: underline; }
  .bwc-sigs            { margin-top: 20pt; }
  .bwc-sig-row         { display: flex; align-items: flex-end; gap: 20pt; margin-bottom: 2pt; }
  .bwc-sig-line        { flex: 1; border-bottom: 1px solid #000; min-height: 32pt; position: relative; }
  .bwc-sig-date        { white-space: nowrap; width: 140pt; border-bottom: 1px solid #000; min-height: 32pt; }
  .bwc-sig-label       { font-size: 9pt; color: #333; margin-bottom: 12pt; }
  .bwc-sig-img         { max-height: 30pt; max-width: 200pt; object-fit: contain; position: absolute; bottom: 2pt; }
  .bwc-section-hdr     { background: #333; color: #fff; font-weight: bold; padding: 3pt 6pt; font-size: 10pt; margin: 6pt 0 4pt; }
  .bwc-cog-table       { width: 100%; border-collapse: collapse; margin-bottom: 6pt; }
  .bwc-cog-table td    { border: 1px solid #ccc; padding: 4pt 6pt; font-size: 9.5pt; vertical-align: top; }
  /* Screen: make the paper form look like a real document */
  .bwc-form            { font-size: 11px; }
  .bwc-practice-name   { font-size: 18px; }
  .bwc-form-title      { font-size: 14px; }
</style>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-slate-400 mb-6 flex-wrap no-print">
    <a href="<?= BASE_URL ?>/patients.php" class="hover:text-blue-600 font-medium">Patients</a>
    <i class="bi bi-chevron-right text-xs"></i>
    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $doc['patient_id'] ?>" class="hover:text-blue-600 font-medium">
        <?= h($doc['first_name'] . ' ' . $doc['last_name']) ?>
    </a>
    <i class="bi bi-chevron-right text-xs"></i>
    <span class="text-slate-700 font-semibold"><?= $fm['label'] ?></span>
</nav>

<!-- Action Bar -->
<div class="flex flex-wrap items-center gap-3 mb-6 no-print">
    <?php if (!empty($_GET['already_signed'])): ?>
    <div class="w-full flex items-center gap-3 px-5 py-3 bg-amber-50 border border-amber-200 rounded-2xl text-sm text-amber-800">
        <i class="bi bi-exclamation-triangle-fill text-amber-500 flex-shrink-0"></i>
        <span>This form was already signed today. Showing the existing signed document.</span>
    </div>
    <?php endif; ?>
    <button onclick="window.print()"
            class="inline-flex items-center gap-2 px-5 py-2.5 bg-slate-700 hover:bg-slate-800 text-white
                   font-semibold rounded-xl transition-all shadow-sm text-sm">
        <i class="bi bi-printer-fill"></i> Print
    </button>
    <?php if ($doc['status'] === 'signed'): ?>
    <a href="<?= BASE_URL ?>/push_to_pf.php?form_id=<?= $doc['id'] ?>"
       class="inline-flex items-center gap-2 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white
              font-semibold rounded-xl transition-all shadow-sm text-sm">
        <i class="bi bi-cloud-upload-fill"></i> Send to Practice Fusion
    </a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $doc['patient_id'] ?>"
       class="inline-flex items-center gap-2 px-5 py-2.5 bg-white border border-slate-200 text-slate-700
              hover:bg-slate-50 font-semibold rounded-xl transition-all text-sm">
        ← Back
    </a>
</div>

<!-- Screen-only status / meta bar -->
<div class="screen-strip no-print flex flex-wrap items-center justify-between gap-3 mb-4 px-5 py-3
            bg-white rounded-2xl shadow-sm border border-slate-100 max-w-3xl">
    <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-xl grid place-items-center bg-slate-100 flex-shrink-0">
            <i class="bi <?= $fm['icon'] ?> <?= $fm['color'] ?> text-lg"></i>
        </div>
        <div>
            <p class="font-bold text-slate-800 text-sm"><?= $fm['label'] ?></p>
            <p class="text-xs text-slate-400"><?= date('M j, Y g:i a', strtotime($doc['created_at'])) ?><?= $doc['ma_name'] ? ' &mdash; MA: ' . h($doc['ma_name']) : '' ?></p>
        </div>
    </div>
    <span class="<?= $sc['bg'] ?> <?= $sc['text'] ?> text-xs font-bold px-3 py-1.5 rounded-full">
        <?= $sc['label'] ?>
    </span>
</div>

<!-- Document Card (printable) -->
<div id="printDoc" class="bg-white rounded-2xl shadow-md border border-slate-200 overflow-hidden max-w-3xl">
    <!-- Paper body -->
    <div class="px-10 py-8">
        <?php
            $tplFile = __DIR__ . '/includes/print_templates/' . preg_replace('/[^a-z0-9_]/', '', $doc['form_type']) . '.php';
            if (file_exists($tplFile)):
                include $tplFile;
            else:
        ?>
        <!-- Fallback: generic key-value rendering -->
        <div class="space-y-4" style="font-family:Arial,sans-serif;font-size:11px;">
            <!-- Practice header fallback -->
            <div style="text-align:center;margin-bottom:16px;">
                <p style="font-size:18px;font-weight:bold;margin:0;">Beyond Wound Care Inc.</p>
                <p style="margin:2px 0;">1340 Remington RD, Ste P &nbsp; Schaumburg, IL 60173</p>
                <p style="margin:2px 0;">Phone: 847.873.8693 &nbsp;&nbsp; Fax: 847.873.8486</p>
                <p style="margin:2px 0;">Email: Support@beyondwoundcare.com</p>
            </div>
            <?php foreach ($data as $key => $value):
                if (in_array($key, ['csrf_token','patient_id','form_type'], true)) continue;
                if ($value === '' || $value === null) continue;
                if (is_array($value) && empty($value)) continue;
                $label = ucwords(str_replace('_', ' ', $key));
            ?>
            <?php if (is_array($value)): ?>
            <div class="border-b border-slate-100 pb-3">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-1"><?= h($label) ?></div>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($value as $v): ?>
                    <span class="inline-flex items-center px-2.5 py-1 bg-blue-50 text-blue-700 text-xs font-medium rounded-lg"><?= h((string)$v) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php elseif ($value === '1' || $value === 1): ?>
            <div class="flex items-center gap-2 text-sm bg-emerald-50 px-4 py-2.5 rounded-xl">
                <i class="bi bi-check-circle-fill text-emerald-500"></i>
                <span class="font-medium text-slate-700"><?= h($label) ?></span>
            </div>
            <?php else: ?>
            <div class="border-b border-slate-100 pb-3">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-1"><?= h($label) ?></div>
                <div class="text-sm text-slate-800 font-medium"><?= nl2br(h((string)$value)) ?></div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Screen-only: signed electronic notice -->
    <?php if ($doc['patient_signature']): ?>
    <div class="no-print px-10 py-3 bg-emerald-50 border-t border-emerald-100 flex items-center gap-2">
        <i class="bi bi-shield-check text-emerald-600"></i>
        <span class="text-xs text-emerald-700 font-semibold">
            Signed electronically on <?= date('F j, Y \a\t g:i a', strtotime($doc['created_at'])) ?>
        </span>
    </div>
    <?php endif; ?>
</div>

<?php if ($doc['status'] === 'draft' && !isBilling()): ?>
<!-- ─── Signature Capture Panel ───────────────────────────────────────── -->
<div id="signPanel" class="sign-panel max-w-3xl mt-6 bg-white rounded-2xl shadow-sm border-2 border-rose-200 overflow-hidden no-print">
    <div class="flex items-center gap-3 px-6 py-4 bg-gradient-to-r from-rose-50 to-orange-50 border-b border-rose-100">
        <div class="w-9 h-9 bg-rose-100 rounded-xl grid place-items-center flex-shrink-0">
            <i class="bi bi-pen-fill text-rose-600"></i>
        </div>
        <div>
            <p class="font-bold text-slate-800 text-sm">Capture Patient Signature</p>
            <p class="text-xs text-slate-500 mt-0.5">Have the patient sign below, then click <strong>Save Signature</strong> to mark this form as signed.</p>
        </div>
    </div>

    <div class="p-6">
        <!-- Canvas -->
        <div class="relative border-2 border-dashed border-slate-300 rounded-xl bg-slate-50 overflow-hidden"
             style="touch-action: none;">
            <canvas id="sigCanvas" class="w-full block" style="height:160px;"></canvas>
            <div id="sigPlaceholder"
                 class="absolute inset-0 flex flex-col items-center justify-center text-slate-300 pointer-events-none select-none">
                <i class="bi bi-pencil-square text-4xl mb-1"></i>
                <span class="text-sm font-medium">Sign here</span>
            </div>
        </div>

        <!-- Controls -->
        <div class="flex items-center gap-3 mt-4 flex-wrap">
            <button id="sigClearBtn"
                    class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-slate-600
                           bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                <i class="bi bi-eraser-fill"></i> Clear
            </button>
            <button id="sigSaveBtn"
                    class="inline-flex items-center gap-2 px-5 py-2.5 text-sm font-bold text-white
                           bg-rose-600 hover:bg-rose-700 rounded-xl transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="bi bi-check-circle-fill"></i> Save Signature
            </button>
            <span id="sigMsg" class="text-sm font-semibold hidden"></span>
        </div>
    </div>
</div>
<?php
$csrfToken = htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8');
$docId     = (int)$doc['id'];
$apiBase   = BASE_URL;
$extraJs = <<<JS
<script>
(function () {
    var canvas      = document.getElementById('sigCanvas');
    var placeholder = document.getElementById('sigPlaceholder');
    var clearBtn    = document.getElementById('sigClearBtn');
    var saveBtn     = document.getElementById('sigSaveBtn');
    var sigMsg      = document.getElementById('sigMsg');

    function resizeCanvas() {
        var rect = canvas.getBoundingClientRect();
        canvas.width  = Math.floor(rect.width  * window.devicePixelRatio);
        canvas.height = Math.floor(rect.height * window.devicePixelRatio);
        canvas.getContext('2d').scale(window.devicePixelRatio, window.devicePixelRatio);
    }
    resizeCanvas();

    var pad = new SignaturePad(canvas, { penColor: '#1e293b', minWidth: 1.5, maxWidth: 3 });

    pad.addEventListener('beginStroke', function () {
        placeholder.style.display = 'none';
    });

    clearBtn.addEventListener('click', function () {
        pad.clear();
        placeholder.style.display = '';
        sigMsg.classList.add('hidden');
    });

    saveBtn.addEventListener('click', async function () {
        if (pad.isEmpty()) {
            sigMsg.textContent = 'Please sign before saving.';
            sigMsg.className   = 'text-sm font-semibold text-rose-600';
            sigMsg.classList.remove('hidden');
            return;
        }
        saveBtn.disabled   = true;
        saveBtn.innerHTML  = '<i class="bi bi-hourglass-split"></i> Saving\u2026';
        sigMsg.classList.add('hidden');
        try {
            var res  = await fetch('{$apiBase}/api/sign_form.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ csrf: '{$csrfToken}', id: {$docId}, signature: pad.toDataURL('image/png') }),
            });
            var json = await res.json();
            if (json.ok) {
                saveBtn.innerHTML = '<i class="bi bi-check2-circle"></i> Signed!';
                saveBtn.classList.replace('bg-rose-600',    'bg-emerald-600');
                saveBtn.classList.replace('hover:bg-rose-700','hover:bg-emerald-700');
                document.getElementById('signPanel').classList.replace('border-rose-200','border-emerald-200');
                sigMsg.textContent = 'Signature saved. Refreshing\u2026';
                sigMsg.className   = 'text-sm font-semibold text-emerald-600';
                sigMsg.classList.remove('hidden');
                setTimeout(function () { location.reload(); }, 1200);
            } else {
                throw new Error(json.error || 'Unknown error');
            }
        } catch (err) {
            saveBtn.disabled  = false;
            saveBtn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Save Signature';
            sigMsg.textContent = 'Error: ' + err.message;
            sigMsg.className   = 'text-sm font-semibold text-rose-600';
            sigMsg.classList.remove('hidden');
        }
    });
})();
</script>
JS;
?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
