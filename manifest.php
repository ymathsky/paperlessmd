<?php
require_once __DIR__ . '/includes/config.php';
header('Content-Type: application/manifest+json');
$base = BASE_URL; // '' on production, '/pd' on local
echo json_encode([
    'name'             => APP_NAME . ' — Beyond Wound Care',
    'short_name'       => 'PaperlessMD',
    'description'      => 'Offline-capable paperless forms for mobile medical visits',
    'start_url'        => $base . '/dashboard.php',
    'id'               => $base . '/',
    'scope'            => $base . '/',
    'display'          => 'standalone',
    'orientation'      => 'portrait-primary',
    'background_color' => '#172554',
    'theme_color'      => '#1e3a8a',
    'categories'       => ['medical', 'health'],
    'icons'            => [
        [
            'src'     => $base . '/assets/img/pwa-icon.svg',
            'sizes'   => 'any',
            'type'    => 'image/svg+xml',
            'purpose' => 'any',
        ],
        [
            'src'     => $base . '/assets/img/pwa-icon.svg',
            'sizes'   => 'any',
            'type'    => 'image/svg+xml',
            'purpose' => 'maskable',
        ],
    ],
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
