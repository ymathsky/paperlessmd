import subprocess

# Find the actual error log
for cmd in [
    ['cat', '/etc/apache2/sites-enabled/000-default.conf'],
    ['cat', '/etc/apache2/sites-enabled/paperlessmd.conf'],
    ['find', '/etc/apache2/sites-enabled/', '-name', '*.conf'],
]:
    r = subprocess.run(cmd, capture_output=True, text=True)
    if r.returncode == 0 and r.stdout.strip():
        print(f'=== {" ".join(cmd)} ===')
        print(r.stdout[:2000])
