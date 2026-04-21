<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/audit.php';
require_once __DIR__ . '/includes/visit_types.php';
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
// Billing users can only see the forms tab; non-admins cannot see the audit tab
if (isBilling() && in_array($activeTab, ['meds', 'photos', 'wounds', 'diagnoses', 'vitals', 'care', 'notes', 'audit'], true)) {
    $activeTab = 'forms';
}
if ($activeTab === 'audit' && !isAdmin()) {
    $activeTab = 'forms';
}
$msg = $_GET['msg'] ?? '';

$photoCsrf = csrfToken();

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
    'wound_care_consent'     => ['label' => 'Wound Care Consent',              'icon' => 'bi-bandaid',                  'bg' => 'bg-rose-100',    'text' => 'text-rose-600'],
    'informed_consent_wound' => ['label' => 'Informed Consent – Wound Care', 'icon' => 'bi-file-earmark-medical',     'bg' => 'bg-red-100',     'text' => 'text-red-700'],
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
        SELECT sc.visit_date, sc.visit_time, sc.status, sc.notes, sc.visit_type,
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

// ── Diagnoses tab data ───────────────────────────────────────────────────────
try {
    $diagStmt = $pdo->prepare("
        SELECT pd.*, s.full_name AS added_by_name
        FROM patient_diagnoses pd
        LEFT JOIN staff s ON s.id = pd.added_by
        WHERE pd.patient_id = ?
        ORDER BY pd.added_at DESC
    ");
    $diagStmt->execute([$id]);
    $diagList = $diagStmt->fetchAll();
} catch (PDOException $e) {
    $diagList = [];
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

if ($activeTab === 'diagnoses' && canAccessClinical()) {
    $diagCsrf = csrfToken();
    ob_start(); ?>
<script>
(function () {
    const PID  = <?= (int)$id ?>;
    const BASE = <?= json_encode(BASE_URL) ?>;
    const CSRF = <?= json_encode($diagCsrf) ?>;

    let selectedCode = '', selectedDesc = '';
    let searchTimer  = null;

    const searchEl   = document.getElementById('diag-search');
    const dropdown   = document.getElementById('diag-dropdown');
    const selBox     = document.getElementById('diag-selected');
    const selCode    = document.getElementById('diag-sel-code');
    const selDesc    = document.getElementById('diag-sel-desc');
    const notesEl    = document.getElementById('diag-notes');
    const errEl      = document.getElementById('diag-error');
    const listEl     = document.getElementById('diag-list');
    const emptyEl    = document.getElementById('diag-empty');

    if (searchEl) {
        searchEl.addEventListener('input', () => {
            clearTimeout(searchTimer);
            const q = searchEl.value.trim();
            if (q.length < 2) { dropdown.classList.add('hidden'); return; }
            searchTimer = setTimeout(() => fetchSuggestions(q), 250);
        });

        document.addEventListener('click', e => {
            if (!e.target.closest('#diag-search') && !e.target.closest('#diag-dropdown')) {
                dropdown.classList.add('hidden');
            }
        });
    }

    async function fetchSuggestions(q) {
        try {
            const r = await fetch(BASE + '/api/icd10_search.php?q=' + encodeURIComponent(q));
            const data = await r.json();
            renderDropdown(data);
        } catch { dropdown.classList.add('hidden'); }
    }

    function renderDropdown(items) {
        if (!items.length) { dropdown.classList.add('hidden'); return; }
        dropdown.innerHTML = items.map(item =>
            `<li class="flex items-center gap-2 px-3 py-2 hover:bg-orange-50 cursor-pointer"
                 data-code="${esc(item.code)}" data-desc="${esc(item.desc)}">
                <span class="font-mono text-orange-600 font-bold text-xs w-20 flex-shrink-0">${esc(item.code)}</span>
                <span class="text-slate-700 text-xs truncate">${esc(item.desc)}</span>
             </li>`
        ).join('');
        dropdown.querySelectorAll('li').forEach(li => {
            li.addEventListener('click', () => {
                selectedCode = li.dataset.code;
                selectedDesc = li.dataset.desc;
                searchEl.value = '';
                dropdown.classList.add('hidden');
                selCode.textContent = selectedCode;
                selDesc.textContent = selectedDesc;
                selBox.classList.remove('hidden');
                if (errEl) { errEl.classList.add('hidden'); }
            });
        });
        dropdown.classList.remove('hidden');
    }

    window.diagClear = function() {
        selectedCode = ''; selectedDesc = '';
        selBox.classList.add('hidden');
        if (searchEl) { searchEl.value = ''; searchEl.focus(); }
    };

    window.diagAdd = async function() {
        if (!selectedCode) {
            showErr('Please search and select an ICD-10 code first.'); return;
        }
        const btn = document.getElementById('diag-add-btn');
        btn.disabled = true;
        try {
            const r = await fetch(BASE + '/api/diagnoses.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'add', csrf: CSRF, patient_id: PID,
                    icd_code: selectedCode, icd_desc: selectedDesc,
                    notes: notesEl ? notesEl.value.trim() : '',
                }),
            });
            const data = await r.json();
            if (data.ok) {
                prependDiag(data.diagnosis);
                diagClear();
                if (notesEl) notesEl.value = '';
                if (emptyEl) emptyEl.remove();
            } else {
                showErr(data.error || 'Could not add diagnosis.');
            }
        } catch { showErr('Network error. Please try again.'); }
        btn.disabled = false;
    };

    function prependDiag(dx) {
        const date = new Date(dx.added_at).toLocaleDateString('en-US');
        const row = document.createElement('div');
        row.className = 'diag-row flex items-start gap-3 px-5 py-3 border-b border-slate-50 hover:bg-slate-50 transition';
        row.dataset.id = dx.id;
        row.innerHTML = `
            <span class="font-mono font-bold text-orange-600 bg-orange-50 px-2 py-0.5 rounded text-sm whitespace-nowrap mt-0.5">${esc(dx.icd_code)}</span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-slate-700">${esc(dx.icd_desc)}</p>
                ${dx.notes ? `<p class="text-xs text-slate-500 mt-0.5">${esc(dx.notes)}</p>` : ''}
                <p class="text-xs text-slate-400 mt-0.5">Added by ${esc(dx.added_by_name || 'You')} &bull; ${date}</p>
            </div>
            <button onclick="diagRemove(${dx.id}, this)" class="text-slate-300 hover:text-red-500 transition flex-shrink-0 mt-0.5">
                <i class="bi bi-trash3"></i>
            </button>`;
        listEl.prepend(row);
    }

    window.diagRemove = async function(diagId, btn) {
        if (!confirm('Remove this diagnosis?')) return;
        btn.disabled = true;
        try {
            const r = await fetch(BASE + '/api/diagnoses.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action:'remove', csrf:CSRF, patient_id:PID, id:diagId}),
            });
            const data = await r.json();
            if (data.ok) {
                btn.closest('.diag-row').remove();
            } else {
                alert(data.error || 'Could not remove.');
                btn.disabled = false;
            }
        } catch { alert('Network error.'); btn.disabled = false; }
    };

    function showErr(msg) {
        if (errEl) { errEl.textContent = msg; errEl.classList.remove('hidden'); }
    }

    function esc(s) {
        return s ? String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;') : '';
    }
})();
</script>
<?php
    $extraJs = ob_get_clean();
}

