#!/usr/bin/env python3
"""Comprehensive user_manual.html update script for PaperlessMD on ecpaperlessmd.com"""

with open('/var/www/paperlessmd/user_manual.html', 'r') as f:
    c = f.read()

changes = 0

def rep(old, new, desc):
    global c, changes
    if old in c:
        c = c.replace(old, new, 1)
        changes += 1
        print(f"  OK: {desc}")
    else:
        print(f"  MISS: {desc}")

# ─────────────────────────────────────────────
# 1. Fix production URL throughout
# ─────────────────────────────────────────────
rep(
    '<tr><td>Production URL</td><td><code>https://docs.md-officesupport.com</code></td></tr>',
    '<tr><td>Production URL</td><td><code>https://ecpaperlessmd.com</code></td></tr>',
    "Fix production URL in overview table"
)

rep(
    '<li>Open your browser and go to <strong>https://docs.md-officesupport.com</strong></li>',
    '<li>Open your browser and go to <strong>https://ecpaperlessmd.com</strong></li>',
    "Fix login URL"
)

rep(
    '"10 clinical and consent forms',
    '"12 clinical and consent forms',
    "Update form count in feature card"
)

# ─────────────────────────────────────────────
# 2. Section 2.3 — Navigation Bar (full table replacement)
# ─────────────────────────────────────────────
rep(
    '''<div class="tbl-wrap">
<table>
  <tr><th>Nav Item</th><th>Goes to</th><th>Visible to</th></tr>
  <tr><td>🏠 Dashboard</td><td>dashboard.php</td><td>All roles</td></tr>
  <tr><td>👥 Patients</td><td>patients.php</td><td>All roles</td></tr>
  <tr><td>📅 Schedule</td><td>schedule.php</td><td>Admin, MA</td></tr>
  <tr><td>⚙ Admin</td><td>admin/ (dropdown)</td><td>Admin only</td></tr>
  <tr><td>🚪 Logout</td><td>logout.php</td><td>All roles</td></tr>
</table>
</div>''',
    '''<div class="tbl-wrap">
<table>
  <tr><th>Nav Item</th><th>Goes to</th><th>Visible to</th></tr>
  <tr><td>🏠 Dashboard</td><td>dashboard.php</td><td>All roles</td></tr>
  <tr><td>👥 Patients</td><td>patients.php</td><td>All roles</td></tr>
  <tr><td>📅 Schedule</td><td>schedule.php</td><td>Admin, MA</td></tr>
  <tr><td>✍ Sign Queue</td><td>esign_queue.php</td><td>Admin, MA (hidden for Billing)</td></tr>
  <tr><td>💬 Messages</td><td>messages.php</td><td>All roles — shows unread badge count</td></tr>
  <tr><td>🚀 What\'s New</td><td>whats_new.php</td><td>All roles</td></tr>
  <tr><td>🔔 Notifications</td><td>Bell icon (top right)</td><td>All roles</td></tr>
  <tr><td>⚙ Admin</td><td>admin/ (dropdown)</td><td>Admin only</td></tr>
  <tr><td>👤 My Profile</td><td>profile.php</td><td>All roles</td></tr>
  <tr><td>🚪 Sign Out</td><td>logout.php</td><td>All roles</td></tr>
</table>
</div>

<h3 class="sub">Admin Dropdown Items</h3>
<div class="tbl-wrap">
<table>
  <tr><th>Admin Menu Item</th><th>Purpose</th></tr>
  <tr><td>Manage Schedule</td><td>Add, edit, reorder, and delete visits for any date/MA</td></tr>
  <tr><td>Recurring Schedule</td><td>Set up repeating visit patterns (e.g., every Monday)</td></tr>
  <tr><td>Wound Photos</td><td>Browse all wound photos across all patients</td></tr>
  <tr><td>Productivity Report</td><td>Per-MA visit and form counts for a date range</td></tr>
  <tr><td>MA Locations</td><td>Real-time GPS map of all active MAs currently in the field</td></tr>
  <tr><td>Manage Staff</td><td>Add, edit, activate/deactivate staff accounts</td></tr>
  <tr><td>Roles &amp; Permissions</td><td>View role definitions and permission matrix</td></tr>
  <tr><td>Audit Log</td><td>Full system event log with user + IP + timestamp</td></tr>
  <tr><td>Settings</td><td>Clinic name, timezone, and app-wide preferences</td></tr>
</table>
</div>''',
    "Expand Navigation Bar section"
)

