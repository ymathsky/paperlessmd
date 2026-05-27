<?php
require_once '/var/www/paperlessmd/includes/config.php';

echo "MAIL_HOST: " . MAIL_HOST . "\n";
echo "MAIL_USER: " . MAIL_USER . "\n";
echo "MAIL_PORT: " . MAIL_PORT . "\n";
echo "MAIL_FROM: " . MAIL_FROM . "\n\n";

// Test TCP connection
$fp = @fsockopen(MAIL_HOST, MAIL_PORT, $errno, $errstr, 10);
if ($fp) {
    echo "TCP connect to " . MAIL_HOST . ":" . MAIL_PORT . " -> OK\n";
    fclose($fp);
} else {
    echo "TCP connect FAILED: $errstr ($errno)\n";
}

// Full PHPMailer debug send
require_once '/var/www/paperlessmd/includes/phpmailer/Exception.php';
require_once '/var/www/paperlessmd/includes/phpmailer/PHPMailer.php';
require_once '/var/www/paperlessmd/includes/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

$mail = new PHPMailer(true);
try {
    $mail->SMTPDebug   = 2;
    $mail->Debugoutput = 'echo';
    $mail->isSMTP();
    $mail->Host        = MAIL_HOST;
    $mail->SMTPAuth    = true;
    $mail->Username    = MAIL_USER;
    $mail->Password    = MAIL_PASS;
    $mail->SMTPSecure  = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port        = MAIL_PORT;
    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addAddress(MAIL_FROM);
    $mail->Subject = 'SMTP Test';
    $mail->Body    = 'Test message from PaperlessMD server.';
    $mail->send();
    echo "\n\nRESULT: SUCCESS\n";
} catch (MailException $e) {
    echo "\n\nRESULT FAILED: " . $e->getMessage() . "\n";
}
