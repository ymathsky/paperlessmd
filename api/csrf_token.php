<?php
/**
 * api/csrf_token.php
 * Returns a fresh CSRF token for offline form sync.
 * Called by offline.js just before POSTing queued forms.
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');
header('Cache-Control: no-store');

echo json_encode(['csrf' => csrfToken()]);
