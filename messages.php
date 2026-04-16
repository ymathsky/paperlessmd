<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
requireLogin();

$pageTitle = 'Messages';
$activeNav = 'messages';
$msgCsrf   = csrfToken();
$myId      = (int)$_SESSION['user_id'];

// Check whether the messages table exists (safe before migration)
$_msgReady = false;
try {
    $pdo->query("SELECT 1 FROM messages LIMIT 1");
    $_msgReady = true;
} catch (PDOException $e) { /* migration not run yet */ }

include __DIR__ . '/includes/header.php';
?>

<?php if (!$_msgReady): ?>
<!-- Migration pending banner -->
<div class="bg-amber-50 border border-amber-200 rounded-2xl p-6 max-w-lg mx-auto mt-10 text-center">
    <div class="w-12 h-12 bg-amber-100 rounded-xl grid place-items-center mx-auto mb-4">
        <i class="bi bi-exclamation-triangle-fill text-amber-500 text-xl"></i>
    </div>
    <h2 class="font-bold text-slate-800 mb-1">Messaging not set up yet</h2>
    <p class="text-sm text-slate-600 mb-4">
        The database tables for messaging haven't been created. 
        <?php if (isAdmin()): ?>
        Run the migration to activate this feature.
        <?php else: ?>
        Please ask your admin to run <code class="bg-slate-100 px-1 rounded">migrate_messages.php</code>.
        <?php endif; ?>
    </p>
    <?php if (isAdmin()): ?>
    <a href="<?= BASE_URL ?>/migrate_messages.php"
       class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white
              text-sm font-semibold px-5 py-2.5 rounded-xl transition">
        <i class="bi bi-database-fill-add"></i> Run Migration Now
    </a>
    <?php endif; ?>
</div>
<?php else: ?>
<!-- ═════════════════════════════════════════════════════════════════════════
     Two-pane messaging layout
═════════════════════════════════════════════════════════════════════════ -->

