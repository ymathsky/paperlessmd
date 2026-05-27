path = '/var/www/paperlessmd/includes/header.php'
with open(path) as f:
    c = f.read()

# Add z-index rules to the existing style block, and remove z-[xx] Tailwind classes that aren't compiled
old_style = '        :root { --sidebar-w: 240px; }\n        @media (min-width: 768px) {\n            body.has-sidebar { padding-left: var(--sidebar-w); }\n        }'
new_style = '''        :root { --sidebar-w: 240px; }
        @media (min-width: 768px) {
            body.has-sidebar { padding-left: var(--sidebar-w); }
        }
        #sidebar { z-index: 60; }
        #sidebarBackdrop { z-index: 55; }'''

if old_style not in c:
    print('ERR: style block not found')
else:
    c = c.replace(old_style, new_style, 1)
    # Also remove z-[60] from sidebar class and z-[55] from backdrop class (not compiled, now using CSS above)
    c = c.replace(
        'class="no-print fixed inset-y-0 left-0 z-[60] flex flex-col',
        'class="no-print fixed inset-y-0 left-0 flex flex-col'
    )
    c = c.replace(
        'class="hidden md:hidden fixed inset-0 z-[55] bg-slate-900/60 no-print"',
        'class="hidden md:hidden fixed inset-0 bg-slate-900/60 no-print"'
    )
    with open(path, 'w') as f:
        f.write(c)
    print('OK: z-indexes moved to style block')

import subprocess
r = subprocess.run(['php', '-l', path], capture_output=True, text=True)
print(r.stdout.strip())
