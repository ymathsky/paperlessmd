"""
Seed demo data for Betty Johnson (patient_id=10) via raw SQL through mysql CLI.
"""
import subprocess, json, datetime, sys

MYSQL = ["mysql", "-u", "pduser", "-pYm@thsky12101992", "paperlessmd"]

def sql(q):
    r = subprocess.run(MYSQL, input=q, capture_output=True, text=True)
    if r.returncode != 0:
        print("ERROR:", r.stderr[:300])
        sys.exit(1)

PATIENT_ID  = 10
ADMIN_ID    = 8
MA_ID       = 2
PROVIDER_ID = 10

today = datetime.date.today()
def dago(n): return (today - datetime.timedelta(days=n)).strftime("%Y-%m-%d")
def dtago(n, h=10, m=15):
    return (datetime.datetime.combine(today - datetime.timedelta(days=n),
                                      datetime.time(h, m))).strftime("%Y-%m-%d %H:%M:%S")

SIG = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwACggF/edZFTAAAAABJRU5ErkJggg=="

sql(f"""
DELETE FROM patient_medications WHERE patient_id={10};
DELETE FROM medication_history   WHERE patient_id={10};
DELETE FROM patient_diagnoses    WHERE patient_id={10};
DELETE FROM wound_measurements   WHERE patient_id={10};
DELETE FROM form_submissions     WHERE patient_id={10} AND form_type='vital_cs';
DELETE FROM soap_notes           WHERE patient_id={10};
DELETE FROM care_notes           WHERE patient_id={10};
""")
print("clean ok")

meds = [
    ("Metformin 500mg","BID","active"),
    ("Lisinopril 10mg","QD","active"),
    ("Atorvastatin 40mg","QD","active"),
    ("Aspirin 81mg","QD","active"),
    ("Furosemide 20mg","QD","active"),
    ("Metoprolol Succinate 25mg","QD","active"),
    ("Gabapentin 300mg","TID","active"),
    ("Pantoprazole 40mg","QD","active"),
    ("Vitamin D3 2000 IU","QD","active"),
    ("Ferrous Sulfate 325mg","BID","active"),
    ("Hydrochlorothiazide 25mg","QD","discontinued"),
]
rows = ",".join(f"({10},'{n}','{f}','{s}',{i},{8},'{dago(60)}')" for i,(n,f,s) in enumerate(meds))
sql(f"INSERT INTO patient_medications (patient_id,med_name,med_frequency,status,sort_order,added_by,added_at) VALUES {rows};")
sql(f"INSERT INTO medication_history (medication_id,patient_id,action,new_name,new_frequency,new_status,changed_by,changed_at) SELECT id,patient_id,IF(status='discontinued','discontinued','added'),med_name,med_frequency,status,{8},'{dago(60)}' FROM patient_medications WHERE patient_id={10};")
print(f"meds ok")

diags = [
    ("L89.152","Pressure ulcer of sacral region, stage 2"),
    ("E11.9","Type 2 diabetes mellitus without complications"),
    ("I10","Essential (primary) hypertension"),
    ("E78.5","Hyperlipidemia, unspecified"),
    ("M79.3","Panniculitis, unspecified"),
    ("Z87.39","Personal history of other musculoskeletal disorders"),
]
rows = ",".join(f"({10},'{c}','{d}',{8},'{dago(55)}')" for c,d in diags)
sql(f"INSERT INTO patient_diagnoses (patient_id,icd_code,icd_desc,added_by,added_at) VALUES {rows};")
print("diags ok")

wounds = [
    (35,4.5,3.2,0.8,"Stage 2 sacral ulcer, serosanguineous drainage, wound bed pink"),
    (28,4.0,3.0,0.6,"Mild improvement, less exudate, periwound erythema decreased"),
    (21,3.5,2.5,0.5,"Continued improvement, granulation tissue forming"),
    (14,3.0,2.0,0.4,"Good granulation, wound edges approximating"),
    (7,2.5,1.8,0.3,"Significant healing, minimal drainage, patient compliant with offloading"),
    (0,2.0,1.5,0.2,"Near closure, wound nearly epithelialized"),
]
rows = ",".join(f"({10},'{dago(a)}','Sacral Region',{L},{W},{D},'{n}',{2})" for a,L,W,D,n in wounds)
sql(f"INSERT INTO wound_measurements (patient_id,measured_at,wound_site,length_cm,width_cm,depth_cm,notes,recorded_by) VALUES {rows};")
print("wounds ok")

