<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireLogin();
requireAdmin();

$pageTitle = 'Roles & Permissions';
$activeNav = 'roles';

// ── Permission matrix definition ────────────────────────────────────────────
$sections = [
    [
        'title' => 'Patients',
        'icon'  => 'bi-people-fill',
        'color' => 'blue',
        'rows'  => [
            ['label' => 'View patient list',                  'desc' => '',                                       'admin' => true,  'ma' => true,  'billing' => true,  'scheduler' => true,  'provider' => true ],
            ['label' => 'View patient profile',               'desc' => '',                                       'admin' => true,  'ma' => true,  'billing' => true,  'scheduler' => true,  'provider' => true ],
            ['label' => 'Add / edit patients',                'desc' => 'Create new records, update demographics','admin' => true,  'ma' => true,  'billing' => false, 'scheduler' => false, 'provider' => false],
            ['label' => 'Change patient status',              'desc' => 'Active / Inactive / Discharged',         'admin' => true,  'ma' => true,  'billing' => false, 'scheduler' => false, 'provider' => false],
            ['label' => 'Delete patient records',             'desc' => 'Permanent removal',                      'admin' => true,  'ma' => false, 'billing' => false, 'scheduler' => false, 'provider' => false],
            ['label' => 'Upload patient photo',               'desc' => '',                                       'admin' => true,  'ma' => true,  'billing' => false, 'scheduler' => false, 'provider' => false],
        ],
    ],
    [
        'title' => 'Forms & Documents',
        'icon'  => 'bi-file-earmark-text-fill',
        'color' => 'indigo',
        'rows'  => [
            ['label' => 'Fill out / submit forms',            'desc' => 'All 12 form types',                      'admin' => true,  'ma' => true,  'billing' => false, 'scheduler' => false, 'provider' => false],
            ['label' => 'View submitted documents',           'desc' => 'Read-only view',                         'admin' => true,  'ma' => true,  'billing' => true,  'scheduler' => false, 'provider' => true ],
            ['label' => 'Print / export PDF',                 'desc' => 'Single or bulk export',                  'admin' => true,  'ma' => true,  'billing' => true,  'scheduler' => false, 'provider' => true ],
            ['label' => 'Upload documents to portal',         'desc' => 'Mark as Uploaded to PF',                 'admin' => true,  'ma' => true,  'billing' => false, 'scheduler' => false, 'provider' => false],
            ['label' => 'Request e-signature',                'desc' => '',                                       'admin' => true,  'ma' => true,  'billing' => false, 'scheduler' => false, 'provider' => false],
            ['label' => 'Provider e-signature',               'desc' => 'Sign forms as the ordering provider',    'admin' => true,  'ma' => false, 'billing' => false, 'scheduler' => false, 'provider' => true ],
            ['label' => 'Delete form submissions',            'desc' => 'Permanent removal',                      'admin' => true,  'ma' => false, 'billing' => false, 'scheduler' => false, 'provider' => false],
        ],
    ],
    [
        'title' => 'Clinical Data',
        'icon'  => 'bi-heart-pulse-fill',
        'color' => 'red',
        'rows'  => [
            ['label' => 'View vitals & vital trends',         'desc' => 'BP, weight, O2, pulse, glucose, etc.',   'admin' => true,  'ma' => true,  'billing' => false, 'scheduler' => false, 'provider' => true ],
            ['label' => 'View & manage medications',          'desc' => 'Add, edit, discontinue meds',            'admin' => true,  'ma' => true,  'billing' => false, 'scheduler' => false, 'provider' => true ],
            ['label' => 'View & log wound measurements',      'desc' => 'Wound size, photos, trend chart',        'admin' => true,  'ma' => true,  'billing' => false, 'scheduler' => false, 'provider' => true ],
            ['label' => 'Wound photos',                       'desc' => 'Upload and view wound images',           'admin' => true,  'ma' => true,  'billing' => false, 'scheduler' => false, 'provider' => true ],
            ['label' => 'Manage diagnoses (ICD-10)',          'desc' => 'Add / remove diagnosis codes',           'admin' => true,  'ma' => true,  'billing' => false, 'scheduler' => false, 'provider' => true ],
            ['label' => 'SOAP notes',                         'desc' => 'Create and edit clinical notes',         'admin' => true,  'ma' => true,  'billing' => false, 'scheduler' => false, 'provider' => true ],
            ['label' => 'Care coordination notes',            'desc' => 'Team discussion thread per patient',     'admin' => true,  'ma' => true,  'billing' => false, 'scheduler' => false, 'provider' => true ],
        ],
    ],
    [
        'title' => 'Billing & Coding',
        'icon'  => 'bi-receipt-cutoff',
        'color' => 'amber',
        'rows'  => [
            ['label' => 'View submitted forms (read-only)',   'desc' => 'For coding reference',                   'admin' => true,  'ma' => true,  'billing' => true,  'scheduler' => false, 'provider' => true ],
            ['label' => 'View ICD-10 diagnoses',              'desc' => 'Diagnosis codes on patient record',      'admin' => true,  'ma' => true,  'billing' => true,  'scheduler' => false, 'provider' => true ],
            ['label' => 'Export forms to PDF',                'desc' => 'For billing records',                    'admin' => true,  'ma' => true,  'billing' => true,  'scheduler' => false, 'provider' => true ],
            ['label' => 'Add / edit diagnoses',               'desc' => 'Modify clinical data',                   'admin' => true,  'ma' => true,  'billing' => false, 'scheduler' => false, 'provider' => true ],
            ['label' => 'View clinical PHI fields',           'desc' => 'Vitals, meds, wounds, chief complaint',  'admin' => true,  'ma' => true,  'billing' => false, 'scheduler' => false, 'provider' => true ],
        ],
    ],
    [
        'title' => 'Communication',
        'icon'  => 'bi-chat-dots-fill',
        'color' => 'teal',
        'rows'  => [
            ['label' => 'Internal messaging',                 'desc' => 'Send/receive staff messages',            'admin' => true,  'ma' => true,  'billing' => true,  'scheduler' => true,  'provider' => true ],
            ['label' => 'Receive notifications',              'desc' => 'Bell icon — pending uploads, drafts',    'admin' => true,  'ma' => true,  'billing' => true,  'scheduler' => true,  'provider' => true ],
            ['label' => 'Global search',                      'desc' => 'Search patients and forms (Ctrl+K)',     'admin' => true,  'ma' => true,  'billing' => false, 'scheduler' => false, 'provider' => true ],
            ['label' => 'View patient messages/updates',      'desc' => 'Message thread for billing only',        'admin' => true,  'ma' => true,  'billing' => true,  'scheduler' => false, 'provider' => true ],
        ],
    ],
    [
        'title' => 'Scheduling',
        'icon'  => 'bi-calendar-week-fill',
        'color' => 'violet',
        'rows'  => [
            ['label' => 'View daily schedule',                'desc' => '',                                       'admin' => true,  'ma' => true,  'billing' => false, 'scheduler' => true,  'provider' => true ],
            ['label' => 'Add / edit visits',                  'desc' => 'Schedule patient home visits',           'admin' => true,  'ma' => false, 'billing' => false, 'scheduler' => true,  'provider' => false],
            ['label' => 'Manage recurring schedules',         'desc' => 'Set up repeating visit patterns',        'admin' => true,  'ma' => false, 'billing' => false, 'scheduler' => true,  'provider' => false],
            ['label' => 'Mark visits complete / en-route',   'desc' => '',                                       'admin' => true,  'ma' => true,  'billing' => false, 'scheduler' => false, 'provider' => false],
            ['label' => 'Productivity reports',               'desc' => 'Per-MA visit and form metrics',          'admin' => true,  'ma' => false, 'billing' => false, 'scheduler' => false, 'provider' => false],
        ],
    ],
    [
        'title' => 'Administration',
        'icon'  => 'bi-shield-lock-fill',
        'color' => 'purple',
        'rows'  => [
            ['label' => 'Add / edit staff accounts',          'desc' => 'Create users, change passwords',         'admin' => true,  'ma' => false, 'billing' => false, 'scheduler' => false, 'provider' => false],
            ['label' => 'Assign / change user roles',         'desc' => '',                                       'admin' => true,  'ma' => false, 'billing' => false, 'scheduler' => false, 'provider' => false],
            ['label' => 'Enable / disable staff accounts',    'desc' => '',                                       'admin' => true,  'ma' => false, 'billing' => false, 'scheduler' => false, 'provider' => false],
            ['label' => 'View HIPAA audit log',               'desc' => 'Full access / action trail',             'admin' => true,  'ma' => false, 'billing' => false, 'scheduler' => false, 'provider' => false],
            ['label' => 'System settings',                    'desc' => 'Portal URL, session timeout, etc.',      'admin' => true,  'ma' => false, 'billing' => false, 'scheduler' => false, 'provider' => false],
        ],
    ],
];

