<?php
// Temporarily enable errors to find the 500 cause on notes tab
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Fake session so requireLogin passes
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';

$_GET['id'] = '1';
$_GET['tab'] = 'notes';

// Capture output including errors
ob_start();
try {
    include '/var/www/paperlessmd/patient_view.php';
} catch (Throwable $e) {
    ob_end_clean();
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    exit;
}
$out = ob_get_clean();

// Look for errors in output
if (preg_match('/(Fatal error|Warning|Notice|Parse error|Uncaught|SQLSTATE)[^\n<]*/i', $out, $m)) {
    echo "ERROR FOUND: " . $m[0] . "\n";
} else {
    echo "Page rendered OK, length=" . strlen($out) . "\n";
}
