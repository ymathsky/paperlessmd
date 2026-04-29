# PaperlessMD — Complete Demo Script
**Beyond Wound Care Inc.**
*Full feature walk-through — live demonstration guide*

---

## PRE-FLIGHT CHECKLIST

> Complete these before anyone enters the room.

- [ ] Open `https://docs.md-officesupport.com` in **Chrome** on the largest screen available
- [ ] Log in as **admin** — every feature must be visible
- [ ] Open a second tab on a **phone or tablet** for the mobile section
- [ ] Confirm test patient **"John Demo"** exists with:
  - Active medication list (3–5 meds)
  - At least one prior visit form (for pre-fill)
  - A wound photo uploaded
  - Insurance card photo and SSS photo on file
- [ ] Open the **AI chat** once to confirm it loads (avoids cold-start delay during demo)
- [ ] Clear any old draft forms on the test patient
- [ ] Have the **DEMO_SCRIPT.md** open on a second monitor or printed — this is your cheat sheet
- [ ] Run `migrate_patient_extras.php` on production if not yet done (adds race, insurance photo, SSS, pharmacy columns)

---

## TIMING OVERVIEW

| Part | Section | Time |
|------|---------|------|
| 1 | The problem we solve | 1 min |
| 2 | Login & security | 1 min |
| 3 | Dashboard | 2 min |
| 4 | Global search | 30 sec |
| 5 | Patient list & filters | 1 min |
| 6 | Patient profile — summary & documents | 2 min |
| 7 | Patient tabs deep-dive | 4 min |
| 8 | Visit form — full wizard | 6 min |
| 9 | Additional form types | 2 min |
| 10 | Provider sign queue | 1.5 min |
| 11 | Schedule — day & week | 2 min |
| 12 | Schedule management (admin) | 1 min |
| 13 | Messages & notifications | 1.5 min |
| 14 | AI assistant | 2 min |
| 15 | Wound photos portal | 1 min |
| 16 | MA locations map | 1 min |
| 17 | MA productivity report | 1 min |
| 18 | Audit log | 1 min |
| 19 | Manage staff & roles | 1 min |
| 20 | Settings | 30 sec |
| 21 | User profile & saved signature | 1 min |
| 22 | Mobile experience | 2 min |
| 23 | Offline mode & PWA | 1 min |
| 24 | Practice Fusion integration | 30 sec |
| 25 | ROI close | 1 min |
| — | Q&A buffer | 5 min |
| **Total** | | **~38 min** |

> **Tip:** For a 20-minute demo, skip Parts 9, 12, 16, 17, 20, 24 and speed through Part 7.

---

## PART 1 — The Problem We Solve (1 minute, no clicking)

> **Say:**
> "Today, your MAs and PCCs carry a folder of paper forms to every visit. Consent sheets. Medication lists. ABN forms. ICD-10 code lookups. They fill them out by hand, photograph them on a personal phone, and upload them to the EMR sometime later — sometimes the next day.
>
> Things get lost. Photos are blurry. Billing gets held up waiting for a legible ICD code. And there's no way to know if a consent was actually signed.
>
> PaperlessMD replaces every one of those paper forms. Every form is digital, every signature is captured in real time, and every record is stored the moment the MA clicks Submit. Let me show you what that looks like in practice."

---

## PART 2 — Login & Security (1 minute)

**Action:** Show the login page at `https://docs.md-officesupport.com`.

**Point out:**
1. **Username + Password** — bcrypt-hashed passwords, no plain-text storage
2. **Rate limiting** — after 5 failed attempts the account is locked; an email alert fires
3. **HTTPS** — padlock in the address bar — all traffic is encrypted in transit

> **Say:** "Security is not an afterthought. Every login attempt is logged. Failed logins trigger alerts. All data travels over TLS."

**Action:** Log in as admin. Land on the Dashboard.

---

## PART 3 — Dashboard (2 minutes)

