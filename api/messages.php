<?php
// ── Error safety net: catches fatal errors try/catch can't reach ──────────────
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && ($err['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR))) {
        while (ob_get_level() > 0) ob_end_clean();
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'ok'     => false,
            'error'  => 'Fatal PHP error',
            '_debug' => $err['message'] . ' in ' . basename($err['file']) . ':' . $err['line'],
        ]);
    }
});

ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

// Buffer includes so any stray die()/warning output is captured
ob_start();
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/audit.php';
ob_end_clean(); // discard any buffered output from includes

requireLogin();

$me     = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Download served as binary — must bypass JSON header
if ($action === 'download') {
    handleDownload();
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    switch ($action) {
        case 'list':          handleList();         break;
        case 'thread':        handleThread();       break;
        case 'send':          handleSend();         break;
        case 'users':         handleUsers();        break;
        case 'unread_count':  handleUnreadCount();  break;
        case 'delete':        handleDelete();       break;
        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (PDOException $e) {
    while (ob_get_level()) ob_end_clean();
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Database error', '_debug' => $e->getMessage()]);
} catch (\Throwable $e) {
    while (ob_get_level()) ob_end_clean();
    error_log('messages API: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Server error', '_debug' => $e->getMessage()]);
}

// ─────────────────────────────────────────────────────────────────────────────
// LIST  — thread roots visible to current user, newest-activity first
// 4 simple queries merged in PHP — no complex GROUP BY, no named params
// ─────────────────────────────────────────────────────────────────────────────
function handleList(): void
{
    global $pdo, $me;

    // ── 1. Root messages visible to me ────────────────────────────────────────
    $stmt = $pdo->prepare("
        SELECT m.id, m.subject, m.from_user_id, m.to_user_id, m.created_at,
               sf.full_name                        AS from_name,
               sf.role                             AS from_role,
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
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) {
        echo json_encode(['ok' => true, 'conversations' => []]);
        return;
    }

    $rootIds = array_map('intval', array_column($rows, 'id'));
    $ph      = implode(',', array_fill(0, count($rootIds), '?'));

    // ── 2. Last activity per thread (GROUP BY only the grouped column) ─────────
    $actStmt = $pdo->prepare("
        SELECT COALESCE(parent_id, id) AS root_id,
               MAX(created_at)         AS last_activity
        FROM   messages
        WHERE  COALESCE(parent_id, id) IN ($ph)
        GROUP  BY COALESCE(parent_id, id)
    ");
    $actStmt->execute($rootIds);
    $actMap = [];
    foreach ($actStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $actMap[(int)$r['root_id']] = $r['last_activity'];
    }

    // ── 3. Latest body preview per thread (ORDER BY + PHP first-seen dedup) ───
    $bodyStmt = $pdo->prepare("
        SELECT COALESCE(parent_id, id) AS root_id,
               LEFT(body, 120)         AS snippet,
               created_at
        FROM   messages
        WHERE  COALESCE(parent_id, id) IN ($ph)
        ORDER  BY created_at DESC
    ");
    $bodyStmt->execute($rootIds);
    $bodyMap = [];
    foreach ($bodyStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $rid = (int)$r['root_id'];
        if (!isset($bodyMap[$rid])) $bodyMap[$rid] = $r['snippet'];
    }

    // ── 4. Unread count per thread (GROUP BY only the grouped column) ─────────
    $unreadStmt = $pdo->prepare("
        SELECT COALESCE(m.parent_id, m.id) AS root_id,
               COUNT(*)                    AS unread_count
        FROM   messages m
        LEFT   JOIN message_reads mr
                    ON  mr.message_id = m.id
                    AND mr.user_id    = ?
        WHERE  m.from_user_id != ?
          AND  mr.id IS NULL
          AND  COALESCE(m.parent_id, m.id) IN ($ph)
        GROUP  BY COALESCE(m.parent_id, m.id)
    ");
    $unreadStmt->execute(array_merge([$me, $me], $rootIds));
    $unreadMap = [];
    foreach ($unreadStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $unreadMap[(int)$r['root_id']] = (int)$r['unread_count'];
    }

    // ── 5. Merge and sort by last activity ────────────────────────────────────
    foreach ($rows as &$row) {
        $rid                  = (int)$row['id'];
        $row['last_activity'] = $actMap[$rid] ?? $row['created_at'];
        $row['last_body']     = $bodyMap[$rid] ?? '';
        $row['unread_count']  = $unreadMap[$rid] ?? 0;
    }
    unset($row);

    usort($rows, fn($a, $b) => strcmp($b['last_activity'], $a['last_activity']));

    echo json_encode(['ok' => true, 'conversations' => $rows]);
}

// ─────────────────────────────────────────────────────────────────────────────
// THREAD  — full message thread (root + replies), marks messages read
// ─────────────────────────────────────────────────────────────────────────────
function handleThread(): void
{
    global $pdo, $me;

    $rootId = (int)($_GET['id'] ?? 0);
    if (!$rootId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing id']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ? AND parent_id IS NULL");
    $stmt->execute([$rootId]);
    $root = $stmt->fetch();

    if (!$root) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Not found']);
        return;
    }

    // Access: I sent it, it was sent to me, broadcast, or admin
    $canSee = (int)$root['from_user_id'] === $me
           || (int)$root['to_user_id']   === $me
           || $root['to_user_id']        === null
           || isAdmin();

    if (!$canSee) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden']);
        return;
    }

    // All messages in thread
    $stmt = $pdo->prepare("
        SELECT m.*, s.full_name AS from_name, s.role AS from_role
        FROM   messages m
        JOIN   staff s ON s.id = m.from_user_id
        WHERE  m.id = ? OR m.parent_id = ?
        ORDER  BY m.created_at ASC
    ");
    $stmt->execute([$rootId, $rootId]);
    $messages = $stmt->fetchAll();

    if (!$messages) {
        echo json_encode(['ok' => true, 'messages' => [], 'root' => $root]);
        return;
    }

    // Attachments
    $ids = array_column($messages, 'id');
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $attStmt = $pdo->prepare(
        "SELECT * FROM message_attachments WHERE message_id IN ($ph) ORDER BY created_at ASC"
    );
    $attStmt->execute($ids);
    $attMap = [];
    foreach ($attStmt->fetchAll() as $a) {
        $attMap[(int)$a['message_id']][] = $a;
    }

    // Mark read + attach data
    $markRead = $pdo->prepare(
        "INSERT IGNORE INTO message_reads (message_id, user_id) VALUES (?, ?)"
    );
    foreach ($messages as &$msg) {
        $msg['attachments'] = $attMap[(int)$msg['id']] ?? [];
        if ((int)$msg['from_user_id'] !== $me) {
            try { $markRead->execute([$msg['id'], $me]); } catch (PDOException $e) { /* ignore */ }
        }
    }
    unset($msg);

    echo json_encode(['ok' => true, 'messages' => $messages, 'root' => $root]);
}

// ─────────────────────────────────────────────────────────────────────────────
// SEND  — new root message or reply; optional file attachment
// ─────────────────────────────────────────────────────────────────────────────
function handleSend(): void
{
    global $pdo, $me;

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        return;
    }

    $body     = trim($_POST['body']      ?? '');
    $subject  = trim($_POST['subject']   ?? '');
    $toRaw    = trim($_POST['to']        ?? '');
    $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    if ($body === '') {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'Message body is required']);
        return;
    }

    // Resolve to_user_id (null = all-staff broadcast)
    $toUserId = null;
    if ($toRaw !== '' && $toRaw !== 'all') {
        $toUserId = (int)$toRaw;
        $chk = $pdo->prepare("SELECT id FROM staff WHERE id = ? AND active = 1");
        $chk->execute([$toUserId]);
        if (!$chk->fetch()) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'error' => 'Invalid recipient']);
            return;
        }
    }

    // Ensure parent_id points to root (not a reply of a reply)
    if ($parentId) {
        $par = $pdo->prepare("SELECT parent_id FROM messages WHERE id = ?");
        $par->execute([$parentId]);
        $parRow = $par->fetch();
        if ($parRow && $parRow['parent_id']) {
            $parentId = (int)$parRow['parent_id'];
        }
    }

    // Insert message
    $ins = $pdo->prepare(
        "INSERT INTO messages (from_user_id, to_user_id, subject, body, parent_id) VALUES (?, ?, ?, ?, ?)"
    );
    $ins->execute([$me, $toUserId, $subject, $body, $parentId]);
    $msgId = (int)$pdo->lastInsertId();

    // Mark read by sender immediately
    try {
        $pdo->prepare("INSERT IGNORE INTO message_reads (message_id, user_id) VALUES (?, ?)")
            ->execute([$msgId, $me]);
    } catch (PDOException $e) { /* ignore */ }

    // Optional file attachment
    if (!empty($_FILES['file'])) {
        $fileErr = $_FILES['file']['error'];
        if ($fileErr !== UPLOAD_ERR_OK) {
            $errMsg = match($fileErr) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File too large. Max allowed by server: ' . ini_get('upload_max_filesize'),
                UPLOAD_ERR_PARTIAL   => 'File upload was interrupted.',
                UPLOAD_ERR_NO_TMP_DIR => 'Server has no temp directory for uploads.',
                UPLOAD_ERR_CANT_WRITE => 'Server cannot write to disk.',
                UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
                default => 'Upload error code: ' . $fileErr,
            };
            echo json_encode(['ok' => false, 'error' => $errMsg]);
            return;
        }

        $file      = $_FILES['file'];
        $maxBytes  = 25 * 1024 * 1024; // 25 MB
        $uploadDir = __DIR__ . '/../uploads/message_files';

        // Ensure upload directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
            file_put_contents($uploadDir . '/.htaccess',
                "Options -Indexes\n<FilesMatch \"\\.php$\">\n  Deny from all\n</FilesMatch>\n");
        }

        if ($file['size'] > $maxBytes) {
            echo json_encode(['ok' => false, 'error' => 'File exceeds 25 MB limit.']);
            return;
        }

        if (!extension_loaded('fileinfo')) {
            echo json_encode(['ok' => false, 'error' => 'Server missing fileinfo extension.']);
            return;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'text/csv',
            'application/zip', 'application/x-zip-compressed',
        ];

        if (!in_array($mime, $allowed, true)) {
            echo json_encode(['ok' => false, 'error' => 'File type not allowed: ' . $mime]);
            return;
        }

        $ext    = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $stored = 'msg_' . $msgId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest   = $uploadDir . '/' . $stored;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            echo json_encode(['ok' => false, 'error' => 'Failed to save attachment. Directory: ' . $uploadDir . ' Writable: ' . (is_writable($uploadDir)?'yes':'no')]);
            return;
        }

        $pdo->prepare("
            INSERT INTO message_attachments
                (message_id, original_name, stored_name, file_size, mime_type)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([$msgId, $file['name'], $stored, $file['size'], $mime]);
    }

    auditLog($pdo, 'message_send', 'message', $msgId, $subject ?: '(reply)');

    echo json_encode(['ok' => true, 'message_id' => $msgId]);
}

