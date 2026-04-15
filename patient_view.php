<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/audit.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/patients.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) { header('Location: ' . BASE_URL . '/patients.php'); exit; }

auditLog($pdo, 'patient_view', 'patient', $id, $patient['first_name'] . ' ' . $patient['last_name']);

$pageTitle = $patient['first_name'] . ' ' . $patient['last_name'];
$activeNav = 'patients';
$activeTab = $_GET['tab'] ?? 'forms';
// Billing users can only see the forms tab
if (isBilling() && in_array($activeTab, ['meds', 'photos'], true)) {
    $activeTab = 'forms';
}
$msg = $_GET['msg'] ?? '';

// Forms submitted for this patient
$formsStmt = $pdo->prepare("
    SELECT fs.*, s.full_name AS ma_name
    FROM form_submissions fs
    LEFT JOIN staff s ON s.id = fs.ma_id
    WHERE fs.patient_id = ?
    ORDER BY fs.created_at DESC
");
$formsStmt->execute([$id]);
$forms = $formsStmt->fetchAll();

// Wound photos
$photosStmt = $pdo->prepare("
    SELECT wp.*, s.full_name AS ma_name
    FROM wound_photos wp
    LEFT JOIN staff s ON s.id = wp.uploaded_by
    WHERE wp.patient_id = ?
    ORDER BY wp.created_at DESC
");
$photosStmt->execute([$id]);
$photos = $photosStmt->fetchAll();

$formDefs = [
    'vital_cs'           => ['label' => 'Visit Consent',           'icon' => 'bi-file-medical',        'bg' => 'bg-red-100',     'text' => 'text-red-700'],
    'new_patient'        => ['label' => 'New Patient Consent',     'icon' => 'bi-person-plus',         'bg' => 'bg-blue-100',    'text' => 'text-blue-600'],
    'abn'                => ['label' => 'ABN (CMS-R-131)',          'icon' => 'bi-file-earmark-ruled',  'bg' => 'bg-amber-100',   'text' => 'text-amber-600'],
    'pf_signup'          => ['label' => 'PF Portal Consent',        'icon' => 'bi-envelope-at',         'bg' => 'bg-cyan-100',    'text' => 'text-cyan-600'],
    'ccm_consent'        => ['label' => 'CCM Consent',              'icon' => 'bi-calendar2-heart',     'bg' => 'bg-emerald-100', 'text' => 'text-emerald-600'],
    'cognitive_wellness' => ['label' => 'Cognitive Wellness Exam',  'icon' => 'bi-brain',               'bg' => 'bg-violet-100',  'text' => 'text-violet-600'],
    'medicare_awv'       => ['label' => 'Medicare AWV',             'icon' => 'bi-clipboard2-pulse',    'bg' => 'bg-sky-100',     'text' => 'text-sky-600'],
    'il_disclosure'      => ['label' => 'IL Disclosure Auth.',       'icon' => 'bi-file-earmark-text',   'bg' => 'bg-slate-100',   'text' => 'text-slate-600'],
];

$statusCfg = [
    'draft'    => ['bg' => 'bg-slate-100',   'text' => 'text-slate-600',   'dot' => 'bg-slate-400',   'label' => 'Draft'],
    'signed'   => ['bg' => 'bg-blue-100',    'text' => 'text-blue-700',    'dot' => 'bg-blue-500',    'label' => 'Signed'],
    'uploaded' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'dot' => 'bg-emerald-500', 'label' => 'Uploaded'],
];

// ── Medications tab data ──────────────────────────────────────────────────────
try {
    $activeMedsQ = $pdo->prepare("
        SELECT pm.*, s.full_name AS added_by_name
        FROM patient_medications pm
        LEFT JOIN staff s ON s.id = pm.added_by
        WHERE pm.patient_id = ? AND pm.status = 'active'
        ORDER BY pm.sort_order ASC, pm.added_at ASC
    ");
    $activeMedsQ->execute([$id]);
    $activeMedsList = $activeMedsQ->fetchAll();

    $discMedsQ = $pdo->prepare("
        SELECT pm.*, s.full_name AS added_by_name
        FROM patient_medications pm
        LEFT JOIN staff s ON s.id = pm.added_by
        WHERE pm.patient_id = ? AND pm.status = 'discontinued'
        ORDER BY pm.updated_at DESC
    ");
    $discMedsQ->execute([$id]);
    $discMedsList = $discMedsQ->fetchAll();
} catch (PDOException $e) {
    $activeMedsList = [];
    $discMedsList   = [];
}

$extraJs = '';
if ($activeTab === 'meds') {
    $csrfJs = csrfToken();
    ob_start(); ?>
<script>
(function () {
    const PID  = <?= (int)$id ?>;
    const BASE = <?= json_encode(BASE_URL) ?>;
    const CSRF = <?= json_encode($csrfJs) ?>;

    async function medApi(data) {
        const r = await fetch(BASE + '/api/meds.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({...data, csrf: CSRF, patient_id: PID})
        });
        return r.json();
    }

    // Add medication
    const addBtn = document.getElementById('addMedBtn');
    const nameIn = document.getElementById('newMedName');
    const freqIn = document.getElementById('newMedFreq');
    const errMsg = document.getElementById('addMedErr');
    if (addBtn) {
        const submit = async () => {
            const name = nameIn.value.trim();
            const freq = freqIn.value.trim();
            errMsg.classList.add('hidden');
            if (!name) {
                errMsg.textContent = 'Medication name is required.';
                errMsg.classList.remove('hidden');
                nameIn.focus();
                return;
            }
            addBtn.disabled = true;
            addBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
            const res = await medApi({action: 'add', med_name: name, med_frequency: freq});
            addBtn.disabled = false;
            addBtn.innerHTML = '<i class="bi bi-plus-lg"></i> Add';
            if (res.ok) { location.reload(); }
            else { errMsg.textContent = res.error || 'Error adding.'; errMsg.classList.remove('hidden'); }
        };
        addBtn.addEventListener('click', submit);
        [nameIn, freqIn].forEach(el => el && el.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); submit(); }
        }));
    }

    // Active meds — event delegation
    const activeList = document.getElementById('activeMedsList');
    if (activeList) {
        activeList.addEventListener('click', async e => {
            const row = e.target.closest('[data-med-id]');
            if (!row) return;
            const medId = parseInt(row.dataset.medId);

            if (e.target.closest('.edit-med-btn')) {
                row.querySelector('.view-mode').classList.add('hidden');
                const em = row.querySelector('.edit-mode');
                em.classList.remove('hidden');
                em.querySelector('.edit-name').focus();
                return;
            }
            if (e.target.closest('.cancel-edit-btn')) {
                row.querySelector('.edit-mode').classList.add('hidden');
                row.querySelector('.view-mode').classList.remove('hidden');
                return;
            }
            if (e.target.closest('.save-edit-btn')) {
                const name = row.querySelector('.edit-name').value.trim();
                const freq = row.querySelector('.edit-freq').value.trim();
                if (!name) return;
                const btn = row.querySelector('.save-edit-btn');
                btn.disabled = true;
                const res = await medApi({action: 'update', id: medId, med_name: name, med_frequency: freq});
                btn.disabled = false;
                if (res.ok) { location.reload(); }
                else { alert(res.error || 'Error updating'); }
                return;
            }
            if (e.target.closest('.dc-med-btn')) {
                const medName = row.querySelector('.med-name-disp').textContent;
                if (!confirm('Discontinue "' + medName + '"?\n\nThis updates the master medication list.')) return;
                const res = await medApi({action: 'discontinue', id: medId});
                if (res.ok) { location.reload(); }
                else { alert(res.error || 'Error'); }
                return;
            }
            if (e.target.closest('.history-btn')) {
                const panel = row.querySelector('.history-panel');
                if (!panel.dataset.loaded) {
                    panel.dataset.loaded = '1';
                    panel.innerHTML = '<p class="text-xs text-slate-400 py-1"><i class="bi bi-hourglass-split mr-1"></i>Loading...</p>';
                    panel.classList.remove('hidden');
                    const res = await fetch(BASE + '/api/meds.php?action=history&id=' + medId + '&patient_id=' + PID);
                    const data = await res.json();
                    panel.innerHTML = data.ok ? renderHistory(data.history) : '<p class="text-xs text-red-500">Could not load history.</p>';
                } else {
                    panel.classList.toggle('hidden');
                }
                return;
            }
        });
    }

    // Discontinued toggle
    const discBtn     = document.getElementById('toggleDiscBtn');
    const discSection = document.getElementById('discMedsSection');
    const discChevron = document.getElementById('discChevron');
    if (discBtn && discSection) {
        discBtn.addEventListener('click', () => {
            const isHidden = discSection.classList.toggle('hidden');
            if (discChevron) discChevron.style.transform = isHidden ? '' : 'rotate(180deg)';
        });
    }

    // Reactivate
    if (discSection) {
        discSection.addEventListener('click', async e => {
            if (!e.target.closest('.reactivate-btn')) return;
            const row = e.target.closest('[data-med-id]');
            if (!row) return;
            const medId = parseInt(row.dataset.medId);
            const res = await medApi({action: 'reactivate', id: medId});
            if (res.ok) { location.reload(); }
            else { alert(res.error || 'Error'); }
        });
    }

    // Admin delete
    const adminDeleteWrap = document.getElementById('adminMedDeleteWrap');
    if (adminDeleteWrap) {
        adminDeleteWrap.addEventListener('click', async e => {
            if (!e.target.closest('.admin-delete-btn')) return;
            const row = e.target.closest('[data-med-id]');
            if (!row) return;
            const medId = parseInt(row.dataset.medId);
            const name  = row.querySelector('.med-name-disp') ? row.querySelector('.med-name-disp').textContent : 'this medication';
            if (!confirm('Permanently delete "' + name + '"? This cannot be undone.')) return;
            const res = await medApi({action: 'delete', id: medId});
            if (res.ok) { location.reload(); }
            else { alert(res.error || 'Error'); }
        });
    }

    // History renderer
    function renderHistory(history) {
        if (!history || !history.length) return '<p class="text-xs text-slate-400 italic py-1">No history recorded.</p>';
        const icons = {
            added:        'bi-plus-circle-fill text-emerald-500',
            modified:     'bi-pencil-fill text-blue-500',
            discontinued: 'bi-x-circle-fill text-red-500',
            reactivated:  'bi-arrow-counterclockwise text-emerald-500',
            removed:      'bi-trash-fill text-slate-400',
        };
        return '<div class="space-y-2.5 py-1">' + history.map(h => {
            const icon = icons[h.action] || 'bi-circle text-slate-400';
            const dt   = new Date(h.changed_at).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'});
            let detail = '';
            if (h.action === 'added')
                detail = esc(h.new_name) + (h.new_frequency ? ' &middot; ' + esc(h.new_frequency) : '');
            else if (h.action === 'modified')
                detail = esc(h.prev_name) + ' &rarr; ' + esc(h.new_name) +
                    (h.prev_frequency !== h.new_frequency ? ' (' + (esc(h.prev_frequency)||'&mdash;') + ' &rarr; ' + (esc(h.new_frequency)||'&mdash;') + ')' : '');
            else if (h.action === 'discontinued' || h.action === 'reactivated')
                detail = esc(h.prev_name || h.new_name);
            const byLine  = h.changed_by_name ? ' by <strong>' + esc(h.changed_by_name) + '</strong>' : '';
            const formRef = h.form_submission_id
                ? ' <a href="' + BASE + '/view_document.php?id=' + h.form_submission_id + '" class="text-blue-500 hover:underline">(visit form)</a>' : '';
            return `<div class="flex items-start gap-2.5">
                <i class="bi ${icon} mt-0.5 shrink-0 text-sm"></i>
                <div class="text-xs">
                    <span class="font-semibold text-slate-700 capitalize">${h.action}</span>
                    ${detail ? ': <span class="text-slate-600">' + detail + '</span>' : ''}
                    <div class="text-slate-400 mt-0.5">${dt}${byLine}${formRef}</div>
                </div>
            </div>`;
        }).join('') + '</div>';
    }

    function esc(s) {
        return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : '';
    }
})();
</script>
<?php
    $extraJs = ob_get_clean();
}

