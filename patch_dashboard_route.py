path = '/var/www/paperlessmd/includes/header.php'
with open(path, 'r') as f:
    src = f.read()

old = """<!-- Today's Schedule Widget -->
<?php if (canAccessClinical()): ?>
<div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden mb-7">
    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
        <h3 class="font-bold text-slate-700 flex items-center gap-2">
            <i class="bi bi-calendar3 text-indigo-500"></i> Today's Route"""

new = """<!-- Today's Schedule Widget -->
<?php if (canAccessClinical()): ?>
<div class="rounded-2xl shadow-sm overflow-hidden mb-7 <?= $scheduleTotalToday ? 'bg-indigo-50 border-2 border-indigo-400 ring-2 ring-indigo-200' : 'bg-white border border-slate-100' ?>">
    <div class="px-6 py-4 border-b <?= $scheduleTotalToday ? 'border-indigo-200 bg-indigo-600' : 'border-slate-100' ?> flex items-center justify-between">
        <h3 class="font-bold flex items-center gap-2 <?= $scheduleTotalToday ? 'text-white' : 'text-slate-700' ?>">
            <i class="bi bi-calendar3 <?= $scheduleTotalToday ? 'text-indigo-200' : 'text-indigo-500' ?>"></i> Today's Route"""

assert old in src, 'Pattern 1 not found'
src = src.replace(old, new, 1)
print('✓ Card wrapper + header patched')

old2 = """            <?php if ($scheduleTotalToday): ?>
            <span class="ml-1 px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs font-bold rounded-full"><?= $scheduleTotalToday ?></span>
            <?php endif; ?>
        </h3>
        <a href="<?= BASE_URL ?>/schedule.php" class="text-xs text-indigo-600 hover:text-indigo-700 font-semibold">Full schedule →</a>"""

new2 = """            <?php if ($scheduleTotalToday): ?>
            <span class="ml-1 px-2 py-0.5 <?= $scheduleTotalToday ? 'bg-white/25 text-white' : 'bg-indigo-100 text-indigo-700' ?> text-xs font-bold rounded-full"><?= $scheduleTotalToday ?></span>
            <?php endif; ?>
        </h3>
        <a href="<?= BASE_URL ?>/schedule.php" class="text-xs font-semibold <?= $scheduleTotalToday ? 'text-indigo-100 hover:text-white' : 'text-indigo-600 hover:text-indigo-700' ?>">Full schedule →</a>"""

assert old2 in src, 'Pattern 2 not found'
src = src.replace(old2, new2, 1)
print('✓ Badge + link colors patched')

with open(path, 'w') as f:
    f.write(src)
print('\n✅ Done')
