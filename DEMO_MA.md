PaperlessMD. Medical Assistant Demo Script. Beyond Wound Care Inc.


PART ONE. Login and First Impression.


hello good morning everyone, today I will show you the walkthrough of paperless md

I want to show you this on a phone, because that's how your Medical Assistants actually use it.

This is the login page at ecpaperlessmd.com. No app to download. No app store. It's a web app that installs to the home screen in one tap — I'll show you that at the end.

Passwords are bcrypt-hashed. After five failed login attempts the account locks automatically and the admin gets an alert. Everything runs over HTTPS.

I'm logging in now as the Medical Assistant account.

Notice what's not here. No analytics charts. No staff management. No audit log. No admin settings. The Medical Assistant sees exactly what they need to do their job — nothing more, nothing less.


PART TWO. The MA Dashboard.

This is the Medical Assistant's dashboard. The first thing they see every morning.

The greeting at the top knows their name and their timezone — not UTC, clinic time.

Below that, three stats. Forms collected today — just their forms, not the whole team's. Wound photos taken today — just theirs. And forms pending a provider signature — the ones they submitted that are still waiting on countersignature.

Compare that to the admin view, which shows the whole team. The Medical Assistant only sees their own work. Clean. Focused.

Down here is Today's Route. Up to six visit cards, each one showing the patient name, address, visit type, scheduled time, and current status — Pending, En Route, or Completed. This is the Medical Assistant's entire workday on one screen.

The Draft Forms panel is right below it. These are forms started but not submitted. Amber means it's under an hour old — probably still mid-visit. Red means it's been sitting there and someone needs to follow up.

On the right — Team Notes. The admin pins announcements here for the whole team to read. Think of it as the morning huddle in text form, always visible, never buried in a text thread.

And in the bottom-right corner — three small floating buttons. Those are the quick action tools. I'll come back to those in a few minutes because they're one of the most useful things in the system.


PART THREE. Schedule and Starting a Visit.

Let me tap Schedule in the bottom bar.

The Medical Assistant sees only their own visits for today. Each card has the patient name, address, phone number, visit type, and time. The phone number is a live link — tap it and it calls. The address is a live link — tap it and Maps opens with directions.

The Medical Assistant starts their day knowing exactly where they're going. One tap to call if they're running late. One tap for turn-by-turn directions. No copying addresses into a separate app.

Now let me go back to the dashboard and tap Start on the first visit.

Tapping Start does three things at once. It records the exact start time. It marks the visit En Route so the admin can see it change in real time. And it opens the correct form immediately — no navigating to the patient chart, no searching for the form type. One tap and you're in.

The visit consent form is open.


PART FOUR. The Visit Consent Form.

This is the core of the system. Let me walk you through every step.

See the progress bar at the top. Eight steps. The Medical Assistant always knows exactly where they are. No infinite scrolling. No surprises.

Step zero is Visit Info. Provider field — start typing a name and autocomplete suggests from the staff list. Date of visit defaults to today. Visit type is one tap — New, Follow Up, Sick, Post Hospital. Time In fills automatically with right now. That timestamp is your billable time documentation, captured without the Medical Assistant thinking about it.

Step one is Chief Complaint and ICD-10. I'll tap the microphone icon and say — sacral wound stage two with serosanguineous drainage. Watch it transcribe in real time. The Medical Assistant doesn't need to type a single letter.

Now I'll type sacral pressure in the ICD-10 search. A live list of codes from the wound care library appears. I'll tap L89.152 and it becomes a chip. Now I'll tap AI Suggest — the system reads the chief complaint and recommends two or three additional codes with clinical reasoning behind each one. One tap to add any of them.

Voice dictation works on every text field in every form. Every single one. The Medical Assistant can dictate while their hands are occupied with the patient.

Step two is Vitals. I'll tap the Blood Pressure card and the numpad slides in. I'll tap the preset — 120 over 80 — it fills and automatically advances to Pulse. Tap 72, advances to Temperature. I'll work through Temp, O2 Sat, and Respiratory Rate the same way — each entry auto-advances to the next vital.

Now I'll tap Set Normal Values — every remaining vital fills with standard normals in one tap. For a stable patient, the Medical Assistant is done with vitals in under fifteen seconds.

