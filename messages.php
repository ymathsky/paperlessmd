<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$pageTitle = 'Messages';
$activeNav = 'messages';
$myId      = (int)$_SESSION['user_id'];

$_msgReady = false;
try {
    $pdo->query("SELECT 1 FROM messages LIMIT 1");
    $_msgReady = true;
} catch (PDOException $e) { }

include __DIR__ . '/includes/header.php';
?>

<?php if (!$_msgReady): ?>
<div class="bg-amber-50 border border-amber-200 rounded-2xl p-6 max-w-lg mx-auto mt-10 text-center">
    <div class="w-12 h-12 bg-amber-100 rounded-xl grid place-items-center mx-auto mb-4">
        <i class="bi bi-exclamation-triangle-fill text-amber-500 text-xl"></i>
    </div>
    <h2 class="font-bold text-slate-800 mb-1">Messaging not set up yet</h2>
    <p class="text-sm text-slate-600 mb-4">Please run migrate_messages.php</p>
</div>
<?php else: ?>

<style>
/* App Layout */
#msgWrap { height: calc(100vh - 110px); }
@media (max-width: 767px) {
    #msgWrap { height: calc(100vh - 60px); position: relative; }
    #leftPanel { width: 100%; flex-shrink: 0; }
    #rightPanel { position: absolute; inset: 0; transform: translateX(100%); transition: transform 0.25s ease; z-index: 10; background: #f8fafc; }
    #msgWrap.panel-right #leftPanel { display: none; }
    #msgWrap.panel-right #rightPanel { transform: translateX(0); }
    #mobileBackBtn { display: flex !important; }
}
.chat-list-item { transition: all 0.15s; }
.chat-list-item:hover { background: #f8fafc; }
.chat-list-item.active { background: #eff6ff; border-left-color: #3b82f6; }
/* Bubbles */
.bubble { max-width: 75%; padding: 10px 14px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); word-wrap: break-word; }
.bubble-me { background: #2563eb; color: #fff; border-radius: 18px 18px 4px 18px; margin-left: auto; }
.bubble-them { background: #fff; color: #1e293b; border-radius: 18px 18px 18px 4px; border: 1px solid #e2e8f0; }
.time-label { font-size: 11px; color: #94a3b8; margin-top: 4px; display: block; }
.bubble-me .time-label { color: #adc9fe; text-align: right; }
/* Attachments */
.att-chip { display: flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 8px; border: 1px solid rgba(0,0,0,0.1); font-size: 12px; background: rgba(255,255,255,0.1); text-decoration: none; color: inherit; transition: background 0.1s; margin-top: 5px; }
.att-chip:hover { background: rgba(255,255,255,0.2); }
#msgHistory { scroll-behavior: auto; overflow-y: auto; }
.unread-badge { min-width: 20px; height: 20px; padding: 0 6px; border-radius: 10px; font-size: 11px; display: inline-flex; align-items: center; justify-content: center; }
/* Reply quotes */
.reply-quote { font-size: 11px; padding: 5px 8px; border-radius: 6px; margin-bottom: 6px; line-height: 1.4; max-width: 100%; overflow: hidden; }
.reply-quote-me { background: rgba(255,255,255,0.2); border-left: 3px solid rgba(255,255,255,0.7); }
.reply-quote-them { background: #f1f5f9; border-left: 3px solid #94a3b8; color: #475569; }
/* Message action buttons */
.msg-actions { opacity: 0; transition: opacity 0.15s; display: flex; gap: 3px; align-items: flex-end; padding-bottom: 4px; }
.msg-row:hover .msg-actions { opacity: 1; }
.msg-action-btn { width: 28px; height: 28px; background: white; border: 1px solid #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; color: #64748b; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.08); transition: all 0.1s; }
.msg-action-btn:hover { background: #f1f5f9; color: #334155; }
.msg-action-delete:hover { background: #fee2e2 !important; color: #dc2626 !important; border-color: #fca5a5 !important; }
/* Read receipts */
.msg-receipt { font-size: 10px; margin-top: 2px; display: block; }
.msg-receipt.seen { color: #3b82f6; text-align: right; }
.msg-receipt.sent { color: #94a3b8; text-align: right; }
</style>

<div id="msgWrap" class="flex bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mx-auto max-w-7xl">
    
    <!-- LEFT PANEL -->
    <div id="leftPanel" class="w-80 flex-shrink-0 border-r border-slate-200 bg-slate-50 flex flex-col">
        <div class="px-4 py-3 border-b border-slate-200 bg-white">
            <div class="flex items-center justify-between mb-2">
                <h2 class="font-bold text-lg text-slate-800 flex items-center gap-2">
                    <i class="bi bi-chat-dots-fill text-blue-600"></i> Chats
                </h2>
                <button onclick="openNewChatModal()" title="New Conversation"
                        class="w-8 h-8 bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center justify-center transition">
                    <i class="bi bi-pencil-square text-sm"></i>
                </button>
                <button id="chimeMuteBtn" onclick="toggleChimeMute()" title="Mute notifications"
                        class="w-8 h-8 text-slate-400 hover:text-slate-600 rounded-lg flex items-center justify-center transition hover:bg-slate-100">
                    <i class="bi bi-bell-fill text-sm"></i>
                </button>
            </div>
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                <input id="chatSearchInput" type="text" placeholder="Search conversations..."
                       class="w-full pl-8 pr-3 py-2 text-xs bg-slate-100 border-0 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>
        </div>
        <div class="flex-1 overflow-y-auto p-2" id="chatList">
            <!-- Rendered by JS -->
            <div class="text-center p-4 text-slate-400 text-sm"><i class="bi bi-arrow-repeat animate-spin inline-block"></i> Loading...</div>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div id="rightPanel" class="flex-1 flex flex-col bg-[#f8fafc] relative">
        <div id="chatEmpty" class="absolute inset-0 flex flex-col items-center justify-center text-center px-6">
            <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mb-4">
                <i class="bi bi-chat-text-fill text-blue-500 text-3xl"></i>
            </div>
            <h3 class="text-xl font-bold text-slate-700">Select a Chat</h3>
            <p class="text-slate-500 mt-2 max-w-sm">Choose a conversation from the left menu to view messages or start a new conversation.</p>
        </div>

        <div id="chatActive" class="hidden flex-col h-full">
            <div class="px-4 py-3 bg-white border-b border-slate-200 shadow-sm shrink-0 flex items-center justify-between z-10">
                <div class="flex items-center gap-2">
                    <button id="mobileBackBtn" class="hidden items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-slate-600 font-semibold hover:text-blue-600 hover:bg-blue-50 transition shrink-0 text-sm" onclick="backToList()" aria-label="Back">
                        <i class="bi bi-arrow-left text-base leading-none"></i><span>Back</span>
                    </button>
                    <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center text-slate-500 font-bold" id="chatHeaderInitials"></div>
                    <div>
                        <h3 class="font-bold text-slate-800 leading-tight" id="chatHeaderName">Name</h3>
                        <p class="text-xs text-slate-500" id="chatHeaderRole">Role</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button id="videoCallBtn" onclick="startVideoCall()" class="hidden w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 hover:bg-emerald-100 flex items-center justify-center transition" title="Start video call">
                            <i class="bi bi-camera-video-fill"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex-1 p-4 relative" id="msgHistory">
                <!-- Messages -->
            </div>

            <div class="px-4 py-3 bg-white border-t border-slate-200 shrink-0">
                <div id="replyBar" class="hidden items-center gap-2 px-3 py-2 bg-blue-50 border border-blue-100 rounded-xl text-sm text-slate-700 mb-2">
                    <i class="bi bi-reply-fill text-blue-500 shrink-0"></i>
                    <span id="replyBarText" class="flex-1 text-xs truncate"></span>
                    <button type="button" onclick="clearReply()" class="text-slate-400 hover:text-red-500 shrink-0 transition">
                        <i class="bi bi-x-lg text-sm"></i>
                    </button>
                </div>
                <div id="filePreviews" class="flex flex-wrap gap-2 mb-2 empty:hidden"></div>
                <form id="composeForm" class="flex items-end gap-2 bg-slate-100 rounded-xl p-2">
                    <label class="p-2 text-slate-400 hover:text-blue-600 cursor-pointer transition rounded-lg hover:bg-slate-200 shrink-0" title="Attach file">
                        <i class="bi bi-paperclip text-xl"></i>
                        <input type="file" id="composeFiles" class="hidden" multiple accept="image/*,.pdf,.doc,.docx,.xls,.xlsx">
                    </label>
                    <textarea id="composeBody" rows="1" placeholder="Type a message..." class="flex-1 bg-transparent border-0 focus:ring-0 resize-none py-2 px-1 text-sm max-h-32 text-slate-800" style="min-height: 40px; outline: none;"></textarea>
                    <button type="submit" id="sendBtn" class="w-10 h-10 bg-blue-600 hover:bg-blue-700 text-white rounded-lg flex items-center justify-center shrink-0 transition">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- New Chat Modal -->
<div id="newChatModal" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" onclick="if(event.target===this)closeNewChatModal()">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200">
            <h3 class="font-bold text-slate-800">New Conversation</h3>
            <button onclick="closeNewChatModal()" class="text-slate-400 hover:text-slate-600"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="px-4 pt-3 pb-2">
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                <input id="newChatSearch" type="text" placeholder="Search staff..."
                       class="w-full pl-8 pr-3 py-2 text-sm bg-slate-100 border-0 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>
        </div>
        <div id="newChatList" class="overflow-y-auto max-h-72 p-2"></div>
    </div>
</div>

<!-- Incoming Call Notification (rendered globally by includes/videocall.php) -->


<!-- Active Video Call (rendered globally by includes/videocall.php) -->



<script>
document.addEventListener('DOMContentLoaded', () => {
    let activeChatId = '';
    let lastMsgId = 0;
    let replyToId = null;
    let cachedChats = [];

    const chatList        = document.getElementById('chatList');
    const msgHistory      = document.getElementById('msgHistory');
    const chatEmpty       = document.getElementById('chatEmpty');
    const chatActive      = document.getElementById('chatActive');
    const chatHeaderName  = document.getElementById('chatHeaderName');
    const chatHeaderRole  = document.getElementById('chatHeaderRole');
    const chatHeaderInitials = document.getElementById('chatHeaderInitials');
    const composeForm     = document.getElementById('composeForm');
    const composeBody     = document.getElementById('composeBody');
    const composeFiles    = document.getElementById('composeFiles');
    const filePreviews    = document.getElementById('filePreviews');
    const sendBtn         = document.getElementById('sendBtn');
    const replyBar        = document.getElementById('replyBar');
    const replyBarText    = document.getElementById('replyBarText');
    const searchInput     = document.getElementById('chatSearchInput');

    let currentUploads = [];
    let isFetching = false;
    let wasAtBottom = true;

    // â”€â”€ Notification chime â”€â”€
    let _audioCtx = null;
    let _chimeMuted = localStorage.getItem('msgChimeMuted') === '1';
    let _prevUnreadTotal = -1; // -1 = first load, don't chime

    // Sync mute button state on load
    (function() {
        const btn = document.getElementById('chimeMuteBtn');
        if (btn && _chimeMuted) btn.innerHTML = '<i class="bi bi-bell-slash-fill text-sm"></i>';
    })();

    function playChime() {
        if (_chimeMuted) return;
        try {
            if (!_audioCtx) _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            if (_audioCtx.state === 'suspended') _audioCtx.resume();
            [880, 1100].forEach((freq, i) => {
                const osc  = _audioCtx.createOscillator();
                const gain = _audioCtx.createGain();
                osc.connect(gain);
                gain.connect(_audioCtx.destination);
                osc.type = 'sine';
                osc.frequency.value = freq;
                const t = _audioCtx.currentTime + i * 0.11;
                gain.gain.setValueAtTime(0, t);
                gain.gain.linearRampToValueAtTime(0.22, t + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.001, t + 0.22);
                osc.start(t);
                osc.stop(t + 0.25);
            });
        } catch(e) {}
    }

    window.toggleChimeMute = function() {
        _chimeMuted = !_chimeMuted;
        localStorage.setItem('msgChimeMuted', _chimeMuted ? '1' : '0');
        const btn = document.getElementById('chimeMuteBtn');
        if (btn) {
            btn.innerHTML = _chimeMuted
                ? '<i class="bi bi-bell-slash-fill text-sm"></i>'
                : '<i class="bi bi-bell-fill text-sm"></i>';
            btn.title = _chimeMuted ? 'Notifications muted' : 'Mute notifications';
        }
        // Init AudioContext on first user interaction so it's ready
        if (!_chimeMuted && !_audioCtx) {
            try { _audioCtx = new (window.AudioContext || window.webkitAudioContext)(); } catch(e) {}
        }
    };

    function scrollToBottom() { msgHistory.scrollTop = msgHistory.scrollHeight; }

    msgHistory.addEventListener('scroll', () => {
        wasAtBottom = msgHistory.scrollHeight - msgHistory.scrollTop - msgHistory.clientHeight < 20;
    });

    // Auto-resize textarea
    composeBody.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = this.scrollHeight + 'px';
    });

    // Enter to send
    composeBody.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (composeBody.value.trim() !== '' || currentUploads.length > 0)
                composeForm.dispatchEvent(new Event('submit'));
        }
    });

    // Attachments â€” file picker with size guard
    composeFiles.addEventListener('change', () => {
        addFilesToUpload(Array.from(composeFiles.files));
        composeFiles.value = '';
    });

    // Paste image from clipboard
    composeBody.addEventListener('paste', e => {
        const items = (e.clipboardData || e.originalEvent?.clipboardData)?.items || [];
        for (const item of items) {
            if (item.kind === 'file' && item.type.startsWith('image/')) {
                const f = item.getAsFile();
                if (f) addFilesToUpload([f]);
            }
        }
    });

    function addFilesToUpload(files) {
        const MAX = 25 * 1024 * 1024;
        for (const f of files) {
            if (f.size > MAX) { pdToast(`"${f.name}" exceeds 25 MB and was not added.`, 'warning'); continue; }
            currentUploads.push(f);
        }
        renderPreviews();
    }

    function renderPreviews() {
        filePreviews.innerHTML = '';
        currentUploads.forEach((f, i) => {
            const el = document.createElement('div');
            el.className = 'relative group flex-shrink-0';
            if (f.type.startsWith('image/')) {
                const url = URL.createObjectURL(f);
                el.innerHTML = `<img src="${url}" class="w-16 h-16 object-cover rounded-xl border-2 border-blue-200" onload="URL.revokeObjectURL(this.src)">
                    <button class="remove-att absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white rounded-full text-[10px] flex items-center justify-center opacity-0 group-hover:opacity-100 transition" data-idx="${i}"><i class="bi bi-x"></i></button>`;
            } else {
                const isPdf = f.type.includes('pdf');
                const icon  = isPdf ? 'bi-file-earmark-pdf-fill text-red-500' : 'bi-file-earmark-fill text-blue-500';
                el.innerHTML = `<div class="bg-slate-50 border border-slate-200 rounded-xl p-2 flex items-center gap-2 max-w-[160px]">
                    <i class="bi ${icon} text-xl shrink-0"></i>
                    <div class="min-w-0"><div class="text-xs font-medium text-slate-700 truncate">${f.name}</div><div class="text-[10px] text-slate-400">${(f.size/1024).toFixed(0)} KB</div></div>
                    <button class="remove-att shrink-0 text-slate-300 hover:text-red-500 transition ml-1" data-idx="${i}"><i class="bi bi-x-lg text-xs"></i></button>
                </div>`;
            }
            filePreviews.appendChild(el);
        });
        filePreviews.querySelectorAll('.remove-att').forEach(btn => btn.addEventListener('click', e => {
            currentUploads.splice(parseInt(e.currentTarget.dataset.idx), 1);
            renderPreviews();
        }));
    }

    function formatTime(d) {
        const date = new Date(d), now = new Date();
        const diffDays = Math.floor((now - date) / 86400000);
        if (diffDays === 0 && now.getDate() === date.getDate())
            return date.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
        if (diffDays < 7)
            return date.toLocaleDateString([], {weekday:'short'}) + ' ' + date.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
        return date.toLocaleDateString([], {month:'short', day:'numeric'}) + ' ' + date.toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'});
    }

    // â”€â”€ Search filter â”€â”€
    searchInput && searchInput.addEventListener('input', () => renderChats(cachedChats));

    // â”€â”€ Render left panel â”€â”€
    function renderChats(chats) {
        cachedChats = chats;
        const q = (searchInput?.value || '').toLowerCase().trim();
        const list = q ? chats.filter(c => c.name.toLowerCase().includes(q) || (c.latest_body || '').toLowerCase().includes(q)) : chats;
        let html = '';
        list.forEach(c => {
            const isAct = c.id == activeChatId;
            const initials = c.name.substring(0,2).toUpperCase();
            const timeStr = c.latest_time ? formatTime(c.latest_time.replace(' ','T')) : '';
            const unreadLine = c.unreads > 0 ? `<div class="bg-blue-600 text-white unread-badge flex-shrink-0">${c.unreads}</div>` : '';
            let preview = c.latest_body || 'No messages yet';
            if (preview.length > 35) preview = preview.substring(0,35) + 'â€¦';
            const safeAvatar = c.avatar_url ? encodeURIComponent(c.avatar_url) : '';
            let avatarHtml = c.id === 'all'
                ? '<div class="w-12 h-12 bg-blue-50 rounded-full flex items-center justify-center shrink-0"><i class="bi bi-megaphone-fill text-blue-500"></i></div>'
                : c.avatar_url
                    ? `<img src="${c.avatar_url}" alt="${initials}" class="w-12 h-12 rounded-full object-cover shrink-0">`
                    : `<div class="w-12 h-12 bg-slate-200 rounded-full flex items-center justify-center text-slate-500 font-bold shrink-0">${initials}</div>`;
            html += `<div class="chat-list-item flex items-center gap-3 p-3 rounded-xl cursor-pointer border-l-4 ${isAct?'active':'border-transparent'}"
                onclick="openChat('${c.id}','${(c.name||'').replace(/'/g,"\\'")}','${(c.role||'').replace(/'/g,"\\'")}','${initials}','${safeAvatar}')">
                ${avatarHtml}
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-baseline mb-0.5">
                        <span class="font-bold text-sm text-slate-800 truncate">${c.name}</span>
                        <span class="text-[10px] text-slate-400 whitespace-nowrap ml-2">${timeStr}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs text-slate-500 gap-2">
                        <span class="truncate">${preview}</span>${unreadLine}
                    </div>
                </div></div>`;
        });
        chatList.innerHTML = html || '<div class="p-4 text-center text-slate-400 text-sm">No results.</div>';
        const total = chats.reduce((s,c) => s + c.unreads, 0);
        // Chime when a new unread message arrives in a background chat
        if (_prevUnreadTotal >= 0 && total > _prevUnreadTotal) playChime();
        _prevUnreadTotal = total;
        const badge = document.querySelector('.bg-red-500.rounded-full');
        if (badge) { badge.textContent = total; badge.classList.toggle('hidden', total === 0); }
    }

    // â”€â”€ Message action delegation (reply + delete) â”€â”€
    msgHistory.addEventListener('click', e => {
        const replyBtn = e.target.closest('.msg-reply-btn');
        if (replyBtn) {
            const id   = parseInt(replyBtn.dataset.msgId);
            const body = replyBtn.dataset.msgBody;
            const sender = replyBtn.dataset.msgSender;
            setReply(id, body, sender);
        }
        const deleteBtn = e.target.closest('.msg-delete-btn');
        if (deleteBtn) deleteMessage(parseInt(deleteBtn.dataset.msgId));
    });

    // â”€â”€ Reply â”€â”€
    window.setReply = function(id, body, senderName) {
        replyToId = id;
        replyBar.classList.remove('hidden');
        replyBar.classList.add('flex');
        const safeBody = String(body).replace(/</g,'&lt;').substring(0,80);
        replyBarText.innerHTML = `<strong class="text-blue-700">${senderName}:</strong> ${safeBody}${body.length > 80 ? 'â€¦' : ''}`;
        composeBody.focus();
    };
    window.clearReply = function() {
        replyToId = null;
        replyBar.classList.add('hidden');
        replyBar.classList.remove('flex');
    };

    // ── Delete message ──
    window.deleteMessage = async function(msgId) {
        if (!await pdConfirm({message: 'Delete this message for everyone?', confirmLabel: 'Delete', confirmIcon: 'bi bi-trash3', confirmStyle: 'background:#dc2626;'})) return;
        await fetch('<?= BASE_URL ?>/api/messages.php', {
            method: 'POST',
            body: new URLSearchParams({action:'delete', id:msgId})
        });
        lastMsgId = 0;
        await syncChat();
    };

    // â”€â”€ Render messages â”€â”€
    function renderMessages(messages, append = false) {
        if (!append) msgHistory.innerHTML = '';
        // Chime when new messages from others arrive in the active chat (append mode only)
        if (append && messages.some(m => m.from_user_id != <?= $myId ?> && !m.deleted_at)) {
            playChime();
        }
        messages.forEach(m => {
            // â”€â”€ System call log message â”€â”€
            if (m.subject === 'call_log') {
                const timeVal = formatTime(m.created_at.replace(' ','T'));
                const isVideo = m.body.includes('\uD83D\uDCF9') || m.body.toLowerCase().includes('video');
                const div = document.createElement('div');
                div.className = 'msg-row';
                div.dataset.msgId = m.id;
                div.innerHTML = `<div class="flex items-center justify-center gap-2 my-2">
                    <div style="height:1px;flex:1;background:rgba(148,163,184,.25)"></div>
                    <div style="display:flex;align-items:center;gap:6px;padding:4px 12px;border-radius:999px;background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.2);font-size:0.75rem;color:#6366f1;white-space:nowrap">
                        <i class="bi bi-camera-video-fill" style="font-size:.8rem"></i>
                        <span>${String(m.body).replace(/</g,'&lt;').replace(/>/g,'&gt;')}</span>
                        <span style="opacity:.5">&middot; ${timeVal}</span>
                    </div>
                    <div style="height:1px;flex:1;background:rgba(148,163,184,.25)"></div>
                </div>`;
                msgHistory.appendChild(div);
                lastMsgId = Math.max(lastMsgId, parseInt(m.id));
                return;
            }

            const isMe = m.from_user_id == <?= $myId ?>;
            const isDeleted = !!m.deleted_at;
            const bClass = isMe ? 'bubble-me' : 'bubble-them';

            // Reply quote
            let replyHtml = '';
            if (m.reply_to_id && m.reply_preview) {
                const rFrom = m.reply_from_name ? `<strong>${m.reply_from_name}:</strong> ` : '';
                const rText = String(m.reply_preview).replace(/</g,'&lt;').replace(/>/g,'&gt;');
                replyHtml = `<div class="reply-quote ${isMe?'reply-quote-me':'reply-quote-them'}">${rFrom}${rText}</div>`;
            }

            // Attachments
            let attHtml = '';
            if (m.attachments && m.attachments.length) {
                m.attachments.forEach(a => {
                    const isImg = a.mime_type && a.mime_type.startsWith('image/');
                    const isPdf = a.mime_type && a.mime_type.includes('pdf');
                    const url   = `<?= BASE_URL ?>/api/messages.php?action=download&file_id=${a.id}`;
                    const safeName = String(a.original_name).replace(/"/g,'&quot;').replace(/</g,'&lt;');
                    if (isImg) {
                        attHtml += `<a href="${url}" class="block mt-2 cursor-zoom-in" onclick="event.preventDefault();openMsgImage('${url}','${safeName}')">
                            <img src="${url}" alt="${safeName}" loading="lazy"
                                 class="rounded-xl max-w-[220px] max-h-[200px] object-cover border border-white/20 hover:opacity-90 transition"></a>`;
                    } else {
                        const icon = isPdf ? 'bi-file-earmark-pdf-fill' : 'bi-file-earmark-arrow-down';
                        attHtml += `<a href="${url}" target="_blank" class="att-chip">
                            <i class="bi ${icon}"></i>
                            <span class="truncate max-w-[140px]" title="${safeName}">${safeName}</span>
                            <i class="bi bi-download text-[11px] opacity-60 ml-auto shrink-0"></i></a>`;
                    }
                });
            }

            // Body
            let bodyFmt = isDeleted
                ? '<em class="text-sm opacity-60">Message deleted</em>'
                : (() => {
                    let b = String(m.body||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
                    b = b.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g,'<br>');
                    return b;
                })();

            const senderName = isMe ? '' : `<div class="text-xs text-slate-500 ml-1 mb-1 font-semibold">${m.from_name||''}</div>`;
            const timeVal = formatTime(m.created_at.replace(' ','T'));

            // Read receipt (only on my sent messages)
            let receiptHtml = '';
            if (isMe && !isDeleted) {
                receiptHtml = parseInt(m.seen_count) > 0
                    ? `<span class="msg-receipt seen"><i class="bi bi-check2-all"></i> Seen</span>`
                    : `<span class="msg-receipt sent"><i class="bi bi-check2"></i> Sent</span>`;
            }

            // Action buttons (reply + delete)
            let actionsHtml = '';
            if (!isDeleted) {
                const escapedBody = (m.body||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;');
                const escapedName = (m.from_name||'Me').replace(/&/g,'&amp;').replace(/"/g,'&quot;');
                actionsHtml = `<div class="msg-actions">
                    <button class="msg-action-btn msg-reply-btn" title="Reply"
                        data-msg-id="${m.id}" data-msg-body="${escapedBody}" data-msg-sender="${escapedName}">
                        <i class="bi bi-reply-fill"></i></button>
                    ${isMe ? `<button class="msg-action-btn msg-action-delete msg-delete-btn" title="Delete" data-msg-id="${m.id}"><i class="bi bi-trash3"></i></button>` : ''}
                </div>`;
            }

            const div = document.createElement('div');
            div.className = 'msg-row';
            div.dataset.msgId = m.id;
            div.innerHTML = `
                <div class="flex flex-col mb-2 ${isMe?'items-end':'items-start'}">
                    ${senderName}
                    <div class="flex items-end gap-1 ${isMe?'flex-row-reverse':''}">
                        ${actionsHtml}
                        <div class="bubble ${bClass} ${isDeleted?'opacity-60':''}">
                            ${replyHtml}
                            <div class="text-sm leading-relaxed">${bodyFmt}</div>
                            ${attHtml}
                            <span class="time-label">${timeVal}</span>
                        </div>
                    </div>
                    ${receiptHtml}
                </div>`;
            msgHistory.appendChild(div);
            lastMsgId = Math.max(lastMsgId, parseInt(m.id));
        });
        if (wasAtBottom || !append) scrollToBottom();
    }

    // â”€â”€ Open chat â”€â”€
    window.openChat = function(id, name, role, initials, avatarEncoded) {
        activeChatId = id;
        window.vcActiveChatId   = id;    // expose for global startVideoCall
        window.vcActivePeerName = name;
        lastMsgId = 0;
        wasAtBottom = true;
        clearReply();

        chatEmpty.classList.add('hidden');
        chatActive.classList.remove('hidden');
        chatActive.classList.add('flex');
        document.getElementById('msgWrap').classList.add('panel-right');

        chatHeaderName.textContent = name;
        chatHeaderRole.textContent = role;

        // Video call button: only visible for 1-on-1 chats
        const vcBtn = document.getElementById('videoCallBtn');
        if (vcBtn) vcBtn.classList.toggle('hidden', id === 'all');

        if (id === 'all') {
            chatHeaderInitials.className = 'w-10 h-10 bg-blue-50 rounded-full flex items-center justify-center text-slate-500 font-bold';
            chatHeaderInitials.innerHTML = '<i class="bi bi-megaphone-fill text-blue-500"></i>';
        } else if (avatarEncoded) {
            const src = decodeURIComponent(avatarEncoded);
            chatHeaderInitials.className = 'w-10 h-10 rounded-full overflow-hidden shrink-0';
            chatHeaderInitials.innerHTML = `<img src="${src}" alt="${initials}" class="w-full h-full object-cover" onerror="this.outerHTML='<div class=\\"w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center text-slate-500 font-bold\\">${initials}</div>'">`;
        } else {
            chatHeaderInitials.className = 'w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center text-slate-500 font-bold';
            chatHeaderInitials.innerHTML = initials;
        }

        msgHistory.innerHTML = '<div class="absolute inset-0 flex items-center justify-center text-slate-300"><i class="bi bi-arrow-repeat animate-spin text-2xl"></i></div>';
        syncChat();
        composeBody.focus();
    };

    // â”€â”€ WebRTC Video Call (engine lives in includes/videocall.php) â”€â”€
    // startVideoCall() is defined globally and reads window.vcActiveChatId / window.vcActivePeerName.

    // â”€â”€ New Chat modal â”€â”€
    window.openNewChatModal = function() {
        const modal = document.getElementById('newChatModal');
        const list  = document.getElementById('newChatList');
        const searchEl = document.getElementById('newChatSearch');
        modal.classList.remove('hidden');
        let html = '';
        cachedChats.forEach(c => {
            if (c.id === 'all') return;
            const initials = c.name.substring(0,2).toUpperCase();
            const safeAvatar = c.avatar_url ? encodeURIComponent(c.avatar_url) : '';
            const avatarHtml = c.avatar_url
                ? `<img src="${c.avatar_url}" class="w-10 h-10 rounded-full object-cover shrink-0">`
                : `<div class="w-10 h-10 bg-slate-200 rounded-full flex items-center justify-center text-slate-600 font-bold shrink-0">${initials}</div>`;
            html += `<button onclick="openChat('${c.id}','${(c.name||'').replace(/'/g,"\\'")}','${(c.role||'').replace(/'/g,"\\'")}','${initials}','${safeAvatar}');closeNewChatModal()"
                class="flex items-center gap-3 w-full p-3 rounded-xl hover:bg-slate-50 transition text-left">
                ${avatarHtml}
                <div><div class="font-semibold text-sm text-slate-800">${c.name}</div><div class="text-xs text-slate-500 capitalize">${c.role}</div></div>
            </button>`;
        });
        list.innerHTML = html;
        searchEl.value = '';
        searchEl.focus();
        searchEl.oninput = function() {
            const q = this.value.toLowerCase();
            list.querySelectorAll('button').forEach(btn => {
                btn.style.display = btn.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        };
    };
    window.closeNewChatModal = function() {
        document.getElementById('newChatModal').classList.add('hidden');
    };

    // â”€â”€ Sync â”€â”€
    async function syncChat() {
        if (isFetching) return;
        isFetching = true;
        const reqChatId = activeChatId;
        try {
            const url = `<?= BASE_URL ?>/api/messages.php?action=sync&active_chat=${encodeURIComponent(reqChatId)}&last_msg_id=${lastMsgId}`;
            const res = await fetch(url);
            const data = await res.json();
            if (reqChatId !== activeChatId) return;
            if (data.ok) {
                renderChats(data.chats);
                if (activeChatId && data.messages.length > 0) {
                    renderMessages(data.messages, lastMsgId > 0);
                } else if (activeChatId && lastMsgId === 0) {
                    msgHistory.innerHTML = '<div class="text-center p-6 text-slate-400 text-sm">No messages here yet. Say hi!</div>';
                }
            }
        } catch (e) { console.error('Sync error:', e); }
        finally { isFetching = false; }
    }

    // â”€â”€ Send â”€â”€
    composeForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = composeBody.value.trim();
        if (!text && currentUploads.length === 0) return;

        composeBody.value = '';
        composeBody.style.height = 'auto';

        const fd = new FormData();
        fd.append('action', 'send');
        fd.append('to', activeChatId);
        fd.append('body', text);
        if (replyToId) fd.append('reply_to_id', replyToId);
        currentUploads.forEach(f => fd.append('attachments[]', f));
        currentUploads = [];
        renderPreviews();
        clearReply();

        composeBody.disabled = true;
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="bi bi-arrow-repeat animate-spin"></i>';

        try {
            const res = await fetch('<?= BASE_URL ?>/api/messages.php', {method:'POST', body:fd});
            await res.json();
            await syncChat();
            scrollToBottom();
        } finally {
            composeBody.disabled = false;
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="bi bi-send-fill"></i>';
            composeBody.focus();
        }
    });

    // â”€â”€ Mobile back â”€â”€
    window.backToList = function() {
        document.getElementById('msgWrap').classList.remove('panel-right');
        activeChatId = '';
        lastMsgId = 0;
        chatActive.classList.add('hidden');
        chatActive.classList.remove('flex');
        chatEmpty.classList.remove('hidden');
    };

    // â”€â”€ Image lightbox â”€â”€
    function _lbEscKey(e) { if (e.key === 'Escape') closeMsgImage(); }
    window.openMsgImage = function(url, name) {
        const lb = document.getElementById('msgImgLightbox');
        // Escape any overflow:hidden parent by re-parenting to body
        if (lb.parentElement !== document.body) document.body.appendChild(lb);
        document.getElementById('msgImgLightboxImg').src = url;
        document.getElementById('msgImgLightboxDl').href = url;
        document.getElementById('msgImgLightboxDl').setAttribute('download', name);
        lb.style.display = 'flex';
        document.addEventListener('keydown', _lbEscKey);
    };
    window.closeMsgImage = function() {
        document.getElementById('msgImgLightbox').style.display = 'none';
        document.removeEventListener('keydown', _lbEscKey);
    };

    // Poll every 3 s
    syncChat();
    setInterval(syncChat, 3000);
});
</script>

<!-- Image Lightbox -->
<div id="msgImgLightbox" class="hidden" onclick="closeMsgImage()"
     style="position:fixed;inset:0;z-index:10001;background:rgba(0,0,0,0.92);display:none;align-items:center;justify-content:center;padding:1rem">
    <img id="msgImgLightboxImg" src="" alt="" onclick="event.stopPropagation()"
         style="max-width:100%;max-height:100%;object-fit:contain;border-radius:12px;box-shadow:0 25px 60px rgba(0,0,0,.6)">
    <button onclick="closeMsgImage()"
            style="position:absolute;top:16px;right:16px;width:40px;height:40px;background:rgba(255,255,255,0.15);border:none;border-radius:10px;color:#fff;font-size:20px;cursor:pointer;display:flex;align-items:center;justify-content:center">
        <i class="bi bi-x-lg"></i>
    </button>
    <a id="msgImgLightboxDl" href="#" onclick="event.stopPropagation()"
       style="position:absolute;bottom:24px;right:24px;background:rgba(255,255,255,0.15);color:#fff;padding:8px 18px;border-radius:10px;text-decoration:none;font-size:14px;display:flex;align-items:center;gap:8px">
        <i class="bi bi-download"></i> Download
    </a>
</div>

<?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