// ── SOAP notes ─────────────────────────────────────────────────
$soapNotes = [];
if ($activeTab === 'notes' && canAccessClinical()) {
    $snStmt = $pdo->prepare("
        SELECT sn.id, sn.note_date, sn.status, sn.assessment, sn.finalized_at,
               s.full_name AS author_name
        FROM soap_notes sn
        JOIN staff s ON s.id = sn.author_id
        WHERE sn.patient_id = ?
        ORDER BY sn.note_date DESC, sn.id DESC
    ");
    $snStmt->execute([$id]);
    $soapNotes = $snStmt->fetchAll();
}

// ── Care coordination notes ────────────────────────────────────
$careNotes = [];
if ($activeTab === 'care' && canAccessClinical()) {
    $cnStmt = $pdo->prepare("
        SELECT cn.id, cn.parent_id, cn.body, cn.pinned, cn.edited_at, cn.created_at,
               s.full_name AS author_name, s.id AS author_id_val, s.role AS author_role
        FROM care_notes cn
        JOIN staff s ON s.id = cn.author_id
        WHERE cn.patient_id = ?
        ORDER BY cn.pinned DESC, cn.created_at ASC
    ");
    $cnStmt->execute([$id]);
    $allCn = $cnStmt->fetchAll();
    // Separate top-level vs replies
    $cnTop = [];
    $cnReplies = [];
    foreach ($allCn as $cn) {
        if ($cn['parent_id'] === null) {
            $cnTop[(int)$cn['id']] = $cn;
        } else {
            $cnReplies[(int)$cn['parent_id']][] = $cn;
        }
    }
    $careNotes = ['top' => $cnTop, 'replies' => $cnReplies];
}

// ── Vitals trend data ───────────────────────────────────────────
$vitalsRows      = [];
$vitalsLatest    = [];
$vitalsChartJson = 'null';
$vSysArr = [];
if ($activeTab === 'vitals' && canAccessClinical()) {
    $vStmt = $pdo->prepare("
        SELECT form_data, created_at
        FROM form_submissions
        WHERE patient_id = ? AND form_type = 'vital_cs'
        ORDER BY created_at ASC
        LIMIT 60
    ");
    $vStmt->execute([$id]);
    $vitalForms = $vStmt->fetchAll();

    $vLabels = $vSysArr = $vDiasArr = $vWeightArr = $vO2Arr = $vPulseArr = $vTempArr = $vGlucoseArr = $vRespArr = [];
    foreach ($vitalForms as $vf) {
        $fd  = json_decode($vf['form_data'], true) ?? [];
        $vLabels[] = date('M j, Y', strtotime($vf['created_at']));

        $bp = trim($fd['bp'] ?? '');
        if (preg_match('/([0-9]+)\s*\/\s*([0-9]+)/', $bp, $m)) {
            $vSysArr[]  = (int)$m[1];
            $vDiasArr[] = (int)$m[2];
        } else { $vSysArr[] = null; $vDiasArr[] = null; }

        preg_match('/([0-9]+\.?[0-9]*)/', $fd['weight']  ?? '', $wM);  $vWeightArr[]  = isset($wM[1])  ? (float)$wM[1]  : null;
        preg_match('/([0-9]+\.?[0-9]*)/', $fd['o2sat']   ?? '', $oM);  $vO2Arr[]      = isset($oM[1])  ? (float)$oM[1]  : null;
        preg_match('/([0-9]+)/',           $fd['pulse']   ?? '', $pM);  $vPulseArr[]   = isset($pM[1])  ? (int)$pM[1]   : null;
        preg_match('/([0-9]+\.?[0-9]*)/', $fd['temp']    ?? '', $tM);  $vTempArr[]    = isset($tM[1])  ? (float)$tM[1]  : null;
        preg_match('/([0-9]+\.?[0-9]*)/', $fd['glucose'] ?? '', $gM);  $vGlucoseArr[] = isset($gM[1])  ? (float)$gM[1]  : null;
        preg_match('/([0-9]+)/',           $fd['resp']    ?? '', $rM);  $vRespArr[]    = isset($rM[1])  ? (int)$rM[1]   : null;

        $vitalsRows[] = [
            'date'    => $vf['created_at'],
            'bp'      => $fd['bp']      ?? '',
            'pulse'   => $fd['pulse']   ?? '',
            'temp'    => $fd['temp']    ?? '',
            'o2sat'   => $fd['o2sat']   ?? '',
            'glucose' => $fd['glucose'] ?? '',
            'weight'  => $fd['weight']  ?? '',
            'resp'    => $fd['resp']    ?? '',
        ];
    }
    foreach (['systolic'=>$vSysArr,'diastolic'=>$vDiasArr,'weight'=>$vWeightArr,'o2sat'=>$vO2Arr,'pulse'=>$vPulseArr,'temp'=>$vTempArr,'glucose'=>$vGlucoseArr,'resp'=>$vRespArr] as $k => $arr) {
        foreach (array_reverse($arr) as $v) { if ($v !== null) { $vitalsLatest[$k] = $v; break; } }
    }
    $vitalsChartJson = json_encode(['labels'=>$vLabels,'systolic'=>$vSysArr,'diastolic'=>$vDiasArr,'weight'=>$vWeightArr,'o2sat'=>$vO2Arr,'pulse'=>$vPulseArr,'temp'=>$vTempArr,'glucose'=>$vGlucoseArr,'resp'=>$vRespArr]);
    ob_start(); ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    const cd = <?= $vitalsChartJson ?>;
    if (!cd || !cd.labels.length) return;

    function mkOpts(yLabel, yMin, yMax) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 400 },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom', labels: { font: { size: 11 }, boxWidth: 12, padding: 12 } },
                tooltip: { backgroundColor: '#0f172a', titleFont: { size: 11 }, bodyFont: { size: 12, weight: '600' }, padding: 10, cornerRadius: 10 },
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#94a3b8', maxTicksLimit: 8 } },
                y: { min: yMin, max: yMax, grid: { color: '#f1f5f9' }, ticks: { font: { size: 10 }, color: '#94a3b8' }, title: { display: !!yLabel, text: yLabel, font: { size: 10 }, color: '#94a3b8' } },
            },
        };
    }
    function mkDs(label, data, color, dash) {
        return { label, data, borderColor: color, backgroundColor: color + '22', borderWidth: 2.5, pointRadius: 4, pointHoverRadius: 6, fill: false, tension: 0.35, spanGaps: true, borderDash: dash || [] };
    }

    /* BP chart */
    const bpCtx = document.getElementById('vChartBP');
    if (bpCtx && (cd.systolic.some(v=>v!==null) || cd.diastolic.some(v=>v!==null))) {
        new Chart(bpCtx, {
            type: 'line',
            data: { labels: cd.labels, datasets: [
                mkDs('Systolic',  cd.systolic,  '#ef4444'),
                mkDs('Diastolic', cd.diastolic, '#f97316', [5,3]),
            ]},
            options: mkOpts('mmHg'),
        });
    }

    /* Other vitals chart */
    const metricCfg = {
        o2sat:   { label: 'O2 Sat (%)',      data: cd.o2sat,    color: '#06b6d4', yLabel: '%',     yMin: 80,  yMax: 100 },
        pulse:   { label: 'Pulse (bpm)',     data: cd.pulse,    color: '#8b5cf6', yLabel: 'bpm' },
        weight:  { label: 'Weight (lbs)',    data: cd.weight,   color: '#0ea5e9', yLabel: 'lbs' },
        temp:    { label: 'Temp (°F)',       data: cd.temp,     color: '#f59e0b', yLabel: '°F',    yMin: 94,  yMax: 106 },
        glucose: { label: 'Glucose (mg/dL)',data: cd.glucose,  color: '#10b981', yLabel: 'mg/dL' },
        resp:    { label: 'Resp Rate (/min)',data: cd.resp,     color: '#64748b', yLabel: '/min' },
    };
    let curChart = null;
    const otherCtx = document.getElementById('vChartOther');
    function showMetric(key) {
        if (curChart) { curChart.destroy(); curChart = null; }
        const cfg = metricCfg[key];
        if (!otherCtx || !cfg) return;
        curChart = new Chart(otherCtx, {
            type: 'line',
            data: { labels: cd.labels, datasets: [mkDs(cfg.label, cfg.data, cfg.color)] },
            options: mkOpts(cfg.yLabel, cfg.yMin, cfg.yMax),
        });
    }
    const metricOrder = ['o2sat','pulse','weight','temp','glucose','resp'];
    let defMetric = metricOrder.find(m => (cd[m] || []).some(v=>v!==null)) || 'o2sat';
    showMetric(defMetric);
    document.querySelectorAll('.vt-btn').forEach(btn => {
        if (btn.dataset.metric === defMetric) {
            btn.classList.add('bg-indigo-600','text-white','shadow-sm');
            btn.classList.remove('bg-white','text-slate-600');
        }
        btn.addEventListener('click', function () {
            document.querySelectorAll('.vt-btn').forEach(b => {
                b.classList.remove('bg-indigo-600','text-white','shadow-sm');
                b.classList.add('bg-white','text-slate-600');
            });
            this.classList.add('bg-indigo-600','text-white','shadow-sm');
            this.classList.remove('bg-white','text-slate-600');
            showMetric(this.dataset.metric);
        });
    });
})();
</script>
<?php
    $extraJs = ob_get_clean();
}