visits = [
    (35,148,88,78,98.2,96,16,172,"draft"),
    (28,145,86,76,98.0,97,16,171,"finalized"),
    (21,140,84,74,98.1,97,15,170,"finalized"),
    (14,138,82,72,98.0,98,16,169,"finalized"),
    (7,135,80,70,98.2,98,15,168,"finalized"),
    (0,132,78,68,97.9,99,15,167,"draft"),
]
active_meds=[m for m in meds if m[2]=="active"]
soap_texts=[
    ("Patient reports sacral wound pain 4/10.","BP 148/88 P78 T98.2 O2 96% Sacral 4.5x3.2x0.8cm serosanguineous.","Stage 2 sacral pressure ulcer. HTN T2DM contributing.","Continue wound care, reinforce offloading, adjust Metformin per PCP."),
    ("Wound pain 3/10, compliance with offloading.","BP 145/86 P76 O2 97% Sacral 4.0x3.0x0.6cm decreased exudate.","Stage 2 ulcer improving. HTN stable.","Continue wound care. Follow up 7 days. Encourage fluids."),
    ("Feeling better, wound less painful.","BP 140/84 P74 O2 97% Sacral 3.5x2.5x0.5cm granulation tissue.","Wound healing well. Granulation tissue present.","Maintain dressing. Follow-up one week. Monitor glucose."),
    ("No significant pain, compliant with turning schedule.","BP 138/82 P72 O2 98% Sacral 3.0x2.0x0.4cm edges approximating.","Stage 2 ulcer continued improvement, near closure.","Continue wound care. Reinforce nutrition. Next visit 7 days."),
    ("Pain 1/10. Wound nearly closed.","BP 135/80 P70 O2 98% Sacral 2.5x1.8x0.3cm minimal drainage.","Near-closure stage 2 ulcer. Excellent compliance.","Continue dressings. Taper frequency if healing continues."),
    ("Wound almost healed per patient. No pain.","BP 132/78 P68 O2 99% Sacral 2.0x1.5x0.2cm near epithelialized.","Stage 2 ulcer nearing closure. BP at goal. DM managed.","Wound care every 10 days. Schedule 30-day follow-up."),
]
for i,(ago,sys,dia,pulse,temp,o2,rr,wt,soap_st) in enumerate(visits):
    fd=json.dumps({"visit_date":dago(ago),"visit_type":"follow_up","chief_complaint":"Wound care follow-up.","vitals":{"bp_systolic":sys,"bp_diastolic":dia,"pulse":pulse,"temperature":temp,"o2_sat":o2,"resp_rate":rr,"weight":wt},"icd_codes":[{"code":"L89.152"},{"code":"E11.9"}],"medications":[{"name":n,"frequency":f} for n,f,s in active_meds]}).replace("\\","\\\\").replace("'","\\'")
    sig=SIG.replace("'","\\'")
    sat=dtago(ago,10,15)
    sql(f"INSERT INTO form_submissions (patient_id,form_type,form_data,patient_signature,ma_signature,provider_signature,provider_name,provider_signed_at,ma_id,status,visit_type,signed_at,created_at) VALUES ({10},'vital_cs','{fd}','{sig}','{sig}','{sig}','Dr. Michael Torres','{sat}',{2},'signed','follow_up','{sat}','{sat}');")
    vid_r=subprocess.run(MYSQL,input="SELECT LAST_INSERT_ID();",capture_output=True,text=True)
    vid=vid_r.stdout.strip().split('\n')[-1]
    s,o,a,p=soap_texts[i]
    fin=f"'{dtago(ago,11,0)}'" if soap_st=="finalized" else "NULL"
    sql(f"INSERT INTO soap_notes (patient_id,visit_id,author_id,note_date,subjective,objective,assessment,plan,status,finalized_at,created_at) VALUES ({10},{vid},{10},'{dago(ago)}','{s}','{o}','{a}','{p}','{soap_st}',{fin},'{dtago(ago,10,30)}');")
print("visits+SOAP ok")

care=[
    (8,34,1,"Called Walgreens Naperville re: Furosemide refill - confirmed ready for pickup."),
    (2,30,0,"Patient requested extra wound care supplies. Notified admin."),
    (8,29,0,"Ordered 4x4 gauze and foam dressings. Should arrive end of week."),
    (2,21,0,"Spoke with Dr. Torres office - Metformin dose review scheduled May 15."),
    (8,14,1,"INSURANCE: Medicare Part B pre-auth confirmed through June 30 2026. Auth MC-20260501-BJ."),
    (2,7,0,"Patient asked about reducing visit frequency. Referred to Dr. Torres."),
    (8,1,0,"Dr. Torres approved every-10-day visits if next measurement shows continued improvement."),
]
rows=",".join(f"({10},{a},'{b.replace(chr(39),chr(39)+chr(39))}',{p},'{dtago(d,9,0)}')" for a,d,p,b in care)
sql(f"INSERT INTO care_notes (patient_id,author_id,body,pinned,created_at) VALUES {rows};")
print("care notes ok")

print("""
All done - Betty Johnson seeded:
  11 medications  |  6 diagnoses  |  6 wound measurements
  6 signed visits  |  6 SOAP notes  |  7 care notes (2 pinned)
""")
