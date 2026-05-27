import subprocess

# Check the yesterday error log for save_form patterns
r = subprocess.run(['zcat', '/var/log/apache2/paperlessmd-error.log.2.gz'], capture_output=True, text=True)
print('=== error.log.2 ===')
for line in r.stdout.splitlines():
    if 'save_form' in line or 'PHP' in line or 'Fatal' in line or 'Error' in line:
        print(line)

# Check yesterday's error log
r2 = subprocess.run(['tail', '-100', '/var/log/apache2/paperlessmd-error.log.1'], capture_output=True, text=True)
print('\n=== error.log.1 (last 100 lines) ===')
print(r2.stdout[-5000:])

# Check access log for 500s today
r3 = subprocess.run(['grep', ' 500 ', '/var/log/apache2/paperlessmd-access.log'], capture_output=True, text=True)
print('\n=== 500s in access log today ===')
print(r3.stdout[-2000:] if r3.stdout else '(none)')