// ── Staff counts per role ─────────────────────────────────────────────────
$roleCounts = [];
$rcRows = $pdo->query("SELECT role, COUNT(*) AS cnt FROM staff WHERE active=1 GROUP BY role")->fetchAll();
foreach ($rcRows as $r) { $roleCounts[$r['role']] = (int)$r['cnt']; }

include __DIR__ . '/../includes/header.php';
?>

<!-- Page header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
        <h2 class="text-2xl font-extrabold text-slate-800">Roles &amp; Permissions</h2>
        <p class="text-slate-500 text-sm mt-0.5">What each staff role can see and do in PaperlessMD.</p>
    </div>
    <a href="<?= BASE_URL ?>/admin/users.php"
       class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold
              px-4 py-2.5 rounded-xl transition-all shadow-sm text-sm active:scale-95">
        <i class="bi bi-people-fill"></i> Manage Staff
    </a>
</div>

<!-- Role summary cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    <?php
    $roleMeta = [
        'admin'     => ['label'=>'Admin',             'color'=>'indigo',  'icon'=>'bi-shield-fill-check',    'desc'=>'Full access to all features, settings, and audit trails.'],
        'ma'        => ['label'=>'Medical Assistant', 'color'=>'blue',    'icon'=>'bi-bandaid-fill',          'desc'=>'Clinical and form access. No admin panel or account management.'],
        'provider'  => ['label'=>'Provider',          'color'=>'teal',    'icon'=>'bi-person-badge-fill',     'desc'=>'Physician/NP — reviews patient charts, signs forms, and views schedules.'],
        'scheduler' => ['label'=>'Scheduler',         'color'=>'violet',  'icon'=>'bi-calendar-week-fill',   'desc'=>'Schedule management only. No clinical data or admin functions.'],
        'billing'   => ['label'=>'Billing',           'color'=>'amber',   'icon'=>'bi-receipt-cutoff',        'desc'=>'Read-only access to submitted forms and diagnoses. No PHI clinical fields.'],
    ];
    foreach ($roleMeta as $role => $meta):
        $cnt = $roleCounts[$role] ?? 0;
        $c   = $meta['color'];
    ?>
    <div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-5 flex items-start gap-4">
        <div class="w-12 h-12 rounded-xl bg-<?= $c ?>-100 grid place-items-center flex-shrink-0">
            <i class="bi <?= $meta['icon'] ?> text-<?= $c ?>-600 text-xl"></i>
        </div>
        <div>
            <div class="font-bold text-slate-800 text-base"><?= $meta['label'] ?></div>
            <div class="text-xs text-slate-500 mt-0.5 mb-2 leading-snug"><?= $meta['desc'] ?></div>
            <a href="<?= BASE_URL ?>/admin/users.php"
               class="inline-flex items-center gap-1.5 text-xs font-semibold text-<?= $c ?>-600 bg-<?= $c ?>-50 hover:bg-<?= $c ?>-100 px-2.5 py-1 rounded-lg transition-colors">
                <i class="bi bi-person-fill"></i>
                <?= $cnt ?> active staff
            </a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Legend -->
