import subprocess, sys

path = '/var/www/paperlessmd/includes/header.php'
r = subprocess.run(['cat', path], capture_output=True, text=True)
content = r.stdout

old = '    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/apple-touch-icon.png">'
new = '''    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/assets/img/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/img/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= BASE_URL ?>/assets/img/favicon-16.png">
    <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/apple-touch-icon.png">'''

if old not in content:
    print('ERR: pattern not found'); sys.exit(1)

content = content.replace(old, new, 1)
r2 = subprocess.run(['tee', path], input=content, capture_output=True, text=True)
if r2.returncode != 0:
    print('ERR:', r2.stderr); sys.exit(1)

print('OK: favicon links added to header.php')
r3 = subprocess.run(['grep', '-n', 'favicon', path], capture_output=True, text=True)
print(r3.stdout)
