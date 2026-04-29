<?php
/**
 * Notification triggers for PaperlessMD.
 *
 * Each function collects the needed recipients from the DB,
 * builds an email, and delegates to sendMail().
 *
 * Requires:
 *   - $pdo  (global PDO instance from db.php)
 *   - includes/mailer.php  (already required before calling these)
 */

// ── Helper: load admins + providers email list ────────────────────────────────
function _notifGetEmails(PDO $pdo, array $roles): array
{
    if (empty($roles)) return [];
    $placeholders = implode(',', array_fill(0, count($roles), '?'));
    $stmt = $pdo->prepare(
        "SELECT email FROM staff WHERE role IN ($placeholders) AND active = 1 AND email IS NOT NULL AND email != ''"
    );
    $stmt->execute($roles);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// ── Helper: get one staff email ───────────────────────────────────────────────
function _notifGetEmail(PDO $pdo, int $userId): string
{
    $stmt = $pdo->prepare("SELECT email FROM staff WHERE id = ? AND active = 1 LIMIT 1");
    $stmt->execute([$userId]);
    return (string)($stmt->fetchColumn() ?: '');
}

// ── Helper: patient full name ─────────────────────────────────────────────────
function _notifPatientName(PDO $pdo, int $patientId): string
{
    $stmt = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) FROM patients WHERE id = ? LIMIT 1");
    $stmt->execute([$patientId]);
    return (string)($stmt->fetchColumn() ?: 'Unknown patient');
}

