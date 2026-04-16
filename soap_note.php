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

<!-- Breadcrumb -->
<nav class="flex items-center gap-2 text-sm text-slate-400 mb-6 flex-wrap">
    <a href="<?= BASE_URL ?>/patients.php" class="hover:text-blue-600 transition-colors font-medium">Patients</a>
    <i class="bi bi-chevron-right text-xs"></i>
    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $patientId ?>&tab=notes"
       class="hover:text-blue-600 transition-colors font-medium"><?= h($patientName) ?></a>
    <i class="bi bi-chevron-right text-xs"></i>
    <span class="text-slate-700 font-semibold"><?= $noteId ? 'SOAP Note' : 'New SOAP Note' ?></span>
</nav>

<div class="max-w-3xl">

    <!-- Page header -->
    <div class="flex items-start justify-between gap-4 mb-5">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2">
                <i class="bi bi-journal-medical text-blue-600"></i>
                SOAP Note
            </h1>
            <p class="text-sm text-slate-500 mt-0.5"><?= h($patientName) ?></p>
        </div>
        <?php if ($note && $note['status'] === 'final'): ?>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-sm font-bold bg-emerald-100 text-emerald-700">
            <i class="bi bi-lock-fill text-xs"></i> Finalized
        </span>
        <?php elseif ($note): ?>
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-sm font-bold bg-amber-100 text-amber-700">
            <i class="bi bi-pencil text-xs"></i> Draft
        </span>
        <?php endif; ?>
    </div>

    <!-- Error banner -->
    <div id="noteError"
         class="hidden mb-4 flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
        <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i>
        <span id="noteErrorMsg"></span>
    </div>

    <!-- Note form -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">

        <!-- Note meta row -->
        <div class="px-5 py-4 bg-slate-50 border-b border-slate-100 flex flex-wrap gap-4 items-center">
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Note Date</label>
                <?php if ($readonly): ?>
                <p class="text-sm font-semibold text-slate-700">
                    <?= date('F j, Y', strtotime($note['note_date'])) ?>
                </p>
                <?php else: ?>
                <input type="date" id="noteDate"
                       value="<?= h($note['note_date'] ?? $defaultDate) ?>"
                       class="text-sm border border-slate-200 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                <?php endif; ?>
            </div>
            <?php if ($note && $note['author_name']): ?>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Author</label>
                <p class="text-sm font-semibold text-slate-700"><?= h($note['author_name']) ?></p>
            </div>
            <?php endif; ?>
            <?php if ($note && $note['finalized_at']): ?>
            <div>
                <label class="block text-xs font-semibold text-slate-500 mb-1">Finalized</label>
                <p class="text-sm text-slate-600"><?= date('M j, Y g:i A', strtotime($note['finalized_at'])) ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- S · O · A · P sections -->
        <?php
        $sections = [
            'subjective' => [
                'label' => 'S — Subjective',
                'color' => 'blue',
                'icon'  => 'bi-chat-left-dots-fill',
                'hint'  => 'Chief complaint, history of present illness, patient-reported symptoms, pain scale, functional status…',
            ],
            'objective' => [
                'label' => 'O — Objective',
                'color' => 'violet',
                'icon'  => 'bi-clipboard2-pulse-fill',
                'hint'  => 'Vital signs, physical exam findings, lab results, wound appearance, medication review…',
            ],
            'assessment' => [
                'label' => 'A — Assessment',
                'color' => 'orange',
                'icon'  => 'bi-tags-fill',
                'hint'  => 'Clinical impression, diagnosis or differential, response to treatment, problem list…',
            ],
            'plan' => [
                'label' => 'P — Plan',
                'color' => 'emerald',
                'icon'  => 'bi-check2-square',
                'hint'  => 'Treatment orders, medication changes, follow-up schedule, referrals, patient education, goals…',
            ],
        ];
        $colorMap = [
            'blue'   => ['border' => 'border-blue-200',   'bg' => 'bg-blue-50',   'text' => 'text-blue-700',   'ring' => 'focus:ring-blue-500'],
            'violet' => ['border' => 'border-violet-200', 'bg' => 'bg-violet-50', 'text' => 'text-violet-700', 'ring' => 'focus:ring-violet-500'],
            'orange' => ['border' => 'border-orange-200', 'bg' => 'bg-orange-50', 'text' => 'text-orange-700', 'ring' => 'focus:ring-orange-500'],
            'emerald'=> ['border' => 'border-emerald-200','bg' => 'bg-emerald-50','text' => 'text-emerald-700', 'ring' => 'focus:ring-emerald-500'],
        ];
        ?>
        <?php foreach ($sections as $field => $sec):
            $c = $colorMap[$sec['color']];
            $val = $note[$field] ?? '';
        ?>
        <div class="border-b border-slate-100 last:border-0">
            <div class="px-5 pt-4 pb-1 flex items-center gap-2">
                <span class="w-7 h-7 <?= $c['bg'] ?> rounded-lg grid place-items-center flex-shrink-0">
                    <i class="bi <?= $sec['icon'] ?> <?= $c['text'] ?> text-sm"></i>
                </span>
                <span class="text-sm font-bold <?= $c['text'] ?>"><?= $sec['label'] ?></span>
            </div>
            <?php if ($readonly): ?>
            <div class="px-5 pb-4 pt-2 text-sm text-slate-700 whitespace-pre-wrap leading-relaxed min-h-[60px]">
                <?= $val ? h($val) : '<span class="text-slate-400 italic">— not recorded —</span>' ?>
            </div>
            <?php else: ?>
            <textarea id="field_<?= $field ?>"
                      rows="4"
                      placeholder="<?= h($sec['hint']) ?>"
                      class="w-full px-5 py-3 text-sm text-slate-700 placeholder-slate-300 resize-y
                             border-0 focus:outline-none focus:ring-2 <?= $c['ring'] ?> rounded-none
                             bg-white transition-shadow leading-relaxed"><?= h($val) ?></textarea>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <!-- Action bar -->
        <?php if (!$readonly): ?>
        <div class="px-5 py-4 bg-slate-50 border-t border-slate-100 flex flex-wrap items-center gap-3">
            <button id="saveDraftBtn"
                    onclick="saveNote('draft')"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-white border border-slate-200
                           text-slate-700 font-semibold text-sm rounded-xl hover:bg-slate-50 transition-colors shadow-sm">
                <i class="bi bi-floppy-fill text-slate-400"></i> Save Draft
            </button>
            <button id="finalizeBtn"
                    onclick="saveNote('final')"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700
                           text-white font-bold text-sm rounded-xl transition-colors shadow-sm">
                <i class="bi bi-lock-fill"></i> Finalize Note
            </button>
            <?php if ($noteId && (isAdmin() || ($note['status'] === 'draft' && (int)$note['author_id'] === (int)$_SESSION['user_id']))): ?>
            <button id="deleteBtn"
                    onclick="deleteNote()"
                    class="ml-auto inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-red-200
                           text-red-600 font-semibold text-sm rounded-xl hover:bg-red-50 transition-colors">
                <i class="bi bi-trash3"></i> Delete
            </button>
            <?php endif; ?>
        </div>
        <?php elseif (isAdmin()): ?>
        <!-- Admin: re-open finalized note -->
        <div class="px-5 py-4 bg-slate-50 border-t border-slate-100 flex items-center gap-3">
            <span class="text-xs text-slate-400">Admin override:</span>
            <button onclick="saveNote('draft')"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-amber-50 border border-amber-200
                           text-amber-700 font-semibold text-sm rounded-xl hover:bg-amber-100 transition-colors">
                <i class="bi bi-unlock-fill"></i> Re-open as Draft
            </button>
            <button onclick="deleteNote()"
                    class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-red-200
                           text-red-600 font-semibold text-sm rounded-xl hover:bg-red-50 transition-colors">
                <i class="bi bi-trash3"></i> Delete
            </button>
        </div>
        <?php endif; ?>
    </div>

</div>

<script>
(function () {
    const BASE       = <?= json_encode(BASE_URL) ?>;
    const CSRF       = <?= json_encode($csrf) ?>;
    const NOTE_ID    = <?= $noteId ?: 'null' ?>;
    const PATIENT_ID = <?= (int)$patientId ?>;
    const VISIT_ID   = <?= $visitId ?: 'null' ?>;

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
            if (loading) el.dataset.origHtml = el.innerHTML;
            else if (el.dataset.origHtml) el.innerHTML = el.dataset.origHtml;
        });
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
                // Redirect to edit mode (or back to patient tab if finalizing)
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

    // Saved flash
    if (new URLSearchParams(window.location.search).get('saved') === '1') {
        const toast = document.createElement('div');
        toast.className = 'fixed top-5 right-5 z-50 flex items-center gap-2 bg-emerald-600 text-white text-sm font-semibold px-4 py-3 rounded-2xl shadow-xl transition-opacity';
        toast.innerHTML = '<i class="bi bi-check-circle-fill"></i> Draft saved';
        document.body.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 400); }, 2500);
    }
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
