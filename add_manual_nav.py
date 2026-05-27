#!/usr/bin/env python3
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

# ── 1. Add sidebar + topbar CSS before </style> ──────────────────────────────
rep(
    '''  /* ── Footer ── */
  footer {
    text-align: center; padding: 40px 20px;
    font-size: 0.82rem; color: #94a3b8;
    border-top: 1px solid #e2e8f0;
  }
</style>''',
    '''  /* ── Footer ── */
  footer {
    text-align: center; padding: 40px 20px;
    font-size: 0.82rem; color: #94a3b8;
    border-top: 1px solid #e2e8f0;
  }

  /* ── Nav / Layout ── */
  #topbar {
    position: fixed; top: 0; left: 0; right: 0; z-index: 100;
    height: 52px;
    background: #1e293b;
    display: flex; align-items: center; gap: 12px;
    padding: 0 18px;
    box-shadow: 0 1px 6px rgba(0,0,0,.3);
  }
  #topbar .tb-logo { font-weight: 800; font-size: 1rem; color: white; letter-spacing: -.5px; }
  #topbar .tb-logo span { color: #818cf8; }
  #topbar .tb-title { color: #94a3b8; font-size: 0.82rem; border-left: 1px solid #334155; padding-left: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  #topbar .tb-back {
    margin-left: auto; display: flex; align-items: center; gap-6px;
    color: #94a3b8; font-size: 0.8rem; text-decoration: none;
    background: #334155; border-radius: 8px; padding: 5px 12px;
    white-space: nowrap; transition: background .15s, color .15s;
  }
  #topbar .tb-back:hover { background: #4f46e5; color: white; }
  #topbar #sidebarToggle {
    display: none; background: none; border: none; cursor: pointer;
    color: #94a3b8; font-size: 1.2rem; padding: 4px 6px;
  }
  #progressBar {
    position: fixed; top: 52px; left: 0; z-index: 99;
    height: 3px; width: 0%; background: #4f46e5;
    transition: width .08s linear;
  }
  #sidebar {
    position: fixed; top: 52px; left: 0; bottom: 0; z-index: 90;
    width: 252px;
    background: #fff;
    border-right: 1px solid #e2e8f0;
    overflow-y: auto;
    padding: 16px 0 40px;
    transition: transform .25s ease;
  }
  #sidebar::-webkit-scrollbar { width: 4px; }
  #sidebar::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }
  .sb-section { padding: 0 12px; margin-top: 4px; }
  .sb-section > .sb-item { font-weight: 700; font-size: 0.78rem; text-transform: uppercase; letter-spacing: .06em; color: #94a3b8; padding: 6px 8px; cursor: default; }
  .sb-link {
    display: block; padding: 6px 8px 6px 12px;
    font-size: 0.82rem; color: #475569; text-decoration: none;
    border-radius: 7px; line-height: 1.4;
    transition: background .12s, color .12s;
  }
  .sb-link:hover { background: #f1f5f9; color: #1e293b; }
  .sb-link.active { background: #eef2ff; color: #4f46e5; font-weight: 600; }
  .sb-sub { padding-left: 16px; }
  .sb-sub .sb-link { font-size: 0.78rem; padding: 4px 8px 4px 12px; }
  .sb-divider { border: none; border-top: 1px solid #f1f5f9; margin: 8px 12px; }
  /* Offset main content */
  body { padding-top: 52px; }
  .page-wrap { display: flex; }
  .page-main { margin-left: 252px; flex: 1; min-width: 0; }
  .container { max-width: 920px; padding: 0 32px 80px; }
  /* Responsive */
  @media (max-width: 860px) {
    #sidebarToggle { display: block !important; }
    #sidebar { transform: translateX(-260px); }
    #sidebar.open { transform: translateX(0); box-shadow: 4px 0 20px rgba(0,0,0,.12); }
    .page-main { margin-left: 0; }
  }
  @media (max-width: 500px) {
    .cover { padding: 48px 24px; }
    .container { padding: 0 16px 60px; }
  }
</style>''',
    "Add topbar/sidebar/progress CSS"
)