// ── Per-patient audit trail (admin only) ─────────────────────
$patientAudit = [];
if ($activeTab === 'audit' && isAdmin()) {
    $auditStmt = $pdo->prepare("
        SELECT al.*
        FROM audit_log al
        WHERE (al.target_type = 'patient' AND al.target_id = ?)
           OR (al.target_type = 'form'
               AND al.target_id IN (SELECT id FROM form_submissions WHERE patient_id = ?))
        ORDER BY al.created_at DESC, al.id DESC
        LIMIT 300
    ");
    $auditStmt->execute([$id, $id]);
    $patientAudit = $auditStmt->fetchAll();
}

include __DIR__ . '/includes/header.php';
// Inline script for status widget (always needed on this page)
$statusCsrfInline = csrfToken();
?>
<script>
(function(){
    const BASE = <?= json_encode(BASE_URL) ?>;
    const CSRF = <?= json_encode($statusCsrfInline) ?>;
    const PID  = <?= (int)$id ?>;
    let currentStatus = <?= json_encode($patient['status'] ?? 'active') ?>;
    let pendingStatus = null;

    /* ---- Modal element refs (lazily resolved after DOM ready) ---- */
    let modal, pwInput, pwErr, pwErrMsg, pwBtn, pwLabel;

    document.addEventListener('DOMContentLoaded', function () {
        modal    = document.getElementById('statusPwModal');
        pwInput  = document.getElementById('statusPwInput');
        pwErr    = document.getElementById('statusPwErr');
        pwErrMsg = document.getElementById('statusPwErrMsg');
        pwBtn    = document.getElementById('statusPwConfirmBtn');
        pwLabel  = document.getElementById('statusPwLabel');

        modal   && modal.addEventListener('click', e => { if (e.target === modal) window.closeStatusModal(); });
        pwInput && pwInput.addEventListener('keydown', e => { if (e.key === 'Enter') window.confirmStatusChange(); });

        const ddInput = document.getElementById('discharge-date');
        if (ddInput) {
            ddInput.addEventListener('change', () => {
                if (currentStatus === 'discharged') openModal('discharged');
            });
        }
    });

    function openModal(status) {
        pendingStatus = status;
        pwInput.value = '';
        pwErr.classList.add('hidden');
        pwLabel.textContent = 'Mark as ' + status.charAt(0).toUpperCase() + status.slice(1);
        modal.classList.remove('hidden');
        requestAnimationFrame(() => pwInput.focus());
    }
    window.closeStatusModal = function() {
        modal.classList.add('hidden');
        pendingStatus = null;
    };

    window.confirmStatusChange = async function() {
        const pw = pwInput.value;
        if (!pw) { pwErrMsg.textContent = 'Please enter your password.'; pwErr.classList.remove('hidden'); return; }
        pwErr.classList.add('hidden');
        pwBtn.disabled = true;
        pwBtn.innerHTML = '<span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>';
        await doSetStatus(pendingStatus, pw);
        pwBtn.disabled = false;
        pwBtn.innerHTML = 'Confirm';
    };

    window.setPatientStatus = function(status) {
        const ddWrap = document.getElementById('discharge-date-wrap');
        if (status === 'discharged') {
            ddWrap && ddWrap.classList.remove('hidden');
        } else {
            ddWrap && ddWrap.classList.add('hidden');
        }
        openModal(status);
    };

    async function doSetStatus(status, password) {
        const dischargedAt = status === 'discharged'
            ? (document.getElementById('discharge-date')?.value || '')
            : '';

        try {
            const r = await fetch(BASE + '/api/patient_status.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({csrf: CSRF, patient_id: PID, status, discharged_at: dischargedAt, password}),
            });
            const data = await r.json();
            if (!data.ok) {
                pwErrMsg.textContent = data.error || 'Could not update status.';
                pwErr.classList.remove('hidden');
                return;
            }

            window.closeStatusModal();
            currentStatus = status;

            // Update button styles
            const colorMap = {active:'emerald', inactive:'amber', discharged:'red'};
            document.querySelectorAll('#status-widget button').forEach(btn => {
                const sv = btn.getAttribute('onclick').match(/'(\w+)'/)?.[1];
                if (!sv) return;
                const c = colorMap[sv];
                const active = sv === status;
                btn.className = `px-3.5 py-1.5 rounded-xl text-xs font-semibold border transition ` +
                    (active
                        ? `bg-${c}-100 text-${c}-700 border-${c}-300 ring-2 ring-${c}-300`
                        : `bg-white text-slate-600 border-slate-200 hover:border-${c}-300 hover:text-${c}-700`);
            });
            // Update header badge
            const badge = document.querySelector('.pt-status-badge');
            if (badge) {
                const badgeMap = {active:'bg-emerald-100 text-emerald-700', inactive:'bg-amber-100 text-amber-700', discharged:'bg-red-100 text-red-700'};
                badge.className = 'pt-status-badge text-xs font-semibold px-2.5 py-0.5 rounded-full ' + (badgeMap[status] || badgeMap.active);
                badge.textContent = status.charAt(0).toUpperCase() + status.slice(1);
            }
            const msg = document.getElementById('status-msg');
            if (msg) { msg.classList.remove('hidden'); setTimeout(()=>msg.classList.add('hidden'), 2000); }
        } catch { pwErrMsg.textContent = 'Network error.'; pwErr.classList.remove('hidden'); }
    }

    // Live discharge date: re-save when user changes date
    const ddInput = document.getElementById('discharge-date');
    if (ddInput) {
        ddInput.addEventListener('change', () => {
            if (currentStatus === 'discharged') openModal('discharged');
        });
    }
})();
</script>

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
            <!-- Patient avatar: photo if set, else initials gradient -->
            <div class="relative flex-shrink-0 group" id="ptAvatarWrap">
                <?php if (!empty($patient['photo_url'])): ?>
                <img id="ptAvatarImg"
                     src="<?= h($patient['photo_url']) ?>"
                     alt="<?= h($patient['first_name']) ?>"
                     class="w-14 h-14 rounded-2xl object-cover shadow-lg border-2 border-white ring-2 ring-blue-100">
                <?php else: ?>
                <div id="ptAvatarImg"
                     class="w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-700 grid place-items-center
                            text-white font-extrabold text-xl shadow-lg">
                    <?= strtoupper(substr($patient['first_name'],0,1) . substr($patient['last_name'],0,1)) ?>
                </div>
                <?php endif; ?>
                <?php if (canAccessClinical()): ?>
                <!-- Upload overlay (hover) -->
                <label for="ptPhotoInput"
                       class="absolute inset-0 rounded-2xl bg-black/50 grid place-items-center cursor-pointer
                              opacity-0 group-hover:opacity-100 transition-opacity"
                       title="Change photo">
                    <i class="bi bi-camera-fill text-white text-lg"></i>
                </label>
                <input type="file" id="ptPhotoInput" accept="image/jpeg,image/png,image/webp,image/gif"
                       class="hidden">
                <?php endif; ?>
            </div>
            <div>
                <h2 class="text-xl font-extrabold text-slate-800 flex items-center gap-2 flex-wrap">
                    <?= h($patient['first_name'] . ' ' . $patient['last_name']) ?>
                    <?php
                    $ptStMap = [
                        'active'     => 'bg-emerald-100 text-emerald-700',
                        'inactive'   => 'bg-amber-100 text-amber-700',
                        'discharged' => 'bg-red-100 text-red-700',
                    ];
                    $ptStatus = $patient['status'] ?? 'active';
                    $ptStCls  = $ptStMap[$ptStatus] ?? $ptStMap['active'];
                    ?>
                    <span class="pt-status-badge text-xs font-semibold px-2.5 py-0.5 rounded-full <?= $ptStCls ?>">
                        <?= ucfirst($ptStatus) ?>
                    </span>
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
        <?php if (!empty($patient['discharged_at']) && ($patient['status'] ?? '') === 'discharged'): ?>
        <span class="text-red-500"><i class="bi bi-calendar-x mr-1"></i>Discharged: <?= date('M j, Y', strtotime($patient['discharged_at'])) ?></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (canAccessClinical()): ?>
    <!-- Inline Status Change -->
    <div class="mt-4 pt-4 border-t border-slate-100 flex flex-wrap items-center gap-3 no-print" id="status-widget">
        <span class="text-xs font-bold text-slate-500 uppercase tracking-wide">Change Status:</span>
        <?php
        $statusOpts = ['active'=>['emerald','Active'], 'inactive'=>['amber','Inactive'], 'discharged'=>['red','Discharged']];
        foreach ($statusOpts as $sv => [$color, $label]):
            $isActive = ($patient['status'] ?? 'active') === $sv;
        ?>
        <button onclick="setPatientStatus('<?= $sv ?>')"
                class="px-3.5 py-1.5 rounded-xl text-xs font-semibold border transition
                       <?= $isActive
                           ? "bg-{$color}-100 text-{$color}-700 border-{$color}-300 ring-2 ring-{$color}-300"
                           : "bg-white text-slate-600 border-slate-200 hover:border-{$color}-300 hover:text-{$color}-700" ?>">
            <?= $label ?>
        </button>
        <?php endforeach; ?>
        <span id="status-msg" class="text-xs text-slate-400 hidden">Saved</span>
        <!-- Discharge date picker (shown only when discharged is active) -->
        <div id="discharge-date-wrap" class="<?= ($patient['status'] ?? 'active') !== 'discharged' ? 'hidden' : '' ?> flex items-center gap-2">
            <label class="text-xs text-slate-500">Discharge date:</label>
            <input type="date" id="discharge-date"
                   value="<?= h($patient['discharged_at'] ?? '') ?>"
                   class="text-xs border border-slate-200 rounded-lg px-2 py-1 focus:outline-none focus:ring-1 focus:ring-red-300">
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ── Password Confirmation Modal ────────────────────────────────────────── -->
<div id="statusPwModal"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
        <!-- Header -->
        <div class="px-6 py-5 border-b border-slate-100 flex items-center gap-3">
            <div class="w-10 h-10 bg-amber-50 rounded-2xl grid place-items-center flex-shrink-0">
                <i class="bi bi-shield-lock-fill text-amber-500 text-lg"></i>
            </div>
            <div>
                <h3 class="font-extrabold text-slate-800 text-base">Confirm Status Change</h3>
                <p id="statusPwLabel" class="text-xs text-slate-500 mt-0.5"></p>
            </div>
            <button onclick="closeStatusModal()" class="ml-auto text-slate-300 hover:text-slate-500 transition-colors">
                <i class="bi bi-x-lg text-lg"></i>
            </button>
        </div>
        <!-- Body -->
        <div class="px-6 py-5">
            <p class="text-sm text-slate-500 mb-4">Enter your password to authorize this change.</p>
            <div id="statusPwErr" class="hidden mb-3 flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-xl text-sm">
                <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
                <span id="statusPwErrMsg"></span>
            </div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-1.5">Password</label>
            <input type="password" id="statusPwInput" autocomplete="current-password"
                   placeholder="Enter your password"
                   class="w-full px-4 py-3 text-sm border border-slate-200 rounded-xl
                          focus:outline-none focus:ring-2 focus:ring-amber-400 bg-slate-50 hover:bg-white transition-colors">
        </div>
        <!-- Footer -->
        <div class="px-6 pb-5 flex gap-3">
            <button onclick="closeStatusModal()"
                    class="flex-1 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700
                           font-semibold text-sm rounded-xl transition-colors">
                Cancel
            </button>
            <button id="statusPwConfirmBtn" onclick="confirmStatusChange()"
                    class="flex-1 px-4 py-2.5 bg-amber-500 hover:bg-amber-600 text-white
                           font-bold text-sm rounded-xl transition-colors shadow-sm active:scale-95 flex items-center justify-center gap-2">
                Confirm
            </button>
        </div>
    </div>
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
<?php
// ── Required Forms Checklist ──────────────────────────────────────────────────
// Use active visit type if present, otherwise fall back to last visit type.
$checklistVisitType = null;
$checklistVisitDate = null;
if ($activeVisit) {
    $checklistVisitType = $activeVisit['visit_type'] ?? 'routine';
    $checklistVisitDate = $activeVisit['visit_date'] ?? null;
} elseif ($lastVisit) {
    $checklistVisitType = $lastVisit['visit_type'] ?? 'routine';
    $checklistVisitDate = $lastVisit['visit_date'] ?? null;
}

