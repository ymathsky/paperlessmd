path = '/var/www/paperlessmd/includes/header.php'
with open(path) as f:
    c = f.read()

# 1. Sidebar: z-50 → z-[60]  (only the aside#sidebar line)
c = c.replace(
    'class="no-print fixed inset-y-0 left-0 z-50 flex flex-col',
    'class="no-print fixed inset-y-0 left-0 z-[60] flex flex-col',
    1
)

# 2. Sidebar backdrop: z-40 → z-[55]
c = c.replace(
    'id="sidebarBackdrop"\n     class="hidden md:hidden fixed inset-0 z-40',
    'id="sidebarBackdrop"\n     class="hidden md:hidden fixed inset-0 z-[55]',
    1
)

with open(path, 'w') as f:
    f.write(c)

import subprocess
r = subprocess.run(['php', '-l', path], capture_output=True, text=True)
print(r.stdout.strip() or r.stderr.strip())