# ─────────────────────────────────────────────
# 3. Section 3 — Roles table (add new tabs + messages)
# ─────────────────────────────────────────────
rep(
    '  <tr><td>Patient → Wounds tab</td><td>✅</td><td>✅</td><td>❌</td></tr>',
    '''  <tr><td>Patient → Wounds tab</td><td>✅</td><td>✅</td><td>❌</td></tr>
  <tr><td>Patient → Diagnoses tab</td><td>✅</td><td>✅</td><td>❌</td></tr>
  <tr><td>Patient → Vitals tab</td><td>✅</td><td>✅</td><td>❌</td></tr>
  <tr><td>Patient → Care Notes tab</td><td>✅</td><td>✅</td><td>❌</td></tr>
  <tr><td>Patient → SOAP Notes tab</td><td>✅</td><td>✅</td><td>❌</td></tr>
  <tr><td>Patient → Audit tab</td><td>✅</td><td>❌</td><td>❌</td></tr>
  <tr><td>Messages (send/receive)</td><td>✅</td><td>✅</td><td>✅</td></tr>
  <tr><td>Team Notes (dashboard)</td><td>✅ Post &amp; delete</td><td>✅ Read only</td><td>❌</td></tr>
  <tr><td>MA Location Monitor</td><td>✅</td><td>❌</td><td>❌</td></tr>
  <tr><td>Productivity Report</td><td>✅</td><td>❌</td><td>❌</td></tr>''',
    "Add new permission rows to roles table"
)

# ─────────────────────────────────────────────
# 4. Section 4 — Dashboard (add missing widgets)
# ─────────────────────────────────────────────
rep(
    '<div class="warn"><strong>MAs</strong> only see their own drafts. <strong>Admins</strong> see all drafts from all staff.</div>',
    '''<div class="warn"><strong>MAs</strong> only see their own drafts. <strong>Admins</strong> see all drafts from all staff.</div>

<h2 class="sub">4.4 Quick Actions</h2>
<p>A row of icon tiles provides one-tap shortcuts:</p>
<ul>
  <li><strong>New Patient</strong> — opens the Add Patient form</li>
  <li><strong>Find Patient</strong> — jumps directly to the patient search</li>
  <li><strong>Pending Upload</strong> — filters patients with unsigned/unuploaded forms</li>
  <li><strong>All Patients</strong> — opens the full patient list</li>
  <li><strong>User Manual</strong> — opens this document</li>
</ul>

<h2 class="sub">4.5 Team Notes <span class="badge badge-admin">Admin</span> + <span class="badge badge-ma">MA</span> (read)</h2>
<p>A sticky-note widget in the right sidebar shows pinned notes posted by admins for the whole team.</p>
<ul>
  <li><span class="badge badge-admin">Admin</span> users can <strong>post new notes</strong> (click the <strong>+</strong> button) and <strong>delete</strong> existing ones (hover → ✕ button).</li>
  <li><span class="badge badge-ma">MA</span> users can <strong>read</strong> all posted notes but cannot post or delete.</li>
  <li>Notes are displayed newest-first. Each note shows the author name and relative time.</li>
</ul>

<h2 class="sub">4.6 Staff Online <span class="badge badge-admin">Admin only</span></h2>
<p>A sidebar widget showing which staff members were active in the last 15 minutes, with their role badge.</p>

<h2 class="sub">4.7 Recent Activity</h2>
<p>Admins see a live feed of recent audit events (patient views, form submissions, logins) in the right column. MAs see their own recent form activity.</p>''',
    "Expand Dashboard section with new widgets"
)

