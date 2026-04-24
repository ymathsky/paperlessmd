<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

header('Content-Type: application/json');

$uid   = (int)$_SESSION['user_id'];
$notifs = [];

// 1. Unread messages
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM messages m
        WHERE (m.to_user_id = ? OR m.to_user_id IS NULL)
          AND m.from_user_id != ?
          AND NOT EXISTS (
              SELECT 1 FROM message_reads mr
              WHERE mr.message_id = m.id AND mr.user_id = ?
          )
    ");
    $stmt->execute([$uid, $uid, $uid]);
    $n = (int)$stmt->fetchColumn();
    if ($n > 0) $notifs[] = [
        'type'  => 'message',
        'icon'  => 'bi-chat-dots-fill',
        'color' => 'emerald',
        'title' => $n . ' unread message' . ($n !== 1 ? 's' : ''),
        'body'  => 'New messages from your team',
        'link'  => '/messages.php',
        'count' => $n,
    ];
} catch (PDOException $e) {}

if (!isBilling()) {
    // 2. Pending billing upload
    try {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM form_submissions WHERE status = 'signed'")->fetchColumn();
        if ($n > 0) $notifs[] = [
            'type'  => 'upload',
            'icon'  => 'bi-cloud-arrow-up-fill',
            'color' => 'amber',
            'title' => $n . ' form' . ($n !== 1 ? 's' : '') . ' pending billing upload',
            'body'  => 'Signed forms ready to be uploaded',
            'link'  => '/patients.php?filter=pending',
            'count' => $n,
        ];
    } catch (PDOException $e) {}

    // 3. E-sign queue
    try {
        $n = (int)$pdo->query("
            SELECT COUNT(*) FROM form_submissions
            WHERE status IN ('signed','uploaded')
              AND (provider_signature IS NULL OR provider_signature = '')
        ")->fetchColumn();
        if ($n > 0) $notifs[] = [
            'type'  => 'esign',
            'icon'  => 'bi-pen-fill',
            'color' => 'violet',
            'title' => $n . ' form' . ($n !== 1 ? 's' : '') . ' awaiting provider signature',
            'body'  => 'Forms in the e-sign queue need signing',
            'link'  => '/esign_queue.php',
            'count' => $n,
        ];
    } catch (PDOException $e) {}

    // 4. Old drafts (> 24 hours)
    try {
        $n = (int)$pdo->query("
            SELECT COUNT(*) FROM form_submissions
            WHERE status = 'draft'
              AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ")->fetchColumn();
        if ($n > 0) $notifs[] = [
            'type'  => 'draft',
            'icon'  => 'bi-clock-history',
            'color' => 'red',
            'title' => $n . ' draft form' . ($n !== 1 ? 's' : '') . ' over 24 hours old',
            'body'  => 'Old drafts may need attention or cleanup',
            'link'  => '/patients.php',
            'count' => $n,
        ];
    } catch (PDOException $e) {}
}

echo json_encode([
    'notifications' => $notifs,
    'total'         => count($notifs),
]);