**Point out each element:**

1. **Personalized greeting** — "Good morning, [Name]" — role-aware and timezone-synced
2. **Today's stats row:**
   - Forms collected today
   - Wound photos today
   - Forms pending upload to Practice Fusion
   - Forms awaiting provider e-signature
3. **Today's Schedule panel** — up to 6 visits with patient name, address, phone, visit type, status badge (Pending / En Route / Completed)
4. **Draft forms panel** — unsigned forms flagged by age:
   - Amber = under 1 hour old
   - Red = overdue
   - "These are forms started but not yet signed — the MA is mid-visit or forgot to submit"
5. **(Admin only) Weekly Analytics** — bar chart of form submissions and completed visits per day for the past 7 days

> **Say:** "From here an admin knows exactly what the whole team is doing without making a single phone call. The MA sees only their own schedule and drafts."

---

## PART 4 — Global Search (30 seconds)

**Action:** Press **Ctrl + K** (or click the search icon in the top bar).

1. The **global search modal** opens with a text field
2. Type the test patient's last name — **patient results** appear instantly below
3. Type a form type keyword like "vital" — **recent forms** matching that type appear
4. Use **arrow keys** to navigate results, **Enter** to open
5. **Escape** to close

> **Say:** "Search across patients and forms without navigating menus. Works from any page."

---

## PART 5 — Patient List (1 minute)

**Action:** Click **Patients** in the sidebar.

1. **Search bar** — type the test patient's last name; results filter instantly (no page reload)
2. **Status filter tabs** — All / Active / Inactive / Discharged — click each to show how the count changes
3. **MA filter** (admin only) — dropdown to show patients assigned to a specific MA
4. **Add Patient button** — top-right corner

> **Say:** "Every MA sees only their assigned patients. The admin sees everyone, with filters to drill in by status or MA."

---

## PART 6 — Adding a New Patient (2 minutes)

**Action:** Click **Add Patient**.

Walk through the form fields:

1. **Name, DOB, Phone, Email, Address** — standard demographics
2. **Insurance** + **Insurance Member ID** — separate fields so the ID is always machine-readable
3. **Insurance Card Photos** — click "Upload Photo" for front and back:
   - Select an image → thumbnail appears instantly (no page refresh — FileReader API)
   - "These are stored securely and displayed on the patient's profile for staff reference"
4. **SSS / Government ID Photo** — same upload flow
   - "Especially useful for home-visit patients who aren't always able to bring their card to every visit"
5. **Race / Ethnicity** — select from standardized list (meets CMS reporting requirements)
6. **PCP** — free text
7. **Pharmacy Details** — Name, Phone, Address:
   - "Pre-fills every future form automatically — the MA never types the pharmacy name twice"
8. **Assigned MA** — admin can assign any active MA from the dropdown
9. Click **Save**

> **Say:** "One setup, zero re-entry. Every field you fill here pre-populates into the visit forms and CCM consents going forward."

---

## PART 7 — Patient Profile (4 minutes)

**Action:** Click the test patient "John Demo" to open their profile.

### Summary tab (top section)
- **Photo** — patient photo (if uploaded)
- **Name, DOB, Insurance** — key demographics at a glance
- **Info pills** — address, PCP, race/ethnicity, insurance member ID, pharmacy name + phone
- **Documents on file** — thumbnail row for insurance card (front/back) and SSS photo
  - Click a thumbnail → **full-screen lightbox** — tap anywhere to dismiss
- **Status buttons** — Active / Inactive / Discharged — changing to Discharged prompts a date picker; requires password confirmation

### Forms tab
1. Every submitted form listed with date, form type badge, status (Draft / Signed / Uploaded)
2. **PDF icon** — opens the print-formatted version in a new tab
3. **Push to PF icon** — one click to upload to Practice Fusion
4. **Duplicate prevention** — if a form of the same type was already signed today, the system blocks a duplicate and redirects to the existing form

