PaperlessMD. Live Demo Speaker Script. Beyond Wound Care Inc.


PART ONE. The Problem We Solve.

Before I show you anything on screen, let me describe what I'm replacing.

Right now, your Medical Assistants carry a folder of paper forms to every visit. Consent sheets. Medication lists. ABN forms. ICD-10 code lookups. They fill them out by hand, photograph them on a personal cell phone, and upload them to the EMR sometime later — sometimes the next day.

Things get lost. Photos are blurry. Billing gets held up waiting for a legible ICD code. And there's no way to know if a consent was actually signed.

PaperlessMD replaces every one of those paper forms. Every form is digital, every signature is captured in real time, and every record is stored the moment the Medical Assistant clicks Submit.

Let me show you what that looks like.


PART TWO. Login and Security.

This is the login page at ecpaperlessmd.com — it's live right now.

Passwords are bcrypt-hashed — no plain-text storage anywhere. After five failed login attempts the account locks automatically and an email alert fires.

Everything runs over HTTPS. All patient data is encrypted in transit.

Let me log in as the admin account.


PART THREE. Dashboard.

This is the dashboard — the first thing anyone sees when they log in.

The greeting at the top knows who you are and your timezone. If you have visits scheduled for today, a banner appears at the very top of the main column — it shows how many visits and links directly to the schedule.

Below that is today at a glance: forms collected, wound photos taken, forms waiting on a provider signature.

The Quick Actions row gets you anywhere in one tap — New Patient, Find Patient, Pending Upload, All Patients.

Down here is Today's Route. Each visit card shows the patient name, address, phone number, visit type, and status. The Medical Assistant sees their own visits. The admin sees the whole team. Each card has a one-tap Start button right here — tap it and the visit goes En Route and the correct form opens immediately. No navigating to the patient chart first.

The Draft Forms panel flags any form that was started but not finished. Amber means it's under an hour old — probably mid-visit. Red means it's been sitting there and someone needs to follow up.

Over on the right — Team Notes. Admins post sticky notes here that the whole team can read. Think of it as a pinned announcement that doesn't get buried in a text thread.

And at the bottom, admins get a weekly analytics chart — form submissions and completed visits per day for the past seven days.

From this one page, an admin knows exactly what the whole team is doing without making a single phone call.


PART FOUR. Global Search.

One thing I want to show you quickly — global search. Press Control-K from any page.

Type a patient's last name — results appear instantly. Type a form type like vital — recent matching forms come up. Arrow keys to navigate, Enter to open, Escape to close.

You're never more than two keystrokes away from any patient or any form, from anywhere in the system.


PART FIVE. Patient List.

The Patient List. Every Medical Assistant sees only their assigned patients. The admin sees everyone.

You can filter by status — Active, Inactive, Discharged — or by Medical Assistant. The search bar filters in real time as you type. No page reloads, no waiting.


PART SIX. Adding a New Patient.

Let me add a new patient to show you the intake process.

Name, date of birth, phone, email, address — standard demographics. Insurance carrier and Member ID are separate fields so the ID is always machine-readable for billing.

Insurance card photos — front and back — upload right here. The thumbnail appears instantly. Same for a government ID or SSS card. These are stored securely and visible on the patient's profile so Medical Assistants don't have to ask for the card at every visit.

Here's the part that saves the most time: the Pharmacy section. Name, phone number, address. You fill this in once when you add the patient.

Every visit form, every consent, every medication list from this point forward pre-fills the pharmacy automatically. The Medical Assistant never types the pharmacy name again.

Same for the PCP. Fill it once here, it appears on every form.

The assigned Medical Assistant is set from the dropdown. Click Save.


PART SEVEN. Patient Profile.

This is the patient profile. Everything about this patient in one place.

At the top — photo, name, date of birth, insurance, Member ID, PCP, pharmacy, address. Documents on file: insurance card front and back, SSS photo. Click any thumbnail and it opens full screen.

