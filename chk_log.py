import subprocess

# Check PHP error log locations
for log in ['/var/log/php8.2-fpm.log', '/var/www/paperlessmd/php_errors.log', '/tmp/php_errors.log']:
    r2 = subprocess.run(['tail', '-20', log], capture_output=True, text=True)
    if r2.stdout.strip():
        print(f'\n=== {log} ===')
        print(r2.stdout[-2000:])

# Lint save_form.php and all recently changed files
for f in [
    '/var/www/paperlessmd/api/save_form.php',
    '/var/www/paperlessmd/api/save_signature.php',
    '/var/www/paperlessmd/forms/vital_cs.php',
    '/var/www/paperlessmd/forms/new_patient_pocket.php',
    '/var/www/paperlessmd/view_document.php',
    '/var/www/paperlessmd/profile.php',
]:
    r4 = subprocess.run(['php', '-l', f], capture_output=True, text=True)
    result = r4.stdout.strip() or r4.stderr.strip()
    print(result)

# Check PHP display_errors / error_log setting
r5 = subprocess.run(['php', '-r', 'echo ini_get("error_log");'], capture_output=True, text=True)
print('error_log path:', r5.stdout.strip())
