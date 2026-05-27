#!/usr/bin/env python3
with open('/var/www/paperlessmd/dashboard.php', 'r') as f:
    c = f.read()

# 1. Change section gate from isAdmin() to isAdmin() || isMa()
old1 = "    <!-- ── Pinned Admin Notes ────────────────────────────────────────── -->\n    <?php if (isAdmin()): ?>"
new1 = "    <!-- ── Pinned Admin Notes ────────────────────────────────────────── -->\n    <?php if (isAdmin() || isMa()): ?>"
c = c.replace(old1, new1, 1)

# 2. Hide the + compose button for non-admins
old2 = """            <button onclick="document.getElementById('noteCompose').classList.toggle('hidden')"
                    class="w-6 h-6 rounded-lg bg-slate-100 hover:bg-slate-200 grid place-items-center transition-colors">
                <i class="bi bi-plus-lg text-slate-600 text-xs"></i>
            </button>"""
new2 = """            <?php if (isAdmin()): ?>
            <button onclick="document.getElementById('noteCompose').classList.toggle('hidden')"
                    class="w-6 h-6 rounded-lg bg-slate-100 hover:bg-slate-200 grid place-items-center transition-colors">
                <i class="bi bi-plus-lg text-slate-600 text-xs"></i>
            </button>
            <?php endif; ?>"""
c = c.replace(old2, new2, 1)

# 3. Hide the Compose textarea block for non-admins
old3 = "        <!-- Compose -->\n        <div id=\"noteCompose\""
new3 = "        <!-- Compose -->\n        <?php if (isAdmin()): ?>\n        <div id=\"noteCompose\""
c = c.replace(old3, new3, 1)

# Find end of compose div and close the isAdmin block after it
old4 = "                </div>\n            </div>\n        </div>\n\n        <!-- Notes list -->"
new4 = "                </div>\n            </div>\n        </div>\n        <?php endif; // isAdmin compose ?>\n\n        <!-- Notes list -->"
c = c.replace(old4, new4, 1)

# 4. Hide delete button for non-admins
old5 = """                <button onclick="deleteAdminNote(<?= (int)$note['id'] ?>, this)"
                        class="opacity-0 group-hover:opacity-100 shrink-0 text-slate-300 hover:text-red-500 transition-all mt-0.5">
                    <i class="bi bi-x-lg text-[10px]"></i>
                </button>"""
new5 = """                <?php if (isAdmin()): ?>
                <button onclick="deleteAdminNote(<?= (int)$note['id'] ?>, this)"
                        class="opacity-0 group-hover:opacity-100 shrink-0 text-slate-300 hover:text-red-500 transition-all mt-0.5">
                    <i class="bi bi-x-lg text-[10px]"></i>
                </button>
                <?php endif; ?>"""
c = c.replace(old5, new5, 1)

# 5. Fix the closing endif comment
old6 = "    <?php endif; // isAdmin admin notes ?>"
new6 = "    <?php endif; // isAdmin || isMa admin notes ?>"
c = c.replace(old6, new6, 1)

with open('/var/www/paperlessmd/dashboard.php', 'w') as f:
    f.write(c)

# Verify
checks = [
    "isAdmin() || isMa()",
    "<?php if (isAdmin()): ?>\n        <div id=\"noteCompose\"",
    "<?php if (isAdmin()): ?>\n                <button onclick=\"deleteAdminNote",
]
for chk in checks:
    print("OK" if chk in c else "MISSING", repr(chk[:60]))
