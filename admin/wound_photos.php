<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();
if (!canAccessClinical()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$pageTitle = 'Wound Photo Portal';
$activeNav = 'wound_photos';

// ── Filters ──────────────────────────────────────────────────────────────────
$filterPatient  = (int)($_GET['patient_id'] ?? 0);
$filterMa       = (int)($_GET['ma_id']      ?? 0);
$filterLocation = trim($_GET['location']    ?? '');
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo   = $_GET['date_to']   ?? '';

// Validate date strings
$filterDateFrom = preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateFrom) ? $filterDateFrom : '';
$filterDateTo   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $filterDateTo)   ? $filterDateTo   : '';

// ── Build query ───────────────────────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($filterPatient) {
    $where[]  = 'wp.patient_id = ?';
    $params[] = $filterPatient;
}
if ($filterMa) {
    $where[]  = 'wp.uploaded_by = ?';
    $params[] = $filterMa;
}
if ($filterLocation !== '') {
    $where[]  = 'wp.wound_location = ?';
    $params[] = $filterLocation;
}
if ($filterDateFrom !== '') {
    $where[]  = 'DATE(wp.created_at) >= ?';
    $params[] = $filterDateFrom;
}
if ($filterDateTo !== '') {
    $where[]  = 'DATE(wp.created_at) <= ?';
    $params[] = $filterDateTo;
}

$whereStr = implode(' AND ', $where);

$sql = "
    SELECT wp.id, wp.filename, wp.description, wp.wound_location, wp.created_at,
           p.id AS patient_id, CONCAT(p.first_name,' ',p.last_name) AS patient_name,
           s.full_name AS ma_name, s.id AS ma_id
    FROM   wound_photos wp
    LEFT   JOIN patients p ON p.id = wp.patient_id
    LEFT   JOIN staff    s ON s.id = wp.uploaded_by
    WHERE  $whereStr
    ORDER  BY wp.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$photos = $stmt->fetchAll();

// ── Stats ─────────────────────────────────────────────────────────────────────
$totalPhotos    = count($photos);
$totalPatients  = count(array_unique(array_column($photos, 'patient_id')));
$todayCount     = count(array_filter($photos, function($p) { return date('Y-m-d', strtotime($p['created_at'])) === date('Y-m-d'); }));
$weekCount      = count(array_filter($photos, function($p) { return strtotime($p['created_at']) >= strtotime('-7 days'); }));