Let me take you through the tabs.

Forms tab — every form ever submitted for this patient. Date, form type, status. Click the PDF icon to open a print-formatted version. If someone tries to start a duplicate form for the same visit today, the system catches it and redirects them to the existing one.

Medications tab — this is the master medication list. Active meds with frequency and type. When a provider discontinues a medication here, it stops appearing on future forms automatically. Click the clock icon on any med to see the full edit history — who changed it and when. This is your audit trail for medication reconciliation.

Vitals History tab — line graphs for blood pressure, pulse, O2 sat, and weight. Every point is a real visit. Hover a point and you see the exact value and date. Below the chart is the raw data table, newest first. At a glance the provider can see if BP has been trending up over six visits — without touching a single paper chart.

Wounds tab — measurements per wound site, per visit. Length by width by depth. Calculated area. The trend line shows whether each wound is improving or worsening over time.

Photos tab — all wound photos for this patient, grouped by wound location. Click any photo to open it full screen in the lightbox viewer. I'll deep-dive the wound analysis and annotation tools in a moment.

Diagnoses tab — active ICD-10 codes. Add, search, remove. Stays in sync with the visit form.

SOAP Notes tab — all provider notes. Draft and Final status. The AI can draft a SOAP note from a completed visit form.

Care Notes tab — coordination notes. Not clinical documentation — more like called the pharmacy about a prior auth, waiting on callback. Threaded replies, admin can pin the important ones.

Audit tab — every action on this patient. Who viewed it, who edited it, who submitted forms. Timestamp and IP address for every event. A compliance auditor asks who accessed this patient on a specific date — you have the answer in ten seconds.


PART SEVEN-B. Wound Photo Analysis and Annotation.

Let me click into this wound photo.

The lightbox opens full screen, with pinch-to-zoom and pan. The top-left toggle switches between Original and Annotated views. Top-right is the Analyze button.

Click Analyze.

The AI measures the wound. It scans for a ruler in the frame. One is visible here — Ruler detected badge appears. Results come back: area in square centimeters, length, and width. Below that — tissue composition. Granulation, slough, eschar, each as a percentage, visualized as a segmented color bar. The method badge shows GPT-4o, high confidence.

Now here's the part that matters clinically.

Click Edit. The provider enters their own bedside measurements — length and width. Click Save.

Watch the sidebar. A second section appears immediately below the AI Analysis section. It's labeled Clinical Measurements. It shows what the staff member entered, and right next to it — their name, their role, and today's date.

So you have two rows. AI Analysis, GPT-4o, area 6.72 square centimeters, length 3.2, width 2.1, ruler detected. And Clinical Measurements, Dr. Smith, Provider, area 6.00 square centimeters, length 3.0, width 2.0, May 19th 2026.

Both are stored independently. The AI result is never overwritten. This is your audit trail — AI measured one value, the clinical team documented another, both preserved with attribution. PCC, Medical Assistant, Provider — whoever saves the measurement gets credited.

Now for annotation. Click the Draw button.

Five tools: pen for freehand, arrow, rectangle, eraser, and clear. Five colors — black, red, green, yellow, white.

I'll switch to the arrow tool and drop an arrow on the wound edge. Change to the pen, circle the periwound inflammation in red. Drop a rectangle around the reference ruler.

Click Save Annotation — those strokes composite directly onto the original image at full resolution. The Annotated toggle is now active. Original and Annotated — flip between them at any time.

The annotated image is stored permanently with the photo. When you pull this photo for an insurance prior auth or a wound care conference, the markups are already there — no screenshots, no separate markup tool.


PART EIGHT. Visit Form. Full Wizard.

This is the core of the system — the visit form. Let me walk you through every step.

You'll see the progress bar at the top. Four steps. The Medical Assistant always knows where they are.

