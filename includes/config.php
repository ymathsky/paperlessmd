<?php
// ── Environment detection ────────────────────────────────────────────────────
// Priority: local override file → Railway env vars → production hard-coded values

if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
}

// ── Railway / generic env-var support ───────────────────────────────────────
// Railway MySQL plugin injects MYSQL_HOST, MYSQL_PORT, MYSQL_DATABASE, MYSQL_USER, MYSQL_PASSWORD
// BASE_URL must be set manually as a Railway environment variable.
if (getenv('MYSQL_HOST') !== false) {
    define('DB_HOST', getenv('MYSQL_HOST') . ':' . (getenv('MYSQL_PORT') ?: '3306'));
    define('DB_NAME', getenv('MYSQL_DATABASE'));
    define('DB_USER', getenv('MYSQL_USER'));
    define('DB_PASS', getenv('MYSQL_PASSWORD'));
    define('BASE_URL', rtrim(getenv('BASE_URL') ?: '', '/'));

    define('MAIL_HOST',      getenv('MAIL_HOST')      ?: '');
    define('MAIL_USER',      getenv('MAIL_USER')      ?: '');
    define('MAIL_PASS',      getenv('MAIL_PASS')      ?: '');
    define('MAIL_PORT',      (int)(getenv('MAIL_PORT') ?: 587));
    define('MAIL_FROM',      getenv('MAIL_FROM')      ?: '');
    define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: 'PaperlessMD');
} else {
    // ── Production settings (cPanel) ─────────────────────────────────────────
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'mdoffic1_pd');
    define('DB_USER', 'mdoffic1_pduser');
    define('DB_PASS', 'Ym@thsky12101992');
    define('BASE_URL', '');

    define('MAIL_HOST',      'docs.md-officesupport.com');
    define('MAIL_USER',      'support@docs.md-officesupport.com');
    define('MAIL_PASS',      'Ym@thsky12101992');
    define('MAIL_PORT',      465);
    define('MAIL_FROM',      'support@docs.md-officesupport.com');
    define('MAIL_FROM_NAME', 'PaperlessMD — Beyond Wound Care Inc.');
}

// ── Shared constants (same on all environments) ──────────────────────────────
define('APP_NAME',          'PaperlessMD');
define('PRACTICE_NAME',     'Beyond Wound Care Inc.');
define('PRACTICE_ADDRESS',  '1340 Remington RD, STE P, Schaumburg, IL 60173');
define('PRACTICE_PHONE',    '847-873-8693');
define('PRACTICE_FAX',      '847-873-8486');
define('PRACTICE_EMAIL',    'Support@beyondwoundcare.com');
define('UPLOAD_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'photos' . DIRECTORY_SEPARATOR);
define('SESSION_TIMEOUT', 7200); // 2 hours
define('GEMINI_API_KEY',  ''); // legacy fallback

// Load API keys from gitignored file (create this file on each server, never commit it)
if (file_exists(__DIR__ . '/config.keys.php')) {
    require_once __DIR__ . '/config.keys.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function h(string $val): string
{
    return htmlspecialchars($val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
