import subprocess, sys

path = '/var/www/paperlessmd/includes/mailer.php'
r = subprocess.run(['cat', path], capture_output=True, text=True)
content = r.stdout

old = "        $mail->Port       = MAIL_PORT;\n        $mail->CharSet    = 'UTF-8';"
new = "        $mail->Port       = MAIL_PORT;\n        $mail->Timeout    = 5;  // fail fast if SMTP unreachable\n        $mail->CharSet    = 'UTF-8';"

if old not in content:
    print('ERR: pattern not found'); sys.exit(1)

content = content.replace(old, new, 1)

r2 = subprocess.run(['tee', path], input=content, capture_output=True, text=True)
if r2.returncode != 0:
    print('ERR:', r2.stderr); sys.exit(1)

print('OK: Added Timeout = 5 to mailer.php')
r3 = subprocess.run(['grep', '-n', 'Timeout', path], capture_output=True, text=True)
print(r3.stdout)