include __DIR__ . '/includes/header.php';
?>

<!-- Success Toast -->
<?php if ($msg === 'created' || $msg === 'updated'): ?>
<div id="toast"
     class="fixed top-20 right-4 z-50 flex items-center gap-3 bg-emerald-600 text-white px-5 py-3.5 rounded-2xl shadow-2xl text-sm font-semibold transition-all">
    <i class="bi bi-check-circle-fill text-lg"></i>
    <?= $msg === 'created' ? 'Patient added successfully!' : 'Patient updated!' ?>
</div>
<script>setTimeout(()=>{const t=document.getElementById('toast');if(t)t.style.opacity='0';},3000);</script>
<?php endif; ?>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-slate-400 mb-6">
    <a href="<?= BASE_URL ?>/patients.php" class="hover:text-blue-600 transition-colors font-medium">Patients</a>
    <i class="bi bi-chevron-right text-xs"></i>
    <span class="text-slate-700 font-semibold"><?= h($patient['first_name'] . ' ' . $patient['last_name']) ?></span>
</nav>

<!-- Patient Header Card -->
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
        <div class="flex gap-2 flex-wrap">
            <?php if (canAccessClinical()): ?>
            <a href="<?= BASE_URL ?>/patient_timeline.php?id=<?= $id ?>"
               class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-blue-700
                      bg-blue-50 hover:bg-blue-100 rounded-xl transition-colors">
                <i class="bi bi-clock-history"></i> Timeline
            </a>
            <a href="<?= BASE_URL ?>/patient_edit.php?id=<?= $id ?>"
               class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-slate-700
                      bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                <i class="bi bi-pencil-fill"></i> Edit
            </a>
            <?php endif; ?>
            <?php if (!empty($forms)): ?>
            <a href="<?= BASE_URL ?>/push_to_pf.php?patient_id=<?= $id ?>"
               class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white
                      bg-indigo-600 hover:bg-indigo-700 rounded-xl transition-colors shadow-sm">
                <i class="bi bi-cloud-upload-fill"></i> Push to PF
            </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($patient['address'] || $patient['pcp'] || $patient['email']): ?>
    <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap gap-x-6 gap-y-1 text-sm text-slate-500">
        <?php if ($patient['email']): ?><span><i class="bi bi-envelope mr-1"></i><?= h($patient['email']) ?></span><?php endif; ?>
        <?php if ($patient['address']): ?><span><i class="bi bi-geo-alt mr-1"></i><?= h($patient['address']) ?></span><?php endif; ?>
        <?php if ($patient['pcp']): ?><span><i class="bi bi-person-badge mr-1"></i>PCP: <?= h($patient['pcp']) ?></span><?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Form Tiles -->
