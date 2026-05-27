path = '/var/www/paperlessmd/schedule.php'
with open(path, 'r') as f:
    content = f.read()

old = "$viewAll  = isAdmin() && ($_GET['ma_id'] ?? '') === 'all';"
new = "$viewAll  = isAdmin() && (($_GET['ma_id'] ?? 'all') === 'all');"

if old in content:
    content = content.replace(old, new, 1)
    with open(path, 'w') as f:
        f.write(content)
    print('Patched OK')
else:
    print('Pattern not found')
