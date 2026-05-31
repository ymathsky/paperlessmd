<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/audit.php';
require_once __DIR__ . '/includes/visit_types.php';
requireLogin();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . BASE_URL . '/patients.php'); exit; }

$stmt = $pdo->prepare("SELECT p.*, ma.full_name AS assigned_ma_name FROM patients p LEFT JOIN staff ma ON ma.id = p.assigned_ma WHERE p.id = ?");
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) { header('Location: ' . BASE_URL . '/patients.php'); exit; }

auditLog($pdo, 'patient_view', 'patient', $id, $patient['first_name'] . ' ' . $patient['last_name']);

// Staff for edit drawer MA/provider selects
$maStaff = $pdo->query("SELECT id, full_name, role FROM staff WHERE active=1 ORDER BY full_name")->fetchAll();

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
    SELECT fs.*, s.full_name AS ma_name,
           COALESCE(NULLIF(fs.provider_name,''), s.full_name) AS display_provider
    FROM form_submissions fs
    LEFT JOIN staff s ON s.id = fs.ma_id
    WHERE fs.patient_id = ?
    ORDER BY fs.created_at DESC
");
$formsStmt->execute([$id]);
$forms = $formsStmt->fetchAll();

// Wound photos
$photosStmt = $pdo->prepare("
    SELECT wp.*, s.full_name AS ma_name,
           wm_ai.area_cm2, wm_ai.length_cm AS ai_length_cm, wm_ai.width_cm AS ai_width_cm,
           wm_ai.ruler_detected AS meas_ruler, wm_ai.annotated_photo_path,
           wm_ai.granulation_pct, wm_ai.slough_pct, wm_ai.eschar_pct, wm_ai.analysis_confidence,
           wm_man.area_cm2 AS man_area_cm2, wm_man.length_cm AS man_length_cm,
           wm_man.width_cm AS man_width_cm, wm_man.depth_cm AS man_depth_cm,
           wm_man.measured_at AS man_measured_at,
           wm_man.annotated_photo_path AS man_annotated_path,
           sm.full_name AS man_by_name, sm.role AS man_by_role
    FROM wound_photos wp
    LEFT JOIN staff s ON s.id = wp.uploaded_by
    LEFT JOIN wound_measurements wm_ai ON wm_ai.id = (
               SELECT id FROM wound_measurements
               WHERE photo_id = wp.id AND entry_type = 'ai' ORDER BY id DESC LIMIT 1)
    LEFT JOIN wound_measurements wm_man ON wm_man.id = (
               SELECT id FROM wound_measurements
               WHERE photo_id = wp.id AND entry_type = 'manual' ORDER BY id DESC LIMIT 1)
    LEFT JOIN staff sm ON sm.id = wm_man.recorded_by
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
    'informed_consent_wound' => ['label' => 'Informed Consent – Wound Care', 'icon' => 'bi-file-earmark-medical',     'bg' => 'bg-red-100',     'text' => 'text-red-700'],
    'rpm_consent'            => ['label' => 'RPM Consent',                       'icon' => 'bi-broadcast',                'bg' => 'bg-teal-100',    'text' => 'text-teal-700'],
    'new_patient_pocket'     => ['label' => 'New Patient Pocket<br><span class="text-rose-500 font-normal">Wound Care</span>',                 'icon' => 'bi-folder2-open',             'bg' => 'bg-indigo-100',  'text' => 'text-indigo-700'],
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

// ── Today's visit check (gates form access for non-admins) ───────────────────
$hasVisitToday = false;
try {
    $todayStmt = $pdo->prepare("
        SELECT id FROM `schedule`
        WHERE patient_id = ? AND visit_date = CURDATE() AND status != 'missed'
        LIMIT 1
    ");
    $todayStmt->execute([$id]);
    $hasVisitToday = (bool)$todayStmt->fetchColumn();
} catch (PDOException $e) { $hasVisitToday = false; }

// Admins can always start forms
$canStartForms = isAdmin() || $hasVisitToday;

// ── Last Visit Summary data ───────────────────────────────────────────────────
$lastVisit = null;
try {
    $lvStmt = $pdo->prepare("
        SELECT sc.visit_date, sc.visit_time, sc.status, sc.notes, sc.visit_type,
               s.full_name AS ma_name,
               COALESCE(NULLIF(sc.provider_name,''), s.full_name) AS display_provider
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
            'bp'       => ($lvfData['bp'] ?? '') ?: trim(($lvfData['bp_systolic'] ?? '') . (!empty($lvfData['bp_systolic']) ? '/' . ($lvfData['bp_diastolic'] ?? '') : '')),
            'hr'       => trim($lvfData['heart_rate'] ?? $lvfData['pulse'] ?? ''),
            'temp'     => trim($lvfData['temperature'] ?? $lvfData['temp'] ?? ''),
            'weight'   => trim($lvfData['weight'] ?? ''),
            'o2'       => trim($lvfData['o2_sat'] ?? $lvfData['o2sat'] ?? ''),
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
$isPartial = isset($_GET['_pt']);
if ($activeTab === 'meds') {
    $csrfJs = csrfToken();
    ob_start(); ?>
<script>
(function () {
    const PID   = <?= (int)$id ?>;
    const BASE  = <?= json_encode(BASE_URL) ?>;
    const CSRF  = <?= json_encode($csrfJs) ?>;
    const UNAME = <?= json_encode($_SESSION['full_name'] ?? '') ?>;

    async function medApi(data) {
        const r = await fetch(BASE + '/api/meds.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({...data, csrf: CSRF, patient_id: PID})
        });
        return r.json();
    }

    // ── Import from Practice Fusion PDF ──────────────────────────────────────
    // ── Medication PDF upload ────────────────────────────────────────────────────
    (function () {
        const fileInput  = document.getElementById('medPdfFileInput');
        const statusEl   = document.getElementById('medPdfUploadStatus');
        const listEl     = document.getElementById('medPdfList');
        if (!fileInput || !listEl) return;

        function setStatus(type, html) {
            const bg = {loading:'bg-blue-50 text-blue-700', success:'bg-emerald-50 text-emerald-700',
                        warn:'bg-amber-50 text-amber-700', error:'bg-red-50 text-red-700'};
            statusEl.className = 'mb-3 text-xs rounded-xl px-3 py-2 ' + (bg[type] || bg.loading);
            statusEl.innerHTML = html;
            statusEl.classList.remove('hidden');
            if (type === 'success') setTimeout(() => statusEl.classList.add('hidden'), 3500);
        }

        function renderFiles(files) {
            if (!files.length) {
                listEl.innerHTML = '<p class="text-xs text-slate-400 italic">No PDFs uploaded yet.</p>';
                return;
            }
            listEl.innerHTML = files.map(f => {
                const d = new Date(f.uploaded_at);
                const dateStr = d.toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'});
                const byStr   = f.uploaded_by_name ? ' by ' + esc(f.uploaded_by_name) : '';
                return `<div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl" data-file-id="${f.id}">
                    <i class="bi bi-file-earmark-pdf-fill text-red-500 text-xl flex-shrink-0"></i>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-700 truncate">${esc(f.original_name)}</p>
                        <p class="text-xs text-slate-400">${dateStr}${byStr}</p>
                    </div>
                    <a href="${f.url}" target="_blank" rel="noopener"
                       class="p-2 text-blue-500 hover:bg-blue-50 rounded-lg transition-colors" title="View">
                        <i class="bi bi-eye text-sm"></i>
                    </a>
                    <a href="${f.url}" download
                       class="p-2 text-slate-400 hover:bg-slate-100 rounded-lg transition-colors" title="Download">
                        <i class="bi bi-download text-sm"></i>
                    </a>
                    <button type="button" class="del-pdf-btn p-2 text-slate-300 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors" title="Delete">
                        <i class="bi bi-trash text-sm"></i>
                    </button>
                </div>`;
            }).join('');
        }

        // Load existing files on init
        fetch(BASE + '/api/patient_files.php?patient_id=' + PID + '&category=medication')
            .then(r => r.json())
            .then(d => { if (d.ok) renderFiles(d.files); })
            .catch(() => {});

        // Upload handler
        fileInput.addEventListener('change', async function () {
            const file = this.files[0];
            if (!file) return;
            this.value = '';
            const btn = document.getElementById('uploadMedPdfBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Uploading…';
            setStatus('loading', '<i class="bi bi-hourglass-split"></i> Uploading…');
            try {
                const fd = new FormData();
                fd.append('file', file);
                fd.append('patient_id', PID);
                fd.append('category', 'medication');
                fd.append('csrf', CSRF);
                const r = await fetch(BASE + '/api/upload_patient_file.php', {method:'POST', body:fd});
                const d = await r.json();
                if (!d.ok) throw new Error(d.error || 'Upload failed');
                // Re-fetch list
                const lr = await fetch(BASE + '/api/patient_files.php?patient_id=' + PID + '&category=medication');
                const ld = await lr.json();
                if (ld.ok) renderFiles(ld.files);
                setStatus('success', '<i class="bi bi-check-circle-fill"></i> ' + esc(d.original_name) + ' uploaded.');
            } catch (err) {
                setStatus('error', '<i class="bi bi-x-circle-fill"></i> ' + esc(err.message || 'Upload failed'));
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-upload"></i> Upload PDF';
            }
        });

        // Delete handler
        listEl.addEventListener('click', async function (e) {
            const btn = e.target.closest('.del-pdf-btn');
            if (!btn) return;
            const row = btn.closest('[data-file-id]');
            if (!row) return;
            if (!confirm('Delete this PDF?')) return;
            const fid = parseInt(row.dataset.fileId);
            const r = await fetch(BASE + '/api/patient_files.php', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({action:'delete', id:fid, patient_id:PID, csrf:CSRF})
            });
            const d = await r.json();
            if (d.ok) row.remove();
            if (!listEl.querySelector('[data-file-id]')) {
                listEl.innerHTML = '<p class="text-xs text-slate-400 italic">No PDFs uploaded yet.</p>';
            }
        });
    })();

    // ── Drug autocomplete ──────────────────────────────────────────────────
    (function () {
        const nameIn  = document.getElementById('newMedName');
        const acDrop  = document.getElementById('medAcDrop');
        if (!nameIn || !acDrop) return;
        let acTimer = null, acCtrl = null, acIdx = -1, acItems = [];

        function renderDrop(items) {
            acItems = items;
            acIdx = -1;
            if (!items.length) { acDrop.classList.add('hidden'); return; }
            acDrop.innerHTML = items.map((d, i) =>
                `<div class="ac-item px-4 py-2.5 cursor-pointer hover:bg-emerald-50 flex items-center gap-3 text-sm" data-i="${i}">
                    <i class="bi bi-capsule text-emerald-500 text-xs shrink-0"></i>
                    <span class="font-medium text-slate-700">${esc(d.name)}</span>
                    ${d.category ? `<span class="ml-auto text-[10px] text-slate-400 shrink-0">${esc(d.category)}</span>` : ''}
                </div>`
            ).join('');
            acDrop.classList.remove('hidden');
        }

        function selectItem(i) {
            if (i < 0 || i >= acItems.length) return;
            nameIn.value = acItems[i].name;
            acDrop.classList.add('hidden');
            document.getElementById('newMedFreq').focus();
        }

        nameIn.addEventListener('input', function () {
            const q = this.value.trim();
            clearTimeout(acTimer);
            if (q.length < 2) { acDrop.classList.add('hidden'); return; }
            if (acCtrl) acCtrl.abort();
            acCtrl = new AbortController();
            acTimer = setTimeout(async () => {
                try {
                    const r = await fetch(BASE + '/api/drug_search.php?q=' + encodeURIComponent(q), { signal: acCtrl.signal });
                    const d = await r.json();
                    renderDrop(d);
                } catch(e) {}
            }, 180);
        });

        nameIn.addEventListener('keydown', function (e) {
            if (acDrop.classList.contains('hidden')) return;
            const max = acItems.length;
            if (e.key === 'ArrowDown') { e.preventDefault(); acIdx = Math.min(acIdx + 1, max - 1); highlightAc(); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); acIdx = Math.max(acIdx - 1, 0); highlightAc(); }
            else if (e.key === 'Enter') { if (acIdx >= 0) { e.preventDefault(); selectItem(acIdx); } }
            else if (e.key === 'Escape') { acDrop.classList.add('hidden'); }
        });

        function highlightAc() {
            acDrop.querySelectorAll('.ac-item').forEach((el, i) => {
                el.classList.toggle('bg-emerald-50', i === acIdx);
                el.classList.toggle('text-emerald-800', i === acIdx);
            });
        }

        acDrop.addEventListener('mousedown', function (e) {
            const item = e.target.closest('.ac-item');
            if (!item) return;
            e.preventDefault();
            selectItem(parseInt(item.dataset.i));
        });

        document.addEventListener('click', e => {
            if (!nameIn.contains(e.target) && !acDrop.contains(e.target)) acDrop.classList.add('hidden');
        });
    })();

    // ── Frequency pills ───────────────────────────────────────────────────────
    (function () {
        const pills   = document.querySelectorAll('.freq-pill');
        const freqIn  = document.getElementById('newMedFreq');
        const otherIn = document.getElementById('freqOtherInput');
        if (!pills.length || !freqIn) return;
        const ACTIVE   = 'bg-emerald-600 text-white shadow-sm';
        const INACTIVE = 'bg-slate-100 text-slate-600 hover:bg-slate-200';
        function setActive(val) {
            pills.forEach(p => {
                const on = p.dataset.freq === val;
                p.className = p.className.replace(ACTIVE, '').replace(INACTIVE, '').trim();
                p.className += ' ' + (on ? ACTIVE : INACTIVE);
            });
            freqIn.value = (val && val !== 'other') ? val : '';
            if (otherIn) otherIn.classList.toggle('hidden', val !== 'other');
            if (val === 'other' && otherIn) { otherIn.focus(); }
        }
        pills.forEach(p => p.addEventListener('click', () => {
            const cur = p.dataset.freq;
            const already = p.classList.contains('bg-emerald-600');
            setActive(already ? '' : cur);
        }));
        if (otherIn) otherIn.addEventListener('input', () => { freqIn.value = otherIn.value.trim(); });
    })();

    // ── Recent med chips ──────────────────────────────────────────────────────
    (function () {
        const wrap = document.getElementById('recentMedChips');
        const list = document.getElementById('recentMedList');
        if (!wrap || !list) return;
        fetch(BASE + '/api/meds.php?action=recent&patient_id=' + PID)
            .then(r => r.json())
            .then(d => {
                if (!d.ok || !d.names || !d.names.length) return;
                // Filter out meds already on this patient's active list
                const existing = Array.from(document.querySelectorAll('.med-name-disp'))
                    .map(el => el.textContent.trim().toLowerCase());
                const filtered = d.names.filter(n => !existing.includes(n.toLowerCase()));
                if (!filtered.length) return;
                list.innerHTML = filtered.map(n =>
                    `<button type="button" class="recent-chip inline-flex items-center gap-1 px-2.5 py-1 bg-slate-100 hover:bg-emerald-100 hover:text-emerald-800 text-slate-600 rounded-lg text-xs font-medium transition-colors" data-name="${esc(n)}">
                        <i class="bi bi-clock-history text-[10px]"></i>${esc(n)}
                    </button>`
                ).join('');
                wrap.classList.remove('hidden');
                list.addEventListener('click', e => {
                    const chip = e.target.closest('.recent-chip');
                    if (!chip) return;
                    document.getElementById('newMedName').value = chip.dataset.name;
                    document.getElementById('newMedFreq').focus();
                    document.getElementById('medAcDrop')?.classList.add('hidden');
                });
            }).catch(() => {});
    })();

    // ── Bulk template add ─────────────────────────────────────────────────────
    (function () {
        const toggleBtn  = document.getElementById('toggleTemplateBtn');
        const section    = document.getElementById('templateSection');
        const chev       = document.getElementById('templateChev');
        const addSelBtn  = document.getElementById('addTemplateSelectedBtn');
        if (!toggleBtn || !section) return;
        toggleBtn.addEventListener('click', () => {
            const hidden = section.classList.toggle('hidden');
            if (chev) chev.style.transform = hidden ? '' : 'rotate(180deg)';
        });
        if (addSelBtn) {
            addSelBtn.addEventListener('click', async () => {
                const checked = Array.from(section.querySelectorAll('input[type=checkbox]:checked'));
                if (!checked.length) { pdToast('Select at least one medication.', 'info'); return; }
                addSelBtn.disabled = true;
                addSelBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Adding…';
                let added = 0;
                for (const cb of checked) {
                    const res = await medApi({action: 'add', med_name: cb.value, med_frequency: cb.dataset.freq || ''});
                    if (res.ok) { appendMedRow(res.id, cb.value, cb.dataset.freq || ''); added++; }
                }
                addSelBtn.disabled = false;
                addSelBtn.innerHTML = '<i class="bi bi-check-lg"></i> Add Selected';
                checked.forEach(cb => { cb.checked = false; });
                if (added) pdToast(added + ' medication' + (added > 1 ? 's' : '') + ' added.', 'success');
            });
        }
    })();

    // ── appendMedRow — insert a new row into the active list without reload ──
    function appendMedRow(id, name, freq) {
        const container = document.getElementById('activeMedsContainer');
        let list = document.getElementById('activeMedsList');

        if (!list) {
            // Remove empty-state placeholder
            if (container) {
                const empty = container.querySelector('.flex.flex-col.items-center.py-10');
                if (empty) empty.remove();
                // Create the list div
                list = document.createElement('div');
                list.id = 'activeMedsList';
                container.appendChild(list);
            } else {
                location.reload(); return;
            }
        }

        const today = new Date();
        const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        const dateStr = months[today.getMonth()] + ' ' + today.getDate() + ', ' + today.getFullYear();
        const freqHtml = freq
            ? `<span class="font-medium text-slate-500">${esc(freq)}</span>`
            : '';
        const byHtml = UNAME ? ` by ${esc(UNAME)}` : '';

        const div = document.createElement('div');
        div.className = 'med-row border-b border-slate-50 last:border-0';
        div.dataset.medId = id;
        div.innerHTML = `
            <div class="view-mode flex items-center gap-3 px-5 py-3.5 hover:bg-slate-50/60 transition-colors">
                <div class="w-8 h-8 bg-emerald-100 rounded-lg grid place-items-center flex-shrink-0">
                    <i class="bi bi-capsule text-emerald-600 text-sm"></i>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-slate-800 med-name-disp">${esc(name)}</p>
                    <p class="text-xs text-slate-400 mt-0.5 flex flex-wrap items-center gap-x-3">
                        ${freqHtml}
                        <span>Added ${dateStr}${byHtml}</span>
                    </p>
                </div>
                <div class="flex items-center gap-1 flex-shrink-0">
                    <button class="edit-med-btn p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="Edit">
                        <i class="bi bi-pencil text-sm"></i>
                    </button>
                    <button class="history-btn p-2 text-slate-400 hover:text-violet-600 hover:bg-violet-50 rounded-lg transition-colors" title="View history">
                        <i class="bi bi-clock-history text-sm"></i>
                    </button>
                    <button class="dc-med-btn px-3 py-1.5 text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 rounded-lg transition-colors" title="Discontinue">D/C</button>
                </div>
            </div>
            <div class="edit-mode hidden px-5 py-3 bg-slate-50 border-t border-slate-100">
                <div class="flex flex-col sm:flex-row gap-2">
                    <input type="text" class="edit-name flex-[3] px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-400 bg-white" value="${esc(name)}" placeholder="Medication name &amp; dose">
                    <input type="text" class="edit-freq flex-[2] px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-emerald-400 bg-white" value="${esc(freq)}" placeholder="Frequency">
                    <div class="flex gap-2">
                        <button class="save-edit-btn px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-xl transition-colors">Save</button>
                        <button class="cancel-edit-btn px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-semibold rounded-xl transition-colors">Cancel</button>
                    </div>
                </div>
            </div>
            <div class="history-panel hidden px-5 py-3 bg-violet-50/40 border-t border-violet-100 text-xs">
                <p class="text-slate-400 italic"><i class="bi bi-hourglass-split mr-1"></i>Loading history...</p>
            </div>`;
        list.appendChild(div);
        div.scrollIntoView({behavior: 'smooth', block: 'nearest'});
    }

    function resetMedForm() {
        const n = document.getElementById('newMedName');
        const f = document.getElementById('newMedFreq');
        const o = document.getElementById('freqOtherInput');
        if (n) n.value = '';
        if (f) f.value = '';
        if (o) { o.value = ''; o.classList.add('hidden'); }
        document.querySelectorAll('.freq-pill').forEach(p => {
            p.classList.remove('bg-emerald-600','text-white','shadow-sm');
            p.classList.add('bg-slate-100','text-slate-600');
        });
        document.getElementById('medAcDrop')?.classList.add('hidden');
    }

    // ── Add medication ────────────────────────────────────────────────────────
    const addBtn = document.getElementById('addMedBtn');
    const nameIn = document.getElementById('newMedName');
    const freqIn = document.getElementById('newMedFreq');
    const errMsg = document.getElementById('addMedErr');
    if (addBtn) {
        const submit = async () => {
            const name = nameIn.value.trim();
            const freq = freqIn.value.trim();
            errMsg.classList.add('hidden');
            document.getElementById('medAcDrop')?.classList.add('hidden');
            if (!name) {
                errMsg.textContent = 'Medication name is required.';
                errMsg.classList.remove('hidden');
                nameIn.focus();
                return;
            }
            // Duplicate detection
            const existing = Array.from(document.querySelectorAll('.med-name-disp'))
                .map(el => el.textContent.trim().toLowerCase());
            if (existing.some(n => n === name.toLowerCase())) {
                if (!await pdConfirm({
                    message: '"' + name + '" is already in the active list.',
                    subtext: 'Add it again as a duplicate?',
                    confirmLabel: 'Add Anyway',
                    confirmIcon: 'bi bi-exclamation-triangle-fill',
                    confirmStyle: 'background:#d97706;'
                })) return;
            }
            addBtn.disabled = true;
            addBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
            const res = await medApi({action: 'add', med_name: name, med_frequency: freq});
            addBtn.disabled = false;
            addBtn.innerHTML = '<i class="bi bi-plus-lg"></i> Add';
            if (res.ok) { appendMedRow(res.id, name, freq); resetMedForm(); nameIn.focus(); }
            else { errMsg.textContent = res.error || 'Error adding.'; errMsg.classList.remove('hidden'); }
        };
        addBtn.addEventListener('click', submit);
        nameIn && nameIn.addEventListener('keydown', e => {
            if (e.key === 'Enter' && document.getElementById('medAcDrop')?.classList.contains('hidden')) { e.preventDefault(); submit(); }
        });
        freqIn && freqIn.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); submit(); }
        });
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
                else { pdToast(res.error || 'Error updating', 'error'); }
                return;
            }
            if (e.target.closest('.dc-med-btn')) {
                const medName = row.querySelector('.med-name-disp').textContent;
                if (!await pdConfirm({message: 'Discontinue "' + medName + '"?', subtext: 'This updates the master medication list.', confirmLabel: 'Discontinue', confirmIcon: 'bi bi-x-circle-fill', confirmStyle: 'background:#dc2626;'})) return;
                const res = await medApi({action: 'discontinue', id: medId});
                if (res.ok) { location.reload(); }
                else { pdToast(res.error || 'Error', 'error'); }
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
            else { pdToast(res.error || 'Error', 'error'); }
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
            if (!await pdConfirm({message: 'Permanently delete "' + name + '"?', subtext: 'This cannot be undone.', confirmLabel: 'Delete', confirmIcon: 'bi bi-trash3', confirmStyle: 'background:#dc2626;'})) return;
            const res = await medApi({action: 'delete', id: medId});
            if (res.ok) { location.reload(); }
            else { pdToast(res.error || 'Error', 'error'); }
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
                wound_type:  fd.get('wound_type'),
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
        if (!await pdConfirm({message: 'Delete this measurement?', subtext: 'This cannot be undone.', confirmLabel: 'Delete', confirmIcon: 'bi bi-trash3', confirmStyle: 'background:#dc2626;'})) return;
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
            pdToast(d.error || 'Error deleting.', 'error');
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

    function fmtChartDate(d) {
        // d is "YYYY-MM-DD HH:MM:SS" or "YYYY-MM-DD"
        const dt = new Date(d.replace(' ', 'T'));
        return dt.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: '2-digit' });
    }

    function getLabels(data) {
        return [...new Set(data.map(d => d.date))].sort();
    }

    function rebuildChart() {
        // Gather remaining rows from DOM
        const remaining = [];
        document.querySelectorAll('[data-wound-row]').forEach(row => {
            remaining.push({
                id:   parseInt(row.dataset.woundRow),
                date: row.dataset.date,
                site: row.dataset.site,
                area: parseFloat(row.dataset.area),
            });
        });
        remaining.sort((a,b) => a.date.localeCompare(b.date));

        if (chartInstance) {
            const labels = getLabels(remaining);
            chartInstance.data.labels   = labels.map(fmtChartDate);
            chartInstance.data.datasets = buildDatasets(remaining, labels);
            chartInstance.update();
        }
    }

    // Override buildDatasets to accept sorted labels and produce index-aligned data
    function buildDatasets(data, labels) {
        if (!labels) labels = getLabels(data);
        const sites = [...new Set(data.map(d => d.site))];
        return sites.map((site, i) => {
            const pts = data.filter(d => d.site === site);
            return {
                label: site,
                data: labels.map(lbl => {
                    const pt = pts.find(p => p.date === lbl);
                    return pt ? pt.area : null;
                }),
                borderColor: COLORS[i % COLORS.length],
                backgroundColor: COLORS[i % COLORS.length] + '22',
                tension: 0.35,
                pointRadius: 5,
                pointHoverRadius: 7,
                fill: false,
                spanGaps: false,
            };
        });
    }

    const ctx = document.getElementById('woundChart');
    if (ctx && rawData.length > 0) {
        const sortedRaw = [...rawData].sort((a,b) => a.date.localeCompare(b.date));
        const labels    = getLabels(sortedRaw);
        const datasets  = buildDatasets(sortedRaw, labels);
        chartInstance = new Chart(ctx, {
            type: 'line',
            data: { labels: labels.map(fmtChartDate), datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16, font: { size: 12 } } },
                    tooltip: {
                        callbacks: {
                            label: c => ` ${c.dataset.label}: ${c.parsed.y} cm²`,
                        }
                    }
                },
                scales: {
                    x: {
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

    // ── Edit Wound Measurement ─────────────────────────────────────────────────────────
    const woundEditModal = document.getElementById('woundEditModal');
    document.addEventListener('click', e => {
        const btn = e.target.closest('.edit-wound-btn');
        if (!btn) return;
        document.getElementById('woundEditId').value    = btn.dataset.id;
        document.getElementById('woundEditDate').value  = btn.dataset.date;
        document.getElementById('woundEditSite').value  = btn.dataset.site;
        document.getElementById('woundEditType').value  = btn.dataset.type  || '';
        document.getElementById('woundEditLen').value   = btn.dataset.len;
        document.getElementById('woundEditWid').value   = btn.dataset.wid;
        document.getElementById('woundEditDep').value   = btn.dataset.dep   || '';
        document.getElementById('woundEditNotes').value = btn.dataset.notes || '';
        document.getElementById('woundEditErr').style.display = 'none';
        woundEditModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    });

    window.closeWoundEditModal = function() {
        woundEditModal.style.display = 'none';
        document.body.style.overflow = '';
    };

    window.submitWoundEdit = async function() {
        const id    = parseInt(document.getElementById('woundEditId').value);
        const date  = document.getElementById('woundEditDate').value;
        const site  = document.getElementById('woundEditSite').value.trim();
        const type  = document.getElementById('woundEditType').value.trim();
        const len   = parseFloat(document.getElementById('woundEditLen').value);
        const wid   = parseFloat(document.getElementById('woundEditWid').value);
        const dep   = parseFloat(document.getElementById('woundEditDep').value) || 0;
        const notes = document.getElementById('woundEditNotes').value.trim();
        const errEl = document.getElementById('woundEditErr');
        errEl.style.display = 'none';
        if (!site || len <= 0 || wid <= 0) {
            errEl.textContent = 'Wound site, length, and width are required.';
            errEl.style.display = 'block';
            return;
        }
        try {
            const r = await fetch(BASE + '/api/wounds.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'edit', csrf: CSRF, id,
                    measured_at: date, wound_site: site, wound_type: type,
                    length_cm: len, width_cm: wid, depth_cm: dep, notes }),
            });
            const d = await r.json();
            if (!d.ok) throw new Error(d.error || 'Could not update.');
            closeWoundEditModal();
            location.reload();
        } catch(err) {
            errEl.textContent = err.message;
            errEl.style.display = 'block';
        }
    };
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
        if (!await pdConfirm({message: 'Remove this diagnosis?', confirmLabel: 'Remove', confirmIcon: 'bi bi-trash3', confirmStyle: 'background:#dc2626;'})) return;
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
                pdToast(data.error || 'Could not remove.', 'error');
                btn.disabled = false;
            }
        } catch { pdToast('Network error.', 'error'); btn.disabled = false; }
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

