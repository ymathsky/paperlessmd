<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();
requireAdmin();

if (file_exists(__DIR__ . '/../includes/WebPush.php')) {
    require_once __DIR__ . '/../includes/WebPush.php';
}

$pageTitle = 'Test Push Notifications';
$activeNav = 'push_test';

// ── Handle AJAX POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!verifyCsrf()) {
        echo json_encode(['error' => 'Invalid CSRF token']); exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'send_test') {
        $staffId = (int)($_POST['staff_id'] ?? 0);
        $title   = trim($_POST['title'] ?? 'Test Notification');
        $body    = trim($_POST['body']  ?? 'This is a test push from PaperlessMD.');
        $url     = trim($_POST['url']   ?? '/dashboard.php');

        if (!$staffId) { echo json_encode(['error' => 'No staff selected']); exit; }
        if (!function_exists('webpush_notify')) { echo json_encode(['error' => 'WebPush not available']); exit; }

        // Check the staff member has at least one subscription
        $subStmt = $pdo->prepare('SELECT COUNT(*) FROM push_subscriptions WHERE staff_id = ?');
        $subStmt->execute([$staffId]);
        $subCount = (int)$subStmt->fetchColumn();
        if ($subCount === 0) {
            echo json_encode(['error' => 'This staff member has no registered push subscription. They need to log in on a browser that has granted notification permission.']);
            exit;
        }

        webpush_notify($pdo, $staffId, $title, $body, BASE_URL . $url);
        echo json_encode(['ok' => true, 'subscriptions' => $subCount]);
        exit;
    }

    if ($action === 'list_subs') {
        $subs = $pdo->query("
            SELECT ps.id, ps.staff_id, s.full_name, s.role, s.username,
                   ps.created_at, ps.updated_at,
                   LEFT(ps.endpoint, 60) AS endpoint_short
            FROM push_subscriptions ps
            JOIN staff s ON s.id = ps.staff_id
            ORDER BY ps.updated_at DESC
        ")->fetchAll();
        echo json_encode(['ok' => true, 'subscriptions' => $subs]);
        exit;
    }

    echo json_encode(['error' => 'Unknown action']); exit;
}

// ── Load staff list for selector ─────────────────────────────────────────────
$staffList = $pdo->query(
    "SELECT s.id, s.full_name, s.username, s.role,
            (SELECT COUNT(*) FROM push_subscriptions ps WHERE ps.staff_id = s.id) AS sub_count
     FROM staff s WHERE s.active = 1
     ORDER BY s.full_name"
)->fetchAll();

// ── Morning push cron token ──────────────────────────────────────────────────
$morningTokenRow = $pdo->query("SELECT value FROM settings WHERE `key` = 'morning_push_token' LIMIT 1")->fetchColumn();
$morningToken = (string)($morningTokenRow ?: '(not yet generated — run the endpoint once to create it)');