# ─────────────────────────────────────────────
# 5. Section 5.3 — Patient View Tabs (fix tab list)
# ─────────────────────────────────────────────
rep(
    '''<p>Click any patient name to open their record. The record has five tabs:</p>

<div class="tbl-wrap">
<table>
  <tr><th>Tab</th><th>Contents</th><th>Available to</th></tr>
  <tr><td><strong>Forms</strong></td><td>All submitted forms in reverse-date order; links to view or export PDF; form-filling buttons; required forms checklist</td><td>All roles</td></tr>
  <tr><td><strong>Photos</strong></td><td>Wound photos grouped by wound location; side-by-side compare mode</td><td>Admin, MA</td></tr>
  <tr><td><strong>Medications</strong></td><td>Active and discontinued medication list</td><td>Admin, MA</td></tr>
  <tr><td><strong>Wounds</strong></td><td>Wound measurement entries with healing trend chart</td><td>Admin, MA</td></tr>
  <tr><td><strong>Last Visit</strong></td><td>Summary strip: date, MA name, vitals snapshot, form count, visit notes</td><td>Admin, MA</td></tr>
</table>
</div>''',
    '''<p>Click any patient name to open their record. The record has nine tabs:</p>

<div class="tbl-wrap">
<table>
  <tr><th>Tab</th><th>Contents</th><th>Available to</th></tr>
  <tr><td><strong>Forms</strong></td><td>All submitted forms; view/PDF/export buttons; required forms checklist; form-filling tiles</td><td>All roles</td></tr>
  <tr><td><strong>Meds</strong></td><td>Active and discontinued medication list; add/edit/discontinue/reactivate controls</td><td>Admin, MA</td></tr>
  <tr><td><strong>Photos</strong></td><td>Wound photos grouped by wound location; lightbox and side-by-side compare mode</td><td>Admin, MA</td></tr>
  <tr><td><strong>Wounds</strong></td><td>Wound measurement entries (length × width × depth, granulation %); healing trend chart</td><td>Admin, MA</td></tr>
  <tr><td><strong>Diagnoses</strong></td><td>ICD-10 diagnosis codes on file; search-and-add new codes; remove codes</td><td>Admin, MA</td></tr>
  <tr><td><strong>Vitals</strong></td><td>Vitals history pulled from Visit Consent form submissions; trend view</td><td>Admin, MA</td></tr>
  <tr><td><strong>Care</strong></td><td>Care coordination notes (threaded); pin important notes to top; reply to notes</td><td>Admin, MA</td></tr>
  <tr><td><strong>Notes</strong></td><td>SOAP clinical notes (Subjective / Objective / Assessment / Plan); draft and finalize</td><td>Admin, MA</td></tr>
  <tr><td><strong>Audit</strong></td><td>Per-patient event history: who viewed, submitted, or edited this patient\'s record</td><td>Admin only</td></tr>
</table>
</div>''',
    "Update patient tabs table (5 → 9 tabs)"
)

