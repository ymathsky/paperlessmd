import subprocess

# Check log sizes
r = subprocess.run(['ls', '-la', '/var/log/apache2/'], capture_output=True, text=True)
print(r.stdout)

# Check the real error log regardless of empty
r2 = subprocess.run(['tail', '-100', '/var/log/apache2/paperlessmd-error.log'], capture_output=True, text=True)
print('=== paperlessmd-error.log (last 100 lines) ===')
print(r2.stdout[-5000:] if r2.stdout else '(empty)')
print(r2.stderr[:200] if r2.stderr else '')

# Check PHP ini
r3 = subprocess.run(['php', '-i'], capture_output=True, text=True)
for line in r3.stdout.splitlines():
    if any(k in line.lower() for k in ['error_log', 'display_errors', 'log_errors']):
        print(line)
