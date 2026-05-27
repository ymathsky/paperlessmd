import subprocess, sys

path = '/var/www/paperlessmd/forms/new_patient_pocket.php'

r = subprocess.run(['cat', path], capture_output=True, text=True)
if r.returncode != 0:
    print('ERR reading file:', r.stderr); sys.exit(1)

content = r.stdout

old1 = '<strong><?= h(PRACTICE_NAME) ?></strong> (referred to as "Provider"),'
new1 = '<strong class="co-name-display"><?= h(PRACTICE_NAME) ?></strong> (referred to as "Provider"),'

old2 = 'You may revoke verbally or in writing to <strong><?= h(PRACTICE_NAME) ?></strong>.'
new2 = 'You may revoke verbally or in writing to <strong class="co-name-display"><?= h(PRACTICE_NAME) ?></strong>.'

if old1 not in content:
    print('ERR: pattern 1 not found'); sys.exit(1)
if old2 not in content:
    print('ERR: pattern 2 not found'); sys.exit(1)

content = content.replace(old1, new1, 1)
content = content.replace(old2, new2, 1)

r2 = subprocess.run(['tee', path], input=content, capture_output=True, text=True)
if r2.returncode != 0:
    print('ERR writing file:', r2.stderr); sys.exit(1)

print('OK: both co-name-display classes added to CCM section in new_patient_pocket.php')

# Verify
r3 = subprocess.run(['grep', '-n', 'co-name-display', path], capture_output=True, text=True)
for line in r3.stdout.splitlines():
    print(' ', line)