Step one — Visit Info. Provider, date of visit, visit type — New, Follow Up, Sick, Post Hospital. Time In defaults to right now. Homebound status — one tap to confirm the patient qualifies for home visits. Missed visit reason if applicable.

Step two — Vitals. Click the Blood Pressure card and a numpad slides in. Tap the preset — 120 over 80 — it fills and automatically advances to Pulse. Tap 72, advances to Temperature. You work through all the vitals in sequence.

For a stable patient, tap Set Normal Values — fills every remaining vital with standard normals in one tap. Under fifteen seconds for vitals on an uncomplicated patient.

The progress dots inside the numpad show which vitals are complete. BMI calculates automatically from weight and height.

Step three — Medications and Allergies. Allergies and assistive device at the top — free text, one field each. Below that, the patient's active medication list pre-fills automatically, highlighted. The Medical Assistant reviews each entry, adjusts frequency if needed — one tap on a quick-select: New, Refill, or Discontinue.

Scroll down to the handwriting pad. For Medical Assistants who prefer to write rather than type — they draw the medication list on this canvas with a stylus or their finger. Or upload a medication PDF and annotate it directly in the browser — no download needed.

We support both workflows — typed, handwritten, or annotated PDF. Whatever the Medical Assistant is comfortable with.

Step four — Sign. Time Out field records when the visit wrapped up. Right above the signatures is an AI SOAP Note button — one tap drafts a structured Subjective, Objective, Assessment, Plan note from today's visit data for the provider to finalize.

Patient signature canvas — draw with a finger or stylus. If a guardian is signing, flip the POA toggle and two fields appear: guardian name and relationship.

Medical Assistant signature — if the Medical Assistant has a saved signature on file, you see the Auto-fill ON badge and the signature draws automatically. Toggle it off if the Medical Assistant wants to sign manually for this form.

Provider signature block — same auto-fill if the provider has a saved signature. If not, it goes to the sign queue for countersignature later.

Click Submit and Save.

Three signatures captured digitally. No paper, no scanning, no uploading later. The form is in the patient's record and the sign queue the moment that button is clicked.

The pre-fill alone saves three to five minutes per visit. For a team seeing twenty patients a day, that's over an hour saved before we count anything else.


PART NINE. Other Form Types.

Let me show you the form library quickly.

Beyond the standard visit form, the system handles: New Patient Consent, ABN, Medicare Annual Wellness Visit with GDS-15 depression screen, Cognitive Wellness Exam, CCM Consent, Informed Consent for Wound Care, RPM Consent for remote monitoring, and Illinois DHS Authorization.

Wound Care Photos — camera first. The Medical Assistant takes photos directly into the form, tagged with the wound site and visit date.

One system. Every consent, every intake, every state-required authorization. The Medical Assistant picks the form type and the wizard guides them through it.


PART TEN. Provider Sign Queue.

This is the sign queue — where providers countersign.

Every form submitted without a provider signature lands here. You see the date, patient name, form type, and which Medical Assistant submitted it.

Click a form — it opens in read-only view. The provider reads the completed form, scrolls to the signature section, and clicks Sign. They draw or use their saved signature.

The countersignature timestamp and provider name appear on the form. It disappears from the queue and the status in the patient's record updates to Signed.

The provider doesn't need to be at the visit. They review and sign from their phone between patients. Total time — under sixty seconds.


PART ELEVEN. Schedule.

The schedule. Day view shows each visit as a card — patient name, address, phone, type, time, status.

Status badges go Pending, En Route, Completed, Missed — the Medical Assistant taps to update as the visit progresses. The admin sees it change in real time.

Phone numbers are tappable — tap to call. Address is a link — tap to open in Maps.

Toggle to Week view — Monday through Sunday, compact visit count per day. Click any day to jump to that day's detail.

For admins, the Medical Assistant dropdown at the top lets you see any individual's schedule or switch to All Medical Assistants for the full team view.

