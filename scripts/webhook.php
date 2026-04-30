<?php
/**
 * scripts/webhook.php
 *
 * GitHub webhook receiver — triggers deploy.sh on every push to main.
 *
 * Setup in GitHub:
 *   Repo → Settings → Webhooks → Add webhook
 *   Payload URL:  https://yourdomain.com/scripts/webhook.php
 *   Content type: application/json
 *   Secret:       (set a strong secret and put it in WEBHOOK_SECRET below)
 *   Events:       Just the push event
 *
 * On the server, make webhook.php accessible but protect deploy.sh:
 *   The web server should NOT serve scripts/deploy.sh directly.
 *   Add to your Apache vhost or .htaccess:
 *     <FilesMatch "\.sh$">
 *         Require all denied
 *     </FilesMatch>
 */

// ── Config ────────────────────────────────────────────────────────────────────
define('WEBHOOK_SECRET', 'REPLACE_WITH_YOUR_GITHUB_WEBHOOK_SECRET');
define('DEPLOY_SCRIPT',  '/usr/local/bin/paperlessmd-deploy');
define('DEPLOY_LOG',     '/var/log/paperlessmd-deploy.log');
define('ALLOWED_BRANCH', 'refs/heads/main');

header('Content-Type: text/plain');

// ── Verify GitHub signature ───────────────────────────────────────────────────
$rawBody = file_get_contents('php://input');
$sig     = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

if (!$sig) {
    http_response_code(403);
    exit('Missing signature');
}

$expected = 'sha256=' . hash_hmac('sha256', $rawBody, WEBHOOK_SECRET);
if (!hash_equals($expected, $sig)) {
    http_response_code(403);
    exit('Invalid signature');
}

// ── Only deploy on pushes to main ────────────────────────────────────────────
$payload = json_decode($rawBody, true);
$ref     = $payload['ref'] ?? '';

if ($ref !== ALLOWED_BRANCH) {
    http_response_code(200);
    exit('Ignored branch: ' . htmlspecialchars($ref, ENT_QUOTES, 'UTF-8'));
}

// ── Trigger deploy asynchronously ────────────────────────────────────────────
// Run as www-data (same user Apache runs as, which has write access to app dir)
$cmd = 'sudo -n /usr/local/bin/paperlessmd-deploy >> ' . escapeshellarg(DEPLOY_LOG) . ' 2>&1 &';
exec($cmd);

http_response_code(200);
echo 'Deploy triggered at ' . date('Y-m-d H:i:s');
