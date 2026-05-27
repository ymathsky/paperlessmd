path = '/var/www/paperlessmd/includes/header.php'
with open(path) as f:
    c = f.read()

old = 'class="hidden md:hidden fixed inset-0 z-[55] bg-slate-900/50 backdrop-blur-sm no-print"'
new = 'class="hidden md:hidden fixed inset-0 z-[55] bg-slate-900/60 no-print"'

if old not in c:
    print('ERR: pattern not found')
else:
    c = c.replace(old, new, 1)
    with open(path, 'w') as f:
        f.write(c)
    print('OK: removed backdrop-blur-sm from sidebar backdrop')

import subprocess
r = subprocess.run(['php', '-l', path], capture_output=True, text=True)
print(r.stdout.strip())