# ── 2. Inject topbar + progressBar + sidebar immediately after <body> ────────
rep(
    '<body>\n\n<!-- ═══════════════════════════════════════════════\n     COVER',
    '''<body>

<!-- ── Top bar ─────────────────────────────────────────────── -->
<div id="topbar">
  <button id="sidebarToggle" onclick="sbToggle()" aria-label="Toggle menu">&#9776;</button>
  <div class="tb-logo">Paperless<span>MD</span></div>
  <div class="tb-title">User Manual</div>
  <a href="/dashboard.php" class="tb-back">&#8592; Back to App</a>
</div>
<div id="progressBar"></div>

<!-- ── Sidebar ──────────────────────────────────────────────── -->
<div id="sidebar">
  <div class="sb-section">
    <div class="sb-item">Contents</div>
    <a href="#s1"   class="sb-link">1 · System Overview</a>
    <a href="#s2"   class="sb-link">2 · Getting Started</a>
    <div class="sb-sub">
      <a href="#s2a" class="sb-link">Logging In</a>
      <a href="#s2b" class="sb-link">Session Timeout</a>
      <a href="#s2c" class="sb-link">Navigation Bar</a>
    </div>
    <a href="#s3"   class="sb-link">3 · Roles &amp; Permissions</a>
    <a href="#s4"   class="sb-link">4 · Dashboard</a>
    <a href="#s5"   class="sb-link">5 · Patients</a>
    <div class="sb-sub">
      <a href="#s5a" class="sb-link">Patient List</a>
      <a href="#s5b" class="sb-link">Adding a Patient</a>
      <a href="#s5c" class="sb-link">Patient Tabs</a>
      <a href="#s5d" class="sb-link">Medications</a>
      <a href="#s5e" class="sb-link">Wound Measurements</a>
      <a href="#s5f" class="sb-link">Wound Photos</a>
      <a href="#s5g" class="sb-link">Assigned MA</a>
      <a href="#s5h" class="sb-link">Diagnoses (ICD-10)</a>
      <a href="#s5i" class="sb-link">Care Notes</a>
      <a href="#s5j" class="sb-link">SOAP Notes</a>
    </div>
    <a href="#s6"   class="sb-link">6 · Forms</a>
    <div class="sb-sub">
      <a href="#s6a" class="sb-link">Available Forms</a>
      <a href="#s6b" class="sb-link">Completing a Form</a>
      <a href="#s6c" class="sb-link">Auto-Save Draft</a>
      <a href="#s6d" class="sb-link">Voice Dictation</a>
      <a href="#s6e" class="sb-link">Signing &amp; Submitting</a>
      <a href="#s6f" class="sb-link">Form Statuses</a>
      <a href="#s6g" class="sb-link">Export PDF</a>
    </div>
    <a href="#s7"   class="sb-link">7 · Visit Types</a>
    <a href="#s8"   class="sb-link">8 · Schedule (MA View)</a>
    <div class="sb-sub">
      <a href="#s8a" class="sb-link">Navigating</a>
      <a href="#s8b" class="sb-link">Visit Actions</a>
      <a href="#s8c" class="sb-link">Google Maps</a>
    </div>
    <a href="#s9"   class="sb-link">9 · Admin Schedule</a>
    <div class="sb-sub">
      <a href="#s9a" class="sb-link">Adding a Visit</a>
      <a href="#s9b" class="sb-link">Reordering</a>
      <a href="#s9c" class="sb-link">Route Optimizer</a>
      <a href="#s9d" class="sb-link">Deleting a Visit</a>
    </div>
    <a href="#s10"  class="sb-link">10 · Staff Management</a>
    <a href="#s11"  class="sb-link">11 · Audit Log</a>
    <a href="#s11b" class="sb-link">11.5 · e-Sign Queue</a>
    <hr class="sb-divider">
    <a href="#s12"  class="sb-link">12 · Messages</a>
    <div class="sb-sub">
      <a href="#s12a" class="sb-link">Chat Layout</a>
      <a href="#s12b" class="sb-link">Sending a Message</a>
      <a href="#s12c" class="sb-link">Unread Badges</a>
      <a href="#s12d" class="sb-link">Auto-Refresh</a>
    </div>
    <a href="#s12b" class="sb-link">12.5 · MA Location Monitor</a>
    <a href="#s12c" class="sb-link">12.7 · Notifications</a>
    <hr class="sb-divider">
    <a href="#s13"  class="sb-link">13 · Offline Mode</a>
    <a href="#s14"  class="sb-link">14 · Security &amp; Privacy</a>
    <a href="#s15"  class="sb-link">15 · Troubleshooting</a>
    <a href="#s16"  class="sb-link">16 · Quick Reference</a>
  </div>
</div>

<div class="page-wrap">
<div class="page-main">

<!-- ═══════════════════════════════════════════════
     COVER''',
    "Inject topbar + sidebar + page-wrap open"
)