<?php if (canAccessClinical()): ?>
<div class="mb-6">
    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-3">Start a Form</h3>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
        <?php foreach ($formDefs as $type => $def): ?>
        <a href="<?= BASE_URL ?>/forms/<?= $type ?>.php?patient_id=<?= $id ?>"
           class="form-tile flex flex-col items-center gap-2.5 p-4 bg-white rounded-2xl border-2 border-slate-100
                  hover:border-blue-300 hover:shadow-md transition-all group cursor-pointer">
            <div class="w-12 h-12 <?= $def['bg'] ?> rounded-xl grid place-items-center transition-colors group-hover:scale-105">
                <i class="bi <?= $def['icon'] ?> <?= $def['text'] ?> text-xl"></i>
            </div>
            <span class="text-xs font-semibold text-slate-700 text-center leading-snug"><?= $def['label'] ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- Wound Care link -->
<a href="<?= BASE_URL ?>/forms/wound_care.php?patient_id=<?= $id ?>"
   class="inline-flex items-center gap-2 mb-6 px-5 py-3 bg-violet-600 hover:bg-violet-700 text-white
          font-semibold rounded-xl transition-all shadow-sm hover:shadow-md active:scale-95 text-sm">
    <i class="bi bi-camera-fill"></i> Add Wound Photos
