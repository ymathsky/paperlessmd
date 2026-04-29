<?php
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && ($err['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR))) {
        while (ob_get_level() > 0) ob_end_clean();
        if (!headers_sent()) {
            http_response_code(500);
            header("Content-Type: application/json; charset=utf-8");
        }
        echo json_encode(["ok"=>false, "error"=>"Fatal PHP error", "_debug"=>$err["message"]]);
    }
});

ini_set("display_errors", "0");
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

ob_start();
require_once __DIR__ . "/../includes/auth.php";
require_once __DIR__ . "/../includes/db.php";
require_once __DIR__ . "/../includes/audit.php";
ob_end_clean();

requireLogin();

$me = (int)$_SESSION["user_id"];
$action = $_GET["action"] ?? ($_POST["action"] ?? "");

if ($action === "download") {
    $id = (int)$_GET["file_id"];
    $stmt = $pdo->prepare("SELECT a.*, m.to_user_id, m.from_user_id FROM message_attachments a JOIN messages m ON m.id = a.message_id WHERE a.id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetch();
    if (!$file) { http_response_code(404); exit("Not found."); }
    
    $isAdmin = ($_SESSION["role"] ?? "") === "admin";
    $canSee = $isAdmin || $file["from_user_id"] == $me || $file["to_user_id"] === null || $file["to_user_id"] == $me;
    if (!$canSee) { http_response_code(403); exit("Denied."); }

    $path = __DIR__ . "/../uploads/messages/" . $file["stored_name"];
    if (!file_exists($path)) { http_response_code(404); exit("Disk false."); }

    header("Content-Type: " . ($file["mime_type"] ?: "application/octet-stream"));
    header("Content-Disposition: inline; filename=\"" . addslashes($file["original_name"]) . "\"");
    header("Content-Length: " . filesize($path));
    readfile($path);
    exit;
}

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

try {
    if ($action === "sync") {
        $activeChat = $_GET["active_chat"] ?? ""; 
        $lastMsgId = (int)($_GET["last_msg_id"] ?? 0);
        
        $response = ["ok" => true, "chats" => [], "messages" => []];
        
        $sql = "SELECT u.id, u.full_name, u.role,
               (SELECT m.body FROM messages m WHERE ((m.from_user_id = ? AND m.to_user_id = u.id) OR (m.from_user_id = u.id AND m.to_user_id = ?)) ORDER BY m.created_at DESC LIMIT 1) as latest_body,
               (SELECT m.created_at FROM messages m WHERE ((m.from_user_id = ? AND m.to_user_id = u.id) OR (m.from_user_id = u.id AND m.to_user_id = ?)) ORDER BY m.created_at DESC LIMIT 1) as latest_time,
               (SELECT COUNT(*) FROM messages m WHERE m.from_user_id = u.id AND m.to_user_id = ? AND NOT EXISTS (SELECT 1 FROM message_reads mr WHERE mr.message_id = m.id AND mr.user_id = ?)) as unreads
            FROM staff u WHERE u.active = 1 AND u.id != ? ORDER BY u.full_name ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$me, $me, $me, $me, $me, $me, $me]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $sqlAll = "SELECT (SELECT m.body FROM messages m WHERE m.to_user_id IS NULL ORDER BY m.created_at DESC LIMIT 1) as latest_body,
               (SELECT m.created_at FROM messages m WHERE m.to_user_id IS NULL ORDER BY m.created_at DESC LIMIT 1) as latest_time,
               (SELECT COUNT(*) FROM messages m WHERE m.to_user_id IS NULL AND m.from_user_id != ? AND NOT EXISTS (SELECT 1 FROM message_reads mr WHERE mr.message_id = m.id AND mr.user_id = ?)) as unreads";
        $stmtAll = $pdo->prepare($sqlAll);
        $stmtAll->execute([$me, $me]);
        $allStaff = $stmtAll->fetch(PDO::FETCH_ASSOC);
        
        $chats = [];
        $chats[] = ["id" => "all", "name" => "All Staff", "role" => "Broadcast", "latest_body" => $allStaff["latest_body"], "latest_time" => $allStaff["latest_time"], "unreads" => (int)$allStaff["unreads"]];
        
        foreach ($users as $u) {
            $chats[] = ["id" => (string)$u["id"], "name" => $u["full_name"], "role" => $u["role"], "latest_body" => $u["latest_body"], "latest_time" => $u["latest_time"], "unreads" => (int)$u["unreads"]];
        }
        
        usort($chats, function($a, $b) {
            $tA = empty($a["latest_time"]) ? 0 : strtotime($a["latest_time"]);
            $tB = empty($b["latest_time"]) ? 0 : strtotime($b["latest_time"]);
            return $tB <=> $tA;
        });
        
        $response["chats"] = $chats;
        
        if ($activeChat !== "") {
            $chatParams = [];
            if ($activeChat === "all") {
                $where = "m.to_user_id IS NULL";
                $pdo->prepare("INSERT IGNORE INTO message_reads (message_id, user_id) SELECT id, ? FROM messages m WHERE m.to_user_id IS NULL AND m.from_user_id != ?")->execute([$me, $me]);
            } else {
                $otherId = (int)$activeChat;
                $where = "((m.from_user_id = ? AND m.to_user_id = ?) OR (m.from_user_id = ? AND m.to_user_id = ?))";
                $chatParams = [$me, $otherId, $otherId, $me];
                $pdo->prepare("INSERT IGNORE INTO message_reads (message_id, user_id) SELECT id, ? FROM messages m WHERE m.from_user_id = ? AND m.to_user_id = ?")->execute([$me, $otherId, $me]);
            }

            if ($lastMsgId > 0) {
                $msgSql = "SELECT m.*, u.full_name as from_name FROM messages m LEFT JOIN staff u ON u.id = m.from_user_id WHERE $where AND m.id > ? ORDER BY m.created_at ASC";
                $chatParams[] = $lastMsgId;
            } else {
                $msgSql = "SELECT * FROM (SELECT m.*, u.full_name as from_name FROM messages m LEFT JOIN staff u ON u.id = m.from_user_id WHERE $where ORDER BY m.created_at DESC LIMIT 50) sub ORDER BY created_at ASC";
            }
            
            $stmt = $pdo->prepare($msgSql);
            $stmt->execute($chatParams);
            $msgs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if ($msgs) {
                $msgIds = array_column($msgs, "id");
                $inQuery = implode(",", array_fill(0, count($msgIds), "?"));
                $attStmt = $pdo->prepare("SELECT * FROM message_attachments WHERE message_id IN ($inQuery)");
                $attStmt->execute($msgIds);
                $atts = $attStmt->fetchAll(PDO::FETCH_ASSOC);
                $attsByMsg = [];
                foreach ($atts as $a) $attsByMsg[$a["message_id"]][] = $a;
                
                foreach ($msgs as &$m) {
                    $m["attachments"] = $attsByMsg[$m["id"]] ?? [];
                }
            }
            $response["messages"] = $msgs;
        }
        echo json_encode($response); exit;
    }
    
    if ($action === "send") {
        $to = $_POST["to"] ?? "";
        $body = trim($_POST["body"] ?? "");
        
        if (empty($body) && empty($_FILES["attachments"]["name"][0])) { echo json_encode(["ok"=>false, "error"=>"Empty msg"]); exit; }
        
        $toId = ($to === "all") ? null : (int)$to;
        $stmt = $pdo->prepare("INSERT INTO messages (from_user_id, to_user_id, subject, body, created_at) VALUES (?, ?, '', ?, NOW())");
        $stmt->execute([$me, $toId, $body]);
        $msgId = $pdo->lastInsertId();
        
        $pdo->prepare("INSERT INTO message_reads (message_id, user_id) VALUES (?, ?)")->execute([$msgId, $me]);

        // Email notification to recipient(s)
        require_once __DIR__ . '/../includes/mailer.php';
        require_once __DIR__ . '/../includes/notifications.php';
        if ($toId === null) {
            notifyBroadcastMessage($pdo, $me, $body);
        } else {
            notifyNewMessage($pdo, $toId, $me, $body);
        }
        
        if (!empty($_FILES["attachments"]["name"][0])) {
            $dir = __DIR__ . "/../uploads/messages";
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            foreach ($_FILES["attachments"]["name"] as $idx => $name) {
                if ($_FILES["attachments"]["error"][$idx] === UPLOAD_ERR_OK) {
                    $tmp = $_FILES["attachments"]["tmp_name"][$idx];
                    $size = $_FILES["attachments"]["size"][$idx];
                    $mime = $_FILES["attachments"]["type"][$idx] ?: "application/octet-stream";
                    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                    $stored = date("YmdHis")."_".bin2hex(random_bytes(4)).($ext?".".$ext:"");
                    if (move_uploaded_file($tmp, "$dir/$stored")) {
                        $s = $pdo->prepare("INSERT INTO message_attachments (message_id, original_name, stored_name, file_size, mime_type) VALUES (?,?,?,?,?)");
                        $s->execute([$msgId, $name, $stored, $size, $mime]);
                    }
                }
            }
        }
        echo json_encode(["ok"=>true, "msg_id"=>$msgId]); exit;
    }
} catch (PDOException $e) { echo json_encode(["ok"=>false, "error"=>"DB Error"]); }
