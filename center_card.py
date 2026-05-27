#!/usr/bin/env python3
f = '/var/www/paperlessmd/admin/manage_user.php'
c = open(f).read()
c = c.replace('<div class="max-w-2xl">', '<div class="max-w-2xl mx-auto">', 1)
# Also center the breadcrumb nav
c = c.replace('<nav class="flex items-center gap-2 text-sm text-slate-400 mb-6">', '<nav class="flex items-center gap-2 text-sm text-slate-400 mb-6 max-w-2xl mx-auto">', 1)
open(f, 'w').write(c)
import subprocess
r = subprocess.run(['php', '-l', f], capture_output=True, text=True)
print(r.stdout.strip() or r.stderr.strip())