# ─────────────────────────────────────────────
# 6. Add sections 5.8, 5.9, 5.10 after 5.7
# ─────────────────────────────────────────────
rep(
    '''<!-- ═══════════════════════════════════════════════
     6. FORMS
═══════════════════════════════════════════════ -->
<h1 class="section" id="s6">''',
    '''<h2 class="sub" id="s5h">5.8 Diagnoses (ICD-10 Codes)</h2>
<p>The <strong>Diagnoses</strong> tab manages the patient's active problem list using standard ICD-10 codes.</p>
<ul class="steps">
  <li>Type at least 3 characters in the <strong>Search ICD-10 code or description</strong> box. Results appear from the live ICD-10 search.</li>
  <li>Click a result to select it — the code and description fill in automatically.</li>
  <li>Optionally add a short clinical <strong>Note</strong> for context.</li>
  <li>Click <strong>+ Add</strong> to save the diagnosis to the patient's record.</li>
</ul>
<div class="note">
  <strong>Duplicate prevention:</strong> If the same ICD-10 code is already on file for this patient, the system will block re-adding it with a clear error message.
</div>
<p>To remove a diagnosis, click the <strong>✕</strong> icon on its row (admin can remove any; MA can only remove codes they personally added).</p>

<h2 class="sub" id="s5i">5.9 Care Notes</h2>
<p>The <strong>Care</strong> tab provides a threaded discussion board for clinical coordination notes tied to this patient.</p>
<ul>
  <li><strong>Post a note:</strong> Type in the text box and click <em>Post</em>. Notes support plain text up to 500 characters.</li>
  <li><strong>Reply:</strong> Click the <em>Reply</em> link under any top-level note to add a nested response.</li>
  <li><strong>Pin:</strong> Admins can pin important notes to the top of the list so they stay visible.</li>
  <li><strong>Edit / Delete:</strong> Authors can edit or delete their own notes. Admins can manage all notes.</li>
</ul>

<h2 class="sub" id="s5j">5.10 SOAP Notes</h2>
<p>The <strong>Notes</strong> tab stores structured clinical SOAP notes for each patient visit.</p>
<div class="tbl-wrap">
<table>
  <tr><th>Field</th><th>Description</th></tr>
  <tr><td>Subjective</td><td>Patient\'s reported symptoms and chief complaint</td></tr>
  <tr><td>Objective</td><td>Clinician\'s observations, exam findings, vital measurements</td></tr>
  <tr><td>Assessment</td><td>Clinical diagnosis and interpretation</td></tr>
  <tr><td>Plan</td><td>Treatment plan, medications, follow-up actions</td></tr>
</table>
</div>
<ul>
  <li>Notes are saved as <strong>Draft</strong> until explicitly finalized.</li>
  <li>Once <strong>Finalized</strong>, a note is locked and timestamped — it cannot be edited.</li>
  <li>All SOAP notes are listed in reverse-date order. Click any row to expand and read the full note.</li>
</ul>


<!-- ═══════════════════════════════════════════════
     6. FORMS
═══════════════════════════════════════════════ -->
<h1 class="section" id="s6">''',
    "Add Diagnoses, Care Notes, SOAP Notes subsections"
)

