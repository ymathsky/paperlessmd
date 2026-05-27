path = '/var/www/paperlessmd/messages.php'
with open(path, 'r') as f:
    src = f.read()

# 1. Remove 'hidden' from right panel HTML — let it default visible (flex on desktop)
old_rp = 'id="chatRightPanel" class="hidden md:flex flex-1 flex-col bg-[#f8fafc] relative"'
new_rp = 'id="chatRightPanel" class="flex-1 flex flex-col bg-[#f8fafc] relative"'
assert old_rp in src, 'Right panel class not found'
src = src.replace(old_rp, new_rp, 1)
print('✓ Right panel: removed hidden from HTML')

# 2. After DOMContentLoaded, add mobile init to hide right panel on mobile on page load
old_init = "document.addEventListener('DOMContentLoaded', () => {\n    let activeChatId = '';"
new_init = """document.addEventListener('DOMContentLoaded', () => {
    // On mobile: right panel starts hidden, left panel (list) is shown
    if (window.innerWidth < 768) {
        document.getElementById('chatRightPanel').classList.add('hidden');
        document.getElementById('chatRightPanel').classList.remove('flex');
    }

    let activeChatId = '';"""
assert old_init in src, 'DOMContentLoaded init not found'
src = src.replace(old_init, new_init, 1)
print('✓ Mobile init added on DOMContentLoaded')

with open(path, 'w') as f:
    f.write(src)
print('\n✅ Patched successfully')