<div id="msgApp"
     class="flex h-full overflow-hidden"
     data-api="<?= BASE_URL ?>/api/messages.php"
     data-csrf="<?= h($msgCsrf) ?>"
     data-me="<?= $myId ?>">

    <!-- ── LEFT: conversation list ──────────────────────────────────────── -->
    <div id="convPane"
         class="w-72 lg:w-80 flex-shrink-0 bg-white border-r border-slate-200
                flex flex-col overflow-hidden transition-all duration-200">

        <!-- Pane header -->
        <div class="px-4 py-3.5 border-b border-slate-100 flex items-center justify-between shrink-0">
            <h1 class="font-bold text-slate-800 text-base flex items-center gap-2">
                <i class="bi bi-chat-dots-fill text-blue-600 text-lg"></i>
                Messages
            </h1>
            <button id="composeBtn"
                    class="bg-blue-600 hover:bg-blue-700 active:scale-95 text-white
                           text-xs font-semibold px-3 py-1.5 rounded-lg transition
                           flex items-center gap-1.5">
                <i class="bi bi-pencil-square"></i> Write
            </button>
        </div>

        <!-- Search box -->
        <div class="px-3 py-2 border-b border-slate-100 shrink-0">
            <div class="relative">
                <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                <input id="convSearch" type="search" placeholder="Search conversations…"
                       class="w-full text-sm pl-8 pr-3 py-2 border border-slate-200
                              rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-300 transition">
            </div>
        </div>

        <!-- Conversation list -->
        <div id="convList" class="flex-1 overflow-y-auto divide-y divide-slate-50">
            <div class="flex items-center justify-center h-20">
                <div class="animate-spin w-5 h-5 border-2 border-slate-200 border-t-blue-500 rounded-full"></div>
            </div>
        </div>
    </div>

    <!-- ── RIGHT: thread / compose / empty ──────────────────────────────── -->
    <div id="rightPane" class="flex-1 flex flex-col overflow-hidden min-w-0">

        <!-- Empty state -->
        <div id="emptyState"
             class="flex-1 flex flex-col items-center justify-center gap-4
                    bg-slate-50 text-slate-300">
            <i class="bi bi-chat-text text-6xl"></i>
            <div class="text-center">
                <p class="text-base font-semibold text-slate-500">No conversation selected</p>
                <p class="text-sm text-slate-400">Select a message or write a new one</p>
            </div>
        </div>

        <!-- ── Thread view (hidden by default) ─────────────────────────── -->
        <div id="threadView" class="hidden flex flex-col h-full overflow-hidden">

            <!-- Thread header -->
            <div id="threadHeader"
                 class="shrink-0 px-5 py-3 bg-white border-b border-slate-200
                        flex items-center justify-between gap-4 min-w-0">
            </div>

            <!-- Message bubbles -->
            <div id="threadMessages"
                 class="flex-1 overflow-y-auto p-5 space-y-4 bg-slate-50">
            </div>

            <!-- Reply composer -->
            <div class="shrink-0 bg-white border-t border-slate-200 p-4">
                <textarea id="replyBody" placeholder="Write a reply…" rows="3"
                          class="w-full text-sm border border-slate-200 rounded-xl
                                 px-3 py-2.5 resize-none focus:outline-none
                                 focus:ring-2 focus:ring-blue-300 transition"></textarea>
                <div class="mt-2.5 flex items-center justify-between gap-3 flex-wrap">
                    <label class="flex items-center gap-1.5 text-xs text-slate-500
                                  cursor-pointer hover:text-blue-600 transition
                                  font-medium select-none">
                        <i class="bi bi-paperclip text-sm"></i>
                        <span id="replyFileName">Attach file</span>
                        <input type="file" id="replyFileInput" class="hidden"
                               accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip">
                    </label>
                    <button id="replyBtn"
                            class="bg-blue-600 hover:bg-blue-700 active:scale-95
                                   text-white text-sm font-semibold px-4 py-2
                                   rounded-xl transition flex items-center gap-2">
                        <i class="bi bi-send-fill text-xs"></i> Reply
                    </button>
                </div>
            </div>
        </div>

        <!-- ── Compose new message (hidden by default) ──────────────────── -->
        <div id="composeView" class="hidden flex flex-col h-full overflow-hidden bg-white">

            <!-- Compose header -->
            <div class="shrink-0 px-5 py-3.5 border-b border-slate-200
                        flex items-center justify-between">
                <h2 class="font-bold text-slate-800 flex items-center gap-2">
                    <i class="bi bi-pencil-square text-blue-600"></i> New Message
                </h2>
                <button id="cancelComposeBtn"
                        class="text-slate-400 hover:text-slate-700 w-8 h-8
                               flex items-center justify-center rounded-lg
                               hover:bg-slate-100 transition">
                    <i class="bi bi-x-lg text-base leading-none"></i>
                </button>
            </div>

            <!-- Compose form -->
            <div class="flex-1 overflow-y-auto p-5">
                <div class="space-y-5 max-w-2xl">

                    <div>
                        <label class="block text-xs font-bold text-slate-500
                                      mb-1.5 uppercase tracking-wider">To</label>
                        <select id="composeTo"
                                class="w-full text-sm border border-slate-200
                                       rounded-xl px-3 py-2.5 bg-white
                                       focus:outline-none focus:ring-2 focus:ring-blue-300">
                            <option value="all">📢 All Staff (Broadcast)</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500
                                      mb-1.5 uppercase tracking-wider">Subject</label>
                        <input type="text" id="composeSubject"
                               placeholder="Message subject"
                               class="w-full text-sm border border-slate-200 rounded-xl
                                      px-3 py-2.5 focus:outline-none
                                      focus:ring-2 focus:ring-blue-300 transition">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500
                                      mb-1.5 uppercase tracking-wider">Message</label>
                        <textarea id="composeBody" placeholder="Type your message…"
                                  rows="9"
                                  class="w-full text-sm border border-slate-200 rounded-xl
                                         px-3 py-2.5 resize-none focus:outline-none
                                         focus:ring-2 focus:ring-blue-300 transition"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-500
                                      mb-1.5 uppercase tracking-wider">
                            Attachment
                            <span class="font-normal normal-case text-slate-400">
                                (optional · max 25 MB)
                            </span>
                        </label>
                        <label id="composeFileLabel"
                               class="flex items-center gap-3 cursor-pointer w-full
                                      border border-dashed border-slate-300
                                      hover:border-blue-400 rounded-xl px-4 py-3
                                      transition group">
                            <i class="bi bi-paperclip text-slate-400 group-hover:text-blue-500
                                      text-lg transition"></i>
                            <span id="composeFileName"
                                  class="text-sm text-slate-500 group-hover:text-blue-600
                                         transition truncate">
                                Click to select a file
                            </span>
                            <input type="file" id="composeFileInput" class="hidden"
                                   accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip">
                        </label>
                    </div>

                </div>
            </div>

            <!-- Compose footer -->
            <div class="shrink-0 px-5 py-3.5 border-t border-slate-200
                        flex items-center justify-end gap-3">
                <button id="cancelComposeBtn2"
                        class="text-sm font-semibold text-slate-600 px-4 py-2
                               rounded-xl hover:bg-slate-100 transition">
                    Cancel
                </button>
                <button id="sendNewBtn"
                        class="bg-blue-600 hover:bg-blue-700 active:scale-95
                               text-white text-sm font-semibold px-5 py-2.5
                               rounded-xl transition flex items-center gap-2 shadow-sm">
                    <i class="bi bi-send-fill text-xs"></i> Send Message
                </button>
            </div>
        </div>

    </div>
