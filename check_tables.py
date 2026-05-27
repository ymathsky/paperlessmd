#!/usr/bin/env python3
import subprocess

def q(sql):
    r = subprocess.run(
        ['mysql', '-u', 'pduser', '-pYm@thsky12101992', 'paperlessmd', '-e', sql],
        capture_output=True, text=True
    )
    return r.stdout + r.stderr

print("=== patient_medications ===")
print(q("DESCRIBE patient_medications"))

print("=== medication_history ===")
print(q("DESCRIBE medication_history"))

print("=== ALL TABLES ===")
print(q("SHOW TABLES"))
