"""
Seed realistic demo data for Betty Johnson (patient_id = 10)
Covers: medications, vitals (via form_submissions), wound measurements,
        diagnoses, SOAP notes, care notes, and signed visit forms.
Run: python3 /tmp/seed_betty.py
"""
import mysql.connector
import json, datetime, base64

DB = dict(host="127.0.0.1", user="pduser", password="Ym@thsky12101992", database="paperlessmd")
PATIENT_ID  = 10
ADMIN_ID    = 8   # acasten
MA_ID       = 2   # mkhan
PROVIDER_ID = 10  # pfabilane (provider)

cn = mysql.connector.connect(**DB)
cu = cn.cursor()

today = datetime.date.today()
def dago(n): return today - datetime.timedelta(days=n)

# ── Tiny 1×1 white pixel as placeholder signature PNG (base64) ────────────────
SIG_B64 = (
    "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwACggF/edZFTAAAAABJRU5ErkJggg=="
)

# ══════════════════════════════════════════════════════════════════════════════
# 1. MEDICATIONS  (master list)
# ══════════════════════════════════════════════════════════════════════════════
cu.execute("DELETE FROM patient_medications WHERE patient_id = %s", (PATIENT_ID,))
cu.execute("DELETE FROM medication_history   WHERE patient_id = %s", (PATIENT_ID,))

meds = [
    ("Metformin 500mg",          "BID",     "active"),
    ("Lisinopril 10mg",          "QD",      "active"),
    ("Atorvastatin 40mg",        "QD",      "active"),
    ("Aspirin 81mg",             "QD",      "active"),
    ("Furosemide 20mg",          "QD",      "active"),
    ("Metoprolol Succinate 25mg","QD",      "active"),
    ("Gabapentin 300mg",         "TID",     "active"),
    ("Pantoprazole 40mg",        "QD",      "active"),
    ("Vitamin D3 2000 IU",       "QD",      "active"),
    ("Ferrous Sulfate 325mg",    "BID",     "active"),
    ("Hydrochlorothiazide 25mg", "QD",      "discontinued"),
]
med_ids = []
for i, (name, freq, status) in enumerate(meds):
    cu.execute(
        """INSERT INTO patient_medications
           (patient_id, med_name, med_frequency, status, sort_order, added_by, added_at)
           VALUES (%s,%s,%s,%s,%s,%s,%s)""",
        (PATIENT_ID, name, freq, status, i, ADMIN_ID, dago(60))
    )
    mid = cu.lastrowid
    med_ids.append(mid)
    action = "discontinued" if status == "discontinued" else "added"
    cu.execute(
        """INSERT INTO medication_history
           (medication_id, patient_id, action, new_name, new_frequency, new_status, changed_by, changed_at)
           VALUES (%s,%s,%s,%s,%s,%s,%s,%s)""",
        (mid, PATIENT_ID, action, name, freq, status, ADMIN_ID, dago(60))
    )
print(f"✓  {len(meds)} medications inserted")

# ══════════════════════════════════════════════════════════════════════════════
# 2. DIAGNOSES
# ══════════════════════════════════════════════════════════════════════════════
cu.execute("DELETE FROM patient_diagnoses WHERE patient_id = %s", (PATIENT_ID,))
diagnoses = [
    ("L89.152",  "Pressure ulcer of sacral region, stage 2"),
    ("E11.9",    "Type 2 diabetes mellitus without complications"),
    ("I10",      "Essential (primary) hypertension"),
    ("E78.5",    "Hyperlipidemia, unspecified"),
    ("M79.3",    "Panniculitis, unspecified"),
    ("Z87.39",   "Personal history of other musculoskeletal disorders"),
]
for code, desc in diagnoses:
    cu.execute(
        """INSERT INTO patient_diagnoses (patient_id, icd_code, icd_desc, added_by, added_at)
           VALUES (%s,%s,%s,%s,%s)""",
        (PATIENT_ID, code, desc, ADMIN_ID, dago(55))
    )
print(f"✓  {len(diagnoses)} diagnoses inserted")

# ══════════════════════════════════════════════════════════════════════════════
# 3. WOUND MEASUREMENTS  (sacral ulcer improving over 5 weeks)
# ══════════════════════════════════════════════════════════════════════════════
cu.execute("DELETE FROM wound_measurements WHERE patient_id = %s", (PATIENT_ID,))
# site, (date_ago, L, W, D, notes)
wound_visits = [
    (35, 4.5, 3.2, 0.8, "Stage 2 sacral ulcer, serosanguineous drainage, wound bed pink"),
    (28, 4.0, 3.0, 0.6, "Mild improvement, less exudate, periwound erythema decreased"),
    (21, 3.5, 2.5, 0.5, "Continued improvement, granulation tissue forming"),
    (14, 3.0, 2.0, 0.4, "Good granulation, wound edges approximating"),
    ( 7, 2.5, 1.8, 0.3, "Significant healing, minimal drainage, patient compliant with offloading"),
    ( 0, 2.0, 1.5, 0.2, "Near closure, wound nearly epithelialized"),
]
for (ago, L, W, D, note) in wound_visits:
    cu.execute(
        """INSERT INTO wound_measurements
           (patient_id, measured_at, wound_site, length_cm, width_cm, depth_cm, notes, recorded_by)
           VALUES (%s,%s,%s,%s,%s,%s,%s,%s)""",
        (PATIENT_ID, dago(ago), "Sacral Region", L, W, D, note, MA_ID)
    )
