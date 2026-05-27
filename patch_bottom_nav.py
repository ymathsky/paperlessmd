import re

# ── 1. footer.php — add bottom nav before </body> ──────────────────────
footer_path = '/var/www/paperlessmd/includes/footer.php'
with open(footer_path, 'r') as f:
    footer = f.read()

bottom_nav = r"""
<!-- ■ Mobile Bottom Navigation Bar ■ -->
<nav class="md:hidden no-print fixed bottom-0 inset-x-0 z-50
            bg-gradient-to-r from-blue-950 to-blue-900
            border-t border-white/10 shadow-[0_-4px_20px_rgba(0,0,0,.35)]
            flex items-stretch"
     style="padding-bottom:env(safe-area-inset-bottom)">
    <?php
    $_bnItems = [
        ['href'=>'/dashboard.php',  'key'=>'dashboard', 'icon'=>'bi-speedometer2',    'label'=>'Home'],
        ['href'=>'/patients.php',   'key'=>'patients',  'icon'=>'bi-people-fill',     'label'=>'Patients'],
        ['href'=>'/schedule.php',   'key'=>'schedule',  'icon'=>'bi-calendar3',       'label'=>'Schedule', 'billingHide'=>true],
        ['href'=>'/esign_queue.php','key'=>'esign',     'icon'=>'bi-pen-fill',        'label'=>'Sign',     'billingHide'=>true,
         'badge'=>($_esignCount??0), 'badgeCls'=>'bg-violet-500'],
        ['href'=>'/messages.php',   'key'=>'messages',  'icon'=>'bi-chat-dots-fill',  'label'=>'Messages',
         'badge'=>($_unreadMessages??0), 'badgeCls'=>'bg-emerald-500'],
    ];
    foreach ($_bnItems as $_bn):
        if (!empty($_bn['billingHide']) && isBilling()) continue;
        $_bnActive = ($activeNav ?? '') === $_bn['key'];
    ?>
    <a href="<?= BASE_URL . $_bn['href'] ?>"
       class="flex-1 flex flex-col items-center justify-center gap-0.5 py-2.5 relative
              transition-colors <?= $_bnActive ? 'text-white' : 'text-blue-300/70 active:text-white' ?>">
        <?php if ($_bnActive): ?>
        <span class="absolute top-0 left-2 right-2 h-0.5 bg-indigo-400 rounded-b-full"></span>
        <?php endif; ?>
        <div class="relative">
            <i class="bi <?= $_bn['icon'] ?> text-[22px] leading-none"></i>
            <?php if (!empty($_bn['badge']) && $_bn['badge'] > 0): ?>
            <span class="absolute -top-1.5 -right-2.5 <?= $_bn['badgeCls'] ?> text-white
                         text-[9px] font-bold px-1 py-px rounded-full leading-none min-w-[16px] text-center">
                <?= min((int)$_bn['badge'], 99) ?>
            </span>
            <?php endif; ?>
        </div>
        <span class="text-[10px] font-semibold leading-none"><?= $_bn['label'] ?></span>
    </a>
    <?php endforeach; ?>
</nav>
"""

if '</body>' in footer and '<!-- ■ Mobile Bottom Navigation Bar ■ -->' not in footer:
    footer = footer.replace('</body>', bottom_nav + '\n</body>', 1)
    with open(footer_path, 'w') as f:
        f.write(footer)
    print('✓ Bottom nav added to footer.php')
elif '<!-- ■ Mobile Bottom Navigation Bar ■ -->' in footer:
    print('⚠ Bottom nav already present in footer.php')
else:
    print('✗ </body> not found in footer.php')

# ── 2. header.php — fix content padding so nothing hides behind bottom nav ──
header_path = '/var/www/paperlessmd/includes/header.php'
with open(header_path, 'r') as f:
    header = f.read()

old_pad = 'pt-14 pb-12 min-h-screen'
new_pad = 'pt-14 md:pb-0 pb-16 min-h-screen'

if old_pad in header:
    header = header.replace(old_pad, new_pad, 1)
    with open(header_path, 'w') as f:
        f.write(header)
    print('✓ Content padding updated in header.php')
elif new_pad in header:
    print('⚠ Content padding already updated in header.php')
else:
    print('✗ Padding pattern not found in header.php')