// Build set of form types submitted on that visit date
$completedForms = [];
if ($checklistVisitDate) {
    foreach ($forms as $f) {
        if (substr($f['created_at'], 0, 10) === $checklistVisitDate) {
            $completedForms[] = $f['form_type'];
        }
    }
}
$vtDef    = VISIT_TYPES[$checklistVisitType] ?? VISIT_TYPES['routine'];
$required = $vtDef['required'];
$allDone  = count(array_diff($required, $completedForms)) === 0;
?>
<?php if ($checklistVisitType && canAccessClinical()): ?>
<div class="bg-white rounded-2xl shadow-sm border <?= $allDone ? 'border-emerald-200' : 'border-amber-200' ?> p-4 mb-6 no-print">
    <div class="flex items-center gap-2 mb-3">
        <i class="bi bi-list-check <?= $allDone ? 'text-emerald-500' : 'text-amber-500' ?> text-lg"></i>
        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Required Forms</span>
        <span class="ml-1 text-xs font-semibold px-2 py-0.5 rounded-full <?= $allDone ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
            <?= $allDone ? 'All Complete' : (count($completedForms) . '/' . count($required) . ' done') ?>
        </span>
        <span class="ml-auto text-xs text-slate-400 font-medium"><?= h($vtDef['label']) ?></span>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
        <?php foreach ($required as $reqType):
            $done    = in_array($reqType, $completedForms, true);
            $flabel  = FORM_LABELS[$reqType] ?? $reqType;
            $formUrl = BASE_URL . '/forms/' . $reqType . '.php?patient_id=' . $id;
        ?>
        <a href="<?= h($formUrl) ?>"
           class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-colors
                  <?= $done ? 'bg-emerald-50 border border-emerald-200 cursor-default pointer-events-none' : 'bg-slate-50 border border-slate-200 hover:border-blue-300 hover:bg-blue-50' ?>">
            <div class="w-6 h-6 rounded-full flex-shrink-0 grid place-items-center
                        <?= $done ? 'bg-emerald-500' : 'bg-white border-2 border-slate-300' ?>">
                <?php if ($done): ?>
                <i class="bi bi-check text-white text-xs font-bold"></i>
                <?php endif; ?>
            </div>
            <span class="text-sm font-medium <?= $done ? 'text-emerald-700 line-through decoration-emerald-400' : 'text-slate-700' ?>">
                <?= h($flabel) ?>
            </span>
            <?php if (!$done): ?>
            <i class="bi bi-pencil-fill text-slate-300 ml-auto text-xs"></i>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

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
    <a href="?id=<?= $id ?>&tab=diagnoses"
       class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $activeTab === 'diagnoses' ? 'bg-white text-orange-700 shadow' : 'text-slate-500 hover:text-slate-700' ?>">
        <i class="bi bi-clipboard2-pulse mr-1.5"></i>Diagnoses
        <?php if (!empty($diagList)): ?>
        <span class="ml-1 bg-orange-100 text-orange-700 text-xs px-1.5 py-0.5 rounded-full"><?= count($diagList) ?></span>
        <?php endif; ?>
    </a>
    <!-- Vitals Trends tab -->
    <a href="?id=<?= $id ?>&tab=vitals"
       class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $activeTab === 'vitals' ? 'bg-white text-rose-700 shadow' : 'text-slate-500 hover:text-slate-700' ?>">
        <i class="bi bi-activity mr-1.5"></i>Vitals
        <?php if (!empty($vitalsRows)): ?>
        <span class="ml-1 bg-rose-100 text-rose-700 text-xs px-1.5 py-0.5 rounded-full"><?= count($vitalsRows) ?></span>
        <?php endif; ?>
    </a>
    <!-- Care Notes tab -->
    <a href="?id=<?= $id ?>&tab=care"
       class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $activeTab === 'care' ? 'bg-white text-teal-700 shadow' : 'text-slate-500 hover:text-slate-700' ?>">
        <i class="bi bi-chat-square-text-fill mr-1.5"></i>Care
        <?php
        $cnCount = !empty($careNotes['top']) ? count($careNotes['top']) : 0;
        if ($cnCount): ?>
        <span class="ml-1 bg-teal-100 text-teal-700 text-xs px-1.5 py-0.5 rounded-full"><?= $cnCount ?></span>
        <?php endif; ?>
    </a>
    <!-- SOAP Notes tab -->
    <a href="?id=<?= $id ?>&tab=notes"
       class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $activeTab === 'notes' ? 'bg-white text-blue-700 shadow' : 'text-slate-500 hover:text-slate-700' ?>">
        <i class="bi bi-journal-medical mr-1.5"></i>Notes
        <?php if (!empty($soapNotes)): ?>
        <span class="ml-1 bg-blue-100 text-blue-700 text-xs px-1.5 py-0.5 rounded-full"><?= count($soapNotes) ?></span>
        <?php endif; ?>
    </a>
    <?php endif; // canAccessClinical tabs ?>
    <?php if (isAdmin()): ?>
    <a href="?id=<?= $id ?>&tab=audit"
       class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all <?= $activeTab === 'audit' ? 'bg-white text-slate-800 shadow' : 'text-slate-500 hover:text-slate-700' ?>">
        <i class="bi bi-shield-lock mr-1.5"></i>Audit
        <?php if (!empty($patientAudit)): ?>
        <span class="ml-1 bg-slate-200 text-slate-600 text-xs px-1.5 py-0.5 rounded-full"><?= count($patientAudit) ?></span>
        <?php endif; ?>
    </a>
    <?php endif; ?>
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

    <!-- Filter bar -->
    <div class="flex flex-wrap items-center gap-3 px-5 py-3 border-b border-slate-100 bg-slate-50/60">
        <div class="flex items-center gap-2 flex-1 min-w-[160px]">
            <i class="bi bi-funnel text-slate-400 text-sm flex-shrink-0"></i>
            <select id="filterType"
                    class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2 bg-white
                           focus:outline-none focus:ring-2 focus:ring-blue-400 text-slate-700">
                <option value="">All categories</option>
                <?php
                $seenTypes = [];
                foreach ($forms as $frow):
                    $ftype = $frow['form_type'];
                    if (in_array($ftype, $seenTypes, true)) continue;
                    $seenTypes[] = $ftype;
                    $flabel2 = $formDefs[$ftype]['label'] ?? $ftype;
                ?>
                <option value="<?= h($ftype) ?>"><?= h($flabel2) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-center gap-2">
            <i class="bi bi-calendar3 text-slate-400 text-sm flex-shrink-0"></i>
            <input type="date" id="filterDate"
                   class="text-sm border border-slate-200 rounded-xl px-3 py-2 bg-white
                          focus:outline-none focus:ring-2 focus:ring-blue-400 text-slate-700">
        </div>
        <button id="filterClear"
                class="text-xs font-semibold text-slate-400 hover:text-rose-600 transition-colors hidden">
            <i class="bi bi-x-circle"></i> Clear filters
        </button>
        <span id="filterCount" class="text-xs text-slate-400 ml-auto hidden"></span>
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
            <?php
            // ── Version history map ─────────────────────────────────────────
            // $forms is DESC by created_at; version 1 = oldest, vN = newest
            $fvTotal   = [];
            $fvLatest  = [];
            foreach (array_reverse($forms) as $fv) {
                $fvTotal[$fv['form_type']] = ($fvTotal[$fv['form_type']] ?? 0) + 1;
            }
            foreach ($forms as $fv) { // DESC: first encountered per type = latest
                if (!isset($fvLatest[$fv['form_type']])) $fvLatest[$fv['form_type']] = $fv['id'];
            }
            $fvNum = array_fill_keys(array_keys($fvTotal), 0);
            $fvMap = [];
            foreach (array_reverse($forms) as $fv) {
                $fvNum[$fv['form_type']]++;
                $fvMap[$fv['id']] = $fvNum[$fv['form_type']];
            }
            ?>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($forms as $f):
                    $fd = $formDefs[$f['form_type']] ?? ['label'=>$f['form_type'],'icon'=>'bi-file','bg'=>'bg-slate-100','text'=>'text-slate-600'];
                    $sc = $statusCfg[$f['status']] ?? $statusCfg['draft'];
                    $rowDate = substr($f['created_at'], 0, 10); // YYYY-MM-DD
                ?>
                <tr class="hover:bg-slate-50/70 transition-colors form-row"
                    data-type="<?= h($f['form_type']) ?>"
                    data-date="<?= h($rowDate) ?>">
                    <td class="pl-5 pr-2 py-4">
                        <input type="checkbox" class="form-chk w-3.5 h-3.5 text-blue-600 border-slate-300 rounded cursor-pointer"
                               value="<?= $f['id'] ?>" onchange="updateBatch()">
                    </td>
                    <td class="px-4 py-4">
                        <div class="flex items-center gap-3">
                            <span class="<?= $fd['bg'] ?> <?= $fd['text'] ?> p-2 rounded-xl">
                                <i class="bi <?= $fd['icon'] ?> text-base"></i>
                            </span>
                            <div>
                                <span class="font-medium text-slate-700"><?= $fd['label'] ?></span>
                                <?php
                                $fvVer      = $fvMap[$f['id']] ?? 1;
                                $fvTot      = $fvTotal[$f['form_type']] ?? 1;
                                $fvIsLatest = ($fvLatest[$f['form_type']] ?? null) === $f['id'];
                                if ($fvTot > 1): ?>
                                <span class="ml-1.5 text-[10px] font-bold px-1.5 py-0.5 rounded-full
                                             <?= $fvIsLatest ? 'bg-indigo-100 text-indigo-600' : 'bg-slate-100 text-slate-400' ?>">
                                    <?= $fvIsLatest ? 'Latest' : 'v' . $fvVer . ' of ' . $fvTot ?>
                                </span>
                                <?php endif; ?>
                            </div>
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

// ── Filter ───────────────────────────────────────────────────────────────────
var filterTypeEl  = document.getElementById('filterType');
var filterDateEl  = document.getElementById('filterDate');
var filterClearEl = document.getElementById('filterClear');
var filterCountEl = document.getElementById('filterCount');

