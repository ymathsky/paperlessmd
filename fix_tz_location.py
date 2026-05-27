#!/usr/bin/env python3
with open('/var/www/paperlessmd/admin/ma_locations.php', 'r') as f:
    c = f.read()

# Fix sessionColor - append Z to treat as UTC
old3 = "    function sessionColor(lastActiveAt) {\n        if (!lastActiveAt) return 'grey';\n        var diff = (Date.now() - new Date(lastActiveAt).getTime()) / 60000;"
new3 = "    function sessionColor(lastActiveAt) {\n        if (!lastActiveAt) return 'grey';\n        var utcStr = lastActiveAt.endsWith('Z') ? lastActiveAt : lastActiveAt.replace(' ', 'T') + 'Z';\n        var diff = (Date.now() - new Date(utcStr).getTime()) / 60000;"
c = c.replace(old3, new3, 1)

# Fix sessionLabel - append Z to treat as UTC
old2 = "    function sessionLabel(lastActiveAt) {\n        if (!lastActiveAt) return 'Never logged in';\n        var diff = Math.round((Date.now() - new Date(lastActiveAt).getTime()) / 60000);"
new2 = "    function sessionLabel(lastActiveAt) {\n        if (!lastActiveAt) return 'Never logged in';\n        var utcStr = lastActiveAt.endsWith('Z') ? lastActiveAt : lastActiveAt.replace(' ', 'T') + 'Z';\n        var diff = Math.round((Date.now() - new Date(utcStr).getTime()) / 60000);"
c = c.replace(old2, new2, 1)

with open('/var/www/paperlessmd/admin/ma_locations.php', 'w') as f:
    f.write(c)

print("Done - replaced:", c.count('utcStr'), "occurrences")
