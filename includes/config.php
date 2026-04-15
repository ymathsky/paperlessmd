<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'pd_paperless');
define('DB_USER', 'root');
define('DB_PASS', '');
define('APP_NAME', 'PaperlessMD');
define('PRACTICE_NAME', 'Beyond Wound Care Inc.');
define('PRACTICE_ADDRESS', '1340 Remington RD, STE P, Schaumburg, IL 60173');
define('PRACTICE_PHONE', '847-873-8693');
define('PRACTICE_FAX', '847-873-8486');
define('PRACTICE_EMAIL', 'Support@beyondwoundcare.com');
define('BASE_URL', '/pd');
define('UPLOAD_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'photos' . DIRECTORY_SEPARATOR);
define('SESSION_TIMEOUT', 7200); // 2 hours

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