// ── Helper: staff full name ───────────────────────────────────────────────────
function _notifStaffName(PDO $pdo, int $userId): string
{
    $stmt = $pdo->prepare("SELECT full_name FROM staff WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    return (string)($stmt->fetchColumn() ?: 'Staff');
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Form signed by MA → notify all admins + providers to countersign
// ─────────────────────────────────────────────────────────────────────────────
function notifyFormSigned(PDO $pdo, int $submissionId, int $patientId, string $formType, int $maId): void
{
    $recipients = _notifGetEmails($pdo, ['admin', 'provider']);
    if (empty($recipients)) return;

    $patientName = _notifPatientName($pdo, $patientId);
    $maName      = _notifStaffName($pdo, $maId);
    $formLabel   = ucwords(str_replace('_', ' ', $formType));
    $link        = (defined('BASE_URL') ? BASE_URL : '') . '/view_document.php?id=' . $submissionId;
    $queueLink   = (defined('BASE_URL') ? BASE_URL : '') . '/esign_queue.php';

    $html = <<<HTML
<p>A new form has been submitted and is awaiting provider countersignature.</p>
<dl class="meta">
  <dt>Patient</dt><dd>{$patientName}</dd>
  <dt>Form</dt><dd>{$formLabel}</dd>
  <dt>Submitted by</dt><dd>{$maName}</dd>
  <dt>Submitted at</dt><dd>{$_ts}</dd>
</dl>
<p>
  <a href="{$link}" class="btn">Review &amp; Sign</a>
</p>
<p style="font-size:13px;color:#64748b">
  Or view the full <a href="{$queueLink}" style="color:#1d4ed8">E-Sign Queue</a>.
</p>
HTML;

    // Replace placeholder
    $ts   = date('F j, Y \a\t g:i a T');
    $html = str_replace('{$_ts}', $ts, $html);

    sendMail($recipients, "New form awaiting signature — {$patientName}", $html);
}

// ─────────────────────────────────────────────────────────────────────────────
// 2. Provider countersigns → notify the MA who submitted the form
// ─────────────────────────────────────────────────────────────────────────────
function notifyProviderSigned(PDO $pdo, int $submissionId, string $providerName): void
{
    // Get MA + patient info from the submission
    $stmt = $pdo->prepare("
        SELECT fs.ma_id, fs.form_type, fs.patient_id,
               CONCAT(p.first_name,' ',p.last_name) AS patient_name
        FROM form_submissions fs
        LEFT JOIN patients p ON p.id = fs.patient_id
        WHERE fs.id = ?
        LIMIT 1
    ");
    $stmt->execute([$submissionId]);
    $row = $stmt->fetch();
    if (!$row) return;

    // Notify MA + all admins
    $recipients = [];
    $maEmail = _notifGetEmail($pdo, (int)$row['ma_id']);
    if ($maEmail) $recipients[] = $maEmail;
    foreach (_notifGetEmails($pdo, ['admin']) as $e) {
        if (!in_array($e, $recipients, true)) $recipients[] = $e;
    }
    if (empty($recipients)) return;

    $formLabel   = ucwords(str_replace('_', ' ', $row['form_type']));
    $patientName = $row['patient_name'];
    $link        = (defined('BASE_URL') ? BASE_URL : '') . '/view_document.php?id=' . $submissionId;
    $ts          = date('F j, Y \a\t g:i a T');

    $html = <<<HTML
<p>A form has been countersigned by the provider and is now complete.</p>
<dl class="meta">
  <dt>Patient</dt><dd>{$patientName}</dd>
  <dt>Form</dt><dd>{$formLabel}</dd>
  <dt>Signed by</dt><dd>{$providerName}</dd>
  <dt>Signed at</dt><dd>{$ts}</dd>
</dl>
<p><a href="{$link}" class="btn">View Document</a></p>
HTML;

    sendMail($recipients, "Form countersigned — {$patientName}", $html);
}

// ─────────────────────────────────────────────────────────────────────────────
// 3. New internal message → notify recipient by email
// ─────────────────────────────────────────────────────────────────────────────
function notifyNewMessage(PDO $pdo, int $toUserId, int $fromUserId, string $messageBody): void
{
    $recipientEmail = _notifGetEmail($pdo, $toUserId);
    if (!$recipientEmail) return;

    $fromName    = _notifStaffName($pdo, $fromUserId);
    $recipName   = _notifStaffName($pdo, $toUserId);
    $messagesUrl = (defined('BASE_URL') ? BASE_URL : '') . '/messages.php';
    $preview     = mb_substr(strip_tags($messageBody), 0, 160);
    if (mb_strlen($messageBody) > 160) $preview .= '…';
    $ts          = date('F j, Y \a\t g:i a T');

    $html = <<<HTML
<p>Hi {$recipName},</p>
<p>You have a new internal message from <strong>{$fromName}</strong>.</p>
<dl class="meta">
  <dt>From</dt><dd>{$fromName}</dd>
  <dt>Received</dt><dd>{$ts}</dd>
  <dt>Preview</dt><dd style="font-style:italic">&ldquo;{$preview}&rdquo;</dd>
</dl>
<p><a href="{$messagesUrl}" class="btn">Open Messages</a></p>
HTML;

    sendMail($recipientEmail, "New message from {$fromName}", $html);
}

// ─────────────────────────────────────────────────────────────────────────────
// 4. Broadcast message (to_user_id IS NULL) → notify all active staff except sender
// ─────────────────────────────────────────────────────────────────────────────
function notifyBroadcastMessage(PDO $pdo, int $fromUserId, string $messageBody): void
{
    $stmt = $pdo->prepare(
        "SELECT email FROM staff WHERE active = 1 AND id != ? AND email IS NOT NULL AND email != ''"
    );
    $stmt->execute([$fromUserId]);
    $recipients = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (empty($recipients)) return;

    $fromName    = _notifStaffName($pdo, $fromUserId);
    $messagesUrl = (defined('BASE_URL') ? BASE_URL : '') . '/messages.php';
    $preview     = mb_substr(strip_tags($messageBody), 0, 160);
    if (mb_strlen($messageBody) > 160) $preview .= '…';
    $ts          = date('F j, Y \a\t g:i a T');

    $html = <<<HTML
<p>A broadcast message has been sent to all staff by <strong>{$fromName}</strong>.</p>
<dl class="meta">
  <dt>From</dt><dd>{$fromName}</dd>
  <dt>Sent</dt><dd>{$ts}</dd>
  <dt>Preview</dt><dd style="font-style:italic">&ldquo;{$preview}&rdquo;</dd>
</dl>
<p><a href="{$messagesUrl}" class="btn">Open Messages</a></p>
HTML;

    sendMail($recipients, "Broadcast message from {$fromName}", $html);
}

// ─────────────────────────────────────────────────────────────────────────────
// 5. Account locked → notify admins
// ─────────────────────────────────────────────────────────────────────────────
function notifyAccountLocked(PDO $pdo, string $lockedName, string $lockedUsername, string $lockedRole, string $lockUntil, string $ip): void
{
    $recipients = _notifGetEmails($pdo, ['admin']);
    if (empty($recipients)) return;

    $ts       = date('F j, Y \a\t g:i a T');
    $usersUrl = (defined('BASE_URL') ? BASE_URL : '') . '/admin/users.php';

    $html = <<<HTML
<p>A staff account has been automatically locked after too many consecutive failed login attempts.</p>
<dl class="meta">
  <dt>Account</dt><dd>{$lockedName} ({$lockedUsername})</dd>
  <dt>Role</dt><dd>{$lockedRole}</dd>
  <dt>Locked at</dt><dd>{$ts}</dd>
  <dt>Unlocks at</dt><dd>{$lockUntil}</dd>
  <dt>IP address</dt><dd>{$ip}</dd>
</dl>
<p><a href="{$usersUrl}" class="btn">Manage Staff Accounts</a></p>
HTML;

    sendMail($recipients, "Account locked — {$lockedName}", $html);
}