<div class="flex items-center gap-6 mb-6 text-sm text-slate-500 flex-wrap">
    <div class="flex items-center gap-1.5">
        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-100">
            <i class="bi bi-check-lg text-emerald-600 text-xs"></i>
        </span>
        Allowed
    </div>
    <div class="flex items-center gap-1.5">
        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-red-100">
            <i class="bi bi-x-lg text-red-400 text-xs"></i>
        </span>
        Not allowed
    </div>
</div>

<!-- Permission sections -->
<div class="space-y-5">
<?php foreach ($sections as $sec):
    $colorMap = [
        'blue'   => ['bg'=>'bg-blue-600',   'light'=>'bg-blue-50',  'text'=>'text-blue-700',  'border'=>'border-blue-100'],
        'indigo' => ['bg'=>'bg-indigo-600', 'light'=>'bg-indigo-50','text'=>'text-indigo-700','border'=>'border-indigo-100'],
        'red'    => ['bg'=>'bg-red-600',    'light'=>'bg-red-50',   'text'=>'text-red-700',   'border'=>'border-red-100'],
        'amber'  => ['bg'=>'bg-amber-500',  'light'=>'bg-amber-50', 'text'=>'text-amber-700', 'border'=>'border-amber-100'],
        'teal'   => ['bg'=>'bg-teal-600',   'light'=>'bg-teal-50',  'text'=>'text-teal-700',  'border'=>'border-teal-100'],
        'violet' => ['bg'=>'bg-violet-600', 'light'=>'bg-violet-50','text'=>'text-violet-700','border'=>'border-violet-100'],
        'purple' => ['bg'=>'bg-purple-700', 'light'=>'bg-purple-50','text'=>'text-purple-700','border'=>'border-purple-100'],
    ];
    $cl = $colorMap[$sec['color']] ?? $colorMap['blue'];
