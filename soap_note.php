<?php
/**
 * soap_note.php — Create or edit a SOAP note for a patient.
 *
 * GET  ?patient_id=X           → new note
 * GET  ?id=X                   → edit existing note
 * POST (handled by api/save_soap.php via JS)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/audit.php';
requireNotBilling();
if (!canAccessClinical()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$noteId    = (int)($_GET['id']         ?? 0);
$patientId = (int)($_GET['patient_id'] ?? 0);
$visitId   = (int)($_GET['visit_id']   ?? 0);
$note      = null;
$patient   = null;
$readonly  = false;

if ($noteId) {
    // Load existing note
    $stmt = $pdo->prepare("
        SELECT sn.*, p.first_name, p.last_name,
               s.full_name AS author_name
        FROM soap_notes sn
        JOIN patients p ON p.id = sn.patient_id
        JOIN staff   s ON s.id = sn.author_id
        WHERE sn.id = ?
    ");
    $stmt->execute([$noteId]);
    $note = $stmt->fetch();
    if (!$note) {
        header('Location: ' . BASE_URL . '/patients.php');
        exit;
    }
    $patientId = (int)$note['patient_id'];
    // Finalized notes are read-only for non-admins (admin can always edit)
    $readonly = ($note['status'] === 'final' && !isAdmin());
    $pageTitle = 'SOAP Note — ' . $note['first_name'] . ' ' . $note['last_name'];
} else {
    // New note — validate patient
    if (!$patientId) {
        header('Location: ' . BASE_URL . '/patients.php');
        exit;
    }
    $stmt = $pdo->prepare("SELECT id, first_name, last_name FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch();
    if (!$patient) {
        header('Location: ' . BASE_URL . '/patients.php');
        exit;
    }
    $pageTitle = 'New SOAP Note — ' . $patient['first_name'] . ' ' . $patient['last_name'];
}

$patientName = $note
    ? ($note['first_name'] . ' ' . $note['last_name'])
    : ($patient['first_name'] . ' ' . $patient['last_name']);

// For new note: pre-fill date = today; optionally link to a visit
$defaultDate = date('Y-m-d');
if ($visitId && !$noteId) {
    $vs = $pdo->prepare("SELECT visit_date FROM `schedule` WHERE id = ? AND patient_id = ?");
    $vs->execute([$visitId, $patientId]);
    $vr = $vs->fetch();
    if ($vr) $defaultDate = $vr['visit_date'];
}

auditLog($pdo, $noteId ? 'soap_view' : 'soap_create_start', 'patient', $patientId, $patientName);

$activeNav = 'patients';
$csrf = csrfToken();
include __DIR__ . '/includes/header.php';
?>


<style>
/* SOAP note page custom styles */
.soap-section { transition: box-shadow .15s, border-color .15s; }
.soap-section:focus-within { box-shadow: 0 0 0 3px var(--soap-ring); border-color: var(--soap-accent) !important; }
.soap-ta { field-sizing: content; min-height: 120px; }
@supports not (field-sizing: content) {
    .soap-ta { resize: vertical; }
}
.soap-ta:focus { outline: none; }
.prog-bar { transition: width .4s cubic-bezier(.4,0,.2,1); }
.section-filled .section-check { opacity: 1; transform: scale(1); }
.section-check { opacity: 0; transform: scale(.5); transition: opacity .2s, transform .2s; }
.sticky-bar { position: sticky; bottom: 0; z-index: 30; }
</style>

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-slate-400 mb-5 flex-wrap">
    <a href="<?= BASE_URL ?>/patients.php" class="hover:text-blue-600 transition-colors font-medium">Patients</a>
    <i class="bi bi-chevron-right text-xs"></i>
    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $patientId ?>&tab=notes"
       class="hover:text-blue-600 transition-colors font-medium"><?= h($patientName) ?></a>
    <i class="bi bi-chevron-right text-xs"></i>
    <span class="text-slate-700 font-semibold"><?= $noteId ? 'SOAP Note' : 'New SOAP Note' ?></span>
</nav>

<div class="max-w-3xl">

