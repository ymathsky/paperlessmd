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
if (isBilling() && in_array($activeTab, ['meds', 'photos', 'wounds'], true)) {
    $activeTab = 'forms';
}
$msg = $_GET['msg'] ?? '';

// Active visit context from One-Tap Start Visit
$visitId    = (int)($_GET['visit'] ?? 0);
$activeVisit = null;
if ($visitId) {
    $vsStmt = $pdo->prepare("SELECT * FROM `schedule` WHERE id = ? AND patient_id = ? AND status = 'en_route'");
    $vsStmt->execute([$visitId, $id]);
    $activeVisit = $vsStmt->fetch() ?: null;
}

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
    ORDER BY wp.wound_location ASC, wp.created_at ASC
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

// ── Wound measurements tab data ───────────────────────────────────────────────
try {
    $woundsQ = $pdo->prepare("
        SELECT wm.*, s.full_name AS recorded_by_name
        FROM wound_measurements wm
        LEFT JOIN staff s ON s.id = wm.recorded_by
        WHERE wm.patient_id = ?
        ORDER BY wm.measured_at ASC, wm.id ASC
    ");
    $woundsQ->execute([$id]);
    $woundMeasurements = $woundsQ->fetchAll();
} catch (PDOException $e) {
    $woundMeasurements = [];
}

// ── Last Visit Summary data ───────────────────────────────────────────────────
$lastVisit = null;
try {
    $lvStmt = $pdo->prepare("
        SELECT sc.visit_date, sc.visit_time, sc.status, sc.notes,
               s.full_name AS ma_name
        FROM `schedule` sc
        LEFT JOIN staff s ON s.id = sc.ma_id
        WHERE sc.patient_id = ? AND sc.status IN ('completed','en_route')
        ORDER BY sc.visit_date DESC, sc.visit_time DESC
        LIMIT 1
    ");
    $lvStmt->execute([$id]);
    $lastVisit = $lvStmt->fetch() ?: null;
} catch (PDOException $e) { $lastVisit = null; }

$lastVitals = null;
try {
    $lvfStmt = $pdo->prepare("
        SELECT form_data, created_at, ma_id
        FROM form_submissions
        WHERE patient_id = ? AND form_type = 'vital_cs'
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $lvfStmt->execute([$id]);
    $lvfRow = $lvfStmt->fetch();
    if ($lvfRow) {
        $lvfData = json_decode($lvfRow['form_data'], true) ?? [];
        $lastVitals = [
            'bp'       => trim(($lvfData['bp_systolic'] ?? '') . ($lvfData['bp_systolic'] ? '/' . ($lvfData['bp_diastolic'] ?? '') : '')),
            'hr'       => trim($lvfData['heart_rate'] ?? ''),
            'temp'     => trim($lvfData['temperature'] ?? ''),
            'weight'   => trim($lvfData['weight'] ?? ''),
            'o2'       => trim($lvfData['o2_sat'] ?? ''),
            'date'     => $lvfRow['created_at'],
        ];
    }
} catch (PDOException $e) { $lastVitals = null; }

// Count forms completed in the last visit's date
$lastVisitFormCount = 0;
if ($lastVisit) {
    foreach ($forms as $f) {
        if (substr($f['created_at'], 0, 10) === $lastVisit['visit_date']) {
            $lastVisitFormCount++;
        }
    }
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

if ($activeTab === 'wounds' && canAccessClinical()) {
    $woundsCsrf = csrfToken();
    ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    const PID   = <?= (int)$id ?>;
    const BASE  = <?= json_encode(BASE_URL) ?>;
    const CSRF  = <?= json_encode($woundsCsrf) ?>;
    const ADMIN = <?= isAdmin() ? 'true' : 'false' ?>;

    // ── Submit new measurement ────────────────────────────────────────────────
    const form    = document.getElementById('woundForm');
    const errEl   = document.getElementById('woundErr');
    const submitB = document.getElementById('woundSubmit');
    if (form) {
        form.addEventListener('submit', async e => {
            e.preventDefault();
            errEl.classList.add('hidden');
            const fd = new FormData(form);
            const payload = {
                action: 'add', csrf: CSRF, patient_id: PID,
                measured_at: fd.get('measured_at'),
                wound_site:  fd.get('wound_site'),
                length_cm:   fd.get('length_cm'),
                width_cm:    fd.get('width_cm'),
                depth_cm:    fd.get('depth_cm'),
                notes:       fd.get('notes'),
            };
            submitB.disabled = true;
            submitB.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';
            try {
                const r = await fetch(BASE + '/api/wounds.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payload),
                });
                const data = await r.json();
                if (data.ok) {
                    location.reload();
                } else {
                    errEl.textContent = data.error || 'Could not save.';
                    errEl.classList.remove('hidden');
                    submitB.disabled = false;
                    submitB.innerHTML = '<i class="bi bi-plus-lg"></i> Log Measurement';
                }
            } catch {
                errEl.textContent = 'Network error. Please try again.';
                errEl.classList.remove('hidden');
                submitB.disabled = false;
                submitB.innerHTML = '<i class="bi bi-plus-lg"></i> Log Measurement';
            }
        });
    }

    // ── Delete measurement ────────────────────────────────────────────────────
    document.addEventListener('click', async e => {
        const del = e.target.closest('.del-wound-btn');
        if (!del || !ADMIN) return;
        const mid = parseInt(del.dataset.id);
        if (!confirm('Delete this measurement? This cannot be undone.')) return;
        del.disabled = true;
        const r = await fetch(BASE + '/api/wounds.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({action: 'delete', csrf: CSRF, id: mid}),
        });
        const d = await r.json();
        if (d.ok) {
            document.querySelector('[data-wound-row="' + mid + '"]')?.remove();
            rebuildChart();
        } else {
            alert(d.error || 'Error deleting.');
            del.disabled = false;
        }
    });

    // ── Trend Chart ──────────────────────────────────────────────────────────
    const rawData = <?= json_encode(array_map(function($m) {
        return [
            'id'         => (int)$m['id'],
            'date'       => $m['measured_at'],
            'site'       => $m['wound_site'],
            'length'     => (float)$m['length_cm'],
            'width'      => (float)$m['width_cm'],
            'depth'      => (float)$m['depth_cm'],
            'area'       => round((float)$m['length_cm'] * (float)$m['width_cm'], 2),
        ];
    }, $woundMeasurements)) ?>;

    const COLORS = [
        '#6366f1','#0ea5e9','#10b981','#f59e0b','#ef4444',
        '#8b5cf6','#ec4899','#14b8a6','#f97316','#84cc16',
    ];

    let chartInstance = null;

    function buildDatasets(data) {
        const sites = [...new Set(data.map(d => d.site))];
        return sites.map((site, i) => {
            const pts = data.filter(d => d.site === site);
            return {
                label: site,
                data: pts.map(p => ({x: p.date, y: p.area})),
                borderColor: COLORS[i % COLORS.length],
                backgroundColor: COLORS[i % COLORS.length] + '22',
                tension: 0.35,
                pointRadius: 5,
                pointHoverRadius: 7,
                fill: false,
            };
        });
    }

    function rebuildChart() {
        // Gather remaining rows from DOM
        const remaining = [];
        document.querySelectorAll('[data-wound-row]').forEach(row => {
            remaining.push({
                id:     parseInt(row.dataset.woundRow),
                date:   row.dataset.date,
                site:   row.dataset.site,
                area:   parseFloat(row.dataset.area),
            });
        });
        remaining.sort((a,b) => a.date.localeCompare(b.date));

        if (chartInstance) {
            chartInstance.data.datasets = buildDatasets(remaining.map(r => ({...r, length:0, width:0})));
            chartInstance.update();
        }
    }

    const ctx = document.getElementById('woundChart');
    if (ctx && rawData.length > 0) {
        const datasets = buildDatasets(rawData);
        chartInstance = new Chart(ctx, {
            type: 'line',
            data: { datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16, font: { size: 12 } } },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.y} cm²`,
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'category',
                        title: { display: true, text: 'Date', font: { size: 11 } },
                        grid: { color: '#f1f5f9' },
                    },
                    y: {
                        title: { display: true, text: 'Area (L × W cm²)', font: { size: 11 } },
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                    }
                }
            }
        });
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

<?php if ($activeVisit): ?>
<!-- ── Visit In Progress Banner ─────────────────────────────────────────────── -->
<div id="visitBanner" class="bg-gradient-to-r from-emerald-600 to-emerald-500 rounded-2xl px-5 py-4 mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-md no-print">
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-white/20 rounded-xl grid place-items-center flex-shrink-0">
            <i class="bi bi-play-circle-fill text-white text-xl"></i>
        </div>
        <div>
            <p class="text-white font-bold text-sm">Visit In Progress</p>
            <p class="text-emerald-100 text-xs mt-0.5">
                <?= date('l, F j', strtotime($activeVisit['visit_date'])) ?>
                <?php if ($activeVisit['visit_time']): ?>
                &bull; <?= date('g:i A', strtotime($activeVisit['visit_time'])) ?>
                <?php endif; ?>
                &bull; Status: <strong class="text-white">En Route</strong>
            </p>
        </div>
    </div>
    <div class="flex gap-2 flex-wrap">
        <button onclick="completeVisit(<?= $activeVisit['id'] ?>)"
                id="completeVisitBtn"
                class="inline-flex items-center gap-2 bg-white text-emerald-700 font-bold text-sm
                       px-5 py-2.5 rounded-xl hover:bg-emerald-50 active:scale-95 transition-all shadow-sm">
            <i class="bi bi-check-circle-fill"></i> Mark Complete
        </button>
        <?php if ($activeVisit['patient_address'] ?? $patient['address']): 
            $addr = htmlspecialchars(urlencode($patient['address'] ?? ''));
        ?>
        <a href="https://www.google.com/maps/search/?api=1&query=<?= $addr ?>" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 bg-white/20 hover:bg-white/30 text-white font-semibold text-sm
                  px-4 py-2.5 rounded-xl transition-colors">
            <i class="bi bi-map-fill"></i> Navigate
        </a>
        <?php endif; ?>
    </div>
</div>
<script>
function completeVisit(visitId) {
    const btn = document.getElementById('completeVisitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving…';
    fetch('<?= BASE_URL ?>/api/schedule_update.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({csrf: '<?= csrfToken() ?>', id: visitId, action: 'status', status: 'completed'})
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            document.getElementById('visitBanner').innerHTML =
                '<div class="flex items-center gap-3 text-white"><i class="bi bi-check-circle-fill text-xl"></i>' +
                '<span class="font-bold">Visit marked complete!</span></div>';
            document.getElementById('visitBanner').className =
                document.getElementById('visitBanner').className.replace('from-emerald-600 to-emerald-500', 'from-slate-500 to-slate-400');
            setTimeout(() => document.getElementById('visitBanner').remove(), 3000);
        } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Mark Complete';
            alert(d.error || 'Could not update visit.');
        }
    })
    .catch(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Mark Complete'; });
}
</script>
<?php endif; ?>

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
            <?php if ($activeVisit): ?>
            <a href="<?= BASE_URL ?>/schedule.php"
               class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-emerald-700
                      bg-emerald-50 hover:bg-emerald-100 rounded-xl transition-colors">
                <i class="bi bi-calendar3"></i> Back to Schedule
            </a>
            <?php endif; ?>
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

<?php if ($lastVisit && canAccessClinical()): ?>
<!-- ── Last Visit Summary Strip ──────────────────────────────────────────────── -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-4 mb-6 no-print">
    <div class="flex items-center gap-2 mb-3">
        <i class="bi bi-clock-history text-blue-500"></i>
        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Last Visit</span>
        <span class="ml-auto text-xs text-slate-400">
            <?= date('M j, Y', strtotime($lastVisit['visit_date'])) ?>
            <?php if ($lastVisit['visit_time']): ?>
            &bull; <?= date('g:i A', strtotime($lastVisit['visit_time'])) ?>
            <?php endif; ?>
        </span>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">

        <!-- MA who visited -->
        <div class="flex items-start gap-2.5 bg-slate-50 rounded-xl p-3">
            <div class="w-8 h-8 rounded-lg bg-blue-100 grid place-items-center flex-shrink-0">
                <i class="bi bi-person-fill text-blue-600 text-sm"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-slate-400 font-medium">MA</p>
                <p class="text-sm font-semibold text-slate-700 truncate"><?= h($lastVisit['ma_name'] ?? 'Unknown') ?></p>
            </div>
        </div>

        <!-- Status -->
        <div class="flex items-start gap-2.5 bg-slate-50 rounded-xl p-3">
            <div class="w-8 h-8 rounded-lg <?= $lastVisit['status'] === 'completed' ? 'bg-emerald-100' : 'bg-amber-100' ?> grid place-items-center flex-shrink-0">
                <i class="bi <?= $lastVisit['status'] === 'completed' ? 'bi-check-circle-fill text-emerald-600' : 'bi-arrow-right-circle-fill text-amber-600' ?> text-sm"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-slate-400 font-medium">Status</p>
                <p class="text-sm font-semibold text-slate-700 capitalize"><?= h(str_replace('_', ' ', $lastVisit['status'])) ?></p>
            </div>
        </div>

        <!-- Vitals snapshot -->
        <div class="flex items-start gap-2.5 bg-slate-50 rounded-xl p-3">
            <div class="w-8 h-8 rounded-lg bg-red-100 grid place-items-center flex-shrink-0">
                <i class="bi bi-heart-pulse-fill text-red-500 text-sm"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-slate-400 font-medium">Vitals</p>
                <?php if ($lastVitals): ?>
                <p class="text-sm font-semibold text-slate-700 leading-snug">
                    <?php
                    $vParts = [];
                    if ($lastVitals['bp'])     $vParts[] = $lastVitals['bp'] . ' mmHg';
                    if ($lastVitals['hr'])     $vParts[] = $lastVitals['hr'] . ' bpm';
                    if ($lastVitals['o2'])     $vParts[] = $lastVitals['o2'] . '% O₂';
                    if ($lastVitals['temp'])   $vParts[] = $lastVitals['temp'] . '°F';
                    if ($lastVitals['weight']) $vParts[] = $lastVitals['weight'] . ' lbs';
                    echo $vParts ? h(implode(' · ', array_slice($vParts, 0, 3))) : '<span class="text-slate-400 text-xs italic">—</span>';
                    ?>
                </p>
                <?php else: ?>
                <p class="text-xs text-slate-400 italic mt-0.5">No vitals recorded</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Forms completed -->
        <div class="flex items-start gap-2.5 bg-slate-50 rounded-xl p-3">
            <div class="w-8 h-8 rounded-lg bg-violet-100 grid place-items-center flex-shrink-0">
                <i class="bi bi-file-earmark-check-fill text-violet-600 text-sm"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-slate-400 font-medium">Forms</p>
                <p class="text-sm font-semibold text-slate-700">
                    <?= $lastVisitFormCount ?> completed
                </p>
            </div>
        </div>

    </div>
    <?php if (!empty($lastVisit['notes'])): ?>
    <div class="mt-3 pt-3 border-t border-slate-100 flex items-start gap-2 text-xs text-slate-500">
        <i class="bi bi-chat-left-text-fill text-slate-300 mt-0.5 flex-shrink-0"></i>
        <span><?= h($lastVisit['notes']) ?></span>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

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
    <a href="?id=<?= $id ?>&tab=wounds"
       class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $activeTab === 'wounds' ? 'bg-white text-rose-700 shadow' : 'text-slate-500 hover:text-slate-700' ?>">
        <i class="bi bi-rulers mr-1.5"></i>Wounds
        <?php if (!empty($woundMeasurements)): ?>
        <span class="ml-1 bg-rose-100 text-rose-700 text-xs px-1.5 py-0.5 rounded-full"><?= count($woundMeasurements) ?></span>
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
<?php elseif ($activeTab === 'photos'): ?>
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
<?php
// Group photos by wound location
$photosByLocation = [];
foreach ($photos as $ph) {
    $loc = $ph['wound_location'] ?: 'Unspecified';
    $photosByLocation[$loc][] = $ph;
}
// Pass photo data to JS
$photosJson = json_encode(array_map(fn($p) => [
    'id'       => (int)$p['id'],
    'filename' => $p['filename'],
    'location' => $p['wound_location'] ?: 'Unspecified',
    'date'     => date('M j, Y', strtotime($p['created_at'])),
    'date_raw' => $p['created_at'],
    'desc'     => $p['description'] ?? '',
    'ma'       => $p['ma_name'] ?? '',
    'url'      => BASE_URL . '/uploads/photos/' . $p['filename'],
], $photos));
?>

<!-- Tab toolbar -->
<div class="flex items-center justify-between mb-4 gap-3 flex-wrap">
    <div class="flex items-center gap-2">
        <span class="text-sm font-bold text-slate-600">
            <i class="bi bi-camera-fill text-violet-500 mr-1"></i>
            <?= count($photos) ?> photo<?= count($photos) !== 1 ? 's' : '' ?>
            &nbsp;&middot;&nbsp;
            <?= count($photosByLocation) ?> site<?= count($photosByLocation) !== 1 ? 's' : '' ?>
        </span>
    </div>
    <div class="flex gap-2">
        <button id="compareToggleBtn"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl border transition-all
                       bg-white border-slate-200 text-slate-600 hover:border-violet-400 hover:text-violet-700">
            <i class="bi bi-layout-split"></i> Compare Mode
        </button>
        <a href="<?= BASE_URL ?>/forms/wound_care.php?patient_id=<?= $id ?>"
           class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-xl
                  bg-violet-600 hover:bg-violet-700 text-white transition-all shadow-sm">
            <i class="bi bi-camera-fill"></i> Add Photos
        </a>
    </div>
</div>

<!-- ── Compare Panel (hidden until 2 selected) ───────────────────────────── -->
<div id="comparePanel" class="hidden bg-white rounded-2xl shadow-sm border-2 border-violet-200 p-5 mb-5">
    <div class="flex items-center justify-between mb-4">
        <h4 class="text-sm font-bold text-slate-700 flex items-center gap-2">
            <i class="bi bi-layout-split text-violet-500"></i> Side-by-Side Comparison
        </h4>
        <button id="clearCompareBtn"
                class="text-xs font-semibold text-slate-400 hover:text-slate-600 px-3 py-1.5 rounded-lg
                       hover:bg-slate-100 transition-colors">
            <i class="bi bi-x-lg mr-1"></i>Clear
        </button>
    </div>
    <div class="grid grid-cols-2 gap-4">
        <!-- Left -->
        <div id="cmpLeft" class="space-y-3">
            <div class="aspect-square bg-slate-100 rounded-xl flex items-center justify-center text-slate-400
                        border-2 border-dashed border-slate-200" id="cmpLeftEmpty">
                <div class="text-center">
                    <i class="bi bi-1-circle text-4xl opacity-30 block mb-1"></i>
                    <span class="text-xs">Tap a photo to compare</span>
                </div>
            </div>
            <div id="cmpLeftImg" class="hidden">
                <div class="aspect-square rounded-xl overflow-hidden bg-slate-50 border-2 border-violet-400 shadow-md">
                    <img id="cmpLeftSrc" src="" alt="" class="w-full h-full object-cover">
                </div>
                <div class="mt-2 px-1">
                    <p id="cmpLeftLoc" class="text-xs font-bold text-slate-700"></p>
                    <p id="cmpLeftDate" class="text-xs text-slate-500"></p>
                    <p id="cmpLeftDesc" class="text-xs text-slate-400 mt-0.5 italic"></p>
                    <p id="cmpLeftMa" class="text-xs text-slate-400"></p>
                </div>
            </div>
        </div>
        <!-- Right -->
        <div id="cmpRight" class="space-y-3">
            <div class="aspect-square bg-slate-100 rounded-xl flex items-center justify-center text-slate-400
                        border-2 border-dashed border-slate-200" id="cmpRightEmpty">
                <div class="text-center">
                    <i class="bi bi-2-circle text-4xl opacity-30 block mb-1"></i>
                    <span class="text-xs">Tap a second photo</span>
                </div>
            </div>
            <div id="cmpRightImg" class="hidden">
                <div class="aspect-square rounded-xl overflow-hidden bg-slate-50 border-2 border-indigo-400 shadow-md">
                    <img id="cmpRightSrc" src="" alt="" class="w-full h-full object-cover">
                </div>
                <div class="mt-2 px-1">
                    <p id="cmpRightLoc" class="text-xs font-bold text-slate-700"></p>
                    <p id="cmpRightDate" class="text-xs text-slate-500"></p>
                    <p id="cmpRightDesc" class="text-xs text-slate-400 mt-0.5 italic"></p>
                    <p id="cmpRightMa" class="text-xs text-slate-400"></p>
                </div>
            </div>
        </div>
    </div>
    <!-- Progress indicator when same site selected -->
    <div id="cmpProgressBar" class="hidden mt-4 pt-4 border-t border-slate-100">
        <p class="text-xs font-semibold text-slate-500 mb-2 text-center">
            <i class="bi bi-graph-down-arrow text-emerald-500 mr-1"></i>
            Comparing same wound site — <span id="cmpSiteLabel" class="text-slate-700"></span>
        </p>
    </div>
</div>

<!-- Gallery grouped by wound site -->
<div class="space-y-6" id="photoGallery">
    <?php foreach ($photosByLocation as $location => $locPhotos): ?>
    <div>
        <div class="flex items-center gap-3 mb-3">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-violet-50 text-violet-700
                         rounded-full text-xs font-bold border border-violet-100">
                <i class="bi bi-geo-alt-fill text-violet-400 text-xs"></i>
                <?= h($location) ?>
            </span>
            <span class="text-xs text-slate-400"><?= count($locPhotos) ?> photo<?= count($locPhotos) !== 1 ? 's' : '' ?></span>
            <div class="flex-1 h-px bg-slate-100"></div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
            <?php foreach ($locPhotos as $ph): ?>
            <div class="photo-card bg-white rounded-2xl shadow-sm border-2 border-slate-100 overflow-hidden
                        cursor-pointer transition-all hover:shadow-md hover:border-violet-200 select-none"
                 data-photo-id="<?= $ph['id'] ?>"
                 onclick="photoCardClick(this)">
                <!-- Selection ring overlay -->
                <div class="photo-ring hidden absolute inset-0 rounded-2xl pointer-events-none z-10"></div>
                <div class="relative">
                    <div class="aspect-square overflow-hidden bg-slate-50">
                        <img src="<?= BASE_URL ?>/uploads/photos/<?= h($ph['filename']) ?>"
                             alt="Wound photo"
                             class="w-full h-full object-cover hover:scale-105 transition-transform duration-300 pointer-events-none">
                    </div>
                    <!-- Selection badge -->
                    <div class="photo-badge hidden absolute top-2 right-2 w-7 h-7 rounded-full flex items-center
                                justify-center text-white text-xs font-extrabold shadow-lg z-10"></div>
                </div>
                <div class="p-3">
                    <p class="text-xs font-semibold text-slate-700 truncate"><?= h($location) ?></p>
                    <p class="text-xs text-slate-400 mt-0.5"><?= date('M j, Y', strtotime($ph['created_at'])) ?></p>
                    <?php if ($ph['description']): ?>
                    <p class="text-xs text-slate-500 mt-1 line-clamp-2"><?= h($ph['description']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
(function () {
    const PHOTOS      = <?= $photosJson ?>;
    const photoMap    = {};
    PHOTOS.forEach(p => photoMap[p.id] = p);

    let compareMode   = false;
    let selected      = []; // [{id, card}]

    const toggleBtn   = document.getElementById('compareToggleBtn');
    const clearBtn    = document.getElementById('clearCompareBtn');
    const panel       = document.getElementById('comparePanel');

    // Toggle compare mode
    toggleBtn.addEventListener('click', () => {
        compareMode = !compareMode;
        if (compareMode) {
            toggleBtn.classList.remove('bg-white','border-slate-200','text-slate-600','hover:border-violet-400','hover:text-violet-700');
            toggleBtn.classList.add('bg-violet-600','border-violet-600','text-white');
            toggleBtn.innerHTML = '<i class="bi bi-layout-split"></i> Compare ON';
            panel.classList.remove('hidden');
        } else {
            exitCompare();
        }
    });

    clearBtn.addEventListener('click', clearSelection);

    function exitCompare() {
        compareMode = false;
        toggleBtn.classList.add('bg-white','border-slate-200','text-slate-600','hover:border-violet-400','hover:text-violet-700');
        toggleBtn.classList.remove('bg-violet-600','border-violet-600','text-white');
        toggleBtn.innerHTML = '<i class="bi bi-layout-split"></i> Compare Mode';
        clearSelection();
        panel.classList.add('hidden');
    }

    function clearSelection() {
        selected.forEach(s => resetCard(s.card));
        selected = [];
        updatePanel();
    }

    function resetCard(card) {
        card.classList.remove('border-violet-400','border-indigo-400','shadow-lg');
        card.classList.add('border-slate-100');
        card.querySelector('.photo-badge').classList.add('hidden');
    }

    window.photoCardClick = function(card) {
        if (!compareMode) return;
        const pid = parseInt(card.dataset.photoId);

        // If already selected, deselect it
        const existIdx = selected.findIndex(s => s.id === pid);
        if (existIdx !== -1) {
            resetCard(card);
            selected.splice(existIdx, 1);
            // Re-number remaining
            selected.forEach((s, i) => {
                const badge = s.card.querySelector('.photo-badge');
                badge.textContent = i + 1;
                badge.style.background = i === 0 ? '#7c3aed' : '#4f46e5';
            });
            updatePanel();
            return;
        }

        // Max 2
        if (selected.length >= 2) {
            // Replace the oldest (index 0)
            resetCard(selected[0].card);
            selected.shift();
            // Re-badge remaining
            selected.forEach((s, i) => {
                const badge = s.card.querySelector('.photo-badge');
                badge.textContent = i + 1;
                badge.style.background = i === 0 ? '#7c3aed' : '#4f46e5';
            });
        }

        selected.push({id: pid, card});
        const slotIndex = selected.length - 1;
        const borderCls = slotIndex === 0 ? 'border-violet-400' : 'border-indigo-400';
        card.classList.remove('border-slate-100');
        card.classList.add(borderCls, 'shadow-lg');
        const badge = card.querySelector('.photo-badge');
        badge.textContent = slotIndex + 1;
        badge.style.background = slotIndex === 0 ? '#7c3aed' : '#4f46e5';
        badge.classList.remove('hidden');
        badge.style.display = 'flex';

        updatePanel();
        // Scroll panel into view
        panel.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    };

    function fillSlot(side, photo) {
        const emptyEl = document.getElementById('cmp' + side + 'Empty');
        const imgWrap = document.getElementById('cmp' + side + 'Img');
        if (!photo) {
            emptyEl.classList.remove('hidden');
            imgWrap.classList.add('hidden');
            return;
        }
        emptyEl.classList.add('hidden');
        imgWrap.classList.remove('hidden');
        document.getElementById('cmp' + side + 'Src').src  = photo.url;
        document.getElementById('cmp' + side + 'Src').alt  = photo.location;
        document.getElementById('cmp' + side + 'Loc').textContent  = photo.location;
        document.getElementById('cmp' + side + 'Date').textContent = photo.date;
        document.getElementById('cmp' + side + 'Desc').textContent = photo.desc || '';
        document.getElementById('cmp' + side + 'Ma').textContent   = photo.ma ? 'Recorded by ' + photo.ma : '';
    }

    function updatePanel() {
        const p1 = selected[0] ? photoMap[selected[0].id] : null;
        const p2 = selected[1] ? photoMap[selected[1].id] : null;
        fillSlot('Left',  p1);
        fillSlot('Right', p2);

        // Show progress hint when same site selected
        const progressBar = document.getElementById('cmpProgressBar');
        if (p1 && p2 && p1.location === p2.location) {
            document.getElementById('cmpSiteLabel').textContent = p1.location;
            progressBar.classList.remove('hidden');
        } else {
            progressBar.classList.add('hidden');
        }
    }
})();
</script>
<?php endif; ?>

<!-- Wounds Tab -->
<?php elseif ($activeTab === 'wounds' && canAccessClinical()): ?>
<div class="space-y-5">

    <!-- Log new measurement -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
        <h4 class="text-sm font-bold text-slate-700 mb-4 flex items-center gap-2">
            <i class="bi bi-plus-circle-fill text-rose-500"></i> Log Wound Measurement
        </h4>
        <form id="woundForm" novalidate>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <div class="col-span-2 sm:col-span-1 lg:col-span-1">
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Date <span class="text-red-500">*</span></label>
                    <input type="date" name="measured_at" required value="<?= date('Y-m-d') ?>"
                           class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-rose-400 focus:bg-white transition">
                </div>
                <div class="col-span-2 sm:col-span-2 lg:col-span-2">
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Wound Site <span class="text-red-500">*</span></label>
                    <input type="text" name="wound_site" required placeholder="e.g. Left heel, Right ankle"
                           list="wound-sites-list"
                           class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-rose-400 focus:bg-white transition">
                    <datalist id="wound-sites-list">
                        <?php
                        $uniqueSites = array_unique(array_column($woundMeasurements, 'wound_site'));
                        foreach ($uniqueSites as $site): ?>
                        <option value="<?= h($site) ?>">
                        <?php endforeach; ?>
                        <option value="Left heel"><option value="Right heel">
                        <option value="Left ankle"><option value="Right ankle">
                        <option value="Left lower leg"><option value="Right lower leg">
                        <option value="Sacrum / coccyx"><option value="Left hip"><option value="Right hip">
                    </datalist>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Length (cm) <span class="text-red-500">*</span></label>
                    <input type="number" name="length_cm" required min="0.1" step="0.1" placeholder="0.0"
                           class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-rose-400 focus:bg-white transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Width (cm) <span class="text-red-500">*</span></label>
                    <input type="number" name="width_cm" required min="0.1" step="0.1" placeholder="0.0"
                           class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-rose-400 focus:bg-white transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Depth (cm)</label>
                    <input type="number" name="depth_cm" min="0" step="0.1" placeholder="0.0"
                           class="w-full px-3 py-2.5 text-sm border border-slate-200 rounded-xl bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-rose-400 focus:bg-white transition">
                </div>
            </div>
            <div class="mt-3 flex flex-col sm:flex-row gap-3">
                <input type="text" name="notes" placeholder="Notes (optional)"
                       class="flex-1 px-3 py-2.5 text-sm border border-slate-200 rounded-xl bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-rose-400 focus:bg-white transition">
                <button id="woundSubmit" type="submit"
                        class="px-6 py-2.5 bg-rose-600 hover:bg-rose-700 active:scale-95 text-white text-sm
                               font-semibold rounded-xl transition-all shadow-sm flex items-center gap-2 whitespace-nowrap">
                    <i class="bi bi-plus-lg"></i> Log Measurement
                </button>
            </div>
            <p id="woundErr" class="text-xs text-red-600 mt-2 hidden"></p>
        </form>
    </div>

    <?php if (!empty($woundMeasurements)): ?>

    <!-- Trend chart -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
        <div class="flex items-center gap-2 mb-4">
            <i class="bi bi-graph-up-arrow text-rose-500"></i>
            <h4 class="text-sm font-bold text-slate-700">Healing Trend — Wound Area (L × W)</h4>
        </div>
        <div class="relative" style="height:260px">
            <canvas id="woundChart"></canvas>
        </div>
        <p class="text-xs text-slate-400 mt-3 text-center">
            Smaller area = healing progress &nbsp;|&nbsp; Each line represents a distinct wound site
        </p>
    </div>

    <!-- History table -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="flex items-center gap-2 px-5 py-3.5 bg-slate-50/70 border-b border-slate-100">
            <i class="bi bi-table text-slate-400"></i>
            <span class="text-sm font-bold text-slate-700">Measurement History</span>
            <span class="text-xs text-slate-400">(<?= count($woundMeasurements) ?> records)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left border-b border-slate-100 bg-slate-50/40">
                        <th class="px-5 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide">Date</th>
                        <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide">Wound Site</th>
                        <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide text-center">L (cm)</th>
                        <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide text-center">W (cm)</th>
                        <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide text-center">D (cm)</th>
                        <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide text-center">Area cm²</th>
                        <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide hidden md:table-cell">Notes</th>
                        <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide hidden lg:table-cell">Recorded By</th>
                        <?php if (isAdmin()): ?><th class="px-4 py-3 w-12"></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50" id="woundTableBody">
                    <?php
                    // Sort descending for display
                    $woundDesc = array_reverse($woundMeasurements);
                    foreach ($woundDesc as $wm):
                        $area = round((float)$wm['length_cm'] * (float)$wm['width_cm'], 2);
                    ?>
                    <tr class="hover:bg-slate-50/70 transition-colors"
                        data-wound-row="<?= $wm['id'] ?>"
                        data-date="<?= h($wm['measured_at']) ?>"
                        data-site="<?= h($wm['wound_site']) ?>"
                        data-area="<?= $area ?>">
                        <td class="px-5 py-3.5 font-medium text-slate-700 whitespace-nowrap">
                            <?= date('M j, Y', strtotime($wm['measured_at'])) ?>
                        </td>
                        <td class="px-4 py-3.5 text-slate-600 max-w-[160px] truncate">
                            <?= h($wm['wound_site']) ?>
                        </td>
                        <td class="px-4 py-3.5 text-center font-mono text-slate-700"><?= number_format($wm['length_cm'], 1) ?></td>
                        <td class="px-4 py-3.5 text-center font-mono text-slate-700"><?= number_format($wm['width_cm'],  1) ?></td>
                        <td class="px-4 py-3.5 text-center font-mono text-slate-700">
                            <?= $wm['depth_cm'] > 0 ? number_format($wm['depth_cm'], 1) : '—' ?>
                        </td>
                        <td class="px-4 py-3.5 text-center">
                            <span class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold
                                         bg-rose-50 text-rose-700 font-mono">
                                <?= number_format($area, 2) ?>
                            </span>
                        </td>
                        <td class="px-4 py-3.5 text-slate-500 text-xs hidden md:table-cell max-w-[180px] truncate">
                            <?= $wm['notes'] ? h($wm['notes']) : '—' ?>
                        </td>
                        <td class="px-4 py-3.5 text-slate-400 text-xs hidden lg:table-cell">
                            <?= h($wm['recorded_by_name'] ?? '—') ?>
                        </td>
                        <?php if (isAdmin()): ?>
                        <td class="px-4 py-3.5">
                            <button class="del-wound-btn" data-id="<?= $wm['id'] ?>"
                                    title="Delete measurement"
                                    style="background:none;border:none;cursor:pointer;color:#94a3b8;padding:4px 6px;border-radius:8px;"
                                    onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='#94a3b8'">
                                <i class="bi bi-trash3 text-sm"></i>
                            </button>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php else: ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 flex flex-col items-center justify-center py-16 text-slate-400">
        <i class="bi bi-rulers text-5xl mb-3 opacity-30"></i>
        <p class="font-semibold text-slate-500">No measurements yet</p>
        <p class="text-sm mt-1">Use the form above to log the first wound measurement.</p>
    </div>
    <?php endif; ?>

</div><!-- /wounds tab -->

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