Each visit card has action buttons. For a pending visit — a green Start Visit button. Tap it — the system records the exact start time, marks the visit En Route, and opens the correct form immediately. New Patient visits go straight to the New Patient intake form. Follow-up, wound care, and all other visit types go straight to the Visit Consent form. One tap — visit started, time stamped, form open.

While the visit is in progress — an Open Forms button and a red End Visit button. Tap End Visit — the exact end time is recorded, the visit is marked Completed. Both start and end times appear right on the visit card. No more guessing when a visit actually happened.

Medical Assistants start their day knowing exactly where they're going. Admins see the full team without a single phone call.


PART TWELVE. Schedule Management.

Schedule management is under the Admin section.

Pick a date, see the current visit list. Add a visit — patient search, Medical Assistant assignment, time, type. Drag to reorder and optimize the route.

Recurring Schedule. Set a patient, an Medical Assistant, visit type, day of the week, frequency — weekly, biweekly, monthly. Set a start date and either an end date or a max occurrence count.

For wound care patients seen every week, you set the rule once. The schedule manages itself.

When a Medical Assistant has two or more visits for the day, an Optimize Route button appears next to their group. Click it — the system reorders the visits by geographic proximity to minimize total drive time. One click, entire day reorganized.


PART THIRTEEN. Messages and Notifications.

Messages. Two-panel layout — contact list on the left, conversation on the right.

At the top of the list is All Staff — broadcast a message to everyone logged in at once. Below that is every active staff member sorted by most recent message, with an unread count badge.

Click a staff member — private thread. Type and press Enter. Messages appear instantly. You can attach wound photos, PDFs, any file.

All timestamps are in clinic time. Not UTC, not whatever timezone the Medical Assistant's phone is set to. No mental math required.

The bell icon in the top bar is the notification drawer. Unread messages, forms pending signature, old drafts. Email alerts fire for new messages, forms awaiting signature, and completed countersignatures.

No texting patient information on personal phones. All clinical communication happens here — logged, searchable, and HIPAA-safe.

Video calling is built in. Click the camera icon next to any conversation to ring that staff member. The callee sees an incoming call bar slide in at the bottom of their screen — no matter what page they're on. Accept and a full-screen peer-to-peer video call opens. No third-party app, no account required. The call is logged in the conversation with a timestamp and duration.

If someone calls you while you are in the middle of documenting a wound or reviewing the schedule, the call bar appears wherever you are — you never miss it.


PART FOURTEEN. AI Assistant.

The AI assistant. Click the blue icon and the panel slides up.

Let me show you four things it can do.

First — clinical coding. I'll ask: What ICD-10 code should I use for a stage 3 pressure ulcer on the left heel? Watch the response — accurate code, clinical reasoning, documentation tips. One click to copy.

Second — SOAP note draft. I'll open a completed visit form in another tab, come back to the AI, and ask it to draft a SOAP note based on that visit. It reads the form data and returns a structured Subjective, Objective, Assessment, Plan. Click Insert into SOAP and it goes directly into the SOAP Notes tab for the provider to finalize.

Third — clinical wound description. Ask it to write a clinical description for a 2.5 by 1.8 by 0.3 centimeter sacral pressure injury with slough. Ready to paste into a referral or progress note.

Fourth — CPT codes. Ask what CPT codes apply to wound debridement on a 5 centimeter wound. It returns the options with billing notes.

This AI is not a general chatbot. It's purpose-built for wound care documentation. It understands clinical terminology, CMS billing rules, and the specific workflows your Medical Assistants use every day.


PART FIFTEEN. Wound Photos Portal.

The Wound Photos portal is an admin-level gallery of every wound photo across every patient.

Filter by patient name, Medical Assistant, wound site, or date range. Click any photo — the same lightbox viewer opens. Patient name, upload date, Medical Assistant, wound site. Previous and next arrows to navigate.

If AI analysis has been run or a staff member has entered clinical measurements, you see both sections right here in the sidebar — AI Analysis and Clinical Measurements with attribution. Admins can review measurement documentation across the whole practice without opening individual patient profiles.

