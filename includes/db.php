<?php
require_once __DIR__ . '/config.php';

try {
    // DB_HOST may be "host:port" (Railway) or plain "localhost"
    $dbHostStr = DB_HOST;
    $dbPort    = '3306';
    if (strpos($dbHostStr, ':') !== false) {
        list($dbHostStr, $dbPort) = explode(':', $dbHostStr, 2);
    }
    $pdo = new PDO(
        'mysql:host=' . $dbHostStr . ';port=' . $dbPort . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;padding:2rem;color:#c00">
         <h2>Database Error</h2>
         <p>Could not connect. Run <a href="' . BASE_URL . '/install.php">install.php</a> first.</p>
         </div>');
}

/* ── Apply timezone from settings table (falls back to America/Chicago) ── */
try {
    $tzVal = $pdo->query("SELECT `value` FROM settings WHERE `key` = 'timezone' LIMIT 1")
                 ->fetchColumn();
    $tz = ($tzVal && @timezone_open($tzVal)) ? $tzVal : 'America/Chicago';
} catch (PDOException $e) {
    $tz = 'America/Chicago'; // table may not exist yet (before migration)
}
date_default_timezone_set($tz);
define('APP_TIMEZONE', $tz);
