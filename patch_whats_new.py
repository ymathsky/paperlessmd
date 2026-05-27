import subprocess, sys

path = '/var/www/paperlessmd/whats_new.php'
r = subprocess.run(['cat', path], capture_output=True, text=True)
content = r.stdout

old = """    [
        'version' => 'v2.1',
        'date'    => 'May 5, 2026',
        'label'   => 'Latest',
        'color'   => 'emerald',
        'items'   => [
            ['icon' => 'bi-capsule-pill',          'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'Medication Add — HTTP 500 Resolved',          'desc' => 'Fixed a 500 error when adding medications on patient records. The two required tables (patient_medications and medication_history) were missing from production. Both were created with the correct schema — including audit trail columns — via the existing migration script.'],
            ['icon' => 'bi-tag-fill',              'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Per-User Form Signature Title',               'desc' => 'Each staff member can now be assigned a custom signature title that controls the label shown on their signing block on all patient forms. Options include Medical Assistant, Provider, Physician, Physician Assistant, Nurse Practitioner, LPN, Clinical Staff, Administrator, or a free-form custom title.'],
            ['icon' => 'bi-shield-lock-fill',      'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Signature Title — Admin-Only Control',        'desc' => 'The Form Signature Title setting is now managed exclusively by administrators in Admin \\u2192 Manage Staff \\u2192 Edit Staff Member. Individual staff members cannot change their own signature title, ensuring consistent and accurate labeling on all clinical forms.'],
        ],
    ],"""

new = """    [
        'version' => 'v2.2',
        'date'    => 'May 5, 2026',
        'label'   => 'Latest',
        'color'   => 'emerald',
        'items'   => [
            ['icon' => 'bi-file-earmark-check-fill', 'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'Form Save HTTP 500 Resolved',                'desc' => 'Fixed a critical 500 error that prevented all form submissions. The ma_signature column was missing from the form_submissions table — every POST to api/save_form.php crashed with a PDO SQLSTATE[42S22] unknown column error. The column has been added and submissions now complete successfully.'],
            ['icon' => 'bi-hourglass-split',         'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'Stuck "Saving…" Spinner After Submit',       'desc' => 'Form saves were hanging indefinitely due to PHPMailer\'s default 300-second SMTP connection timeout. When the SMTP host is unreachable the entire PHP request blocked, leaving the browser stuck on "Saving\u2026". Added a 5-second connection timeout so SMTP failures fail fast and the redirect to the signed document happens immediately.'],
            ['icon' => 'bi-person-check-fill',       'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'Provider Countersignature Panel Fixed',       'desc' => 'Fixed the provider signature panel on already-signed forms (view_document.php). The JavaScript block that wires up the signature canvas, saved-signature auto-fill, and "Sign manually" override was missing entirely from the signed-status code path. Provider countersigning now works correctly on all submitted forms.'],
            ['icon' => 'bi-building-fill',           'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'CCM Consent — Company Name Updates Correctly','desc' => 'In the New Patient Packet form (Section 4 of 9 — Chronic Care Management Consent), the company name in the agreement text and the Beneficiary Rights section was hardcoded and did not update when switching between Beyond Wound Care Inc. and Visiting Medical Physician Inc. Both instances now use the .co-name-display class and update live with the company selector.'],
            ['icon' => 'bi-image-fill',              'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Custom PaperlessMD App Icon & Favicon',       'desc' => 'Replaced the generic clipboard Bootstrap icon with a custom-designed PaperlessMD logo across all surfaces: browser tab favicon (16×16, 32×32, 48×48 .ico), sidebar brand icon, mobile top bar, Apple touch icon (180×180), and PWA home-screen icon (192×192 and 512×512 PNG). The logo features a navy-to-blue gradient rounded square with a white clipboard, ECG heartbeat line, and a teal MD badge.'],
            ['icon' => 'bi-bell-fill',               'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'Notification Timestamp Warning Fixed',        'desc' => 'Fixed a PHP Warning (Undefined variable $_ts) in notifications.php where the $ts timestamp variable was defined after the heredoc string that referenced it. The email body was rendering an empty timestamp; the variable is now defined before the heredoc.'],
            ['icon' => 'bi-capsule-pill',            'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'Medication Add — HTTP 500 Resolved',          'desc' => 'Fixed a 500 error when adding medications on patient records. The two required tables (patient_medications and medication_history) were missing from production. Both were created with the correct schema — including audit trail columns — via the existing migration script.'],
            ['icon' => 'bi-tag-fill',                'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Per-User Form Signature Title',               'desc' => 'Each staff member can now be assigned a custom signature title that controls the label shown on their signing block on all patient forms. Options include Medical Assistant, Provider, Physician, Physician Assistant, Nurse Practitioner, LPN, Clinical Staff, Administrator, or a free-form custom title.'],
            ['icon' => 'bi-shield-lock-fill',        'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Signature Title — Admin-Only Control',        'desc' => 'The Form Signature Title setting is now managed exclusively by administrators in Admin \u2192 Manage Staff \u2192 Edit Staff Member. Individual staff members cannot change their own signature title, ensuring consistent and accurate labeling on all clinical forms.'],
        ],
    ],"""

if old not in content:
    print('ERR: v2.1 block not found')
    sys.exit(1)

content = content.replace(old, new, 1)
r2 = subprocess.run(['tee', path], input=content, capture_output=True, text=True)
if r2.returncode != 0:
    print('ERR:', r2.stderr); sys.exit(1)

print("OK: What's New updated to v2.2 with all May 5 changes")