Useful for QA rounds, insurance submissions, and tracking healing outcomes at a practice level.


PART SIXTEEN. Medical Assistant Locations Map.

The locations map. Every Medical Assistant who has shared their GPS location shows as a pin on the map.

The left panel shows each staff member with a color-coded dot. Green — active in the last ten minutes. Amber — active ten to sixty minutes ago. Grey — no recent activity.

Click a map pin and you see the Medical Assistant's name and when their location was last recorded.

Location updates every minute from the Medical Assistant's device. The map refreshes every sixty seconds.

For a multi-Medical Assistant home-visit team, this tells you where everyone is without calling anyone. If an Medical Assistant hasn't updated in thirty minutes, you know something may be wrong.


PART SEVENTEEN. Medical Assistant Productivity Report.

The productivity report. Pick a period — today, this week, last thirty days, or a custom range.

Each Medical Assistant gets a row: total visits completed, visits missed, unique patients seen, forms submitted, wound photos uploaded, active days in the period. Plus a sparkline bar chart showing day-by-day output.

The whole thing is exportable as a CSV.

One page tells you who the top performers are, who is missing visits, and which Medical Assistants need support — without reviewing a single timesheet manually.


PART EIGHTEEN. Audit Log.

The audit log. Every action taken by every user — view, add, edit, delete, login, logout, form submit.

Columns: timestamp, user, role, action, what they acted on, IP address.

Filter by action type, by user, by date range. Free-text search across all entries. Export to CSV.

If a compliance auditor asks who accessed a specific patient record on a specific date, you pull up the audit log and have the answer in ten seconds. Every access is logged — not just edits.


PART NINETEEN. Manage Staff and Roles.

Staff management. Every user listed with their role, last login, and an active/inactive toggle.

Add Staff opens the form — full name, email, role picker. The role picker is six color-coded cards with icons and descriptions so there's no guessing what each role can do.

The password strength bar animates from red to green as you type. Show and hide toggles on both password fields.

Edit a user — same form, pre-filled. Leave the password blank to keep it unchanged. Deactivate a user — one click. They can't log in. Their records are preserved.

Now — Roles and Permissions. This is the visual permissions matrix. Rows are roles, columns are permissions.

The Billing role literally cannot see vitals or wound photos. It's not hidden — it's absent. The Scheduler role can manage the schedule and nothing else. A Provider sees the sign queue and patient clinical data but has no admin tools.

Access is locked down by role from day one. No configuration required.


PART TWENTY. Settings and What's New.

Settings. Three fields: practice timezone, practice name, session timeout. The system handles everything else.

What's New — click the rocket icon in the sidebar. This is the release changelog. Every update we push shows up here — version number, date, plain-language description of what changed.

The most recent release added wound photo annotation, AI versus clinical measurement separation with full staff attribution, and improvements to the mobile sidebar.

Every time we push an update, your team sees it here. No release notes buried in an email chain.


PART TWENTY-ONE. User Profile and Saved Signature.

User profile. Update your display name, change your password.

Saved Signature. Draw your signature on the canvas with a mouse or finger, or upload a PNG of a wet signature. Click Save.

Now go to any form — the Medical Assistant signature block auto-fills immediately. You see the Auto-fill ON badge. The signature is already there.

Toggle it off if the Medical Assistant wants to sign manually for a specific form.

Every Medical Assistant sets their signature once during onboarding. Every form after that — automatic. Thirty seconds saved per form, twenty patients a day — that adds up.


PART TWENTY-TWO. Mobile Experience.

Let me hand this to you so you can feel it.

Top bar — hamburger menu, search, notifications — everything reachable with one thumb.

Tap the hamburger. The sidebar slides in from the left. Custom PaperlessMD branding. All links are fully tappable.

Tap the dark overlay behind the sidebar to close it.