<!-- Hero header card -->
<div class="relative rounded-2xl overflow-hidden mb-5 shadow-sm">
    <div class="absolute inset-0 bg-gradient-to-br from-blue-600 via-indigo-600 to-violet-700"></div>
    <div class="absolute inset-0 opacity-10" style="background-image:radial-gradient(circle at 80% 20%, #fff 0%, transparent 60%)"></div>
    <div class="relative px-6 py-5 flex items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur grid place-items-center flex-shrink-0">
                <i class="bi bi-journal-medical text-white text-2xl"></i>
            </div>
            <div>
                <p class="text-blue-100 text-xs font-semibold uppercase tracking-wider mb-0.5">SOAP Note</p>
                <h1 class="text-white text-xl font-extrabold leading-tight"><?= h($patientName) ?></h1>
                <?php if ($note && $note['author_name']): ?>
                <p class="text-blue-200 text-xs mt-0.5 flex items-center gap-1.5">
                    <i class="bi bi-person-circle"></i><?= h($note['author_name']) ?>
                    <?php if ($note['finalized_at']): ?>
                    &nbsp;·&nbsp;<i class="bi bi-lock-fill text-xs"></i> Finalized <?= date('M j, Y', strtotime($note['finalized_at'])) ?>
                    <?php endif; ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex flex-col items-end gap-2">
            <?php if ($note && $note['status'] === 'final'): ?>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-emerald-500/30 text-emerald-100 border border-emerald-400/30">
                <i class="bi bi-lock-fill"></i> Finalized
            </span>
            <?php elseif ($note): ?>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-amber-400/30 text-amber-100 border border-amber-300/30">
                <i class="bi bi-pencil-fill"></i> Draft
            </span>
            <?php else: ?>
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold bg-white/20 text-white border border-white/20">
                <i class="bi bi-plus-circle"></i> New Note
            </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Error banner -->
<div id="noteError" class="hidden mb-4 flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
    <i class="bi bi-exclamation-circle-fill flex-shrink-0 text-base"></i>
    <span id="noteErrorMsg"></span>
</div>

<!-- Meta row -->
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 px-5 py-4 mb-4 flex flex-wrap gap-5 items-center">
    <div>
        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-1.5">
            <i class="bi bi-calendar3 mr-1"></i>Note Date
        </label>
        <?php if ($readonly): ?>
        <p class="text-sm font-bold text-slate-800"><?= date('F j, Y', strtotime($note['note_date'])) ?></p>
        <?php else: ?>
        <input type="date" id="noteDate"
               value="<?= h($note['note_date'] ?? $defaultDate) ?>"
               class="text-sm font-semibold border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-slate-50 hover:bg-white transition-colors">
        <?php endif; ?>
    </div>

    <?php if (!$readonly): ?>
    <!-- Progress pill -->
    <div class="flex-1 min-w-[180px]">
        <div class="flex items-center justify-between mb-1.5">
            <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Completion</span>
            <span id="progLabel" class="text-xs font-bold text-slate-600">0 / 4 sections</span>
        </div>
        <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
            <div id="progBar" class="prog-bar h-full rounded-full bg-gradient-to-r from-blue-500 to-emerald-500" style="width:0%"></div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- S · O · A · P sections -->