$csrfTok = csrfToken();
include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto space-y-6">

    <div class="flex items-center gap-3 mb-2">
        <div class="w-10 h-10 bg-sky-100 rounded-2xl grid place-items-center">
            <i class="bi bi-bell-fill text-sky-600 text-xl"></i>
        </div>
        <div>
            <h1 class="text-xl font-black text-slate-800">Push Notification Tester</h1>
            <p class="text-sm text-slate-500">Send a test push to any staff member to verify delivery.</p>
        </div>
    </div>

    <!-- Active Subscriptions -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <i class="bi bi-phone text-slate-400"></i>
                <h4 class="text-sm font-bold text-slate-700">Active Push Subscriptions</h4>
            </div>
            <button onclick="loadSubs()" class="text-xs px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold rounded-lg transition-colors">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </div>
        <div id="subsTable" class="p-5">
            <p class="text-sm text-slate-400 italic">Loading subscriptions…</p>
        </div>
    </div>

    <!-- Send Test Push -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="bg-gradient-to-r from-sky-600 to-cyan-500 px-6 py-4 flex items-center gap-3">
            <div class="bg-white/20 p-2 rounded-xl shrink-0"><i class="bi bi-send-fill text-white text-lg"></i></div>
            <div>
                <h3 class="text-white font-bold">Send Test Push</h3>
                <p class="text-sky-100 text-xs">Target a specific staff member to verify end-to-end delivery</p>
            </div>
        </div>
        <div class="p-6 space-y-4">
            <div id="testResult" class="hidden px-4 py-3 rounded-xl text-sm font-semibold"></div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Staff Member</label>
                <select id="testStaffId" class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 transition">
                    <option value="">— Select recipient —</option>
                    <?php foreach ($staffList as $s): ?>
                    <option value="<?= (int)$s['id'] ?>" <?= $s['sub_count'] == 0 ? 'class="text-slate-400"' : '' ?>>
                        <?= h($s['full_name']) ?> (<?= h($s['role']) ?>)
                        <?= $s['sub_count'] > 0 ? ' ✓ ' . (int)$s['sub_count'] . ' device(s)' : ' — no subscription' ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Title</label>
                <input type="text" id="testTitle" value="Test Notification"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 transition"
                       placeholder="Notification title">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Body</label>
                <input type="text" id="testBody" value="This is a test push from PaperlessMD."
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 transition"
                       placeholder="Notification body text">
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Tap URL <span class="text-slate-400 font-normal text-xs">(relative path)</span></label>
                <input type="text" id="testUrl" value="/dashboard.php"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50 focus:outline-none focus:ring-2 focus:ring-sky-500 transition font-mono"
                       placeholder="/dashboard.php">
            </div>

            <button onclick="sendTest()" id="testSendBtn"
                    class="inline-flex items-center gap-2 px-5 py-2.5 bg-sky-600 hover:bg-sky-700 active:scale-95 text-white font-semibold rounded-xl text-sm transition-all shadow-sm">
                <i class="bi bi-send-fill"></i> Send Test Push
            </button>
        </div>
    </div>

    <!-- Morning Route Cron Info -->
    <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
            <i class="bi bi-sunrise text-amber-500"></i>
            <h4 class="text-sm font-bold text-slate-700">Morning Route Push (Cron)</h4>
        </div>
        <div class="p-5 space-y-4">
            <p class="text-sm text-slate-600">
                Each morning at 7 AM, MAs receive a push notification with their visit count for the day.
                Set up a cron job on the server to automate this:
            </p>
            <div class="bg-slate-900 text-emerald-300 rounded-xl px-4 py-3 font-mono text-xs overflow-x-auto whitespace-nowrap">
                0 7 * * * /usr/bin/php /var/www/paperlessmd/api/push_morning_route.php >> /var/log/pd_push.log 2>&amp;1
            </div>
            <p class="text-sm text-slate-600">Or trigger it now via HTTP (the token auto-generates on first use):</p>
            <div class="bg-slate-900 text-emerald-300 rounded-xl px-4 py-3 font-mono text-xs overflow-x-auto">
                <?= h(BASE_URL) ?>/api/push_morning_route.php?token=<span id="morningToken" class="text-yellow-300"><?= h($morningToken) ?></span>
            </div>
            <div class="flex flex-wrap gap-3">
                <button onclick="triggerMorningRoute()" id="morningBtn"
                        class="inline-flex items-center gap-2 px-4 py-2.5 bg-amber-500 hover:bg-amber-600 active:scale-95 text-white font-semibold rounded-xl text-sm transition-all shadow-sm">
                    <i class="bi bi-sunrise-fill"></i> Run Now
                </button>
                <a href="<?= h(BASE_URL) ?>/api/push_morning_route.php?token=<?= urlencode($morningToken) ?>"
                   target="_blank"
                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold rounded-xl text-sm transition-all">
                    <i class="bi bi-box-arrow-up-right"></i> Open in New Tab
                </a>
            </div>
            <div id="morningResult" class="hidden px-4 py-3 rounded-xl text-sm font-semibold"></div>
        </div>
    </div>

</div>

<script>
const CSRF = '<?= $csrfTok ?>';
const BASE = '<?= h(BASE_URL) ?>';
const MORNING_TOKEN = '<?= h($morningToken) ?>';