</a>
<?php endif; // canAccessClinical form tiles ?>

<!-- Tab Nav -->
<div class="flex gap-1 mb-4 bg-slate-100 p-1 rounded-2xl w-fit">
    <a href="?id=<?= $id ?>&tab=forms"
       class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $activeTab === 'forms' ? 'bg-white text-blue-700 shadow' : 'text-slate-500 hover:text-slate-700' ?>">
        <i class="bi bi-file-earmark-text mr-1.5"></i>Forms
        <?php if (count($forms)): ?>
        <span class="ml-1 bg-blue-100 text-blue-700 text-xs px-1.5 py-0.5 rounded-full"><?= count($forms) ?></span>
        <?php endif; ?>
    </a>
    <?php if (canAccessClinical()): ?>
    <a href="?id=<?= $id ?>&tab=meds"
       class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $activeTab === 'meds' ? 'bg-white text-emerald-700 shadow' : 'text-slate-500 hover:text-slate-700' ?>">
        <i class="bi bi-capsule mr-1.5"></i>Meds
        <?php if (!empty($activeMedsList)): ?>
        <span class="ml-1 bg-emerald-100 text-emerald-700 text-xs px-1.5 py-0.5 rounded-full"><?= count($activeMedsList) ?></span>
        <?php endif; ?>
    </a>
    <a href="?id=<?= $id ?>&tab=photos"
       class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $activeTab === 'photos' ? 'bg-white text-violet-700 shadow' : 'text-slate-500 hover:text-slate-700' ?>">
        <i class="bi bi-camera mr-1.5"></i>Photos
        <?php if (count($photos)): ?>
        <span class="ml-1 bg-violet-100 text-violet-700 text-xs px-1.5 py-0.5 rounded-full"><?= count($photos) ?></span>
        <?php endif; ?>
    </a>
    <?php endif; // canAccessClinical tabs ?>
