import subprocess, sys

path = '/var/www/paperlessmd/manifest.php'
r = subprocess.run(['cat', path], capture_output=True, text=True)
content = r.stdout

old = """    'icons'            => [
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
    ],"""

new = """    'icons'            => [
        [
            'src'     => $base . '/assets/img/pwa-icon-192.png',
            'sizes'   => '192x192',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src'     => $base . '/assets/img/pwa-icon-512.png',
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'any',
        ],
        [
            'src'     => $base . '/assets/img/pwa-icon-512.png',
            'sizes'   => '512x512',
            'type'    => 'image/png',
            'purpose' => 'maskable',
        ],
    ],"""

if old not in content:
    print('ERR: pattern not found'); sys.exit(1)

content = content.replace(old, new, 1)
r2 = subprocess.run(['tee', path], input=content, capture_output=True, text=True)
if r2.returncode != 0:
    print('ERR:', r2.stderr); sys.exit(1)

print('OK: manifest.php updated to use logo PNG icons')
