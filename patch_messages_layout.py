path = '/var/www/paperlessmd/messages.php'
with open(path, 'r') as f:
    src = f.read()

# 1. Left panel: remove w-full (was overriding md:w-80 on desktop)
old_lp = 'id="chatListPanel" class="w-full md:w-80 flex-shrink-0 border-r border-slate-200 bg-slate-50 flex flex-col"'
new_lp = 'id="chatListPanel" class="w-80 flex-shrink-0 border-r border-slate-200 bg-slate-50 flex flex-col"'
assert old_lp in src, 'Left panel class not found'
src = src.replace(old_lp, new_lp, 1)
print('✓ Left panel: removed w-full')

# 2. Mobile CSS: make left panel full-width on mobile via CSS (no Tailwind responsive needed)
old_css = '@media (max-width: 767px) {\n    #msgWrap { height: calc(100svh - 130px); border-radius: 0; margin: 0 -1rem; }\n}'
new_css = '@media (max-width: 767px) {\n    #msgWrap { height: calc(100svh - 130px); border-radius: 0; margin: 0 -1rem; }\n    #chatListPanel { width: 100%; min-width: 100%; }\n}'
assert old_css in src, 'Mobile CSS block not found'
src = src.replace(old_css, new_css, 1)
print('✓ Mobile CSS: chatListPanel width: 100% added')

with open(path, 'w') as f:
    f.write(src)
print('\n✅ Patched successfully')