<?php
$sections = [
    'subjective' => [
        'label'    => 'Subjective',
        'letter'   => 'S',
        'color'    => 'blue',
        'gradient' => 'from-blue-50 to-blue-50/0',
        'icon'     => 'bi-chat-left-dots-fill',
        'hint'     => 'Chief complaint, history of present illness, patient-reported symptoms, pain scale, functional status…',
    ],
    'objective' => [
        'label'    => 'Objective',
        'letter'   => 'O',
        'color'    => 'violet',
        'gradient' => 'from-violet-50 to-violet-50/0',
        'icon'     => 'bi-clipboard2-pulse-fill',
        'hint'     => 'Vital signs, physical exam findings, lab results, wound appearance, medication review…',
    ],
    'assessment' => [
        'label'    => 'Assessment',
        'letter'   => 'A',
        'color'    => 'orange',
        'gradient' => 'from-orange-50 to-orange-50/0',
        'icon'     => 'bi-tags-fill',
        'hint'     => 'Clinical impression, diagnosis or differential, response to treatment, problem list…',
    ],
    'plan' => [
        'label'    => 'Plan',
        'letter'   => 'P',
        'color'    => 'emerald',
        'gradient' => 'from-emerald-50 to-emerald-50/0',
        'icon'     => 'bi-check2-square',
        'hint'     => 'Treatment orders, medication changes, follow-up schedule, referrals, patient education, goals…',
    ],
];
$colorMap = [
    'blue'    => ['accent'=>'#3b82f6','ring'=>'rgba(59,130,246,.25)',  'border'=>'border-blue-200',   'bg'=>'bg-blue-500',   'bgLight'=>'bg-blue-50',   'text'=>'text-blue-700',   'textLight'=>'text-blue-500',   'wc'=>'text-blue-400'],
    'violet'  => ['accent'=>'#8b5cf6','ring'=>'rgba(139,92,246,.25)',  'border'=>'border-violet-200', 'bg'=>'bg-violet-500', 'bgLight'=>'bg-violet-50', 'text'=>'text-violet-700', 'textLight'=>'text-violet-500', 'wc'=>'text-violet-400'],
    'orange'  => ['accent'=>'#f97316','ring'=>'rgba(249,115,22,.25)',  'border'=>'border-orange-200', 'bg'=>'bg-orange-500', 'bgLight'=>'bg-orange-50', 'text'=>'text-orange-700', 'textLight'=>'text-orange-500', 'wc'=>'text-orange-400'],
    'emerald' => ['accent'=>'#10b981','ring'=>'rgba(16,185,129,.25)',  'border'=>'border-emerald-200','bg'=>'bg-emerald-500','bgLight'=>'bg-emerald-50','text'=>'text-emerald-700','textLight'=>'text-emerald-500','wc'=>'text-emerald-400'],
];
?>

<div class="space-y-3 mb-4">
<?php foreach ($sections as $field => $sec):
    $c   = $colorMap[$sec['color']];
    $val = $note[$field] ?? '';
    $hasVal = trim($val) !== '';
?>
<div class="soap-section bg-white rounded-2xl border-2 <?= $c['border'] ?> overflow-hidden <?= $hasVal ? 'section-filled' : '' ?>"
     style="--soap-accent:<?= $c['accent'] ?>;--soap-ring:<?= $c['ring'] ?>">

    <!-- Section header -->
    <div class="flex items-center gap-3 px-5 py-3.5 bg-gradient-to-r <?= $sec['gradient'] ?> border-b <?= $c['border'] ?>">
        <!-- Letter badge -->
        <div class="<?= $c['bg'] ?> text-white w-8 h-8 rounded-xl font-extrabold text-base grid place-items-center flex-shrink-0 shadow-sm">
            <?= $sec['letter'] ?>
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                <span class="font-extrabold <?= $c['text'] ?> text-sm"><?= $sec['letter'] ?> — <?= $sec['label'] ?></span>
                <i class="bi <?= $sec['icon'] ?> <?= $c['textLight'] ?> text-xs"></i>
                <!-- Filled check -->
                <span class="section-check w-4 h-4 <?= $c['bgLight'] ?> <?= $c['text'] ?> rounded-full grid place-items-center text-[10px]">
                    <i class="bi bi-check2"></i>
                </span>
            </div>
        </div>
        <?php if (!$readonly): ?>
        <span id="wc_<?= $field ?>" class="text-xs <?= $c['wc'] ?> font-semibold tabular-nums shrink-0">
            <?= $hasVal ? str_word_count($val) . ' w' : '' ?>
        </span>
        <?php endif; ?>
    </div>

    <!-- Content -->
    <?php if ($readonly): ?>
    <div class="px-5 py-4 text-sm text-slate-700 whitespace-pre-wrap leading-relaxed min-h-[80px]">
        <?= $val ? h($val) : '<span class="text-slate-350 italic">— not recorded —</span>' ?>
    </div>
    <?php else: ?>
    <textarea id="field_<?= $field ?>"
              placeholder="<?= h($sec['hint']) ?>"
              data-field="<?= $field ?>"
              class="soap-ta w-full px-5 py-4 text-sm text-slate-700 placeholder-slate-300
                     border-0 bg-white leading-relaxed font-normal"><?= h($val) ?></textarea>
    <?php endif; ?>