The progress dots inside the numpad show which vitals are complete. BMI calculates automatically from weight and height. The tap targets are large — this works with a finger, a stylus, or even a gloved hand.

Step three is Medications. Look at this — the patient's active medication list pre-filled automatically. Every row is highlighted in amber. The Medical Assistant reviews each entry, taps the frequency field and quick-pill options appear — QD, BID, TID, QID, PRN — tap one and it fills. The type dropdown is right there — Maintenance, Refill, New, Discontinue.

This list comes directly from the patient's master medication record. When a provider discontinues a medication, it stops appearing here automatically. No stale meds showing up on forms.

Scroll down and you'll see the handwriting pad. For Medical Assistants who prefer to write rather than type — they draw the medication list right here with their finger or a stylus. Or they can upload a medication PDF and annotate it directly in the browser. We support both workflows. No one is forced to change how they work.

Step four — Allergies and Homebound. Allergy field is free text with a mic button. Homebound reason is a checkbox list — standard CMS homebound criteria. This is a Medicare documentation requirement. It's a checkbox here, not a separate form.

Steps five, six, and seven are the consent pages. CCM consent checkboxes — pre-checked by default. ABN acknowledgment. Wound care consent, Illinois PHI disclosure, and Practice Fusion portal opt-in. Every consent the patient needs to sign is embedded in the same workflow. The Medical Assistant doesn't carry five separate forms. It's all here, in order, in one session.

Step eight — Signatures.

The patient signature canvas is right here. They sign with their finger. If a guardian is signing for the patient, flip the POA toggle and fields appear for guardian name and relationship.

The Medical Assistant signature block is below that. See the Auto-fill On badge — the saved signature draws automatically. The Medical Assistant never lifts a finger. If they want to sign manually for a specific form, they toggle it off.

Provider signature is at the bottom. Same auto-fill if the provider has a saved signature. If not, the form goes to the sign queue for countersignature later.

Three signatures. No paper. No scanning. No uploading later. The form is in the patient's record and the sign queue the moment Submit is tapped.

I'm going to hold off on submitting for one moment — I want to show you the floating action buttons first.


PART FIVE. Floating Action Buttons.

While the Medical Assistant is in the middle of a form, three tools are always available in the bottom-right corner of the screen. They don't require navigating away. They don't interrupt the form. They're always there.

Let me tap the violet camera button.

The Wound Photo panel slides up from the bottom. I'll tap Take Photo — the native camera opens. I'll take a photo. The photo appears in the panel and I select the wound site from the dropdown. Tap Save and the photo is stored in this patient's chart immediately, tagged with today's date and the Medical Assistant's name. The camera button shows a badge with the photo count.

Now I'll close the panel. We're back exactly where we left off in the form. Nothing was lost.

No personal phone photos. No WhatsApp. No texting wound images in a group chat. The photo goes directly into the patient record — organized by wound site, timestamped, attributed. When a provider needs to review wound progression, every photo is already there, in order, with measurements.

Now the amber sticky note button.

The Quick Note panel slides up. I'll type — patient reports pain three out of ten, wound has improved, less drainage noted. Tap Save Note and it's saved to this visit immediately. A green dot badge appears on the button confirming it's there.

Or I'll tap the microphone and just say it. Takes about five seconds.

The note saves to the visit record. The provider sees it in the sign queue when they review the form. It's the verbal handoff, written down — without the Medical Assistant stopping to navigate anywhere.

Now the purple prescription button.

The RX Pad slides up. The patient's name and date of birth are already filled in from the patient record. The provider name auto-fills from today's schedule. I tap the VMP preset and all the practice details fill in — name, address, phone, fax. I type the medication name, strength, quantity, refills, and sig — or I dictate it. Tap Print and a formatted prescription opens ready to print.

No prescription pads to carry. No hunting for the practice address. No writing out the same clinic information twenty times a day.

And finally — the small chevron toggle button in the center. Tap it once and all three action buttons collapse out of view. If the Medical Assistant wants a clean screen while the patient is signing, one tap hides everything. One tap brings it all back. Their preference saves automatically.

These three buttons are available on the form pages and also on the dashboard itself. If the Medical Assistant needs to take a wound photo between visits, they don't need to open a form first.


PART SIX. Submit and the Sign Queue.

Let me go back to step eight and tap Submit and Save.