# ─────────────────────────────────────────────
# 7. Add Section 12 — Messages (before Offline Mode which was 13)
# ─────────────────────────────────────────────
rep(
    '''<h1 class="section" id="s13"><span class="num">13</span> Offline Mode (PWA)</h1>''',
    '''<!-- ═══════════════════════════════════════════════
     12. MESSAGES
═══════════════════════════════════════════════ -->
<h1 class="section" id="s12"><span class="num">12</span> Messages — Internal Staff Chat</h1>

<p>PaperlessMD includes a built-in secure messaging system for staff communication. All roles can send and receive messages. Click <strong>Messages</strong> in the navigation bar to open the chat interface.</p>

<h2 class="sub" id="s12a">12.1 Chat Layout</h2>
<p>The Messages page is split into two panels:</p>
<ul>
  <li><strong>Left panel — Chat List:</strong> Shows <em>All Staff</em> (broadcast) at the top, followed by every active staff member sorted by most recent message. Each row shows the person\'s initials, name, role, latest message preview, and an unread count badge.</li>
  <li><strong>Right panel — Conversation:</strong> Shows the message thread with the selected person or the All Staff broadcast. Messages you sent appear on the right (blue); messages from others appear on the left (white).</li>
</ul>

<h2 class="sub" id="s12b">12.2 Sending a Message</h2>
<ul class="steps">
  <li>Click the person you want to message in the left panel — or click <strong>All Staff</strong> for a broadcast to everyone.</li>
  <li>Type your message in the <em>Type a message…</em> box at the bottom.</li>
  <li>Press <strong>Enter</strong> or click the send button (blue arrow) to send.</li>
  <li>To attach a file, click the <strong>📎 paperclip</strong> icon and select a file from your device.</li>
</ul>

<h2 class="sub" id="s12c">12.3 Unread Badges</h2>
<p>A green unread count badge appears on the <strong>Messages</strong> nav item and on each chat row. It clears automatically when you open that conversation.</p>

<h2 class="sub" id="s12d">12.4 Auto-Refresh</h2>
<p>The message list and open conversation auto-refresh every <strong>3 seconds</strong> — no page reload needed. New messages appear instantly in the thread.</p>


<!-- ═══════════════════════════════════════════════
     12.5. MA LOCATION MONITOR
═══════════════════════════════════════════════ -->
<h1 class="section" id="s12b"><span class="num">12.5</span> MA Location Monitor <span class="badge badge-admin">Admin only</span></h1>

<p>Go to <strong>Admin → MA Locations</strong> to open the real-time GPS map of all active MAs currently working in the field.</p>

<ul>
  <li>The <strong>left panel</strong> lists all active staff with a colored dot indicating online status:
    <ul>
      <li><span style="color:#10b981">●</span> <strong>Green</strong> — active in the last 10 minutes</li>
      <li><span style="color:#f59e0b">●</span> <strong>Amber</strong> — active 10–60 minutes ago</li>
      <li><span style="color:#94a3b8">●</span> <strong>Grey</strong> — no recent activity / never logged in</li>
    </ul>
  </li>
  <li>The <strong>map</strong> (OpenStreetMap) shows a marker for each MA who has shared their GPS location. Click a marker to see the MA\'s name and when their location was last recorded.</li>
  <li>Location updates are sent automatically from the MA\'s device while they are logged in and have granted browser location permission. The map refreshes every <strong>60 seconds</strong>.</li>
  <li>The "last seen" label under each staff name is displayed in the clinic\'s configured <strong>timezone</strong> (set under Admin → Settings).</li>
</ul>

<div class="note">
  <strong>Location permission:</strong> MAs must allow location access in their browser when prompted. Without permission, their GPS coordinates will not appear on the map, though their online status dot will still update.
</div>


<!-- ═══════════════════════════════════════════════
     12.7. NOTIFICATIONS
═══════════════════════════════════════════════ -->
<h1 class="section" id="s12c"><span class="num">12.7</span> Notifications</h1>

<p>The bell icon (🔔) in the top-right of every page shows a count of unread notifications. Click it to open the notification panel.</p>
<ul>
  <li>Notifications are generated for events such as new messages, e-sign requests, and admin alerts.</li>
  <li>Click a notification to navigate directly to the relevant record.</li>
  <li>Notifications are automatically marked as read when viewed.</li>
</ul>


<h1 class="section" id="s13"><span class="num">13</span> Offline Mode (PWA)</h1>''',
    "Add Messages, MA Locations, Notifications sections"
)

# ─────────────────────────────────────────────
# 8. TOC — add new entries
# ─────────────────────────────────────────────
rep(
    '<li><a href="#s11b">11.5 e-Sign Queue — Provider Countersignatures</a></li>',
    '''<li><a href="#s11b">11.5 e-Sign Queue — Provider Countersignatures</a></li>
  <li><a href="#s12">12 Messages — Internal Staff Chat</a></li>
  <li><a href="#s12b">12.5 MA Location Monitor</a></li>
  <li><a href="#s12c">12.7 Notifications</a></li>''',
    "Add new TOC entries"
)

rep(
    '<li><a href="#s5g">5.7 Assigned MA</a></li>',
    '''<li><a href="#s5g">5.7 Assigned MA</a></li>
      <li><a href="#s5h">5.8 Diagnoses (ICD-10)</a></li>
      <li><a href="#s5i">5.9 Care Notes</a></li>
      <li><a href="#s5j">5.10 SOAP Notes</a></li>''',
    "Add patient subsections to TOC"
)

# ─────────────────────────────────────────────
# 9. Update version/date in cover if present
# ─────────────────────────────────────────────
rep(
    'meta { margin-top: 24px; font-size: 0.85rem; color: #94a3b8; }',
    'meta { margin-top: 24px; font-size: 0.85rem; color: #94a3b8; }',
    "No-op to check style block"
)

with open('/var/www/paperlessmd/user_manual.html', 'w') as f:
    f.write(c)

print(f"\nDone. {changes} replacements applied.")