</div>

<!-- Forms Tab -->
<?php if ($activeTab === 'forms'): ?>
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <?php if (empty($forms)): ?>
    <div class="flex flex-col items-center justify-center py-16 text-slate-400">
        <i class="bi bi-file-earmark-x text-5xl mb-3 opacity-30"></i>
        <p class="font-semibold text-slate-500">No forms yet</p>
        <p class="text-sm mt-1">Use the tiles above to start a form for this patient.</p>
    </div>
    <?php else: ?>

    <!-- Batch export toolbar (hidden until checkbox selected) -->
    <div id="batchBar" class="hidden items-center gap-3 px-5 py-3 bg-blue-50 border-b border-blue-100">
        <span id="batchCount" class="text-sm font-bold text-blue-800">0 selected</span>
        <a id="batchExportBtn" href="#"
           class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-bold text-white
                  bg-blue-600 hover:bg-blue-700 rounded-xl transition-colors shadow-sm">
            <i class="bi bi-file-earmark-pdf-fill"></i> Export Selected as PDF
        </a>
        <button onclick="document.querySelectorAll('.form-chk').forEach(c=>c.checked=false);updateBatch();"
                class="text-xs font-semibold text-blue-500 hover:text-blue-700">
            Clear
        </button>
        <div class="ml-auto">
            <a href="<?= BASE_URL ?>/export_pdf.php?patient_id=<?= $id ?>"
               class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-semibold text-slate-600
                      bg-white border border-slate-200 hover:bg-slate-50 rounded-xl transition-colors">
                <i class="bi bi-file-earmark-pdf"></i> Export All Forms
            </a>
        </div>
    </div>
    <!-- Always-visible Export All (when nothing selected) -->
    <div id="exportAllBar" class="flex items-center justify-end px-5 py-2.5 border-b border-slate-50">
        <a href="<?= BASE_URL ?>/export_pdf.php?patient_id=<?= $id ?>"
           class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500
                  hover:text-blue-700 transition-colors">
            <i class="bi bi-file-earmark-pdf-fill text-blue-400"></i> Export all <?= count($forms) ?> forms as PDF
        </a>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-slate-50 text-left border-b border-slate-100">
                    <th class="pl-5 pr-2 py-3.5 w-8">
                        <input type="checkbox" id="chkAll"
                               class="w-3.5 h-3.5 text-blue-600 border-slate-300 rounded cursor-pointer"
                               title="Select all">
                    </th>
                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Form</th>
                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide hidden md:table-cell">MA</th>
                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide hidden sm:table-cell">Date</th>
                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Status</th>
                    <th class="px-4 py-3.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($forms as $f):
                    $fd = $formDefs[$f['form_type']] ?? ['label'=>$f['form_type'],'icon'=>'bi-file','bg'=>'bg-slate-100','text'=>'text-slate-600'];
                    $sc = $statusCfg[$f['status']] ?? $statusCfg['draft'];
                ?>
                <tr class="hover:bg-slate-50/70 transition-colors">
                    <td class="pl-5 pr-2 py-4">
                        <input type="checkbox" class="form-chk w-3.5 h-3.5 text-blue-600 border-slate-300 rounded cursor-pointer"
                               value="<?= $f['id'] ?>" onchange="updateBatch()">
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex items-center gap-3">
                            <span class="<?= $fd['bg'] ?> <?= $fd['text'] ?> p-2 rounded-xl">
                                <i class="bi <?= $fd['icon'] ?> text-base"></i>
                            </span>
                            <span class="font-medium text-slate-700"><?= $fd['label'] ?></span>
                        </div>
                    </td>
                    <td class="px-4 py-4 text-slate-500 hidden md:table-cell"><?= h($f['ma_name'] ?? '—') ?></td>
                    <td class="px-4 py-4 text-slate-500 hidden sm:table-cell"><?= date('M j, Y g:ia', strtotime($f['created_at'])) ?></td>
                    <td class="px-4 py-4">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?= $sc['bg'] ?> <?= $sc['text'] ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= $sc['dot'] ?>"></span>
                            <?= $sc['label'] ?>
                        </span>
                    </td>
                    <td class="px-4 py-4">
                        <a href="<?= BASE_URL ?>/view_document.php?id=<?= $f['id'] ?>"
                           class="inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-800 font-semibold text-xs
                                  bg-blue-50 hover:bg-blue-100 px-3.5 py-2 rounded-xl transition-colors">
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
<script>
var BASE_URL_PID = '<?= BASE_URL ?>/export_pdf.php?patient_id=<?= $id ?>&ids=';
function updateBatch() {
    var checked = Array.from(document.querySelectorAll('.form-chk:checked')).map(c => c.value);
    var bar     = document.getElementById('batchBar');
    var allBar  = document.getElementById('exportAllBar');
    var cnt     = document.getElementById('batchCount');
    var btn     = document.getElementById('batchExportBtn');
    if (checked.length > 0) {
        bar.classList.remove('hidden'); bar.classList.add('flex');
        allBar.classList.add('hidden');
        cnt.textContent = checked.length + ' selected';
        btn.href = BASE_URL_PID + checked.join(',');
    } else {
        bar.classList.add('hidden'); bar.classList.remove('flex');
        allBar.classList.remove('hidden');
    }
}
document.getElementById('chkAll').addEventListener('change', function () {
    document.querySelectorAll('.form-chk').forEach(c => { c.checked = this.checked; });
    updateBatch();
});
</script>

