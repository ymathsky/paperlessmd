<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireNotBilling();

$pageTitle = 'Quick Notes';
$activeNav = 'quick_notes';

// Filters
$filterDate    = trim($_GET['date']    ?? '');
$filterPatient = trim($_GET['patient'] ?? '');
$filterMa      = trim($_GET['ma']      ?? '');

if ($filterDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDate)) $filterDate = '';

// Build query — only visits that have a note
$sql = "
    SELECT sc.id, sc.visit_date, sc.visit_type, sc.visit_notes, sc.status,
           CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
           p.id AS patient_id,
           ma.full_name AS ma_name
    FROM `schedule` sc
    JOIN patients p ON p.id = sc.patient_id
    JOIN staff ma   ON ma.id = sc.ma_id
    WHERE sc.visit_notes IS NOT NULL AND sc.visit_notes != ''
";
$params = [];

if ($filterDate)    { $sql .= " AND sc.visit_date = ?";                   $params[] = $filterDate; }
if ($filterPatient) { $sql .= " AND CONCAT(p.first_name,' ',p.last_name) LIKE ?"; $params[] = '%' . $filterPatient . '%'; }
if ($filterMa)      { $sql .= " AND sc.ma_id = ?";                        $params[] = (int)$filterMa; }

// MAs only see their own notes
if (!isAdmin() && !isProvider()) {
    $sql .= " AND sc.ma_id = ?";
    $params[] = (int)$_SESSION['user_id'];
}

$sql .= " ORDER BY sc.visit_date DESC, sc.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$notes = $stmt->fetchAll();

// MA list for filter dropdown (admins/providers only)
$maList = [];
if (isAdmin()) {
    $maList = $pdo->query("SELECT id, full_name FROM staff WHERE role='ma' AND active=1 ORDER BY full_name")->fetchAll();
}

// Visit type labels
$vtLabels = ['routine'=>'Follow-Up','new_patient'=>'New Patient','wound_care'=>'Wound Care','awv'=>'Annual Wellness','ccm'=>'CCM','il'=>'IL Disc.','sick'=>'Sick','post_hospital'=>'Post Hosp. F/U'];
$statusClasses = [
    'pending'   => ['bg-slate-100',   'text-slate-600',  'bi-clock'],
    'en_route'  => ['bg-blue-100',    'text-blue-700',   'bi-play-circle-fill'],
    'completed' => ['bg-emerald-100', 'text-emerald-700','bi-check-circle-fill'],
    'missed'    => ['bg-red-100',     'text-red-600',    'bi-x-circle-fill'],
];

include __DIR__ . '/includes/header.php';
?>

<div class="mb-6 flex items-start justify-between gap-4 flex-wrap">
    <div>
        <h2 class="text-2xl font-extrabold text-slate-800">Quick Notes</h2>
        <p class="text-slate-500 text-sm mt-0.5">Clinical observations saved during visits</p>
    </div>
    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-100 text-amber-700 rounded-xl text-sm font-semibold">
        <i class="bi bi-sticky-fill"></i> <?= count($notes) ?> note<?= count($notes) !== 1 ? 's' : '' ?>
    </span>
</div>

