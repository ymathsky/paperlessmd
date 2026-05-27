import subprocess

def sql(query):
    result = subprocess.run(
        ['mysql', '-u', 'pduser', '-pYm@thsky12101992', 'paperlessmd', '-e', query],
        capture_output=True, text=True
    )
    if result.returncode != 0:
        print('ERR:', result.stderr.strip())
    return result.stdout.strip()

# ── 1. Insert sample patient ─────────────────────────────────────────────────
patient_sql = """
INSERT INTO patients
    (first_name, last_name, dob, phone, email, address, insurance, insurance_id,
     pcp, pharmacy_name, pharmacy_phone, pharmacy_address,
     race, status, company, created_by)
VALUES
    ('Betty', 'Johnson', '1948-03-14', '630-555-0192', 'bjohnson@example.com',
     '742 Evergreen Terrace, Naperville, IL 60540',
     'Medicare Part B', 'H1234567890',
     'Dr. Michael Torres', 'Walgreens Naperville', '630-555-0188',
     '1220 E Ogden Ave, Naperville, IL 60563',
     'White', 'active', 'VMP', 8)
ON DUPLICATE KEY UPDATE first_name=first_name;
"""
sql(patient_sql)
pid_result = sql("SELECT id FROM patients WHERE first_name='Betty' AND last_name='Johnson' AND dob='1948-03-14' LIMIT 1;")
lines = pid_result.strip().split('\n')
pid = lines[-1].strip()
print(f'Patient ID: {pid}')

if not pid.isdigit():
    print('ERROR: could not get patient ID')
    exit(1)

# ── 2. Insert 5 schedule visits this week ────────────────────────────────────
# May 5 (Mon) through May 9 (Fri) 2026
# Alternate MAs: mkhan=2, mhassan=3, agutierrez=4, rdelacruz=5, shassan=6
visits = [
    ('2026-05-06', 2,  '09:30:00', 1, 'routine',     'Dr. Carlos Reyes'),   # Tue mkhan
    ('2026-05-07', 3,  '10:00:00', 1, 'wound_care',  'Dr. Carlos Reyes'),   # Wed mhassan
    ('2026-05-08', 4,  '11:00:00', 1, 'routine',     'Dr. Carlos Reyes'),   # Thu agutierrez
    ('2026-05-09', 5,  '09:00:00', 1, 'follow_up',   'Dr. Carlos Reyes'),   # Fri rdelacruz
    ('2026-05-12', 2,  '09:30:00', 1, 'wound_care',  'Dr. Carlos Reyes'),   # Mon mkhan
]

for v in visits:
    visit_date, ma_id, visit_time, visit_order, visit_type, provider = v
    vsql = f"""
    INSERT INTO schedule (visit_date, ma_id, patient_id, visit_time, visit_order, status, visit_type, provider_name, created_by)
    VALUES ('{visit_date}', {ma_id}, {pid}, '{visit_time}', {visit_order}, 'pending', '{visit_type}', '{provider}', 8);
    """
    sql(vsql)
    print(f'  Added visit {visit_date} MA={ma_id} type={visit_type}')

print('\nDone. Sample patient Betty Johnson (ID', pid, ') created with 5 scheduled visits.')
