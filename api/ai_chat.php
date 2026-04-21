<?php
/**
 * api/ai_chat.php — AI chat history persistence
 *
 * Actions (POST JSON):
 *   history — fetch last 200 messages for the current user
 *   save    — append one message (role: user|bot, content: string)
 *   clear   — delete all chat history for the current user
 */

error_reporting(0);
ini_set('display_errors', '0');

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

$me    = (int)$_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if (!verifyCsrf($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Auto-create table on first use
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ai_chat_history (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id    INT NOT NULL,
            role       ENUM('user','bot') NOT NULL,
            content    TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_created (user_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (PDOException $e) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Database error', '_debug' => $e->getMessage()]);
    exit;
}

$action = $input['action'] ?? ($_GET['action'] ?? '');

try {
    switch ($action) {

        case 'history':
            $stmt = $pdo->prepare("
                SELECT role, content, created_at
                FROM   ai_chat_history
                WHERE  user_id = ?
                ORDER  BY created_at ASC
                LIMIT  200
            ");
            $stmt->execute([$me]);
            echo json_encode(['ok' => true, 'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'save':
            $role    = $input['role']    ?? '';
            $content = trim($input['content'] ?? '');
            if (!in_array($role, ['user', 'bot'], true) || $content === '') {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Invalid role or empty content']);
                break;
            }
            // Trim content to 10 000 chars to prevent abuse
            $content = mb_substr($content, 0, 10000);
            $stmt = $pdo->prepare("
                INSERT INTO ai_chat_history (user_id, role, content) VALUES (?, ?, ?)
            ");
            $stmt->execute([$me, $role, $content]);
            echo json_encode(['ok' => true]);
            break;

        case 'clear':
            $stmt = $pdo->prepare("DELETE FROM ai_chat_history WHERE user_id = ?");
            $stmt->execute([$me]);
            echo json_encode(['ok' => true]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    }
} catch (PDOException $e) {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'Database error', '_debug' => $e->getMessage()]);
}
