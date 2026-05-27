#!/usr/bin/env python3
with open('/var/www/paperlessmd/admin/manage_user.php', 'r') as f:
    c = f.read()

OLD = """<nav class="flex items-center gap-2 text-sm text-slate-400 mb-6">
    <a href="<?= BASE_URL ?>/admin/users.php" class="hover:text-blue-600 font-medium">Manage Staff</a>
    <i class="bi bi-chevron-right text-xs"></i>
    <span class="text-slate-700 font-semibold"><?= $isEdit ? 'Edit ' . h($user['full_name']) : 'Add Staff Member' ?></span>
</nav>

<div class="max-w-lg">
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
    <div class="bg-gradient-to-r from-indigo-600 to-indigo-500 px-6 py-4 flex items-center gap-3">
        <div class="bg-white/20 p-2 rounded-xl">
            <i class="bi bi-<?= $isEdit ? 'pencil-fill' : 'person-plus-fill' ?> text-white text-xl"></i>
        </div>
        <div>
            <h2 class="text-white font-bold text-lg"><?= $isEdit ? 'Edit Staff Member' : 'Add Staff Member' ?></h2>
            <?php if ($isEdit): ?>
            <p class="text-indigo-200 text-sm"><?= h($user['full_name']) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="p-6">
        <?php if ($error): ?>
        <div class="mb-5 flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
            <i class="bi bi-exclamation-circle-fill flex-shrink-0"></i> <?= h($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                    Email Address <span class="text-slate-400 text-xs font-normal">(for notifications)</span>
                </label>
                <input type="email" name="email" value="<?= h($vals['email']) ?>"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition focus:bg-white"
                       placeholder="jsmith@example.com" autocomplete="email">
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                    Full Name <span class="text-red-400">*</span>
                </label>
                <input type="text" name="full_name" value="<?= h($vals['full_name']) ?>"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition focus:bg-white"
                       placeholder="Dr. Jane Smith" required autofocus>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                    Username <span class="text-red-400">*</span>
                </label>
                <input type="text" name="username" value="<?= h($vals['username']) ?>"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition focus:bg-white font-mono"
                       placeholder="jsmith" autocomplete="off" required>
            </div>

                <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Role</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <label class="flex items-center gap-3 p-3.5 border border-slate-200 rounded-xl cursor-pointer transition-colors has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50">
                        <input type="radio" name="role" value="ma" <?= $vals['role'] === 'ma' ? 'checked' : '' ?>
                               class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-400">
                        <div>
                            <div class="text-sm font-semibold text-slate-700">Medical Assistant</div>
                            <div class="text-xs text-slate-500">Clinical access</div>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3.5 border border-slate-200 rounded-xl cursor-pointer transition-colors has-[:checked]:border-teal-400 has-[:checked]:bg-teal-50">
                        <input type="radio" name="role" value="provider" <?= $vals['role'] === 'provider' ? 'checked' : '' ?>
                               class="w-4 h-4 text-teal-600 border-slate-300 focus:ring-teal-400">
                        <div>
                            <div class="text-sm font-semibold text-slate-700">Provider</div>
                            <div class="text-xs text-slate-500">Physician / NP — signs &amp; reviews forms</div>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3.5 border border-slate-200 rounded-xl cursor-pointer transition-colors has-[:checked]:border-violet-400 has-[:checked]:bg-violet-50">
                        <input type="radio" name="role" value="scheduler" <?= $vals['role'] === 'scheduler' ? 'checked' : '' ?>
                               class="w-4 h-4 text-violet-600 border-slate-300 focus:ring-violet-400">
                        <div>
                            <div class="text-sm font-semibold text-slate-700">Scheduler</div>
                            <div class="text-xs text-slate-500">Schedule management only</div>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3.5 border border-slate-200 rounded-xl cursor-pointer transition-colors has-[:checked]:border-teal-400 has-[:checked]:bg-teal-50">
                        <input type="radio" name="role" value="pcc" <?= $vals['role'] === 'pcc' ? 'checked' : '' ?>
                               class="w-4 h-4 text-teal-600 border-slate-300 focus:ring-teal-400">
                        <div>
                            <div class="text-sm font-semibold text-slate-700">PCC <span class="text-xs font-normal text-slate-400">Patient Care Coordinator</span></div>
                            <div class="text-xs text-slate-500">Clinical access — same as MA</div>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3.5 border border-slate-200 rounded-xl cursor-pointer transition-colors has-[:checked]:border-amber-400 has-[:checked]:bg-amber-50">
                        <input type="radio" name="role" value="billing" <?= $vals['role'] === 'billing' ? 'checked' : '' ?>
                               class="w-4 h-4 text-amber-600 border-slate-300 focus:ring-amber-400">
                        <div>
                            <div class="text-sm font-semibold text-slate-700">Billing</div>
                            <div class="text-xs text-slate-500">Forms &amp; ICD-10 only</div>
                        </div>
                    </label>
                    <label class="flex items-center gap-3 p-3.5 border border-slate-200 rounded-xl cursor-pointer transition-colors has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50">
                        <input type="radio" name="role" value="admin" <?= $vals['role'] === 'admin' ? 'checked' : '' ?>
                               class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-400">
                        <div>
                            <div class="text-sm font-semibold text-slate-700">Admin</div>
                            <div class="text-xs text-slate-500">Full access</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Password -->
            <div class="mb-4">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                    Password
                    <?php if ($isEdit): ?>
                    <span class="ml-1 text-xs text-slate-400 font-normal">(leave blank to keep current)</span>
                    <?php else: ?>
                    <span class="text-red-400">*</span>
                    <?php endif; ?>
                </label>
                <input type="password" name="password"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition focus:bg-white"
                       placeholder="Min. 6 characters" autocomplete="new-password"
                       <?= !$isEdit ? 'required' : '' ?>>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">Confirm Password</label>
                <input type="password" name="password2"
                       class="w-full px-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                              focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition focus:bg-white"
                       placeholder="Repeat password" autocomplete="new-password">
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <button type="submit"
                        class="flex-1 sm:flex-none flex items-center justify-center gap-2
                               bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white font-bold
                               px-8 py-3 rounded-xl transition-all shadow-sm hover:shadow-md">
                    <i class="bi bi-check-circle-fill"></i> <?= $isEdit ? 'Save Changes' : 'Create Account' ?>
                </button>
                <a href="<?= BASE_URL ?>/admin/users.php"
                   class="flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold
                          text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
</div>"""

