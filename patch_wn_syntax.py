import subprocess, sys

path = '/var/www/paperlessmd/whats_new.php'
r = subprocess.run(['cat', path], capture_output=True, text=True)
content = r.stdout

# Fix the unescaped single quote in PHPMailer's and "Saving..." in desc
old = """'desc' => 'Form saves were hanging indefinitely due to PHPMailer's default 300-second SMTP connection timeout. When the SMTP host is unreachable the entire PHP request blocked, leaving the browser stuck on "Saving\u2026". Added a 5-second connection timeout so SMTP failures fail fast and the redirect to the signed document happens immediately.'"""

new = """'desc' => 'Form saves were hanging indefinitely due to PHPMailer\\\'s default 300-second SMTP connection timeout. When the SMTP host is unreachable the entire PHP request blocked, leaving the browser stuck on &ldquo;Saving&hellip;&rdquo;. Added a 5-second connection timeout so SMTP failures fail fast and the redirect to the signed document happens immediately.'"""

if old not in content:
    print('Pattern not found — trying raw search...')
    for i, line in enumerate(content.splitlines(), 1):
        if 'PHPMailer' in line:
            print(f'  line {i}: {repr(line[:120])}')
    sys.exit(1)

content = content.replace(old, new, 1)

r2 = subprocess.run(['tee', path], input=content, capture_output=True, text=True)
if r2.returncode != 0:
    print('ERR:', r2.stderr); sys.exit(1)

# Verify PHP syntax
r3 = subprocess.run(['php', '-l', path], capture_output=True, text=True)
print(r3.stdout.strip())
if r3.returncode != 0:
    print('STDERR:', r3.stderr.strip())