<!-- Meds Tab -->
<?php elseif ($activeTab === 'meds'): ?>
<div class="space-y-5">

    <!-- Add medication card -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
        <h4 class="text-sm font-bold text-slate-700 mb-3 flex items-center gap-2">
            <i class="bi bi-plus-circle-fill text-emerald-600"></i> Add Medication
        </h4>
        <div class="flex flex-col sm:flex-row gap-3">
            <input id="newMedName" type="text"
                   class="flex-[3] px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50
                          focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent transition focus:bg-white"
                   placeholder="Medication name &amp; dose (e.g. Metformin 500mg)" autocomplete="off">
            <input id="newMedFreq" type="text"
                   class="flex-[2] px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50
                          focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent transition focus:bg-white"
                   placeholder="Frequency (e.g. BID, QD)" autocomplete="off">
            <button id="addMedBtn"
                    class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-sm
                           font-semibold rounded-xl transition-all shadow-sm flex items-center gap-2 whitespace-nowrap">
                <i class="bi bi-plus-lg"></i> Add
            </button>
        </div>
        <p id="addMedErr" class="text-xs text-red-600 mt-2 hidden"></p>
    </div>

    <!-- Active medications -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/70">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 bg-emerald-500 rounded-full"></span>
                <span class="text-sm font-bold text-slate-700">Active Medications</span>
                <span class="text-xs text-slate-400">(<?= count($activeMedsList) ?>)</span>
            </div>
            <span class="text-xs text-slate-400 hidden sm:block">
                <i class="bi bi-info-circle mr-0.5"></i>Synced automatically with Visit Consent forms
            </span>
        </div>
        <?php if (empty($activeMedsList)): ?>
        <div class="flex flex-col items-center py-10 text-slate-400">
            <i class="bi bi-capsule text-5xl mb-3 opacity-20"></i>
            <p class="font-semibold text-slate-500">No active medications</p>
            <p class="text-xs text-slate-400 mt-1">Add medications above — they'll auto-populate Visit Consent forms.</p>
        </div>
        <?php else: ?>
        <div id="activeMedsList">
            <?php foreach ($activeMedsList as $med): ?>
            <div class="med-row border-b border-slate-50 last:border-0" data-med-id="<?= $med['id'] ?>">
                <!-- View mode -->
                <div class="view-mode flex items-center gap-3 px-5 py-3.5 hover:bg-slate-50/60 transition-colors">
                    <div class="w-8 h-8 bg-emerald-100 rounded-lg grid place-items-center flex-shrink-0">
                        <i class="bi bi-capsule text-emerald-600 text-sm"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-slate-800 med-name-disp"><?= h($med['med_name']) ?></p>
                        <p class="text-xs text-slate-400 mt-0.5 flex flex-wrap items-center gap-x-3">
                            <?php if ($med['med_frequency']): ?>
                            <span class="font-medium text-slate-500"><?= h($med['med_frequency']) ?></span>
                            <?php endif; ?>
                            <span>Added <?= date('M j, Y', strtotime($med['added_at'])) ?>
                                <?php if ($med['added_by_name']): ?>
                                by <?= h($med['added_by_name']) ?>
                                <?php endif; ?>
                            </span>
                        </p>
                    </div>
                    <div class="flex items-center gap-1 flex-shrink-0">
                        <button class="edit-med-btn p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors"
                                title="Edit">
                            <i class="bi bi-pencil text-sm"></i>
                        </button>
                        <button class="history-btn p-2 text-slate-400 hover:text-violet-600 hover:bg-violet-50 rounded-lg transition-colors"
                                title="View history">
                            <i class="bi bi-clock-history text-sm"></i>
                        </button>
                        <button class="dc-med-btn px-3 py-1.5 text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100
                                       rounded-lg transition-colors" title="Discontinue">
                            D/C
                        </button>
                    </div>
                </div>
                <!-- Edit mode -->
                <div class="edit-mode hidden px-5 py-3 bg-slate-50 border-t border-slate-100">
                    <div class="flex flex-col sm:flex-row gap-2">
                        <input type="text" class="edit-name flex-[3] px-3 py-2 text-sm border border-slate-200 rounded-xl
                                                   focus:outline-none focus:ring-2 focus:ring-emerald-400 bg-white"
                               value="<?= h($med['med_name']) ?>" placeholder="Medication name &amp; dose">
                        <input type="text" class="edit-freq flex-[2] px-3 py-2 text-sm border border-slate-200 rounded-xl
                                                   focus:outline-none focus:ring-2 focus:ring-emerald-400 bg-white"
                               value="<?= h($med['med_frequency']) ?>" placeholder="Frequency">
                        <div class="flex gap-2">
                            <button class="save-edit-btn px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white
                                           text-xs font-semibold rounded-xl transition-colors">Save</button>
                            <button class="cancel-edit-btn px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600
                                           text-xs font-semibold rounded-xl transition-colors">Cancel</button>
                        </div>
                    </div>
                </div>
                <!-- History panel -->
                <div class="history-panel hidden px-5 py-3 bg-violet-50/40 border-t border-violet-100 text-xs">
                    <p class="text-slate-400 italic"><i class="bi bi-hourglass-split mr-1"></i>Loading history...</p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Discontinued medications -->
    <?php if (!empty($discMedsList)): ?>
    <div id="adminMedDeleteWrap" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <button id="toggleDiscBtn"
                class="w-full flex items-center justify-between px-5 py-3.5 bg-slate-50/70 hover:bg-slate-100
                       transition-colors text-left">
            <div class="flex items-center gap-2">
                <span class="w-2 h-2 bg-slate-400 rounded-full"></span>
                <span class="text-sm font-bold text-slate-500">Discontinued</span>
                <span class="text-xs text-slate-400">(<?= count($discMedsList) ?>)</span>
            </div>
            <i id="discChevron" class="bi bi-chevron-down text-slate-400 transition-transform"></i>
        </button>
        <div id="discMedsSection" class="hidden">
            <?php foreach ($discMedsList as $med): ?>
            <div class="border-b border-slate-50 last:border-0 flex items-center gap-3 px-5 py-3
                        hover:bg-slate-50/60 transition-colors" data-med-id="<?= $med['id'] ?>">
                <div class="w-8 h-8 bg-slate-100 rounded-lg grid place-items-center flex-shrink-0">
                    <i class="bi bi-capsule text-slate-400 text-sm"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm text-slate-400 line-through"><?= h($med['med_name']) ?></p>
                    <p class="text-xs text-slate-400 mt-0.5">
                        <?php if ($med['med_frequency']): ?><span class="mr-2"><?= h($med['med_frequency']) ?></span><?php endif; ?>
                        D/C'd <?= date('M j, Y', strtotime($med['updated_at'])) ?>
                    </p>
                </div>
                <div class="flex items-center gap-1.5">
                    <button class="reactivate-btn px-3 py-1.5 text-xs font-semibold text-emerald-700
                                   bg-emerald-50 hover:bg-emerald-100 rounded-lg transition-colors">
                        Reactivate
                    </button>
                    <?php if (isAdmin()): ?>
                    <button class="admin-delete-btn p-2 text-slate-300 hover:text-red-500 hover:bg-red-50
                                   rounded-lg transition-colors" title="Permanently delete">
                        <i class="bi bi-trash text-sm"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /meds tab -->