See the bottom bar — Home, Patients, Schedule, Sign Queue, Messages. Always there, no matter what page you're on.

Tap a patient, open the profile, start a New Visit Form. Notice the step progress bar reflows to fit the screen. Back and Next buttons are in a sticky bar at the bottom — always visible, no scrolling to find them.

The vitals numpad has large tap targets — preset buttons fill the full width on mobile. The medication rows stack into cards. The signature canvas fills the full width — sign with your finger.

For wound photos — the camera launches natively. The photo goes directly to the patient record.

See the browser prompt at the bottom — Add to Home Screen. Install this app and it launches from your home screen with no browser chrome and the custom PaperlessMD icon.

An Medical Assistant can run a complete visit — check-in to signed form — on a two-hundred-dollar Android phone. No special hardware. No app store. The app installs to the home screen and works like a native app.


PART TWENTY-THREE. Offline Mode.

Now I'll turn on Airplane Mode.

Reload the app — still loads. Navigate to a patient, start a form — works completely offline. The service worker cached the app. Forms save to the device's local storage as you work.

See the sync counter badge in the sidebar — two pending uploads.

Turn Airplane Mode back off. The badge clears as the sync fires automatically.

Home visit in a rural area with no signal? The app keeps going. The moment the Medical Assistant drives back into range, everything syncs. They never lose a form.


PART TWENTY-FOUR. The Close.

Let me put numbers on what you just saw.

Before PaperlessMD: an Medical Assistant spends twelve to fifteen minutes on paperwork per patient visit. Filling forms by hand. Photographing them. Uploading them. Waiting for a provider to sign a paper chart. Billing waits until the next day for ICD codes.

After PaperlessMD: the same visit takes under four minutes of form work. Meds pre-fill. Vitals take fifteen seconds. Signatures are instant. The provider countersigns on their phone. Billing has the codes the same hour.

For a team seeing twenty patients a day, that's over three hours saved — every single day. Sixty-plus hours a month. One full-time Medical Assistant-hour, every day, without adding a single person.

And that's before we talk about zero lost consents, zero illegible signatures, and an audit trail that takes ten seconds to produce for any compliance request.

That's PaperlessMD. What questions do you have?


Q AND A.

Is it HIPAA compliant? Yes. Data encrypted in transit. Every access is audit-logged. No PHI goes to third parties except the AI, which receives only de-identified clinical text. Role-based access means staff see only what their role permits.

Where is the data stored? On a dedicated DigitalOcean server — your own virtual machine. Not a shared multi-tenant cloud. MySQL database is local to that server. Accessible only over HTTPS.

What if the Medical Assistant loses internet mid-visit? The app stays running — service worker cache. Forms save to the device. Sync fires automatically when connectivity returns. Nothing is lost.

Can providers sign from their own device? Yes. The sign queue works on any device, any browser, no install required.

How does billing access work? The Billing role sees submitted forms and ICD codes. That's it. Vitals, wound photos, clinical notes — not visible to billing users. By design, not by configuration.

Can we add more form types? Yes. New form types are added as wizard files following the existing pattern. No database changes required.

Does it work on iOS? Yes — Safari on iPhone and iPad, fully supported. PWA install works on iOS 16.4 and later.

What AI is used for wound measurement? GPT-4o via the OpenAI API. When a ruler is visible in the photo, GPT-4o returns area, length, and width with high confidence plus a tissue composition breakdown. For photos without a ruler, the OpenCV computer-vision model gives a relative-scale estimate. The sidebar always shows which method was used, so the clinician always knows.

What AI model does it use for the assistant? Google Gemini for the clinical AI assistant — ICD coding, SOAP drafts, billing guidance. GPT-4o for wound measurement and tissue analysis. Both are single-endpoint integrations, independently swappable.

Is the source code available? Yes — self-hosted and fully open. The repo is at github dot com slash ymathsky slash paperlessmd.
