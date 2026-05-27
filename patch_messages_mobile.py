path = '/var/www/paperlessmd/messages.php'
with open(path, 'r') as f:
    src = f.read()

# ── 1. CSS: fix mobile height ─────────────────────────────────────────────
old_css = """/* App Layout */
#msgWrap { height: calc(100vh - 110px); }
@media (max-width: 767px) { #msgWrap { height: calc(100vh - 60px); } }"""

new_css = """/* App Layout */
#msgWrap { height: calc(100vh - 110px); }
@media (max-width: 767px) {
    #msgWrap { height: calc(100svh - 130px); border-radius: 0; margin: 0 -1rem; }
}"""

assert old_css in src, 'CSS block not found'
src = src.replace(old_css, new_css, 1)
print('✓ CSS updated')

# ── 2. Left panel: add id ─────────────────────────────────────────────────
old_lpanel = '    <!-- LEFT PANEL -->\n    <div class="w-80 flex-shrink-0 border-r border-slate-200 bg-slate-50 flex flex-col">'
new_lpanel = '    <!-- LEFT PANEL -->\n    <div id="chatListPanel" class="w-full md:w-80 flex-shrink-0 border-r border-slate-200 bg-slate-50 flex flex-col">'

assert old_lpanel in src, 'Left panel div not found'
src = src.replace(old_lpanel, new_lpanel, 1)
print('✓ Left panel id added')

# ── 3. Right panel: add id + hidden on mobile by default ─────────────────
old_rpanel = '    <!-- RIGHT PANEL -->\n    <div class="flex-1 flex flex-col bg-[#f8fafc] relative">'
new_rpanel = '    <!-- RIGHT PANEL -->\n    <div id="chatRightPanel" class="hidden md:flex flex-1 flex-col bg-[#f8fafc] relative">'

assert old_rpanel in src, 'Right panel div not found'
src = src.replace(old_rpanel, new_rpanel, 1)
print('✓ Right panel id + hidden added')

# ── 4. Full chat header block (includes closing divs) ─────────────────────
old_full_hdr = '''            <div class="px-6 py-4 bg-white border-b border-slate-200 shadow-sm shrink-0 flex items-center justify-between z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center text-slate-500 font-bold" id="chatHeaderInitials"></div>
                    <div>
                        <h3 class="font-bold text-slate-800 leading-tight" id="chatHeaderName">Name</h3>
                        <p class="text-xs text-slate-500" id="chatHeaderRole">Role</p>
                    </div>
                </div>
            </div>'''

new_full_hdr = '''            <div class="px-4 py-3.5 bg-white border-b border-slate-200 shadow-sm shrink-0 flex items-center gap-3 z-10">
                <button id="backBtn" class="md:hidden -ml-1 p-2 text-slate-500 hover:text-blue-600 rounded-xl transition-colors shrink-0">
                    <i class="bi bi-arrow-left-short text-2xl leading-none"></i>
                </button>
                <div class="w-9 h-9 bg-slate-100 rounded-full flex items-center justify-center text-slate-500 font-bold shrink-0" id="chatHeaderInitials"></div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-slate-800 leading-tight truncate" id="chatHeaderName">Name</h3>
                    <p class="text-xs text-slate-500" id="chatHeaderRole">Role</p>
                </div>
            </div>'''

assert old_full_hdr in src, 'Full chat header not found'
src = src.replace(old_full_hdr, new_full_hdr, 1)
print('✓ Chat header + back button replaced')

# ── 5. openChat(): show/hide panels on mobile ─────────────────────────────
old_open = '''        chatEmpty.classList.add('hidden');
        chatActive.classList.remove('hidden');
        chatActive.classList.add('flex');'''

new_open = '''        chatEmpty.classList.add('hidden');
        chatActive.classList.remove('hidden');
        chatActive.classList.add('flex');

        // Mobile: hide list panel, show right panel full-width
        if (window.innerWidth < 768) {
            document.getElementById('chatListPanel').classList.add('hidden');
            const rp = document.getElementById('chatRightPanel');
            rp.classList.remove('hidden');
            rp.classList.add('flex', 'w-full');
        }'''

assert old_open in src, 'openChat show logic not found'
src = src.replace(old_open, new_open, 1)
print('✓ openChat panel switch added')

# ── 6. After element declarations: add back btn + resize listener ─────────
old_elements = '''    const composeForm = document.getElementById('composeForm');
    const composeBody = document.getElementById('composeBody');
    const composeFiles = document.getElementById('composeFiles');
    const filePreviews = document.getElementById('filePreviews');
    const sendBtn = document.getElementById('sendBtn');'''

new_elements = '''    const composeForm = document.getElementById('composeForm');
    const composeBody = document.getElementById('composeBody');
    const composeFiles = document.getElementById('composeFiles');
    const filePreviews = document.getElementById('filePreviews');
    const sendBtn = document.getElementById('sendBtn');

    // Mobile: back button returns to chat list
    document.getElementById('backBtn').addEventListener('click', () => {
        document.getElementById('chatListPanel').classList.remove('hidden');
        const rp = document.getElementById('chatRightPanel');
        rp.classList.add('hidden');
        rp.classList.remove('flex', 'w-full');
        activeChatId = '';
        lastMsgId = 0;
        chatActive.classList.add('hidden');
        chatActive.classList.remove('flex');
        chatEmpty.classList.remove('hidden');
    });

    // Resize: restore both panels on desktop
    window.addEventListener('resize', () => {
        if (window.innerWidth >= 768) {
            document.getElementById('chatListPanel').classList.remove('hidden');
            const rp = document.getElementById('chatRightPanel');
            rp.classList.remove('hidden', 'w-full');
            if (!rp.classList.contains('flex')) rp.classList.add('flex');
        }
    });'''

assert old_elements in src, 'Element declarations not found'
src = src.replace(old_elements, new_elements, 1)
print('✓ Back button JS + resize listener added')

with open(path, 'w') as f:
    f.write(src)

print('\n✅ messages.php patched successfully')