</div>
<?php endforeach; ?>
</div>

<!-- Sticky action bar -->
<?php if (!$readonly): ?>
<div class="sticky-bar -mx-4 sm:-mx-6 px-4 sm:px-6 py-4 bg-white/90 backdrop-blur border-t border-slate-200 mt-6">
    <div class="max-w-3xl flex flex-wrap items-center gap-3">
        <button id="saveDraftBtn" onclick="saveNote('draft')"
                class="inline-flex items-center gap-2 px-5 py-2.5 bg-white border-2 border-slate-200
                       text-slate-700 font-bold text-sm rounded-xl hover:border-slate-300 hover:bg-slate-50
                       transition-all shadow-sm active:scale-95">
            <i class="bi bi-floppy-fill text-slate-400"></i> Save Draft
        </button>
        <button id="finalizeBtn" onclick="saveNote('final')"
                class="inline-flex items-center gap-2 px-6 py-2.5 bg-gradient-to-r from-emerald-500 to-teal-500
                       hover:from-emerald-600 hover:to-teal-600 text-white font-bold text-sm rounded-xl
                       transition-all shadow-md hover:shadow-lg active:scale-95">
            <i class="bi bi-lock-fill"></i> Finalize Note
        </button>
        <?php if ($noteId && (isAdmin() || ($note['status'] === 'draft' && (int)$note['author_id'] === (int)$_SESSION['user_id']))): ?>
        <button id="deleteBtn" onclick="deleteNote()"
                class="ml-auto inline-flex items-center gap-2 px-4 py-2.5 bg-white border-2 border-red-200
                       text-red-500 font-semibold text-sm rounded-xl hover:bg-red-50 hover:border-red-300
                       transition-all active:scale-95">
            <i class="bi bi-trash3"></i> Delete
        </button>
        <?php endif; ?>
    </div>
</div>
<?php elseif (isAdmin()): ?>
<div class="sticky-bar -mx-4 sm:-mx-6 px-4 sm:px-6 py-4 bg-white/90 backdrop-blur border-t border-slate-200 mt-6">
    <div class="max-w-3xl flex items-center gap-3">
        <span class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Admin override</span>
        <button onclick="saveNote('draft')"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-amber-50 border-2 border-amber-200
                       text-amber-700 font-bold text-sm rounded-xl hover:bg-amber-100 transition-all active:scale-95">
            <i class="bi bi-unlock-fill"></i> Re-open as Draft
        </button>
        <button onclick="deleteNote()"
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border-2 border-red-200
                       text-red-500 font-bold text-sm rounded-xl hover:bg-red-50 transition-all active:scale-95">
            <i class="bi bi-trash3"></i> Delete
        </button>
    </div>
</div>
<?php endif; ?>

</div><!-- /max-w-3xl -->