<!-- Filters -->
<form method="get" class="bg-white border border-slate-100 rounded-2xl shadow-sm px-5 py-4 mb-6 flex flex-wrap gap-3 items-end">
    <div class="flex flex-col gap-1 min-w-[140px]">
        <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Date</label>
        <input type="date" name="date" value="<?= h($filterDate) ?>"
               class="px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-400">
    </div>
    <div class="flex flex-col gap-1 flex-1 min-w-[160px]">
        <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Patient Name</label>
        <input type="text" name="patient" value="<?= h($filterPatient) ?>" placeholder="Search patient…"
               class="px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-400">
    </div>
    <?php if (isAdmin() && $maList): ?>
    <div class="flex flex-col gap-1 min-w-[160px]">
        <label class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Medical Assistant</label>
        <select name="ma" class="px-3 py-2 text-sm border border-slate-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-400">
            <option value="">All MAs</option>
            <?php foreach ($maList as $m): ?>
            <option value="<?= $m['id'] ?>" <?= $filterMa == $m['id'] ? 'selected' : '' ?>><?= h($m['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
    <button type="submit"
            class="px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-bold rounded-xl transition-colors shadow-sm">
        <i class="bi bi-funnel-fill mr-1"></i> Filter
    </button>
    <?php if ($filterDate || $filterPatient || $filterMa): ?>
    <a href="<?= BASE_URL ?>/quick_notes.php"
       class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold rounded-xl transition-colors">
        Clear
    </a>
    <?php endif; ?>
</form>

<?php if (empty($notes)): ?>
<div class="bg-white border border-slate-100 rounded-2xl shadow-sm p-12 text-center">
    <div class="w-16 h-16 bg-amber-50 rounded-2xl grid place-items-center mx-auto mb-4">
        <i class="bi bi-sticky text-amber-400 text-3xl"></i>
    </div>
    <p class="text-slate-600 font-semibold text-lg mb-1">No notes found</p>
    <p class="text-slate-400 text-sm">Notes saved during visits will appear here.</p>
</div>
<?php else: ?>
<div class="space-y-3" id="notesList">
    <?php foreach ($notes as $note):
        $sc = $statusClasses[$note['status']] ?? $statusClasses['pending'];
        $vt = $note['visit_type'] ?? 'routine';
    ?>
    <div class="bg-white border border-slate-100 rounded-2xl shadow-sm hover:shadow-md transition-shadow"
         id="note-row-<?= $note['id'] ?>">
        <div class="flex items-start gap-4 p-4">
            <!-- Date badge -->
            <div class="shrink-0 w-14 text-center bg-amber-50 border border-amber-100 rounded-xl py-2">
                <div class="text-[10px] font-bold text-amber-500 uppercase leading-none"><?= date('M', strtotime($note['visit_date'])) ?></div>
                <div class="text-lg font-extrabold text-slate-800 leading-tight"><?= date('d', strtotime($note['visit_date'])) ?></div>
                <div class="text-[10px] text-slate-400 leading-none"><?= date('Y', strtotime($note['visit_date'])) ?></div>
            </div>
            <!-- Content -->
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-1.5">
                    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= $note['patient_id'] ?>"
                       class="font-bold text-slate-800 hover:text-indigo-600 transition-colors">
                        <?= h($note['patient_name']) ?>
                    </a>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold <?= $sc[0] ?> <?= $sc[1] ?>">
                        <i class="bi <?= $sc[2] ?>"></i> <?= ucwords(str_replace('_',' ',$note['status'])) ?>
                    </span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-violet-100 text-violet-700">
                        <?= h($vtLabels[$vt] ?? 'Follow-Up') ?>
                    </span>
                    <?php if (isAdmin()): ?>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-600">
                        <i class="bi bi-person-fill"></i> <?= h($note['ma_name']) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <!-- Note text (editable inline) -->
                <div class="bg-amber-50 border border-amber-100 rounded-xl px-3 py-2.5 text-sm text-slate-700 note-display-<?= $note['id'] ?>">
                    <?= nl2br(h($note['visit_notes'])) ?>
                </div>
                <textarea id="note-edit-<?= $note['id'] ?>"
                          class="hidden w-full mt-2 px-3 py-2.5 border border-amber-300 rounded-xl text-sm bg-white
                                 focus:outline-none focus:ring-2 focus:ring-amber-400 resize-none"
                          rows="3"><?= h($note['visit_notes']) ?></textarea>
                <div class="flex items-center gap-2 mt-2">
                    <button onclick="qnEditStart(<?= $note['id'] ?>)"
                            id="btn-edit-<?= $note['id'] ?>"
                            class="text-xs text-slate-400 hover:text-amber-600 font-semibold transition-colors">
                        <i class="bi bi-pencil-fill mr-0.5"></i> Edit
                    </button>
                    <button onclick="qnEditSave(<?= $note['id'] ?>)"
                            id="btn-save-<?= $note['id'] ?>"
                            class="hidden text-xs text-white bg-amber-500 hover:bg-amber-600 px-3 py-1 rounded-lg font-bold transition-colors">
                        <i class="bi bi-floppy-fill mr-0.5"></i> Save
                    </button>
                    <button onclick="qnEditCancel(<?= $note['id'] ?>)"
                            id="btn-cancel-<?= $note['id'] ?>"
                            class="hidden text-xs text-slate-500 hover:text-slate-700 font-semibold transition-colors">
                        Cancel
                    </button>
                    <span id="save-msg-<?= $note['id'] ?>" class="hidden text-xs text-emerald-600 font-semibold">
                        <i class="bi bi-check-circle-fill mr-0.5"></i> Saved!
                    </span>
                    <span class="ml-auto text-xs text-slate-400">
                        <a href="<?= BASE_URL ?>/schedule.php?date=<?= $note['visit_date'] ?>"
                           class="hover:text-indigo-500 transition-colors">
                            <i class="bi bi-calendar3 mr-0.5"></i> View in Schedule
                        </a>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
var QN_BASE = '<?= BASE_URL ?>';
var QN_CSRF = '<?= csrfToken() ?>';

function qnEditStart(id) {
    document.querySelector('.note-display-' + id).classList.add('hidden');
    document.getElementById('note-edit-' + id).classList.remove('hidden');
    document.getElementById('btn-edit-' + id).classList.add('hidden');
    document.getElementById('btn-save-' + id).classList.remove('hidden');
    document.getElementById('btn-cancel-' + id).classList.remove('hidden');
    document.getElementById('note-edit-' + id).focus();
}
function qnEditCancel(id) {
    document.querySelector('.note-display-' + id).classList.remove('hidden');
    document.getElementById('note-edit-' + id).classList.add('hidden');
    document.getElementById('btn-edit-' + id).classList.remove('hidden');
    document.getElementById('btn-save-' + id).classList.add('hidden');
    document.getElementById('btn-cancel-' + id).classList.add('hidden');
}
function qnEditSave(id) {
    var text = document.getElementById('note-edit-' + id).value;
    var msg  = document.getElementById('save-msg-' + id);
    fetch(QN_BASE + '/api/schedule_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ csrf: QN_CSRF, id: id, action: 'save_note', visit_notes: text })
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            // Update the display div
            var display = document.querySelector('.note-display-' + id);
            display.innerHTML = text.replace(/\n/g, '<br>') || '<span class="text-slate-400 italic">Note cleared</span>';
            qnEditCancel(id);
            msg.classList.remove('hidden');
            setTimeout(() => msg.classList.add('hidden'), 3000);
        }
    })
    .catch(() => {});
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
