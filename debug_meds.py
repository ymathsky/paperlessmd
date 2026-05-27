#!/usr/bin/env python3
import subprocess, sys

# Run mysql queries
def q(sql):
    r = subprocess.run(
        ['mysql', '-u', 'pduser', '-pYm@thsky12101992', 'paperlessmd', '-e', sql],
        capture_output=True, text=True
    )
    return r.stdout + r.stderr

# 1. Check meds table schema
print("=== medications table ===")
print(q("DESCRIBE medications"))

# 2. Check what meds.php expects vs what exists
print("=== api/meds.php ===")
with open('/var/www/paperlessmd/api/meds.php') as f:
    print(f.read())