print(f"✓  {len(wound_visits)} wound measurements inserted")

# ══════════════════════════════════════════════════════════════════════════════
# 4. FORM SUBMISSIONS  (Vital CS — signed, with vitals JSON)
# ══════════════════════════════════════════════════════════════════════════════
cu.execute("DELETE FROM form_submissions WHERE patient_id = %s AND form_type = 'vital_cs'", (PATIENT_ID,))

visit_vitals = [
    # (days_ago, bp_sys, bp_dia, pulse, temp, o2, rr, wt)
    (35, 148, 88, 78, 98.2, 96, 16, 172),
    (28, 145, 86, 76, 98.0, 97, 16, 171),
    (21, 140, 84, 74, 98.1, 97, 15, 170),
    (14, 138, 82, 72, 98.0, 98, 16, 169),
    ( 7, 135, 80, 70, 98.2, 98, 15, 168),
    ( 0, 132, 78, 68, 97.9, 99, 15, 167),
]

visit_types = ["follow_up", "follow_up", "follow_up", "follow_up", "follow_up", "follow_up"]
form_ids = []
for i, (ago, sys, dia, pulse, temp, o2, rr, wt) in enumerate(visit_vitals):
    visit_date = dago(ago)
    vtype = visit_types[i]
    form_data = {
        "visit_date":  str(visit_date),
        "visit_type":  vtype,
        "time_in":     "09:30",
        "provider":    "Dr. Michael Torres",
        "chief_complaint": "Wound care follow-up — sacral pressure ulcer, stage 2, progressive healing.",
        "icd_codes":   [{"code": "L89.152", "desc": "Pressure ulcer of sacral region, stage 2"},
                        {"code": "E11.9",   "desc": "Type 2 diabetes mellitus without complications"}],
        "vitals": {
            "bp_systolic":    sys,  "bp_diastolic": dia,
            "pulse":          pulse,"temperature":  temp,
            "o2_sat":         o2,   "resp_rate":    rr,
            "weight":         wt,   "height":       "64",
            "bmi":            round(wt / (64 * 64) * 703, 1)
        },
        "allergies":    "Penicillin — rash",
        "homebound":    ["Unable to leave home without considerable effort"],
        "medications":  [{"name": n, "frequency": f, "type": "Maintenance"}
                         for n, f, s in meds if s == "active"],
    }
    signed_at = datetime.datetime.combine(visit_date, datetime.time(10, 15))
    cu.execute(
        """INSERT INTO form_submissions
           (patient_id, form_type, form_data, patient_signature, ma_signature,
            provider_signature, provider_name, provider_signed_at,
            ma_id, status, visit_type, signed_at, created_at)
           VALUES (%s,'vital_cs',%s,%s,%s,%s,%s,%s,%s,'signed',%s,%s,%s)""",
        (PATIENT_ID, json.dumps(form_data), SIG_B64, SIG_B64,
         SIG_B64, "Dr. Michael Torres", signed_at,
         MA_ID, vtype, signed_at, signed_at)
    )
    form_ids.append(cu.lastrowid)

print(f"✓  {len(visit_vitals)} signed visit forms inserted")

