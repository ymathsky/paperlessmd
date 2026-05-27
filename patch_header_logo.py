import subprocess, sys

path = '/var/www/paperlessmd/includes/header.php'
r = subprocess.run(['cat', path], capture_output=True, text=True)
content = r.stdout

# Replace sidebar brand icon (desktop)
old1 = '        <div class="w-9 h-9 bg-white/20 group-hover:bg-white/30 rounded-xl grid place-items-center transition-colors shrink-0">\n            <i class="bi bi-clipboard2-heart-fill text-white text-base leading-none"></i>\n        </div>'
new1 = '        <div class="w-9 h-9 rounded-xl overflow-hidden shrink-0">\n            <img src="<?= BASE_URL ?>/assets/img/pwa-icon-192.png" alt="PaperlessMD" class="w-full h-full object-cover">\n        </div>'

# Replace mobile top bar icon
old2 = '        <div class="w-8 h-8 bg-white/20 rounded-xl grid place-items-center">\n            <i class="bi bi-clipboard2-heart-fill text-white text-sm leading-none"></i>\n        </div>'
new2 = '        <div class="w-8 h-8 rounded-xl overflow-hidden">\n            <img src="<?= BASE_URL ?>/assets/img/pwa-icon-192.png" alt="PaperlessMD" class="w-full h-full object-cover">\n        </div>'

if old1 not in content:
    print('ERR: sidebar icon pattern not found'); sys.exit(1)
if old2 not in content:
    print('ERR: mobile icon pattern not found'); sys.exit(1)

content = content.replace(old1, new1, 1)
content = content.replace(old2, new2, 1)

r2 = subprocess.run(['tee', path], input=content, capture_output=True, text=True)
if r2.returncode != 0:
    print('ERR:', r2.stderr); sys.exit(1)

print('OK: sidebar and mobile header icons updated to use PaperlessMD logo')
