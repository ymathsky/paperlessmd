import subprocess

# Check access log for save_form requests
r = subprocess.run(
    ['grep', 'save_form', '/var/log/apache2/paperlessmd-access.log'],
    capture_output=True, text=True
)
print('=== save_form in access log ===')
print(r.stdout[-3000:] if r.stdout else '(none)')

# Also check yesterday's log
r2 = subprocess.run(
    ['grep', 'save_form', '/var/log/apache2/paperlessmd-access.log.1'],
    capture_output=True, text=True
)
print('=== save_form in access.log.1 (last 20 lines) ===')
lines = r2.stdout.strip().splitlines()
print('\n'.join(lines[-20:]) if lines else '(none)')