// ── Dropdown data (unfiltered) ────────────────────────────────────────────────
$allPatients   = $pdo->query("
    SELECT DISTINCT p.id, CONCAT(p.first_name,' ',p.last_name) AS full_name
    FROM wound_photos wp
    JOIN patients p ON p.id = wp.patient_id
    ORDER BY p.last_name, p.first_name
")->fetchAll();

$allMas = $pdo->query("
    SELECT DISTINCT s.id, s.full_name
    FROM wound_photos wp
    JOIN staff s ON s.id = wp.uploaded_by
    ORDER BY s.full_name
")->fetchAll();

$allLocations = $pdo->query("
    SELECT DISTINCT wound_location
    FROM wound_photos
    WHERE wound_location IS NOT NULL AND wound_location != ''
    ORDER BY wound_location
")->fetchAll(PDO::FETCH_COLUMN);

// ── Group by date ─────────────────────────────────────────────────────────────
$byDate = [];
foreach ($photos as $ph) {
    $day = date('Y-m-d', strtotime($ph['created_at']));
    $byDate[$day][] = $ph;
}

include __DIR__ . '/../includes/header.php';
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2">
            <i class="bi bi-camera-fill text-violet-500"></i>
            Wound Photo Portal
        </h1>
        <p class="text-slate-500 text-sm mt-0.5">All wound photos across every patient</p>
    </div>
    <?php if (isAdmin() || isMa()): ?>
    <a href="<?= BASE_URL ?>/patients.php"
       class="inline-flex items-center gap-2 px-4 py-2 bg-violet-600 hover:bg-violet-700
              text-white text-sm font-semibold rounded-xl shadow-sm transition-colors">
        <i class="bi bi-camera-fill"></i> Add Photos
    </a>
    <?php endif; ?>
</div>

<!-- Stats row -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <?php
    $stats = [
        ['label' => 'Total Photos',       'value' => $totalPhotos,   'icon' => 'bi-images',        'color' => 'violet'],
        ['label' => 'Patients',           'value' => $totalPatients, 'icon' => 'bi-people-fill',   'color' => 'blue'],
        ['label' => 'This Week',          'value' => $weekCount,     'icon' => 'bi-calendar-week', 'color' => 'emerald'],
        ['label' => 'Today',              'value' => $todayCount,    'icon' => 'bi-sun-fill',      'color' => 'amber'],
    ];
    $colorMap = [
        'violet'  => ['bg' => 'bg-violet-50',  'icon' => 'text-violet-500',  'num' => 'text-violet-700',  'border' => 'border-violet-100'],
        'blue'    => ['bg' => 'bg-blue-50',    'icon' => 'text-blue-500',    'num' => 'text-blue-700',    'border' => 'border-blue-100'],
        'emerald' => ['bg' => 'bg-emerald-50', 'icon' => 'text-emerald-500', 'num' => 'text-emerald-700', 'border' => 'border-emerald-100'],
        'amber'   => ['bg' => 'bg-amber-50',   'icon' => 'text-amber-500',   'num' => 'text-amber-700',   'border' => 'border-amber-100'],
    ];
    foreach ($stats as $s):
        $c = $colorMap[$s['color']];
    ?>
    <div class="<?= $c['bg'] ?> border <?= $c['border'] ?> rounded-2xl p-4 flex items-center gap-4">
        <div class="w-11 h-11 rounded-xl bg-white shadow-sm flex items-center justify-center shrink-0">
            <i class="bi <?= $s['icon'] ?> <?= $c['icon'] ?> text-xl"></i>
        </div>
        <div>
            <div class="text-2xl font-extrabold <?= $c['num'] ?>"><?= $s['value'] ?></div>
            <div class="text-xs text-slate-500 font-medium"><?= $s['label'] ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Filters -->
<form method="get" class="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 mb-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
        <!-- Patient -->
        <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wide">Patient</label>
            <select name="patient_id"
                    class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2 bg-white
                           focus:outline-none focus:ring-2 focus:ring-violet-400 text-slate-700">
                <option value="">All patients</option>
                <?php foreach ($allPatients as $ap): ?>
                <option value="<?= (int)$ap['id'] ?>" <?= $filterPatient === (int)$ap['id'] ? 'selected' : '' ?>>
                    <?= h($ap['full_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <!-- MA -->
        <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wide">Uploaded by</label>
            <select name="ma_id"
                    class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2 bg-white
                           focus:outline-none focus:ring-2 focus:ring-violet-400 text-slate-700">
                <option value="">All staff</option>
                <?php foreach ($allMas as $am): ?>
                <option value="<?= (int)$am['id'] ?>" <?= $filterMa === (int)$am['id'] ? 'selected' : '' ?>>
                    <?= h($am['full_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <!-- Location -->
        <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wide">Wound Site</label>
            <select name="location"
                    class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2 bg-white
                           focus:outline-none focus:ring-2 focus:ring-violet-400 text-slate-700">
                <option value="">All sites</option>
                <?php foreach ($allLocations as $loc): ?>
                <option value="<?= h($loc) ?>" <?= $filterLocation === $loc ? 'selected' : '' ?>>
                    <?= h($loc) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <!-- Date range -->
        <div>
            <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wide">Date From</label>
            <input type="date" name="date_from" value="<?= h($filterDateFrom) ?>"
                   class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2 bg-white
                          focus:outline-none focus:ring-2 focus:ring-violet-400 text-slate-700">
        </div>
        <div class="flex gap-2 items-end">
            <div class="flex-1">
                <label class="block text-xs font-semibold text-slate-500 mb-1.5 uppercase tracking-wide">Date To</label>
                <input type="date" name="date_to" value="<?= h($filterDateTo) ?>"
                       class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2 bg-white
                              focus:outline-none focus:ring-2 focus:ring-violet-400 text-slate-700">
            </div>
            <div class="flex gap-1.5 shrink-0">
                <button type="submit"
                        class="px-4 py-2 bg-violet-600 hover:bg-violet-700 text-white text-sm font-semibold
                               rounded-xl transition-colors shadow-sm">
                    <i class="bi bi-funnel-fill"></i>
                </button>
                <?php if ($filterPatient || $filterMa || $filterLocation || $filterDateFrom || $filterDateTo): ?>
                <a href="<?= BASE_URL ?>/admin/wound_photos.php"
                   class="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-sm font-semibold
                          rounded-xl transition-colors">
                    <i class="bi bi-x-lg"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if ($filterPatient || $filterMa || $filterLocation || $filterDateFrom || $filterDateTo): ?>
    <div class="mt-3 pt-3 border-t border-slate-100 flex flex-wrap gap-2 text-xs">
        <span class="text-slate-400 font-medium mr-1">Filters:</span>
        <?php if ($filterPatient): $fp = array_filter($allPatients, function($p) use ($filterPatient) { return (int)$p['id'] === $filterPatient; }); $fpName = reset($fp)['full_name'] ?? $filterPatient; ?>
        <span class="bg-violet-100 text-violet-700 px-2.5 py-1 rounded-full font-semibold">
            <i class="bi bi-person-fill mr-1"></i><?= h($fpName) ?>
        </span>
        <?php endif; ?>
        <?php if ($filterMa): $fm = array_filter($allMas, function($m) use ($filterMa) { return (int)$m['id'] === $filterMa; }); $fmName = reset($fm)['full_name'] ?? $filterMa; ?>
        <span class="bg-blue-100 text-blue-700 px-2.5 py-1 rounded-full font-semibold">
            <i class="bi bi-person-badge-fill mr-1"></i><?= h($fmName) ?>
        </span>
        <?php endif; ?>
        <?php if ($filterLocation): ?>
        <span class="bg-emerald-100 text-emerald-700 px-2.5 py-1 rounded-full font-semibold">
            <i class="bi bi-geo-alt-fill mr-1"></i><?= h($filterLocation) ?>
        </span>
        <?php endif; ?>
        <?php if ($filterDateFrom || $filterDateTo): ?>
        <span class="bg-amber-100 text-amber-700 px-2.5 py-1 rounded-full font-semibold">
            <i class="bi bi-calendar-range mr-1"></i>
            <?= $filterDateFrom ? date('M j, Y', strtotime($filterDateFrom)) : '...' ?>
            &ndash;
            <?= $filterDateTo ? date('M j, Y', strtotime($filterDateTo)) : 'today' ?>
        </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</form>

<!-- Gallery -->
<?php if (empty($photos)): ?>
<div class="text-center py-20 text-slate-400">
    <i class="bi bi-camera text-6xl opacity-20 block mb-4"></i>
    <p class="text-lg font-semibold text-slate-500">No wound photos found</p>
    <p class="text-sm mt-1">
        <?= ($filterPatient || $filterMa || $filterLocation || $filterDateFrom || $filterDateTo)
            ? 'Try adjusting your filters.'
            : 'Photos uploaded via the Wound Care form will appear here.' ?>
    </p>
</div>
<?php else: ?>
<div class="space-y-8" id="photoGallery">
    <?php foreach ($byDate as $day => $dayPhotos): ?>
    <div>
        <!-- Date header -->
        <div class="flex items-center gap-3 mb-4">
            <div class="flex items-center gap-2 bg-white border border-slate-200 rounded-xl px-3.5 py-1.5 shadow-sm shrink-0">
                <i class="bi bi-calendar3 text-violet-400 text-sm"></i>
                <span class="text-sm font-bold text-slate-700">
                    <?php
                    $ts = strtotime($day);
                    $today = date('Y-m-d');
                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                    if ($day === $today)          echo 'Today';
                    elseif ($day === $yesterday)  echo 'Yesterday';
                    else                          echo date('F j, Y', $ts);
                    ?>
                </span>
            </div>
            <span class="text-xs text-slate-400 font-medium">
                <?= count($dayPhotos) ?> photo<?= count($dayPhotos) !== 1 ? 's' : '' ?>
            </span>
            <div class="flex-1 h-px bg-slate-100"></div>
        </div>

        <!-- Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4">
            <?php foreach ($dayPhotos as $ph): ?>
            <div class="group bg-white rounded-2xl border-2 border-slate-100 overflow-hidden shadow-sm
                        hover:shadow-md hover:border-violet-200 transition-all cursor-pointer"
                 onclick="openLightbox(<?= (int)$ph['id'] ?>)">
                <!-- Thumbnail -->
                <div class="aspect-square overflow-hidden bg-slate-50 relative">
                    <img src="<?= BASE_URL ?>/uploads/photos/<?= h($ph['filename']) ?>"
                         alt="Wound photo"
                         loading="lazy"
                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                    <!-- Location badge -->
                    <?php if ($ph['wound_location']): ?>
                    <div class="absolute bottom-0 left-0 right-0 bg-gradient-to-t from-black/60 to-transparent
                                px-2 py-2">
                        <span class="text-white text-[10px] font-semibold leading-tight line-clamp-1">
                            <?= h($ph['wound_location']) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                <!-- Meta -->
                <div class="p-2.5">
                    <a href="<?= BASE_URL ?>/patient_view.php?id=<?= (int)$ph['patient_id'] ?>"
                       onclick="event.stopPropagation()"
                       class="text-xs font-bold text-slate-700 hover:text-violet-600 truncate block leading-tight
                              transition-colors">
                        <?= h($ph['patient_name']) ?>
                    </a>
                    <p class="text-[10px] text-slate-400 mt-0.5 truncate">
                        <?= h($ph['ma_name'] ?? 'Unknown') ?>
                        &nbsp;&middot;&nbsp;
                        <?= date('g:i a', strtotime($ph['created_at'])) ?>
                    </p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Lightbox modal -->
<div id="lightbox" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4"
     onclick="if(event.target===this) closeLightbox()">
    <div class="absolute inset-0 bg-black/80 backdrop-blur-sm"></div>
    <div class="relative bg-white rounded-3xl shadow-2xl max-w-4xl w-full max-h-[90vh] flex flex-col
                overflow-hidden z-10">
        <!-- Header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 shrink-0">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 bg-violet-100 rounded-xl flex items-center justify-center">
                    <i class="bi bi-camera-fill text-violet-600"></i>
                </div>
                <div>
                    <p id="lbPatientName" class="text-sm font-bold text-slate-800"></p>
                    <p id="lbLocation" class="text-xs text-violet-600 font-semibold"></p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a id="lbPatientLink" href="#"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-violet-50 hover:bg-violet-100
                          text-violet-700 text-xs font-semibold rounded-xl transition-colors">
                    <i class="bi bi-person-fill"></i> Patient Profile
                </a>
                <button onclick="closeLightbox()"
                        class="w-9 h-9 flex items-center justify-center rounded-xl hover:bg-slate-100
                               text-slate-400 hover:text-slate-700 transition-colors">
                    <i class="bi bi-x-lg text-base"></i>
                </button>
            </div>
        </div>
        <!-- Body -->
        <div class="flex flex-col md:flex-row overflow-y-auto flex-1 min-h-0">
            <!-- Image -->
            <div class="flex-1 bg-slate-900 flex items-center justify-center min-h-[300px] p-4">
                <img id="lbImg" src="" alt="Wound photo"
                     class="max-w-full max-h-[60vh] object-contain rounded-xl shadow-lg">
            </div>
            <!-- Sidebar meta -->
            <div class="w-full md:w-72 shrink-0 p-5 space-y-4 border-t md:border-t-0 md:border-l border-slate-100">
                <div>
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Patient</div>
                    <a id="lbPatientMeta" href="#"
                       class="text-sm font-bold text-slate-800 hover:text-violet-600 transition-colors"></a>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Wound Site</div>
                    <p id="lbLocationMeta" class="text-sm text-slate-700 font-medium"></p>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Date & Time</div>
                    <p id="lbDateMeta" class="text-sm text-slate-700"></p>
                </div>
                <div>
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Uploaded by</div>
                    <p id="lbMaMeta" class="text-sm text-slate-700"></p>
                </div>
                <div id="lbDescBlock" class="hidden">
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Notes</div>
                    <p id="lbDescMeta" class="text-sm text-slate-600 italic leading-relaxed"></p>
                </div>
                <a id="lbDownload" href="#" download target="_blank"
                   class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5
                          bg-slate-800 hover:bg-slate-900 text-white text-sm font-semibold
                          rounded-xl transition-colors shadow-sm mt-2">
                    <i class="bi bi-download"></i> Download
                </a>
            </div>
        </div>
        <!-- Prev / Next -->
        <div id="lbNav" class="absolute top-1/2 -translate-y-1/2 w-full flex justify-between px-3 pointer-events-none">
            <button onclick="lbStep(-1)"
                    class="pointer-events-auto w-10 h-10 rounded-full bg-black/50 hover:bg-black/70
                           text-white flex items-center justify-center shadow-lg transition-colors">
                <i class="bi bi-chevron-left text-sm"></i>
            </button>
            <button onclick="lbStep(1)"
                    class="pointer-events-auto w-10 h-10 rounded-full bg-black/50 hover:bg-black/70
                           text-white flex items-center justify-center shadow-lg transition-colors">
                <i class="bi bi-chevron-right text-sm"></i>
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    const PHOTOS = <?= json_encode(array_values(array_map(function($p) {
        return [
            'id'       => (int)$p['id'],
            'filename' => $p['filename'],
            'location' => $p['wound_location'] ?: 'Unspecified',
            'date'     => date('F j, Y \a\t g:i a', strtotime($p['created_at'])),
            'patient'  => $p['patient_name'],
            'patient_id' => (int)$p['patient_id'],
            'ma'       => $p['ma_name'] ?: 'Unknown',
            'desc'     => $p['description'] ?? '',
            'url'      => BASE_URL . '/uploads/photos/' . $p['filename'],
        ];
    }, $photos))) ?>;

    const BASE = <?= json_encode(BASE_URL) ?>;

    const indexMap = {};
    PHOTOS.forEach((p, i) => indexMap[p.id] = i);

    let current = 0;

    window.openLightbox = function(id) {
        current = indexMap[id];
        renderLightbox();
        document.getElementById('lightbox').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    };

    window.closeLightbox = function() {
        document.getElementById('lightbox').classList.add('hidden');
        document.body.style.overflow = '';
    };

    window.lbStep = function(dir) {
        current = (current + dir + PHOTOS.length) % PHOTOS.length;
        renderLightbox();
    };

    function renderLightbox() {
        const p = PHOTOS[current];
        document.getElementById('lbImg').src          = p.url;
        document.getElementById('lbPatientName').textContent = p.patient;
        document.getElementById('lbLocation').textContent    = p.location;
        document.getElementById('lbPatientMeta').textContent = p.patient;
        document.getElementById('lbPatientMeta').href        = BASE + '/patient_view.php?id=' + p.patient_id;
        document.getElementById('lbPatientLink').href        = BASE + '/patient_view.php?id=' + p.patient_id;
        document.getElementById('lbLocationMeta').textContent = p.location;
        document.getElementById('lbDateMeta').textContent    = p.date;
        document.getElementById('lbMaMeta').textContent      = p.ma;
        document.getElementById('lbDownload').href           = p.url;
        const descBlock = document.getElementById('lbDescBlock');
        if (p.desc) {
            document.getElementById('lbDescMeta').textContent = p.desc;
            descBlock.classList.remove('hidden');
        } else {
            descBlock.classList.add('hidden');
        }
        // Hide nav if only 1 photo
        document.getElementById('lbNav').style.display = PHOTOS.length > 1 ? '' : 'none';
    }

    // Keyboard nav
    document.addEventListener('keydown', function(e) {
        if (document.getElementById('lightbox').classList.contains('hidden')) return;
        if (e.key === 'Escape')      closeLightbox();
        if (e.key === 'ArrowLeft')   lbStep(-1);
        if (e.key === 'ArrowRight')  lbStep(1);
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