### Medications tab
1. **Active medication list** — med name, frequency, type (Maintenance / Refill / New / D/C)
2. **Edit** any med inline — pencil icon
3. **Discontinue** — marks the med inactive; it stops pre-filling forms
4. **Medication history** — click the clock icon on any med to see the full edit/discontinue log with timestamps and the staff member who made each change
5. **Add medication** button at the bottom

> **Say:** "This is the master medication list. Every visit form pre-fills from here. When a provider discontinues a med, it stops appearing on future forms automatically."

### Vitals History tab
1. **Trend charts** — line graphs for:
   - Blood pressure (systolic / diastolic)
   - Pulse
   - O2 saturation
   - Weight
2. Each point on the chart is a visit — hover for the exact value and date
3. **Raw data table** below the chart — every vital from every visit, newest first

> **Say:** "At a glance the provider sees if the patient's BP has been trending up over 6 visits. No digging through paper charts."

### Wounds tab
1. **Wound measurements** — length × width × depth for each wound site, per visit
2. **Calculated area** next to each measurement
3. **Trend** — each wound site shows whether it is improving or worsening over time
4. **Add measurement** — site, date, L/W/D, notes

### Photos tab
1. All wound photos grouped by wound location
2. **Lightbox viewer** — click any photo to expand; prev/next arrows
3. **Compare mode** — select two photos of the same site side-by-side to show healing progress

> **Say:** "Wound photos are geo-stamped with the visit date. Providers can see healing progression at a glance instead of hunting through paper files."

### Diagnoses tab
1. **Active ICD-10 diagnoses** listed with code and description
2. **Add diagnosis** — same live search as the visit form
3. **Remove** — soft-delete, stays in history

### SOAP Notes tab
1. List of all SOAP notes — date, author, status (Draft / Final)
2. Click a note to view the full Subjective / Objective / Assessment / Plan
3. **Draft → Final** status transition
4. **AI Assist** badge — AI-drafted notes appear highlighted until a provider reviews

### Care Notes tab
1. **Threaded coordination notes** — e.g. "Called pharmacy re: prior auth, waiting on callback"
2. Staff can reply in-thread — full conversation history per patient
3. Admin can **pin** important notes so they always show at the top
4. **Edit / Delete** own notes

### Patient Timeline tab
1. **Chronological feed** — all form submissions, photos, vitals, wounds, care notes in one scroll
2. Color-coded by event type
3. Filter by date range

### Audit tab (admin only)
1. Every action taken on this patient — who viewed, who edited, who submitted forms
2. Timestamp + IP address for every event

---

## PART 8 — Starting a Visit Form — Full Wizard (6 minutes)

**Action:** From the patient profile, click **New Visit Form** → **Vital CS**.

> **Say:** "This is the bread and butter of the system — the visit form. Let me walk you through every step."

### Step 0 — Visit Info
1. **Provider field** — start typing a name; autocomplete suggests from the staff list
2. **Date of Visit** — defaults to today; tap to change for a backdated entry
3. **Visit Type** — New / Follow Up / Sick / Post Hospital F/U — radio buttons, one tap
4. **Time In** — auto-populated with the current time (timezone from system settings)
5. Point to the **progress bar** at the top — "8 steps, always shows where you are — no surprises"

### Step 1 — Chief Complaint & ICD-10
1. **Chief Complaint field** — click the mic icon to use **voice dictation**: say "Sacral wound stage two with serosanguineous drainage" — it transcribes in real time
2. Type in the ICD-10 search box: "sacral pressure" — live results from the wound-care library appear
3. Click code **L89.152** — it becomes a chip above the search box
4. Add a second code — show the **max-6 limit**
5. Click **AI Suggest** — the AI reads the chief complaint and recommends 2–3 additional codes with reasoning
6. Show **removing a chip** — click the × on any code

> **Say:** "Voice dictation works on every text field in every form. No keyboard required."

