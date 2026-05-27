#!/usr/bin/env python3
"""
update_whats_new_v21.py
Adds v2.1 release entry at top of whats_new.php and demotes v2.0 from 'Latest'.
"""
BASE = '/var/www/paperlessmd'

def read(p):
    return open(p, encoding='utf-8').read()

def write(p, c):
    open(p, 'w', encoding='utf-8').write(c)

path = BASE + '/whats_new.php'
c = read(path)

# Remove 'Latest' label from v2.0
old_v20_label = "'label'   => 'Latest',"
new_v20_label = "'label'   => null,"
assert old_v20_label in c, "FAIL: v2.0 Latest label not found"
c = c.replace(old_v20_label, new_v20_label, 1)
print("✓ v2.0 'Latest' label removed")

# Insert v2.1 entry at the top of $releases array
V21_ENTRY = """    [
        'version' => 'v2.1',
        'date'    => 'May 5, 2026',
        'label'   => 'Latest',
        'color'   => 'emerald',
        'items'   => [
            ['icon' => 'bi-capsule-pill',          'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'Medication Add — HTTP 500 Resolved',          'desc' => 'Fixed a 500 error when adding medications on patient records. The two required tables (patient_medications and medication_history) were missing from production. Both were created with the correct schema — including audit trail columns — via the existing migration script.'],
            ['icon' => 'bi-tag-fill',              'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Per-User Form Signature Title',               'desc' => 'Each staff member can now be assigned a custom signature title that controls the label shown on their signing block on all patient forms. Options include Medical Assistant, Provider, Physician, Physician Assistant, Nurse Practitioner, LPN, Clinical Staff, Administrator, or a free-form custom title.'],
            ['icon' => 'bi-shield-lock-fill',      'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Signature Title — Admin-Only Control',        'desc' => 'The Form Signature Title setting is now managed exclusively by administrators in Admin \\u2192 Manage Staff \\u2192 Edit Staff Member. Individual staff members cannot change their own signature title, ensuring consistent and accurate labeling on all clinical forms.'],
        ],
    ],
"""

old_releases_open = "$releases = [\n    [\n        'version' => 'v2.0',"
new_releases_open = "$releases = [\n" + V21_ENTRY + "    [\n        'version' => 'v2.0',"
assert old_releases_open in c, "FAIL: v2.0 entry start not found"
c = c.replace(old_releases_open, new_releases_open, 1)
print("✓ v2.1 entry inserted")

write(path, c)
print("✓ whats_new.php saved")
print("\n✅ Done — v2.1 is now the Latest release.")
