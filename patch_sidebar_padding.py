path = '/var/www/paperlessmd/includes/header.php'
with open(path, 'rb') as f:
    raw = f.read()

old = b'border-white/10 px-3 py-3">'
new = b'border-white/10 px-3 py-3 md:pb-3 pb-20">'
# The user section is the second occurrence (offset ~11210)
idx = raw.find(old, 11000)
print('found at:', idx)
raw2 = raw[:idx] + new + raw[idx+len(old):]
with open(path, 'wb') as f:
    f.write(raw2)

import subprocess
r = subprocess.run(['php', '-l', path], capture_output=True, text=True)
print(r.stdout.strip())
with open(path, 'rb') as f:
    v = f.read()
print('verified pb-20 present:', b'pb-20' in v)
