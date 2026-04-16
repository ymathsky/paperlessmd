<?php
/**
 * msg_debug.php — Temporary diagnostic page for messaging API 500 errors.
 * DELETE THIS FILE after the issue is resolved.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireAdmin(); // admin only

$me = (int)$_SESSION['user_id'];
$results = [];

function test(string $label, callable $fn): array {
    $start = microtime(true);
    try {
        $out = $fn();
        return ['label' => $label, 'ok' => true, 'result' => $out, 'ms' => round((microtime(true)-$start)*1000)];
    } catch (\Throwable $e) {
        return ['label' => $label, 'ok' => false, 'error' => $e->getMessage(), 'ms' => round((microtime(true)-$start)*1000)];
    }
}

// 1. PHP version
$results[] = test('PHP version', function() {
    return PHP_VERSION;
});

// 2. MySQL version
$results[] = test('MySQL version', function() use ($pdo) {
    return $pdo->query('SELECT VERSION()')->fetchColumn();
});

// 3. messages table exists
$results[] = test('messages table exists', function() use ($pdo) {
    $pdo->query('SELECT 1 FROM messages LIMIT 1');
    return 'yes';
});

// 4. message_reads table exists
$results[] = test('message_reads table exists', function() use ($pdo) {
    $pdo->query('SELECT 1 FROM message_reads LIMIT 1');
    return 'yes';
});

// 5. message_attachments table exists
$results[] = test('message_attachments table exists', function() use ($pdo) {
    $pdo->query('SELECT 1 FROM message_attachments LIMIT 1');
    return 'yes';
});

// 6. staff table has "role" column
$results[] = test('staff.role column exists', function() use ($pdo) {
    $r = $pdo->query("SHOW COLUMNS FROM staff LIKE 'role'")->fetch();
    return $r ? 'yes — type: '.$r['Type'] : 'MISSING';
});

// 7. uploads/message_files dir writable
$results[] = test('uploads/message_files/ writable', function() {
    $dir = __DIR__ . '/uploads/message_files';
    return is_dir($dir) ? (is_writable($dir) ? 'yes' : 'NOT writable') : 'MISSING';
});

// 8. Count rows in messages
$results[] = test('messages row count', function() use ($pdo) {
    return $pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();
});

// 9. Step 1 query — root messages
$results[] = test('Query 1: root messages for current user', function() use ($pdo, $me) {
    $stmt = $pdo->prepare("
        SELECT m.id, m.subject, m.from_user_id, m.to_user_id, m.created_at,
               sf.full_name AS from_name,
               sf.role      AS from_role,
               COALESCE(st.full_name, 'All Staff') AS to_name
        FROM   messages m
        JOIN   staff sf ON sf.id = m.from_user_id
        LEFT   JOIN staff st ON st.id = m.to_user_id
        WHERE  m.parent_id IS NULL
          AND  (m.from_user_id = ? OR m.to_user_id = ? OR m.to_user_id IS NULL)
        ORDER  BY m.created_at DESC
        LIMIT  200
    ");
    $stmt->execute([$me, $me]);
    $rows = $stmt->fetchAll();
    return count($rows) . ' rows';
});

// 10. Step 2 query — last activity (only if we have root IDs)
$results[] = test('Query 2: last activity subquery', function() use ($pdo, $me) {
    $stmt = $pdo->prepare("
        SELECT m.id FROM messages m
        WHERE m.parent_id IS NULL
          AND (m.from_user_id = ? OR m.to_user_id = ? OR m.to_user_id IS NULL)
        LIMIT 200
    ");
    $stmt->execute([$me, $me]);
    $rootIds = array_map('intval', array_column($stmt->fetchAll(), 'id'));
    if (empty($rootIds)) return '0 roots — skipped';

    $ph = implode(',', array_fill(0, count($rootIds), '?'));
    $actStmt = $pdo->prepare("
        SELECT latest.root_id, latest.last_activity, LEFT(m2.body, 120) AS last_body
        FROM (
            SELECT COALESCE(parent_id, id) AS root_id, MAX(created_at) AS last_activity
            FROM   messages
            WHERE  COALESCE(parent_id, id) IN ($ph)
            GROUP  BY COALESCE(parent_id, id)
        ) AS latest
        JOIN messages m2
             ON  COALESCE(m2.parent_id, m2.id) = latest.root_id
             AND m2.created_at = latest.last_activity
        GROUP BY latest.root_id
    ");
    $actStmt->execute($rootIds);
    $rows = $actStmt->fetchAll();
    return count($rows) . ' rows (from ' . count($rootIds) . ' roots)';
});

// 11. Step 3 query — unread count
$results[] = test('Query 3: unread count subquery', function() use ($pdo, $me) {
    $stmt = $pdo->prepare("
        SELECT m.id FROM messages m
        WHERE m.parent_id IS NULL
          AND (m.from_user_id = ? OR m.to_user_id = ? OR m.to_user_id IS NULL)
        LIMIT 200
    ");
    $stmt->execute([$me, $me]);
    $rootIds = array_map('intval', array_column($stmt->fetchAll(), 'id'));
    if (empty($rootIds)) return '0 roots — skipped';

    $ph = implode(',', array_fill(0, count($rootIds), '?'));
    $unreadStmt = $pdo->prepare("
        SELECT COALESCE(m.parent_id, m.id) AS root_id, COUNT(*) AS unread_count
        FROM   messages m
        LEFT   JOIN message_reads mr ON mr.message_id = m.id AND mr.user_id = ?
        WHERE  m.from_user_id != ?
          AND  mr.id IS NULL
          AND  COALESCE(m.parent_id, m.id) IN ($ph)
        GROUP  BY COALESCE(m.parent_id, m.id)
    ");
    $unreadStmt->execute(array_merge([$me, $me], $rootIds));
    $rows = $unreadStmt->fetchAll();
    return count($rows) . ' rows';
});

// 12. Full action=list simulation
$results[] = test('Full handleList() simulation', function() use ($pdo, $me) {
    ob_start();
    // inline copy of handleList
    $stmt = $pdo->prepare("
        SELECT m.id, m.subject, m.from_user_id, m.to_user_id, m.created_at,
               sf.full_name AS from_name, sf.role AS from_role,
               COALESCE(st.full_name, 'All Staff') AS to_name
        FROM   messages m
        JOIN   staff sf ON sf.id = m.from_user_id
        LEFT   JOIN staff st ON st.id = m.to_user_id
        WHERE  m.parent_id IS NULL
          AND  (m.from_user_id = ? OR m.to_user_id = ? OR m.to_user_id IS NULL)
        ORDER  BY m.created_at DESC LIMIT 200
    ");
    $stmt->execute([$me, $me]);
    $rows = $stmt->fetchAll();
    ob_end_clean();
    $json = json_encode(['ok' => true, 'conversations' => $rows]);
    return 'JSON length: ' . strlen($json) . ' bytes, ' . count($rows) . ' conversations';
});

$pageTitle = 'MSG Debug';
include __DIR__ . '/includes/header.php';
?>
<div class="max-w-2xl mx-auto py-8 px-4 space-y-3">
    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 bg-red-100 rounded-xl grid place-items-center">
            <i class="bi bi-bug-fill text-red-500 text-lg"></i>
        </div>
        <div>
            <h1 class="text-lg font-bold text-slate-800">Messages API Diagnostics</h1>
            <p class="text-xs text-slate-500">Running as user ID <?= $me ?> — delete this file after fixing</p>
        </div>
    </div>

    <?php foreach ($results as $r): ?>
    <div class="rounded-xl border <?= $r['ok'] ? 'border-emerald-200 bg-emerald-50' : 'border-red-300 bg-red-50' ?> px-4 py-3">
        <div class="flex items-start justify-between gap-2">
            <div class="flex items-center gap-2 min-w-0">
                <i class="bi <?= $r['ok'] ? 'bi-check-circle-fill text-emerald-500' : 'bi-x-circle-fill text-red-500' ?> shrink-0"></i>
                <span class="text-sm font-semibold <?= $r['ok'] ? 'text-emerald-800' : 'text-red-800' ?>"><?= htmlspecialchars($r['label']) ?></span>
            </div>
            <span class="text-[10px] text-slate-400 shrink-0"><?= $r['ms'] ?>ms</span>
        </div>
        <?php if ($r['ok']): ?>
        <p class="text-xs text-emerald-700 mt-1 ml-6 font-mono"><?= htmlspecialchars((string)$r['result']) ?></p>
        <?php else: ?>
        <p class="text-xs text-red-700 mt-1 ml-6 font-mono break-all"><?= htmlspecialchars($r['error']) ?></p>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 mt-4 text-xs text-amber-800">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <strong> Security reminder:</strong> Delete <code>msg_debug.php</code> from production after diagnosing the issue.
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