// ─────────────────────────────────────────────────────────────────────────────
// USERS  — all active staff for compose recipient picker
// ─────────────────────────────────────────────────────────────────────────────
function handleUsers(): void
{
    global $pdo;
    $stmt = $pdo->query(
        "SELECT id, full_name, role FROM staff WHERE active = 1 ORDER BY full_name ASC"
    );
    echo json_encode(['ok' => true, 'users' => $stmt->fetchAll()]);
}

// ─────────────────────────────────────────────────────────────────────────────
// UNREAD_COUNT  — for the nav badge (polled periodically)
// ─────────────────────────────────────────────────────────────────────────────
function handleUnreadCount(): void
{
    global $pdo, $me;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM messages m
        WHERE  (m.to_user_id = ? OR m.to_user_id IS NULL)
          AND  m.from_user_id != ?
          AND  NOT EXISTS (
              SELECT 1 FROM message_reads mr
              WHERE mr.message_id = m.id AND mr.user_id = ?
          )
    ");
    $stmt->execute([$me, $me, $me]);
    echo json_encode(['ok' => true, 'count' => (int)$stmt->fetchColumn()]);
}

// ─────────────────────────────────────────────────────────────────────────────
// DELETE  — sender or admin can delete; deletes whole thread if root
// ─────────────────────────────────────────────────────────────────────────────
function handleDelete(): void
{
    global $pdo, $me;

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $csrf  = $input['csrf_token'] ?? ($_POST['csrf_token'] ?? '');

    if (!verifyCsrf($csrf)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
        return;
    }

    $msgId = (int)($input['message_id'] ?? 0);
    if (!$msgId) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing message_id']);
        return;
    }

    $stmt = $pdo->prepare("SELECT * FROM messages WHERE id = ?");
    $stmt->execute([$msgId]);
    $msg = $stmt->fetch();

    if (!$msg) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Not found']);
        return;
    }

    if ((int)$msg['from_user_id'] !== $me && !isAdmin()) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Forbidden']);
        return;
    }

    // If deleting root, collect all replies too
    $ids = [$msgId];
    if (!$msg['parent_id']) {
        $replies = $pdo->prepare("SELECT id FROM messages WHERE parent_id = ?");
        $replies->execute([$msgId]);
        foreach ($replies->fetchAll() as $r) {
            $ids[] = (int)$r['id'];
        }
    }

    foreach ($ids as $id) {
        $atts = $pdo->prepare(
            "SELECT stored_name FROM message_attachments WHERE message_id = ?"
        );
        $atts->execute([$id]);
        foreach ($atts->fetchAll() as $a) {
            $f = __DIR__ . '/../uploads/message_files/' . basename($a['stored_name']);
            if (file_exists($f)) {
                unlink($f);
            }
        }
        $pdo->prepare("DELETE FROM message_attachments WHERE message_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM message_reads       WHERE message_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM messages             WHERE id = ?")->execute([$id]);
    }

    auditLog($pdo, 'message_delete', 'message', $msgId, 'deleted');

    echo json_encode(['ok' => true]);
}

