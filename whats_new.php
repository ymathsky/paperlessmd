<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$pageTitle = "What's New";
$activeNav = 'whats_new';

include __DIR__ . '/includes/header.php';

$releases = [
    [
        'version' => 'v3.6',
        'date'    => 'May 31, 2026',
        'label'   => 'Latest',
        'color'   => 'emerald',
        'items'   => [
            ['icon' => 'bi-person-badge-fill',        'tag' => 'New',     'tag_color' => 'blue',  'title' => 'PCC Role: Pre-Visit Encoding Access',            'desc' => 'Patient Care Coordinators (PCC) now have access to the All Forms page, can edit and countersign visit forms, and have a dedicated sidebar navigation link — enabling full pre-visit documentation support alongside Medical Assistants.'],
            ['icon' => 'bi-capsule-pill',             'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Live Drug Name Autocomplete',                    'desc' => 'Typing two or more characters in the medication name field now shows a live dropdown of matching drugs from the formulary. Results are sorted so exact-start matches appear first, and keyboard navigation (↑ ↓ Enter Esc) is fully supported.'],
            ['icon' => 'bi-grid-3x3-gap-fill',        'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Frequency Pill Buttons',                         'desc' => 'The free-text frequency field is replaced by one-tap pill buttons — QD, BID, TID, QID, PRN, Weekly, Monthly, and Other. Selecting "Other" reveals a text input for custom frequencies. This reduces typos and speeds up encoding.'],
            ['icon' => 'bi-exclamation-triangle-fill','tag' => 'New',     'tag_color' => 'blue',  'title' => 'Duplicate Medication Detection',                 'desc' => 'Before a medication is saved, the system checks whether a matching name already exists in the active list. If a duplicate is found, a confirmation prompt asks whether to add it anyway — preventing accidental double entries.'],
            ['icon' => 'bi-clock-history',            'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Recently Used Medication Chips',                 'desc' => 'When the medications tab loads, clickable chips show the last 12 unique medications you personally entered across all patients (excluding meds already on this patient). Clicking a chip fills the name field instantly.'],
            ['icon' => 'bi-list-check',               'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Quick Add Common Medications',                   'desc' => 'A collapsible "Quick Add" panel lists 18 common medications with default frequencies. Staff can check multiple items and click "Add Selected" to bulk-add them all in one action — ideal for pre-visit encoding of chronic medication lists.'],
            ['icon' => 'bi-lightning-charge-fill',    'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Medication Add Is Now Instant — No Page Reload', 'desc' => 'Adding a medication no longer reloads the entire page. The new row appears immediately in the active list and the form resets, keeping the cursor in the name field so staff can continue entering medications without interruption.'],
        ],
    ],
    [
        'version' => 'v3.5',
        'date'    => 'May 29, 2026',
        'label'   => null,
        'color'   => 'emerald',
        'items'   => [
            ['icon' => 'bi-folder2-open',          'tag' => 'New',     'tag_color' => 'blue',  'title' => 'MA All Forms Page',                            'desc' => 'Medical Assistants now have a dedicated "All Forms" page accessible from the sidebar. It lists every submitted form for their assigned patients — filterable by form type, date, and status — giving MAs a single place to review all documentation without navigating patient by patient.'],
            ['icon' => 'bi-arrow-repeat',          'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Wound Photo Gallery Auto-Refreshes After Upload', 'desc' => 'The All Photos gallery inside the Add Wound Photo panel now reloads automatically after a photo is saved. The new image appears instantly without needing to close and reopen the panel.'],
            ['icon' => 'bi-pen-fill',              'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Provider Signature on All New Patient Packet Forms', 'desc' => 'All six forms in the New Patient Packet now include a provider signature block, ensuring complete provider sign-off is captured across the entire intake packet.'],
            ['icon' => 'bi-file-earmark-text-fill','tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'New Patient Pocket: Vitals & Meds Parity',      'desc' => 'The New Patient Pocket form now matches the Visit Consent form for vitals entry and medication reconciliation. PDF preloading is faster, and ABN and PHI consent defaults are pre-selected for a smoother intake workflow.'],
            ['icon' => 'bi-list-check',            'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'Medications Always Display on Printed Documents', 'desc' => 'Print templates now fall back to the med_list_json field when indexed medication fields are absent from older submissions, so medication lists print correctly on all historical and new documents.'],
            ['icon' => 'bi-x-circle',              'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'Send to Practice Fusion Button Removed',        'desc' => 'The "Send to Practice Fusion" button has been removed from the signed document view to streamline the page and reduce confusion for staff who do not use the Practice Fusion integration.'],
        ],
    ],
    [
        'version' => 'v3.4',
        'date'    => 'May 28, 2026',
        'label'   => null,
        'color'   => 'emerald',
        'items'   => [
            ['icon' => 'bi-bell-fill',             'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Push Notifications',                           'desc' => 'Staff now receive real-time push notifications on their device for new messages, visit assignments, and morning route reminders — even when the app is in the background.'],
            ['icon' => 'bi-capsule-pill',          'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Drug Search & Rx Pad',                         'desc' => 'Providers can now search medications by name or NDC and add them to the patient Rx pad directly inside the visit workflow, with dosage, frequency, and refill fields.'],
            ['icon' => 'bi-camera-video-fill',     'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Video Call Integration',                       'desc' => 'Telehealth video calls can now be launched directly from the patient chart, enabling remote visits without leaving PaperlessMD.'],
            ['icon' => 'bi-pen-fill',              'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Provider Signature Panel',                     'desc' => 'Providers can now apply their saved digital signature to forms from within the visit workflow, eliminating the need to sign outside the app.'],
            ['icon' => 'bi-cloud-check-fill',      'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Server-Side Autosave Every 45 Seconds',        'desc' => 'Visit forms now silently sync a draft to the server every 45 seconds. If staff lose connectivity or close the tab unexpectedly, their work is preserved and recoverable from any device.'],
            ['icon' => 'bi-stop-circle-fill',      'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'End Visit Available on All Visit Forms',       'desc' => 'The End Visit button now appears on every visit form — ABN, New Patient, CCM Consent, Medicare AWV, Cognitive Wellness, IL Disclosure, PF Signup, and Wound Care Consent — making it easy to close the visit from whichever form is completed last.'],
            ['icon' => 'bi-person-badge-fill',     'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Avatar / Profile Photo Upload',                'desc' => 'Staff can now upload a profile photo from their My Profile page. The avatar appears in the sidebar and session header across the app.'],
            ['icon' => 'bi-sticky-fill',           'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Quick Notes',                                  'desc' => 'A quick-notes panel is now available for fast free-text notes tied to the current session without opening a full visit form.'],
            ['icon' => 'bi-unlock-fill',           'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Forgot & Reset Password',                      'desc' => 'Staff can now reset their password via a secure email link from the login screen, removing the need for admin intervention for forgotten passwords.'],
        ],
    ],
    [
        'version' => 'v3.3',
        'date'    => 'May 27, 2026',
        'label'   => null,
        'color'   => 'emerald',
        'items'   => [
            ['icon' => 'bi-save2-fill',            'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Save Draft Keeps In-Progress Visits Safe', 'desc' => 'The form footer now clearly supports Save Draft as the safe pause point during a visit. Staff can preserve incomplete work, leave the chart, and return later without losing the current visit state.'],
            ['icon' => 'bi-stop-circle-fill',      'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'End Visit Finalizes the Chart and Visit Status', 'desc' => 'End Visit now acts as the completion step for the workflow. It records time out, marks the visit Completed, and takes staff to the generated document for a final review before moving on.'],
            ['icon' => 'bi-graph-up-arrow',        'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Add Wound Photo Now Surfaces Healing Trends', 'desc' => 'The Add Wound Photo workflow now gives staff direct visibility into healing trends while documenting wound images. This makes it easier to compare current photos against prior wound progress without leaving the photo workflow.'],
            ['icon' => 'bi-arrow-repeat',          'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'Draft-to-Completion Flow Is Easier to Follow', 'desc' => 'Save Draft and End Visit are now presented as two distinct actions, making it clearer when to pause documentation and when to close the visit. This reduces accidental early completion and helps keep schedule status accurate.'],
        ],
    ],
    [
        'version' => 'v3.2',
        'date'    => 'May 27, 2026',
        'label'   => null,
        'color'   => 'emerald',
        'items'   => [
            ['icon' => 'bi-graph-up-arrow',         'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'Wounds Healing Trend Chart Now Renders Without Reload', 'desc' => 'The patient chart tab-loader now executes external scripts correctly on first tab open, so the Healing Trend graph appears immediately when switching to Wounds. Users no longer need to refresh the page to see the chart.'],
            ['icon' => 'bi-file-earmark-medical-fill', 'tag' => 'Fix',  'tag_color' => 'amber', 'title' => 'PF Medication Import Filters Out Non-Medication Rows', 'desc' => 'Practice Fusion PDF import now ignores metadata/list fragments such as QUANTITY lines, status labels, and script-only rows. Medication parsing was tightened so only true medication entries are imported into the patient list.'],
            ['icon' => 'bi-sign-turn-right-fill',   'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'End Visit Redirect Reliability Improved for New Patient Packet', 'desc' => 'End Visit now consistently routes to the correct View Document entry for New Patient Packet workflows, with stronger fallback handling by visit date and form type to avoid wrong document targets.'],
            ['icon' => 'bi-ui-checks-grid',         'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Primary Care Packet Workflow Aligned with Wound Packet', 'desc' => 'Primary-care New Patient Packet behavior now matches wound-packet improvements across save/signature and redirect flows, including unified document labeling and packet-specific completion handling.'],
        ],
    ],
    [
        'version' => 'v3.1',
        'date'    => 'May 27, 2026',
        'label'   => null,
        'color'   => 'emerald',
        'items'   => [
            ['icon' => 'bi-save2-fill',            'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Visit Consent Save Flow Is Now Draft-First', 'desc' => 'Save on Visit Consent now keeps the submission as Draft so MAs can continue editing. After saving, users return to the form with a visible "Draft saved successfully" confirmation banner.'],
            ['icon' => 'bi-pencil-square',         'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Signed Forms Are Editable for MA',            'desc' => 'MAs can now re-open and edit previously signed Visit Consent entries. The Edit Form entry is visible from the signed document view, and edit mode no longer blocks form continuation for this workflow.'],
            ['icon' => 'bi-pen-fill',              'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'Patient Signature Auto-Reused in Edit Mode', 'desc' => 'When a patient signature already exists, the form now reuses it automatically during MA edits instead of forcing re-sign. Signature validation alerts were also corrected to avoid stale false warnings.'],
            ['icon' => 'bi-stop-circle-fill',      'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'End Visit Action Integrated in Form Footer',  'desc' => 'End Visit was redesigned for better usability: it now appears inline with Save Draft on the final step, reducing floating-button clutter. The form keeps the End Visit modal flow with optional Follow Up capture.'],
            ['icon' => 'bi-clock-history',         'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'End Visit Time Out Now Uses Settings Timezone', 'desc' => 'The End Visit modal now stamps Time Out using the timezone configured in Admin Settings (instead of browser-local time), keeping visit timestamps consistent across staff devices.'],
            ['icon' => 'bi-layout-text-window-reverse', 'tag' => 'Fix', 'tag_color' => 'amber', 'title' => 'Schedule Undo Modal Alignment Corrected',      'desc' => 'The Undo End Visit confirmation modal on Schedule now aligns correctly relative to the sidebar and no longer appears awkwardly overlapped with the left menu area on desktop layouts.'],
        ],
    ],
    [
        'version' => 'v3.0',
        'date'    => 'May 27, 2026',
        'label'   => null,
        'color'   => 'emerald',
        'items'   => [
            ['icon' => 'bi-grid-3x3-gap-fill',      'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Add Wound Photo: Inline "All Photos" Tab',      'desc' => 'The Add Wound Photo panel now includes a built-in All Photos tab that shows every wound image for the current patient without leaving the form. Staff can browse the full gallery and preview images directly inside the same slide-up panel.'],
            ['icon' => 'bi-x-octagon-fill',         'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'Wound Measurement Analysis UI Removed',          'desc' => 'The inaccurate auto-measurement card and related visual analysis indicators were removed from the wound photo capture experience. Upload and photo documentation remain available, but no unreliable size analysis is shown to clinicians.'],
            ['icon' => 'bi-prescription2',          'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Write Prescription Workflow Upgraded',          'desc' => 'The RX panel now has inline status feedback (no disruptive alerts), quick Add actions from chart medications, and a one-tap "Use Active Meds" option to populate prescription rows instantly. Save and print flows now provide clearer validation and progress messaging.'],
            ['icon' => 'bi-ui-checks-grid',         'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Visit Info Section UI Refreshed',               'desc' => 'Provider, date, time-in, visit type, homebound status, and missed-visit fields on the Visit Consent form were visually reorganized for faster mobile use. Selection states are clearer, tap targets are improved, and helper text now appears where users commonly pause.'],
            ['icon' => 'bi-clipboard2-check',       'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Per-Vital Source Selection',                    'desc' => 'Each vital sign on the Visit Consent form now has its own source selection so staff can mark whether that specific value was checked directly or documented per patient. The selection is saved with the rest of the form data for each vital field.'],
            ['icon' => 'bi-bell-fill',              'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Starting a New Visit Now Closes the Previous One', 'desc' => 'If staff start a new visit while an earlier visit is still marked in progress, the system now automatically ends the previous visit first. The opened form also shows a visible banner explaining that the earlier visit was auto-completed, reducing missed End clicks and overlapping active visits.'],
            ['icon' => 'bi-hospital',               'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Follow-Up Visits Can Update Pharmacy Info',    'desc' => 'The Visit Consent form now includes an editable Pharmacy Information section for follow-up visits. Staff can update the patient\'s pharmacy name, phone, and address during the visit, and those changes now sync back to the patient profile automatically on save.'],
            ['icon' => 'bi-list-check',             'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Medication Entry Moved to Modal + List View',  'desc' => 'Medication reconciliation on the Visit Consent form no longer relies on a dense inline table. Staff now add medications through a focused modal, and the form renders them as a clean list while still syncing to the medication reconciliation workflow on save.'],
            ['icon' => 'bi-pencil-square',          'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Medication Attachments Section Polished',      'desc' => 'The stylus drawing and PDF annotation tools for medication documentation are now grouped into a clearer Medication Attachments panel with stronger visual hierarchy, consistent action buttons, and improved mobile spacing.'],
            ['icon' => 'bi-pencil-square',          'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Dashboard Team Notes: Edit Support',            'desc' => 'Team Notes on the dashboard now support in-place editing through an edit action, allowing admins to quickly correct or update note text without deleting and recreating the entry.'],
            ['icon' => 'bi-graph-up-arrow',         'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'Wound Progress Chart Visible in Dark Mode',     'desc' => 'The wound healing progress chart in patient view now renders correctly in dark mode with explicit contrast-safe axis, legend, tooltip, and container styling.'],
        ],
    ],
    [
        'version' => 'v2.9',
        'date'    => 'May 17–18, 2026',
        'label'   => null,
        'color'   => 'emerald',
        'items'   => [
            ['icon' => 'bi-phone-fill',               'tag' => 'New',     'tag_color' => 'blue',   'title' => 'Incoming Video Calls on Every Page',            'desc' => 'The incoming call notification and WebRTC engine now run on every page of the app — not just Messages. If someone calls you while you are on the dashboard, a patient chart, the schedule, or any other screen, a call bar slides in at the bottom so you can accept or decline without navigating away. This also means the video call button in Messages continues to work as before.'],
            ['icon' => 'bi-layout-tabs',             'tag' => 'Fix',     'tag_color' => 'amber',  'title' => 'Patient Chart Tabs Fully Visible on Mobile',    'desc' => 'On small screens the patient chart tab bar was cutting off the last several tabs. All nine tabs are now always visible — labels are hidden and icons expand to fill the full width so every tab is reachable with a single tap.'],
            ['icon' => 'bi-arrows-collapse',         'tag' => 'Fix',     'tag_color' => 'amber',  'title' => 'No More Horizontal Scroll on Patient Chart',    'desc' => 'The patient chart page was scrolling horizontally on phones due to negative margin helpers used for edge-to-edge cards. The layout now uses overflow-clip to contain all content within the viewport — no more accidental sideways swipes.'],
            ['icon' => 'bi-table',                   'tag' => 'Improve', 'tag_color' => 'teal',   'title' => 'Forms Table Fits on Mobile',                    'desc' => 'The forms list on the patient chart no longer requires horizontal scrolling. Column padding is reduced, form names are kept to a single truncated line with an ellipsis, and version badges break correctly. Tapping any form row now navigates directly to the document — no need to find a small icon button.'],
            ['icon' => 'bi-phone-landscape-fill',    'tag' => 'New',     'tag_color' => 'blue',   'title' => 'Landscape Rotation Enabled in PWA',             'desc' => 'The installed PaperlessMD app now allows the screen to rotate freely. Previously the manifest locked the display to portrait. Setting orientation to "any" lets clinicians tilt their tablet or phone and have the layout reflow — useful when filling out forms or viewing wound photos in landscape.'],
        ],
    ],
    [
        'version' => 'v2.8',
        'date'    => 'May 15, 2026',
        'label'   => null,
        'color'   => 'blue',
        'items'   => [
            ['icon' => 'bi-camera-video-fill',       'tag' => 'New',     'tag_color' => 'blue',   'title' => 'Video Calling in Messages',                     'desc' => 'Staff can now start a live video call directly from the Messages page. Click the video camera button next to any conversation to ring the other user. The callee sees an incoming call bar with Accept and Decline buttons. Both sides get real-time audio and video through a secure peer-to-peer WebRTC connection — no third-party app required.'],
            ['icon' => 'bi-webcam-fill',             'tag' => 'Improve', 'tag_color' => 'teal',   'title' => 'Camera & Mic Permission Error Guidance',       'desc' => 'If the browser cannot access the camera or microphone, a clear modal now explains the exact reason — permission denied, no device found, device in use by another app, or a generic error — and guides the user to fix it. Previously a plain browser alert was shown with no actionable detail.'],
            ['icon' => 'bi-arrow-repeat',            'tag' => 'New',     'tag_color' => 'blue',   'title' => 'Video Call Reconnects After Page Reload',       'desc' => 'If either participant reloads the page mid-call, the call automatically re-establishes without the other person having to re-dial. The call state is saved in session storage and renegotiation happens in the background on reload.'],
            ['icon' => 'bi-camera-video-off-fill',   'tag' => 'New',     'tag_color' => 'blue',   'title' => 'Call History Logged in Chat',                   'desc' => 'Video calls are now recorded in the conversation thread. A timestamped pill shows when a call started, when it ended with the total duration (e.g. 📹 Video call ended · 4:32), or when a call was missed — giving both parties a clear call history alongside their messages.'],
            ['icon' => 'bi-image-fill',              'tag' => 'Fix',     'tag_color' => 'amber',  'title' => 'VMP Logo Now Appears on Printed Forms',         'desc' => 'The Visiting Medical Physician Inc. logo was showing as a broken image placeholder on the Visit Consent / Clinical Summary form and other printed documents. The logo file has been added to the server and now appears correctly on all VMP form printouts and PDFs.'],
        ],
    ],
    [
        'version' => 'v2.7',
        'date'    => 'May 12, 2026',
        'label'   => null,
        'color'   => 'blue',
        'items'   => [
            ['icon' => 'bi-calendar2-check-fill',    'tag' => 'Improve', 'tag_color' => 'teal',   'title' => 'Dashboard: Today\'s Visit Alert Banner',        'desc' => 'The dashboard now shows a prominent indigo banner at the top of the main column when the logged-in user has visits assigned for today. The banner displays the visit count and date, and clicking it navigates directly to the schedule. The Today\'s Route widget also gets a highlighted border and tinted header when visits exist, making it easy to spot at a glance.'],
            ['icon' => 'bi-person-badge-fill',       'tag' => 'Improve', 'tag_color' => 'teal',   'title' => 'Schedule: Provider Selection Prioritized',      'desc' => 'On the Manage Schedule "Assign New Visit" form, the Attending Provider field has been moved to Step 1 — the very first thing to fill in — with a distinct teal-highlighted panel. A confirmation badge appears once a provider is selected. The step numbers for "Who & When" and "Visit Details" have shifted to Step 2 and Step 3 accordingly.'],
            ['icon' => 'bi-search',                  'tag' => 'Improve', 'tag_color' => 'teal',   'title' => 'Schedule: Searchable Dropdowns',                'desc' => 'The Provider, MA / Staff, and Patient dropdowns on the Assign New Visit form are now searchable. Type any part of a name to instantly filter the list. No more scrolling through all staff or patients — especially helpful as the patient list grows.'],
            ['icon' => 'bi-bell-slash-fill',         'tag' => 'Fix',     'tag_color' => 'amber',  'title' => 'Pending Upload Notification Temporarily Hidden', 'desc' => 'The "X forms pending billing upload" notification in the bell menu has been temporarily disabled to reduce notification noise while billing workflows are being finalized. It can be re-enabled at any time.'],
        ],
    ],
    [
        'version' => 'v2.6',
        'date'    => 'May 12, 2026',
        'label'   => null,
        'color'   => 'blue',
        'items'   => [
            ['icon' => 'bi-ui-radios',               'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'Visit Type Validation Fixed',                  'desc' => 'Selecting Follow Up, Sick, or Post Hospital F/U on the Visit Consent / Vital CS form would still trigger a "Visit Type required" error and block navigation to the next step. Root cause: the HTML required attribute was only on the first radio button ("New"), so the browser treated it as "is this specific radio checked?" instead of "is any radio in this group selected?". Fixed by applying required to all four radio options.'],
            ['icon' => 'bi-check2-square',           'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Visit Type Auto-Selected from Schedule',      'desc' => 'When an MA taps Start Visit on the schedule, the Visit Type radio on the Vital CS form is now automatically pre-selected based on the scheduled visit type. Routine visits pre-select Follow Up; New Patient pre-selects New; Sick visits pre-select Sick; Post-hospital visits pre-select Post Hospital F/U. AWV, CCM, Wound Care, and IL visits also default to Follow Up. MAs can still change the selection before submitting.'],
        ],
    ],
    [
        'version' => 'v2.5',
        'date'    => 'May 11, 2026',
        'label'   => null,
        'color'   => 'blue',
        'items'   => [
            ['icon' => 'bi-person-plus-fill',        'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Welcome Email on Account Creation',           'desc' => 'When a new staff account is created in Admin → Manage Staff, the new user automatically receives a welcome email containing their username and temporary password, along with a direct link to the login page. Admins no longer need to manually share credentials.'],
            ['icon' => 'bi-clipboard2-pulse-fill',   'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Visit Completed → Billing Notification',      'desc' => 'When a SOAP note is finalized (status set to Final), billing staff and admins are automatically notified by email with the patient name, visit date, the provider who signed, and a direct link to the patient record — so billing can begin processing without manually checking for completed visits.'],
            ['icon' => 'bi-bell-slash-fill',         'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Schedule Notifications Scoped to Assigned Staff', 'desc' => 'Visit schedule email notifications are now sent only to the assigned MA and the assigned provider — not all admins. This reduces notification noise for users who are not involved in the visit.'],
        ],
    ],
    [
        'version' => 'v2.4',
        'date'    => 'May 9, 2026',
        'label'   => null,
        'color'   => 'blue',
        'items'   => [
            // Patient Profile
            ['icon' => 'bi-zoom-in',                 'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Wound Photo Viewer — Zoom & Pan',              'desc' => 'Wound photos in the patient profile now open in a full-screen lightbox. Zoom in/out with buttons, scroll wheel, or pinch gesture. Pan by clicking and dragging (or single-finger drag on mobile). Keyboard shortcuts: ←/→ to navigate photos, +/− to zoom, 0 to reset, Esc to close.'],
            // Form & Schedule improvements
            ['icon' => 'bi-arrow-repeat',            'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Visit Type Pre-Selected on Forms',            'desc' => 'When an MA taps Start Visit on the schedule, the visit type (New Patient, Follow-Up, Sick, Post Hospital F/U, etc.) is now passed directly to the form and the matching Visit Type radio button is pre-selected automatically. MAs can still change it before submitting.'],
            // Form data hygiene
            ['icon' => 'bi-bag-heart',               'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Pharmacy Info on New Patient Forms Only',     'desc' => 'Pharmacy name and pharmacy phone fields now appear only on the New Patient Packet form, where they are collected at intake. These fields have been removed from the routine Visit Consent / CS form to avoid redundant data entry on follow-up visits.'],
            ['icon' => 'bi-shield-lock',             'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'SSN Removed from Non-Intake Forms',           'desc' => 'The Social Security Number (last 4 digits) field has been removed from the IL DHS PHI Authorization form. SSN collection is now limited to the New Patient Packet form only, reducing unnecessary exposure of sensitive patient information on routine visit forms.'],
            // Camera / photo
            ['icon' => 'bi-camera2',                 'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Wound Camera Button on Every Clinical Form',   'desc' => 'The floating wound photo camera button is now available on every clinical form — New Patient Packet, Vital CS, SOAP Note, Medicare AWV, CCM Consent, Cognitive Wellness, ABN, Informed Consent (Wound), IL Disclosure, RPM Consent, PF Signup, and Wound Care Consent. MAs can capture or upload wound photos from any form without leaving the page.'],
            // Smart visit routing
            ['icon' => 'bi-signpost-split-fill',     'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Smart Visit Routing by Visit Type',            'desc' => 'Start Visit on the schedule now routes to the correct form based on visit type: New Patient visits open the New Patient Packet form, while Follow-Up and all other visit types open the Visit Consent / Vital CS form. The Open Forms button follows the same logic for visits already En Route.'],
            // Voice / mic
            ['icon' => 'bi-mic-fill',                'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Vitals Mic: Numbers Only',                     'desc' => 'The voice dictation microphone on vital sign fields (BP, Pulse, Temp, O2 Sat, Glucose, Height, Weight, Resp) now filters speech to numbers only. Words and non-numeric speech are silently discarded, so accidental background conversation no longer fills in vital fields with text.'],
        ],
    ],
    [
        'version' => 'v2.3',
        'date'    => 'May 8, 2026',
        'label'   => null,
        'color'   => 'blue',
        'items'   => [
            // Visit Workflow
            ['icon' => 'bi-clock-fill',              'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Visit Start Time Captured Automatically',      'desc' => 'Tapping Start Visit on the schedule now records the exact date and time the visit began. The start time is displayed on the visit card and stored permanently on the visit record. Re-clicking Start Visit will never overwrite the original timestamp.'],
            ['icon' => 'bi-stop-circle-fill',        'tag' => 'New',     'tag_color' => 'blue',  'title' => 'End Visit Button on Schedule & Forms',         'desc' => 'A red End Visit button now appears on the schedule for any in-progress (En Route) visit, and on the final step of the visit form when opened from a scheduled visit. Tapping it marks the visit as Completed, records the exact end time, and returns to the schedule. Start and end times are shown directly on the visit card for quick reference.'],
            ['icon' => 'bi-geo-alt-fill',            'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'GPS Location Updates Every Minute',           'desc' => 'MA location tracking now updates every minute (previously every 5 minutes). The browser now requests a fresh GPS fix instead of accepting a cached position, and high-accuracy mode is enabled. A live status badge on the schedule page shows MAs whether location sharing is active, searching, or blocked — with a message to enable it if denied.'],
            ['icon' => 'bi-navigation-fill',         'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Navigate Button Pushes Location Immediately',  'desc' => 'Tapping Navigate on a visit card now immediately sends the MA\'s current GPS coordinates to the office before opening Google Maps, so the admin always sees an up-to-date position when an MA starts driving.'],
            ['icon' => 'bi-tag-fill',                'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Patient Status Labels on Schedule',            'desc' => 'Visit type labels on the schedule now reflect clinical patient status. "Routine" visits are now labeled "Follow-Up," and visit type abbreviations have been expanded (e.g. "New Pt" → "New Patient," "AWV" → "Annual Wellness"). Labels appear consistently across the day view, week view, print calendar, and summary print.'],
        ],
    ],
    [
        'version' => 'v2.2',
        'date'    => 'May 7, 2026',
        'label'   => null,
        'color'   => 'blue',
        'items'   => [
            ['icon' => 'bi-play-circle-fill',       'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'One-Tap Start Visit',                          'desc' => 'Tapping Start Visit on the schedule now marks the visit En Route AND navigates directly to the correct first form — skipping the patient chart page entirely. Routine → Vital CS, New Patient → New Patient Packet, AWV → Medicare AWV, CCM → CCM Consent, IL → IL Disclosure. Wound Care visits still go to the Forms tab since multiple forms are required.'],
            ['icon' => 'bi-calendar-plus-fill',     'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Assign New Visit Form Redesigned',             'desc' => 'The Admin "Assign New Visit" form has been redesigned with a provider dropdown (replacing a plain text field), visit type selection pills, a patient quick-info preview panel, and a live summary bar showing all visit details before saving.'],
            ['icon' => 'bi-arrow-counterclockwise', 'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Return to Page After Session Timeout',         'desc' => 'When a session expires and a user logs back in, they are now automatically redirected to the page they were originally trying to access. Previously all users were sent to the dashboard after re-logging in, losing their place.'],
            // Primary Care form changes
            ['icon' => 'bi-person-badge-fill',      'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Visits Listed Under Provider',                 'desc' => 'The daily schedule now groups and displays visits under the assigned Provider rather than the Medical Assistant. Applies to both Primary Care and Wound Care workflows.'],
            ['icon' => 'bi-person-fill-check',      'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Provider Name Auto-Populates in Consents',     'desc' => 'The provider\'s name entered at the start of the Primary Care visit is automatically carried through to all Consent sections, eliminating redundant manual entry.'],
            ['icon' => 'bi-pen-fill',               'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Provider Signature Auto-Fill from Schedule',   'desc' => 'When an MA opens a form, the app now looks up the provider assigned on today\'s schedule and pre-fills their saved signature automatically — regardless of who is logged in. Previously the auto-fill only worked when the provider themselves was the one logged in. Fallback: if no scheduled provider is found, the logged-in user\'s own saved signature is used (provider self-sign workflow).'],
            ['icon' => 'bi-clock-history',          'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Time Out Moved to Last Consent Step',          'desc' => 'The Time Out section has been repositioned to the final step of the Consents workflow in both Primary Care and Wound Care. Previously it appeared mid-flow and was blocking progression to the rest of the form.'],
            ['icon' => 'bi-dash-circle-fill',       'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Chief Complaints & ICD-10 Fields Removed',     'desc' => 'Chief Complaints and ICD-10 diagnosis fields have been removed from both Primary Care and Wound Care forms. Providers enter these directly in Practice Fusion — duplicating them in PaperlessMD was unnecessary.'],
            ['icon' => 'bi-file-earmark-pdf-fill',  'tag' => 'New',     'tag_color' => 'blue',  'title' => 'Editable PDF Medication List',                 'desc' => 'MAs and Providers can now upload an editable PDF for the Medication List section. The PDF opens inline and can be annotated directly in the browser without downloading — available for both Medical Assistants and Providers.'],
            ['icon' => 'bi-file-earmark-text-fill', 'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'ABN: ID Clarification + Section H Removed',    'desc' => 'The Advance Beneficiary Notice (ABN) form now includes a tooltip clarifying what the ID number field refers to. Section H, which was not applicable to the practice\'s workflow, has been removed from the form.'],
            ['icon' => 'bi-shield-fill-check',      'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'PHI Authorization: Record Search Dates Removed', 'desc' => 'The date fields for medical record search range have been removed from the IL DHS PHI Authorization form, simplifying the document for the typical use case.'],
            ['icon' => 'bi-hash',                   'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'NPI Section Removed',                          'desc' => 'The NPI (National Provider Identifier) section has been removed from patient intake forms. NPI data is managed elsewhere and was identified as redundant by the clinical team.'],
            // Wound Care form changes
            ['icon' => 'bi-bandaid-fill',           'tag' => 'Improve', 'tag_color' => 'teal',  'title' => 'Wound Care: Medication List Removed',          'desc' => 'The Medication List has been removed from the Wound Care workflow. Providers document medications exclusively in Practice Fusion — the duplicate field in Wound Care was causing confusion.'],
            ['icon' => 'bi-arrow-left-right',       'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'Wound Care: Navigate Away Without Losing Progress', 'desc' => 'MAs can now navigate away from the Wound Care Clinical Summary (e.g. to check patient info) and return without the system resetting the workflow. Previously, any navigation away would restart the process from the beginning.'],
            ['icon' => 'bi-camera-fill',            'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'Wound Photos: Full-Scale Camera Capture Restored', 'desc' => 'The camera capture in the Wound Pictures form was cropping or scaling down photos on certain mobile devices. Fixed — the camera now uses the full native resolution when capturing wound photos.'],
            // Bug fix
            ['icon' => 'bi-hourglass-split',        'tag' => 'Fix',     'tag_color' => 'amber', 'title' => 'Schedule Save No Longer Hangs',                'desc' => 'Adding a visit to the schedule was hanging for up to 60 seconds. Root cause: PHPMailer was attempting an SMTP connection with no timeout configured, blocking the page until the TCP connection timed out. Fixed by setting a 5-second SMTP connect timeout — the save now completes instantly.'],
        ],
    ],
    [
        'version' => 'v1.9',
        'date'    => 'April 30, 2026',
        'label'   => null,
        'color'   => 'blue',
        'items'   => [
            ['icon' => 'bi-envelope-fill',       'tag' => 'New',     'tag_color' => 'blue',    'title' => 'Email Notifications (SMTP)',            'desc' => 'PaperlessMD now sends real-time email alerts via SMTP for key events: a new form awaiting provider countersignature, a form countersigned by a provider, a new internal message or broadcast, a new visit scheduled, and account lockout after too many failed logins. All email is handled through a centralized PHPMailer helper — no @mail() calls.'],
            ['icon' => 'bi-calendar-check-fill', 'tag' => 'New',     'tag_color' => 'blue',    'title' => 'Schedule Assignment Notifications',    'desc' => 'When a visit is added in Manage Schedule, the assigned MA, the matching provider (looked up by full name), and all admins receive an email notification with the patient name, visit date, visit type, and who scheduled it.'],
            ['icon' => 'bi-camera-fill',         'tag' => 'New',     'tag_color' => 'blue',    'title' => 'Wound Photo Portal',                   'desc' => 'New Admin / MA / Provider portal (Admin → Wound Photos) showing all wound photos across all patients in a date-grouped gallery. Includes a stats bar (total photos, unique patients, this week, today), 5-way filtering (patient, MA, wound site, date range), a full-screen lightbox with prev/next arrow and keyboard navigation, and per-photo download.'],
            ['icon' => 'bi-at',                  'tag' => 'New',     'tag_color' => 'blue',    'title' => 'Staff Email Addresses',                'desc' => 'Staff accounts now have an email address field. Emails can be set in Admin → Manage Staff → Edit. A one-click migration page (migrate_staff_email.php) adds the column to existing installations. Email addresses are required for notification delivery.'],
        ],
    ],
    [
        'version' => 'v1.8',
        'date'    => 'May 2, 2026',
        'label'   => null,
        'color'   => 'blue',
        'items'   => [
            ['icon' => 'bi-person-badge-fill',  'tag' => 'New',     'tag_color' => 'blue',    'title' => 'Provider Role',                         'desc' => 'New "Provider" user role for physicians and nurse practitioners. Providers have full read access to clinical data (vitals, medications, wounds, SOAP notes, diagnoses), can view and sign the e-signature queue, view the daily schedule, and use internal messaging — without schedule management or admin functions.'],
            ['icon' => 'bi-pen-fill',            'tag' => 'New',     'tag_color' => 'blue',    'title' => 'E-Sign Queue: Provider Access',          'desc' => 'Provider-role users now see all pending provider signatures across every MA in the E-Sign queue — not just forms belonging to their own MA. Admins retain the same all-access view.'],
            ['icon' => 'bi-heart-pulse-fill',    'tag' => 'Improve', 'tag_color' => 'teal',    'title' => 'Clinical Data Access for Providers',    'desc' => 'The canAccessClinical() permission now includes the Provider role alongside Admin and MA, granting access to vitals, medications, wounds, and SOAP notes throughout the application.'],
        ],
    ],
    [
        'version' => 'v1.7',
        'date'    => 'April 29, 2026',
        'label'   => null,
        'color'   => 'blue',
        'items'   => [
            ['icon' => 'bi-calendar-week-fill', 'tag' => 'New',     'tag_color' => 'blue',    'title' => 'Scheduler Role',                        'desc' => 'New "Scheduler" user role for staff who manage scheduling only. Schedulers can view the daily schedule, add/edit visits, and manage recurring schedules — without access to clinical data, forms, or admin functions.'],
            ['icon' => 'bi-calendar-plus-fill', 'tag' => 'New',     'tag_color' => 'blue',    'title' => 'Schedule Management for Schedulers',   'desc' => 'Manage Schedule and Recurring Schedule pages are now accessible to both Admins and Schedulers. Schedulers see a dedicated "Scheduling" section in the sidebar navigation.'],
            ['icon' => 'bi-person-badge-fill',  'tag' => 'Improve', 'tag_color' => 'teal',    'title' => 'Roles & Permissions Matrix Updated',   'desc' => 'The Roles & Permissions page now shows all four roles — Admin, Medical Assistant, Scheduler, and Billing — with a full permission matrix for each.'],
        ],
    ],
    [
        'version' => 'v1.6',
        'date'    => 'April 29, 2026',
        'label'   => null,
        'color'   => 'blue',
        'items'   => [
            ['icon' => 'bi-pencil-square',     'tag' => 'New',     'tag_color' => 'blue',    'title' => 'Handwriting Pad for Medications',       'desc' => 'Draw medication names, doses, and frequencies with a tablet stylus or finger directly on the Medications step of Visit Consent and New Patient Packet forms. Saved as an image and printed on the document.'],
            ['icon' => 'bi-geo-alt-fill',       'tag' => 'New',     'tag_color' => 'blue',    'title' => 'MA Location Monitor',                   'desc' => 'Admins can now view the real-time GPS location of all active MAs on an interactive map (Admin → MA Locations). MAs are automatically tracked every 5 minutes while logged in. Color-coded status: green = online (<10 min), amber = away (10–60 min), grey = offline (>60 min).'],
            ['icon' => 'bi-check2-circle',      'tag' => 'New',     'tag_color' => 'blue',    'title' => 'Required Field Validation',             'desc' => 'Forms now highlight missing required fields before submission. A red banner lists every missing field by name. Step-by-step validation on wizard Next buttons prevents advancing with incomplete data.'],
            ['icon' => 'bi-pen-fill',           'tag' => 'Fix',     'tag_color' => 'amber',   'title' => 'Profile Signature Pad Fix',             'desc' => 'Fixed a bug where the Pre-Saved Signature drawing pad on the Profile page was unresponsive (SignaturePad loaded after the inline script ran).'],
        ],
    ],
    [
        'version' => 'v1.5',
        'date'    => 'April 28, 2026',
        'label'   => null,
        'color'   => 'blue',
        'items'   => [
            ['icon' => 'bi-pen-fill',           'tag' => 'New',     'tag_color' => 'blue',    'title' => 'Pre-Saved Signature — Upload Tab',      'desc' => 'Added an Upload tab to the Pre-Saved Signature card on the Profile page. MAs can upload a PNG/JPG image of their signature (≤2 MB), which is normalized to a white-background PNG and saved as their auto-fill signature.'],
            ['icon' => 'bi-person-check-fill',  'tag' => 'New',     'tag_color' => 'blue',    'title' => 'Login Prompt for Missing Signature',    'desc' => 'After login, MAs and admins without a pre-saved signature see a one-time modal prompting them to set one up. Dismissing it shows the prompt again next login until a signature is saved.'],
            ['icon' => 'bi-shield-check',       'tag' => 'Fix',     'tag_color' => 'amber',   'title' => 'HTTP 500 Fix on Production Login',      'desc' => 'Wrapped the saved_signature DB query in a try/catch so production servers without the migration applied do not return a 500 error on login.'],
        ],
    ],
    [
        'version' => 'v1.4',
        'date'    => 'April 25, 2026',
        'label'   => null,
        'color'   => 'violet',
        'items'   => [
            ['icon' => 'bi-pen-fill',           'tag' => 'New',     'tag_color' => 'blue',    'title' => 'Pre-Saved MA Signature',                'desc' => 'MAs can draw and save a signature once on their Profile page. It auto-fills the MA signature field on every form, with a "Sign manually" option to override it on any individual form.'],
            ['icon' => 'bi-person-badge-fill',  'tag' => 'New',     'tag_color' => 'blue',    'title' => 'Provider Signature Pre-Fill',           'desc' => 'Provider signatures saved on the profile are auto-applied to the New Patient Packet form, reducing repeated signing.'],
        ],
    ],
    [
        'version' => 'v1.3',
        'date'    => 'April 20, 2026',
        'label'   => null,
        'color'   => 'indigo',
        'items'   => [
            ['icon' => 'bi-calendar3',          'tag' => 'New',     'tag_color' => 'blue',    'title' => 'Recurring Schedule',                    'desc' => 'Admin can set up recurring visit schedules (weekly, bi-weekly, monthly) for patients. Recurring rules auto-generate schedule entries going forward.'],
            ['icon' => 'bi-graph-up-arrow',     'tag' => 'New',     'tag_color' => 'blue',    'title' => 'MA Productivity Report',                'desc' => 'Admins can view a productivity report showing forms completed per MA, visit counts, and date-range filtering.'],
            ['icon' => 'bi-arrow-repeat',       'tag' => 'Improve', 'tag_color' => 'teal',    'title' => 'Auto-Save Drafts',                      'desc' => 'All wizard forms auto-save to localStorage every 300–600 ms. A "Resume draft" banner appears on next visit so work is never lost.'],
        ],
    ],
    [
        'version' => 'v1.2',
        'date'    => 'April 12, 2026',
        'label'   => null,
        'color'   => 'rose',
        'items'   => [
            ['icon' => 'bi-stars',              'tag' => 'New',     'tag_color' => 'blue',    'title' => 'AI Clinical Assistant',                 'desc' => 'Floating AI bubble on every page. Chat with the clinical AI for ICD-10 code suggestions, SOAP note drafting, documentation guidance, and general clinical Q&A.'],
            ['icon' => 'bi-broadcast-pin',      'tag' => 'New',     'tag_color' => 'blue',    'title' => 'RPM Consent Form',                      'desc' => 'Added Remote Patient Monitoring consent form with full wizard, patient + MA signatures, and PDF export.'],
            ['icon' => 'bi-bandaid-fill',       'tag' => 'New',     'tag_color' => 'blue',    'title' => 'Wound Care Consent Forms',              'desc' => 'Added Wound Care Consent and Informed Consent – Wound Care forms with patient/MA/provider signature blocks.'],
        ],
    ],
    [
        'version' => 'v1.1',
        'date'    => 'April 1, 2026',
        'label'   => null,
        'color'   => 'slate',
        'items'   => [
            ['icon' => 'bi-people-fill',        'tag' => 'New',     'tag_color' => 'blue',    'title' => 'Billing Role',                          'desc' => 'New "Billing" user role with read-only access to signed forms and patient data. Clinical notes, vitals, and PHI fields are hidden from billing users.'],
            ['icon' => 'bi-envelope-at-fill',   'tag' => 'New',     'tag_color' => 'blue',    'title' => 'Patient Portal Signup',                 'desc' => 'PF Portal Signup form added for onboarding patients to the patient-facing portal.'],
            ['icon' => 'bi-chat-dots-fill',     'tag' => 'New',     'tag_color' => 'blue',    'title' => 'Internal Messaging',                    'desc' => 'Staff can send internal messages to each other with unread badge counts in the sidebar.'],
            ['icon' => 'bi-shield-lock-fill',   'tag' => 'New',     'tag_color' => 'blue',    'title' => 'Audit Log',                             'desc' => 'Every form view, edit, sign, and delete action is recorded in the audit log, accessible to admins.'],
        ],
    ],
];

$tagColors = [
    'blue'  => 'bg-blue-100 text-blue-700',
    'amber' => 'bg-amber-100 text-amber-700',
    'teal'  => 'bg-teal-100 text-teal-700',
    'red'   => 'bg-red-100 text-red-700',
];
$versionColors = [
    'emerald' => ['ring' => 'ring-emerald-500', 'bg' => 'bg-emerald-500', 'text' => 'text-emerald-700', 'badge' => 'bg-emerald-100 text-emerald-700 border-emerald-200'],
    'blue'    => ['ring' => 'ring-blue-400',    'bg' => 'bg-blue-400',    'text' => 'text-blue-700',    'badge' => 'bg-blue-100 text-blue-700 border-blue-200'],
    'violet'  => ['ring' => 'ring-violet-400',  'bg' => 'bg-violet-400',  'text' => 'text-violet-700',  'badge' => 'bg-violet-100 text-violet-700 border-violet-200'],
    'indigo'  => ['ring' => 'ring-indigo-400',  'bg' => 'bg-indigo-400',  'text' => 'text-indigo-700',  'badge' => 'bg-indigo-100 text-indigo-700 border-indigo-200'],
    'rose'    => ['ring' => 'ring-rose-400',    'bg' => 'bg-rose-400',    'text' => 'text-rose-700',    'badge' => 'bg-rose-100 text-rose-700 border-rose-200'],
    'slate'   => ['ring' => 'ring-slate-400',   'bg' => 'bg-slate-400',   'text' => 'text-slate-700',   'badge' => 'bg-slate-100 text-slate-600 border-slate-200'],
];

// ── Filters + pagination ───────────────────────────────────────────────────
$filterQ       = trim((string)($_GET['q'] ?? ''));
$filterTag     = trim((string)($_GET['tag'] ?? ''));
$filterVersion = trim((string)($_GET['version'] ?? ''));
$perPageOpts   = [3, 5, 8, 12];
$perPage       = (int)($_GET['per_page'] ?? 5);
if (!in_array($perPage, $perPageOpts, true)) {
    $perPage = 5;
}

$availableTags = [];
foreach ($releases as $_rel) {
    foreach (($_rel['items'] ?? []) as $_item) {
        $availableTags[$_item['tag']] = true;
    }
}
$availableTags = array_keys($availableTags);
sort($availableTags);

$availableVersions = array_map(function ($r) {
    return $r['version'];
}, $releases);

$qNeedle = strtolower($filterQ);
$filteredReleases = array_values(array_filter($releases, function ($release) use ($filterTag, $filterVersion, $qNeedle) {
    if ($filterVersion !== '' && ($release['version'] ?? '') !== $filterVersion) {
        return false;
    }

    if ($filterTag === '' && $qNeedle === '') {
        return true;
    }

    foreach (($release['items'] ?? []) as $item) {
        $tagOk = ($filterTag === '') || (($item['tag'] ?? '') === $filterTag);
        $hay   = strtolower(($item['title'] ?? '') . ' ' . ($item['desc'] ?? '') . ' ' . ($item['tag'] ?? '') . ' ' . ($release['version'] ?? ''));
        $qOk   = ($qNeedle === '') || (strpos($hay, $qNeedle) !== false);
        if ($tagOk && $qOk) {
            return true;
        }
    }
    return false;
}));

$totalReleases = count($filteredReleases);
$totalPages    = max(1, (int)ceil($totalReleases / $perPage));
$page          = max(1, (int)($_GET['page'] ?? 1));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset       = ($page - 1) * $perPage;
$releasesPage = array_slice($filteredReleases, $offset, $perPage);

$showStart = $totalReleases ? ($offset + 1) : 0;
$showEnd   = $totalReleases ? min($offset + $perPage, $totalReleases) : 0;
$hasFilter = ($filterQ !== '' || $filterTag !== '' || $filterVersion !== '');

$buildUrl = function (array $overrides = []) {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    $qs = http_build_query($params);
    return BASE_URL . '/whats_new.php' . ($qs ? ('?' . $qs) : '');
};
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
        <h2 class="text-2xl font-extrabold text-slate-800 flex items-center gap-2.5">
            <span class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-600 to-indigo-600 grid place-items-center shrink-0">
                <i class="bi bi-rocket-takeoff-fill text-white text-base"></i>
            </span>
            What's New
        </h2>
        <p class="text-slate-500 text-sm mt-1">PaperlessMD release history &mdash; latest features, fixes, and improvements</p>
    </div>
    <a href="<?= BASE_URL ?>/dashboard.php"
       class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-100 hover:bg-slate-200
              text-slate-700 font-semibold text-sm rounded-xl transition-all">
        <i class="bi bi-house-fill"></i> Dashboard
    </a>
</div>

<!-- Filter + paging controls -->
<div class="max-w-3xl mb-5">
    <form method="GET" action="<?= BASE_URL ?>/whats_new.php"
          class="bg-white border border-slate-200 rounded-2xl p-4 shadow-sm grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
        <div class="sm:col-span-2 lg:col-span-2">
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Search</label>
            <input type="text" name="q" value="<?= htmlspecialchars($filterQ, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Search title or details..."
                   class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Tag</label>
            <select name="tag" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                <option value="">All tags</option>
                <?php foreach ($availableTags as $_tag): ?>
                <option value="<?= htmlspecialchars($_tag, ENT_QUOTES, 'UTF-8') ?>" <?= $filterTag === $_tag ? 'selected' : '' ?>><?= htmlspecialchars($_tag) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Version</label>
            <select name="version" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                <option value="">All versions</option>
                <?php foreach ($availableVersions as $_ver): ?>
                <option value="<?= htmlspecialchars($_ver, ENT_QUOTES, 'UTF-8') ?>" <?= $filterVersion === $_ver ? 'selected' : '' ?>><?= htmlspecialchars($_ver) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wide mb-1.5">Per page</label>
            <select name="per_page" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-400 focus:border-transparent">
                <?php foreach ($perPageOpts as $_opt): ?>
                <option value="<?= $_opt ?>" <?= $perPage === $_opt ? 'selected' : '' ?>><?= $_opt ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <input type="hidden" name="page" value="1">
        <div class="sm:col-span-2 lg:col-span-5 flex flex-wrap items-center justify-between gap-2 pt-1">
            <div class="text-xs text-slate-500">
                Showing <span class="font-bold text-slate-700"><?= $showStart ?></span>
                to <span class="font-bold text-slate-700"><?= $showEnd ?></span>
                of <span class="font-bold text-slate-700"><?= $totalReleases ?></span> release<?= $totalReleases === 1 ? '' : 's' ?>
            </div>
            <div class="flex items-center gap-2">
                <?php if ($hasFilter): ?>
                <a href="<?= BASE_URL ?>/whats_new.php"
                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-slate-200 text-slate-600 text-xs font-semibold hover:bg-slate-50 transition-colors">
                    <i class="bi bi-x-circle"></i> Clear filters
                </a>
                <?php endif; ?>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-blue-600 text-white text-xs font-semibold hover:bg-blue-700 transition-colors">
                    <i class="bi bi-funnel-fill"></i> Apply
                </button>
            </div>
        </div>
    </form>
</div>

<!-- Timeline -->
<div class="max-w-3xl">
    <?php if (empty($releasesPage)): ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-8 text-center text-slate-500 shadow-sm">
        <i class="bi bi-search text-2xl block mb-2 text-slate-300"></i>
        No releases matched your filter.
    </div>
    <?php else: ?>
    <?php foreach ($releasesPage as $ri => $release):
        $vc = $versionColors[$release['color']] ?? $versionColors['slate'];
    ?>
    <div class="flex gap-5 <?= $ri > 0 ? 'mt-10' : '' ?>">

        <!-- Timeline spine -->
        <div class="flex flex-col items-center shrink-0">
            <div class="w-10 h-10 rounded-full ring-2 ring-offset-2 <?= $vc['ring'] ?> <?= $vc['bg'] ?>
                        flex items-center justify-center text-white font-extrabold text-xs shadow-sm">
                <?= htmlspecialchars($release['version']) ?>
            </div>
            <?php if ($ri < count($releasesPage) - 1): ?>
            <div class="flex-1 w-px bg-slate-200 mt-2"></div>
            <?php endif; ?>
        </div>

        <!-- Release card -->
        <div class="flex-1 pb-2">
            <div class="flex flex-wrap items-center gap-2 mb-4">
                <span class="text-base font-extrabold text-slate-800"><?= htmlspecialchars($release['version']) ?></span>
                <?php if ($release['label']): ?>
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold
                             border <?= $vc['badge'] ?>">
                    <i class="bi bi-lightning-charge-fill text-[10px]"></i>
                    <?= htmlspecialchars($release['label']) ?>
                </span>
                <?php endif; ?>
                <span class="text-xs text-slate-400 ml-auto"><?= htmlspecialchars($release['date']) ?></span>
            </div>

            <div class="space-y-3">
                <?php foreach ($release['items'] as $item):
                    $tc = $tagColors[$item['tag_color']] ?? $tagColors['blue'];
                ?>
                <div class="flex gap-4 bg-white border border-slate-100 rounded-2xl p-4 shadow-sm
                            hover:shadow-md hover:border-slate-200 transition-all">
                    <div class="w-9 h-9 rounded-xl <?= $vc['bg'] ?>/10 border border-current/10
                                flex items-center justify-center shrink-0 <?= $vc['text'] ?>">
                        <i class="bi <?= htmlspecialchars($item['icon']) ?> text-base"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span class="font-bold text-sm text-slate-800"><?= htmlspecialchars($item['title']) ?></span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold <?= $tc ?>">
                                <?= htmlspecialchars($item['tag']) ?>
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 leading-relaxed"><?= htmlspecialchars($item['desc']) ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if ($totalPages > 1): ?>
    <div class="mt-8 flex flex-wrap items-center justify-between gap-3 bg-white border border-slate-200 rounded-2xl px-4 py-3 shadow-sm">
        <div class="text-xs text-slate-500">Page <span class="font-bold text-slate-700"><?= $page ?></span> of <span class="font-bold text-slate-700"><?= $totalPages ?></span></div>
        <div class="flex items-center gap-1.5">
            <?php if ($page > 1): ?>
            <a href="<?= htmlspecialchars($buildUrl(['page' => $page - 1]), ENT_QUOTES, 'UTF-8') ?>"
               class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                <i class="bi bi-chevron-left"></i> Prev
            </a>
            <?php endif; ?>

            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="<?= htmlspecialchars($buildUrl(['page' => $p]), ENT_QUOTES, 'UTF-8') ?>"
               class="px-2.5 py-1.5 rounded-lg border text-xs font-bold <?= $p === $page ? 'border-blue-300 bg-blue-50 text-blue-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
            <a href="<?= htmlspecialchars($buildUrl(['page' => $page + 1]), ENT_QUOTES, 'UTF-8') ?>"
               class="px-3 py-1.5 rounded-lg border border-slate-200 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                Next <i class="bi bi-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- End cap -->
    <?php if (!$hasFilter && !empty($releasesPage) && $page === $totalPages): ?>
    <div class="flex gap-5 mt-10">
        <div class="flex flex-col items-center shrink-0">
            <div class="w-10 h-10 rounded-full bg-slate-100 border-2 border-slate-300
                        flex items-center justify-center text-slate-400">
                <i class="bi bi-flag-fill text-sm"></i>
            </div>
        </div>
        <div class="flex-1 pb-2 pt-2">
            <p class="text-sm text-slate-400 italic">PaperlessMD initial launch &mdash; 2025</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
