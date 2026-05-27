import subprocess

r = subprocess.run(
    ['tail', '-50', '/var/log/apache2/paperlessmd-error.log'],
    capture_output=True, text=True
)
print('=== paperlessmd-error.log (last 50) ===')
print(r.stdout or '(empty)')

# Also check access log for recent 500s
r2 = subprocess.run(
    ['grep', ' 500 ', '/var/log/apache2/paperlessmd-access.log'],
    capture_output=True, text=True
)
print('=== 500s in access log ===')
print(r2.stdout[-3000:] if r2.stdout else '(none)')