</div>

<script>
(function () {
    'use strict';

    // ── Config ────────────────────────────────────────────────────────────────
    const app   = document.getElementById('msgApp');
    const API   = app.dataset.api;
    const CSRF  = app.dataset.csrf;
    const MY_ID = parseInt(app.dataset.me, 10);

    // ── State ─────────────────────────────────────────────────────────────────
    let currentRootId = null;
    let allUsers      = [];
    let pollTimer     = null;
    let allConvs      = [];  // cached for search

    // ── DOM refs ──────────────────────────────────────────────────────────────
    const convList        = document.getElementById('convList');
    const convSearch      = document.getElementById('convSearch');
    const emptyState      = document.getElementById('emptyState');
    const threadView      = document.getElementById('threadView');
    const composeView     = document.getElementById('composeView');
    const threadHeader    = document.getElementById('threadHeader');
    const threadMessages  = document.getElementById('threadMessages');
    const replyBody       = document.getElementById('replyBody');
    const replyBtn        = document.getElementById('replyBtn');
    const replyFileInput  = document.getElementById('replyFileInput');
    const replyFileName   = document.getElementById('replyFileName');
    const composeBtn      = document.getElementById('composeBtn');
    const cancelCompose   = document.getElementById('cancelComposeBtn');
    const cancelCompose2  = document.getElementById('cancelComposeBtn2');
    const sendNewBtn      = document.getElementById('sendNewBtn');
    const composeTo       = document.getElementById('composeTo');
    const composeSubject  = document.getElementById('composeSubject');
    const composeBody     = document.getElementById('composeBody');
    const composeFileInput = document.getElementById('composeFileInput');
    const composeFileName  = document.getElementById('composeFileName');
    const convPane        = document.getElementById('convPane');

    // ── Helpers ───────────────────────────────────────────────────────────────
    function esc(s) {
        return String(s ?? '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function fmtTime(dtStr) {
        if (!dtStr) return '';
        const d   = new Date(dtStr.replace(' ', 'T') + 'Z');
        const now = new Date();
        const diffMs  = now - d;
        const diffMin = Math.floor(diffMs / 60000);
        const diffHr  = Math.floor(diffMin / 60);
        const diffDay = Math.floor(diffHr  / 24);
        if (diffMin < 1)   return 'just now';
        if (diffMin < 60)  return diffMin + 'm ago';
        if (diffHr  < 24)  return diffHr  + 'h ago';
        if (diffDay < 7)   return diffDay + 'd ago';
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
    }

    function fmtBytes(n) {
        n = parseInt(n, 10);
        if (n < 1024)       return n + ' B';
        if (n < 1048576)    return (n / 1024).toFixed(1) + ' KB';
        return (n / 1048576).toFixed(1) + ' MB';
    }

    function initials(name) {
        const parts = String(name ?? '').split(' ').filter(Boolean);
        if (!parts.length) return '?';
        return (parts[0][0] + (parts[1]?.[0] ?? '')).toUpperCase();
    }

    function roleColor(role) {
        const map = { admin: 'from-blue-500 to-blue-700',
                      ma:    'from-emerald-500 to-emerald-700',
                      billing: 'from-amber-500 to-amber-600' };
        return map[role] ?? 'from-slate-500 to-slate-600';
    }

    function showView(name) {
        // name: 'empty' | 'thread' | 'compose'
        emptyState.classList.toggle ('hidden', name !== 'empty');
        threadView.classList.toggle ('hidden', name !== 'thread');
        composeView.classList.toggle('hidden', name !== 'compose');

        emptyState.classList.toggle ('flex', name === 'empty');
        threadView.classList.toggle ('flex', name === 'thread');
        composeView.classList.toggle('flex', name === 'compose');

        // Mobile: hide conv pane when a thread/compose is open
        if (window.innerWidth < 768) {
            convPane.classList.toggle('hidden', name !== 'empty');
        }
    }

    function setBusy(btn, busy) {
        btn.disabled = busy;
        btn.classList.toggle('opacity-60', busy);
        btn.classList.toggle('cursor-not-allowed', busy);
    }

    function attachmentIcon(mime) {
        if (mime.startsWith('image/'))         return 'bi-file-image-fill text-blue-500';
        if (mime === 'application/pdf')         return 'bi-file-pdf-fill text-red-500';
        if (mime.includes('word'))              return 'bi-file-word-fill text-blue-600';
        if (mime.includes('excel') || mime.includes('spreadsheet')) return 'bi-file-excel-fill text-emerald-600';
        if (mime === 'text/csv')                return 'bi-filetype-csv text-emerald-500';
        if (mime === 'text/plain')              return 'bi-file-text-fill text-slate-500';
        if (mime.includes('zip'))               return 'bi-file-zip-fill text-amber-500';
        return 'bi-file-earmark-fill text-slate-400';
    }

    // ── Conversation list ─────────────────────────────────────────────────────
    async function loadConvs() {
        try {
            const res  = await fetch(API + '?action=list');
            const data = await res.json();
            allConvs = data.ok ? (data.conversations || []) : [];
            renderConvList(allConvs);
        } catch (e) {
            renderConvList([]);
        }
    }

    function renderConvList(convs) {
        if (!convs.length) {
            convList.innerHTML =
                '<div class="p-8 text-center text-slate-400 text-sm">' +
                '<i class="bi bi-chat-dots text-3xl block mb-2 opacity-40"></i>' +
                'No messages yet</div>';
            return;
        }
        convList.innerHTML = convs.map(c => convItem(c)).join('');
        convList.querySelectorAll('[data-root-id]').forEach(el => {
            el.addEventListener('click', () => openThread(parseInt(el.dataset.rootId, 10)));
        });
    }

    function convItem(c) {
        const isMine   = parseInt(c.from_user_id, 10) === MY_ID;
        const peerName = isMine ? c.to_name : c.from_name;
        const unread   = parseInt(c.unread_count, 10) > 0;
        const active   = currentRootId === parseInt(c.id, 10);
        const snippet  = (c.last_body ?? '').substring(0, 80);
        const subject  = c.subject || '(no subject)';

        return `<div data-root-id="${esc(c.id)}"
             class="px-4 py-3.5 cursor-pointer select-none transition-colors
                    hover:bg-slate-50 ${active ? 'bg-blue-50' : ''}
                    ${unread ? 'border-l-2 border-blue-500' : 'border-l-2 border-transparent'}">
            <div class="flex items-start gap-3 min-w-0">
                <div class="flex-shrink-0 w-9 h-9 rounded-xl
                            bg-gradient-to-br ${isMine ? 'from-blue-500 to-blue-700' : 'from-slate-500 to-slate-600'}
                            grid place-items-center text-white text-xs font-bold shadow-sm">
                    ${esc(initials(peerName))}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="text-sm font-${unread ? 'bold' : 'semibold'} text-slate-800 truncate">
                            ${esc(peerName)}
                        </span>
                        <span class="text-[10px] text-slate-400 shrink-0 whitespace-nowrap">
                            ${esc(fmtTime(c.last_activity))}
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-2 mt-0.5">
                        <p class="text-xs ${unread ? 'font-semibold text-slate-700' : 'text-slate-500'} truncate flex-1">
                            ${esc(subject)}
                        </p>
                        ${unread ? `<span class="shrink-0 w-2 h-2 rounded-full bg-blue-500"></span>` : ''}
                    </div>
                    ${snippet ? `<p class="text-[11px] text-slate-400 truncate mt-0.5">${esc(snippet)}</p>` : ''}
                </div>
            </div>
        </div>`;
    }

    // ── Thread view ───────────────────────────────────────────────────────────
    async function openThread(rootId) {
        currentRootId = rootId;
        showView('thread');
        threadMessages.innerHTML =
            '<div class="flex items-center justify-center h-20">' +
            '<div class="animate-spin w-5 h-5 border-2 border-slate-200 border-t-blue-500 rounded-full"></div>' +
            '</div>';

        try {
            const res  = await fetch(`${API}?action=thread&id=${rootId}`);
            const data = await res.json();
            if (!data.ok) {
                threadMessages.innerHTML =
                    '<p class="text-center text-red-500 text-sm py-8">Failed to load thread.</p>';
                return;
            }
            renderThreadHeader(data.root);
            renderMessages(data.messages);
        } catch (e) {
            threadMessages.innerHTML =
                '<p class="text-center text-red-500 text-sm py-8">Network error.</p>';
        }

        // Refresh conv list to clear unread dots
        await loadConvs();
        // Re-highlight selected
        document.querySelectorAll('[data-root-id]').forEach(el => {
            const rid = parseInt(el.dataset.rootId, 10);
            el.classList.toggle('bg-blue-50', rid === currentRootId);
        });
    }

    function renderThreadHeader(root) {
        const subject   = root.subject || '(no subject)';
        const backBtn   = window.innerWidth < 768
            ? `<button id="backBtn" class="mr-2 text-slate-400 hover:text-slate-700">
                   <i class="bi bi-arrow-left text-lg"></i>
               </button>` : '';
        threadHeader.innerHTML = `
            ${backBtn}
            <div class="flex-1 min-w-0">
                <h2 class="font-bold text-slate-800 text-sm truncate">${esc(subject)}</h2>
            </div>`;

        const backEl = document.getElementById('backBtn');
        if (backEl) {
            backEl.addEventListener('click', () => {
                currentRootId = null;
                showView('empty');
                convPane.classList.remove('hidden');
            });
        }
    }

    function renderMessages(msgs) {
        if (!msgs.length) {
            threadMessages.innerHTML =
                '<p class="text-center text-slate-400 text-sm py-8">No messages.</p>';
            return;
        }
        threadMessages.innerHTML = msgs.map(m => msgBubble(m)).join('');
        // Scroll to bottom
        threadMessages.scrollTop = threadMessages.scrollHeight;
    }

    function msgBubble(m) {
        const isMine = parseInt(m.from_user_id, 10) === MY_ID;
        const atts   = (m.attachments || []).map(a => `
            <a href="${API}?action=download&id=${esc(a.id)}"
               class="inline-flex items-center gap-2 text-xs font-medium
                      text-blue-700 bg-blue-50 hover:bg-blue-100
                      rounded-lg px-3 py-1.5 mt-2 transition border border-blue-200"
               download="${esc(a.original_name)}">
                <i class="bi ${esc(attachmentIcon(a.mime_type))} text-sm shrink-0"></i>
                <span class="truncate max-w-[200px]">${esc(a.original_name)}</span>
                <span class="text-slate-400 ml-1 shrink-0">${esc(fmtBytes(a.file_size))}</span>
            </a>`).join('');

        const deleteBtn = isMine ? `
            <button class="delete-msg opacity-0 group-hover:opacity-100 ml-2
                           text-slate-300 hover:text-red-500 text-xs transition shrink-0"
                    data-msg-id="${esc(m.id)}" title="Delete">
                <i class="bi bi-trash3 pointer-events-none"></i>
            </button>` : '';

        return `
        <div class="flex gap-3 items-start group ${isMine ? 'flex-row-reverse' : ''}">
            <div class="flex-shrink-0 w-8 h-8 rounded-xl
                        bg-gradient-to-br ${esc(roleColor(m.from_role))}
                        grid place-items-center text-white text-[11px] font-bold shadow-sm">
                ${esc(initials(m.from_name))}
            </div>
            <div class="flex-1 min-w-0 max-w-[75%]">
                <div class="flex items-center gap-2 mb-1 ${isMine ? 'justify-end' : ''}">
                    <span class="text-xs font-semibold text-slate-700">
                        ${esc(isMine ? 'You' : m.from_name)}
                    </span>
                    <span class="text-[10px] text-slate-400">
                        ${esc(fmtTime(m.created_at))}
                    </span>
                    ${deleteBtn}
                </div>
                <div class="rounded-2xl px-4 py-2.5 text-sm leading-relaxed break-words
                            ${isMine
                                ? 'bg-blue-600 text-white rounded-tr-sm'
                                : 'bg-white text-slate-800 border border-slate-200 rounded-tl-sm shadow-sm'}">
                    ${esc(m.body).replace(/\n/g, '<br>')}
                </div>
                ${atts ? `<div class="flex flex-wrap gap-2 mt-1 ${isMine ? 'justify-end' : ''}">${atts}</div>` : ''}
            </div>
        </div>`;
    }

    // ── Reply ─────────────────────────────────────────────────────────────────
    async function sendReply() {
        const body = replyBody.value.trim();
        if (!body) { replyBody.focus(); return; }

        setBusy(replyBtn, true);
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('body', body);
        fd.append('parent_id', currentRootId);
        if (replyFileInput.files[0]) {
            fd.append('file', replyFileInput.files[0]);
        }

        try {
            const res  = await fetch(`${API}?action=send`, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) {
                replyBody.value = '';
                replyFileInput.value = '';
                replyFileName.textContent = 'Attach file';
                await openThread(currentRootId);
            } else {
                alert(data.error || 'Failed to send reply.');
            }
        } catch (e) {
            alert('Network error. Please try again.');
        } finally {
            setBusy(replyBtn, false);
        }
    }

    // ── Send new message ──────────────────────────────────────────────────────
    async function sendNew() {
        const body    = composeBody.value.trim();
        const subject = composeSubject.value.trim();
        const to      = composeTo.value;

        if (!subject) { composeSubject.focus(); return; }
        if (!body)    { composeBody.focus();    return; }

        setBusy(sendNewBtn, true);
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('body',       body);
        fd.append('subject',    subject);
        fd.append('to',         to);
        if (composeFileInput.files[0]) {
            fd.append('file', composeFileInput.files[0]);
        }

        try {
            const res  = await fetch(`${API}?action=send`, { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) {
                // Reset compose form
                composeSubject.value = '';
                composeBody.value    = '';
                composeFileInput.value = '';
                composeFileName.textContent = 'Click to select a file';
                // Open the new thread
                await loadConvs();
                await openThread(data.message_id);
            } else {
                alert(data.error || 'Failed to send message.');
            }
        } catch (e) {
            alert('Network error. Please try again.');
        } finally {
            setBusy(sendNewBtn, false);
        }
    }

    // ── Delete message ────────────────────────────────────────────────────────
    async function deleteMessage(msgId) {
        if (!confirm('Delete this message?')) return;
        try {
            const res  = await fetch(`${API}?action=delete`, {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ csrf_token: CSRF, message_id: msgId })
            });
            const data = await res.json();
            if (data.ok) {
                // If we deleted the root, go back to empty; otherwise reload thread
                if (parseInt(msgId, 10) === currentRootId) {
                    currentRootId = null;
                    showView('empty');
                    await loadConvs();
                } else {
                    await openThread(currentRootId);
                }
            } else {
                alert(data.error || 'Could not delete message.');
            }
        } catch (e) {
            alert('Network error.');
        }
    }

    // ── Load staff for compose picker ─────────────────────────────────────────
    async function loadUsers() {
        try {
            const res  = await fetch(`${API}?action=users`);
            const data = await res.json();
            if (!data.ok) return;
            allUsers = data.users || [];
            allUsers.forEach(u => {
                const opt = document.createElement('option');
                opt.value       = u.id;
                opt.textContent = u.full_name + ' (' + u.role + ')';
                composeTo.appendChild(opt);
            });
        } catch (e) { /* ignore */ }
    }

    // ── Search filter ─────────────────────────────────────────────────────────
    function filterConvs(q) {
        q = q.toLowerCase().trim();
        if (!q) { renderConvList(allConvs); return; }
        renderConvList(allConvs.filter(c =>
            (c.subject      || '').toLowerCase().includes(q) ||
            (c.from_name    || '').toLowerCase().includes(q) ||
            (c.to_name      || '').toLowerCase().includes(q) ||
            (c.last_body    || '').toLowerCase().includes(q)
        ));
    }

    // ── Polling ───────────────────────────────────────────────────────────────
    function startPolling() {
        pollTimer = setInterval(async () => {
            await loadConvs();
            // If a thread is open, silently refresh it for new replies
            if (currentRootId) {
                try {
                    const res  = await fetch(`${API}?action=thread&id=${currentRootId}`);
                    const data = await res.json();
                    if (data.ok) {
                        renderMessages(data.messages);
                        // Update nav badge
                        const badgeEl = document.getElementById('msgNavBadge');
                        if (badgeEl) badgeEl.textContent = '';
                    }
                } catch (e) { /* ignore */ }
            }
        }, 30000);
    }

    // ── Event wiring ──────────────────────────────────────────────────────────
    composeBtn.addEventListener('click', () => {
        currentRootId = null;
        showView('compose');
        composeBody.focus();
    });
    cancelCompose.addEventListener('click',  () => showView('empty'));
    cancelCompose2.addEventListener('click', () => showView('empty'));
    sendNewBtn.addEventListener('click',     () => sendNew());
    replyBtn.addEventListener('click',       () => sendReply());

    composeBody.addEventListener('keydown', e => {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) sendNew();
    });
    replyBody.addEventListener('keydown', e => {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) sendReply();
    });

    // File picker labels
    composeFileInput.addEventListener('change', () => {
        composeFileName.textContent = composeFileInput.files[0]
            ? composeFileInput.files[0].name : 'Click to select a file';
    });
    replyFileInput.addEventListener('change', () => {
        replyFileName.textContent = replyFileInput.files[0]
            ? replyFileInput.files[0].name : 'Attach file';
    });

    // Delete bubble clicks (delegated)
    threadMessages.addEventListener('click', e => {
        const btn = e.target.closest('.delete-msg');
        if (btn) deleteMessage(parseInt(btn.dataset.msgId, 10));
    });

    // Search
    convSearch.addEventListener('input', () => filterConvs(convSearch.value));

    // ── Init ──────────────────────────────────────────────────────────────────
    showView('empty');
    loadConvs();
    loadUsers();
    startPolling();

})();
</script>

<?php endif; // $_msgReady ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