<!-- Photos Tab -->
<?php else: ?>
<?php if (empty($photos)): ?>
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 flex flex-col items-center justify-center py-16 text-slate-400">
    <i class="bi bi-camera-slash text-5xl mb-3 opacity-30"></i>
    <p class="font-semibold text-slate-500">No wound photos yet</p>
    <p class="text-sm mt-1">
        <a href="<?= BASE_URL ?>/forms/wound_care.php?patient_id=<?= $id ?>" class="text-blue-600 hover:underline">Add photos</a>
        for this patient.
    </p>
</div>
<?php else: ?>
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
    <?php foreach ($photos as $ph): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="aspect-square overflow-hidden bg-slate-50">
            <img src="<?= BASE_URL ?>/uploads/photos/<?= h($ph['filename']) ?>"
                 alt="Wound photo"
                 class="w-full h-full object-cover hover:scale-105 transition-transform duration-300">
        </div>
        <div class="p-3">
            <p class="text-xs font-semibold text-slate-700 truncate"><?= h($ph['location'] ?: 'Unspecified') ?></p>
            <p class="text-xs text-slate-400 mt-0.5"><?= date('M j, Y', strtotime($ph['created_at'])) ?></p>
            <?php if ($ph['description']): ?>
            <p class="text-xs text-slate-500 mt-1 line-clamp-2"><?= h($ph['description']) ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
