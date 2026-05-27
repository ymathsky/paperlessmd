import subprocess

# Add missing ma_signature column
sql_stmts = [
    # Add ma_signature after patient_signature
    "ALTER TABLE form_submissions ADD COLUMN ma_signature MEDIUMTEXT NULL AFTER patient_signature;",
]

for sql in sql_stmts:
    r = subprocess.run(
        ['mysql', '-upduser', '-pYm@thsky12101992', 'paperlessmd'],
        input=sql, capture_output=True, text=True
    )
    msg = r.stderr.strip().replace('mysql: [Warning] Using a password on the command line interface can be insecure.', '').strip()
    print(f'{"OK" if r.returncode==0 else "ERR"}: {sql[:60]} | {msg[:100] if msg else ""}')

# Verify
r2 = subprocess.run(
    ['mysql', '-upduser', '-pYm@thsky12101992', 'paperlessmd', '-e', 'DESCRIBE form_submissions;'],
    capture_output=True, text=True
)
for line in r2.stdout.splitlines():
    if 'signature' in line.lower() or 'poa' in line.lower():
        print(' ', line)