### Step 2 — Interactive Vitals
1. Click the **Blood Pressure** vital card — the numpad slides in below
2. Tap the **preset button** `120/80` — fills the field and auto-advances to Pulse
3. Tap a Pulse preset (`72`) — advances to Temperature
4. Continue through Temp, O2 Sat, Respiratory Rate — show **auto-advance** between vitals
5. Click **Set Normal Values** — fills all remaining vitals with standard normals in one tap
6. Point to the **progress dots** inside the numpad — filled dots show which vitals are complete
7. Show **manual entry** — click the field and type directly if needed
8. Show **Weight** and **Height** — BMI calculates automatically

> **Say:** "For a stable patient, the MA sets normals and is done with vitals in under 15 seconds. For a complex patient, every entry is one tap."

### Step 3 — Medications
1. **Pre-filled rows** — the patient's active medications appear automatically, highlighted in amber
2. Show the **frequency quick-pills** — click in the Frequency field and pills appear: QD / BID / TID / QID / PRN / Weekly — tap one to fill
3. **Type dropdown** — Maintenance / Refill / New / D/C — show how D/C is styled differently
4. **Add Row** — adds a blank row at the bottom
5. **Delete row** — trash icon on any row
6. **Handwriting pad** — scroll to the bottom; show the canvas pad for stylus/finger medication entry
7. **Add Another Drawing** — adds a second pad (up to 5) for patients with many medications
8. **Upload PDF** — click "Upload Medication PDF" to attach a scanned med list as a PDF; filename appears as confirmation; can be removed before submit

> **Say:** "The pre-fill saves 3–5 minutes per visit. The handwriting pad handles the MAs who prefer to write rather than type — we support both workflows."

### Step 4 — Allergies & Homebound
1. **Allergy field** — free text, mic button available
2. **Homebound reason** checkboxes — select one or more standard CMS homebound criteria
3. **Additional notes** — free text with voice dictation

### Step 5 — CCM / Care Management Consent
1. Show the **4 CCM checkboxes** — pre-checked by default (consent, electronic access, one-only, copay acknowledgment)
2. Patient signs here if not already captured

### Step 6 — ABN (Advance Beneficiary Notice)
1. **Procedure description** — pre-filled from chief complaint
2. **Cost estimate** field
3. **Patient acknowledgment** — checkbox + signature

### Step 7 — Additional Consents
1. **Wound care consent** — general authorization
2. **IL PHI disclosure** — Illinois-specific health information authorization
3. **Patient Fusion portal** — patient opt-in to the portal, email capture for account setup

### Step 8 — Signatures
1. **Patient signature canvas** — draw with mouse or finger; show clear button
2. **POA toggle** — flip on if a guardian is signing; fields appear for guardian name and relationship
3. **MA signature** — if a pre-saved signature is on file, an **"Auto-fill: ON"** badge appears and the signature draws automatically
4. Toggle Auto-fill off to show drawing manually instead
5. **Provider signature block** — same auto-fill behavior; provider can countersign live or it stays pending in the sign queue
6. Click **Submit & Save**

> **Say:** "Three signatures captured digitally. No paper, no scanning, no uploading later. The form appears in the sign queue and the patient's record instantly."

---

## PART 9 — Other Form Types (2 minutes)

**Action:** From the patient's Forms tab, click **New Form** and show the form type picker.

Walk through each type briefly:

