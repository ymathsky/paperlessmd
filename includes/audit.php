<?php
/**
 * HIPAA Audit Logging helper.
 * Always fails silently — a logging error must never interrupt clinical workflows.
 *
 * Usage:
 *   auditLog($pdo, 'patient_view', 'patient', $patientId, 'John Smith');
 *   auditLog($pdo, 'login');
 *   auditLog($pdo, 'form_create', 'form', $formId, 'Visit Consent', 'patient_id=5');
 */
function auditLog(
    PDO     $pdo,
    string  $action,
    ?string $targetType  = null,
    ?int    $targetId    = null,
    ?string $targetLabel = null,
    ?string $details     = null
): void {
    try {
        $userId   = isset($_SESSION['user_id'])  ? (int)$_SESSION['user_id']  : null;
        $username = isset($_SESSION['username'])  ? (string)$_SESSION['username'] : null;
        $role     = isset($_SESSION['role'])      ? (string)$_SESSION['role']     : null;
        $ip       = $_SERVER['HTTP_X_FORWARDED_FOR']
                    ?? $_SERVER['REMOTE_ADDR']
                    ?? null;
        // Trim to first IP if comma-list (proxy chain)
        if ($ip) $ip = trim(explode(',', $ip)[0]);

        $stmt = $pdo->prepare("
            INSERT INTO audit_log
                (user_id, username, user_role, action, target_type, target_id, target_label, ip_address, details)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId, $username, $role,
            $action, $targetType, $targetId, $targetLabel,
            $ip, $details,
        ]);
    } catch (Throwable $e) {
        // Never propagate — logging must not break the app
        error_log('[AuditLog] ' . $e->getMessage());
    }
}