# ── 3. Close page-wrap + page-main before </body> ────────────────────────────
rep(
    '</body>\n</html>',
    '''</div><!-- /page-main -->
</div><!-- /page-wrap -->

<script>
// ── Scroll progress bar ──────────────────────────────────────
const bar = document.getElementById('progressBar');
function updateProgress() {
  const scrollTop  = window.scrollY;
  const docHeight  = document.documentElement.scrollHeight - window.innerHeight;
  bar.style.width  = (docHeight > 0 ? (scrollTop / docHeight) * 100 : 0) + '%';
}

// ── Active sidebar link ──────────────────────────────────────
const sbLinks   = document.querySelectorAll('#sidebar .sb-link[href^="#"]');
const sections  = [];
sbLinks.forEach(a => {
  const id = a.getAttribute('href').slice(1);
  const el = document.getElementById(id);
  if (el) sections.push({ id, el, a });
});

function updateActive() {
  const scrollY = window.scrollY + 80;
  let current   = sections[0];
  sections.forEach(s => { if (s.el.offsetTop <= scrollY) current = s; });
  sbLinks.forEach(a => a.classList.remove('active'));
  if (current) {
    current.a.classList.add('active');
    // scroll sidebar to keep active link visible
    const sb  = document.getElementById('sidebar');
    const lnk = current.a;
    const lTop = lnk.offsetTop;
    if (lTop < sb.scrollTop + 40 || lTop > sb.scrollTop + sb.clientHeight - 60) {
      sb.scrollTo({ top: lTop - sb.clientHeight / 2, behavior: 'smooth' });
    }
  }
}

// ── Topbar section title ─────────────────────────────────────
const headings = document.querySelectorAll('h1.section');
const titleEl  = document.querySelector('#topbar .tb-title');
function updateTitle() {
  const scrollY = window.scrollY + 80;
  let active = null;
  headings.forEach(h => { if (h.offsetTop <= scrollY) active = h; });
  if (active) {
    const clone = active.cloneNode(true);
    clone.querySelectorAll('.num').forEach(n => n.remove());
    titleEl.textContent = clone.textContent.trim();
  } else {
    titleEl.textContent = 'User Manual';
  }
}

window.addEventListener('scroll', () => { updateProgress(); updateActive(); updateTitle(); }, { passive: true });
updateProgress(); updateActive(); updateTitle();

// ── Sidebar toggle (mobile) ──────────────────────────────────
function sbToggle() {
  document.getElementById('sidebar').classList.toggle('open');
}
// Close sidebar when a link is clicked on mobile
document.querySelectorAll('#sidebar .sb-link').forEach(a => {
  a.addEventListener('click', () => {
    if (window.innerWidth <= 860) {
      document.getElementById('sidebar').classList.remove('open');
    }
  });
});
// Close sidebar when clicking outside
document.addEventListener('click', e => {
  const sb = document.getElementById('sidebar');
  if (window.innerWidth <= 860 && sb.classList.contains('open')
      && !sb.contains(e.target) && e.target.id !== 'sidebarToggle') {
    sb.classList.remove('open');
  }
});
</script>

</body>
</html>''',
    "Close page-wrap + inject JS"
)

with open('/var/www/paperlessmd/user_manual.html', 'w') as f:
    f.write(c)

print(f"\nDone. {changes} replacements applied.")