| Form | Key point to mention |
|---|---|
| **New Patient Consent** | Full intake — demographics confirm, privacy notice, emergency contact, insurance. All pharmacy and race fields pre-fill from the patient record. |
| **New Patient Pocket** | Condensed intake — same pre-fill; requires provider signature; good for follow-up patients switching to wound care |
| **ABN** | Standalone ABN when billing flags a non-covered service mid-cycle |
| **Medicare Annual Wellness Visit (AWV)** | GDS-15 depression screen, cognitive assessment, medication reconciliation, risk stratification — all in one step |
| **Cognitive Wellness Exam** | Full dementia screening — MoCA-adapted, functional assessment, care plan |
| **CCM Consent** | Standalone CCM authorization for care coordination enrollment |
| **Informed Consent – Wound Care** | Procedure-specific consent before starting wound treatment |
| **RPM Consent** | Remote patient monitoring authorization for wearables and daily data capture |
| **IL DHS Authorization** | Illinois state-required PHI disclosure authorization |
| **PF Portal Signup** | Practice Fusion portal enrollment — standalone if missed during visit |
| **Wound Care Photos** | Camera-first photo capture — supports camera roll or live camera, geo-tagged, multiple photos per wound site |

> **Say:** "One system handles every consent, every intake form, every state-required authorization. The MA picks the form type and the workflow guides them through it."

---

## PART 10 — Provider Sign Queue (1.5 minutes)

**Action:** Click **Sign Queue** in the sidebar.

1. The form just submitted appears here — **date, patient name, form type badge, submitting MA**
2. **Filter bar** — filter by form type (Vital CS / AWV / CCM / etc.) and date range (Today / This Week / All)
3. Click the form — opens the read-only view of the entire completed form
4. Scroll to the signature section — click **Sign** — provider draws or uses their saved signature
5. Show the **countersignature timestamp** and provider name on the form after signing
6. Show that the form disappears from the queue and updates to **"Signed"** status in the patient's record

> **Say:** "The provider doesn't need to be at the visit. They review and countersign from their phone on the way to the next patient. Total time: under 60 seconds."

---

## PART 11 — Schedule (2 minutes)

**Action:** Click **Schedule** in the sidebar.

### Day View
1. **Visit cards** — each card shows patient name, address, phone, visit type, time, status badge
2. **Status badges** — Pending → En Route → Completed → Missed — tap to change status in one click
3. **Visit notes** — click the notes icon on any card to add or view visit notes
4. **Phone/address links** — tap the phone number to call, address to open in Maps

### Week View
1. Toggle to **Week** — Mon–Sun grid; each day shows a compact visit count
2. Click any day to jump to that day's detail view

### (Admin) MA Filter
1. The **MA dropdown** at the top — shows all active MAs; select one to see only their schedule
2. Or select **"All MAs"** — see the full team's day at once

> **Say:** "MAs arrive on their first patient knowing exactly where they're going. Admins see the full team without a single phone call."

---

## PART 12 — Schedule Management & Recurring Visits (1 minute)

**Action:** Admin → **Manage Schedule** (under Admin section in sidebar).

1. **Day picker** — select a date; the current visit list for that day appears
2. **Add visit** — patient search, MA assignment, visit time, visit type
3. **Reorder** — drag visits up/down to adjust the route order
4. **Delete** a visit — with confirmation

**Action:** Click **Recurring Schedule**.

1. Set a patient + MA + visit type + day(s) of week + frequency (weekly / biweekly / monthly)
2. Set a **start date** and either an **end date** or a **max occurrence count**
3. Click Save — future visit slots generate automatically; admins can still edit individual instances

> **Say:** "For wound care patients seen every week, you set the recurring rule once and never touch it again. The schedule manages itself."

---

## PART 13 — Messages & Notifications (1.5 minutes)

**Action:** Click **Messages** in the sidebar.

1. **Conversation list** — left panel, sorted by most recent; **unread count badge** on each thread
2. Click a conversation — **threaded message view** on the right
3. **Send a message** — type and hit Enter; shows instantly
4. **Attachment button** — attach wound photos, PDF forms, or any file
5. **Broadcast** option — send a message to all staff at once (admin feature)

**Notifications:**
1. Point to the **bell icon** in the top bar — click it to open the notification drawer
2. Drawer shows:
   - Unread messages
   - Forms pending provider signature
   - Forms pending Practice Fusion upload
   - Old draft forms (unsaved work)