if (!$isPartial) include __DIR__ . '/includes/header.php';
// Inline script for status widget (always needed on this page)
$statusCsrfInline = csrfToken();
if ($isPartial) ob_start(); // buffer + discard page chrome in partial requests
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
<nav class="flex items-center gap-1.5 text-sm mb-5">
    <a href="<?= BASE_URL ?>/patients.php"
       class="inline-flex items-center gap-1 text-slate-400 hover:text-blue-600 transition-colors font-medium">
        <i class="bi bi-people text-xs"></i> Patients
    </a>
    <i class="bi bi-chevron-right text-[10px] text-slate-300"></i>
    <span class="text-slate-700 font-semibold truncate"><?= h($patient['first_name'] . ' ' . $patient['last_name']) ?></span>
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
            pdToast(d.error || 'Could not update visit.', 'error');
        }
    })
    .catch(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Mark Complete'; });
}
</script>
<?php endif; ?>

<!-- Patient Header Card -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-6">
    <!-- Gradient accent stripe -->
    <div class="h-1.5 bg-gradient-to-r from-blue-500 via-violet-500 to-indigo-500"></div>
    <div class="p-5">
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div class="flex items-center gap-4">
            <!-- Patient avatar: photo if set, else initials gradient -->
            <div class="relative flex-shrink-0 group" id="ptAvatarWrap">
                <?php if (!empty($patient['photo_url'])): ?>
                <img id="ptAvatarImg"
                     src="<?= h($patient['photo_url']) ?>"
                     alt="<?= h($patient['first_name']) ?>"
                     class="w-16 h-16 rounded-2xl object-cover shadow-md border-2 border-white ring-2 ring-blue-100">
                <?php else: ?>
                <div id="ptAvatarImg"
                     class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-500 to-indigo-600 grid place-items-center
                            text-white font-extrabold text-2xl shadow-md">
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
                <h2 id="ptNameDisplay" class="text-xl font-extrabold text-slate-800 flex items-center gap-2 flex-wrap">
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
                <div id="ptSubInfo" class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-sm text-slate-500">
                    <?php if ($patient['dob']):
                        $ptAge = (int)(new DateTime($patient['dob']))->diff(new DateTime('today'))->y;
                    ?>
                    <span id="ptDobDisplay"><i class="bi bi-calendar3 mr-1"></i><?= date('M j, Y', strtotime($patient['dob'])) ?> &middot; <strong class="text-slate-700"><?= $ptAge ?> yrs</strong></span>
                    <?php endif; ?>
                    <?php if ($patient['phone']): ?>
                    <span id="ptPhoneDisplay"><i class="bi bi-telephone mr-1"></i><?= h($patient['phone']) ?></span>
                    <?php endif; ?>
                    <?php if ($patient['company'] && $patient['company'] !== 'Beyond Wound Care Inc.'): ?>
                    <span class="text-blue-600 bg-blue-50 px-2 py-0.5 rounded-md"><i class="bi bi-building mr-1"></i><?= h($patient['company']) ?></span>
                    <?php endif; ?>
                    <?php if ($patient['insurance']): ?>
                    <span id="ptInsDisplay"><i class="bi bi-shield-plus mr-1"></i><?= h($patient['insurance']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($patient['assigned_ma_name'])): ?>
                    <span id="ptMaBadge" class="text-blue-700 bg-blue-50 px-2 py-0.5 rounded-md"><i class="bi bi-person-badge mr-1"></i><?= h($patient['assigned_ma_name']) ?></span>
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
            <button id="ptEditBtn" onclick="openPtEditDrawer()"
               class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-slate-700
                      bg-slate-100 hover:bg-slate-200 rounded-xl transition-colors">
                <i class="bi bi-pencil-fill"></i> Edit
            </button>
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

    </div><!-- /p-5 -->
    <?php if ($patient['address'] || $patient['pcp'] || $patient['email'] || !empty($patient['race']) || !empty($patient['insurance_id']) || !empty($patient['pharmacy_name'])): ?>
    <div id="ptInfoBar" class="px-5 pb-4 pt-0 border-t border-slate-50 flex flex-wrap gap-x-5 gap-y-1.5 text-sm text-slate-500">
        <?php if ($patient['email']): ?><span style="word-break:break-all"><i class="bi bi-envelope mr-1"></i><?= h($patient['email']) ?></span><?php endif; ?>
        <?php if ($patient['address']): ?><span><i class="bi bi-geo-alt mr-1"></i><?= h($patient['address']) ?></span><?php endif; ?>
        <?php if ($patient['pcp']): ?><span><i class="bi bi-person-badge mr-1"></i>PCP: <?= h($patient['pcp']) ?></span><?php endif; ?>
        <?php if (!empty($patient['race'])): ?><span><i class="bi bi-people mr-1"></i><?= h($patient['race']) ?></span><?php endif; ?>
        <?php if (!empty($patient['insurance_id'])): ?><span><i class="bi bi-credit-card mr-1"></i>ID: <?= h($patient['insurance_id']) ?></span><?php endif; ?>
        <?php if (!empty($patient['pharmacy_name'])): ?><span><i class="bi bi-prescription2 mr-1 text-emerald-500"></i><?= h($patient['pharmacy_name']) ?><?php if (!empty($patient['pharmacy_phone'])): ?> &middot; <?= h($patient['pharmacy_phone']) ?><?php endif; ?></span><?php endif; ?>
        <?php if (!empty($patient['discharged_at']) && ($patient['status'] ?? '') === 'discharged'): ?>
        <span class="text-red-500"><i class="bi bi-calendar-x mr-1"></i>Discharged: <?= date('M j, Y', strtotime($patient['discharged_at'])) ?></span>
        <?php endif; ?>
    </div>

    <?php if (!empty($patient['insurance_photo']) || !empty($patient['insurance_photo_back']) || !empty($patient['sss_photo'])): ?>
    <div class="px-5 pb-4 pt-3 border-t border-slate-100">
        <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">Documents on file</p>
        <div class="flex flex-wrap gap-3">
            <?php foreach ([
                ['insurance_photo',      'Insurance Front'],
                ['insurance_photo_back', 'Insurance Back'],
                ['sss_photo',            'SSS / Gov ID'],
            ] as [$field, $docLabel]): ?>
            <?php if (!empty($patient[$field])): ?>
            <div class="text-center">
                <img src="<?= h($patient[$field]) ?>"
                     class="h-16 w-24 object-cover rounded-xl border border-slate-200 cursor-pointer hover:opacity-80 transition"
                     onclick="document.getElementById('docViewer').src=this.src; document.getElementById('docViewerModal').classList.remove('hidden');"
                     title="<?= h($docLabel) ?>">
                <p class="text-xs text-slate-400 mt-1"><?= h($docLabel) ?></p>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (!empty($patient['pharmacy_name']) || !empty($patient['assigned_ma_name'])): ?>
    <div class="px-5 py-4 border-t border-slate-100">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
            <?php if (!empty($patient['pharmacy_name'])): ?>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">
                    <i class="bi bi-prescription2 text-emerald-500 mr-1"></i> Pharmacy Details
                </p>
                <div class="space-y-1 text-sm">
                    <div class="font-semibold text-slate-700"><?= h($patient['pharmacy_name']) ?></div>
                    <?php if (!empty($patient['pharmacy_phone'])): ?>
                    <div class="text-slate-500"><i class="bi bi-telephone mr-1 text-slate-400"></i><?= h($patient['pharmacy_phone']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($patient['pharmacy_address'])): ?>
                    <div class="text-slate-500"><i class="bi bi-geo-alt mr-1 text-slate-400"></i><?= h($patient['pharmacy_address']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (!empty($patient['assigned_ma_name'])): ?>
            <div>
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">
                    <i class="bi bi-person-badge text-blue-500 mr-1"></i> Assigned MA
                </p>
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 bg-blue-100 rounded-full grid place-items-center shrink-0">
                        <i class="bi bi-person-fill text-blue-600 text-sm"></i>
                    </div>
                    <span class="text-sm font-semibold text-slate-700"><?= h($patient['assigned_ma_name']) ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (canAccessClinical()): ?>
    <!-- Inline Status Change -->
    <div class="px-5 py-3 border-t border-slate-100 flex flex-wrap items-center gap-3 no-print" id="status-widget">
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

<!-- ── Document Viewer Modal ──────────────────────────────────────────────── -->
<div id="docViewerModal"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4 no-print"
     onclick="this.classList.add('hidden')">
    <img id="docViewer" src="" class="max-h-[90vh] max-w-full rounded-2xl shadow-2xl" alt="Document">
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

<!-- Provider -->
        <div class="flex items-start gap-2.5 bg-slate-50 rounded-xl p-3">
            <div class="w-8 h-8 rounded-lg bg-blue-100 grid place-items-center flex-shrink-0">
                <i class="bi bi-person-fill text-blue-600 text-sm"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-slate-400 font-medium">Provider</p>
                <p class="text-sm font-semibold text-slate-700 truncate"><?= h($lastVisit['display_provider'] ?? 'Unknown') ?></p>
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
<?php if ($checklistVisitType && canAccessClinical() && !isMa()): ?>
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

<?php if (canAccessClinical() && !isMa()): ?>
<div class="mb-6">
    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-3">Start a Form</h3>

    <?php if (!$canStartForms): ?>
    <!-- No visit today — most forms locked, intake forms still accessible -->
    <div class="relative rounded-2xl overflow-hidden">
        <!-- Dim overlay -->
        <div class="absolute inset-0 bg-white/80 backdrop-blur-[2px] z-10 flex flex-col items-center justify-center gap-2 rounded-2xl">
            <div class="flex items-center gap-2.5 bg-amber-50 border border-amber-200 rounded-xl px-5 py-3 shadow-sm">
                <i class="bi bi-calendar-x text-amber-500 text-xl shrink-0"></i>
                <div>
                    <p class="text-sm font-bold text-amber-800">No visit scheduled for today</p>
                    <p class="text-xs text-amber-600 mt-0.5">Add a visit on the <a href="<?= BASE_URL ?>/schedule.php" class="underline hover:text-amber-800">Schedule</a> to unlock visit forms.</p>
                </div>
            </div>
        </div>
        <!-- Greyed-out tiles (decorative, pointer-events blocked) -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 pointer-events-none select-none opacity-40">
            <?php foreach ($formDefs as $type => $def): ?>
            <div class="flex flex-col items-center gap-2.5 p-4 bg-white rounded-2xl border-2 border-slate-100">
                <div class="w-12 h-12 <?= $def['bg'] ?> rounded-xl grid place-items-center">
                    <i class="bi <?= $def['icon'] ?> <?= $def['text'] ?> text-xl"></i>
                </div>
                <span class="text-xs font-semibold text-slate-700 text-center leading-snug"><?= $def['label'] ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <!-- Intake forms always available (no visit needed) -->
    <?php $intakeDefs = array_intersect_key($formDefs, array_flip(['new_patient', 'new_patient_pocket', 'pf_signup'])); ?>
    <?php if (!empty($intakeDefs)): ?>
    <div class="mt-3">
        <p class="text-xs text-slate-400 font-medium mb-2 uppercase tracking-wide">Intake forms (no visit required)</p>
        <div class="flex flex-wrap gap-3">
            <?php foreach ($intakeDefs as $type => $def): ?>
            <a href="<?= BASE_URL ?>/forms/<?= $type ?>.php?patient_id=<?= $id ?>"
               class="flex flex-col items-center gap-2 p-4 bg-white rounded-2xl border-2 border-blue-100
                      hover:border-blue-300 hover:shadow-md transition-all group cursor-pointer w-36">
                <div class="w-12 h-12 <?= $def['bg'] ?> rounded-xl grid place-items-center group-hover:scale-105 transition-transform">
                    <i class="bi <?= $def['icon'] ?> <?= $def['text'] ?> text-xl"></i>
                </div>
                <span class="text-xs font-semibold text-slate-700 text-center leading-snug"><?= $def['label'] ?></span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- Visit exists today — normal tiles -->
    <?php if (isAdmin() && !$hasVisitToday): ?>
    <div class="flex items-center gap-2 text-xs text-blue-600 bg-blue-50 border border-blue-100 rounded-xl px-3 py-2 mb-3">
        <i class="bi bi-shield-check shrink-0"></i> Admin override — no visit scheduled today, but you can still start forms.
    </div>
    <?php endif; ?>
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
        <a href="<?= BASE_URL ?>/forms/new_patient_pocket.php?patient_id=<?= $id ?>&np_type=primary_care"
           class="form-tile flex flex-col items-center gap-2.5 p-4 bg-white rounded-2xl border-2 border-slate-100
                  hover:border-blue-300 hover:shadow-md transition-all group cursor-pointer">
            <div class="w-12 h-12 bg-indigo-100 rounded-xl grid place-items-center transition-colors group-hover:scale-105">
                <i class="bi bi-folder2-open text-indigo-700 text-xl"></i>
            </div>
            <span class="text-xs font-semibold text-slate-700 text-center leading-snug">New Patient Pocket<br><span class="text-indigo-500 font-normal">Primary Care</span></span>
        </a>
    </div>
    <?php endif; ?>
</div>
<?php if ($canStartForms): ?>
<a href="<?= BASE_URL ?>/forms/wound_care.php?patient_id=<?= $id ?>"
   class="inline-flex items-center gap-2 mb-6 px-5 py-3 bg-violet-600 hover:bg-violet-700 text-white
          font-semibold rounded-xl transition-all shadow-sm hover:shadow-md active:scale-95 text-sm">
    <i class="bi bi-camera-fill"></i> Add Wound Photos
</a>
<?php else: ?>
<button disabled
        class="inline-flex items-center gap-2 mb-6 px-5 py-3 bg-slate-200 text-slate-400 cursor-not-allowed
               font-semibold rounded-xl text-sm">
    <i class="bi bi-camera-fill"></i> Add Wound Photos
</button>
<?php endif; ?>
<?php endif; // canAccessClinical form tiles ?>

<!-- Tab Nav -->
<style>
/* Prevent horizontal scroll from full-bleed tab bar negative margins */
.page-fade { overflow-x: clip; }
@keyframes pvSpin { from { transform:rotate(0deg); } to { transform:rotate(360deg); } }
/* Lightbox responsive */
.pvlb-body    { display:flex; flex:1; min-height:0; overflow:hidden; }
.pvlb-imgwrap { flex:1; min-width:0; }
.pvlb-sidebar { width:240px; flex-shrink:0; overflow-y:auto; }
@media (max-width:640px) {
    #pvLbDialog { border-radius:1rem !important; max-height:96vh; }
    .pvlb-body    { flex-direction:column; overflow-y:auto !important; overflow-x:hidden; }
    .pvlb-imgwrap { flex:none; min-height:52vw; max-height:56vw; }
    .pvlb-sidebar { width:100% !important; border-left:none !important; border-top:1px solid #f1f5f9; }
}
/* Overlay controls — fade at rest, reveal on hover (desktop) / always show (touch) */
.pvlb-overlay { opacity:0.12; transition:opacity .22s ease; }
#pvLbDialog:hover .pvlb-overlay,
#pvLbDialog.pvlb-show-ui .pvlb-overlay { opacity:1; }
@media (max-width:640px) { .pvlb-overlay { opacity:1 !important; } }
/* Sidebar toggle */
#pvLbDialog.pvlb-no-sidebar .pvlb-sidebar { display:none !important; }
#pvLbSidebarToggle { color:#94a3b8; transition:color .15s; }
#pvLbDialog:not(.pvlb-no-sidebar) #pvLbSidebarToggle { color:#7c3aed; }
/* Annotation bar */
#pvAnnotateBar { display:none; position:absolute; top:.625rem; left:50%; transform:translateX(-50%);
    z-index:20; background:rgba(0,0,0,.82); border-radius:.875rem;
    padding:.25rem .375rem; align-items:center; gap:.2rem;
    overflow-x:auto; max-width:calc(100% - .5rem); white-space:nowrap; }
#pvAnnotateBar button {
    width:1.75rem; height:1.75rem; border:none; border-radius:.5rem; cursor:pointer;
    display:inline-flex; align-items:center; justify-content:center; font-size:.75rem;
    background:transparent; color:#fff; flex-shrink:0; }
#pvAnnotateBar button:hover { background:rgba(255,255,255,.15); }
.pv-ann-sep { display:inline-block; width:1px; height:1.25rem; background:rgba(255,255,255,.2); margin:0 .1rem; vertical-align:middle; flex-shrink:0; }
/* Forms table — compact on mobile */
@media(max-width:767px){
  .form-tbl td, .form-tbl th { padding-left:6px; padding-right:6px; }
  .form-tbl td:first-child, .form-tbl th:first-child { padding-left:10px; }
  .form-tbl .form-act-btn { padding:6px 8px; }
  .form-tbl .btn-txt { display:none; }
  .form-tbl .form-icon-box { padding:5px; }
  .form-tbl .form-icon-box i { font-size:.8125rem; }
  .form-tbl .form-name-flex { gap:8px; }
  .form-name-text { max-width:100%; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
}
.pt-tab-bar::-webkit-scrollbar{display:none}
.pt-tab { flex-shrink:0; display:inline-flex; align-items:center; gap:5px; position:relative;
           padding:9px 13px; border-radius:12px; font-size:.8125rem; font-weight:600;
           white-space:nowrap; transition:all .15s; -webkit-tap-highlight-color:transparent; }
.pt-tab-active { background:#fff; box-shadow:0 1px 4px rgba(0,0,0,.12); }
.pt-tab-inactive { color:#64748b; }
.pt-tab-inactive:hover { color:#334155; background:rgba(0,0,0,.04); }
.pt-badge { display:inline-flex; align-items:center; justify-content:center;
            font-size:.625rem; font-weight:700; min-width:18px; height:18px;
            padding:0 4px; border-radius:99px; line-height:1; }
/* Mobile: icon-only tabs that fill the full bar width */
@media(max-width:767px){
  .pt-tab-bar { overflow-x:visible; gap:1px; }
  .pt-tab { flex:1; justify-content:center; padding:9px 2px; gap:0; min-width:0; }
  .pt-tab i { font-size:1rem; }
  .pt-tab > span:not(.pt-badge) { display:none; }
  .pt-badge { position:absolute; top:3px; right:3px; min-width:14px; height:14px;
              padding:0 2px; font-size:.5rem; border-radius:99px; }
}
</style>
<div class="sticky top-14 md:top-0 z-30 -mx-4 sm:-mx-6 px-3 sm:px-5 py-2 mb-5 bg-white/97 backdrop-blur-sm
            border-b border-slate-100 shadow-sm no-print" style="transition:box-shadow .2s;">
    <div class="pt-tab-bar flex gap-0.5 overflow-x-auto relative" style="scrollbar-width:none;-webkit-overflow-scrolling:touch;">
        <a href="?id=<?= $id ?>&tab=forms"
           onclick="ptTab('forms');return false;" data-tab="forms"
           class="pt-tab <?= $activeTab==='forms' ? 'pt-tab-active text-blue-700' : 'pt-tab-inactive' ?>">
            <i class="bi bi-file-earmark-text"></i>
            <span>Forms</span>
            <?php if (count($forms)): ?><span class="pt-badge bg-blue-100 text-blue-700"><?= count($forms) ?></span><?php endif; ?>
        </a>
        <?php if (canAccessClinical()): ?>
        <a href="?id=<?= $id ?>&tab=meds"
           onclick="ptTab('meds');return false;" data-tab="meds"
           class="pt-tab <?= $activeTab==='meds' ? 'pt-tab-active text-emerald-700' : 'pt-tab-inactive' ?>">
            <i class="bi bi-capsule"></i>
            <span>Meds</span>
            <?php if (!empty($activeMedsList)): ?><span class="pt-badge bg-emerald-100 text-emerald-700"><?= count($activeMedsList) ?></span><?php endif; ?>
        </a>
        <a href="?id=<?= $id ?>&tab=photos"
           onclick="ptTab('photos');return false;" data-tab="photos"
           class="pt-tab <?= $activeTab==='photos' ? 'pt-tab-active text-violet-700' : 'pt-tab-inactive' ?>">
            <i class="bi bi-camera"></i>
            <span>Photos</span>
            <?php if (count($photos)): ?><span class="pt-badge bg-violet-100 text-violet-700"><?= count($photos) ?></span><?php endif; ?>
        </a>
        <a href="?id=<?= $id ?>&tab=wounds"
           onclick="ptTab('wounds');return false;" data-tab="wounds"
           class="pt-tab <?= $activeTab==='wounds' ? 'pt-tab-active text-rose-700' : 'pt-tab-inactive' ?>">
            <i class="bi bi-rulers"></i>
            <span>Wounds</span>
            <?php if (!empty($woundMeasurements)): ?><span class="pt-badge bg-rose-100 text-rose-700"><?= count($woundMeasurements) ?></span><?php endif; ?>
        </a>
        <a href="?id=<?= $id ?>&tab=diagnoses"
           onclick="ptTab('diagnoses');return false;" data-tab="diagnoses"
           class="pt-tab <?= $activeTab==='diagnoses' ? 'pt-tab-active text-orange-700' : 'pt-tab-inactive' ?>">
            <i class="bi bi-clipboard2-pulse"></i>
            <span>Diagnoses</span>
            <?php if (!empty($diagList)): ?><span class="pt-badge bg-orange-100 text-orange-700"><?= count($diagList) ?></span><?php endif; ?>
        </a>
        <a href="?id=<?= $id ?>&tab=vitals"
           onclick="ptTab('vitals');return false;" data-tab="vitals"
           class="pt-tab <?= $activeTab==='vitals' ? 'pt-tab-active text-sky-700' : 'pt-tab-inactive' ?>">
            <i class="bi bi-activity"></i>
            <span>Vitals</span>
            <?php if (!empty($vitalsRows)): ?><span class="pt-badge bg-sky-100 text-sky-700"><?= count($vitalsRows) ?></span><?php endif; ?>
        </a>
        <a href="?id=<?= $id ?>&tab=care"
           onclick="ptTab('care');return false;" data-tab="care"
           class="pt-tab <?= $activeTab==='care' ? 'pt-tab-active text-teal-700' : 'pt-tab-inactive' ?>">
            <i class="bi bi-chat-square-text-fill"></i>
            <span>Care</span>
            <?php $cnCount = !empty($careNotes['top']) ? count($careNotes['top']) : 0; ?>
            <?php if ($cnCount): ?><span class="pt-badge bg-teal-100 text-teal-700"><?= $cnCount ?></span><?php endif; ?>
        </a>
        <a href="?id=<?= $id ?>&tab=notes"
           onclick="ptTab('notes');return false;" data-tab="notes"
           class="pt-tab <?= $activeTab==='notes' ? 'pt-tab-active text-blue-700' : 'pt-tab-inactive' ?>">
            <i class="bi bi-journal-medical"></i>
            <span>Notes</span>
            <?php if (!empty($soapNotes)): ?><span class="pt-badge bg-blue-100 text-blue-700"><?= count($soapNotes) ?></span><?php endif; ?>
        </a>
        <?php endif; // canAccessClinical ?>
        <?php if (isAdmin()): ?>
        <a href="?id=<?= $id ?>&tab=audit"
           onclick="ptTab('audit');return false;" data-tab="audit"
           class="pt-tab <?= $activeTab==='audit' ? 'pt-tab-active text-slate-800' : 'pt-tab-inactive' ?>">
            <i class="bi bi-shield-lock"></i>
            <span>Audit</span>
            <?php if (!empty($patientAudit)): ?><span class="pt-badge bg-slate-200 text-slate-600"><?= count($patientAudit) ?></span><?php endif; ?>
        </a>
        <?php endif; ?>
    </div>
</div>
<?php if ($isPartial): ob_end_clean(); endif; ?>
<script>
/* ── Ajax tab switching ──────────────────────────────────────── */
(function(){
    var _ptPID     = <?= (int)$id ?>;
    var _ptLoading = false;
    var _ptColors  = {
        forms:'text-blue-700', meds:'text-emerald-700', photos:'text-violet-700',
        wounds:'text-rose-700', diagnoses:'text-orange-700', vitals:'text-sky-700',
        care:'text-teal-700', notes:'text-blue-700', audit:'text-slate-800'
    };

    function execScripts(container) {
        var scripts = Array.from(container.querySelectorAll('script'));
        var chain = Promise.resolve();
        scripts.forEach(function(old) {
            chain = chain.then(function() {
                return new Promise(function(resolve) {
                    var s = document.createElement('script');
                    if (old.src) {
                        if (document.querySelector('script[src="' + old.src + '"]')) {
                            resolve(); return; // already loaded, skip
                        }
                        s.src    = old.src;
                        s.onload  = resolve;
                        s.onerror = resolve;
                        document.body.appendChild(s);
                    } else {
                        s.textContent = old.textContent;
                        document.body.appendChild(s);
                        resolve();
                    }
                });
            });
        });
        return chain;
    }

    window.ptTab = function(name) {
        if (_ptLoading) return;
        _ptLoading = true;
        history.pushState({tab: name}, '', '?id=' + _ptPID + '&tab=' + encodeURIComponent(name));

        // Update tab nav active states
        var allColors = Object.values(_ptColors);
        document.querySelectorAll('.pt-tab').forEach(function(el) {
            var t = el.dataset.tab;
            if (!t) return;
            el.classList.remove('pt-tab-active', 'pt-tab-inactive');
            allColors.forEach(function(c){ el.classList.remove(c); });
            if (t === name) {
                el.classList.add('pt-tab-active', _ptColors[t] || 'text-blue-700');
            } else {
                el.classList.add('pt-tab-inactive');
            }
        });

        var body = document.getElementById('pt-tab-body');
        body.style.opacity = '0.4';
        body.style.transition = 'opacity .15s';

        fetch('?id=' + _ptPID + '&tab=' + encodeURIComponent(name) + '&_pt=1')
            .then(function(r){ return r.text(); })
            .then(function(html){
                body.innerHTML = html;
                body.style.opacity = '1';
                execScripts(body);
                _ptLoading = false;
            })
            .catch(function(){
                body.style.opacity = '1';
                _ptLoading = false;
                location.href = '?id=' + _ptPID + '&tab=' + encodeURIComponent(name);
            });
    };

    window.addEventListener('popstate', function(e){
        if (e.state && e.state.tab) ptTab(e.state.tab);
    });
    history.replaceState({tab: <?= json_encode($activeTab) ?>}, '', location.href);
})();
</script>
<div id="pt-tab-body">
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

    <div>
        <table class="w-full text-sm form-tbl">
            <thead>
                <tr class="bg-slate-50 text-left border-b border-slate-100">
                    <th class="pl-5 pr-2 py-3.5 w-8">
                        <input type="checkbox" id="chkAll"
                               class="w-3.5 h-3.5 text-blue-600 border-slate-300 rounded cursor-pointer"
                               title="Select all">
                    </th>
                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Form</th>
                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide hidden md:table-cell">Provider</th>
                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide hidden sm:table-cell">Date</th>
                    <th class="px-4 py-3.5 text-xs font-bold text-slate-500 uppercase tracking-wide">Status</th>
                    <th class="px-4 py-3.5 hidden md:table-cell"></th>
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
                <tr class="hover:bg-slate-50/70 transition-colors form-row md:cursor-default cursor-pointer"
                    data-type="<?= h($f['form_type']) ?>"
                    data-date="<?= h($rowDate) ?>"
                    onclick="window.location='<?= BASE_URL ?>/view_document.php?id=<?= $f['id'] ?>'">
                    <td class="pl-5 pr-2 py-4" onclick="event.stopPropagation()">
                        <input type="checkbox" class="form-chk w-3.5 h-3.5 text-blue-600 border-slate-300 rounded cursor-pointer"
                               value="<?= $f['id'] ?>" onchange="updateBatch()">
                    </td>
                    <td class="px-4 py-4">
                        <div class="form-name-flex flex items-center gap-3">
                            <span class="form-icon-box flex-shrink-0 <?= $fd['bg'] ?> <?= $fd['text'] ?> p-2 rounded-xl">
                                <i class="bi <?= $fd['icon'] ?> text-base"></i>
                            </span>
                            <div class="min-w-0">
                                <span class="form-name-text font-medium text-slate-700"><?= $fd['label'] ?></span>
                                <?php
                                $fvVer      = $fvMap[$f['id']] ?? 1;
                                $fvTot      = $fvTotal[$f['form_type']] ?? 1;
                                $fvIsLatest = ($fvLatest[$f['form_type']] ?? null) === $f['id'];
                                if ($fvTot > 1): ?>
                                <span class="block mt-0.5 ml-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full w-fit
                                             <?= $fvIsLatest ? 'bg-indigo-100 text-indigo-600' : 'bg-slate-100 text-slate-400' ?>">
                                    <?= $fvIsLatest ? 'Latest' : 'v' . $fvVer . ' of ' . $fvTot ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-4 text-slate-500 hidden md:table-cell"><?= h($f['display_provider'] ?? '—') ?></td>
                    <td class="px-4 py-4 text-slate-500 hidden sm:table-cell"><?= date('M j, Y g:ia', strtotime($f['created_at'])) ?></td>
                    <td class="px-4 py-4">
                        <div class="flex items-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?= $sc['bg'] ?> <?= $sc['text'] ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?= $sc['dot'] ?>"></span>
                            <?= $sc['label'] ?>
                        </span>

                        </div>
                    </td>
                    <td class="px-4 py-4 text-right hidden md:table-cell">
                        <div class="flex items-center justify-end gap-2 flex-wrap">
                        <a href="<?= BASE_URL ?>/view_document.php?id=<?= $f['id'] ?>"
                           class="form-act-btn inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-800 font-semibold text-xs
                                  bg-blue-50 hover:bg-blue-100 px-3.5 py-2 rounded-xl transition-colors">
                            <i class="bi bi-eye-fill"></i><span class="btn-txt"> View</span>
                        </a>
                        <?php if ($f['form_type'] === 'vital_cs' && canAccessClinical()): ?>
                        <a href="<?= BASE_URL ?>/forms/vital_cs.php?patient_id=<?= $id ?>&edit=1"
                           class="form-act-btn inline-flex items-center gap-1.5 text-amber-600 hover:text-amber-800 font-semibold text-xs
                                  bg-amber-50 hover:bg-amber-100 px-3.5 py-2 rounded-xl transition-colors">
                            <i class="bi bi-pencil-fill"></i><span class="btn-txt"> Edit CS</span>
                        </a>
                        <?php endif; ?>
                        </div>
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
var chkAllEl = document.getElementById('chkAll');
if (chkAllEl) chkAllEl.addEventListener('change', function () {
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

    <!-- PDF attachment card -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                <i class="bi bi-file-earmark-pdf-fill text-red-500"></i> Medication PDFs
            </h4>
            <button id="uploadMedPdfBtn" onclick="document.getElementById('medPdfFileInput').click()"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 active:scale-95
                           text-white text-xs font-semibold rounded-xl transition-all shadow-sm">
                <i class="bi bi-upload"></i> Upload PDF
            </button>
            <input type="file" id="medPdfFileInput" accept="application/pdf,.pdf" style="display:none;">
        </div>
        <div id="medPdfUploadStatus" class="hidden mb-3 text-xs rounded-xl px-3 py-2"></div>
        <div id="medPdfList" class="space-y-2">
            <p class="text-xs text-slate-400 italic">No PDFs uploaded yet.</p>
        </div>
    </div>

    <!-- Add medication card -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-sm font-bold text-slate-700 flex items-center gap-2">
                <i class="bi bi-plus-circle-fill text-emerald-600"></i> Add Medication
            </h4>
        </div>

        <!-- Recently used chips -->
        <div id="recentMedChips" class="hidden mb-3">
            <p class="text-xs text-slate-400 mb-1.5 flex items-center gap-1"><i class="bi bi-clock-history"></i> Recently used — click to fill</p>
            <div id="recentMedList" class="flex flex-wrap gap-1.5"></div>
        </div>

        <!-- Name input with autocomplete -->
        <div class="flex flex-col sm:flex-row gap-3 mb-3">
            <div class="relative flex-[3]">
                <input id="newMedName" type="text"
                       class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent transition focus:bg-white"
                       placeholder="Medication name &amp; dose (e.g. Metformin 500mg)" autocomplete="off">
                <div id="medAcDrop" class="hidden absolute z-50 left-0 right-0 top-full mt-1 bg-white border border-slate-200 rounded-xl shadow-xl max-h-52 overflow-y-auto divide-y divide-slate-50"></div>
            </div>
            <button id="addMedBtn"
                    class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-700 active:scale-95 text-white text-sm
                           font-semibold rounded-xl transition-all shadow-sm flex items-center gap-2 whitespace-nowrap sm:self-start">
                <i class="bi bi-plus-lg"></i> Add
            </button>
        </div>

        <!-- Frequency pill buttons -->
        <div class="mb-1">
            <p class="text-xs text-slate-400 mb-2">Frequency</p>
            <div class="flex flex-wrap gap-1.5">
                <?php foreach (['QD','BID','TID','QID','PRN','Weekly','Monthly'] as $fq): ?>
                <button type="button" data-freq="<?= $fq ?>" class="freq-pill px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all">
                    <?= $fq ?>
                </button>
                <?php endforeach; ?>
                <button type="button" data-freq="other" class="freq-pill px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 text-slate-600 hover:bg-slate-200 transition-all">
                    Other…
                </button>
            </div>
            <input type="text" id="freqOtherInput"
                   class="hidden mt-2 px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white
                          focus:outline-none focus:ring-2 focus:ring-emerald-400 w-full sm:w-64"
                   placeholder="Enter frequency…">
            <!-- hidden value carrier read by JS -->
            <input type="hidden" id="newMedFreq">
        </div>

        <p id="addMedErr" class="text-xs text-red-600 mt-2 hidden"></p>

        <!-- Quick add templates (collapsible) -->
        <div class="border-t border-slate-100 mt-4 pt-4">
            <button id="toggleTemplateBtn" type="button"
                    class="flex items-center gap-2 text-xs font-semibold text-slate-500 hover:text-slate-700 transition-colors">
                <i class="bi bi-grid-3x3-gap-fill text-slate-400"></i>
                Quick Add Common Medications
                <i id="templateChev" class="bi bi-chevron-down text-[10px] transition-transform"></i>
            </button>
            <div id="templateSection" class="hidden mt-3">
                <p class="text-xs text-slate-400 mb-2">Select medications to add in bulk</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1.5 mb-3 max-h-48 overflow-y-auto pr-1">
                    <?php
                    $templates = [
                        ['Aspirin 81mg',              'QD'],
                        ['Metformin 500mg',           'BID'],
                        ['Lisinopril 10mg',           'QD'],
                        ['Amlodipine 5mg',            'QD'],
                        ['Atorvastatin 40mg',         'QD'],
                        ['Metoprolol Succinate 50mg', 'QD'],
                        ['Furosemide 40mg',           'QD'],
                        ['Pantoprazole 40mg',         'QD'],
                        ['Gabapentin 300mg',          'TID'],
                        ['Vitamin D3 2000 IU',        'QD'],
                        ['Potassium Chloride 20mEq',  'QD'],
                        ['Warfarin 5mg',              'QD'],
                        ['Clopidogrel 75mg',          'QD'],
                        ['Levothyroxine 50mcg',       'QD'],
                        ['Acetaminophen 500mg',       'PRN'],
                        ['Mupirocin 2% Ointment',     'BID'],
                        ['Silver Sulfadiazine 1% Cream','BID'],
                        ['Collagenase Santyl Ointment','QD'],
                    ];
                    foreach ($templates as [$tname, $tfreq]):
                    ?>
                    <label class="flex items-center gap-2 cursor-pointer group">
                        <input type="checkbox" class="w-3.5 h-3.5 rounded accent-emerald-600"
                               value="<?= h($tname) ?>" data-freq="<?= h($tfreq) ?>">
                        <span class="text-xs text-slate-700 group-hover:text-slate-900"><?= h($tname) ?></span>
                        <span class="text-[10px] text-slate-400 ml-auto"><?= h($tfreq) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <button id="addTemplateSelectedBtn" type="button"
                        class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-xl transition-all shadow-sm">
                    <i class="bi bi-check-lg"></i> Add Selected
                </button>
            </div>
        </div>
    </div>

    <!-- Active medications -->
    <div id="activeMedsContainer" class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
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
    'id'            => (int)$p['id'],
    'filename'      => $p['filename'],
    'location'      => $p['wound_location'] ?: 'Unspecified',
    'date'          => date('M j, Y', strtotime($p['created_at'])),
    'date_raw'      => $p['created_at'],
    'desc'          => $p['description'] ?? '',
    'ma'            => $p['ma_name'] ?? '',
    'url'           => BASE_URL . '/uploads/photos/' . $p['filename'],
    'annotated_url'     => $p['annotated_photo_path'] ?? null,
    'man_annotated_url' => $p['man_annotated_path']   ?? null,
    'area_cm2'      => $p['area_cm2']    !== null ? (float)$p['area_cm2']    : null,
    'length_cm'     => $p['ai_length_cm'] !== null ? (float)$p['ai_length_cm'] : null,
    'width_cm'      => $p['ai_width_cm']  !== null ? (float)$p['ai_width_cm']  : null,
    'ruler'         => (bool)($p['meas_ruler'] ?? false),
    'gran_pct'      => $p['granulation_pct'] !== null ? (int)$p['granulation_pct'] : null,
    'slough_pct'    => $p['slough_pct']      !== null ? (int)$p['slough_pct']      : null,
    'eschar_pct'    => $p['eschar_pct']      !== null ? (int)$p['eschar_pct']      : null,
    'confidence'    => $p['analysis_confidence'] ?? null,
    'visit_type'    => $p['visit_type'] ?? null,
    'manual_length' => $p['length_cm']  !== null ? (float)$p['length_cm']  : null,
    'manual_width'  => $p['width_cm']   !== null ? (float)$p['width_cm']   : null,
    'manual_depth'  => $p['depth_cm']   !== null ? (float)$p['depth_cm']   : null,
    'man_area_cm2'  => $p['man_area_cm2']  !== null ? (float)$p['man_area_cm2']  : null,
    'man_length_cm' => $p['man_length_cm'] !== null ? (float)$p['man_length_cm'] : null,
    'man_width_cm'  => $p['man_width_cm']  !== null ? (float)$p['man_width_cm']  : null,
    'man_depth_cm'  => $p['man_depth_cm']  !== null ? (float)$p['man_depth_cm']  : null,
    'man_by_name'   => $p['man_by_name']   ?? null,
    'man_by_role'   => $p['man_by_role']   ?? null,
    'man_date'      => isset($p['man_measured_at']) && $p['man_measured_at']
                        ? date('M j, Y', strtotime($p['man_measured_at'])) : null,
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
                    <!-- Analyzed badge -->
                    <?php if (!empty($ph['annotated_photo_path'])): ?>
                    <div style="position:absolute;top:.375rem;left:.375rem;width:1.375rem;height:1.375rem;
                                background:#7c3aed;border-radius:9999px;display:flex;align-items:center;
                                justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.3);z-index:11;"
                         title="AI Analyzed">
                        <i class="bi bi-rulers" style="color:#fff;font-size:.55rem;"></i>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="p-3">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.25rem;">
                        <div style="min-width:0;">
                            <p class="text-xs font-semibold text-slate-700 truncate"><?= h($location) ?></p>
                            <p class="text-xs text-slate-400 mt-0.5"><?= date('M j, Y', strtotime($ph['created_at'])) ?></p>
                            <?php if ($ph['description']): ?>
                            <p class="text-xs text-slate-500 mt-1 line-clamp-2"><?= h($ph['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if (!isBilling()): ?>
                        <div style="display:flex;gap:.125rem;flex-shrink:0;">
                            <button onclick="event.stopPropagation();pvEditPhoto(<?= (int)$ph['id'] ?>)"
                                    title="Edit photo details"
                                    style="width:22px;height:22px;border:none;background:none;cursor:pointer;color:#94a3b8;
                                           border-radius:.375rem;display:flex;align-items:center;justify-content:center;padding:0;"
                                    onmouseover="this.style.color='#7c3aed';this.style.background='#ede9fe'"
                                    onmouseout="this.style.color='#94a3b8';this.style.background='none'">
                                <i class="bi bi-pencil" style="font-size:.7rem;"></i>
                            </button>
                            <button onclick="event.stopPropagation();pvDeletePhoto(<?= (int)$ph['id'] ?>)"
                                    title="Delete photo"
                                    style="width:22px;height:22px;border:none;background:none;cursor:pointer;color:#94a3b8;
                                           border-radius:.375rem;display:flex;align-items:center;justify-content:center;padding:0;"
                                    onmouseover="this.style.color='#ef4444';this.style.background='#fef2f2'"
                                    onmouseout="this.style.color='#94a3b8';this.style.background='none'">
                                <i class="bi bi-trash3" style="font-size:.7rem;"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
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

    if (!toggleBtn) return;

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

    // Photo IDs in display order (for lightbox prev/next)
    const photoIds = PHOTOS.map(p => p.id);

    window.photoCardClick = function(card) {
        if (!compareMode) {
            openPhotoLightbox(parseInt(card.dataset.photoId));
            return;
        }
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
    // ── Lightbox ────────────────────────────────────────────────────────────
    let lbCurrent = 0;

    window.openPhotoLightbox = function(id) {
        lbCurrent = photoIds.indexOf(id);
        renderPhotoLb();
        const lb = document.getElementById('pvLightbox');
        lb.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        // Briefly show overlays so user knows controls are there
        const dlg = document.getElementById('pvLbDialog');
        dlg.classList.add('pvlb-show-ui');
        setTimeout(() => dlg.classList.remove('pvlb-show-ui'), 1800);
    };
    window.closePhotoLightbox = function() {
        document.getElementById('pvLightbox').style.display = 'none';
        document.body.style.overflow = '';
    };
    window.pvLbToggleSidebar = function() {
        document.getElementById('pvLbDialog').classList.toggle('pvlb-no-sidebar');
    };
    window.pvLbStep = function(dir) {
        lbCurrent = (lbCurrent + dir + PHOTOS.length) % PHOTOS.length;
        renderPhotoLb();
    };
    // ── Zoom ────────────────────────────────────────────────────────────────
    let lbScale = 1, lbTx = 0, lbTy = 0;
    const MIN_SCALE = 1, MAX_SCALE = 5;

    function applyZoom() {
        const img = document.getElementById('pvLbImg');
        img.style.transform = `translate(${lbTx}px,${lbTy}px) scale(${lbScale})`;
        document.getElementById('pvLbZoomLabel').textContent = Math.round(lbScale * 100) + '%';
    }
    function resetZoom() {
        lbScale = 1; lbTx = 0; lbTy = 0;
        const img = document.getElementById('pvLbImg');
        img.style.transition = 'transform 0.15s ease';
        applyZoom();
    }
    window.pvLbZoom = function(delta) {
        lbScale = Math.min(MAX_SCALE, Math.max(MIN_SCALE, lbScale + delta));
        if (lbScale === 1) { lbTx = 0; lbTy = 0; }
        const img = document.getElementById('pvLbImg');
        img.style.transition = 'transform 0.15s ease';
        applyZoom();
    };
    window.pvLbZoomReset = resetZoom;

    // Scroll-wheel zoom + drag — deferred until pvLightbox HTML is in the DOM
    document.addEventListener('DOMContentLoaded', function() {
        var imgWrap = document.getElementById('pvLbImgWrap');
        if (!imgWrap) return;

        // Scroll-wheel zoom
        imgWrap.addEventListener('wheel', function(e) {
            if (document.getElementById('pvLightbox').style.display === 'none') return;
            e.preventDefault();
            const delta = e.deltaY < 0 ? 0.2 : -0.2;
            lbScale = Math.min(MAX_SCALE, Math.max(MIN_SCALE, lbScale + delta));
            if (lbScale === 1) { lbTx = 0; lbTy = 0; }
            const img = document.getElementById('pvLbImg');
            img.style.transition = 'none';
            applyZoom();
        }, { passive: false });

        // Click-drag + touch-drag to pan
        (function() {
            const wrap = imgWrap;
            const img  = document.getElementById('pvLbImg');
            let dragging = false, startX, startY, startTx, startTy;

            wrap.addEventListener('pointerdown', function(e) {
                if (e.pointerType === 'mouse' && e.button !== 0) return;
                e.preventDefault();
                wrap.setPointerCapture(e.pointerId);
                dragging = true;
                startX = e.clientX; startY = e.clientY;
                startTx = lbTx; startTy = lbTy;
                img.style.transition = 'none';
                wrap.style.cursor = 'grabbing';
            });
            wrap.addEventListener('pointermove', function(e) {
                if (!dragging) return;
                e.preventDefault();
                lbTx = startTx + (e.clientX - startX);
                lbTy = startTy + (e.clientY - startY);
                applyZoom();
            });
            wrap.addEventListener('pointerup',     function(e) { dragging = false; wrap.style.cursor = 'grab'; });
            wrap.addEventListener('pointercancel', function(e) { dragging = false; wrap.style.cursor = 'grab'; });

            let lastDist = null;
            wrap.addEventListener('touchstart', function(e) {
                if (e.touches.length === 2) {
                    lastDist = Math.hypot(
                        e.touches[0].clientX - e.touches[1].clientX,
                        e.touches[0].clientY - e.touches[1].clientY);
                }
            }, { passive: true });
            wrap.addEventListener('touchmove', function(e) {
                if (e.touches.length !== 2 || !lastDist) return;
                e.preventDefault();
                const dist = Math.hypot(
                    e.touches[0].clientX - e.touches[1].clientX,
                    e.touches[0].clientY - e.touches[1].clientY);
                lbScale = Math.min(MAX_SCALE, Math.max(MIN_SCALE, lbScale * (dist / lastDist)));
                lastDist = dist;
                if (lbScale <= 1) { lbTx = 0; lbTy = 0; lbScale = 1; }
                img.style.transition = 'none';
                applyZoom();
            }, { passive: false });
            wrap.addEventListener('touchend', function() { lastDist = null; });
        })();
    });

    function renderPhotoLb() {
        resetZoom();
        const p = PHOTOS[lbCurrent];
        document.getElementById('pvLbImg').src             = p.url;
        document.getElementById('pvLbTitle').textContent   = p.location;
        document.getElementById('pvLbDate').textContent    = p.date;
        document.getElementById('pvLbTitleMeta').textContent = p.location;
        document.getElementById('pvLbDateMeta').textContent  = p.date;
        document.getElementById('pvLbMa').textContent      = p.ma || '—';
        document.getElementById('pvLbDownload').href       = p.url;
        const descEl = document.getElementById('pvLbDescBlock');
        if (p.desc) {
            document.getElementById('pvLbDesc').textContent = p.desc;
            descEl.style.display = '';
        } else { descEl.style.display = 'none'; }
        document.getElementById('pvLbNav').style.display = PHOTOS.length > 1 ? '' : 'none';
        document.getElementById('pvLbCount').textContent = (lbCurrent + 1) + ' / ' + PHOTOS.length;
        // Reset then restore analysis state
        pvLbShowImg('orig');
        pvLbRestoreAnalysis(p);
    }

    // ── Analysis helpers ──────────────────────────────────────────────
    let _pvAnnotatedUrl = null;

    function pvLbRestoreAnalysis(p) {
        ['pvLbSpinner','pvLbAnalyzeError','pvLbResult'].forEach(id =>
            document.getElementById(id).style.display = 'none');
        document.getElementById('pvLbAnalyzeBtn').style.display = '';
        document.getElementById('pvLbImgToggle').style.display = 'none';
        document.getElementById('pvLbManualResult').style.display = 'none';
        _pvAnnotatedUrl = null;

        if (p.area_cm2 !== null && p.area_cm2 !== undefined) {
            _pvAnnotatedUrl = p.man_annotated_url || p.annotated_url || null;
            pvLbPopulateResults({
                area_cm2:       p.area_cm2,
                length_cm:      p.length_cm,
                width_cm:       p.width_cm,
                ruler_detected: p.ruler,
                annotated_url:  p.man_annotated_url || p.annotated_url,
                tissue_info: (p.gran_pct !== null && p.gran_pct !== undefined) ? {
                    granulation_pct: p.gran_pct,
                    slough_pct:      p.slough_pct,
                    eschar_pct:      p.eschar_pct,
                    confidence:      p.confidence
                } : null,
                method: p.confidence ? 'gpt4o' : 'opencv'
            });
            const btn = document.getElementById('pvLbAnalyzeBtn');
            btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Re-analyze';
        }
        pvLbShowManualResult(p);
    }

    function pvLbShowManualResult(p) {
        const el = document.getElementById('pvLbManualResult');
        if (p.man_area_cm2 === null || p.man_area_cm2 === undefined) {
            el.style.display = 'none';
            return;
        }
        document.getElementById('pvLbMMArea').textContent   =
            Number(p.man_area_cm2).toFixed(2);
        document.getElementById('pvLbMMLength').textContent =
            (p.man_length_cm != null) ? Number(p.man_length_cm).toFixed(1) : '—';
        document.getElementById('pvLbMMWidth').textContent  =
            (p.man_width_cm  != null) ? Number(p.man_width_cm).toFixed(1)  : '—';

        const roleLabel = { admin:'Admin', ma:'MA', provider:'Provider',
                            pcc:'PCC', billing:'Billing', scheduler:'Scheduler' };
        const rLabel = roleLabel[p.man_by_role] || p.man_by_role || '';
        const parts  = [];
        if (p.man_by_name) parts.push(p.man_by_name);
        if (rLabel)        parts.push('(' + rLabel + ')');
        if (p.man_date)    parts.push('\u00b7 ' + p.man_date);
        document.getElementById('pvLbManAttrib').textContent = parts.join(' ');

        // Show annotated toggle if only manual annotated exists (no AI analysis ran)
        if (p.man_annotated_url && !_pvAnnotatedUrl) {
            _pvAnnotatedUrl = p.man_annotated_url;
            document.getElementById('pvLbImgToggle').style.display = '';
            pvLbShowImg('annotated');
        }
        el.style.display = '';
    }

    window.pvLbShowImg = function(which) {
        const img       = document.getElementById('pvLbImg');
        const btnOrig   = document.getElementById('pvLbBtnOrig');
        const btnAnnot  = document.getElementById('pvLbBtnAnnotated');
        if (which === 'annotated' && _pvAnnotatedUrl) {
            img.src = _pvAnnotatedUrl;
            btnOrig.style.color  = 'rgba(255,255,255,.5)'; btnOrig.style.background  = 'transparent';
            btnAnnot.style.color = '#fff';                 btnAnnot.style.background = 'rgba(255,255,255,.2)';
        } else {
            img.src = PHOTOS[lbCurrent].url;
            btnAnnot.style.color = 'rgba(255,255,255,.5)'; btnAnnot.style.background = 'transparent';
            btnOrig.style.color  = '#fff';                 btnOrig.style.background  = 'rgba(255,255,255,.2)';
        }
    };

    window.pvLbAnalyze = function() {
        const p = PHOTOS[lbCurrent];
        const BASE_URL_JS = <?= json_encode(BASE_URL) ?>;
        document.getElementById('pvLbAnalyzeBtn').style.display  = 'none';
        document.getElementById('pvLbAnalyzeError').style.display = 'none';
        document.getElementById('pvLbResult').style.display       = 'none';
        document.getElementById('pvLbSpinner').style.display      = 'flex';

        fetch(p.url)
            .then(r => r.blob())
            .then(blob => new Promise((res, rej) => {
                const fr = new FileReader();
                fr.onload  = () => res(fr.result);
                fr.onerror = rej;
                fr.readAsDataURL(blob);
            }))
            .then(dataUrl => fetch(BASE_URL_JS + '/api/wound_measure.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ image: dataUrl, patient_id: p.patient_id || <?= $id ?>, photo_id: p.id })
            }))
            .then(r => r.json())
            .then(data => {
                document.getElementById('pvLbSpinner').style.display = 'none';
                if (!data.success) throw new Error(data.error || 'Analysis failed');
                _pvAnnotatedUrl = data.annotated_url || null;
                // Cache result in PHOTOS array
                const ph = PHOTOS[lbCurrent];
                ph.area_cm2     = data.area_cm2;
                ph.length_cm    = data.length_cm;
                ph.width_cm     = data.width_cm;
                ph.ruler        = !!data.ruler_detected;
                ph.annotated_url = _pvAnnotatedUrl;
                if (data.tissue_info) {
                    ph.gran_pct   = data.tissue_info.granulation_pct;
                    ph.slough_pct = data.tissue_info.slough_pct;
                    ph.eschar_pct = data.tissue_info.eschar_pct;
                    ph.confidence = data.tissue_info.confidence;
                }
                pvLbPopulateResults(data);
                const btn = document.getElementById('pvLbAnalyzeBtn');
                btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Re-analyze';
                btn.style.display = '';
            })
            .catch(err => {
                document.getElementById('pvLbSpinner').style.display = 'none';
                document.getElementById('pvLbAnalyzeBtn').style.display = '';
                const errEl = document.getElementById('pvLbAnalyzeError');
                errEl.innerHTML = '<i class="bi bi-x-circle-fill" style="margin-right:.25rem;"></i>' +
                    (err.message || 'Analysis failed. Please try again.');
                errEl.style.display = '';
            });
    };

    function pvLbPopulateResults(data) {
        document.getElementById('pvLbMArea').textContent   =
            (data.area_cm2   != null) ? Number(data.area_cm2).toFixed(2)  : '—';
        document.getElementById('pvLbMLength').textContent =
            (data.length_cm  != null) ? Number(data.length_cm).toFixed(1) : '—';
        document.getElementById('pvLbMWidth').textContent  =
            (data.width_cm   != null) ? Number(data.width_cm).toFixed(1)  : '—';

        // Badges
        const badgeStyle = 'display:inline-flex;align-items:center;gap:.2rem;font-size:.6rem;font-weight:700;' +
                           'padding:.2rem .5rem;border-radius:9999px;border:1px solid;';
        const badges = document.getElementById('pvLbMBadges');
        const rulerOk = data.ruler_detected ?? data.ruler ?? false;
        badges.innerHTML = rulerOk
            ? `<span style="${badgeStyle}background:#f0fdf4;border-color:#bbf7d0;color:#15803d;"><i class="bi bi-check-circle-fill"></i> Ruler detected</span>`
            : `<span style="${badgeStyle}background:#fffbeb;border-color:#fde68a;color:#92400e;"><i class="bi bi-exclamation-triangle-fill"></i> No ruler</span>`;
        const method = data.method || '';
        if (method === 'gpt4o') {
            badges.innerHTML += `<span style="${badgeStyle}background:#f5f3ff;border-color:#ddd6fe;color:#6d28d9;"><i class="bi bi-stars"></i> GPT-4o</span>`;
        } else if (method === 'opencv') {
            badges.innerHTML += `<span style="${badgeStyle}background:#f8fafc;border-color:#e2e8f0;color:#475569;"><i class="bi bi-eye-fill"></i> OpenCV</span>`;
        } else if (method === 'manual') {
            badges.innerHTML += `<span style="${badgeStyle}background:#faf5ff;border-color:#e9d5ff;color:#7c3aed;"><i class="bi bi-pencil-fill"></i> Manual</span>`;
        }
        const ti = data.tissue_info;
        if (ti && (ti.confidence === 'high' || ti.confidence === 'medium')) {
            badges.innerHTML += `<span style="${badgeStyle}background:#f0f9ff;border-color:#bae6fd;color:#0369a1;"><i class="bi bi-shield-check"></i> ${ti.confidence} conf.</span>`;
        }

        // No ruler warning (suppress for manual entries)
        document.getElementById('pvLbNoRulerWarn').style.display = (rulerOk || method === 'manual') ? 'none' : '';

        // Tissue bar
        const tissueBlock = document.getElementById('pvLbTissueBlock');
        if (ti && ti.granulation_pct != null) {
            const g = ti.granulation_pct || 0, s = ti.slough_pct || 0, e = ti.eschar_pct || 0;
            document.getElementById('pvLbTissueBar').innerHTML =
                `<div style="width:${g}%;background:#f87171;transition:width .3s;"></div>` +
                `<div style="width:${s}%;background:#facc15;transition:width .3s;"></div>` +
                `<div style="width:${e}%;background:#475569;transition:width .3s;"></div>`;
            const lblStyle = 'display:inline-flex;align-items:center;gap:.25rem;font-size:.65rem;';
            const dot = (c) => `<span style="width:.5rem;height:.5rem;border-radius:9999px;background:${c};display:inline-block;"></span>`;
            document.getElementById('pvLbTissueLabels').innerHTML =
                `<span style="${lblStyle}">${dot('#f87171')}<b>${g}%</b> Gran.</span>` +
                `<span style="${lblStyle}">${dot('#facc15')}<b>${s}%</b> Slough</span>` +
                `<span style="${lblStyle}">${dot('#475569')}<b>${e}%</b> Eschar</span>`;
            tissueBlock.style.display = '';
        } else {
            tissueBlock.style.display = 'none';
        }

        // Annotated image toggle
        const annUrl = data.annotated_url || _pvAnnotatedUrl;
        if (annUrl) {
            _pvAnnotatedUrl = annUrl;
            document.getElementById('pvLbImgToggle').style.display = '';
            pvLbShowImg('annotated');
        }

        document.getElementById('pvLbResult').style.display = '';
    }
    document.addEventListener('keydown', function(e) {
        if (document.getElementById('pvLightbox').style.display === 'none') return;
        if (e.key === 'Escape')     closePhotoLightbox();
        if (e.key === 'ArrowLeft')  pvLbStep(-1);
        if (e.key === 'ArrowRight') pvLbStep(1);
        if (e.key === '+' || e.key === '=') pvLbZoom(0.25);
        if (e.key === '-')                  pvLbZoom(-0.25);
        if (e.key === '0')                  pvLbZoomReset();
    });

    // ── Photo Edit / Delete ──────────────────────────────────────────────────────────
    const PV_CSRF     = <?= json_encode($photoCsrf) ?>;
    const PV_BASE     = <?= json_encode(BASE_URL) ?>;

    window.pvEditPhoto = function(pid) {
        const p = photoMap[pid];
        if (!p) return;
        document.getElementById('pvEditPhotoId').value  = pid;
        document.getElementById('pvEditLocText').value  = (p.location === 'Unspecified') ? '' : (p.location || '');
        document.getElementById('pvEditNoteText').value = p.desc || '';
        document.getElementById('pvEditLength').value   = p.manual_length != null ? p.manual_length : '';
        document.getElementById('pvEditWidth').value    = p.manual_width  != null ? p.manual_width  : '';
        document.getElementById('pvEditDepth').value    = p.manual_depth  != null ? p.manual_depth  : '';
        document.querySelectorAll('.pv-edit-loc-btn').forEach(function(btn) {
            const active = btn.textContent.trim() === p.location;
            btn.style.borderColor = active ? '#7c3aed' : '#e2e8f0';
            btn.style.background  = active ? '#ede9fe' : '#f8fafc';
            btn.style.color       = active ? '#6d28d9' : '#475569';
        });
        document.querySelectorAll('.pv-edit-visit-btn').forEach(function(btn) {
            const active = btn.dataset.vtype === p.visit_type;
            btn.style.borderColor = active ? '#7c3aed' : '#e2e8f0';
            btn.style.background  = active ? '#ede9fe' : '#f8fafc';
            btn.style.color       = active ? '#6d28d9' : '#475569';
        });
        document.getElementById('pvEditPhotoErr').style.display = 'none';
        document.getElementById('pvEditPhotoModal').style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };
    window.pvEditPhotoLb  = function() { pvEditPhoto(PHOTOS[lbCurrent].id); };
    window.pvEditLocSelect = function(btn, loc) {
        document.querySelectorAll('.pv-edit-loc-btn').forEach(function(b) {
            b.style.borderColor = '#e2e8f0'; b.style.background = '#f8fafc'; b.style.color = '#475569';
        });
        btn.style.borderColor = '#7c3aed'; btn.style.background = '#ede9fe'; btn.style.color = '#6d28d9';
        document.getElementById('pvEditLocText').value = loc;
    };
    window.pvEditVisitSelect = function(btn, vtype) {
        // Toggle off if already active
        const isActive = btn.style.borderColor === 'rgb(124, 58, 237)';
        document.querySelectorAll('.pv-edit-visit-btn').forEach(function(b) {
            b.style.borderColor = '#e2e8f0'; b.style.background = '#f8fafc'; b.style.color = '#475569';
        });
        if (!isActive) {
            btn.style.borderColor = '#7c3aed'; btn.style.background = '#ede9fe'; btn.style.color = '#6d28d9';
        }
    };
    window.pvCloseEditPhoto = function() {
        document.getElementById('pvEditPhotoModal').style.display = 'none';
        document.body.style.overflow = '';
    };
    window.pvSaveEditPhoto = async function() {
        const pid      = parseInt(document.getElementById('pvEditPhotoId').value);
        const loc      = document.getElementById('pvEditLocText').value.trim();
        const note     = document.getElementById('pvEditNoteText').value.trim();
        const lenVal   = document.getElementById('pvEditLength').value.trim();
        const widVal   = document.getElementById('pvEditWidth').value.trim();
        const depVal   = document.getElementById('pvEditDepth').value.trim();
        const activeVBtn = document.querySelector('.pv-edit-visit-btn[style*="rgb(124, 58, 237)"]');
        const visitType  = activeVBtn ? activeVBtn.dataset.vtype : null;
        const errEl = document.getElementById('pvEditPhotoErr');
        errEl.style.display = 'none';
        try {
            const r = await fetch(PV_BASE + '/api/update_wound_photo.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    csrf: PV_CSRF, photo_id: pid,
                    wound_location: loc, description: note,
                    visit_type: visitType,
                    length_cm: lenVal !== '' ? parseFloat(lenVal) : '',
                    width_cm:  widVal !== '' ? parseFloat(widVal) : '',
                    depth_cm:  depVal !== '' ? parseFloat(depVal) : ''
                })
            });
            const d = await r.json();
            if (!d.ok) throw new Error(d.error || 'Update failed');
            photoMap[pid].location     = loc || 'Unspecified';
            photoMap[pid].desc         = note;
            photoMap[pid].visit_type   = visitType;
            photoMap[pid].manual_length = lenVal !== '' ? parseFloat(lenVal) : null;
            photoMap[pid].manual_width  = widVal !== '' ? parseFloat(widVal) : null;
            photoMap[pid].manual_depth  = depVal !== '' ? parseFloat(depVal) : null;
            // If server returned measurement data, update the manual section
            if (d.area_cm2 !== null && d.area_cm2 !== undefined) {
                photoMap[pid].man_area_cm2  = d.area_cm2;
                photoMap[pid].man_length_cm = d.length_cm;
                photoMap[pid].man_width_cm  = d.width_cm;
                photoMap[pid].man_by_name   = d.man_by_name || '';
                photoMap[pid].man_by_role   = d.man_by_role || '';
                photoMap[pid].man_date      = d.man_date    || '';
                const pidx = PHOTOS.findIndex(function(ph) { return ph.id === pid; });
                if (pidx !== -1) {
                    PHOTOS[pidx].man_area_cm2  = d.area_cm2;
                    PHOTOS[pidx].man_length_cm = d.length_cm;
                    PHOTOS[pidx].man_width_cm  = d.width_cm;
                    PHOTOS[pidx].man_by_name   = d.man_by_name || '';
                    PHOTOS[pidx].man_by_role   = d.man_by_role || '';
                    PHOTOS[pidx].man_date      = d.man_date    || '';
                }
            }
            // Update annotated image if server regenerated it
            if (d.annotated_url) {
                const annUrl = d.annotated_url + '?ts=' + Date.now();
                photoMap[pid].annotated_url = d.annotated_url;
                const pidx2 = PHOTOS.findIndex(function(ph) { return ph.id === pid; });
                if (pidx2 !== -1) PHOTOS[pidx2].annotated_url = d.annotated_url;
                // If lightbox is showing this photo, refresh annotated view
                if (document.getElementById('pvLightbox').style.display !== 'none' &&
                    PHOTOS[lbCurrent] && PHOTOS[lbCurrent].id === pid) {
                    _pvAnnotatedUrl = d.annotated_url;
                    document.getElementById('pvLbImg').src = annUrl;
                    document.getElementById('pvLbImgToggle').style.display = '';
                    pvLbShowImg('annotated');
                }
            }
            const card = document.querySelector('[data-photo-id="' + pid + '"]');
            if (card) {
                const ps = card.querySelectorAll('p');
                if (ps[0]) ps[0].textContent = loc || 'Unspecified';
            }
            pvCloseEditPhoto();
            if (document.getElementById('pvLightbox').style.display !== 'none' &&
                PHOTOS[lbCurrent] && PHOTOS[lbCurrent].id === pid) {
                renderPhotoLb();
            }
        } catch(err) {
            errEl.textContent = err.message;
            errEl.style.display = 'block';
        }
    };
    window.pvDeletePhoto = async function(pid) {
        if (!await pdConfirm({message: 'Delete this wound photo?', subtext: 'This cannot be undone.', confirmLabel: 'Delete', confirmIcon: 'bi bi-trash3', confirmStyle: 'background:#dc2626;'})) return;
        try {
            const r = await fetch(PV_BASE + '/api/update_wound_photo.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ csrf: PV_CSRF, photo_id: pid, action: 'delete' })
            });
            const d = await r.json();
            if (!d.ok) { pdToast(d.error || 'Delete failed', 'error'); return; }
            const idx = PHOTOS.findIndex(function(p) { return p.id === pid; });
            if (idx !== -1) PHOTOS.splice(idx, 1);
            delete photoMap[pid];
            const card = document.querySelector('[data-photo-id="' + pid + '"]');
            if (card) card.remove();
            if (document.getElementById('pvLightbox').style.display !== 'none') {
                if (PHOTOS.length === 0) { closePhotoLightbox(); }
                else { lbCurrent = Math.min(lbCurrent, PHOTOS.length - 1); renderPhotoLb(); }
            }
        } catch(e) { pdToast(e.message, 'error'); }
    };
    window.pvDeletePhotoLb = function() { pvDeletePhoto(PHOTOS[lbCurrent].id); };

    // ── Manual Canvas Annotation ─────────────────────────────────────────────
    let _pvAnnTool = 'pen', _pvAnnColor = '#ef4444', _pvAnnWidth = 3;
    let _pvAnnDrawing = false, _pvAnnStartX = 0, _pvAnnStartY = 0;
    let _pvAnnHistory = [], _pvAnnRectSnap = null, _pvAnnHandlers = {};

    function pvAnnDrawArrow(ctx, x1, y1, x2, y2) {
        const dx = x2 - x1, dy = y2 - y1;
        const len = Math.sqrt(dx*dx + dy*dy);
        if (len < 2) return;
        const headLen = Math.min(32, len * 0.38);
        const angle   = Math.atan2(dy, dx);
        ctx.beginPath();
        ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(x2, y2);
        ctx.lineTo(x2 - headLen * Math.cos(angle - Math.PI/6), y2 - headLen * Math.sin(angle - Math.PI/6));
        ctx.moveTo(x2, y2);
        ctx.lineTo(x2 - headLen * Math.cos(angle + Math.PI/6), y2 - headLen * Math.sin(angle + Math.PI/6));
        ctx.stroke();
    }

    window.pvAnnStart = function() {
        resetZoom();
        const wrap   = document.getElementById('pvLbImgWrap');
        const canvas = document.getElementById('pvAnnCanvas');
        canvas.width  = wrap.offsetWidth;
        canvas.height = wrap.offsetHeight;
        canvas.getContext('2d', { willReadFrequently: true }); // prime context with read hint
        canvas.style.display = 'block';
        document.getElementById('pvAnnotateBar').style.display = 'flex';
        document.getElementById('pvLbDrawBtn').style.display   = 'none';
        document.getElementById('pvLbImgToggle').style.display = 'none';
        _pvAnnHistory = [];
        pvAnnBindEvents(canvas);
    };
    window.pvAnnCancel = function() {
        const canvas = document.getElementById('pvAnnCanvas');
        canvas.style.display = 'none';
        document.getElementById('pvAnnotateBar').style.display = 'none';
        document.getElementById('pvLbDrawBtn').style.display   = '';
        _pvAnnHistory = []; _pvAnnRectSnap = null;
        pvAnnUnbindEvents(canvas);
        const p = PHOTOS[lbCurrent];
        if (p && (p.annotated_url || _pvAnnotatedUrl)) {
            document.getElementById('pvLbImgToggle').style.display = '';
        }
    };
    window.pvAnnSetTool = function(tool, btn) {
        _pvAnnTool = tool;
        document.querySelectorAll('#pvAnnotateBar [id^="pvAnnBtn"]').forEach(function(b) {
            b.style.background = 'transparent';
        });
        if (btn) btn.style.background = 'rgba(255,255,255,.25)';
    };
    window.pvAnnSetColor = function(color, btn) {
        _pvAnnColor = color;
        document.querySelectorAll('#pvAnnotateBar button[onclick^="pvAnnSetColor"]').forEach(function(b) {
            b.style.borderColor = 'transparent';
        });
        if (btn) btn.style.borderColor = '#fff';
    };
    window.pvAnnSetWidth = function(w, btn) {
        _pvAnnWidth = w;
        document.querySelectorAll('#pvAnnotateBar button[onclick^="pvAnnSetWidth"]').forEach(function(b) {
            b.style.borderColor = 'transparent';
        });
        if (btn) btn.style.borderColor = 'rgba(255,255,255,.7)';
    };
    window.pvAnnClear = function() {
        const canvas = document.getElementById('pvAnnCanvas');
        pvAnnSaveHist(canvas);
        canvas.getContext('2d').clearRect(0, 0, canvas.width, canvas.height);
    };
    window.pvAnnUndo = function() {
        if (!_pvAnnHistory.length) return;
        const canvas = document.getElementById('pvAnnCanvas');
        canvas.getContext('2d').putImageData(_pvAnnHistory.pop(), 0, 0);
    };
    function pvAnnSaveHist(canvas) {
        _pvAnnHistory.push(canvas.getContext('2d').getImageData(0, 0, canvas.width, canvas.height));
        if (_pvAnnHistory.length > 25) _pvAnnHistory.shift();
    }
    function pvAnnGetPos(canvas, e) {
        const r = canvas.getBoundingClientRect();
        const cx = e.touches ? e.touches[0].clientX : e.clientX;
        const cy = e.touches ? e.touches[0].clientY : e.clientY;
        return { x: (cx - r.left) * (canvas.width / r.width),
                 y: (cy - r.top)  * (canvas.height / r.height) };
    }
    function pvAnnBindEvents(canvas) {
        const ctx = canvas.getContext('2d');
        _pvAnnHandlers.down = function(e) {
            e.preventDefault(); e.stopPropagation();
            _pvAnnDrawing = true;
            const pos = pvAnnGetPos(canvas, e);
            _pvAnnStartX = pos.x; _pvAnnStartY = pos.y;
            if (_pvAnnTool !== 'eraser') pvAnnSaveHist(canvas);
            if (_pvAnnTool === 'pen') { ctx.beginPath(); ctx.moveTo(pos.x, pos.y); }
            else if (_pvAnnTool === 'rect' || _pvAnnTool === 'arrow') {
                _pvAnnRectSnap = ctx.getImageData(0, 0, canvas.width, canvas.height);
            }
        };
        _pvAnnHandlers.move = function(e) {
            if (!_pvAnnDrawing) return;
            e.preventDefault(); e.stopPropagation();
            const pos = pvAnnGetPos(canvas, e);
            ctx.strokeStyle = _pvAnnColor; ctx.lineWidth = _pvAnnWidth; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
            if (_pvAnnTool === 'pen') {
                ctx.lineTo(pos.x, pos.y); ctx.stroke();
            } else if (_pvAnnTool === 'rect') {
                ctx.putImageData(_pvAnnRectSnap, 0, 0);
                ctx.beginPath(); ctx.rect(_pvAnnStartX, _pvAnnStartY, pos.x - _pvAnnStartX, pos.y - _pvAnnStartY); ctx.stroke();
            } else if (_pvAnnTool === 'arrow') {
                ctx.putImageData(_pvAnnRectSnap, 0, 0);
                pvAnnDrawArrow(ctx, _pvAnnStartX, _pvAnnStartY, pos.x, pos.y);
            } else if (_pvAnnTool === 'eraser') {
                ctx.save();
                ctx.globalCompositeOperation = 'destination-out';
                ctx.beginPath();
                ctx.arc(pos.x, pos.y, _pvAnnWidth * 3, 0, Math.PI * 2);
                ctx.fillStyle = 'rgba(0,0,0,1)';
                ctx.fill();
                ctx.restore();
            }
        };
        _pvAnnHandlers.up = function(e) {
            if (!_pvAnnDrawing) return;
            _pvAnnDrawing = false;
            if (_pvAnnTool === 'pen') canvas.getContext('2d').closePath();
            else if (_pvAnnTool === 'arrow' && _pvAnnRectSnap) {
                // finalize arrow
                const pos = pvAnnGetPos(canvas, e);
                ctx.putImageData(_pvAnnRectSnap, 0, 0);
                ctx.strokeStyle = _pvAnnColor; ctx.lineWidth = _pvAnnWidth; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
                pvAnnDrawArrow(ctx, _pvAnnStartX, _pvAnnStartY, pos.x, pos.y);
            }
            _pvAnnRectSnap = null;
        };
        canvas.addEventListener('pointerdown',  _pvAnnHandlers.down);
        canvas.addEventListener('pointermove',  _pvAnnHandlers.move);
        canvas.addEventListener('pointerup',    _pvAnnHandlers.up);
        canvas.addEventListener('pointercancel',_pvAnnHandlers.up);
    }
    function pvAnnUnbindEvents(canvas) {
        if (_pvAnnHandlers.down)   canvas.removeEventListener('pointerdown',  _pvAnnHandlers.down);
        if (_pvAnnHandlers.move)   canvas.removeEventListener('pointermove',  _pvAnnHandlers.move);
        if (_pvAnnHandlers.up)   { canvas.removeEventListener('pointerup',    _pvAnnHandlers.up);
                                   canvas.removeEventListener('pointercancel',_pvAnnHandlers.up); }
        _pvAnnHandlers = {};
    }
    window.pvAnnSave = async function() {
        const canvas = document.getElementById('pvAnnCanvas');
        const imgEl  = document.getElementById('pvLbImg');
        const wrap   = document.getElementById('pvLbImgWrap');
        const p      = PHOTOS[lbCurrent];
        // Composite at natural resolution
        const off = document.createElement('canvas');
        off.width  = imgEl.naturalWidth;
        off.height = imgEl.naturalHeight;
        const ctx  = off.getContext('2d');
        ctx.drawImage(imgEl, 0, 0, off.width, off.height);
        // Map canvas coords → natural image coords
        const imgRect  = imgEl.getBoundingClientRect();
        const wrapRect = wrap.getBoundingClientRect();
        const scaleC   = canvas.width / wrapRect.width;
        const ox = (imgRect.left - wrapRect.left) * scaleC;
        const oy = (imgRect.top  - wrapRect.top)  * (canvas.height / wrapRect.height);
        const dw = imgRect.width  * scaleC;
        const dh = imgRect.height * (canvas.height / wrapRect.height);
        ctx.drawImage(canvas, ox, oy, dw, dh, 0, 0, off.width, off.height);
        const imageData = off.toDataURL('image/png');

        const saveBtn = document.getElementById('pvAnnSaveBtn');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="bi bi-hourglass-split"></i>';
        try {
            const r = await fetch(PV_BASE + '/api/save_annotation.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ csrf: PV_CSRF, photo_id: p.id, image_data: imageData })
            });
            const d = await r.json();
            if (!d.ok) throw new Error(d.error || 'Save failed');
            // Update photo data
            const annUrl = d.annotated_url + '?ts=' + Date.now();
            _pvAnnotatedUrl = d.annotated_url;
            p.annotated_url = d.annotated_url;
            photoMap[p.id].annotated_url = d.annotated_url;
            // Exit annotation mode, load annotated view
            pvAnnCancel();
            const lbImg = document.getElementById('pvLbImg');
            lbImg.src = annUrl;
            document.getElementById('pvLbImgToggle').style.display = '';
            pvLbShowImg('annotated');
        } catch(err) {
            pdToast('Save failed: ' + err.message, 'error');
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-check-lg"></i> Save';
        }
    };

})();
</script>

<!-- Photo Edit Modal -->
<div id="pvEditPhotoModal" style="display:none;position:fixed;inset:0;z-index:10100;
     align-items:center;justify-content:center;padding:1rem;"
     onclick="if(event.target===this)pvCloseEditPhoto()">
    <div style="position:absolute;inset:0;background:rgba(0,0,0,0.65);backdrop-filter:blur(4px);"></div>
    <div style="position:relative;background:#fff;border-radius:1.5rem;box-shadow:0 25px 60px rgba(0,0,0,.4);
                width:100%;max-width:420px;padding:1.5rem;z-index:10;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
            <h3 style="font-size:.9375rem;font-weight:700;color:#1e293b;margin:0;">
                <i class="bi bi-pencil-fill" style="color:#7c3aed;margin-right:.5rem;"></i>Edit Photo Details
            </h3>
            <button onclick="pvCloseEditPhoto()"
                    style="border:none;background:none;cursor:pointer;color:#94a3b8;width:2rem;height:2rem;
                           border-radius:.5rem;display:flex;align-items:center;justify-content:center;font-size:1rem;">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <input type="hidden" id="pvEditPhotoId">
        <div style="margin-bottom:1rem;">
            <label style="display:block;font-size:.7rem;font-weight:700;color:#64748b;text-transform:uppercase;
                          letter-spacing:.08em;margin-bottom:.5rem;">Wound Location</label>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.375rem;margin-bottom:.5rem;">
                <?php foreach (['Left Foot','Right Foot','Left Leg','Right Leg','Sacrum','Other'] as $_eloc): ?>
                <button type="button" class="pv-edit-loc-btn"
                        style="padding:.4375rem .25rem;border:1.5px solid #e2e8f0;border-radius:.625rem;
                               background:#f8fafc;color:#475569;font-size:.71875rem;cursor:pointer;transition:.1s;"
                        onclick="pvEditLocSelect(this,'<?= addslashes($_eloc) ?>')">
                    <?= htmlspecialchars($_eloc) ?>
                </button>
                <?php endforeach; ?>
            </div>
            <input id="pvEditLocText" type="text" placeholder="Or type location…"
                   style="width:100%;box-sizing:border-box;padding:.5625rem .75rem;border:1.5px solid #e2e8f0;
                          border-radius:.75rem;font-size:.875rem;outline:none;transition:border-color .15s;"
                   onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e2e8f0'">
        </div>
        <div style="margin-bottom:1rem;">
            <label style="display:block;font-size:.7rem;font-weight:700;color:#64748b;text-transform:uppercase;
                          letter-spacing:.08em;margin-bottom:.375rem;">Note <span style="font-weight:400;text-transform:none;">(optional)</span></label>
            <textarea id="pvEditNoteText" rows="3" placeholder="e.g. wound size 3×2 cm, improving…"
                      style="width:100%;box-sizing:border-box;padding:.5625rem .75rem;border:1.5px solid #e2e8f0;
                             border-radius:.75rem;font-size:.875rem;resize:none;outline:none;transition:border-color .15s;"
                      onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e2e8f0'"></textarea>
        </div>
        <!-- Visit type -->
        <div style="margin-bottom:1rem;">
            <label style="display:block;font-size:.7rem;font-weight:700;color:#64748b;text-transform:uppercase;
                          letter-spacing:.08em;margin-bottom:.5rem;">Photo Type</label>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.375rem;">
                <?php foreach (['pre_debridement' => 'Pre-Debridement', 'post_debridement' => 'Post-Debridement', 'post_graft' => 'Post-Graft'] as $_vkey => $_vlabel): ?>
                <button type="button" class="pv-edit-visit-btn" data-vtype="<?= $_vkey ?>"
                        style="padding:.4375rem .25rem;border:1.5px solid #e2e8f0;border-radius:.625rem;
                               background:#f8fafc;color:#475569;font-size:.6875rem;font-weight:600;cursor:pointer;transition:.1s;"
                        onclick="pvEditVisitSelect(this,'<?= $_vkey ?>')">
                    <?= htmlspecialchars($_vlabel) ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        <!-- Measurements L × W × D -->
        <div style="margin-bottom:1.25rem;">
            <label style="display:block;font-size:.7rem;font-weight:700;color:#64748b;text-transform:uppercase;
                          letter-spacing:.08em;margin-bottom:.5rem;">Measurements <span style="font-weight:400;text-transform:none;">(cm, optional)</span></label>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:.5rem;">
                <div>
                    <label style="font-size:.625rem;color:#94a3b8;font-weight:600;display:block;margin-bottom:.25rem;">Length</label>
                    <input id="pvEditLength" type="number" step="0.1" min="0" max="99" placeholder="0.0"
                           style="width:100%;box-sizing:border-box;padding:.5rem .625rem;border:1.5px solid #e2e8f0;
                                  border-radius:.625rem;font-size:.875rem;outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e2e8f0'">
                </div>
                <div>
                    <label style="font-size:.625rem;color:#94a3b8;font-weight:600;display:block;margin-bottom:.25rem;">Width</label>
                    <input id="pvEditWidth" type="number" step="0.1" min="0" max="99" placeholder="0.0"
                           style="width:100%;box-sizing:border-box;padding:.5rem .625rem;border:1.5px solid #e2e8f0;
                                  border-radius:.625rem;font-size:.875rem;outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e2e8f0'">
                </div>
                <div>
                    <label style="font-size:.625rem;color:#94a3b8;font-weight:600;display:block;margin-bottom:.25rem;">Depth</label>
                    <input id="pvEditDepth" type="number" step="0.1" min="0" max="99" placeholder="0.0"
                           style="width:100%;box-sizing:border-box;padding:.5rem .625rem;border:1.5px solid #e2e8f0;
                                  border-radius:.625rem;font-size:.875rem;outline:none;transition:border-color .15s;"
                           onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e2e8f0'">
                </div>
            </div>
        </div>
        <div style="display:flex;gap:.75rem;">
            <button onclick="pvSaveEditPhoto()"
                    style="flex:1;padding:.75rem;background:#7c3aed;color:#fff;border:none;border-radius:.75rem;
                           font-size:.875rem;font-weight:700;cursor:pointer;display:flex;align-items:center;
                           justify-content:center;gap:.375rem;"
                    onmouseover="this.style.background='#6d28d9'" onmouseout="this.style.background='#7c3aed'">
                <i class="bi bi-floppy-fill"></i> Save Changes
            </button>
            <button onclick="pvCloseEditPhoto()"
                    style="padding:.75rem 1.25rem;background:#f1f5f9;color:#64748b;border:none;border-radius:.75rem;
                           font-size:.875rem;font-weight:600;cursor:pointer;"
                    onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                Cancel
            </button>
        </div>
        <p id="pvEditPhotoErr" style="display:none;color:#dc2626;font-size:.75rem;margin-top:.625rem;text-align:center;"></p>
    </div>
</div>

<!-- Patient-view wound photo lightbox -->
<div id="pvLightbox" style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;padding:1rem;"
     onclick="if(event.target===this)closePhotoLightbox()">
    <div style="position:absolute;inset:0;background:rgba(0,0,0,0.82);backdrop-filter:blur(4px);"></div>
    <div id="pvLbDialog" style="position:relative;background:#fff;border-radius:1.5rem;box-shadow:0 25px 60px rgba(0,0,0,.4);
                width:100%;max-width:960px;max-height:90vh;display:flex;flex-direction:column;overflow:hidden;z-index:10;">
        <!-- Header -->
        <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.5rem;border-bottom:1px solid #f1f5f9;flex-shrink:0;">
            <div style="display:flex;align-items:center;gap:.75rem;">
                <div style="width:2.25rem;height:2.25rem;background:#ede9fe;border-radius:.75rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-camera-fill" style="color:#7c3aed;font-size:1rem;"></i>
                </div>
                <div>
                    <p id="pvLbTitle" style="font-size:.875rem;font-weight:700;color:#1e293b;margin:0;"></p>
                    <p id="pvLbDate"  style="font-size:.75rem;font-weight:600;color:#7c3aed;margin:0;"></p>
                </div>
            </div>
            <div style="display:flex;align-items:center;gap:.5rem;">
                <span id="pvLbCount" style="font-size:.75rem;color:#94a3b8;font-weight:500;"></span>
                <button id="pvLbSidebarToggle" onclick="pvLbToggleSidebar()" title="Toggle wound details"
                        style="width:2.25rem;height:2.25rem;border:none;background:transparent;border-radius:.75rem;
                               cursor:pointer;display:flex;align-items:center;justify-content:center;"
                        onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">
                    <i class="bi bi-layout-sidebar-reverse" style="font-size:1rem;"></i>
                </button>
                <button onclick="closePhotoLightbox()"
                        style="width:2.25rem;height:2.25rem;border:none;background:transparent;border-radius:.75rem;
                               cursor:pointer;display:flex;align-items:center;justify-content:center;color:#94a3b8;"
                        onmouseover="this.style.background='#f1f5f9'" onmouseout="this.style.background='transparent'">
                    <i class="bi bi-x-lg" style="font-size:1rem;"></i>
                </button>
            </div>
        </div>
        <!-- Body: image left, sidebar right -->
        <div class="pvlb-body">
            <!-- Image panel -->
            <div id="pvLbImgWrap" class="pvlb-imgwrap"
                 style="background:#0f172a;position:relative;overflow:hidden;
                        display:flex;align-items:center;justify-content:center;
                        cursor:grab;user-select:none;min-height:320px;touch-action:none;">
                <img id="pvLbImg" src="" alt="Wound photo"
                     style="max-width:100%;max-height:100%;object-fit:contain;border-radius:.75rem;
                            pointer-events:none;transform-origin:center center;
                            transition:transform .15s ease;will-change:transform;">
                <!-- Original/Annotated toggle -->
                <div id="pvLbImgToggle" class="pvlb-overlay" style="display:none;position:absolute;top:.625rem;left:.625rem;
                     background:rgba(0,0,0,.6);border-radius:.75rem;padding:.25rem;z-index:10;"
                     onpointerdown="event.stopPropagation()" onclick="event.stopPropagation()">
                    <button id="pvLbBtnOrig" onclick="pvLbShowImg('orig')"
                            style="padding:.25rem .625rem;border:none;background:transparent;cursor:pointer;
                                   border-radius:.5rem;font-size:.7rem;font-weight:600;color:rgba(255,255,255,.5);transition:.15s;">
                        Original
                    </button>
                    <button id="pvLbBtnAnnotated" onclick="pvLbShowImg('annotated')"
                            style="padding:.25rem .625rem;border:none;background:transparent;cursor:pointer;
                                   border-radius:.5rem;font-size:.7rem;font-weight:600;color:rgba(255,255,255,.5);transition:.15s;">
                        <i class="bi bi-rulers" style="margin-right:.2rem;"></i>Annotated
                    </button>
                </div>
                <!-- Zoom controls -->
                <div class="pvlb-overlay" style="position:absolute;bottom:.75rem;left:50%;transform:translateX(-50%);
                            display:flex;align-items:center;gap:.25rem;
                            background:rgba(0,0,0,.6);border-radius:9999px;padding:.25rem .5rem;z-index:10;">
                    <button onclick="pvLbZoom(-0.25)" title="Zoom out"
                            style="width:1.75rem;height:1.75rem;border:none;background:transparent;cursor:pointer;
                                   color:#fff;border-radius:9999px;display:flex;align-items:center;justify-content:center;font-size:1rem;">
                        <i class="bi bi-dash-lg"></i>
                    </button>
                    <button onclick="pvLbZoomReset()" id="pvLbZoomLabel"
                            style="min-width:2.5rem;border:none;background:transparent;cursor:pointer;
                                   color:#fff;font-size:.7rem;font-weight:700;text-align:center;">100%</button>
                    <button onclick="pvLbZoom(0.25)" title="Zoom in"
                            style="width:1.75rem;height:1.75rem;border:none;background:transparent;cursor:pointer;
                                   color:#fff;border-radius:9999px;display:flex;align-items:center;justify-content:center;font-size:1rem;">
                        <i class="bi bi-plus-lg"></i>
                    </button>
                </div>
                <!-- Annotation toolbar (visible in draw mode) -->
                <div id="pvAnnotateBar">
                    <button id="pvAnnBtnPen"   onclick="pvAnnSetTool('pen',this)"    title="Freehand pen"  style="background:rgba(255,255,255,.25);"><i class="bi bi-pencil-fill"></i></button>
                    <button id="pvAnnBtnArrow" onclick="pvAnnSetTool('arrow',this)"  title="Arrow"><i class="bi bi-arrow-up-right"></i></button>
                    <button id="pvAnnBtnRect"  onclick="pvAnnSetTool('rect',this)"   title="Rectangle"><i class="bi bi-square"></i></button>
                    <button id="pvAnnBtnErase" onclick="pvAnnSetTool('eraser',this)" title="Eraser"><i class="bi bi-eraser"></i></button>
                    <span class="pv-ann-sep"></span>
                    <button onclick="pvAnnSetColor('#000000',this)" title="Black"
                            style="width:.9rem;height:.9rem;border-radius:9999px;border:2px solid transparent;
                                   background:#000;padding:0;"></button>
                    <button onclick="pvAnnSetColor('#ef4444',this)" title="Red"
                            style="width:.9rem;height:.9rem;border-radius:9999px;border:2px solid #fff;
                                   background:#ef4444;padding:0;"></button>
                    <button onclick="pvAnnSetColor('#22c55e',this)" title="Green"
                            style="width:.9rem;height:.9rem;border-radius:9999px;border:2px solid transparent;
                                   background:#22c55e;padding:0;"></button>
                    <button onclick="pvAnnSetColor('#eab308',this)" title="Yellow"
                            style="width:.9rem;height:.9rem;border-radius:9999px;border:2px solid transparent;
                                   background:#eab308;padding:0;"></button>
                    <button onclick="pvAnnSetColor('#fff',this)" title="White"
                            style="width:.9rem;height:.9rem;border-radius:9999px;border:2px solid rgba(255,255,255,.35);
                                   background:#fff;padding:0;"></button>
                    <span class="pv-ann-sep"></span>
                    <button onclick="pvAnnSetWidth(3,this)" title="Thin"
                            style="border:2px solid rgba(255,255,255,.5);">
                        <div style="width:.65rem;height:2px;background:#fff;border-radius:1px;"></div></button>
                    <button onclick="pvAnnSetWidth(6,this)" title="Medium"
                            style="border:2px solid transparent;">
                        <div style="width:.65rem;height:4px;background:#fff;border-radius:2px;"></div></button>
                    <button onclick="pvAnnSetWidth(10,this)" title="Thick"
                            style="border:2px solid transparent;">
                        <div style="width:.65rem;height:7px;background:#fff;border-radius:3px;"></div></button>
                    <span class="pv-ann-sep"></span>
                    <button onclick="pvAnnUndo()"  title="Undo"  style="color:#fbbf24;"><i class="bi bi-arrow-counterclockwise"></i></button>
                    <button onclick="pvAnnClear()" title="Clear all" style="color:#f87171;"><i class="bi bi-trash3"></i></button>
                    <span class="pv-ann-sep"></span>
                    <button id="pvAnnSaveBtn" onclick="pvAnnSave()"
                            style="padding:.2rem .5rem;border-radius:.5rem;background:#4ade80;color:#14532d;font-size:.65rem;font-weight:700;width:auto;">
                        <i class="bi bi-check-lg"></i> Save
                    </button>
                    <button onclick="pvAnnCancel()" title="Cancel" style="color:#f87171;"><i class="bi bi-x-lg"></i></button>
                </div>
                <!-- Annotation canvas -->
                <canvas id="pvAnnCanvas" style="display:none;position:absolute;inset:0;z-index:15;cursor:crosshair;touch-action:none;"></canvas>
                <!-- Draw trigger button (top-right overlay) -->
                <div class="pvlb-overlay" style="position:absolute;top:.625rem;right:.625rem;z-index:12;"
                     onpointerdown="event.stopPropagation()">
                    <button id="pvLbDrawBtn" onclick="pvAnnStart()" title="Draw / annotate photo"
                            style="display:flex;align-items:center;gap:.3rem;padding:.25rem .55rem;
                                   background:rgba(0,0,0,.6);color:#fff;border:none;border-radius:.625rem;
                                   font-size:.68rem;font-weight:700;cursor:pointer;white-space:nowrap;"
                            onmouseover="this.style.background='rgba(0,0,0,.85)'" onmouseout="this.style.background='rgba(0,0,0,.6)'">
                        <i class="bi bi-pencil-fill"></i> Draw
                    </button>
                </div>
                <!-- Prev / Next (inside imgwrap so they stay over the image on mobile) -->
                <div id="pvLbNav" class="pvlb-overlay" style="position:absolute;top:50%;transform:translateY(-50%);width:100%;
                                  display:flex;justify-content:space-between;padding:0 .75rem;pointer-events:none;z-index:9;">
                    <button onclick="pvLbStep(-1)" onpointerdown="event.stopPropagation()"
                            style="pointer-events:auto;width:2.5rem;height:2.5rem;border:none;cursor:pointer;
                                   border-radius:9999px;background:rgba(0,0,0,.5);color:#fff;
                                   display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,.3);">
                        <i class="bi bi-chevron-left" style="font-size:.875rem;"></i>
                    </button>
                    <button onclick="pvLbStep(1)" onpointerdown="event.stopPropagation()"
                            style="pointer-events:auto;width:2.5rem;height:2.5rem;border:none;cursor:pointer;
                                   border-radius:9999px;background:rgba(0,0,0,.5);color:#fff;
                                   display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,.3);">
                        <i class="bi bi-chevron-right" style="font-size:.875rem;"></i>
                    </button>
                </div>
            </div>
            <!-- Sidebar -->
            <div class="pvlb-sidebar" style="width:240px;flex-shrink:0;padding:1.25rem;
                        border-left:1px solid #f1f5f9;display:flex;flex-direction:column;gap:1rem;">
                <div>
                    <div style="font-size:.625rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.25rem;">Wound Site</div>
                    <p id="pvLbTitleMeta" style="font-size:.875rem;font-weight:600;color:#334155;margin:0;"></p>
                </div>
                <div>
                    <div style="font-size:.625rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.25rem;">Date</div>
                    <p id="pvLbDateMeta" style="font-size:.875rem;color:#334155;margin:0;"></p>
                </div>
                <div>
                    <div style="font-size:.625rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.25rem;">Recorded by</div>
                    <p id="pvLbMa" style="font-size:.875rem;color:#334155;margin:0;"></p>
                </div>
                <div id="pvLbDescBlock" style="display:none;">
                    <div style="font-size:.625rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.1em;margin-bottom:.25rem;">Notes</div>
                    <p id="pvLbDesc" style="font-size:.875rem;color:#64748b;font-style:italic;line-height:1.5;margin:0;"></p>
                </div>
                <a id="pvLbDownload" href="#" download target="_blank"
                   style="display:flex;align-items:center;justify-content:center;gap:.5rem;
                          padding:.625rem 1rem;background:#1e293b;color:#fff;text-decoration:none;
                          border-radius:.75rem;font-size:.875rem;font-weight:600;margin-top:auto;">
                    <i class="bi bi-download"></i> Download
                </a>

                <!-- ── Analysis Panel ───────────────────────────────── -->
                <div style="border-top:1px solid #f1f5f9;padding-top:.875rem;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.625rem;">
                        <div style="font-size:.6rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.1em;">
                            <i class="bi bi-rulers" style="color:#a78bfa;margin-right:.25rem;"></i>Wound Analysis
                        </div>
                        <button id="pvLbAnalyzeBtn" onclick="pvLbAnalyze()"
                                style="display:inline-flex;align-items:center;gap:.35rem;padding:.3rem .625rem;
                                       background:#7c3aed;color:#fff;border:none;border-radius:.625rem;
                                       font-size:.7rem;font-weight:700;cursor:pointer;transition:.15s;white-space:nowrap;"
                                onmouseover="this.style.background='#6d28d9'" onmouseout="this.style.background='#7c3aed'">
                            <i class="bi bi-cpu-fill"></i> Analyze
                        </button>
                    </div>

                    <!-- Spinner -->
                    <div id="pvLbSpinner" style="display:none;align-items:center;gap:.625rem;
                         background:#f5f3ff;border:1px solid #ede9fe;border-radius:.75rem;
                         padding:.625rem .875rem;margin-bottom:.5rem;">
                        <i class="bi bi-arrow-repeat" style="color:#7c3aed;font-size:1.1rem;animation:pvSpin 1s linear infinite;"></i>
                        <div>
                            <p style="font-size:.75rem;font-weight:700;color:#6d28d9;margin:0;">Analyzing…</p>
                            <p style="font-size:.65rem;color:#a78bfa;margin:0;">AI measuring wound bed</p>
                        </div>
                    </div>

                    <!-- Error -->
                    <div id="pvLbAnalyzeError" style="display:none;background:#fef2f2;border:1px solid #fecaca;
                         border-radius:.75rem;padding:.5rem .75rem;font-size:.75rem;color:#dc2626;margin-bottom:.5rem;"></div>

                    <!-- Results -->
                    <div id="pvLbResult" style="display:none;">
                        <!-- Section label -->
                        <p style="font-size:.6rem;font-weight:700;color:#94a3b8;text-transform:uppercase;
                                  letter-spacing:.1em;margin:0 0 .375rem;">
                            <i class="bi bi-cpu-fill" style="margin-right:.2rem;"></i>AI Analysis
                        </p>
                        <!-- Measurements grid -->
                        <div style="display:grid;grid-template-columns:repeat(3,1fr);border:1px solid #ddd6fe;
                                    border-radius:.75rem;overflow:hidden;margin-bottom:.5rem;">
                            <div style="padding:.5rem .25rem;text-align:center;background:#f5f3ff;">
                                <p style="font-size:.55rem;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:.08em;margin:0 0 .15rem;">Area</p>
                                <p id="pvLbMArea" style="font-size:1rem;font-weight:900;color:#5b21b6;margin:0;line-height:1;">—</p>
                                <p style="font-size:.55rem;color:#a78bfa;margin:.15rem 0 0;">cm²</p>
                            </div>
                            <div style="padding:.5rem .25rem;text-align:center;background:#f5f3ff;border-left:1px solid #ddd6fe;border-right:1px solid #ddd6fe;">
                                <p style="font-size:.55rem;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:.08em;margin:0 0 .15rem;">Length</p>
                                <p id="pvLbMLength" style="font-size:1rem;font-weight:900;color:#5b21b6;margin:0;line-height:1;">—</p>
                                <p style="font-size:.55rem;color:#a78bfa;margin:.15rem 0 0;">cm</p>
                            </div>
                            <div style="padding:.5rem .25rem;text-align:center;background:#f5f3ff;">
                                <p style="font-size:.55rem;font-weight:700;color:#7c3aed;text-transform:uppercase;letter-spacing:.08em;margin:0 0 .15rem;">Width</p>
                                <p id="pvLbMWidth" style="font-size:1rem;font-weight:900;color:#5b21b6;margin:0;line-height:1;">—</p>
                                <p style="font-size:.55rem;color:#a78bfa;margin:.15rem 0 0;">cm</p>
                            </div>
                        </div>
                        <!-- Badges -->
                        <div id="pvLbMBadges" style="display:flex;flex-wrap:wrap;gap:.375rem;margin-bottom:.5rem;"></div>
                        <!-- No ruler warning -->
                        <div id="pvLbNoRulerWarn" style="display:none;font-size:.7rem;color:#92400e;
                             background:#fffbeb;border:1px solid #fde68a;border-radius:.625rem;
                             padding:.375rem .625rem;margin-bottom:.5rem;">
                            <i class="bi bi-exclamation-triangle-fill" style="margin-right:.25rem;"></i>No ruler — estimates may vary. Place a ruler next to the wound.
                        </div>
                        <!-- Tissue composition -->
                        <div id="pvLbTissueBlock" style="display:none;">
                            <p style="font-size:.6rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.1em;margin:0 0 .375rem;">Tissue Composition</p>
                            <div id="pvLbTissueBar" style="display:flex;border-radius:9999px;overflow:hidden;height:.875rem;margin-bottom:.375rem;"></div>
                            <div id="pvLbTissueLabels" style="display:flex;flex-wrap:wrap;gap:.375rem .75rem;"></div>
                        </div>
                    </div>

                    <!-- Clinical Measurements (manual entry) -->
                    <div id="pvLbManualResult" style="display:none;margin-top:.5rem;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.375rem;gap:.5rem;flex-wrap:wrap;">
                            <p style="font-size:.6rem;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.1em;margin:0;">
                                <i class="bi bi-pencil-fill" style="margin-right:.2rem;"></i>Clinical Measurements
                            </p>
                            <span id="pvLbManAttrib" style="font-size:.6rem;color:#64748b;font-weight:500;"></span>
                        </div>
                        <div style="display:grid;grid-template-columns:repeat(3,1fr);border:1px solid #e2e8f0;
                                    border-radius:.75rem;overflow:hidden;">
                            <div style="padding:.5rem .25rem;text-align:center;background:#f8fafc;">
                                <p style="font-size:.55rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.08em;margin:0 0 .15rem;">Area</p>
                                <p id="pvLbMMArea" style="font-size:1rem;font-weight:900;color:#0f172a;margin:0;line-height:1;">—</p>
                                <p style="font-size:.55rem;color:#94a3b8;margin:.15rem 0 0;">cm²</p>
                            </div>
                            <div style="padding:.5rem .25rem;text-align:center;background:#f8fafc;border-left:1px solid #e2e8f0;border-right:1px solid #e2e8f0;">
                                <p style="font-size:.55rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.08em;margin:0 0 .15rem;">Length</p>
                                <p id="pvLbMMLength" style="font-size:1rem;font-weight:900;color:#0f172a;margin:0;line-height:1;">—</p>
                                <p style="font-size:.55rem;color:#94a3b8;margin:.15rem 0 0;">cm</p>
                            </div>
                            <div style="padding:.5rem .25rem;text-align:center;background:#f8fafc;">
                                <p style="font-size:.55rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.08em;margin:0 0 .15rem;">Width</p>
                                <p id="pvLbMMWidth" style="font-size:1rem;font-weight:900;color:#0f172a;margin:0;line-height:1;">—</p>
                                <p style="font-size:.55rem;color:#94a3b8;margin:.15rem 0 0;">cm</p>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- ── /Analysis Panel ──────────────────────────────── -->
                <?php if (!isBilling()): ?>
                <div style="display:flex;gap:.5rem;margin-top:.5rem;">
                    <button id="pvLbEditBtn" onclick="pvEditPhotoLb()"
                            style="flex:1;display:flex;align-items:center;justify-content:center;gap:.375rem;
                                   padding:.5rem .75rem;background:#ede9fe;color:#6d28d9;border:none;
                                   border-radius:.75rem;font-size:.8rem;font-weight:600;cursor:pointer;"
                            onmouseover="this.style.background='#ddd6fe'" onmouseout="this.style.background='#ede9fe'">
                        <i class="bi bi-pencil-fill"></i> Edit
                    </button>
                    <button id="pvLbDeleteBtn" onclick="pvDeletePhotoLb()"
                            style="flex:1;display:flex;align-items:center;justify-content:center;gap:.375rem;
                                   padding:.5rem .75rem;background:#fef2f2;color:#dc2626;border:none;
                                   border-radius:.75rem;font-size:.8rem;font-weight:600;cursor:pointer;"
                            onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'">
                        <i class="bi bi-trash3"></i> Delete
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
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
                <input type="text" name="wound_type" list="wound-types-list"
                       placeholder="Wound type (e.g. Diabetic ulcer)"
                       class="flex-1 px-3 py-2.5 text-sm border border-slate-200 rounded-xl bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-rose-400 focus:bg-white transition">
                <datalist id="wound-types-list">
                    <option value="Diabetic ulcer"><option value="Venous ulcer">
                    <option value="Arterial ulcer"><option value="Pressure injury">
                    <option value="Surgical wound"><option value="Traumatic wound">
                    <option value="Burn"><option value="Mixed etiology">
                </datalist>
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
                        <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide hidden sm:table-cell">Type</th>
                        <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide text-center">L (cm)</th>
                        <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide text-center">W (cm)</th>
                        <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide text-center">D (cm)</th>
                        <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide text-center">Area cm²</th>
                        <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide hidden md:table-cell">Notes</th>
                        <th class="px-4 py-3 text-xs font-bold text-slate-500 uppercase tracking-wide hidden lg:table-cell">Recorded By</th>
                        <?php if (!isBilling()): ?><th class="px-4 py-3 w-20"></th><?php endif; ?>
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
                        data-type="<?= h($wm['wound_type'] ?? '') ?>"
                        data-area="<?= $area ?>">
                        <td class="px-5 py-3.5 font-medium text-slate-700 whitespace-nowrap">
                            <?= date('M j, Y', strtotime($wm['measured_at'])) ?>
                        </td>
                        <td class="px-4 py-3.5 text-slate-600 max-w-[160px] truncate">
                            <?= h($wm['wound_site']) ?>
                        </td>
                        <td class="px-4 py-3.5 text-slate-500 text-xs hidden sm:table-cell max-w-[120px] truncate">
                            <?= h($wm['wound_type'] ?? '') ?: '—' ?>
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
                        <?php if (!isBilling()): ?>
                        <td class="px-4 py-3.5">
                            <div style="display:flex;gap:.25rem;align-items:center;">
                                <button class="edit-wound-btn"
                                        data-id="<?= $wm['id'] ?>"
                                        data-date="<?= h($wm['measured_at']) ?>"
                                        data-site="<?= h($wm['wound_site']) ?>"
                                        data-type="<?= h($wm['wound_type'] ?? '') ?>"
                                        data-len="<?= (float)$wm['length_cm'] ?>"
                                        data-wid="<?= (float)$wm['width_cm'] ?>"
                                        data-dep="<?= (float)$wm['depth_cm'] ?>"
                                        data-notes="<?= h($wm['notes'] ?? '') ?>"
                                        title="Edit measurement"
                                        style="background:none;border:none;cursor:pointer;color:#94a3b8;padding:4px 6px;border-radius:8px;"
                                        onmouseover="this.style.color='#7c3aed';this.style.background='#ede9fe'"
                                        onmouseout="this.style.color='#94a3b8';this.style.background='none'">
                                    <i class="bi bi-pencil text-sm"></i>
                                </button>
                                <?php if (isAdmin()): ?>
                                <button class="del-wound-btn" data-id="<?= $wm['id'] ?>"
                                        title="Delete measurement"
                                        style="background:none;border:none;cursor:pointer;color:#94a3b8;padding:4px 6px;border-radius:8px;"
                                        onmouseover="this.style.color='#ef4444';this.style.background='#fef2f2'"
                                        onmouseout="this.style.color='#94a3b8';this.style.background='none'">
                                    <i class="bi bi-trash3 text-sm"></i>
                                </button>
                                <?php endif; ?>
                            </div>
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

