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

include __DIR__ . '/includes/header.php';
?>

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

<!-- Document Card (printable) -->
<div id="printDoc" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden max-w-3xl">
    <!-- Header -->
    <div class="bg-gradient-to-r <?= $fm['bg'] ?> px-6 py-5 flex items-center gap-3">
        <div class="bg-white/20 p-2.5 rounded-xl">
            <i class="bi <?= $fm['icon'] ?> text-white text-2xl"></i>
        </div>
        <div>
            <h2 class="text-white font-extrabold text-xl"><?= $fm['label'] ?></h2>
            <p class="text-white/75 text-sm mt-0.5"><?= h(PRACTICE_NAME) ?></p>
        </div>
        <div class="ml-auto">
            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-bold bg-white/20 text-white">
                <?= $sc['label'] ?>
            </span>
        </div>
    </div>

    <!-- Patient Info -->
    <div class="px-6 py-4 bg-slate-50 border-b border-slate-100">
        <div class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
            <div>
                <span class="text-slate-500 text-xs font-semibold uppercase tracking-wide">Patient</span>
                <div class="font-bold text-slate-800"><?= h($doc['first_name'] . ' ' . $doc['last_name']) ?></div>
            </div>
            <?php if ($doc['dob']): ?>
            <div>
                <span class="text-slate-500 text-xs font-semibold uppercase tracking-wide">DOB</span>
                <div class="font-semibold text-slate-700"><?= date('M j, Y', strtotime($doc['dob'])) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($doc['insurance']): ?>
            <div>
                <span class="text-slate-500 text-xs font-semibold uppercase tracking-wide">Insurance</span>
                <div class="font-semibold text-slate-700"><?= h($doc['insurance']) ?></div>
            </div>
            <?php endif; ?>
            <div>
                <span class="text-slate-500 text-xs font-semibold uppercase tracking-wide">Date</span>
                <div class="font-semibold text-slate-700"><?= date('M j, Y g:i a', strtotime($doc['created_at'])) ?></div>
            </div>
            <?php if ($doc['ma_name']): ?>
            <div>
                <span class="text-slate-500 text-xs font-semibold uppercase tracking-wide">MA</span>
                <div class="font-semibold text-slate-700"><?= h($doc['ma_name']) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Document Body -->
    <div class="p-6">
        <?php if (!empty($data)): ?>
        <div class="space-y-4">
            <?php foreach ($data as $key => $value):
                if ($key === 'csrf_token' || $key === 'patient_id' || $key === 'form_type') continue;
                // Billing users see only billing-relevant fields
                if (isBilling() && in_array($key, CLINICAL_ONLY_FIELDS, true)) continue;

                /* ── ICD-10 codes: special billing-friendly block ── */
                if ($key === 'icd10_codes' && is_array($value) && !empty($value)):
            ?>
            <div class="border border-red-200 bg-red-50/40 rounded-xl p-4">
                <div class="flex items-center gap-2 mb-3">
                    <i class="bi bi-clipboard2-pulse-fill text-red-600 text-base"></i>
                    <span class="text-xs font-bold text-red-700 uppercase tracking-wide">Diagnosis / ICD-10 Codes</span>
                    <span class="ml-auto text-xs text-slate-400"><?= count($value) ?> code<?= count($value) !== 1 ? 's' : '' ?></span>
                </div>
                <div class="space-y-1.5">
                    <?php foreach ($value as $icdRaw):
                        $m = preg_match('/^([A-Z0-9.]+)\s+[—–-]+\s+(.+)$/', $icdRaw, $mt);
                        $icdCode = $m ? $mt[1] : $icdRaw;
                        $icdDesc = $m ? $mt[2] : '';
                    ?>
                    <div class="flex items-start gap-3 bg-white border border-red-100 rounded-lg px-3 py-2">
                        <span class="font-mono text-xs font-bold text-red-600 shrink-0 mt-0.5 w-20"><?= h($icdCode) ?></span>
                        <span class="text-xs text-slate-700"><?= h($icdDesc) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php continue; endif; ?>

                <?php $label = ucwords(str_replace(['_', 'ack'], [' ', 'Acknowledged: '], $key)); ?>
            ?>
            <?php if (is_array($value)): ?>
            <div class="border-b border-slate-100 pb-3">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-1"><?= h($label) ?></div>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($value as $v): ?>
                    <span class="inline-flex items-center px-2.5 py-1 bg-blue-50 text-blue-700 text-xs font-medium rounded-lg">
                        <?= h($v) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php elseif ($value === '1'): ?>
            <div class="flex items-center gap-2 text-sm text-slate-700 bg-emerald-50 px-4 py-2.5 rounded-xl">
                <i class="bi bi-check-circle-fill text-emerald-500 flex-shrink-0"></i>
                <span><?= h($label) ?></span>
            </div>
            <?php elseif ($value !== '' && $value !== null): ?>
            <div class="border-b border-slate-100 pb-3">
                <div class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-1"><?= h($label) ?></div>
                <div class="text-sm text-slate-800 font-medium"><?= nl2br(h($value)) ?></div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="text-slate-400 text-sm italic">No form data available.</p>
        <?php endif; ?>

        <!-- Signature -->
        <?php if ($doc['patient_signature']): ?>
        <div class="mt-8 pt-6 border-t border-slate-200">
            <div class="text-xs font-bold text-slate-500 uppercase tracking-wide mb-3">Patient Signature</div>
            <div class="border-2 border-slate-200 rounded-2xl p-4 inline-block bg-slate-50">
                <img src="<?= h($doc['patient_signature']) ?>" alt="Patient signature"
                     class="max-h-24 max-w-xs object-contain">
            </div>
            <p class="text-xs text-slate-400 mt-2">
                Signed electronically on <?= date('F j, Y \a\t g:i a', strtotime($doc['created_at'])) ?>
            </p>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($doc['status'] === 'draft' && !isBilling()): ?>
<!-- ─── Signature Capture Panel ───────────────────────────────────────── -->
<div id="signPanel" class="max-w-3xl mt-6 bg-white rounded-2xl shadow-sm border-2 border-rose-200 overflow-hidden no-print">
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