3. **Auto-refreshes every 60 seconds** — no manual refresh needed
4. Email alerts fire for: new message, form awaiting signature, completed countersignature, visit scheduled

> **Say:** "No texting patient information on personal phones. All clinical communication happens here — logged, searchable, and HIPAA-safe."

---

## PART 14 — AI Assistant (2 minutes)

**Action:** Click the **blue pulse icon** in the bottom-right corner.

1. The **AI chat panel** slides up from the bottom
2. **Persistent history** — previous conversations are remembered; new session clears automatically

### Demo 1 — Clinical coding question
Ask: *"What ICD-10 code should I use for a stage 3 pressure ulcer on the left heel?"*
- Show the response — accurate code (L89.622), clinical reasoning, documentation tips
- Click **Copy** to copy the answer to clipboard

### Demo 2 — SOAP note draft
1. Open a completed visit form in another tab
2. Return to AI chat and ask: *"Draft a SOAP note based on today's visit"*
3. AI reads the form data and returns a structured S/O/A/P note
4. Click **Insert into SOAP** — the note appears in the SOAP Notes tab for the provider to finalize

### Demo 3 — Wound description
Ask: *"Write a clinical wound description for a 2.5 x 1.8 x 0.3 cm sacral pressure injury with slough"*
- AI returns a ready-to-paste clinical narrative

### Demo 4 — ICD-10/CPT suggestions
Ask: *"What CPT codes apply to wound debridement with a 5 cm wound?"*
- AI returns CPT options with billing notes

> **Say:** "The AI is purpose-built for wound care documentation. It's not just a generic chatbot — it understands clinical terminology, CMS billing rules, and the specific workflows your MAs use every day."

---

## PART 15 — Wound Photos Portal (1 minute)

**Action:** Admin → **Wound Photos** in the sidebar.

1. **Gallery view** — all wound photos across all patients, newest first
2. **Filters** — filter by patient name, MA, wound site, date range
3. Click any photo — **lightbox viewer** with patient name, upload date, MA name, wound site
4. **Prev/Next navigation** within the lightbox
5. Download icon — save any photo for a referral or insurance submission

> **Say:** "The wound photo portal is an admin-level view of every photo across every patient — useful for QA reviews, insurance submissions, or tracking healing outcomes at a practice level."

---

## PART 16 — MA Locations Map (1 minute)

**Action:** Admin → **MA Locations**.

1. **Leaflet map** — pins for every active MA who has shared their location
2. **Color-coded status:**
   - Green — active, recently updated
   - Amber — location stale (over 10 min)
   - Grey — location not available
3. Click a pin — shows MA name, last known address, time of last update
4. **Sidebar list** — all MAs listed with their current status badge
5. Location updates automatically every 5 minutes from the MA's device (opt-in geolocation)

> **Say:** "For a multi-MA home-visit team, this tells you exactly where everyone is without calling anyone. If an MA hasn't updated in 30 minutes you know something may be wrong."

---

## PART 17 — MA Productivity Report (1 minute)

**Action:** Admin → **MA Productivity Report**.

1. **Period selector** — Today / This Week / Last 7 Days / Last 30 Days / Custom date range
2. **Per-MA row** showing:
   - Total visits completed
   - Total visits missed
   - Unique patients seen
   - Forms submitted
   - Wound photos uploaded
   - Active days in period
3. **Daily sparkline** — tiny bar chart per MA showing day-by-day output
4. **Exportable** — download the current view as CSV

> **Say:** "One page tells you who the top performers are, who is missing visits, and which MAs need support — without reviewing every timesheet manually."

---

## PART 18 — Audit Log (1 minute)

**Action:** Admin → **Audit Log**.

1. **Full-system action log** — every action taken by every user
2. **Columns:** Timestamp, User, Role, Action (view/add/edit/delete/login/logout/form_submit), Target (patient name or form ID), IP address
3. **Filters** — by action type, by user, by date range
4. **Search** — free-text search across all log entries
5. **CSV Export** — download the filtered log

