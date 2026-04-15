<?php
require_once __DIR__ . '/config.php';

function requireLogin(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
    if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: ' . BASE_URL . '/index.php?msg=timeout');
        exit;
    }
    $_SESSION['last_active'] = time();
}

function requireAdmin(): void
{
    requireLogin();
    if (($_SESSION['role'] ?? '') !== 'admin') {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(?string $token = null): bool
{
    $token = $token ?? ($_POST['csrf_token'] ?? '');
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function isAdmin(): bool
{
    return ($_SESSION['role'] ?? '') === 'admin';
}

function isBilling(): bool
{
    return ($_SESSION['role'] ?? '') === 'billing';
}

function isMa(): bool
{
    return ($_SESSION['role'] ?? '') === 'ma';
}

/**
 * Redirects billing users away — use on any clinical-only page/action.
 */
function requireNotBilling(): void
{
    requireLogin();
    if (($_SESSION['role'] ?? '') === 'billing') {
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    }
}

/**
 * Returns true if the current user can access clinical data
 * (vitals, chief complaint, medications, wound photos).
 */
function canAccessClinical(): bool
{
    return in_array($_SESSION['role'] ?? '', ['admin', 'ma'], true);
}

/**
 * For JSON API endpoints — emits 403 JSON and exits for billing users.
 */
function requireNotBillingApi(): void
{
    requireLogin();
    if (($_SESSION['role'] ?? '') === 'billing') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Access denied.']);
        exit;
    }
}
