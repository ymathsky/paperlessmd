import subprocess, datetime, sys

MYSQL = ["mysql", "-u", "pduser", "-pYm@thsky12101992", "paperlessmd"]

def sql(q, fetch=False):
    r = subprocess.run(MYSQL, input=q, capture_output=True, text=True)
    if r.returncode != 0:
        print("ERROR:", r.stderr[:400]); sys.exit(1)
    return r.stdout if fetch else None

def insert_get_id(q):
    out = sql(q + "\nSELECT LAST_INSERT_ID();", fetch=True)
    for line in reversed(out.strip().split('\n')):
        line = line.strip()
        if line.isdigit() and line != '0':
            return line
    print("Could not get insert ID from:", out); sys.exit(1)

today = datetime.date.today()
def dago(n): return (today - datetime.timedelta(days=n)).strftime("%Y-%m-%d")

# ── Patient 1: James Kowalski (Primary/CCM) ───────────────────────────────────
pid1 = insert_get_id("""
INSERT INTO patients
  (first_name,last_name,dob,phone,email,address,
   insurance,insurance_id,pcp,
   pharmacy_name,pharmacy_phone,pharmacy_address,
   race,status,assigned_ma,created_by)
VALUES
  ('James','Kowalski','1958-09-22','(630) 555-0182','jkowalski@email.com',
   '318 Maple Grove Dr, Naperville, IL 60563',
   'Medicare Part B + Illinois Medicaid','1ZB7-HM4-PR39',
   'Dr. Sarah Mitchell, Midwest Internal Medicine, (630) 555-0310',
   'Walgreens Naperville','(630) 555-0200','1440 W Ogden Ave, Naperville, IL 60540',
   'White','active',2,8);
""")
print(f"Patient 1 (Kowalski) id={pid1}")

meds1 = [
    ("Lisinopril 10mg","QD","active"),
    ("Metformin 1000mg","BID","active"),
    ("Carvedilol 12.5mg","BID","active"),
    ("Furosemide 40mg","QD","active"),
    ("Atorvastatin 40mg","QD","active"),
    ("Spironolactone 25mg","QD","active"),
    ("Aspirin 81mg","QD","active"),
    ("Amlodipine 5mg","QD","discontinued"),
]
rows = ",".join(f"({pid1},'{n}','{f}','{s}',{i},8,'{dago(45)}')" for i,(n,f,s) in enumerate(meds1))
sql(f"INSERT INTO patient_medications (patient_id,med_name,med_frequency,status,sort_order,added_by,added_at) VALUES {rows};")
sql(f"INSERT INTO medication_history (medication_id,patient_id,action,new_name,new_frequency,new_status,changed_by,changed_at) SELECT id,patient_id,IF(status='discontinued','discontinued','added'),med_name,med_frequency,status,8,'{dago(45)}' FROM patient_medications WHERE patient_id={pid1};")

diags1 = [
    ("I50.9","Heart failure, unspecified"),
    ("E11.9","Type 2 diabetes mellitus without complications"),
    ("I10","Essential (primary) hypertension"),
    ("N18.3","Chronic kidney disease, stage 3 (moderate)"),
    ("E78.5","Hyperlipidemia, unspecified"),
    ("Z87.39","Personal history of musculoskeletal disorder"),
]
rows = ",".join(f"({pid1},'{c}','{d}',8,'{dago(45)}')" for c,d in diags1)
sql(f"INSERT INTO patient_diagnoses (patient_id,icd_code,icd_desc,added_by,added_at) VALUES {rows};")
print(f"  -> {len(meds1)} meds, {len(diags1)} diagnoses")

# ── Patient 2: Gloria Santana (Wound Care) ────────────────────────────────────
pid2 = insert_get_id("""
INSERT INTO patients
  (first_name,last_name,dob,phone,email,address,
   insurance,insurance_id,pcp,
   pharmacy_name,pharmacy_phone,pharmacy_address,
   race,status,assigned_ma,created_by)
VALUES
  ('Gloria','Santana','1945-07-19','(847) 555-0241','',
   '5902 W Belmont Ave, Chicago, IL 60634',
   'Medicare Part B','2HK9-TE3-LW61',
   'Dr. Raymond Ochoa, Chicago Family Health, (773) 555-0419',
   'Rite Aid Chicago','(773) 555-0133','5800 W Belmont Ave, Chicago, IL 60634',
   'Hispanic or Latino','active',4,8);
""")
print(f"Patient 2 (Santana) id={pid2}")

meds2 = [
    ("Metformin 500mg","BID","active"),
    ("Insulin Glargine 20 units","QD at bedtime","active"),
    ("Clopidogrel 75mg","QD","active"),
    ("Atorvastatin 20mg","QD","active"),
    ("Amlodipine 10mg","QD","active"),
    ("Gabapentin 300mg","TID","active"),
    ("Bacitracin/Polymyxin Ointment","Apply to wound BID","active"),
]
rows = ",".join(f"({pid2},'{n}','{f}','{s}',{i},8,'{dago(20)}')" for i,(n,f,s) in enumerate(meds2))
sql(f"INSERT INTO patient_medications (patient_id,med_name,med_frequency,status,sort_order,added_by,added_at) VALUES {rows};")
sql(f"INSERT INTO medication_history (medication_id,patient_id,action,new_name,new_frequency,new_status,changed_by,changed_at) SELECT id,patient_id,'added',med_name,med_frequency,status,8,'{dago(20)}' FROM patient_medications WHERE patient_id={pid2};")

diags2 = [
    ("E11.621","Type 2 diabetes mellitus with foot ulcer"),
    ("I70.213","Atherosclerosis of native arteries of right leg with ulceration of ankle"),
    ("L97.512","Non-pressure chronic ulcer of right toe with fat layer exposed"),
    ("I73.9","Peripheral vascular disease, unspecified"),
    ("E11.9","Type 2 diabetes mellitus without complications"),
]
rows = ",".join(f"({pid2},'{c}','{d}',8,'{dago(20)}')" for c,d in diags2)
sql(f"INSERT INTO patient_diagnoses (patient_id,icd_code,icd_desc,added_by,added_at) VALUES {rows};")

wounds2 = [
    (18, 2.2, 1.8, 0.6, "Diabetic foot ulcer, right great toe plantar surface. Wound base pink, mild seropurulent exudate. Periwound callus trimmed."),
    (11, 2.0, 1.6, 0.5, "Slight reduction in size. Exudate decreased. Offloading boot in use. Periwound erythema improved."),
    ( 4, 1.7, 1.4, 0.4, "Continued improvement. Granulation tissue forming at wound base. Patient compliant with offloading and glucose monitoring."),
]
rows = ",".join(f"({pid2},'{dago(a)}','Right Great Toe (Plantar)',{L},{W},{D},'{n}',4)" for a,L,W,D,n in wounds2)
sql(f"INSERT INTO wound_measurements (patient_id,measured_at,wound_site,length_cm,width_cm,depth_cm,notes,recorded_by) VALUES {rows};")
print(f"  -> {len(meds2)} meds, {len(diags2)} diagnoses, {len(wounds2)} wound measurements")

print(f"\nDone! James Kowalski (Primary) id={pid1}  |  Gloria Santana (Wound) id={pid2}")