# ══════════════════════════════════════════════════════════════════════════════
# 5. SOAP NOTES  (one per visit, alternating draft/finalized)
# ══════════════════════════════════════════════════════════════════════════════
cu.execute("DELETE FROM soap_notes WHERE patient_id = %s", (PATIENT_ID,))
soap_data = [
    (35, "draft",
     "Patient reports pain at sacral wound site, rated 4/10. Wound dressing changed at prior visit.",
     "BP 148/88, P 78, T 98.2°F, O2 96%, RR 16. Wt 172 lbs. Sacral ulcer 4.5×3.2×0.8 cm, serosanguineous drainage.",
     "Stage 2 sacral pressure ulcer. Poorly controlled HTN and T2DM contributing to delayed healing.",
     "Continue wound care protocol. Reinforce offloading instructions. Adjust Metformin per PCP."),
    (28, "finalized",
     "Patient reports slight improvement in wound pain, now 3/10. Compliance with offloading reported.",
     "BP 145/86, P 76, O2 97%. Sacral ulcer 4.0×3.0×0.6 cm, decreased exudate, periwound erythema reduced.",
     "Stage 2 sacral pressure ulcer — improving. HTN stable on Lisinopril and Metoprolol.",
     "Continue current wound care regimen. Follow up in 7 days. Encourage fluid intake."),
    (21, "finalized",
     "Patient feeling better. Wound site less painful. Reports sleeping on her side with pillow support.",
     "BP 140/84, P 74, O2 97%. Sacral ulcer 3.5×2.5×0.5 cm. Granulation tissue present.",
     "Wound healing progressing well. Granulation tissue indicates positive response to treatment.",
     "Maintain dressing protocol. Schedule follow-up in one week. Monitor blood glucose logs."),
    (14, "finalized",
     "Patient denies significant pain. Compliant with turning schedule. Reports improved appetite.",
     "BP 138/82, P 72, O2 98%. Sacral ulcer 3.0×2.0×0.4 cm. Wound edges approximating.",
     "Stage 2 sacral ulcer — continued improvement. Edges approximating suggests near-closure trajectory.",
     "Continue wound care. Reinforce nutrition. Next visit in 7 days."),
    ( 7, "finalized",
     "Patient in good spirits. Pain 1/10. Wound nearly closed per patient report.",
     "BP 135/80, P 70, O2 98%. Sacral ulcer 2.5×1.8×0.3 cm. Minimal drainage, good granulation.",
     "Near-closure of stage 2 sacral pressure ulcer. Excellent patient compliance. Vitals trending toward goal.",
     "Continue dressing changes. Begin tapering visit frequency if healing continues. Educate on maintenance."),
    ( 0, "draft",
     "Patient reports wound 'almost healed.' No pain. Sleeping well with wedge pillow.",
     "BP 132/78, P 68, O2 99%. Sacral ulcer 2.0×1.5×0.2 cm. Near complete epithelialization.",
     "Stage 2 sacral ulcer nearing full closure. BP at target. Diabetes well-managed.",
     "Wound care every 10 days if trajectory maintained. Continue medications as prescribed. Schedule 30-day f/u."),
]
for i, (ago, status, subj, obj, assess, plan) in enumerate(soap_data):
    note_date = dago(ago)
    finalized_at = datetime.datetime.combine(note_date, datetime.time(11, 0)) if status == "finalized" else None
    cu.execute(
        """INSERT INTO soap_notes
           (patient_id, visit_id, author_id, note_date, subjective, objective,
            assessment, plan, status, finalized_at, created_at)
           VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)""",
        (PATIENT_ID, form_ids[i], PROVIDER_ID, note_date,
         subj, obj, assess, plan, status, finalized_at,
         datetime.datetime.combine(note_date, datetime.time(10, 30)))
    )
print(f"✓  {len(soap_data)} SOAP notes inserted")

# ══════════════════════════════════════════════════════════════════════════════
# 6. CARE NOTES  (coordination thread)
# ══════════════════════════════════════════════════════════════════════════════
cu.execute("DELETE FROM care_notes WHERE patient_id = %s", (PATIENT_ID,))
care = [
    (ADMIN_ID, 34, 1,
     "Called Walgreens Naperville re: Furosemide refill — confirmed ready for pickup. Patient's daughter will collect."),
    (MA_ID,    30, 0,
     "Patient requested extra wound care supplies. Notified admin to check inventory."),
    (ADMIN_ID, 29, 0,
     "Ordered additional 4×4 gauze and foam dressings. Should arrive by end of week."),
    (MA_ID,    21, 0,
     "Spoke with Dr. Torres office — Metformin dose review scheduled for May 15. Please note in chart."),
    (ADMIN_ID, 14, 1,
     "INSURANCE NOTE: Medicare Part B pre-auth for wound care confirmed through June 30, 2026. Auth # MC-20260501-BJ."),
    (MA_ID,     7, 0,
     "Patient asked about possibility of reducing visit frequency. Referred to Dr. Torres for decision."),
    (ADMIN_ID,  1, 0,
     "Dr. Torres approved reducing to every-10-day visits if next measurement shows continued improvement."),
]
for author, ago, pinned, body in care:
    cu.execute(
        """INSERT INTO care_notes (patient_id, author_id, body, pinned, created_at)
           VALUES (%s,%s,%s,%s,%s)""",
        (PATIENT_ID, author, body, pinned,
         datetime.datetime.combine(dago(ago), datetime.time(9, 0)))
    )
print(f"✓  {len(care)} care notes inserted")

cn.commit()
cu.close()
cn.close()
print("\n✅  All demo data seeded for Betty Johnson (patient_id=10)")
print("   Medications:        10 active + 1 discontinued")
print("   Diagnoses:           6 ICD-10 codes")
print("   Wound measurements:  6 data points (sacral ulcer, improving)")
print("   Visit forms:         6 signed Vital CS forms")
print("   SOAP notes:          6 (4 finalized + 2 draft)")
print("   Care notes:          7 (2 pinned)")