> **Say:** "If a compliance auditor asks who accessed a specific patient record on a specific date, you have the answer in 10 seconds. Every access is logged — not just edits."

---

## PART 19 — Manage Staff & Roles (1 minute)

**Action:** Admin → **Manage Staff**.

1. **Staff list** — all users with name, role, last login, active/inactive toggle
2. **Add Staff** button — opens the add user form
3. **Edit** a user — change name, password, role, active status
4. **Deactivate** — one click; the user cannot log in but their records are preserved

**Action:** Admin → **Roles & Permissions**.

1. **Visual permissions matrix** — rows = roles, columns = permissions
2. Roles available: **Admin, MA, PCC, Provider, Scheduler, Billing**
3. Point out key permission differences:
   - Billing: forms and ICD codes only — no clinical vitals or photos
   - Scheduler: manage schedule only — no patient clinical data
   - Provider: sign queue, patient clinical data — no admin tools
   - PCC: same as MA with care coordination notes access

> **Say:** "Access is locked down by role from day one. A billing user literally cannot see vitals or wound photos — it's not hidden, it's absent."

---

## PART 20 — Settings (30 seconds)

**Action:** Admin → **Settings**.

1. **Timezone** — select the practice timezone; all timestamps across the system adjust
2. **Practice Name** — displayed in form headers and PDFs
3. **Session timeout** — how long before inactive users are logged out automatically

> **Say:** "Three settings. The system handles everything else."

---

## PART 21 — User Profile & Saved Signature (1 minute)

**Action:** Click the user avatar/name in the sidebar → **Profile**.

1. **Display name** — update the name shown in form headers and messages
2. **Change password**
3. **Saved Signature section:**
   - **Draw tab** — draw the signature with mouse or finger on the canvas
   - **Upload tab** — upload a PNG/JPG of a wet signature
   - Click **Save Signature**
4. Return to a form — show the MA signature block auto-filling with the saved signature; **"Auto-fill: ON"** badge
5. Toggle off to draw manually if the MA wants to override for a specific form

> **Say:** "Every MA sets their signature once during onboarding. Every form after that — automatic. Saves 30 seconds per form times 20 patients a day."

---

## PART 22 — Mobile Experience (2 minutes)

**Action:** Open the URL on a phone browser or pass the tablet to the audience.

1. **Top bar** — hamburger menu, global search icon, notification bell — everything reachable in one thumb
2. **Sidebar** — tap the hamburger; full navigation slides in from the left
3. **Patient list** — tap a patient name; profile loads instantly
4. **New Visit Form** — start a form on the phone:
   - Step progress bar reflows to fit the small screen
   - **Sticky bottom bar** — Back / Next buttons are always visible, no scrolling to find them
   - **Vitals numpad** — large tap targets, optimized for thumbs; presets are full-width buttons
   - **Medication rows** become stacked cards on mobile — easier to read and tap
   - **Signature canvas** fills the screen width; sign with a finger
5. **Wound Photos form** — camera launches natively; photo uploads directly to the patient record
6. Show the **PWA install prompt** — "Add to Home Screen" — launches like a native app with no browser chrome

> **Say:** "MAs can run a complete visit — from check-in to signed form — on a $200 Android phone. No special hardware required."

---

## PART 23 — Offline Mode & PWA (1 minute)

**Action:** On the phone/tablet, put the device in **Airplane Mode**.

1. Reload the app — it still loads from the service worker cache
2. Navigate to a patient, start a form — the form wizard works completely offline
3. Fill out vitals and medications — the draft is saved to the device's local storage (IndexedDB)
4. A **sync counter badge** appears in the sidebar: "2 pending uploads"
5. Turn Airplane Mode back off — the badge clears as the sync fires automatically in the background

