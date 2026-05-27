<?php
require __DIR__ . '/includes/config.php';
require __DIR__ . '/includes/db.php';

// Simulate logged-in admin
$_SESSION['user_id'] = 1;
$me = 1;

try {
    $sql = "SELECT u.id, u.full_name, u.role,
           (SELECT m.body FROM messages m WHERE ((m.from_user_id = ? AND m.to_user_id = u.id) OR (m.from_user_id = u.id AND m.to_user_id = ?)) ORDER BY m.created_at DESC LIMIT 1) as latest_body,
           (SELECT m.created_at FROM messages m WHERE ((m.from_user_id = ? AND m.to_user_id = u.id) OR (m.from_user_id = u.id AND m.to_user_id = ?)) ORDER BY m.created_at DESC LIMIT 1) as latest_time,
           (SELECT COUNT(*) FROM messages m WHERE m.from_user_id = u.id AND m.to_user_id = ? AND NOT EXISTS (SELECT 1 FROM message_reads mr WHERE mr.message_id = m.id AND mr.user_id = ?)) as unreads
        FROM staff u WHERE u.active = 1 AND u.id != ? ORDER BY u.full_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$me,$me,$me,$me,$me,$me,$me]);
    $users = $stmt->fetchAll();
    echo "OK - found " . count($users) . " users\n";
    echo json_encode(["ok"=>true,"chats_count"=>count($users)]) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