function applyFilters() {
    var typeVal = filterTypeEl ? filterTypeEl.value : '';
    var dateVal = filterDateEl ? filterDateEl.value : '';
    var hasFilter = typeVal || dateVal;
    var rows = document.querySelectorAll('.form-row');
    var visible = 0;
    rows.forEach(function (tr) {
        var matchType = !typeVal || tr.dataset.type === typeVal;
        var matchDate = !dateVal || tr.dataset.date === dateVal;
        var show = matchType && matchDate;
        tr.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    if (filterClearEl) filterClearEl.classList.toggle('hidden', !hasFilter);
    if (filterCountEl) {
        if (hasFilter) {
            filterCountEl.textContent = visible + ' of <?= count($forms) ?> form' + (<?= count($forms) ?> !== 1 ? 's' : '');
            filterCountEl.classList.remove('hidden');
        } else {
            filterCountEl.classList.add('hidden');
        }
    }
    // deselect hidden rows
    document.querySelectorAll('.form-chk').forEach(function (c) {
        if (c.closest('tr') && c.closest('tr').style.display === 'none') c.checked = false;
    });
    updateBatch();
}

if (filterTypeEl) filterTypeEl.addEventListener('change', applyFilters);
if (filterDateEl) filterDateEl.addEventListener('input', applyFilters);
if (filterClearEl) filterClearEl.addEventListener('click', function () {
    filterTypeEl.value = '';
    filterDateEl.value = '';
    applyFilters();
});

// ── Batch export ─────────────────────────────────────────────────────────────
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
    var isChecked = this.checked;
    document.querySelectorAll('.form-row').forEach(function (tr) {
        if (tr.style.display !== 'none') {
            var chk = tr.querySelector('.form-chk');
            if (chk) chk.checked = isChecked;
        }
    });
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

<?php elseif ($activeTab === 'diagnoses' && canAccessClinical()): ?>
<!-- Diagnoses Tab -->
<div id="diag-tab" class="space-y-4">

    <!-- Add Diagnosis Card -->
    <?php if (!isBilling()): ?>
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
        <h3 class="font-bold text-slate-700 mb-3 flex items-center gap-2">
            <i class="bi bi-plus-circle-fill text-orange-500"></i> Add Diagnosis
        </h3>
        <div class="flex gap-2 mb-2">
            <div class="relative flex-1">
                <input id="diag-search" type="text" placeholder="Search ICD-10 code or description..."
                       class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-orange-400 focus:bg-white transition"
                       autocomplete="off">
                <ul id="diag-dropdown"
                    class="absolute z-50 left-0 right-0 bg-white border border-slate-200 rounded-xl shadow-lg mt-1
                           max-h-60 overflow-y-auto hidden text-sm"></ul>
            </div>
        </div>
        <!-- Selected code preview -->
        <div id="diag-selected" class="hidden flex items-center gap-3 bg-orange-50 border border-orange-200 rounded-xl px-4 py-2.5 mb-3">
            <span class="font-mono font-bold text-orange-700" id="diag-sel-code"></span>
            <span class="text-slate-700 text-sm" id="diag-sel-desc"></span>
            <button type="button" onclick="diagClear()" class="ml-auto text-slate-400 hover:text-red-500"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="flex gap-2">
            <input id="diag-notes" type="text" maxlength="500" placeholder="Notes (optional)"
                   class="flex-1 px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50
                          focus:outline-none focus:ring-2 focus:ring-orange-400 focus:bg-white transition">
            <button id="diag-add-btn" onclick="diagAdd()"
                    class="px-5 py-2.5 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-xl text-sm transition disabled:opacity-50">
                <i class="bi bi-plus-lg mr-1"></i>Add
            </button>
        </div>
        <p id="diag-error" class="text-red-600 text-xs mt-2 hidden"></p>
    </div>
    <?php endif; ?>

    <!-- Diagnoses List -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-700 flex items-center gap-2">
                <i class="bi bi-clipboard2-pulse text-orange-500"></i>
                Active Diagnoses
            </h3>
        </div>
        <div id="diag-list">
        <?php if (empty($diagList)): ?>
        <div id="diag-empty" class="flex flex-col items-center justify-center py-12 text-slate-400">
            <i class="bi bi-clipboard2 text-4xl mb-2"></i>
            <p class="font-semibold">No diagnoses on file</p>
            <p class="text-sm mt-1">Search and add an ICD-10 code above.</p>
        </div>
        <?php else: ?>
        <?php foreach ($diagList as $dx): ?>
        <div class="diag-row flex items-start gap-3 px-5 py-3 border-b border-slate-50 hover:bg-slate-50 transition" data-id="<?= (int)$dx['id'] ?>">
            <span class="font-mono font-bold text-orange-600 bg-orange-50 px-2 py-0.5 rounded text-sm whitespace-nowrap mt-0.5"><?= h($dx['icd_code']) ?></span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-slate-700"><?= h($dx['icd_desc']) ?></p>
                <?php if ($dx['notes']): ?><p class="text-xs text-slate-500 mt-0.5"><?= h($dx['notes']) ?></p><?php endif; ?>
                <p class="text-xs text-slate-400 mt-0.5">Added by <?= h($dx['added_by_name'] ?? 'Unknown') ?> &bull; <?= date('m/d/Y', strtotime($dx['added_at'])) ?></p>
            </div>
            <?php if (!isBilling()): ?>
            <button onclick="diagRemove(<?= (int)$dx['id'] ?>, this)" class="text-slate-300 hover:text-red-500 transition flex-shrink-0 mt-0.5">
                <i class="bi bi-trash3"></i>
            </button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>
</div><!-- /diagnoses tab -->

<?php elseif ($activeTab === 'vitals' && canAccessClinical()): ?>
<!-- ── Vitals Trend Tab ─────────────────────────────────────────────────────── -->
<?php if (empty($vitalsRows)): ?>
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 flex flex-col items-center justify-center py-16 text-slate-400">
    <i class="bi bi-activity text-5xl mb-3 opacity-30"></i>
    <p class="font-semibold text-slate-500">No vitals recorded yet</p>
    <p class="text-sm mt-1">Vitals are collected automatically from Visit Consent (Vital CS) forms.</p>
</div>
<?php else: ?>
<?php
$vitKpis = [
    ['label'=>'Blood Pressure', 'icon'=>'bi-heart-pulse-fill',  'color'=>'red',    'unit'=>'',      'val'=> isset($vitalsLatest['systolic']) ? ($vitalsLatest['systolic'] . '/' . ($vitalsLatest['diastolic'] ?? '?')) : null],
    ['label'=>'O2 Saturation',  'icon'=>'bi-lungs-fill',         'color'=>'cyan',   'unit'=>'%',     'val'=> $vitalsLatest['o2sat']    ?? null],
    ['label'=>'Pulse',          'icon'=>'bi-activity',           'color'=>'violet', 'unit'=>' bpm',  'val'=> $vitalsLatest['pulse']    ?? null],
    ['label'=>'Weight',         'icon'=>'bi-speedometer2',       'color'=>'sky',    'unit'=>' lbs',  'val'=> $vitalsLatest['weight']   ?? null],
    ['label'=>'Temperature',    'icon'=>'bi-thermometer-half',   'color'=>'amber',  'unit'=>'°F',    'val'=> $vitalsLatest['temp']     ?? null],
    ['label'=>'Glucose',        'icon'=>'bi-droplet-fill',       'color'=>'emerald','unit'=>' mg/dL','val'=> $vitalsLatest['glucose']  ?? null],
];
$kpiColors = [
    'red'    => ['bg'=>'bg-red-50',    'icon'=>'text-red-500',    'border'=>'border-red-100'],
    'cyan'   => ['bg'=>'bg-cyan-50',   'icon'=>'text-cyan-500',   'border'=>'border-cyan-100'],
    'violet' => ['bg'=>'bg-violet-50', 'icon'=>'text-violet-500', 'border'=>'border-violet-100'],
    'sky'    => ['bg'=>'bg-sky-50',    'icon'=>'text-sky-500',    'border'=>'border-sky-100'],
    'amber'  => ['bg'=>'bg-amber-50',  'icon'=>'text-amber-500',  'border'=>'border-amber-100'],
    'emerald'=> ['bg'=>'bg-emerald-50','icon'=>'text-emerald-500','border'=>'border-emerald-100'],
];
?>
<!-- KPI Summary Cards -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
    <?php foreach ($vitKpis as $kpi):
        $kc = $kpiColors[$kpi['color']]; ?>
    <div class="bg-white border <?= $kc['border'] ?> rounded-2xl p-4 hover:shadow-sm transition-shadow">
        <div class="flex items-center gap-2 mb-2">
            <span class="w-7 h-7 <?= $kc['bg'] ?> rounded-lg grid place-items-center flex-shrink-0">
                <i class="bi <?= $kpi['icon'] ?> <?= $kc['icon'] ?> text-sm"></i>
            </span>
        </div>
        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wide mb-1.5"><?= $kpi['label'] ?></p>
        <?php if ($kpi['val'] !== null): ?>
        <p class="text-xl font-extrabold text-slate-800 leading-none">
            <?= h($kpi['val']) ?><span class="text-xs font-semibold text-slate-400 ml-0.5"><?= $kpi['unit'] ?></span>
        </p>
        <p class="text-xs text-slate-400 mt-1.5">Most recent</p>
        <?php else: ?>
        <p class="text-lg font-bold text-slate-300">—</p>
        <p class="text-xs text-slate-300 mt-1.5">No data</p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<!-- Charts Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
    <!-- BP Chart -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-9 h-9 bg-red-50 rounded-xl grid place-items-center flex-shrink-0">
                <i class="bi bi-heart-pulse-fill text-red-500"></i>
            </div>
            <div>
                <h3 class="font-bold text-slate-700 text-sm">Blood Pressure</h3>
                <p class="text-xs text-slate-400">Systolic &amp; Diastolic (mmHg)</p>
            </div>
        </div>
        <?php if (!empty(array_filter($vSysArr))): ?>
        <div class="relative h-52"><canvas id="vChartBP"></canvas></div>
        <?php else: ?>
        <div class="h-52 flex items-center justify-center">
            <div class="text-center text-slate-300">
                <i class="bi bi-heart-pulse text-3xl"></i>
                <p class="text-sm mt-2">No BP data recorded</p>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Other Vitals Chart -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
        <div class="flex items-center gap-3 mb-3">
            <div class="w-9 h-9 bg-indigo-50 rounded-xl grid place-items-center flex-shrink-0">
                <i class="bi bi-graph-up-arrow text-indigo-500"></i>
            </div>
            <div>
                <h3 class="font-bold text-slate-700 text-sm">Vital Trends</h3>
                <p class="text-xs text-slate-400">Select a metric to view over time</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-1.5 mb-3">
            <?php foreach ([['o2sat','O2 Sat'],['pulse','Pulse'],['weight','Weight'],['temp','Temp'],['glucose','Glucose'],['resp','Resp']] as [$m,$lbl]): ?>
            <button class="vt-btn text-xs font-bold px-3 py-1.5 rounded-xl border border-slate-200 bg-white text-slate-600 transition-all hover:border-indigo-300"
                    data-metric="<?= $m ?>"><?= $lbl ?></button>
            <?php endforeach; ?>
        </div>
        <div class="relative h-44"><canvas id="vChartOther"></canvas></div>
    </div>
</div>

<!-- History Table -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-semibold text-slate-700 text-sm flex items-center gap-2">
            <i class="bi bi-table text-slate-400"></i> Vitals History
        </h3>
        <span class="text-xs text-slate-400"><?= count($vitalsRows) ?> visit<?= count($vitalsRows) !== 1 ? 's' : '' ?> — showing last <?= min(count($vitalsRows), 20) ?></span>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-xs">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-100 text-slate-500 font-semibold uppercase tracking-wide">
                <th class="px-4 py-3 text-left">Date</th>
                <th class="px-4 py-3 text-left">BP</th>
                <th class="px-4 py-3 text-left">O2 Sat</th>
                <th class="px-4 py-3 text-left">Pulse</th>
                <th class="px-4 py-3 text-left">Weight</th>
                <th class="px-4 py-3 text-left hidden sm:table-cell">Temp</th>
                <th class="px-4 py-3 text-left hidden sm:table-cell">Glucose</th>
                <th class="px-4 py-3 text-left hidden md:table-cell">Resp</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
        <?php foreach (array_slice(array_reverse($vitalsRows), 0, 20) as $vr):
            $vrBpColor = '';
            if (!empty($vr['bp']) && preg_match('/([0-9]+)\s*\/\s*([0-9]+)/', $vr['bp'], $bpM)) {
                $vrSys = (int)$bpM[1]; $vrDia = (int)$bpM[2];
                if ($vrSys >= 140 || $vrDia >= 90) $vrBpColor = 'text-red-600 font-bold';
                elseif ($vrSys >= 130 || $vrDia >= 80) $vrBpColor = 'text-amber-600 font-semibold';
                else $vrBpColor = 'text-emerald-600 font-semibold';
            }
        ?>
        <tr class="hover:bg-slate-50/70 transition-colors">
            <td class="px-4 py-3 font-semibold text-slate-700 whitespace-nowrap"><?= date('M j, Y', strtotime($vr['date'])) ?></td>
            <td class="px-4 py-3 whitespace-nowrap <?= $vrBpColor ?>"><?= h($vr['bp']) ?: '<span class="text-slate-300">—</span>' ?></td>
            <td class="px-4 py-3 text-slate-700"><?= h($vr['o2sat']) ?: '<span class="text-slate-300">—</span>' ?></td>
            <td class="px-4 py-3 text-slate-700"><?= h($vr['pulse']) ?: '<span class="text-slate-300">—</span>' ?></td>
            <td class="px-4 py-3 text-slate-700"><?= h($vr['weight']) ?: '<span class="text-slate-300">—</span>' ?></td>
            <td class="px-4 py-3 text-slate-700 hidden sm:table-cell"><?= h($vr['temp']) ?: '<span class="text-slate-300">—</span>' ?></td>
            <td class="px-4 py-3 text-slate-700 hidden sm:table-cell"><?= h($vr['glucose']) ?: '<span class="text-slate-300">—</span>' ?></td>
            <td class="px-4 py-3 text-slate-700 hidden md:table-cell"><?= h($vr['resp']) ?: '<span class="text-slate-300">—</span>' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php endif; // vitalsRows ?>

<?php elseif ($activeTab === 'care' && canAccessClinical()): ?>
<!-- ── Care Coordination Notes Tab ─────────────────────────────────────── -->
<?php
$cnTop     = $careNotes['top']     ?? [];
$cnReplies = $careNotes['replies'] ?? [];
$cnCsrf    = csrfToken();
?>
<div class="space-y-4">

<!-- Compose box -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden" id="careComposeWrap">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-3">
        <div class="w-9 h-9 bg-teal-50 rounded-xl grid place-items-center flex-shrink-0">
            <i class="bi bi-chat-square-text-fill text-teal-500"></i>
        </div>
        <div>
            <h3 class="font-bold text-slate-700 text-sm">Care Coordination Notes</h3>
            <p class="text-xs text-slate-400">Internal handoff notes — not visible to patient or outside parties</p>
        </div>
    </div>
    <div class="px-5 py-4">
        <div id="careComposeErr" class="hidden mb-3 flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 px-3 py-2 rounded-xl text-sm">
            <i class="bi bi-exclamation-circle-fill"></i><span id="careComposeErrMsg"></span>
        </div>
        <textarea id="careNoteBody" rows="3" maxlength="5000"
                  placeholder="Leave a handoff note, flag for follow-up, or update the care team…"
                  class="w-full px-4 py-3 text-sm text-slate-700 placeholder-slate-300 border border-slate-200
                         rounded-xl focus:outline-none focus:ring-2 focus:ring-teal-500 resize-none bg-slate-50
                         hover:bg-white transition-colors"></textarea>
        <div class="flex items-center justify-between mt-3">
            <span id="careCharCount" class="text-xs text-slate-400">0 / 5000</span>
            <button id="carePostBtn" onclick="postCareNote()"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-teal-600 hover:bg-teal-700
                           text-white font-bold text-sm rounded-xl transition-all shadow-sm active:scale-95">
                <i class="bi bi-send-fill text-xs"></i> Post Note
            </button>
        </div>
    </div>
</div>

<!-- Notes feed -->
<?php if (empty($cnTop)): ?>
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 flex flex-col items-center justify-center py-14 text-slate-400">
    <i class="bi bi-chat-square text-5xl mb-3 opacity-25"></i>
    <p class="font-semibold text-slate-500">No care notes yet</p>
    <p class="text-sm mt-1">Be the first to leave a handoff note for this patient.</p>
</div>
<?php else: ?>
<div id="careNotesFeed" class="space-y-3">
    <?php
    // Sort: pinned first, then newest first for display
    $sortedTop = $cnTop;
    usort($sortedTop, function($a, $b) {
        if ($b['pinned'] !== $a['pinned']) return (int)$b['pinned'] - (int)$a['pinned'];
        return strcmp($b['created_at'], $a['created_at']);
    });
    foreach ($sortedTop as $cn):
        $cnId      = (int)$cn['id'];
        $isOwn     = ((int)$cn['author_id_val'] === (int)($_SESSION['user_id'] ?? 0));
        $replies   = $cnReplies[$cnId] ?? [];
        $roleLabel = match($cn['author_role']) { 'admin' => 'Admin', 'ma' => 'MA', default => ucfirst($cn['author_role']) };
    ?>
    <div class="care-note-card bg-white rounded-2xl border <?= $cn['pinned'] ? 'border-amber-300 shadow-amber-100 shadow' : 'border-slate-100' ?> overflow-hidden"
         data-id="<?= $cnId ?>">
        <!-- Thread header -->
        <div class="px-5 py-4">
            <div class="flex items-start gap-3">
                <!-- Avatar -->
                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-teal-400 to-cyan-500 grid place-items-center flex-shrink-0 mt-0.5">
                    <span class="text-white text-xs font-extrabold"><?= strtoupper(substr($cn['author_name'], 0, 2)) ?></span>
                </div>
                <!-- Body -->
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                        <span class="font-bold text-slate-800 text-sm"><?= h($cn['author_name']) ?></span>
                        <span class="text-xs text-slate-400 bg-slate-100 px-2 py-0.5 rounded-full"><?= $roleLabel ?></span>
                        <?php if ($cn['pinned']): ?>
                        <span class="inline-flex items-center gap-1 text-xs font-bold text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full border border-amber-200">
                            <i class="bi bi-pin-fill text-[10px]"></i> Pinned
                        </span>
                        <?php endif; ?>
                        <span class="text-xs text-slate-400 ml-auto"><?= date('M j, Y g:i A', strtotime($cn['created_at'])) ?></span>
                        <?php if ($cn['edited_at']): ?>
                        <span class="text-xs text-slate-400 italic">(edited)</span>
                        <?php endif; ?>
                    </div>
                    <div class="cn-body text-sm text-slate-700 leading-relaxed whitespace-pre-wrap" id="cnBody_<?= $cnId ?>"><?= h($cn['body']) ?></div>
                    <!-- Edit textarea (hidden) -->
                    <div id="cnEdit_<?= $cnId ?>" class="hidden mt-2">
                        <textarea class="cn-edit-ta w-full px-3 py-2 text-sm border border-teal-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-teal-500 resize-none"
                                  rows="3" maxlength="5000"><?= h($cn['body']) ?></textarea>
                        <div class="flex gap-2 mt-2">
                            <button onclick="saveCnEdit(<?= $cnId ?>)"
                                    class="text-xs font-bold px-3 py-1.5 bg-teal-600 text-white rounded-xl hover:bg-teal-700 transition-colors">Save</button>
                            <button onclick="cancelCnEdit(<?= $cnId ?>)"
                                    class="text-xs font-semibold px-3 py-1.5 bg-slate-100 text-slate-600 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Action row -->
            <div class="mt-3 ml-12 flex flex-wrap items-center gap-3">
                <button onclick="toggleReplyBox(<?= $cnId ?>)"
                        class="text-xs font-semibold text-slate-500 hover:text-teal-600 flex items-center gap-1.5 transition-colors">
                    <i class="bi bi-reply-fill"></i> Reply
                    <?php if (count($replies)): ?>
                    <span class="bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded-full"><?= count($replies) ?></span>
                    <?php endif; ?>
                </button>
                <?php if ($isOwn): ?>
                <button onclick="openCnEdit(<?= $cnId ?>)"
                        class="text-xs font-semibold text-slate-400 hover:text-blue-600 flex items-center gap-1 transition-colors">
                    <i class="bi bi-pencil"></i> Edit
                </button>
                <?php endif; ?>
                <?php if (isAdmin()): ?>
                <button onclick="pinCareNote(<?= $cnId ?>, <?= $cn['pinned'] ? 0 : 1 ?>)"
                        class="text-xs font-semibold text-slate-400 hover:text-amber-600 flex items-center gap-1 transition-colors">
                    <i class="bi <?= $cn['pinned'] ? 'bi-pin-angle' : 'bi-pin-fill' ?>"></i> <?= $cn['pinned'] ? 'Unpin' : 'Pin' ?>
                </button>
                <?php endif; ?>
                <?php if ($isOwn || isAdmin()): ?>
                <button onclick="deleteCareNote(<?= $cnId ?>)"
                        class="ml-auto text-xs font-semibold text-slate-300 hover:text-red-500 flex items-center gap-1 transition-colors">
                    <i class="bi bi-trash3"></i> Delete
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Reply compose (hidden) -->
        <div id="replyBox_<?= $cnId ?>" class="hidden border-t border-slate-100 bg-slate-50 px-5 py-3">
            <textarea id="replyBody_<?= $cnId ?>" rows="2" maxlength="5000"
                      placeholder="Add a reply…"
                      class="w-full px-3 py-2.5 text-sm text-slate-700 placeholder-slate-300 border border-slate-200
                             rounded-xl focus:outline-none focus:ring-2 focus:ring-teal-400 resize-none bg-white"></textarea>
            <div class="flex justify-end gap-2 mt-2">
                <button onclick="toggleReplyBox(<?= $cnId ?>)"
                        class="text-xs font-semibold px-3 py-1.5 bg-white border border-slate-200 text-slate-600 rounded-xl hover:bg-slate-50 transition-colors">Cancel</button>
                <button onclick="postReply(<?= $cnId ?>)"
                        class="text-xs font-bold px-4 py-1.5 bg-teal-600 text-white rounded-xl hover:bg-teal-700 transition-colors">Reply</button>
            </div>
        </div>

        <!-- Replies -->
        <?php if (!empty($replies)): ?>
        <div class="border-t border-slate-100 bg-slate-50/60 divide-y divide-slate-100">
            <?php foreach ($replies as $rep):
                $repId  = (int)$rep['id'];
                $repOwn = ((int)$rep['author_id_val'] === (int)($_SESSION['user_id'] ?? 0));
                $repRL  = match($rep['author_role']) { 'admin' => 'Admin', 'ma' => 'MA', default => ucfirst($rep['author_role']) };
            ?>
            <div class="px-5 py-3 flex items-start gap-3" id="reply_<?= $repId ?>">
                <div class="w-7 h-7 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 grid place-items-center flex-shrink-0 mt-0.5">
                    <span class="text-white text-[10px] font-extrabold"><?= strtoupper(substr($rep['author_name'], 0, 2)) ?></span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <span class="font-bold text-slate-700 text-xs"><?= h($rep['author_name']) ?></span>
                        <span class="text-[10px] text-slate-400 bg-slate-200 px-1.5 py-0.5 rounded-full"><?= $repRL ?></span>
                        <span class="text-[10px] text-slate-400 ml-auto"><?= date('M j g:i A', strtotime($rep['created_at'])) ?></span>
                        <?php if ($rep['edited_at']): ?><span class="text-[10px] text-slate-400 italic">(edited)</span><?php endif; ?>
                    </div>
                    <div class="cn-body text-sm text-slate-700 leading-relaxed whitespace-pre-wrap" id="cnBody_<?= $repId ?>"><?= h($rep['body']) ?></div>
                    <!-- Reply edit textarea (hidden) -->
                    <div id="cnEdit_<?= $repId ?>" class="hidden mt-2">
                        <textarea class="cn-edit-ta w-full px-3 py-2 text-sm border border-teal-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-teal-500 resize-none"
                                  rows="2" maxlength="5000"><?= h($rep['body']) ?></textarea>
                        <div class="flex gap-2 mt-2">
                            <button onclick="saveCnEdit(<?= $repId ?>)"
                                    class="text-xs font-bold px-3 py-1.5 bg-teal-600 text-white rounded-xl hover:bg-teal-700 transition-colors">Save</button>
                            <button onclick="cancelCnEdit(<?= $repId ?>)"
                                    class="text-xs font-semibold px-3 py-1.5 bg-slate-100 text-slate-600 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                        </div>
                    </div>
                    <?php if ($repOwn || isAdmin()): ?>
                    <div class="flex gap-3 mt-1.5">
                        <?php if ($repOwn): ?>
                        <button onclick="openCnEdit(<?= $repId ?>)" class="text-xs text-slate-400 hover:text-blue-600 flex items-center gap-1 transition-colors"><i class="bi bi-pencil"></i> Edit</button>
                        <?php endif; ?>
                        <button onclick="deleteCareNote(<?= $repId ?>)" class="text-xs text-slate-300 hover:text-red-500 flex items-center gap-1 transition-colors"><i class="bi bi-trash3"></i> Delete</button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; // replies ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; // cnTop empty ?>
</div><!-- /care notes wrapper -->

<script>
(function () {
    const BASE  = <?= json_encode(BASE_URL) ?>;
    const CSRF  = <?= json_encode($cnCsrf) ?>;
    const PID   = <?= (int)$id ?>;

    const postBtn  = document.getElementById('carePostBtn');
    const bodyTa   = document.getElementById('careNoteBody');
    const charCnt  = document.getElementById('careCharCount');
    const errWrap  = document.getElementById('careComposeErr');
    const errMsg   = document.getElementById('careComposeErrMsg');

    bodyTa && bodyTa.addEventListener('input', () => {
        charCnt.textContent = bodyTa.value.length + ' / 5000';
    });

    function showErr(msg) { errMsg.textContent = msg; errWrap.classList.remove('hidden'); }
    function hideErr()    { errWrap.classList.add('hidden'); }

    async function apiFetch(payload) {
        const r = await fetch(BASE + '/api/save_care_note.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ csrf: CSRF, ...payload }),
        });
        return r.json();
    }

    window.postCareNote = async function() {
        const body = bodyTa ? bodyTa.value.trim() : '';
        if (!body) { showErr('Please write something before posting.'); return; }
        hideErr();
        postBtn.disabled = true;
        const d = await apiFetch({ action: 'create', patient_id: PID, body });
        if (d.ok) { location.reload(); }
        else { showErr(d.error || 'Could not post note.'); postBtn.disabled = false; }
    };

    window.toggleReplyBox = function(id) {
        const box = document.getElementById('replyBox_' + id);
        if (!box) return;
        box.classList.toggle('hidden');
        if (!box.classList.contains('hidden')) {
            const ta = document.getElementById('replyBody_' + id);
            if (ta) ta.focus();
        }
    };

    window.postReply = async function(parentId) {
        const ta   = document.getElementById('replyBody_' + parentId);
        const body = ta ? ta.value.trim() : '';
        if (!body) return;
        ta.disabled = true;
        const d = await apiFetch({ action: 'create', patient_id: PID, body, parent_id: parentId });
        if (d.ok) { location.reload(); }
        else { alert(d.error || 'Could not post reply.'); ta.disabled = false; }
    };

    window.openCnEdit = function(id) {
        document.getElementById('cnBody_' + id)?.classList.add('hidden');
        const editWrap = document.getElementById('cnEdit_' + id);
        editWrap?.classList.remove('hidden');
        editWrap?.querySelector('.cn-edit-ta')?.focus();
    };
    window.cancelCnEdit = function(id) {
        document.getElementById('cnBody_' + id)?.classList.remove('hidden');
        document.getElementById('cnEdit_' + id)?.classList.add('hidden');
    };
    window.saveCnEdit = async function(id) {
        const ta   = document.querySelector('#cnEdit_' + id + ' .cn-edit-ta');
        const body = ta ? ta.value.trim() : '';
        if (!body) return;
        const d = await apiFetch({ action: 'edit', id, body });
        if (d.ok) { location.reload(); }
        else { alert(d.error || 'Could not save edit.'); }
    };

    window.deleteCareNote = async function(id) {
        if (!confirm('Delete this note? Replies will also be removed.')) return;
        const d = await apiFetch({ action: 'delete', id, patient_id: PID });
        if (d.ok) { location.reload(); }
        else { alert(d.error || 'Could not delete note.'); }
    };

    window.pinCareNote = async function(id, pinned) {
        const d = await apiFetch({ action: 'pin', id, patient_id: PID, pinned });
        if (d.ok) { location.reload(); }
        else { alert(d.error || 'Could not update pin.'); }
    };
})();
</script>

<?php elseif ($activeTab === 'notes' && canAccessClinical()): ?>
<!-- ── SOAP Notes Tab ──────────────────────────────────────────────────────── -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
        <h3 class="font-semibold text-slate-700 flex items-center gap-2">
            <i class="bi bi-journal-medical text-blue-500"></i> SOAP Notes
        </h3>
        <a href="<?= BASE_URL ?>/soap_note.php?patient_id=<?= $id ?><?= $visitId ? '&visit_id=' . $visitId : '' ?>"
           class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 hover:bg-blue-700
                  text-white font-semibold text-sm rounded-xl transition-colors shadow-sm">
            <i class="bi bi-plus-lg"></i> New Note
        </a>
    </div>
    <?php if (empty($soapNotes)): ?>
    <div class="flex flex-col items-center justify-center py-14 text-slate-400">
        <i class="bi bi-journal text-5xl mb-3 opacity-30"></i>
        <p class="font-semibold text-slate-500">No SOAP notes yet</p>
        <p class="text-sm mt-1">Click <strong>New Note</strong> to document the first visit note.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                <th class="px-5 py-3 text-left">Date</th>
                <th class="px-5 py-3 text-left hidden sm:table-cell">Author</th>
                <th class="px-5 py-3 text-left">Assessment (preview)</th>
                <th class="px-5 py-3 text-left">Status</th>
                <th class="px-5 py-3 text-left"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
        <?php foreach ($soapNotes as $sn):
            $snFinal = $sn['status'] === 'final';
        ?>
        <tr class="hover:bg-slate-50/70 transition-colors">
            <td class="px-5 py-4 whitespace-nowrap">
                <div class="font-semibold text-slate-800"><?= date('M j, Y', strtotime($sn['note_date'])) ?></div>
                <div class="text-xs text-slate-400"><?= date('D', strtotime($sn['note_date'])) ?></div>
            </td>
            <td class="px-5 py-4 text-slate-600 hidden sm:table-cell"><?= h($sn['author_name']) ?></td>
            <td class="px-5 py-4 max-w-xs">
                <?php if ($sn['assessment']): ?>
                <p class="text-slate-700 truncate"><?= h(mb_strimwidth($sn['assessment'], 0, 100, '…')) ?></p>
                <?php else: ?>
                <span class="text-slate-400 italic text-xs">No assessment recorded</span>
                <?php endif; ?>
            </td>
            <td class="px-5 py-4 whitespace-nowrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold
                            <?= $snFinal ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' ?>">
                    <i class="bi <?= $snFinal ? 'bi-lock-fill' : 'bi-pencil' ?> text-[10px]"></i>
                    <?= $snFinal ? 'Final' : 'Draft' ?>
                </span>
            </td>
            <td class="px-5 py-4">
                <a href="<?= BASE_URL ?>/soap_note.php?id=<?= (int)$sn['id'] ?>"
                   class="inline-flex items-center gap-1.5 text-xs font-semibold px-3.5 py-2 rounded-xl transition-colors
                          <?= $snFinal ? 'bg-slate-50 text-slate-600 hover:bg-slate-100' : 'bg-blue-50 text-blue-600 hover:bg-blue-100' ?>">
                    <?= $snFinal ? 'View' : 'Edit' ?>
                    <i class="bi bi-arrow-right text-[10px]"></i>
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div><!-- /notes tab -->

<?php elseif ($activeTab === 'audit' && isAdmin()): ?>
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-semibold text-slate-700 flex items-center gap-2">
            <i class="bi bi-shield-lock text-slate-500"></i> Access &amp; Activity Log
        </h3>
        <a href="<?= BASE_URL ?>/admin/audit_log.php?q=<?= urlencode($patient['first_name'] . ' ' . $patient['last_name']) ?>"
           class="text-xs text-blue-600 hover:underline font-medium" target="_blank">
            Full audit log <i class="bi bi-box-arrow-up-right ml-0.5"></i>
        </a>
    </div>
    <?php if (empty($patientAudit)): ?>
    <div class="flex flex-col items-center justify-center py-12 text-slate-400">
        <i class="bi bi-shield text-4xl mb-2 opacity-30"></i>
        <p class="font-semibold">No activity recorded yet</p>
    </div>
    <?php else: ?>
    <?php
    $auditActionLabels = [
        'patient_add'    => ['icon' => 'bi-person-plus-fill',   'text' => 'text-teal-700',    'label' => 'Patient Created'],
        'patient_view'   => ['icon' => 'bi-person-lines-fill',  'text' => 'text-blue-600',    'label' => 'Patient Viewed'],
        'patient_edit'   => ['icon' => 'bi-pencil-fill',        'text' => 'text-cyan-700',    'label' => 'Patient Edited'],
        'patient_status' => ['icon' => 'bi-person-check-fill',  'text' => 'text-orange-600',  'label' => 'Status Changed'],
        'form_view'      => ['icon' => 'bi-eye-fill',           'text' => 'text-sky-600',     'label' => 'Form Viewed'],
        'form_create'    => ['icon' => 'bi-file-earmark-plus',  'text' => 'text-violet-600',  'label' => 'Form Created'],
        'form_sign'      => ['icon' => 'bi-pen',                'text' => 'text-indigo-600',  'label' => 'Patient Signed'],
        'provider_sign'  => ['icon' => 'bi-pen-fill',           'text' => 'text-purple-600',  'label' => 'Provider Signed'],
        'form_amend'     => ['icon' => 'bi-pencil-square',      'text' => 'text-rose-600',    'label' => 'Form Amended'],
        'form_export'    => ['icon' => 'bi-file-earmark-pdf',   'text' => 'text-amber-600',   'label' => 'PDF Exported'],
    ];
    ?>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="bg-slate-50 border-b border-slate-100 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                <th class="px-4 py-3 text-left">Timestamp</th>
                <th class="px-4 py-3 text-left">Action</th>
                <th class="px-4 py-3 text-left">User</th>
                <th class="px-4 py-3 text-left">Details</th>
                <th class="px-4 py-3 text-left">IP</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-50">
        <?php foreach ($patientAudit as $ae):
            $aeCfg = $auditActionLabels[$ae['action']] ?? ['icon' => 'bi-activity', 'text' => 'text-slate-500', 'label' => $ae['action']];
            $aeTs  = new DateTime($ae['created_at']);
        ?>
        <tr class="hover:bg-slate-50/60 transition-colors">
            <td class="px-4 py-3 whitespace-nowrap">
                <div class="font-medium text-slate-800"><?= h($aeTs->format('M j, Y')) ?></div>
                <div class="text-xs text-slate-400"><?= h($aeTs->format('g:i:s A')) ?></div>
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
                <span class="inline-flex items-center gap-1.5 text-xs font-semibold <?= $aeCfg['text'] ?>">
                    <i class="bi <?= $aeCfg['icon'] ?>"></i>
                    <?= h($aeCfg['label']) ?>
                </span>
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
                <?php if ($ae['username']): ?>
                <div class="font-medium text-slate-700"><?= h($ae['username']) ?></div>
                <?php if ($ae['user_role']): ?>
                <span class="text-xs text-slate-400"><?= h($ae['user_role']) ?></span>
                <?php endif; ?>
                <?php else: ?>
                <span class="text-slate-400">—</span>
                <?php endif; ?>
            </td>
            <td class="px-4 py-3">
                <span class="text-xs text-slate-500">
                    <?php if ($ae['target_type'] === 'form' && $ae['target_id']): ?>
                    <a href="<?= BASE_URL ?>/view_document.php?id=<?= (int)$ae['target_id'] ?>"
                       class="text-blue-600 hover:underline"><?= h($ae['target_label'] ?? 'Form #' . $ae['target_id']) ?></a>
                    <?php elseif ($ae['details']): ?>
                    <?= h($ae['details']) ?>
                    <?php else: ?>
                    —
                    <?php endif; ?>
                </span>
            </td>
            <td class="px-4 py-3 whitespace-nowrap">
                <span class="font-mono text-xs text-slate-400"><?= h($ae['ip_address'] ?? '—') ?></span>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div><!-- /audit tab -->

<?php endif; ?>

<?php if (canAccessClinical()): ?>
<script>
/* ── Patient profile photo upload ───────────────────────────────────── */
(function () {
    var input  = document.getElementById('ptPhotoInput');
    var wrap   = document.getElementById('ptAvatarWrap');
    var CSRF   = <?= json_encode($photoCsrf) ?>;
    var BASE   = <?= json_encode(BASE_URL) ?>;
    var PID    = <?= (int)$id ?>;
    var HAS_PHOTO = <?= !empty($patient['photo_url']) ? 'true' : 'false' ?>;

    if (!input) return;

    input.addEventListener('change', function () {
        var file = input.files[0];
        if (!file) return;

        // Client-side size guard (10 MB)
        if (file.size > 10 * 1024 * 1024) {
            alert('File too large — max 10 MB.');
            input.value = '';
            return;
        }

        var fd = new FormData();
        fd.append('action',     'upload');
        fd.append('csrf',       CSRF);
        fd.append('patient_id', PID);
        fd.append('photo',      file);

        // Show spinner in avatar
        var imgEl = document.getElementById('ptAvatarImg');
        var origContent = imgEl ? imgEl.outerHTML : '';

        var spinner = document.createElement('div');
        spinner.className = 'w-14 h-14 rounded-2xl bg-slate-200 grid place-items-center flex-shrink-0';
        spinner.innerHTML = '<span class="inline-block w-5 h-5 border-2 border-blue-500 border-t-transparent rounded-full animate-spin"></span>';
        if (imgEl) imgEl.replaceWith(spinner);

        fetch(BASE + '/api/patient_photo.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) { alert(data.error || 'Upload failed'); spinner.outerHTML = origContent; return; }
                // Replace with new image
                var newImg = document.createElement('img');
                newImg.id        = 'ptAvatarImg';
                newImg.src       = data.url + '?v=' + Date.now();
                newImg.alt       = '';
                newImg.className = 'w-14 h-14 rounded-2xl object-cover shadow-lg border-2 border-white ring-2 ring-blue-100';
                spinner.replaceWith(newImg);
                HAS_PHOTO = true;
            })
            .catch(function () { alert('Network error during upload.'); spinner.replaceWith(document.createElement('div')); });

        input.value = '';
    });

    // Right-click avatar → remove photo
    if (wrap) {
        wrap.addEventListener('contextmenu', function (e) {
            if (!HAS_PHOTO) return;
            e.preventDefault();
            if (!confirm('Remove profile photo?')) return;
            fetch(BASE + '/api/patient_photo.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'remove', csrf: CSRF, patient_id: PID}),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) { alert(data.error || 'Error'); return; }
                // Replace image with initials div
                var imgEl = document.getElementById('ptAvatarImg');
                var ini = document.createElement('div');
                ini.id        = 'ptAvatarImg';
                ini.className = 'w-14 h-14 rounded-2xl bg-gradient-to-br from-blue-500 to-blue-700 grid place-items-center text-white font-extrabold text-xl shadow-lg';
                ini.textContent = <?= json_encode(strtoupper(substr($patient['first_name'],0,1) . substr($patient['last_name'],0,1))) ?>;
                if (imgEl) imgEl.replaceWith(ini);
                HAS_PHOTO = false;
            });
        });
    }
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
