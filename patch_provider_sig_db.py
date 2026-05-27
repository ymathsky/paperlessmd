import subprocess

def run_sql(sql):
    r = subprocess.run(
        ['mysql', '-upduser', '-pYm@thsky12101992', 'paperlessmd'],
        input=sql, capture_output=True, text=True
    )
    return r.returncode, r.stdout, r.stderr

# Check which columns already exist
_, out, _ = run_sql("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA='paperlessmd' AND TABLE_NAME='staff' AND COLUMN_NAME IN ('saved_provider_signature','saved_provider_name','saved_provider_npi');")
existing = [l.strip() for l in out.splitlines() if l.strip() and l.strip() != 'COLUMN_NAME']
print('existing:', existing)

cols = {
    'saved_provider_signature': 'MEDIUMTEXT NULL',
    'saved_provider_name': 'VARCHAR(100) NULL',
    'saved_provider_npi': 'VARCHAR(20) NULL',
}
for col, defn in cols.items():
    if col not in existing:
        rc, _, err = run_sql(f"ALTER TABLE staff ADD COLUMN {col} {defn};")
        print(f"ADD {col}: {'OK' if rc==0 else err.strip()[:100]}")
    else:
        print(f"SKIP {col}: already exists")
