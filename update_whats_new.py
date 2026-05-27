#!/usr/bin/env python3
with open('/var/www/paperlessmd/whats_new.php', 'r') as f:
    c = f.read()

old = """$releases = [
    [
        'version' => 'v1.9',
        'date'    => 'April 30, 2026',
        'label'   => 'Latest',
        'color'   => 'emerald',"""

new = """$releases = [
    [
        'version' => 'v2.0',
        'date'    => 'May 1, 2026',
        'label'   => 'Latest',
        'color'   => 'emerald',
        'items'   => [
            ['icon' => 'bi-cloud-arrow-up-fill',   'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Production Deployment — DigitalOcean',        'desc' => 'PaperlessMD is now live on a dedicated DigitalOcean Droplet (Ubuntu 24.04 LTS) in the NYC1 region, served by Apache 2 with PHP 8.2 and MySQL 8 — fully replacing the previous local/shared-host environment.'],
            ['icon' => 'bi-globe2',                'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Custom Domain — ecpaperlessmd.com',           'desc' => 'The application is now accessible at https://ecpaperlessmd.com. DNS is managed through DigitalOcean nameservers; the A record points directly to the Droplet. All internal BASE_URL references have been updated accordingly.'],
            ['icon' => 'bi-shield-lock-fill',      'tag' => 'New',     'tag_color' => 'blue',  'title' => 'HTTPS / TLS via Let\'s Encrypt',              'desc' => 'A free TLS certificate was issued by Let\'s Encrypt via Certbot and is auto-renewing every 90 days. All HTTP traffic is permanently redirected to HTTPS. Apache is configured with recommended TLS hardening headers.'],
            ['icon' => 'bi-chat-dots-fill',        'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'Messages Table Rebuilt',                      'desc' => 'The messages table was recreated with the correct schema (from_user_id / to_user_id). The missing subject column was added. Messages now load, send, and display correctly on the live server.'],
            ['icon' => 'bi-clock-history',         'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'Timezone-Aware Timestamps',                   'desc' => 'All UTC timestamps stored in the database are now correctly converted to the clinic\'s configured timezone before display. Message timestamps, MA location "last seen" labels, and session timers all show correct local clinic time.'],
            ['icon' => 'bi-activity',              'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'Patient Tab 500 Errors Resolved',             'desc' => 'Three missing database tables were created on production: patient_diagnoses (ICD-10 codes), care_notes (care coordination thread), and soap_notes (SOAP clinical notes, with columns renamed to match the app schema). All patient record tabs now load without error.'],
            ['icon' => 'bi-geo-alt-fill',          'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'MA Location Monitor — last_active_at',        'desc' => 'The last_active_at column was added to the staff table. MA Location Monitor no longer shows "Never logged in" for all staff, and the color-coded online/away/offline status dots update correctly.'],
            ['icon' => 'bi-sticky-fill',           'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Team Notes Visible to MAs',                   'desc' => 'Medical Assistants can now read Team Notes posted by admins on the dashboard. MAs have read-only access — the compose and delete controls remain admin-only, keeping MAs informed without granting write access.'],
        ],
    ],
    [
        'version' => 'v1.9',
        'date'    => 'April 30, 2026',
        'label'   => null,
        'color'   => 'blue',"""

if old in c:
    c = c.replace(old, new, 1)
    with open('/var/www/paperlessmd/whats_new.php', 'w') as f:
        f.write(c)
    print("OK: v2.0 release added")
else:
    print("MISS: could not find target string")
    # debug: show first 300 chars of releases array
    idx = c.find('$releases')
    print(repr(c[idx:idx+300]))