<!-- Wound Measurement Edit Modal -->
<div id="woundEditModal" style="display:none;position:fixed;inset:0;z-index:10100;
     align-items:center;justify-content:center;padding:1rem;"
     onclick="if(event.target===this)closeWoundEditModal()">
    <div style="position:absolute;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);"></div>
    <div style="position:relative;background:#fff;border-radius:1.5rem;box-shadow:0 25px 60px rgba(0,0,0,.4);
                width:100%;max-width:540px;padding:1.5rem;z-index:10;">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem;">
            <h3 style="font-size:.9375rem;font-weight:700;color:#1e293b;margin:0;">
                <i class="bi bi-pencil-fill" style="color:#e11d48;margin-right:.5rem;"></i>Edit Measurement
            </h3>
            <button onclick="closeWoundEditModal()"
                    style="border:none;background:none;cursor:pointer;color:#94a3b8;width:2rem;height:2rem;
                           border-radius:.5rem;display:flex;align-items:center;justify-content:center;font-size:1rem;">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <input type="hidden" id="woundEditId">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:.75rem;">
            <div>
                <label style="display:block;font-size:.7rem;font-weight:700;color:#64748b;margin-bottom:.25rem;">Date *</label>
                <input id="woundEditDate" type="date"
                       style="width:100%;box-sizing:border-box;padding:.5rem .75rem;border:1.5px solid #e2e8f0;
                              border-radius:.75rem;font-size:.875rem;outline:none;"
                       onfocus="this.style.borderColor='#e11d48'" onblur="this.style.borderColor='#e2e8f0'">
            </div>
            <div>
                <label style="display:block;font-size:.7rem;font-weight:700;color:#64748b;margin-bottom:.25rem;">Wound Site *</label>
                <input id="woundEditSite" type="text"
                       style="width:100%;box-sizing:border-box;padding:.5rem .75rem;border:1.5px solid #e2e8f0;
                              border-radius:.75rem;font-size:.875rem;outline:none;"
                       onfocus="this.style.borderColor='#e11d48'" onblur="this.style.borderColor='#e2e8f0'">
            </div>
            <div>
                <label style="display:block;font-size:.7rem;font-weight:700;color:#64748b;margin-bottom:.25rem;">Wound Type</label>
                <input id="woundEditType" type="text" list="wound-types-list" placeholder="e.g. Diabetic ulcer"
                       style="width:100%;box-sizing:border-box;padding:.5rem .75rem;border:1.5px solid #e2e8f0;
                              border-radius:.75rem;font-size:.875rem;outline:none;"
                       onfocus="this.style.borderColor='#e11d48'" onblur="this.style.borderColor='#e2e8f0'">
            </div>
            <div>
                <label style="display:block;font-size:.7rem;font-weight:700;color:#64748b;margin-bottom:.25rem;">Notes</label>
                <input id="woundEditNotes" type="text" placeholder="Optional notes"
                       style="width:100%;box-sizing:border-box;padding:.5rem .75rem;border:1.5px solid #e2e8f0;
                              border-radius:.75rem;font-size:.875rem;outline:none;"
                       onfocus="this.style.borderColor='#e11d48'" onblur="this.style.borderColor='#e2e8f0'">
            </div>
            <div>
                <label style="display:block;font-size:.7rem;font-weight:700;color:#64748b;margin-bottom:.25rem;">Length (cm) *</label>
                <input id="woundEditLen" type="number" min="0.1" step="0.1"
                       style="width:100%;box-sizing:border-box;padding:.5rem .75rem;border:1.5px solid #e2e8f0;
                              border-radius:.75rem;font-size:.875rem;outline:none;"
                       onfocus="this.style.borderColor='#e11d48'" onblur="this.style.borderColor='#e2e8f0'">
            </div>
            <div>
                <label style="display:block;font-size:.7rem;font-weight:700;color:#64748b;margin-bottom:.25rem;">Width (cm) *</label>
                <input id="woundEditWid" type="number" min="0.1" step="0.1"
                       style="width:100%;box-sizing:border-box;padding:.5rem .75rem;border:1.5px solid #e2e8f0;
                              border-radius:.75rem;font-size:.875rem;outline:none;"
                       onfocus="this.style.borderColor='#e11d48'" onblur="this.style.borderColor='#e2e8f0'">
            </div>
            <div>
                <label style="display:block;font-size:.7rem;font-weight:700;color:#64748b;margin-bottom:.25rem;">Depth (cm)</label>
                <input id="woundEditDep" type="number" min="0" step="0.1"
                       style="width:100%;box-sizing:border-box;padding:.5rem .75rem;border:1.5px solid #e2e8f0;
                              border-radius:.75rem;font-size:.875rem;outline:none;"
                       onfocus="this.style.borderColor='#e11d48'" onblur="this.style.borderColor='#e2e8f0'">
            </div>
        </div>
        <div style="display:flex;gap:.75rem;">
            <button onclick="submitWoundEdit()"
                    style="flex:1;padding:.75rem;background:#e11d48;color:#fff;border:none;border-radius:.75rem;
                           font-size:.875rem;font-weight:700;cursor:pointer;display:flex;align-items:center;
                           justify-content:center;gap:.375rem;"
                    onmouseover="this.style.background='#be123c'" onmouseout="this.style.background='#e11d48'">
                <i class="bi bi-floppy-fill"></i> Save Changes
            </button>
            <button onclick="closeWoundEditModal()"
                    style="padding:.75rem 1.25rem;background:#f1f5f9;color:#64748b;border:none;border-radius:.75rem;
                           font-size:.875rem;font-weight:600;cursor:pointer;"
                    onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
                Cancel
            </button>
        </div>
        <p id="woundEditErr" style="display:none;color:#dc2626;font-size:.75rem;margin-top:.5rem;text-align:center;"></p>
    </div>