function showResult(el, msg, ok) {
    el.textContent = msg;
    el.className = 'px-4 py-3 rounded-xl text-sm font-semibold ' + (ok ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200');
    el.classList.remove('hidden');
    if (ok) setTimeout(function(){ el.classList.add('hidden'); }, 6000);
}

async function sendTest() {
    const staffId = document.getElementById('testStaffId').value;
    const title   = document.getElementById('testTitle').value.trim();
    const body    = document.getElementById('testBody').value.trim();
    const url     = document.getElementById('testUrl').value.trim();
    const resultEl = document.getElementById('testResult');
    const btn      = document.getElementById('testSendBtn');

    if (!staffId) { showResult(resultEl, 'Please select a staff member.', false); return; }

    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Sending…';

    const fd = new FormData();
    fd.append('action',   'send_test');
    fd.append('staff_id', staffId);
    fd.append('title',    title || 'Test Notification');
    fd.append('body',     body  || 'This is a test push from PaperlessMD.');
    fd.append('url',      url   || '/dashboard.php');
    fd.append('csrf_token', CSRF);

    try {
        const res  = await fetch('', { method: 'POST', body: fd });
        const data = await res.json();
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send-fill"></i> Send Test Push';
        if (data.ok) {
            showResult(resultEl, '✓ Push sent to ' + data.subscriptions + ' device(s). Check the recipient\'s browser/phone.', true);
        } else {
            showResult(resultEl, '✗ ' + (data.error || 'Unknown error'), false);
        }
    } catch (e) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send-fill"></i> Send Test Push';
        showResult(resultEl, '✗ Network error — try again', false);
    }
}

async function loadSubs() {
    const container = document.getElementById('subsTable');
    container.innerHTML = '<p class="text-sm text-slate-400 italic">Loading…</p>';

    const fd = new FormData();
    fd.append('action', 'list_subs');
    fd.append('csrf_token', CSRF);
    const res  = await fetch('', { method: 'POST', body: fd });
    const data = await res.json();

    if (!data.ok || !data.subscriptions.length) {
        container.innerHTML = '<p class="text-sm text-slate-400 italic">No active subscriptions found.</p>';
        return;
    }

    let html = '<div class="overflow-x-auto"><table class="w-full text-sm">';
    html += '<thead><tr class="text-left text-xs text-slate-400 font-semibold uppercase tracking-wide border-b border-slate-100">';
    html += '<th class="pb-2 pr-4">Staff</th><th class="pb-2 pr-4">Role</th><th class="pb-2 pr-4">Endpoint</th><th class="pb-2">Last Active</th></tr></thead><tbody>';
    data.subscriptions.forEach(function(s) {
        html += '<tr class="border-b border-slate-50 hover:bg-slate-50">';
        html += '<td class="py-2 pr-4 font-semibold text-slate-700">' + s.full_name + '</td>';
        html += '<td class="py-2 pr-4"><span class="px-2 py-0.5 bg-sky-50 text-sky-700 rounded-full text-xs font-semibold">' + s.role + '</span></td>';
        html += '<td class="py-2 pr-4 text-slate-400 font-mono text-xs truncate max-w-xs">' + s.endpoint_short + '…</td>';
        html += '<td class="py-2 text-slate-500 text-xs">' + (s.updated_at || s.created_at) + '</td>';
        html += '</tr>';
    });
    html += '</tbody></table></div>';
    html += '<p class="text-xs text-slate-400 mt-3">' + data.subscriptions.length + ' active subscription(s)</p>';
    container.innerHTML = html;
}

async function triggerMorningRoute() {
    const btn = document.getElementById('morningBtn');
    const resultEl = document.getElementById('morningResult');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Running…';

    try {
        const res  = await fetch(BASE + '/api/push_morning_route.php?token=' + encodeURIComponent(MORNING_TOKEN));
        const data = await res.json();
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-sunrise-fill"></i> Run Now';
        if (data.ok) {
            const msg = '✓ Sent to ' + data.sent + ' MA(s), skipped ' + data.skipped + ' (opted out), date=' + data.date;
            showResult(resultEl, msg, true);
        } else {
            showResult(resultEl, '✗ ' + (data.error || 'Unknown error'), false);
        }
    } catch (e) {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-sunrise-fill"></i> Run Now';
        showResult(resultEl, '✗ Network error — try again', false);
    }
}

// Auto-load subscriptions on page load
document.addEventListener('DOMContentLoaded', loadSubs);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