The form saves, the visit is marked Completed, and we're redirected to the dashboard. The visit card is now green — Done.

Let me switch over to the admin account and open the Sign Queue.

The form is right at the top. Date, patient name, form type, and the Medical Assistant who submitted it. I'll click it — full read-only view of everything that was just filled out.

I'll scroll to the signature section and click Sign. The provider draws or uses their saved signature. The countersignature timestamp and their name appear on the form. It disappears from the queue and the patient's record updates to Signed.

The provider doesn't need to be at the visit. They review and countersign from their phone between patients. Total time — under sixty seconds. And the audit trail records who submitted it, who signed it, and the exact timestamp of both events.


PART SEVEN. Messages.

Let me switch back to the Medical Assistant account and tap Messages.

Two-panel layout — contact list on the left with All Staff at the top, then every active staff member sorted by most recent message. Unread count badge on each row.

I'll tap a staff member and a private thread opens. I'll type — finished at the first patient, heading to the next stop. Send. Appears instantly. I can attach a wound photo or any file with the attachment button.

The bell icon in the top bar is the notification drawer. New messages, forms waiting on a signature, old draft alerts. All in one place.

No more texting patient photos on personal phones. All clinical communication goes through here — logged, searchable, and HIPAA-compliant. The timestamps are always in clinic time, not whatever timezone the Medical Assistant's phone is set to. And if there's ever a compliance question, the admin has the full thread.


PART EIGHT. Profile and Saved Signature.

Tap the avatar in the sidebar to open the profile page.

There are four tabs — Account, Signatures, Notifications, and Preferences.

Tap Signatures. This is where the Medical Assistant sets up their saved signature once during onboarding. Draw it with a finger or stylus on the canvas and tap Save. From this point forward, every form they submit auto-fills the signature. Zero drawing required.

Tap Notifications. This is where push notifications are enabled. Toggle it on — the browser fires a permission prompt. Once approved, the Medical Assistant gets a notification on their phone for new messages, forms that have been countersigned, and draft forms that haven't been submitted yet. They don't need to have the app open.

One minute of setup. Every form after that — automatic. Thirty seconds saved per form, twenty patients a day, that's ten minutes of signing time recovered every single day.


PART NINE. Mobile and PWA.

Let me show you what this looks like as a native app.

In Chrome on Android — I'll tap the three-dot menu and tap Add to Home Screen. On iOS, tap the Share icon and Add to Home Screen.

The custom PaperlessMD icon appears on the home screen — not a generic browser globe, the actual app icon. Tap it and the app launches fullscreen. No address bar. No browser chrome. It feels exactly like a native app, because the service worker caches it locally.

The bottom navigation bar is always there — Home, Patients, Schedule, Sign Queue, Messages. Everything reachable with one thumb.

Now I'll turn on Airplane Mode.

Open the app. It still loads. Navigate to a patient. Open a form. Works completely offline. The service worker cached the entire app. Forms save to the device as you work.

See the sync badge in the sidebar — two pending uploads queued.

Turn Airplane Mode back off. The badge clears as the sync fires automatically. The Medical Assistant never loses a form.

Home visits happen in apartment buildings, rural areas, and facilities with unreliable Wi-Fi. The app handles all of it.


PART TEN. The Close.

Let me put numbers on what you just saw.

Before PaperlessMD: a Medical Assistant spends twelve to fifteen minutes on paperwork per patient visit. Filling forms by hand. Photographing them on a personal phone. Uploading them to the EMR sometime later. Waiting for a provider to sign a paper chart. Billing waits until the next day for the ICD codes.

After PaperlessMD: the same visit takes under four minutes of form work. Medications pre-fill. Vitals take fifteen seconds. Signatures are automatic. Wound photos go straight into the chart with one tap. The provider countersigns on their phone. Billing has the codes the same hour.

For a team seeing twenty patients a day, that's over three hours saved — every single day. Sixty hours a month. One full Medical Assistant-hour, recovered, every day, without adding a single person to the team.

And that's before we count zero lost consents, zero illegible signatures, and an audit trail that produces any compliance answer in ten seconds.

The Medical Assistant installs this on their phone in thirty seconds. The admin sees the whole team's progress in real time. There's nothing to configure, nothing to integrate, and no paper to touch.

