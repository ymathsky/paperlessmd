<?php
/**
 * Creates push_subscriptions table and generates VAPID key pair.
 * Added automatically to deploy.sh migration order.
 */
header('Content-Type: text/plain');
try {
    require_once __DIR__ . '/includes/db.php';
    require_once __DIR__ . '/includes/WebPush.php';

    // ── Table ──────────────────────────────────────────────────────────────
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS push_subscriptions (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            staff_id   INT UNSIGNED NOT NULL,
            endpoint   TEXT         NOT NULL,
            p256dh     VARCHAR(512) NOT NULL,
            auth       VARCHAR(255) NOT NULL,
            created_at DATETIME     DEFAULT NOW(),
            updated_at DATETIME     DEFAULT NOW() ON UPDATE NOW(),
            INDEX  idx_staff (staff_id),
            UNIQUE KEY uniq_ep (endpoint(191))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    echo "OK: push_subscriptions table ready.\n";

    // ── VAPID keys ─────────────────────────────────────────────────────────
    $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = 'vapid_private' LIMIT 1");
    $stmt->execute();
    if ($stmt->fetchColumn()) {
        echo "OK: VAPID keys already exist — skipping.\n";
    } else {
        $keys = webpush_generate_vapid_keys();
        $ins  = $pdo->prepare(
            "INSERT INTO settings (`key`, `value`, `label`)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)"
        );
        $ins->execute(['vapid_public',  $keys['public'],  'VAPID Public Key (base64url)']);
        $ins->execute(['vapid_private', $keys['private'], 'VAPID Private Key (PEM)']);
        $ins->execute(['vapid_contact', 'admin@paperlessmd.com', 'VAPID Contact Email']);
        echo "OK: VAPID key pair generated and stored.\n";
        echo "    Public key: " . $keys['public'] . "\n";
    }
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
