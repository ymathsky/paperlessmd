<?php
/**
 * Practice Fusion FHIR R4 Configuration
 * ─────────────────────────────────────
 * Fill in your credentials from developer.practicefusion.com
 * NEVER commit this file to a public repository.
 */

// ── OAuth 2.0 Credentials (from PF Developer Portal) ────────────────
define('PF_CLIENT_ID',     'YOUR_CLIENT_ID_HERE');
define('PF_CLIENT_SECRET', 'YOUR_CLIENT_SECRET_HERE');

// ── Practice Fusion FHIR Base URL ───────────────────────────────────
// Sandbox (for testing — use this first):
define('PF_FHIR_BASE',   'https://qa-api.practicefusion.com/ehr/r4');
define('PF_TOKEN_URL',   'https://qa-api.practicefusion.com/ehr/oauth2/token');

// Production (uncomment once live and comment out sandbox above):
// define('PF_FHIR_BASE', 'https://api.practicefusion.com/ehr/r4');
// define('PF_TOKEN_URL', 'https://api.practicefusion.com/ehr/oauth2/token');

// ── Token Cache File (stored outside webroot is ideal) ───────────────
define('PF_TOKEN_CACHE', dirname(__DIR__) . '/storage/pf_token.json');

// ── Practice GUID (shown in PF Settings → Practice Information) ──────
define('PF_PRACTICE_FUSION_GUID', 'YOUR_PRACTICE_GUID_HERE');