NEW = """<nav class="flex items-center gap-2 text-sm text-slate-400 mb-6">
    <a href="<?= BASE_URL ?>/admin/users.php" class="hover:text-blue-600 font-medium transition-colors">Manage Staff</a>
    <i class="bi bi-chevron-right text-xs"></i>
    <span class="text-slate-700 font-semibold"><?= $isEdit ? 'Edit ' . h($user['full_name']) : 'Add Staff Member' ?></span>
</nav>

<div class="max-w-2xl">
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">

    <!-- Card header -->
    <div class="bg-gradient-to-r from-indigo-600 to-indigo-500 px-6 py-5 flex items-center gap-4">
        <div class="w-11 h-11 bg-white/20 rounded-xl flex items-center justify-center shrink-0">
            <i class="bi bi-<?= $isEdit ? 'pencil-fill' : 'person-plus-fill' ?> text-white text-xl"></i>
        </div>
        <div>
            <h2 class="text-white font-bold text-lg leading-tight"><?= $isEdit ? 'Edit Staff Member' : 'Add Staff Member' ?></h2>
            <?php if ($isEdit): ?>
            <p class="text-indigo-200 text-sm mt-0.5"><?= h($user['full_name']) ?></p>
            <?php else: ?>
            <p class="text-indigo-200 text-sm mt-0.5">Create a new staff account and assign a role</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="p-6 sm:p-8">

        <?php if ($error): ?>
        <div class="mb-6 flex items-start gap-3 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
            <i class="bi bi-exclamation-circle-fill mt-0.5 shrink-0"></i> <?= h($error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

            <!-- Row 1: Full Name + Email -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Full Name <span class="text-red-400">*</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                            <i class="bi bi-person-fill text-sm"></i>
                        </span>
                        <input type="text" name="full_name" value="<?= h($vals['full_name']) ?>"
                               class="w-full pl-9 pr-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent focus:bg-white transition"
                               placeholder="Dr. Jane Smith" required autofocus>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Email <span class="text-slate-400 font-normal text-xs">(for notifications)</span>
                    </label>
                    <div class="relative">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                            <i class="bi bi-envelope-fill text-sm"></i>
                        </span>
                        <input type="email" name="email" value="<?= h($vals['email']) ?>"
                               class="w-full pl-9 pr-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                      focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent focus:bg-white transition"
                               placeholder="jsmith@example.com" autocomplete="email">
                    </div>
                </div>
            </div>

            <!-- Username -->
            <div class="mb-5">
                <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                    Username <span class="text-red-400">*</span>
                </label>
                <div class="relative">
                    <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                        <i class="bi bi-at text-sm"></i>
                    </span>
                    <input type="text" name="username" value="<?= h($vals['username']) ?>"
                           class="w-full pl-9 pr-4 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                  focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent focus:bg-white transition font-mono"
                           placeholder="jsmith" autocomplete="off" required>
                </div>
                <p class="text-xs text-slate-400 mt-1.5 ml-1">Used to log in. Lowercase letters and numbers recommended.</p>
            </div>

            <!-- Role -->
            <div class="mb-5">
                <label class="block text-sm font-semibold text-slate-700 mb-2">Role</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">

                    <label class="flex items-start gap-3 p-3.5 border-2 border-slate-200 rounded-xl cursor-pointer transition-all
                                  hover:border-indigo-300 has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50">
                        <input type="radio" name="role" value="ma" <?= $vals['role'] === 'ma' ? 'checked' : '' ?>
                               class="mt-0.5 w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-400 shrink-0">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <i class="bi bi-person-badge-fill text-indigo-500 text-sm"></i>
                                <span class="text-sm font-semibold text-slate-700">Medical Assistant</span>
                            </div>
                            <div class="text-xs text-slate-500 mt-0.5">Full clinical access — forms, vitals, wounds, notes</div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3.5 border-2 border-slate-200 rounded-xl cursor-pointer transition-all
                                  hover:border-teal-300 has-[:checked]:border-teal-400 has-[:checked]:bg-teal-50">
                        <input type="radio" name="role" value="provider" <?= $vals['role'] === 'provider' ? 'checked' : '' ?>
                               class="mt-0.5 w-4 h-4 text-teal-600 border-slate-300 focus:ring-teal-400 shrink-0">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <i class="bi bi-clipboard2-pulse-fill text-teal-500 text-sm"></i>
                                <span class="text-sm font-semibold text-slate-700">Provider</span>
                            </div>
                            <div class="text-xs text-slate-500 mt-0.5">Physician / NP — signs &amp; reviews forms</div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3.5 border-2 border-slate-200 rounded-xl cursor-pointer transition-all
                                  hover:border-violet-300 has-[:checked]:border-violet-400 has-[:checked]:bg-violet-50">
                        <input type="radio" name="role" value="scheduler" <?= $vals['role'] === 'scheduler' ? 'checked' : '' ?>
                               class="mt-0.5 w-4 h-4 text-violet-600 border-slate-300 focus:ring-violet-400 shrink-0">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <i class="bi bi-calendar-week-fill text-violet-500 text-sm"></i>
                                <span class="text-sm font-semibold text-slate-700">Scheduler</span>
                            </div>
                            <div class="text-xs text-slate-500 mt-0.5">Schedule management only</div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3.5 border-2 border-slate-200 rounded-xl cursor-pointer transition-all
                                  hover:border-teal-300 has-[:checked]:border-teal-400 has-[:checked]:bg-teal-50">
                        <input type="radio" name="role" value="pcc" <?= $vals['role'] === 'pcc' ? 'checked' : '' ?>
                               class="mt-0.5 w-4 h-4 text-teal-600 border-slate-300 focus:ring-teal-400 shrink-0">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <i class="bi bi-heart-pulse-fill text-teal-500 text-sm"></i>
                                <span class="text-sm font-semibold text-slate-700">PCC
                                    <span class="font-normal text-slate-400 text-xs">Patient Care Coordinator</span>
                                </span>
                            </div>
                            <div class="text-xs text-slate-500 mt-0.5">Clinical access — same as MA</div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3.5 border-2 border-slate-200 rounded-xl cursor-pointer transition-all
                                  hover:border-amber-300 has-[:checked]:border-amber-400 has-[:checked]:bg-amber-50">
                        <input type="radio" name="role" value="billing" <?= $vals['role'] === 'billing' ? 'checked' : '' ?>
                               class="mt-0.5 w-4 h-4 text-amber-600 border-slate-300 focus:ring-amber-400 shrink-0">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <i class="bi bi-receipt text-amber-500 text-sm"></i>
                                <span class="text-sm font-semibold text-slate-700">Billing</span>
                            </div>
                            <div class="text-xs text-slate-500 mt-0.5">Forms &amp; ICD-10 codes — read only</div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3.5 border-2 border-slate-200 rounded-xl cursor-pointer transition-all
                                  hover:border-rose-300 has-[:checked]:border-rose-400 has-[:checked]:bg-rose-50">
                        <input type="radio" name="role" value="admin" <?= $vals['role'] === 'admin' ? 'checked' : '' ?>
                               class="mt-0.5 w-4 h-4 text-rose-600 border-slate-300 focus:ring-rose-400 shrink-0">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5">
                                <i class="bi bi-shield-fill-check text-rose-500 text-sm"></i>
                                <span class="text-sm font-semibold text-slate-700">Admin</span>
                            </div>
                            <div class="text-xs text-slate-500 mt-0.5">Full access including staff management</div>
                        </div>
                    </label>

                </div>
            </div>

            <!-- Password section -->
            <div class="border-t border-slate-100 pt-5 mb-5">
                <h3 class="text-sm font-semibold text-slate-600 mb-3 flex items-center gap-2">
                    <i class="bi bi-lock-fill text-slate-400"></i> Password
                    <?php if ($isEdit): ?>
                    <span class="text-xs text-slate-400 font-normal">(leave blank to keep current)</span>
                    <?php endif; ?>
                </h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                            New Password <?= !$isEdit ? '<span class="text-red-400">*</span>' : '' ?>
                        </label>
                        <div class="relative">
                            <input type="password" name="password" id="pw1"
                                   class="w-full pl-4 pr-10 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                          focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent focus:bg-white transition"
                                   placeholder="Min. 6 characters" autocomplete="new-password"
                                   <?= !$isEdit ? 'required' : '' ?>>
                            <button type="button" onclick="pmToggle('pw1','eye1')"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition">
                                <i id="eye1" class="bi bi-eye text-sm"></i>
                            </button>
                        </div>
                        <div class="mt-2 h-1.5 bg-slate-100 rounded-full overflow-hidden">
                            <div id="strengthBar" class="h-full rounded-full transition-all duration-300 w-0"></div>
                        </div>
                        <p id="strengthLabel" class="text-xs text-slate-400 mt-1 min-h-[1em]"></p>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Confirm Password</label>
                        <div class="relative">
                            <input type="password" name="password2" id="pw2"
                                   class="w-full pl-4 pr-10 py-3 border border-slate-200 rounded-xl text-sm bg-slate-50
                                          focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent focus:bg-white transition"
                                   placeholder="Repeat password" autocomplete="new-password">
                            <button type="button" onclick="pmToggle('pw2','eye2')"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 transition">
                                <i id="eye2" class="bi bi-eye text-sm"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex flex-col sm:flex-row gap-3 pt-1">
                <button type="submit"
                        class="flex items-center justify-center gap-2 bg-indigo-600 hover:bg-indigo-700 active:scale-95
                               text-white font-bold px-8 py-3 rounded-xl transition-all shadow-sm hover:shadow-md text-sm">
                    <i class="bi bi-check-circle-fill"></i> <?= $isEdit ? 'Save Changes' : 'Create Account' ?>
                </button>
                <a href="<?= BASE_URL ?>/admin/users.php"
                   class="flex items-center justify-center gap-2 px-6 py-3 rounded-xl text-sm font-semibold
                          text-slate-600 bg-slate-100 hover:bg-slate-200 transition-colors">
                    <i class="bi bi-x-circle"></i> Cancel
                </a>
            </div>

        </form>
    </div>
</div>
</div>

<script>
function pmToggle(inputId, iconId) {
    const inp  = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    inp.type   = inp.type === 'password' ? 'text' : 'password';
    icon.className = inp.type === 'password' ? 'bi bi-eye text-sm' : 'bi bi-eye-slash text-sm';
}
document.getElementById('pw1').addEventListener('input', function () {
    const val = this.value;
    const bar = document.getElementById('strengthBar');
    const lbl = document.getElementById('strengthLabel');
    if (!val) { bar.style.width = '0'; lbl.textContent = ''; return; }
    let score = 0;
    if (val.length >= 6)              score++;
    if (val.length >= 10)             score++;
    if (/[A-Z]/.test(val))            score++;
    if (/[0-9]/.test(val))            score++;
    if (/[^A-Za-z0-9]/.test(val))    score++;
    const levels = [
        { w: '20%',  cls: 'bg-red-400',    text: 'Weak',        tc: 'text-red-500' },
        { w: '40%',  cls: 'bg-orange-400', text: 'Fair',        tc: 'text-orange-500' },
        { w: '60%',  cls: 'bg-yellow-400', text: 'Good',        tc: 'text-yellow-600' },
        { w: '80%',  cls: 'bg-teal-400',   text: 'Strong',      tc: 'text-teal-600' },
        { w: '100%', cls: 'bg-green-500',  text: 'Very strong', tc: 'text-green-600' },
    ];
    const l = levels[Math.min(score, 4)];
    bar.style.width = l.w;
    bar.className   = 'h-full rounded-full transition-all duration-300 ' + l.cls;
    lbl.textContent = l.text;
    lbl.className   = 'text-xs mt-1 min-h-[1em] ' + l.tc;
});
</script>"""

if OLD in c:
    c = c.replace(OLD, NEW, 1)
    with open('/var/www/paperlessmd/admin/manage_user.php', 'w') as f:
        f.write(c)
    print("OK: manage_user.php updated")
else:
    print("MISS: target block not found")
    # show first 200 chars after the nav tag to debug
    idx = c.find('<nav class="flex items-center gap-2 text-sm text-slate-400 mb-6">')
    print(repr(c[idx:idx+200]))
