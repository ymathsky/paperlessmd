<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$pageTitle = "What's New";
$activeNav = 'whats_new';

include __DIR__ . '/includes/header.php';

$releases = [
    [
        'version' => 'v1.9',
        'date'    => 'April 30, 2026',
        'label'   => 'Latest',
        'color'   => 'emerald',
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
            ['icon' => 'bi-pencil-square',     'tag' => 'New',     'tag_color' => 'blue',    'title' => 'Handwriting Pad for Medications',       'desc' => 'Draw medication names, doses, and frequencies with a tablet stylus or finger directly on the Medications step of Visit Consent and New Patient Pocket forms. Saved as an image and printed on the document.'],
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
            ['icon' => 'bi-person-badge-fill',  'tag' => 'New',     'tag_color' => 'blue',    'title' => 'Provider Signature Pre-Fill',           'desc' => 'Provider signatures saved on the profile are auto-applied to the New Patient Pocket form, reducing repeated signing.'],
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

<!-- Timeline -->
<div class="max-w-3xl">
    <?php foreach ($releases as $ri => $release):
        $vc = $versionColors[$release['color']] ?? $versionColors['slate'];
    ?>
    <div class="flex gap-5 <?= $ri > 0 ? 'mt-10' : '' ?>">

        <!-- Timeline spine -->
        <div class="flex flex-col items-center shrink-0">
            <div class="w-10 h-10 rounded-full ring-2 ring-offset-2 <?= $vc['ring'] ?> <?= $vc['bg'] ?>
                        flex items-center justify-center text-white font-extrabold text-xs shadow-sm">
                <?= htmlspecialchars($release['version']) ?>
            </div>
            <?php if ($ri < count($releases) - 1): ?>
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

    <!-- End cap -->
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
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
