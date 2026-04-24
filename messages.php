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
@media (max-width: 767px) { #msgWrap { height: calc(100vh - 60px); } }
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
</style>

<div id="msgWrap" class="flex bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mx-auto max-w-7xl">
    
    <!-- LEFT PANEL -->
    <div class="w-80 flex-shrink-0 border-r border-slate-200 bg-slate-50 flex flex-col">
        <div class="px-5 py-4 border-b border-slate-200 bg-white">
            <h2 class="font-bold text-lg text-slate-800 flex items-center gap-2">
                <i class="bi bi-chat-dots-fill text-blue-600"></i> Chats
            </h2>
        </div>
        <div class="flex-1 overflow-y-auto p-2" id="chatList">
            <!-- Rendered by JS -->
            <div class="text-center p-4 text-slate-400 text-sm"><i class="bi bi-arrow-repeat animate-spin inline-block"></i> Loading...</div>
        </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="flex-1 flex flex-col bg-[#f8fafc] relative">
        <div id="chatEmpty" class="absolute inset-0 flex flex-col items-center justify-center text-center px-6">
            <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mb-4">
                <i class="bi bi-chat-text-fill text-blue-500 text-3xl"></i>
            </div>
            <h3 class="text-xl font-bold text-slate-700">Select a Chat</h3>
            <p class="text-slate-500 mt-2 max-w-sm">Choose a conversation from the left menu to view messages or start a new conversation.</p>
        </div>

        <div id="chatActive" class="hidden flex-col h-full">
            <div class="px-6 py-4 bg-white border-b border-slate-200 shadow-sm shrink-0 flex items-center justify-between z-10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center text-slate-500 font-bold" id="chatHeaderInitials"></div>
                    <div>
                        <h3 class="font-bold text-slate-800 leading-tight" id="chatHeaderName">Name</h3>
                        <p class="text-xs text-slate-500" id="chatHeaderRole">Role</p>
                    </div>
                </div>
            </div>

            <div class="flex-1 p-6 space-y-4 relative" id="msgHistory">
                <!-- Messages -->
            </div>

            <div class="px-4 py-3 bg-white border-t border-slate-200 shrink-0">
                <div id="filePreviews" class="flex flex-wrap gap-2 mb-2 empty:hidden"></div>
                <form id="composeForm" class="flex items-end gap-2 bg-slate-100 rounded-xl p-2">
                    <label class="p-2 text-slate-400 hover:text-blue-600 cursor-pointer transition rounded-lg hover:bg-slate-200 shrink-0" title="Attach file">
                        <i class="bi bi-paperclip text-xl"></i>
                        <input type="file" id="composeFiles" class="hidden" multiple>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    let activeChatId = '';
    let lastMsgId = 0;
    
    // Elements
    const chatList = document.getElementById('chatList');
    const msgHistory = document.getElementById('msgHistory');
    const chatEmpty = document.getElementById('chatEmpty');
    const chatActive = document.getElementById('chatActive');
    const chatHeaderName = document.getElementById('chatHeaderName');
    const chatHeaderRole = document.getElementById('chatHeaderRole');
    const chatHeaderInitials = document.getElementById('chatHeaderInitials');
    
    const composeForm = document.getElementById('composeForm');
    const composeBody = document.getElementById('composeBody');
    const composeFiles = document.getElementById('composeFiles');
    const filePreviews = document.getElementById('filePreviews');
    const sendBtn = document.getElementById('sendBtn');
    
    let currentUploads = [];
    let isFetching = false;
    
    function scrollToBottom() {
        msgHistory.scrollTop = msgHistory.scrollHeight;
    }
    
    // Auto-resize textarea
    composeBody.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });
    
    // Enter to send
    composeBody.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (composeBody.value.trim() !== '' || currentUploads.length > 0) {
                composeForm.dispatchEvent(new Event('submit'));
            }
        }
    });
    
    // Attachments
    composeFiles.addEventListener('change', () => {
        for (let file of composeFiles.files) {
            currentUploads.push(file);
        }
        renderPreviews();
        composeFiles.value = '';
    });
    
    function renderPreviews() {
        filePreviews.innerHTML = '';
        currentUploads.forEach((f, i) => {
            const el = document.createElement('div');
            el.className = 'bg-blue-50 text-blue-700 text-xs px-2 py-1 rounded flex items-center gap-2 border border-blue-200';
            el.innerHTML = `
                <span class="truncate max-w-[120px]">${f.name}</span>
                <i class="bi bi-x cursor-pointer hover:text-red-500" data-idx="${i}"></i>
            `;
            filePreviews.appendChild(el);
        });
        filePreviews.querySelectorAll('.bi-x').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const idx = e.target.dataset.idx;
                currentUploads.splice(idx, 1);
                renderPreviews();
            });
        });
    }
    
    // Date formatting
    function formatTime(d) {
        const date = new Date(d);
        const now = new Date();
        const diffMs = now - date;
        const diffDays = Math.floor(diffMs / 86400000);
        
        if (diffDays === 0 && now.getDate() === date.getDate()) {
            return date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        } else if (diffDays < 7) {
            return date.toLocaleDateString([], {weekday: 'short'}) + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        }
        return date.toLocaleDateString([], {month: 'short', day: 'numeric'}) + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }

    // Render left panel
    function renderChats(chats) {
        // Keep active selection visual state
        let html = '';
        chats.forEach(c => {
            const isAct = c.id == activeChatId;
            const initials = c.name.substring(0,2).toUpperCase();
            const timeStr = c.latest_time ? formatTime(c.latest_time.replace(' ','T')) : '';
            const unreadLine = c.unreads > 0 
                ? `<div class="bg-blue-600 text-white unread-badge flex-shrink-0">${c.unreads}</div>` 
                : '';
                
            let preview = c.latest_body || 'No messages yet';
            if (preview.length > 35) preview = preview.substring(0,35) + '...';
            
            html += `
            <div class="chat-list-item flex items-center gap-3 p-3 rounded-xl cursor-pointer border-l-4 ${isAct ? 'active' : 'border-transparent'}" onclick="openChat('${c.id}', '${(c.name || '').replace(/'/g, "\\'")}', '${(c.role || '').replace(/'/g, "\\'")}', '${initials}')">
                <div class="w-12 h-12 bg-slate-200 rounded-full flex items-center justify-center text-slate-500 font-bold shrink-0">
                    ${c.id === 'all' ? '<i class="bi bi-megaphone-fill text-blue-500"></i>' : initials}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex justify-between items-baseline mb-0.5">
                        <span class="font-bold text-sm text-slate-800 truncate">${c.name}</span>
                        <span class="text-[10px] text-slate-400 whitespace-nowrap ml-2">${timeStr}</span>
                    </div>
                    <div class="flex items-center justify-between text-xs text-slate-500 gap-2">
                        <span class="truncate">${preview}</span>
                        ${unreadLine}
                    </div>
                </div>
            </div>`;
        });
        chatList.innerHTML = html;
        
        // Let's update the global header badge too if there are any unreads total
        const totalUnread = chats.reduce((sum, c) => sum + c.unreads, 0);
        const headerBadge = document.querySelector('.bg-red-500.rounded-full'); // specific to header.php nav
        if (headerBadge) {
            if (totalUnread > 0) {
                headerBadge.textContent = totalUnread;
                headerBadge.classList.remove('hidden');
            } else {
                headerBadge.classList.add('hidden');
            }
        }
    }
    
    // Ensure scrolling happens only when users are at bottom, or we explicitly force it (on new open)
    let wasAtBottom = true;
    msgHistory.addEventListener('scroll', () => {
        wasAtBottom = msgHistory.scrollHeight - msgHistory.scrollTop - msgHistory.clientHeight < 20;
    });

    // Render messages
    function renderMessages(messages, append = false) {
        if (!append) msgHistory.innerHTML = '';
        
        messages.forEach(m => {
            const isMe = m.from_user_id == <?= $myId ?>;
            const bClass = isMe ? 'bubble-me' : 'bubble-them';
            
            let attHtml = '';
            if (m.attachments && m.attachments.length) {
                m.attachments.forEach(a => {
                    const dlLink = `<?= BASE_URL ?>/api/messages.php?action=download&file_id=${a.id}`;
                    attHtml += `<a href="${dlLink}" target="_blank" class="att-chip">
                        <i class="bi bi-file-earmark-arrow-down"></i> <span class="truncate max-w-[150px]" title="${a.original_name}">${a.original_name}</span>
                    </a>`;
                });
            }
            
            // Format body (bold text, newlines)
            let bodyFmt = m.body;
            bodyFmt = String(bodyFmt).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
            bodyFmt = bodyFmt.replace(/\\*\\*(.*?)\\*\\*/g, '<strong>$1</strong>');
            bodyFmt = bodyFmt.replace(/\n/g, '<br>');
            
            const senderName = isMe ? '' : `<div class="text-xs text-slate-500 ml-1 mb-1 font-semibold">${m.from_name}</div>`;
            const timeVal = formatTime(m.created_at.replace(' ','T'));
            
            const div = document.createElement('div');
            div.innerHTML = `
                <div class="flex flex-col mb-3">
                    ${senderName}
                    <div class="bubble ${bClass}">
                        <div class="text-sm leading-relaxed">${bodyFmt}</div>
                        ${attHtml}
                        <span class="time-label">${timeVal}</span>
                    </div>
                </div>
            `;
            msgHistory.appendChild(div);
            lastMsgId = Math.max(lastMsgId, parseInt(m.id));
        });
        
        if (wasAtBottom || !append) scrollToBottom();
    }

    // Assign globally so onclick works
    window.openChat = function(id, name, role, initials) {
        activeChatId = id;
        lastMsgId = 0; // reset for full fetch
        wasAtBottom = true;
        
        chatEmpty.classList.add('hidden');
        chatActive.classList.remove('hidden');
        chatActive.classList.add('flex');
        
        chatHeaderName.textContent = name;
        chatHeaderRole.textContent = role;
        
        if (id === 'all') {
            chatHeaderInitials.innerHTML = '<i class="bi bi-megaphone-fill text-blue-500"></i>';
        } else {
            chatHeaderInitials.innerHTML = initials;
        }
        
        // Render loading state inside message area
        msgHistory.innerHTML = '<div class="absolute inset-0 flex items-center justify-center text-slate-300"><i class="bi bi-arrow-repeat animate-spin text-2xl"></i></div>';
        
        // Immediate fetch
        syncChat();
        composeBody.focus();
    }

    // Sync function: fetches chats list and any new messages for active chat
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
                    renderMessages(data.messages, lastMsgId > 0); // append if lastMsgId > 0, else replace
                } else if (activeChatId && lastMsgId === 0) {
                    msgHistory.innerHTML = '<div class="text-center p-6 text-slate-400 text-sm">No messages here yet. Say hi!</div>';
                }
            }
        } catch (e) {
            console.error('Sync error:', e);
        } finally {
            isFetching = false;
        }
    }
    
    // Submit message
    composeForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = composeBody.value.trim();
        if (!text && currentUploads.length === 0) return;
        
        // optimistic UI
        composeBody.value = '';
        composeBody.style.height = 'auto'; // reset
        
        const fd = new FormData();
        fd.append('action', 'send');
        fd.append('to', activeChatId);
        fd.append('body', text);
        currentUploads.forEach(f => fd.append('attachments[]', f));
        
        // clear uploads visually
        currentUploads = [];
        renderPreviews();
        
        // lock UI a bit
        composeBody.disabled = true;
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="bi bi-arrow-repeat animate-spin"></i>';
        
        try {
            const res = await fetch('<?= BASE_URL ?>/api/messages.php', {
                method: 'POST',
                body: fd
            });
            await res.json();
            // Immediate sync to show our message
            await syncChat();
            scrollToBottom();
        } finally {
            composeBody.disabled = false;
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="bi bi-send-fill"></i>';
            composeBody.focus();
        }
    });

    // Poll every 3 seconds
    syncChat(); // Initial load
    setInterval(syncChat, 3000);
});
</script>

<?php endif; ?>