// ─────────────────────────────────────────────────────────────────────────────
// DOWNLOAD  — serve attachment as binary download
// ─────────────────────────────────────────────────────────────────────────────
function handleDownload(): void
{
    global $pdo, $me;

    $attId = (int)($_GET['id'] ?? 0);
    if (!$attId) {
        http_response_code(400);
        exit('Bad request');
    }

    $stmt = $pdo->prepare("
        SELECT ma.*, m.from_user_id, m.to_user_id
        FROM   message_attachments ma
        JOIN   messages m ON m.id = ma.message_id
        WHERE  ma.id = ?
    ");
    $stmt->execute([$attId]);
    $att = $stmt->fetch();

    if (!$att) {
        http_response_code(404);
        exit('Not found');
    }

    $canAccess = (int)$att['from_user_id'] === $me
              || (int)$att['to_user_id']   === $me
              || $att['to_user_id']        === null
              || isAdmin();

    if (!$canAccess) {
        http_response_code(403);
        exit('Forbidden');
    }

    $file = __DIR__ . '/../uploads/message_files/' . basename($att['stored_name']);
    if (!file_exists($file)) {
        http_response_code(404);
        exit('File not found');
    }

    header('Content-Type: '        . $att['mime_type'], true);
    header('Content-Disposition: attachment; filename="' . rawurlencode($att['original_name']) . '"');
    header('Content-Length: '      . filesize($file));
    header('Cache-Control: private, no-cache');
    readfile($file);
}