> **Say:** "Home visit in a rural area with no signal? The app keeps going. The moment the MA drives back into range, everything syncs. They never lose a form."

---

## PART 24 — Practice Fusion Integration (30 seconds)

**Action:** Open any submitted form → click the **Push to PF** button.

1. System searches Practice Fusion for a matching patient by name and DOB
2. Confirmation modal — confirm the PF patient match
3. Click **Upload** — the form is sent to the patient's Practice Fusion chart as a document
4. Timestamp updates to **"Uploaded to PF"** with the date/time

> **Say:** "For practices using Practice Fusion as their primary EMR, PaperlessMD is a perfect companion — forms collected in the field, pushed to the main chart with one click."

---

## PART 25 — Closing — The ROI Pitch (1 minute)

> **Say:**
> "Let me put numbers on what you just saw.
>
> Before PaperlessMD: an MA spends 12–15 minutes on paperwork per patient visit. Filling forms by hand. Photographing them. Uploading them. Waiting for a provider to sign a paper. Billing waits until the next day for ICD codes.
>
> After PaperlessMD: the same visit takes under 4 minutes of form work. Meds pre-fill. Vitals take 15 seconds. Signatures are instant. The provider countersigns on their phone. Billing has the codes the same hour.
>
> For a team seeing 20 patients a day, that's over 3 hours saved — every single day. That's 60+ hours a month. That's one full-time MA-hour you get back, every day, without adding a single person.
>
> And that's before we talk about zero lost consents, zero illegible signatures, and an audit trail that takes 10 seconds to produce for any compliance request.
>
> That's PaperlessMD."

---

## Q&A CHEAT SHEET

| Question | Answer |
|---|---|
| **Is it HIPAA compliant?** | Yes. All data is encrypted in transit (HTTPS/TLS). Every access is audit-logged. No PHI is sent to third parties except the AI, which receives only de-identified clinical text. Role-based access means staff only see what they need to. |
| **Where is the data stored?** | On your own server — you control the database. There is no shared multi-tenant cloud. Data never leaves your infrastructure. |
| **What if the MA loses internet mid-visit?** | The service worker caches the app. Forms save to IndexedDB on-device. Sync fires automatically when connectivity returns. Nothing is lost. |
| **Can providers sign from their own device?** | Yes — the sign queue works on any device, any browser, no app install required. |
| **How does billing access work?** | The Billing role sees submitted forms and ICD-10 codes only. Vitals, wound photos, and clinical notes are not visible to billing users — by design, not by configuration. |
| **Can we customize the ICD-10 code library?** | Yes — the wound-care ICD-10 library is a JSON file that an admin can update without touching any code. |
| **Is there a patient portal?** | Patient Fusion portal enrollment is handled inside the form wizard. A standalone patient-facing portal is on the roadmap. |
| **How are signatures stored?** | As base64 PNG embedded in the form record, attached permanently to the submission. Available in every PDF export. |
| **Can we add more form types?** | Yes — the form template system is extensible. New form types are added as PHP wizard files following the existing pattern. |
| **What happens if a form needs to be amended?** | Forms can be amended post-submission (tracked separately as an amendment, not an overwrite). The original signature and original submission are preserved for the audit trail. |
| **Does it work on iOS?** | Yes — Safari on iPhone/iPad is fully supported. The PWA install flow works on iOS 16.4+ via "Add to Home Screen." |
| **How many users can use it at once?** | No hard limit — it's a standard PHP/MySQL app on your server. Scales with your hosting tier. |
| **What AI model does it use?** | Google Gemini API. The integration is in a single endpoint file (`api/ai.php`) and can be swapped to any provider. |
| **Is the source code available?** | Yes — the repo is at `github.com/ymathsky/paperlessmd`. Self-hosted and fully open. |

---

*Demo prepared April 30, 2026 — PaperlessMD v2.1*
*Practice: Beyond Wound Care Inc.*