<script>
(function () {
    const BASE       = <?= json_encode(BASE_URL) ?>;
    const CSRF       = <?= json_encode($csrf) ?>;
    const NOTE_ID    = <?= $noteId ?: 'null' ?>;
    const PATIENT_ID = <?= (int)$patientId ?>;
    const VISIT_ID   = <?= $visitId ?: 'null' ?>;
    const READONLY   = <?= $readonly ? 'true' : 'false' ?>;

    const errBanner = document.getElementById('noteError');
    const errMsg    = document.getElementById('noteErrorMsg');

    function showErr(msg) {
        errMsg.textContent = msg;
        errBanner.classList.remove('hidden');
        window.scrollTo({top: 0, behavior: 'smooth'});
    }
    function hideErr() { errBanner.classList.add('hidden'); }

    function getField(id) {
        const el = document.getElementById(id);
        return el ? el.value.trim() : '';
    }

    function setLoading(btns, loading) {
        btns.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.disabled = loading;
            if (loading) {
                el.dataset.origHtml = el.innerHTML;
                el.innerHTML = '<span class="inline-block w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin"></span> Saving…';
            } else if (el.dataset.origHtml) {
                el.innerHTML = el.dataset.origHtml;
            }
        });
    }

    // Auto-grow textareas + word count + progress
    if (!READONLY) {
        const fields = ['subjective','objective','assessment','plan'];
        const progBar   = document.getElementById('progBar');
        const progLabel = document.getElementById('progLabel');

        function countWords(str) {
            return str.trim() === '' ? 0 : str.trim().split(/\s+/).length;
        }

        function updateProgress() {
            let filled = 0;
            fields.forEach(f => {
                const ta  = document.getElementById('field_' + f);
                const sec = ta ? ta.closest('.soap-section') : null;
                const wc  = document.getElementById('wc_' + f);
                if (!ta) return;
                const words = countWords(ta.value);
                if (ta.value.trim()) {
                    filled++;
                    sec && sec.classList.add('section-filled');
                } else {
                    sec && sec.classList.remove('section-filled');
                }
                if (wc) wc.textContent = words > 0 ? words + ' w' : '';
            });
            if (progBar)   progBar.style.width = (filled / fields.length * 100) + '%';
            if (progLabel) progLabel.textContent = filled + ' / 4 sections';
        }

        fields.forEach(f => {
            const ta = document.getElementById('field_' + f);
            if (!ta) return;
            ta.addEventListener('input', () => {
                updateProgress();
            });
        });

        updateProgress();
    }

    window.saveNote = async function(status) {
        hideErr();
        const noteDateEl = document.getElementById('noteDate');
        const noteDate   = noteDateEl ? noteDateEl.value : <?= json_encode($note['note_date'] ?? $defaultDate) ?>;

        if (!noteDate) { showErr('Please select a note date.'); return; }

        const payload = {
            csrf:        CSRF,
            action:      'save',
            id:          NOTE_ID,
            patient_id:  PATIENT_ID,
            visit_id:    VISIT_ID,
            note_date:   noteDate,
            subjective:  getField('field_subjective'),
            objective:   getField('field_objective'),
            assessment:  getField('field_assessment'),
            plan:        getField('field_plan'),
            status:      status,
        };

        setLoading(['saveDraftBtn', 'finalizeBtn'], true);
        try {
            const r    = await fetch(BASE + '/api/save_soap.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload),
            });
            const data = await r.json();
            if (data.ok) {
                if (status === 'final') {
                    window.location.href = BASE + '/patient_view.php?id=' + PATIENT_ID + '&tab=notes&msg=soap_saved';
                } else {
                    window.location.href = BASE + '/soap_note.php?id=' + data.id + '&saved=1';
                }
            } else {
                showErr(data.error || 'Could not save note. Please try again.');
                setLoading(['saveDraftBtn', 'finalizeBtn'], false);
            }
        } catch {
            showErr('Network error. Please check your connection and try again.');
            setLoading(['saveDraftBtn', 'finalizeBtn'], false);
        }
    };

    window.deleteNote = async function() {
        if (!confirm('Delete this SOAP note? This cannot be undone.')) return;
        setLoading(['deleteBtn'], true);
        try {
            const r    = await fetch(BASE + '/api/save_soap.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({csrf: CSRF, action: 'delete', id: NOTE_ID, patient_id: PATIENT_ID}),
            });
            const data = await r.json();
            if (data.ok) {
                window.location.href = BASE + '/patient_view.php?id=' + PATIENT_ID + '&tab=notes';
            } else {
                showErr(data.error || 'Could not delete note.');
                setLoading(['deleteBtn'], false);
            }
        } catch {
            showErr('Network error.');
            setLoading(['deleteBtn'], false);
        }
    };

    // Saved toast
    if (new URLSearchParams(window.location.search).get('saved') === '1') {
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-24 right-5 z-50 flex items-center gap-2.5 bg-slate-900 text-white text-sm font-semibold px-5 py-3 rounded-2xl shadow-2xl';
        toast.innerHTML = '<div class="w-5 h-5 bg-emerald-500 rounded-full grid place-items-center flex-shrink-0"><i class="bi bi-check2 text-xs text-white"></i></div> Draft saved';
        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.transition = 'opacity .3s, transform .3s';
            toast.style.opacity    = '0';
            toast.style.transform  = 'translateY(10px)';
            setTimeout(() => toast.remove(), 350);
        }, 2500);
    }
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
