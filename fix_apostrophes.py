#!/usr/bin/env python3
with open('/var/www/paperlessmd/whats_new.php', 'r') as f:
    c = f.read()

# Fix unescaped apostrophes inside PHP single-quoted strings
c = c.replace("Let's Encrypt", "Let\\'s Encrypt")
c = c.replace("clinic's configured", "clinic\\'s configured")

with open('/var/www/paperlessmd/whats_new.php', 'w') as f:
    f.write(c)

import subprocess
result = subprocess.run(['php', '-l', '/var/www/paperlessmd/whats_new.php'], capture_output=True, text=True)
print(result.stdout.strip() or result.stderr.strip())
