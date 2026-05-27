path = '/var/www/paperlessmd/schedule.php'
with open(path, 'rb') as f:
    raw = f.read()

changes = [
    # 1. Controls row: add flex-wrap so items wrap to next line on mobile
    (
        b'<div class="flex items-center gap-2">',
        b'<div class="flex flex-wrap items-center gap-2">',
    ),
    # 2. Stats bar: 2 cols on mobile, 4 on sm+
    (
        b'<div class="grid grid-cols-4 gap-3 mb-6 print-stat-bar"',
        b'<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6 print-stat-bar"',
    ),
    # 3. Print button: hide on mobile (not useful in field)
    (
        b'class="flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-colors shadow-sm no-print"',
        b'class="hidden sm:flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-sm font-semibold transition-colors shadow-sm no-print"',
    ),
]

for old, new in changes:
    count = raw.count(old)
    if count == 0:
        print(f'NOT FOUND: {old[:60]}')
    elif count > 1:
        print(f'MULTIPLE ({count}): {old[:60]}')
        raw = raw.replace(old, new, 1)  # replace only first
    else:
        raw = raw.replace(old, new)
        print(f'OK: {old[:60]}')

with open(path, 'wb') as f:
    f.write(raw)

import subprocess
r = subprocess.run(['php', '-l', path], capture_output=True, text=True)
print(r.stdout.strip())