?>
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <!-- Section heading -->
    <div class="flex items-center gap-3 px-6 py-4 border-b border-slate-100 bg-slate-50/60">
        <div class="w-8 h-8 rounded-xl <?= $cl['bg'] ?> grid place-items-center flex-shrink-0">
            <i class="bi <?= $sec['icon'] ?> text-white text-sm"></i>
        </div>
        <h3 class="font-bold text-slate-700 text-sm uppercase tracking-wide"><?= $sec['title'] ?></h3>
    </div>

    <!-- Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-100">
                    <th class="px-6 py-3 text-left text-xs font-bold text-slate-400 uppercase tracking-wide w-full">Permission</th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-indigo-600 uppercase tracking-wide whitespace-nowrap min-w-[90px]">
                        <div class="flex items-center justify-center gap-1.5">
                            <i class="bi bi-shield-fill-check"></i> Admin
                        </div>
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-blue-600 uppercase tracking-wide whitespace-nowrap min-w-[110px]">
                        <div class="flex items-center justify-center gap-1.5">
                            <i class="bi bi-bandaid-fill"></i> Med. Asst.
                        </div>
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-teal-600 uppercase tracking-wide whitespace-nowrap min-w-[100px]">
                        <div class="flex items-center justify-center gap-1.5">
                            <i class="bi bi-person-badge-fill"></i> Provider
                        </div>
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-violet-600 uppercase tracking-wide whitespace-nowrap min-w-[100px]">
                        <div class="flex items-center justify-center gap-1.5">
                            <i class="bi bi-calendar-week-fill"></i> Scheduler
                        </div>
                    </th>
                    <th class="px-4 py-3 text-center text-xs font-bold text-amber-600 uppercase tracking-wide whitespace-nowrap min-w-[90px]">
                        <div class="flex items-center justify-center gap-1.5">
                            <i class="bi bi-receipt-cutoff"></i> Billing
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                <?php foreach ($sec['rows'] as $row): ?>
                <tr class="hover:bg-slate-50/60 transition-colors">
                    <td class="px-6 py-3.5">
                        <div class="font-medium text-slate-700"><?= htmlspecialchars($row['label']) ?></div>
                        <?php if ($row['desc']): ?>
                        <div class="text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($row['desc']) ?></div>
                        <?php endif; ?>
                    </td>
                    <?php foreach (['admin','ma','provider','scheduler','billing'] as $role): ?>
                    <td class="px-4 py-3.5 text-center">
                        <?php if ($row[$role]): ?>
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-emerald-100">
                            <i class="bi bi-check-lg text-emerald-600 text-sm"></i>
                        </span>
                        <?php else: ?>
                        <span class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-slate-100">
                            <i class="bi bi-x-lg text-slate-300 text-sm"></i>
                        </span>
                        <?php endif; ?>
                    </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>
</div>

<!-- Footer note -->
<p class="mt-6 text-xs text-slate-400 text-center">
    Permissions are enforced server-side on every request and API endpoint.
    Role changes take effect on the user's next login.
    To modify a user's role, go to
    <a href="<?= BASE_URL ?>/admin/users.php" class="text-indigo-500 hover:underline">Manage Staff</a>.
</p>

<?php include __DIR__ . '/../includes/footer.php'; ?>
