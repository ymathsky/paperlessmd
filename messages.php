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
        <?php if (isAdmin()): ?>Run the migration to activate this feature.
        <?php else: ?>Please ask your admin to run <code class="bg-slate-100 px-1 rounded">migrate_messages.php</code>.
        <?php endif; ?>
    </p>
    <?php if (isAdmin()): ?>
    <a href="<?= BASE_URL ?>/migrate_messages.php"
       class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-semibold px-5 py-2.5 rounded-xl transition">
        <i class="bi bi-database-fill-add"></i> Run Migration Now
    </a>
    <?php endif; ?>
</div>
<?php else: ?>

<style>
#msgWrap { height: calc(100vh - 112px); }
@media (max-width: 767px) { #msgWrap { height: calc(100vh - 56px); } }
#threadMessages { scroll-behavior: smooth; }
.conv-item { transition: background .12s; }
.conv-item:hover { background: #f1f5f9; }
.conv-item.active { background: #eff6ff; }
.bubble-me   { background: #2563eb; color:#fff; border-radius:18px 18px 4px 18px; }
.bubble-them { background: #fff;    color:#1e293b; border-radius:18px 18px 18px 4px; box-shadow:0 1px 2px rgba(0,0,0,.08); border:1px solid #e2e8f0; }
.att-chip { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:500;
            border-radius:10px; padding:5px 10px; transition:background .15s; }
.att-chip-blue { background:#eff6ff; color:#1d4ed8; border:1px solid #bfdbfe; }
.att-chip-blue:hover { background:#dbeafe; }
.att-chip-white { background:rgba(255,255,255,.15); color:#fff; border:1px solid rgba(255,255,255,.3); }
.att-chip-white:hover { background:rgba(255,255,255,.25); }
.reply-input { resize:none; min-height:44px; max-height:120px; overflow-y:auto; }
</style>

<!-- ═══════ MESSAGES APP ═══════ -->
<div id="msgApp" class="flex overflow-hidden rounded-2xl shadow-sm border border-slate-200 bg-white" id="msgWrap" style="height:calc(100vh - 112px)"
     data-api="<?= BASE_URL ?>/api/messages.php"
     data-csrf="<?= h($msgCsrf) ?>"
     data-me="<?= $myId ?>">

    <!-- ╔══════════════════════════════╗
         ║  LEFT — conversation list   ║
         ╚══════════════════════════════╝ -->
    <div id="convPane" class="w-72 xl:w-80 flex-shrink-0 flex flex-col border-r border-slate-100 bg-slate-50/60">

        <!-- Header -->
        <div class="flex items-center justify-between px-4 pt-4 pb-3 border-b border-slate-100 bg-white">
            <span class="font-bold text-slate-800 text-sm tracking-tight flex items-center gap-2">
                <i class="bi bi-chat-dots-fill text-blue-600"></i> Messages
            </span>
            <button id="composeBtn"
                    title="New message"
                    class="w-8 h-8 bg-blue-600 hover:bg-blue-700 active:scale-95 text-white rounded-lg
                           grid place-items-center transition shadow-sm">
                <i class="bi bi-pencil-fill text-xs leading-none"></i>
            </button>
        </div>

        <!-- Search -->
        <div class="px-3 py-2.5 border-b border-slate-100 bg-white">
            <div class="flex items-center gap-2 bg-slate-100 rounded-xl px-3 py-2">
                <i class="bi bi-search text-slate-400 text-xs shrink-0"></i>
                <input id="convSearch" type="search" placeholder="Search…"
                       class="bg-transparent flex-1 text-sm text-slate-700 focus:outline-none placeholder-slate-400 min-w-0">
            </div>
        </div>

        <!-- List -->
        <div id="convList" class="flex-1 overflow-y-auto">
            <div class="flex items-center justify-center py-10">
                <div class="w-5 h-5 rounded-full border-2 border-slate-200 border-t-blue-500 animate-spin"></div>
            </div>
        </div>
    </div>

    <!-- ╔══════════════════════════════════════════╗
         ║  RIGHT — thread / empty state           ║
         ╚══════════════════════════════════════════╝ -->
    <div id="rightPane" class="flex-1 flex flex-col overflow-hidden min-w-0 bg-slate-50">

        <!-- ── Empty state ──────────────────────────── -->
        <div id="emptyState" class="flex flex-col items-center justify-center h-full gap-5 text-center px-8">
            <div class="w-20 h-20 bg-blue-50 rounded-3xl grid place-items-center">
                <i class="bi bi-chat-heart-fill text-blue-400 text-4xl"></i>
            </div>
            <div>
                <p class="text-base font-bold text-slate-700">Your messages</p>
                <p class="text-sm text-slate-400 mt-1 max-w-xs">Select a conversation from the left, or write a new message to a colleague.</p>
            </div>
            <button id="emptyComposeBtn"
                    class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm
                           font-semibold px-5 py-2.5 rounded-xl transition shadow-sm active:scale-95">
                <i class="bi bi-pencil-fill text-xs"></i> Write a Message
            </button>
        </div>

        <!-- ── Thread view ──────────────────────────── -->
        <div id="threadView" class="hidden flex-col h-full overflow-hidden">

            <!-- Thread top bar -->
            <div id="threadHeader" class="shrink-0 flex items-center gap-3 px-5 py-3 bg-white border-b border-slate-100 shadow-sm">
                <!-- filled by JS -->
            </div>

            <!-- Bubbles -->
            <div id="threadMessages" class="flex-1 overflow-y-auto px-5 py-5 space-y-3">
            </div>

            <!-- Reply bar -->
            <div class="shrink-0 bg-white border-t border-slate-100 px-4 py-3">
                <div class="flex items-end gap-3 bg-slate-100 rounded-2xl px-4 py-2.5">
                    <label class="shrink-0 text-slate-400 hover:text-blue-600 cursor-pointer transition pb-0.5" title="Attach file">
                        <i class="bi bi-paperclip text-lg leading-none"></i>
                        <input type="file" id="replyFileInput" class="hidden"
                               accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip">
                    </label>
                    <textarea id="replyBody" rows="1" placeholder="Write a reply… (Ctrl+Enter to send)"
                              class="reply-input flex-1 bg-transparent text-sm text-slate-800
                                     placeholder-slate-400 focus:outline-none leading-relaxed py-1"></textarea>
                    <button id="replyBtn"
                            class="shrink-0 w-9 h-9 bg-blue-600 hover:bg-blue-700 active:scale-95 text-white
                                   rounded-xl grid place-items-center transition shadow-sm pb-0.5">
                        <i class="bi bi-send-fill text-sm leading-none"></i>
                    </button>
                </div>
                <p id="replyFileName" class="text-[11px] text-blue-600 mt-1 ml-2 hidden truncate"></p>
            </div>
        </div>

    </div>
</div>

<!-- ╔══════════════════════════════════════════════════════╗
     ║  COMPOSE MODAL (overlay)                           ║
     ╚══════════════════════════════════════════════════════╝ -->
<div id="composeModal"
     class="hidden fixed inset-0 z-50 flex items-center justify-center p-4"
     aria-modal="true">
    <!-- Backdrop -->
    <div id="composeBackdrop" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm"></div>

    <!-- Panel -->
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg flex flex-col overflow-hidden">

        <!-- Modal header -->
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
            <h2 class="font-bold text-slate-800 flex items-center gap-2 text-base">
                <i class="bi bi-pencil-square text-blue-600"></i> New Message
            </h2>
            <button id="cancelComposeBtn"
                    class="w-8 h-8 rounded-xl hover:bg-slate-100 grid place-items-center text-slate-400 hover:text-slate-700 transition">
                <i class="bi bi-x-lg text-base leading-none"></i>
            </button>
        </div>

        <!-- Modal body -->
        <div class="px-6 py-5 space-y-4 overflow-y-auto" style="max-height:70vh">

            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wider">To</label>
                <select id="composeTo"
                        class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2.5 bg-white
                               focus:outline-none focus:ring-2 focus:ring-blue-300 transition">
                    <option value="all">📢 All Staff (Broadcast)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Subject</label>
                <input type="text" id="composeSubject" placeholder="What's this about?"
                       class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2.5
                              focus:outline-none focus:ring-2 focus:ring-blue-300 transition">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wider">Message</label>
                <textarea id="composeBody" placeholder="Write your message…" rows="6"
                          class="w-full text-sm border border-slate-200 rounded-xl px-3 py-2.5
                                 resize-none focus:outline-none focus:ring-2 focus:ring-blue-300 transition"></textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1.5 uppercase tracking-wider">
                    Attachment <span class="font-normal normal-case text-slate-400">(optional · max 25 MB)</span>
                </label>
                <label class="flex items-center gap-3 cursor-pointer border-2 border-dashed border-slate-200
                              hover:border-blue-400 rounded-xl px-4 py-3 transition group">
                    <i class="bi bi-paperclip text-slate-400 group-hover:text-blue-500 text-lg transition"></i>
                    <span id="composeFileName" class="text-sm text-slate-400 group-hover:text-blue-600 transition truncate">
                        Click to attach a file
                    </span>
                    <input type="file" id="composeFileInput" class="hidden"
                           accept="image/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.csv,.zip">
                </label>
            </div>
        </div>

        <!-- Modal footer -->
        <div class="flex items-center justify-end gap-3 px-6 py-4 border-t border-slate-100">
            <button id="cancelComposeBtn2"
                    class="text-sm font-semibold text-slate-600 px-4 py-2.5 rounded-xl hover:bg-slate-100 transition">
                Cancel
            </button>
            <button id="sendNewBtn"
                    class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 active:scale-95
                           text-white text-sm font-semibold px-6 py-2.5 rounded-xl transition shadow-sm">
                <i class="bi bi-send-fill text-xs"></i> Send
            </button>
        </div>
    </div>
</div>

<script>
(function () {
'use strict';

const app   = document.getElementById('msgApp');
const API   = app.dataset.api;
const CSRF  = app.dataset.csrf;
const MY_ID = parseInt(app.dataset.me, 10);

let currentRootId = null;
let allUsers      = [];
let allConvs      = [];

// DOM
const convList         = document.getElementById('convList');
const convSearch       = document.getElementById('convSearch');
const convPane         = document.getElementById('convPane');
const emptyState       = document.getElementById('emptyState');
const threadView       = document.getElementById('threadView');
const threadHeader     = document.getElementById('threadHeader');
const threadMessages   = document.getElementById('threadMessages');
const replyBody        = document.getElementById('replyBody');
const replyBtn         = document.getElementById('replyBtn');
const replyFileInput   = document.getElementById('replyFileInput');
const replyFileNameEl  = document.getElementById('replyFileName');
const composeModal     = document.getElementById('composeModal');
const composeBackdrop  = document.getElementById('composeBackdrop');
const composeTo        = document.getElementById('composeTo');
const composeSubject   = document.getElementById('composeSubject');
const composeBody      = document.getElementById('composeBody');
const composeFileInput = document.getElementById('composeFileInput');
const composeFileName  = document.getElementById('composeFileName');
const sendNewBtn       = document.getElementById('sendNewBtn');

// ── Utils ───────────────────────────────────────────────────────────────────
function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
                          .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function fmtTime(dtStr) {
    if (!dtStr) return '';
    const d = new Date(dtStr.replace(' ','T')+'Z'), now = new Date();
    const m = Math.floor((now-d)/60000), h = Math.floor(m/60), dy = Math.floor(h/24);
    if (m < 1)  return 'just now';
    if (m < 60) return m+'m ago';
    if (h < 24) return h+'h ago';
    if (dy < 7) return dy+'d ago';
    return d.toLocaleDateString(undefined,{month:'short',day:'numeric'});
}

function fmtFull(dtStr) {
    if (!dtStr) return '';
    return new Date(dtStr.replace(' ','T')+'Z')
        .toLocaleString(undefined,{month:'short',day:'numeric',hour:'numeric',minute:'2-digit'});
}

function fmtBytes(n) {
    n = parseInt(n,10);
    if (n<1024) return n+' B';
    if (n<1048576) return (n/1024).toFixed(1)+' KB';
    return (n/1048576).toFixed(1)+' MB';
}

function initials(name) {
    const p = String(name??'').split(' ').filter(Boolean);
    return p.length ? (p[0][0]+(p[1]?.[0]??'')).toUpperCase() : '?';
}

function avatarGradient(role) {
    return ({admin:'#1d4ed8,#1e40af', ma:'#059669,#047857', billing:'#d97706,#b45309'})[role] ?? '#475569,#334155';
}

function attIcon(mime) {
    if (mime.startsWith('image/'))    return 'bi-file-image-fill';
    if (mime==='application/pdf')     return 'bi-file-pdf-fill';
    if (mime.includes('word'))        return 'bi-file-word-fill';
    if (mime.includes('excel')||mime.includes('spreadsheet')) return 'bi-file-excel-fill';
    if (mime==='text/csv')            return 'bi-filetype-csv';
    if (mime==='text/plain')          return 'bi-file-text-fill';
    if (mime.includes('zip'))         return 'bi-file-zip-fill';
    return 'bi-file-earmark-fill';
}

function showView(name) {
    const isThread  = name==='thread';
    const isEmpty   = name==='empty';
    emptyState.classList.toggle('hidden', !isEmpty);
    emptyState.classList.toggle('flex',    isEmpty);
    threadView.classList.toggle('hidden',  !isThread);
    threadView.classList.toggle('flex',    isThread);
    if (window.innerWidth < 768) convPane.classList.toggle('hidden', !isEmpty);
}

function setBusy(btn, b) {
    btn.disabled = b;
    btn.classList.toggle('opacity-60', b);
    btn.classList.toggle('cursor-not-allowed', b);
}

// ── Compose modal ────────────────────────────────────────────────────────────
function openCompose() {
    composeModal.classList.remove('hidden');
    composeModal.classList.add('flex');
    setTimeout(() => composeSubject.focus(), 50);
}

function closeCompose() {
    composeModal.classList.add('hidden');
    composeModal.classList.remove('flex');
}

document.getElementById('composeBtn').addEventListener('click', openCompose);
document.getElementById('emptyComposeBtn').addEventListener('click', openCompose);
document.getElementById('cancelComposeBtn').addEventListener('click', closeCompose);
document.getElementById('cancelComposeBtn2').addEventListener('click', closeCompose);
composeBackdrop.addEventListener('click', closeCompose);

// ── Conversation list ─────────────────────────────────────────────────────────
async function loadConvs() {
    try {
        const res = await fetch(API+'?action=list');
        const d   = await res.json();
        allConvs  = d.ok ? (d.conversations||[]) : [];
        renderConvList(allConvs);
    } catch(e) { renderConvList([]); }
}

function renderConvList(convs) {
    if (!convs.length) {
        convList.innerHTML = `
            <div class="flex flex-col items-center justify-center py-16 px-6 text-center gap-3">
                <i class="bi bi-chat-dots text-4xl text-slate-300"></i>
                <p class="text-sm text-slate-400 font-medium">No conversations yet</p>
                <button onclick="document.getElementById('composeBtn').click()"
                        class="text-xs text-blue-600 hover:underline font-semibold">Start one →</button>
            </div>`;
        return;
    }
    convList.innerHTML = convs.map(c => convItemHtml(c)).join('');
    convList.querySelectorAll('.conv-item').forEach(el => {
        el.addEventListener('click', () => openThread(parseInt(el.dataset.rootId, 10)));
    });
}

function convItemHtml(c) {
    const isMine  = parseInt(c.from_user_id,10) === MY_ID;
    const peer    = isMine ? (c.to_name||'All Staff') : c.from_name;
    const unread  = parseInt(c.unread_count,10) > 0;
    const active  = currentRootId === parseInt(c.id,10);
    const subject = c.subject || '(no subject)';
    const snippet = (c.last_body||'').substring(0,60);
    const time    = fmtTime(c.last_activity||c.created_at);
    const [c1,c2] = avatarGradient(c.from_role||'').split(',');

    return `
    <div class="conv-item px-3 py-3 cursor-pointer flex items-start gap-3 ${active?'active':''} ${unread?'border-l-[3px] border-blue-500 pl-[9px]':'border-l-[3px] border-transparent'}"
         data-root-id="${esc(c.id)}">
        <div class="w-10 h-10 rounded-2xl grid place-items-center text-white text-xs font-bold shrink-0 shadow-sm"
             style="background:linear-gradient(135deg,${esc(c1)},${esc(c2)})">
            ${esc(initials(peer))}
        </div>
        <div class="flex-1 min-w-0">
            <div class="flex items-baseline justify-between gap-1">
                <span class="text-sm ${unread?'font-bold':'font-semibold'} text-slate-800 truncate leading-5">${esc(peer)}</span>
                <span class="text-[10px] text-slate-400 shrink-0">${esc(time)}</span>
            </div>
            <p class="text-xs ${unread?'font-semibold text-slate-700':'text-slate-500'} truncate mt-0.5">${esc(subject)}</p>
            ${snippet?`<p class="text-[11px] text-slate-400 truncate mt-0.5">${esc(snippet)}</p>`:''}
        </div>
        ${unread?`<span class="w-2 h-2 rounded-full bg-blue-500 shrink-0 mt-1.5"></span>`:''}
    </div>`;
}

// ── Thread ────────────────────────────────────────────────────────────────────
async function openThread(rootId) {
    currentRootId = rootId;
    showView('thread');
    threadMessages.innerHTML = '<div class="flex items-center justify-center py-10"><div class="w-5 h-5 rounded-full border-2 border-slate-200 border-t-blue-500 animate-spin"></div></div>';

    try {
        const res = await fetch(`${API}?action=thread&id=${rootId}`);
        const d   = await res.json();
        if (!d.ok) { threadMessages.innerHTML='<p class="text-center text-red-400 text-sm py-8">Failed to load.</p>'; return; }
        renderThreadHeader(d.root);
        renderMessages(d.messages);
    } catch(e) {
        threadMessages.innerHTML = '<p class="text-center text-red-400 text-sm py-8">Network error.</p>';
    }
    await loadConvs();
    document.querySelectorAll('.conv-item').forEach(el => {
        el.classList.toggle('active', parseInt(el.dataset.rootId,10)===currentRootId);
    });
}

function renderThreadHeader(root) {
    const back = window.innerWidth<768
        ? `<button id="backBtn" class="mr-2 w-8 h-8 rounded-xl hover:bg-slate-100 grid place-items-center text-slate-500 transition">
               <i class="bi bi-arrow-left text-base leading-none"></i>
           </button>` : '';

    threadHeader.innerHTML = `
        ${back}
        <div class="flex-1 min-w-0">
            <p class="font-bold text-slate-800 text-sm truncate">${esc(root.subject||'(no subject)')}</p>
        </div>
        <button class="delete-thread w-8 h-8 rounded-xl hover:bg-red-50 grid place-items-center text-slate-300 hover:text-red-500 transition"
                data-msg-id="${esc(root.id)}" title="Delete conversation">
            <i class="bi bi-trash3 text-sm leading-none pointer-events-none"></i>
        </button>`;

    const backEl = document.getElementById('backBtn');
    if (backEl) backEl.addEventListener('click', () => { currentRootId=null; showView('empty'); convPane.classList.remove('hidden'); });
}

function renderMessages(msgs) {
    if (!msgs.length) { threadMessages.innerHTML='<p class="text-center text-slate-400 text-sm py-8">No messages.</p>'; return; }
    threadMessages.innerHTML = msgs.map(m => bubbleHtml(m)).join('');
    threadMessages.scrollTop = threadMessages.scrollHeight;
}

function bubbleHtml(m) {
    const isMine = parseInt(m.from_user_id,10) === MY_ID;
    const [c1,c2] = avatarGradient(m.from_role||'').split(',');

    const attachments = (m.attachments||[]).map(a => {
        const chipCls = isMine ? 'att-chip att-chip-white' : 'att-chip att-chip-blue';
        return `<a href="${API}?action=download&id=${esc(a.id)}"
                   class="${chipCls}"
                   download="${esc(a.original_name)}">
                    <i class="bi ${esc(attIcon(a.mime_type))} text-sm shrink-0"></i>
                    <span class="max-w-[180px] truncate">${esc(a.original_name)}</span>
                    <span class="opacity-60 text-[10px]">${esc(fmtBytes(a.file_size))}</span>
                </a>`;
    }).join('');

    const delBtn = isMine
        ? `<button class="delete-msg opacity-0 group-hover:opacity-100 text-slate-300 hover:text-red-500 transition"
                   data-msg-id="${esc(m.id)}" title="Delete">
               <i class="bi bi-trash3 text-xs pointer-events-none"></i>
           </button>` : '';

    return `
    <div class="flex gap-3 items-end group ${isMine?'flex-row-reverse':''} max-w-[85%] ${isMine?'ml-auto':'mr-auto'}">
        <div class="w-7 h-7 rounded-full grid place-items-center text-white text-[10px] font-bold shrink-0 mb-0.5"
             style="background:linear-gradient(135deg,${esc(c1)},${esc(c2)})">
            ${esc(initials(m.from_name))}
        </div>
        <div class="${isMine?'items-end':'items-start'} flex flex-col gap-1 min-w-0">
            <div class="flex items-center gap-2 ${isMine?'flex-row-reverse':''}">
                <span class="text-[11px] font-semibold text-slate-500">${esc(isMine?'You':m.from_name)}</span>
                <span class="text-[10px] text-slate-400" title="${esc(fmtFull(m.created_at))}">${esc(fmtTime(m.created_at))}</span>
                ${delBtn}
            </div>
            <div class="${isMine?'bubble-me':'bubble-them'} px-4 py-2.5 text-sm leading-relaxed break-words max-w-full">
                ${esc(m.body).replace(/\n/g,'<br>')}
            </div>
            ${attachments?`<div class="flex flex-wrap gap-2 ${isMine?'justify-end':''}">${attachments}</div>`:''}
        </div>
    </div>`;
}

// ── Reply ──────────────────────────────────────────────────────────────────────
async function sendReply() {
    const body = replyBody.value.trim();
    if (!body && !replyFileInput.files[0]) { replyBody.focus(); return; }
    setBusy(replyBtn, true);
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('body', body||' ');
    fd.append('parent_id', currentRootId);
    if (replyFileInput.files[0]) fd.append('file', replyFileInput.files[0]);
    try {
        const res = await fetch(`${API}?action=send`, {method:'POST',body:fd});
        const d   = await res.json();
        if (d.ok) {
            replyBody.value = '';
            replyFileInput.value = '';
            replyFileNameEl.classList.add('hidden');
            replyFileNameEl.textContent = '';
            replyBody.style.height = '';
            await openThread(currentRootId);
        } else { alert(d.error||'Failed to send.'); }
    } catch(e) { alert('Network error.'); }
    finally { setBusy(replyBtn,false); }
}

// ── Send new ───────────────────────────────────────────────────────────────────
async function sendNew() {
    const body    = composeBody.value.trim();
    const subject = composeSubject.value.trim();
    const to      = composeTo.value;
    if (!subject) { composeSubject.focus(); return; }
    if (!body)    { composeBody.focus();    return; }
    setBusy(sendNewBtn, true);
    const fd = new FormData();
    fd.append('csrf_token', CSRF);
    fd.append('body', body);
    fd.append('subject', subject);
    fd.append('to', to);
    if (composeFileInput.files[0]) fd.append('file', composeFileInput.files[0]);
    try {
        const res = await fetch(`${API}?action=send`, {method:'POST',body:fd});
        const d   = await res.json();
        if (d.ok) {
            composeSubject.value=''; composeBody.value='';
            composeFileInput.value=''; composeFileName.textContent='Click to attach a file';
            closeCompose();
            await loadConvs();
            await openThread(d.message_id);
        } else { alert(d.error||'Failed to send.'); }
    } catch(e) { alert('Network error.'); }
    finally { setBusy(sendNewBtn,false); }
}

// ── Delete ─────────────────────────────────────────────────────────────────────
async function deleteMessage(msgId, isRoot=false) {
    if (!confirm(isRoot?'Delete this entire conversation?':'Delete this message?')) return;
    try {
        const res = await fetch(`${API}?action=delete`,{
            method:'POST', headers:{'Content-Type':'application/json'},
            body: JSON.stringify({csrf_token:CSRF, message_id:msgId})
        });
        const d = await res.json();
        if (d.ok) {
            if (isRoot || parseInt(msgId,10)===currentRootId) { currentRootId=null; showView('empty'); await loadConvs(); }
            else await openThread(currentRootId);
        } else { alert(d.error||'Could not delete.'); }
    } catch(e) { alert('Network error.'); }
}

// ── Users ──────────────────────────────────────────────────────────────────────
async function loadUsers() {
    try {
        const res = await fetch(`${API}?action=users`);
        const d   = await res.json();
        if (!d.ok) return;
        allUsers = d.users||[];
        allUsers.forEach(u => {
            const o = document.createElement('option');
            o.value = u.id;
            o.textContent = u.full_name + ' (' + u.role + ')';
            composeTo.appendChild(o);
        });
    } catch(e){}
}

// ── Auto-grow reply textarea ───────────────────────────────────────────────────
replyBody.addEventListener('input', () => {
    replyBody.style.height = 'auto';
    replyBody.style.height = Math.min(replyBody.scrollHeight, 120) + 'px';
});

// ── Events ─────────────────────────────────────────────────────────────────────
sendNewBtn.addEventListener('click', () => sendNew());
replyBtn.addEventListener('click',   () => sendReply());

composeBody.addEventListener('keydown', e => { if (e.key==='Enter'&&(e.ctrlKey||e.metaKey)) sendNew(); });
replyBody.addEventListener('keydown',   e => { if (e.key==='Enter'&&(e.ctrlKey||e.metaKey)) sendReply(); });

composeFileInput.addEventListener('change', () => {
    composeFileName.textContent = composeFileInput.files[0]?.name || 'Click to attach a file';
});
replyFileInput.addEventListener('change', () => {
    const f = replyFileInput.files[0];
    if (f) { replyFileNameEl.textContent = '📎 ' + f.name; replyFileNameEl.classList.remove('hidden'); }
    else   { replyFileNameEl.classList.add('hidden'); }
});

// Delegated: delete buttons in thread
threadHeader.addEventListener('click', e => {
    const btn = e.target.closest('.delete-thread');
    if (btn) deleteMessage(parseInt(btn.dataset.msgId,10), true);
});
threadMessages.addEventListener('click', e => {
    const btn = e.target.closest('.delete-msg');
    if (btn) deleteMessage(parseInt(btn.dataset.msgId,10), false);
});

convSearch.addEventListener('input', () => {
    const q = convSearch.value.toLowerCase().trim();
    if (!q) { renderConvList(allConvs); return; }
    renderConvList(allConvs.filter(c =>
        (c.subject||'').toLowerCase().includes(q) ||
        (c.from_name||'').toLowerCase().includes(q) ||
        (c.to_name||'').toLowerCase().includes(q) ||
        (c.last_body||'').toLowerCase().includes(q)
    ));
});

// ── Poll ───────────────────────────────────────────────────────────────────────
setInterval(async () => {
    await loadConvs();
    if (currentRootId) {
        try {
            const res = await fetch(`${API}?action=thread&id=${currentRootId}`);
            const d   = await res.json();
            if (d.ok) renderMessages(d.messages);
        } catch(e){}
    }
}, 30000);

// ── Init ───────────────────────────────────────────────────────────────────────
showView('empty');
loadConvs();
loadUsers();

})();
</script>

<?php endif; // $_msgReady ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