</div>

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
        else { pdToast(d.error || 'Could not post reply.', 'error'); ta.disabled = false; }
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
        else { pdToast(d.error || 'Could not save edit.', 'error'); }
    };

    window.deleteCareNote = async function(id) {
        if (!await pdConfirm({message: 'Delete this note?', subtext: 'Replies will also be removed.', confirmLabel: 'Delete', confirmIcon: 'bi bi-trash3', confirmStyle: 'background:#dc2626;'})) return;
        const d = await apiFetch({ action: 'delete', id, patient_id: PID });
        if (d.ok) { location.reload(); }
        else { pdToast(d.error || 'Could not delete note.', 'error'); }
    };

    window.pinCareNote = async function(id, pinned) {
        const d = await apiFetch({ action: 'pin', id, patient_id: PID, pinned });
        if (d.ok) { location.reload(); }
        else { pdToast(d.error || 'Could not update pin.', 'error'); }
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
</div><!-- /pt-tab-body -->

<?php if (!$isPartial && canAccessClinical()): ?>
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
            pdToast('File too large — max 10 MB.', 'error');
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
                if (!data.ok) { pdToast(data.error || 'Upload failed', 'error'); spinner.outerHTML = origContent; return; }
                // Replace with new image
                var newImg = document.createElement('img');
                newImg.id        = 'ptAvatarImg';
                newImg.src       = data.url + '?v=' + Date.now();
                newImg.alt       = '';
                newImg.className = 'w-14 h-14 rounded-2xl object-cover shadow-lg border-2 border-white ring-2 ring-blue-100';
                spinner.replaceWith(newImg);
                HAS_PHOTO = true;
            })
            .catch(function () { pdToast('Network error during upload.', 'error'); spinner.replaceWith(document.createElement('div')); });

        input.value = '';
    });

    // Right-click avatar → remove photo
    if (wrap) {
        wrap.addEventListener('contextmenu', async function (e) {
            if (!HAS_PHOTO) return;
            e.preventDefault();
            if (!await pdConfirm({message: 'Remove profile photo?', confirmLabel: 'Remove', confirmIcon: 'bi bi-trash3', confirmStyle: 'background:#dc2626;'})) return;
            fetch(BASE + '/api/patient_photo.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'remove', csrf: CSRF, patient_id: PID}),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) { pdToast(data.error || 'Error', 'error'); return; }
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

<!-- ── Patient Edit Drawer ──────────────────────────────────────────────────── -->
<?php if (canAccessClinical()): ?>
<!-- Backdrop -->
<div id="ptEditBackdrop"
     class="fixed inset-0 bg-black/40 z-[9998] hidden"
     onclick="closePtEditDrawer()"></div>

<!-- Slide-in drawer -->
<div id="ptEditDrawer"
     class="fixed top-0 right-0 h-full w-full sm:w-[480px] bg-white shadow-2xl z-[9999]
            flex flex-col translate-x-full transition-transform duration-300 ease-in-out overflow-hidden">

    <!-- Header -->
    <div class="flex items-center justify-between px-5 py-4 bg-gradient-to-r from-blue-600 to-blue-700 flex-shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 bg-white/20 rounded-xl grid place-items-center">
                <i class="bi bi-pencil-fill text-white"></i>
            </div>
            <div>
                <p class="text-white font-bold text-sm">Edit Patient</p>
                <p class="text-blue-200 text-xs" id="ptEditDrawerSubtitle"><?= h($patient['first_name'] . ' ' . $patient['last_name']) ?></p>
            </div>
        </div>
        <button onclick="closePtEditDrawer()" class="text-white/70 hover:text-white transition p-1.5 rounded-lg hover:bg-white/10">
            <i class="bi bi-x-lg text-lg"></i>
        </button>
    </div>

    <!-- Error bar -->
    <div id="ptEditErr" class="hidden mx-5 mt-4 flex items-center gap-2 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm flex-shrink-0">
        <i class="bi bi-exclamation-circle-fill"></i>
        <span id="ptEditErrMsg"></span>
    </div>

    <!-- Body (scrollable) -->
    <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">

        <!-- Name -->
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">First Name <span class="text-red-400">*</span></label>
                <input type="text" id="pef_first_name" value="<?= h($patient['first_name']) ?>"
                       class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition" required>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Last Name <span class="text-red-400">*</span></label>
                <input type="text" id="pef_last_name" value="<?= h($patient['last_name']) ?>"
                       class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition" required>
            </div>
        </div>

        <!-- DOB / Phone -->
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Date of Birth</label>
                <input type="date" id="pef_dob" value="<?= h($patient['dob'] ?? '') ?>"
                       class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Phone</label>
                <input type="tel" id="pef_phone" value="<?= h($patient['phone'] ?? '') ?>"
                       class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition">
            </div>
        </div>

        <!-- Email -->
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Email Address</label>
            <input type="email" id="pef_email" value="<?= h($patient['email'] ?? '') ?>"
                   class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition">
        </div>

        <!-- Address -->
        <div>
            <label class="block text-xs font-semibold text-slate-600 mb-1">Address</label>
            <input type="text" id="pef_address" value="<?= h($patient['address'] ?? '') ?>"
                   class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition">
        </div>

        <!-- Insurance -->
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Insurance</label>
                <input type="text" id="pef_insurance" value="<?= h($patient['insurance'] ?? '') ?>"
                       class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Member ID</label>
                <input type="text" id="pef_insurance_id" value="<?= h($patient['insurance_id'] ?? '') ?>"
                       class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition">
            </div>
        </div>

        <!-- Race / PCP -->
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Race / Ethnicity</label>
                <select id="pef_race"
                        class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition">
                    <option value="">— Select —</option>
                    <?php foreach ([
                        'American Indian or Alaska Native','Asian','Black or African American',
                        'Hispanic or Latino','Native Hawaiian or Other Pacific Islander',
                        'White / Caucasian','Two or More Races','Other','Unknown / Declined to State',
                    ] as $r): ?>
                    <option value="<?= h($r) ?>" <?= ($patient['race'] ?? '') === $r ? 'selected' : '' ?>><?= h($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">PCP</label>
                <input type="text" id="pef_pcp" value="<?= h($patient['pcp'] ?? '') ?>"
                       class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition">
            </div>
        </div>

        <!-- Pharmacy -->
        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
            <p class="text-xs font-bold text-slate-600"><i class="bi bi-prescription2 text-emerald-500 mr-1"></i> Pharmacy</p>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Name</label>
                    <input type="text" id="pef_pharmacy_name" value="<?= h($patient['pharmacy_name'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1">Phone</label>
                    <input type="tel" id="pef_pharmacy_phone" value="<?= h($patient['pharmacy_phone'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Address</label>
                <input type="text" id="pef_pharmacy_address" value="<?= h($patient['pharmacy_address'] ?? '') ?>"
                       class="w-full px-3 py-2 border border-slate-200 rounded-xl text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
            </div>
        </div>

        <!-- Status -->
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1">Status</label>
                <select id="pef_status"
                        onchange="document.getElementById('pef_discharge_wrap').classList.toggle('hidden', this.value !== 'discharged')"
                        class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition">
                    <option value="active"     <?= ($patient['status'] ?? 'active') === 'active'     ? 'selected' : '' ?>>Active</option>
                    <option value="inactive"   <?= ($patient['status'] ?? 'active') === 'inactive'   ? 'selected' : '' ?>>Inactive</option>
                    <option value="discharged" <?= ($patient['status'] ?? 'active') === 'discharged' ? 'selected' : '' ?>>Discharged</option>
                </select>
            </div>
            <div id="pef_discharge_wrap" class="<?= ($patient['status'] ?? 'active') !== 'discharged' ? 'hidden' : '' ?>">
                <label class="block text-xs font-semibold text-slate-600 mb-1">Discharge Date</label>
                <input type="date" id="pef_discharged_at" value="<?= h($patient['discharged_at'] ?? '') ?>"
                       class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-red-400 focus:border-transparent focus:bg-white transition">
            </div>
        </div>

        <?php if (isAdmin()): ?>
        <!-- Provider / MA (admin only) -->
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1"><i class="bi bi-person-video3 mr-1"></i>Provider</label>
                <select id="pef_assigned_provider"
                        class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition">
                    <option value="">— Unassigned —</option>
                    <?php foreach ($maStaff as $sf): if ($sf['role'] !== 'provider' && $sf['role'] !== 'admin') continue; ?>
                    <option value="<?= h($sf['full_name']) ?>" <?= ($patient['assigned_provider'] ?? '') === $sf['full_name'] ? 'selected' : '' ?>><?= h($sf['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-600 mb-1"><i class="bi bi-person-badge mr-1"></i>Assigned MA</label>
                <select id="pef_assigned_ma"
                        class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent focus:bg-white transition">
                    <option value="">— Unassigned —</option>
                    <?php foreach ($maStaff as $sf): ?>
                    <option value="<?= $sf['id'] ?>" <?= ((int)($patient['assigned_ma'] ?? 0) === (int)$sf['id']) ? 'selected' : '' ?>><?= h($sf['full_name']) ?> (<?= h($sf['role']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endif; ?>

        <!-- Insurance Card Photos -->
        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-3">
            <p class="text-xs font-bold text-slate-600"><i class="bi bi-credit-card-2-front text-blue-500 mr-1"></i> Insurance Card Photos</p>
            <div class="grid grid-cols-2 gap-3">
                <?php foreach ([
                    ['insurance_photo',      'Front of Card', 'pefInsFront', 'pefInsFrontThumb'],
                    ['insurance_photo_back', 'Back of Card',  'pefInsBack',  'pefInsBackThumb'],
                ] as [$field, $label, $inputId, $thumbId]): ?>
                <div>
                    <label class="block text-xs font-semibold text-slate-500 mb-1"><?= $label ?></label>
                    <?php if (!empty($patient[$field])): ?>
                    <div class="mb-1.5 flex items-center gap-2">
                        <img src="<?= h($patient[$field]) ?>" class="h-12 rounded-lg border border-slate-200 object-cover">
                        <label class="text-xs text-red-400 hover:text-red-600 cursor-pointer flex items-center gap-1">
                            <input type="checkbox" id="pef_remove_<?= $field ?>" class="sr-only pef-remove-photo" data-field="<?= $field ?>">
                            <i class="bi bi-trash"></i> Remove
                        </label>
                    </div>
                    <?php endif; ?>
                    <input type="hidden" id="<?= $inputId ?>Data" value="">
                    <label class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-600 cursor-pointer hover:bg-blue-50 hover:border-blue-300 transition-colors">
                        <i class="bi bi-camera text-blue-500"></i> <?= !empty($patient[$field]) ? 'Replace' : 'Upload' ?>
                        <input type="file" accept="image/*" class="sr-only pef-photo-input" data-target="<?= $inputId ?>Data" data-thumb="<?= $thumbId ?>">
                    </label>
                    <img id="<?= $thumbId ?>" src="" class="hidden h-10 mt-1.5 rounded-lg border border-slate-200 object-cover">
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- SSS / Gov ID -->
        <div class="p-3.5 bg-slate-50 border border-slate-200 rounded-xl space-y-2">
            <p class="text-xs font-bold text-slate-600"><i class="bi bi-person-vcard text-indigo-500 mr-1"></i> SSS / Government ID</p>
            <?php if (!empty($patient['sss_photo'])): ?>
            <div class="flex items-center gap-2">
                <img src="<?= h($patient['sss_photo']) ?>" class="h-12 rounded-lg border border-slate-200 object-cover">
                <label class="text-xs text-red-400 hover:text-red-600 cursor-pointer flex items-center gap-1">
                    <input type="checkbox" id="pef_remove_sss_photo" class="sr-only pef-remove-photo" data-field="sss_photo">
                    <i class="bi bi-trash"></i> Remove
                </label>
            </div>
            <?php endif; ?>
            <input type="hidden" id="pefSssData" value="">
            <label class="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-white border border-slate-200 rounded-xl text-xs font-semibold text-slate-600 cursor-pointer hover:bg-indigo-50 hover:border-indigo-300 transition-colors">
                <i class="bi bi-camera text-indigo-500"></i> <?= !empty($patient['sss_photo']) ? 'Replace' : 'Upload' ?>
                <input type="file" accept="image/*" class="sr-only pef-photo-input" data-target="pefSssData" data-thumb="pefSssThumb">
            </label>
            <img id="pefSssThumb" src="" class="hidden h-10 mt-1.5 rounded-lg border border-slate-200 object-cover">
        </div>

    </div><!-- /body -->

    <!-- Footer buttons -->
    <div class="flex gap-3 px-5 py-4 border-t border-slate-100 bg-white flex-shrink-0">
        <button id="ptEditSaveBtn" onclick="savePtEdit()"
                class="flex-1 flex items-center justify-center gap-2 bg-blue-600 hover:bg-blue-700 active:scale-95
                       text-white font-semibold px-5 py-2.5 rounded-xl transition-all shadow-sm">
            <i class="bi bi-check-circle-fill"></i> Save Changes
        </button>
        <button onclick="closePtEditDrawer()"
                class="px-5 py-2.5 rounded-xl text-sm font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors">
            Cancel
        </button>
    </div>
</div>

<script>
(function () {
    const PATIENT_ID = <?= $id ?>;
    const CSRF       = <?= json_encode(csrfToken()) ?>;
    const BASE       = '<?= BASE_URL ?>';

    // ── Drawer open/close ────────────────────────────────────────────────────
    window.openPtEditDrawer = function () {
        document.getElementById('ptEditDrawer').classList.remove('translate-x-full');
        document.getElementById('ptEditBackdrop').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        // Hide error bar
        document.getElementById('ptEditErr').classList.add('hidden');
    };

    window.closePtEditDrawer = function () {
        document.getElementById('ptEditDrawer').classList.add('translate-x-full');
        document.getElementById('ptEditBackdrop').classList.add('hidden');
        document.body.style.overflow = '';
    };

    // Close on Escape
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closePtEditDrawer();
    });

    // ── Photo file inputs ────────────────────────────────────────────────────
    document.querySelectorAll('.pef-photo-input').forEach(function (input) {
        input.addEventListener('change', function () {
            var file = input.files[0];
            if (!file || !file.type.startsWith('image/')) return;
            if (file.size > 8 * 1024 * 1024) { alert('Image must be under 8 MB.'); input.value = ''; return; }
            var reader = new FileReader();
            reader.onload = function (e) {
                var targetInput = document.getElementById(input.dataset.target);
                var thumb       = document.getElementById(input.dataset.thumb);
                if (targetInput) targetInput.value = e.target.result;
                if (thumb) { thumb.src = e.target.result; thumb.classList.remove('hidden'); }
            };
            reader.readAsDataURL(file);
        });
    });

    // Visual feedback for photo removal checkboxes
    document.querySelectorAll('.pef-remove-photo').forEach(function (cb) {
        cb.addEventListener('change', function () {
            var img = cb.closest('div').querySelector('img');
            if (img) img.style.opacity = cb.checked ? '0.3' : '1';
        });
    });

    // ── Save ─────────────────────────────────────────────────────────────────
    window.savePtEdit = async function () {
        var errBar = document.getElementById('ptEditErr');
        var errMsg = document.getElementById('ptEditErrMsg');
        var saveBtn = document.getElementById('ptEditSaveBtn');

        errBar.classList.add('hidden');
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="bi bi-hourglass-split animate-spin"></i> Saving…';

        var payload = {
            csrf: CSRF,
            patient_id: PATIENT_ID,
            first_name: document.getElementById('pef_first_name').value.trim(),
            last_name:  document.getElementById('pef_last_name').value.trim(),
            dob:        document.getElementById('pef_dob').value,
            phone:      document.getElementById('pef_phone').value.trim(),
            email:      document.getElementById('pef_email').value.trim(),
            address:    document.getElementById('pef_address').value.trim(),
            insurance:  document.getElementById('pef_insurance').value.trim(),
            insurance_id:        document.getElementById('pef_insurance_id').value.trim(),
            race:                document.getElementById('pef_race').value,
            pcp:                 document.getElementById('pef_pcp').value.trim(),
            pharmacy_name:       document.getElementById('pef_pharmacy_name').value.trim(),
            pharmacy_phone:      document.getElementById('pef_pharmacy_phone').value.trim(),
            pharmacy_address:    document.getElementById('pef_pharmacy_address').value.trim(),
            status:              document.getElementById('pef_status').value,
            discharged_at:       document.getElementById('pef_discharged_at') ? document.getElementById('pef_discharged_at').value : '',
            // Photos
            insurance_photo:      document.getElementById('pefInsFrontData') ? document.getElementById('pefInsFrontData').value : '',
            insurance_photo_back: document.getElementById('pefInsBackData')  ? document.getElementById('pefInsBackData').value  : '',
            sss_photo:            document.getElementById('pefSssData')       ? document.getElementById('pefSssData').value       : '',
        };

        // Removal flags
        document.querySelectorAll('.pef-remove-photo:checked').forEach(function (cb) {
            payload['remove_' + cb.dataset.field] = true;
        });

        // Admin-only fields
        var provEl = document.getElementById('pef_assigned_provider');
        var maEl   = document.getElementById('pef_assigned_ma');
        if (provEl) payload.assigned_provider = provEl.value;
        if (maEl)   payload.assigned_ma       = maEl.value;

        try {
            var res  = await fetch(BASE + '/api/patient_update.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload),
            });
            var data = await res.json();
            if (!data.ok) {
                errMsg.textContent = data.error || 'Save failed.';
                errBar.classList.remove('hidden');
                return;
            }
            var pt = data.patient;
            updatePtDisplay(pt);
            closePtEditDrawer();
            // Show toast
            showPtToast('Patient updated successfully!');
        } catch (e) {
            errMsg.textContent = 'Network error — please try again.';
            errBar.classList.remove('hidden');
        } finally {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Save Changes';
        }
    };

    // ── Update visible patient info ──────────────────────────────────────────
    function updatePtDisplay(pt) {
        // Name heading
        var nameEl = document.getElementById('ptNameDisplay');
        if (nameEl) {
            var badge = nameEl.querySelector('.pt-status-badge');
            nameEl.childNodes[0].textContent = (pt.first_name + ' ' + pt.last_name + ' ');
            if (badge) {
                var stMap = {active:'bg-emerald-100 text-emerald-700', inactive:'bg-amber-100 text-amber-700', discharged:'bg-red-100 text-red-700'};
                badge.className = 'pt-status-badge text-xs font-semibold px-2.5 py-0.5 rounded-full ' + (stMap[pt.status] || stMap.active);
                badge.textContent = pt.status.charAt(0).toUpperCase() + pt.status.slice(1);
            }
        }

        // Sub-info line (dob/age, phone, insurance, MA)
        var subInfo = document.getElementById('ptSubInfo');
        if (subInfo) {
            var parts = [];
            if (pt.dob) {
                var born = new Date(pt.dob + 'T00:00:00');
                var today = new Date();
                var age = today.getFullYear() - born.getFullYear() - (today < new Date(today.getFullYear(), born.getMonth(), born.getDate()) ? 1 : 0);
                var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                parts.push('<span id="ptDobDisplay"><i class="bi bi-calendar3 mr-1"></i>' + months[born.getMonth()] + ' ' + born.getDate() + ', ' + born.getFullYear() + ' &middot; <strong class="text-slate-700">' + age + ' yrs</strong></span>');
            }
            if (pt.phone) parts.push('<span id="ptPhoneDisplay"><i class="bi bi-telephone mr-1"></i>' + escHtml(pt.phone) + '</span>');
            if (pt.insurance) parts.push('<span id="ptInsDisplay"><i class="bi bi-shield-plus mr-1"></i>' + escHtml(pt.insurance) + '</span>');
            if (pt.assigned_ma_name) parts.push('<span id="ptMaBadge" class="text-blue-700 bg-blue-50 px-2 py-0.5 rounded-md"><i class="bi bi-person-badge mr-1"></i>' + escHtml(pt.assigned_ma_name) + '</span>');
            subInfo.innerHTML = parts.join('');
        }

        // Page title
        document.title = pt.first_name + ' ' + pt.last_name;

        // Breadcrumb name
        var bc = document.querySelector('nav .text-slate-700.font-semibold.truncate');
        if (bc) bc.textContent = pt.first_name + ' ' + pt.last_name;

        // Drawer subtitle
        var sub = document.getElementById('ptEditDrawerSubtitle');
        if (sub) sub.textContent = pt.first_name + ' ' + pt.last_name;

        // Info bar (email, address, pcp, race, insurance_id, pharmacy)
        var infoBar = document.getElementById('ptInfoBar');
        if (infoBar) {
            var items = [];
            if (pt.email) items.push('<span style="word-break:break-all"><i class="bi bi-envelope mr-1"></i>' + escHtml(pt.email) + '</span>');
            if (pt.address) items.push('<span><i class="bi bi-geo-alt mr-1"></i>' + escHtml(pt.address) + '</span>');
            if (pt.pcp) items.push('<span><i class="bi bi-person-badge mr-1"></i>PCP: ' + escHtml(pt.pcp) + '</span>');
            if (pt.race) items.push('<span><i class="bi bi-people mr-1"></i>' + escHtml(pt.race) + '</span>');
            if (pt.insurance_id) items.push('<span><i class="bi bi-credit-card mr-1"></i>ID: ' + escHtml(pt.insurance_id) + '</span>');
            if (pt.pharmacy_name) {
                var phStr = escHtml(pt.pharmacy_name);
                if (pt.pharmacy_phone) phStr += ' &middot; ' + escHtml(pt.pharmacy_phone);
                items.push('<span><i class="bi bi-prescription2 mr-1 text-emerald-500"></i>' + phStr + '</span>');
            }
            if (pt.discharged_at && pt.status === 'discharged') {
                var d = new Date(pt.discharged_at + 'T00:00:00');
                var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
                items.push('<span class="text-red-500"><i class="bi bi-calendar-x mr-1"></i>Discharged: ' + months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear() + '</span>');
            }
            infoBar.innerHTML = items.join('');
            infoBar.classList.toggle('hidden', items.length === 0);
        }

        // Update drawer input values so re-opening shows new data
        var flds = ['first_name','last_name','dob','phone','email','address','insurance','insurance_id','pcp','pharmacy_name','pharmacy_phone','pharmacy_address'];
        flds.forEach(function (f) {
            var el = document.getElementById('pef_' + f);
            if (el) el.value = pt[f] || '';
        });
        var raceEl = document.getElementById('pef_race');
        if (raceEl) raceEl.value = pt.race || '';
        var stEl = document.getElementById('pef_status');
        if (stEl) { stEl.value = pt.status || 'active'; document.getElementById('pef_discharge_wrap').classList.toggle('hidden', pt.status !== 'discharged'); }
        var daEl = document.getElementById('pef_discharged_at');
        if (daEl) daEl.value = pt.discharged_at || '';
    }

    function escHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function showPtToast(msg) {
        var t = document.createElement('div');
        t.className = 'fixed top-20 right-4 z-[99999] flex items-center gap-3 bg-emerald-600 text-white px-5 py-3.5 rounded-2xl shadow-2xl text-sm font-semibold transition-all';
        t.innerHTML = '<i class="bi bi-check-circle-fill text-lg"></i>' + msg;
        document.body.appendChild(t);
        setTimeout(function () { t.style.opacity = '0'; t.style.transition = 'opacity 0.4s'; setTimeout(function () { t.remove(); }, 450); }, 2500);
    }
})();
</script>
<?php endif; ?>

<?php
if ($isPartial) {
    if (isset($extraJs)) echo $extraJs;
    exit;
}
include __DIR__ . '/includes/footer.php';
?>
