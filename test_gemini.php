<?php
// Temporary diagnostic script — delete after use
require_once __DIR__ . '/includes/config.local.php';
// Inline helpers (no auth middleware) ─────────────────────────────────────────
function b64url(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function getVertexAccessToken(array $sa): string {
    $cacheFile = sys_get_temp_dir() . '/pd_vertex_token_' . substr(md5($sa['client_email']), 0, 8) . '.json';
    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (!empty($cached['token']) && ($cached['expires_at'] ?? 0) > time() + 60) {
            return $cached['token'];
        }
    }
    $now    = time();
    $header = b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $claims = b64url(json_encode([
        'iss'   => $sa['client_email'],
        'scope' => 'https://www.googleapis.com/auth/cloud-platform',
        'aud'   => 'https://oauth2.googleapis.com/token',
        'exp'   => $now + 3600,
        'iat'   => $now,
    ]));
    $toSign = $header . '.' . $claims;
    $pk = openssl_pkey_get_private($sa['private_key']);
    if (!$pk) { throw new \RuntimeException('Could not load service account private key.'); }
    openssl_sign($toSign, $sig, $pk, OPENSSL_ALGO_SHA256);
    $jwt = $toSign . '.' . b64url($sig);
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS     => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt]),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'], CURLOPT_TIMEOUT => 10,
    ]);
    $raw  = curl_exec($ch); $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); $cerr = curl_error($ch); curl_close($ch);
    if ($cerr) { throw new \RuntimeException('Token request cURL error: ' . $cerr); }
    $resp = json_decode($raw, true);
    if ($code !== 200 || empty($resp['access_token'])) {
        throw new \RuntimeException($resp['error_description'] ?? $resp['error'] ?? 'Unknown token error (HTTP ' . $code . ')');
    }
    file_put_contents($cacheFile, json_encode(['token' => $resp['access_token'], 'expires_at' => $now + ($resp['expires_in'] ?? 3600)]));
    return $resp['access_token'];
}
// ─────────────────────────────────────────────────────────────────────────────
$model = 'gemini-2.0-flash';

if (defined('VERTEX_API_KEY') && VERTEX_API_KEY !== '') {
    echo "Using Vertex AI Express key\n\n";
    $url  = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . VERTEX_API_KEY;
    $hdrs = ['Content-Type: application/json'];
} else {
    $saKeyPath = defined('VERTEX_SA_KEY_PATH') ? VERTEX_SA_KEY_PATH : '';
    if (!$saKeyPath || !file_exists($saKeyPath)) {
        echo "No VERTEX_API_KEY or SA key found. Check config.\n";
        exit(1);
    }
    $sa = json_decode(file_get_contents($saKeyPath), true);
    echo "Service account: " . ($sa['client_email'] ?? '?') . "\n";
    echo "Project:         " . ($sa['project_id']    ?? '?') . "\n\n";
    try {
        $token = getVertexAccessToken($sa);
        echo "Access token obtained: " . substr($token, 0, 20) . "...\n\n";
    } catch (\Throwable $e) {
        echo "TOKEN ERROR: " . $e->getMessage() . "\n";
        exit(1);
    }
    $project  = $sa['project_id'];
    $location = defined('VERTEX_LOCATION') ? VERTEX_LOCATION : 'us-central1';
    $url  = "https://{$location}-aiplatform.googleapis.com/v1/projects/{$project}/locations/{$location}/publishers/google/models/{$model}:generateContent";
    $hdrs = ['Content-Type: application/json', 'Authorization: Bearer ' . $token];
}

$payload = json_encode([
    'contents' => [['role' => 'user', 'parts' => [['text' => 'Say the word OK and nothing else.']]]],
]);

$ch = curl_init($url);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_HTTPHEADER => $hdrs, CURLOPT_TIMEOUT => 15]);
$raw  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr = curl_error($ch);
curl_close($ch);

echo "HTTP $code\n";
if ($cerr) { echo "cURL error: $cerr\n"; exit; }

$r = json_decode($raw, true);
if (isset($r['error'])) {
    echo "Error:   " . ($r['error']['code']    ?? '?') . " " . ($r['error']['status'] ?? '') . "\n";
    echo "Message: " . ($r['error']['message'] ?? '?') . "\n";
    foreach ($r['error']['details'] ?? [] as $d) {
        echo "Detail:  " . json_encode($d) . "\n";
    }
} else {
    $text = $r['candidates'][0]['content']['parts'][0]['text'] ?? '(empty)';
    echo "SUCCESS: $text\n";
}
