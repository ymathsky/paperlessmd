<?php
/**
 * Centralized mailer helper.
 *
 * Uses PHPMailer + SMTP.  Constants are defined in config.php / config.local.php:
 *   MAIL_HOST, MAIL_USER, MAIL_PASS, MAIL_PORT, MAIL_FROM, MAIL_FROM_NAME
 *
 * Usage:
 *   sendMail('to@example.com', 'Subject', '<p>HTML body</p>');
 *   sendMail(['a@x.com', 'b@x.com'], 'Subject', '<p>HTML</p>');
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailException;

require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

/**
 * Send an HTML email.
 *
 * @param string|string[] $to      One or more recipient email addresses.
 * @param string          $subject Subject line.
 * @param string          $html    Full HTML body (plain-text alt is auto-stripped).
 * @param array           $extra   Optional: ['replyTo' => 'addr', 'cc' => ['a@b.com']]
 * @return bool  true on success, false on failure (never throws).
 */
function sendMail($to, string $subject, string $html, array $extra = []): bool
{
    // Skip silently when SMTP credentials are not configured
    if (!defined('MAIL_HOST') || MAIL_HOST === '') {
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USER;
        $mail->Password   = MAIL_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // port 465 SSL
        $mail->Port       = MAIL_PORT;
        $mail->CharSet    = 'UTF-8';

        // From
        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);

        // Reply-To
        if (!empty($extra['replyTo'])) {
            $mail->addReplyTo($extra['replyTo']);
        }

        // Recipients
        foreach ((array)$to as $addr) {
            $mail->addAddress(trim($addr));
        }
        foreach ((array)($extra['cc'] ?? []) as $addr) {
            $mail->addCC(trim($addr));
        }

        // Content
        $mail->isHTML(true);
        $mail->Subject = '[' . APP_NAME . '] ' . $subject;
        $mail->Body    = _mailWrap($subject, $html);
        $mail->AltBody = strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $html));

        $mail->send();
        return true;
    } catch (MailException $e) {
        error_log('[PaperlessMD mailer] ' . $e->getMessage());
        return false;
    }
}

/**
 * Wrap content in a consistent branded HTML email shell.
 */
function _mailWrap(string $subject, string $body): string
{
    $appName  = defined('APP_NAME')      ? APP_NAME      : 'PaperlessMD';
    $practice = defined('PRACTICE_NAME') ? PRACTICE_NAME : '';
    $baseUrl  = defined('BASE_URL')      ? BASE_URL       : '';
    $year     = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$subject}</title>
<style>
  body{margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;color:#334155}
  .wrap{max-width:560px;margin:32px auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)}
  .hdr{background:linear-gradient(135deg,#1e3a8a,#1d4ed8);padding:28px 32px;text-align:center}
  .hdr h1{margin:0;color:#fff;font-size:18px;font-weight:700;letter-spacing:-.3px}
  .hdr p{margin:4px 0 0;color:#bfdbfe;font-size:13px}
  .body{padding:32px}
  .body p{margin:0 0 14px;line-height:1.65;font-size:14px}
  .btn{display:inline-block;margin:6px 0 18px;padding:11px 24px;background:#1d4ed8;color:#fff !important;
       border-radius:10px;text-decoration:none;font-size:14px;font-weight:600}
  .meta{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:14px 16px;margin:16px 0;font-size:13px}
  .meta dt{font-weight:600;color:#64748b;text-transform:uppercase;font-size:10px;letter-spacing:.6px;margin-top:8px}
  .meta dt:first-child{margin-top:0}
  .meta dd{margin:2px 0 0;color:#1e293b}
  .ftr{padding:20px 32px;border-top:1px solid #f1f5f9;text-align:center;font-size:11px;color:#94a3b8}
</style>
</head>
<body>
<div class="wrap">
  <div class="hdr">
    <h1>{$appName}</h1>
    <p>{$practice}</p>
  </div>
  <div class="body">
    {$body}
  </div>
  <div class="ftr">
    &copy; {$year} {$appName} &mdash; {$practice}<br>
    This is an automated message. Do not reply directly to this email.
  </div>
</div>
</body>
</html>
HTML;
}
