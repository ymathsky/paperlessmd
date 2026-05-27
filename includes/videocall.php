<?php
// Global video call UI + WebRTC engine — included by footer.php on every page.
// Only rendered for logged-in users.
if (empty($_SESSION['user_id'])) return;
?>
<style>
@keyframes pulse-ring {
    0%   { transform: scale(1);   opacity: .7; }
    70%  { transform: scale(1.55); opacity: 0; }
    100% { transform: scale(1.55); opacity: 0; }
}
.call-pulse::before,
.call-pulse::after {
    content: '';
    position: absolute;
    inset: -6px;
    border-radius: 50%;
    background: rgba(52,211,153,.4);
    animation: pulse-ring 1.8s ease-out infinite;
}
.call-pulse::after { animation-delay: .9s; }
#vcControls { transition: opacity .3s, transform .3s; }
#vcControls.hidden-controls { opacity: 0; pointer-events: none; transform: translateY(20px); }
#localVideo { cursor: move; user-select: none; }
#videoCallModal { z-index: 9999 !important; }
#incomingCallBar { z-index: 9998 !important; }
</style>

<!-- Incoming Call Notification -->
<div id="incomingCallBar" class="hidden fixed no-print"
     style="top:24px;left:50%;transform:translateX(-50%);width:360px;max-width:calc(100vw - 32px);filter:drop-shadow(0 8px 32px rgba(0,0,0,.35));z-index:9998">
    <div class="bg-white/95 backdrop-blur rounded-2xl overflow-hidden border border-white/60">
        <!-- Green top bar -->
        <div class="bg-gradient-to-r from-emerald-500 to-teal-500 px-4 py-2 flex items-center gap-2">
            <i class="bi bi-camera-video-fill text-white text-sm"></i>
            <span class="text-white text-xs font-semibold tracking-wide uppercase">Incoming Video Call</span>
        </div>
        <div class="px-4 py-4 flex items-center gap-4">
            <div class="relative shrink-0">
                <div class="call-pulse w-14 h-14 rounded-full bg-emerald-100 flex items-center justify-center font-bold text-emerald-700 text-xl relative" id="callerAvatar">?</div>
            </div>
            <div class="flex-1 min-w-0">
                <div class="font-bold text-slate-800 text-base truncate" id="callerName">Incoming call</div>
                <div class="text-xs text-slate-500 mt-0.5">Wants to start a video call</div>
            </div>
        </div>
        <div class="px-4 pb-4 flex gap-3">
            <button onclick="rejectCall()" class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-xl bg-red-50 hover:bg-red-100 text-red-600 font-semibold text-sm transition">
                <i class="bi bi-telephone-x-fill"></i> Decline
            </button>
            <button onclick="acceptCall()" class="flex-1 flex items-center justify-center gap-2 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm transition shadow">
                <i class="bi bi-camera-video-fill"></i> Accept
            </button>
        </div>
    </div>
</div>

<!-- Active Video Call -->
<div id="videoCallModal" class="hidden fixed inset-0 no-print bg-black flex flex-col" style="z-index:9999">

    <!-- Remote video fills full screen -->
    <div class="flex-1 relative overflow-hidden bg-[#0d1117]">
        <video id="remoteVideo" autoplay playsinline class="absolute inset-0 w-full h-full object-cover"></video>

        <!-- Subtle vignette -->
        <div class="absolute inset-0 pointer-events-none" style="background:radial-gradient(ellipse at center,transparent 40%,rgba(0,0,0,.55) 100%)"></div>

        <!-- Connecting overlay -->
        <div id="callOverlay" class="absolute inset-0 flex flex-col items-center justify-center"
             style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%)">
            <div class="relative mb-8">
                <div class="absolute inset-0 rounded-full animate-ping opacity-20 bg-emerald-400" style="animation-duration:1.8s"></div>
                <div class="absolute inset-0 rounded-full animate-ping opacity-10 bg-emerald-300" style="animation-duration:2.4s; animation-delay:.6s"></div>
                <div class="w-28 h-28 rounded-full bg-gradient-to-br from-slate-600 to-slate-700 flex items-center justify-center text-4xl font-bold text-white border-2 border-white/10 shadow-2xl" id="callRemoteAvatar">?</div>
            </div>
            <div class="text-white text-2xl font-bold mb-2" id="callRemoteName">Calling...</div>
            <div class="flex items-center gap-2 text-emerald-400 text-sm">
                <span class="flex gap-1">
                    <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-bounce" style="animation-delay:0s"></span>
                    <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-bounce" style="animation-delay:.15s"></span>
                    <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full animate-bounce" style="animation-delay:.3s"></span>
                </span>
                <span id="callStatusText">Connecting</span>
            </div>
        </div>

        <!-- Top bar (peer name + timer) -->
        <div id="vcTopBar" class="absolute top-0 inset-x-0 px-5 pt-5 pb-10 flex items-start justify-between pointer-events-none"
             style="background:linear-gradient(to bottom,rgba(0,0,0,.7) 0%,transparent 100%)">
            <div>
                <div class="text-white font-semibold text-base" id="vcPeerName"></div>
                <div class="text-white/60 text-xs" id="vcCallTimer">0:00</div>
            </div>
            <div class="flex items-center gap-1.5 bg-black/30 backdrop-blur rounded-full px-3 py-1 pointer-events-auto">
                <span class="w-2 h-2 bg-emerald-400 rounded-full animate-pulse"></span>
                <span class="text-white/80 text-xs font-medium">Encrypted</span>
            </div>
        </div>

        <!-- Local PiP -->
        <video id="localVideo" autoplay playsinline muted
               class="absolute bottom-28 right-4 w-36 h-28 object-cover rounded-2xl shadow-2xl border-2 border-white/20"
               style="transition:width .2s,height .2s"></video>
        <div class="absolute bottom-28 right-4 w-36 flex justify-center pointer-events-none" style="bottom:calc(7rem + 108px)">
            <span class="text-white/50 text-[10px] bg-black/40 rounded px-1">You</span>
        </div>
    </div>

    <!-- Controls bar -->
    <div id="vcControls" class="absolute bottom-0 inset-x-0 pb-8 pt-16 flex flex-col items-center gap-3"
         style="background:linear-gradient(to top,rgba(0,0,0,.85) 0%,transparent 100%)">
        <div id="vcStatusChip" class="hidden bg-white/10 backdrop-blur text-white/70 text-xs rounded-full px-3 py-1 mb-1"></div>
        <div class="flex items-end gap-4">
            <div class="flex flex-col items-center gap-1">
                <button id="muteBtn" onclick="toggleMute()"
                        class="w-14 h-14 bg-white/15 hover:bg-white/25 backdrop-blur text-white rounded-full flex items-center justify-center transition-all text-lg border border-white/10">
                    <i class="bi bi-mic-fill"></i>
                </button>
                <span class="text-white/50 text-[10px]">Mute</span>
            </div>
            <div class="flex flex-col items-center gap-1">
                <button onclick="endCall()"
                        class="w-20 h-20 bg-red-600 hover:bg-red-500 text-white rounded-full flex items-center justify-center transition-all shadow-2xl border-4 border-red-800/40"
                        style="box-shadow:0 0 0 8px rgba(239,68,68,.15)">
                    <i class="bi bi-telephone-x-fill text-2xl"></i>
                </button>
                <span class="text-white/50 text-[10px]">End</span>
            </div>
            <div class="flex flex-col items-center gap-1">
                <button id="camBtn" onclick="toggleCam()"
                        class="w-14 h-14 bg-white/15 hover:bg-white/25 backdrop-blur text-white rounded-full flex items-center justify-center transition-all text-lg border border-white/10">
                    <i class="bi bi-camera-video-fill"></i>
                </button>
                <span class="text-white/50 text-[10px]">Camera</span>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    const BASE = '<?= BASE_URL ?>';

    const RTC_CONFIG = {
        iceServers: [
            { urls: 'stun:134.209.113.86:3478' },
            { urls: ['turn:134.209.113.86:3478', 'turns:ecpaperlessmd.com:5349'],
              username: 'paperlessmd', credential: 'PmdTurn2024' }
        ]
    };

    let pc = null;
    let localStream = null;
    let callPeerId = null;
    let callPollTimer = null;
    let iceCandidateQueue = [];
    let isMuted = false;
    let isCamOff = false;
    let incomingOffer = null;
    let incomingPollTimer = null;
    let callTimerInterval = null;
    let callStartTime = null;

    // ── Media helpers ──────────────────────────────────────────────────────
    async function requestMedia() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showMediaError('Your browser does not support camera/microphone access. Please use Chrome or Safari on HTTPS.');
            return null;
        }
        try {
            return await navigator.mediaDevices.getUserMedia({video:true, audio:true});
        } catch(e) {
            if (e.name === 'NotAllowedError' || e.name === 'PermissionDeniedError') {
                showMediaError('Camera & microphone access was blocked.<br><br>To fix this:<br>1. Click the <b>camera icon</b> in your browser\'s address bar<br>2. Set Camera and Microphone to <b>Allow</b><br>3. Reload the page and try again');
            } else if (e.name === 'NotFoundError') {
                showMediaError('No camera or microphone found. Please connect a camera/mic and try again.');
            } else if (e.name === 'NotReadableError') {
                showMediaError('Camera or microphone is already in use by another application. Close it and try again.');
            } else {
                showMediaError('Could not access camera/microphone: ' + e.message);
            }
            return null;
        }
    }

    function showMediaError(msg) {
        const d = document.createElement('div');
        d.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,.6);display:flex;align-items:center;justify-content:center;padding:16px';
        d.innerHTML = `<div style="background:#fff;border-radius:16px;padding:28px 24px;max-width:380px;width:100%;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.4)">
            <div style="font-size:2.5rem;margin-bottom:12px">🎥</div>
            <div style="font-weight:700;font-size:1.05rem;color:#1e293b;margin-bottom:10px">Camera / Microphone Needed</div>
            <div style="font-size:.9rem;color:#475569;line-height:1.6">${msg}</div>
            <button onclick="this.closest('div[style]').remove()" style="margin-top:20px;padding:10px 28px;background:#2563eb;color:#fff;border:none;border-radius:10px;font-size:.95rem;font-weight:600;cursor:pointer">OK</button>
        </div>`;
        document.body.appendChild(d);
    }

    // ── Button state helpers ───────────────────────────────────────────────
    function updateMuteBtn() {
        const btn = document.getElementById('muteBtn');
        if (!btn) return;
        const lbl = btn.nextElementSibling;
        btn.innerHTML = isMuted ? '<i class="bi bi-mic-mute-fill"></i>' : '<i class="bi bi-mic-fill"></i>';
        btn.classList.toggle('bg-red-600/70', isMuted);
        btn.classList.toggle('border-red-500/50', isMuted);
        btn.classList.toggle('bg-white/15', !isMuted);
        btn.classList.toggle('border-white/10', !isMuted);
        if (lbl) lbl.textContent = isMuted ? 'Unmute' : 'Mute';
    }
    function updateCamBtn() {
        const btn = document.getElementById('camBtn');
        if (!btn) return;
        const lbl = btn.nextElementSibling;
        btn.innerHTML = isCamOff ? '<i class="bi bi-camera-video-off-fill"></i>' : '<i class="bi bi-camera-video-fill"></i>';
        btn.classList.toggle('bg-red-600/70', isCamOff);
        btn.classList.toggle('border-red-500/50', isCamOff);
        btn.classList.toggle('bg-white/15', !isCamOff);
        btn.classList.toggle('border-white/10', !isCamOff);
        if (lbl) lbl.textContent = isCamOff ? 'Show' : 'Camera';
    }
    window.toggleMute = function() {
        if (!localStream) return;
        isMuted = !isMuted;
        localStream.getAudioTracks().forEach(t => t.enabled = !isMuted);
        updateMuteBtn();
    };
    window.toggleCam = function() {
        if (!localStream) return;
        isCamOff = !isCamOff;
        localStream.getVideoTracks().forEach(t => t.enabled = !isCamOff);
        updateCamBtn();
    };

    // ── Signaling ──────────────────────────────────────────────────────────
    async function sendSignal(type, payload, toId) {
        await fetch(BASE + '/api/messages.php', {
            method: 'POST',
            body: new URLSearchParams({action:'call_signal', to:toId, signal_type:type, payload:JSON.stringify(payload)})
        });
    }

    async function logCall(body) {
        if (!callPeerId) return;
        await fetch(BASE + '/api/messages.php', {
            method: 'POST',
            body: new URLSearchParams({action:'call_log', to:callPeerId, body})
        }).catch(()=>{});
    }

    // ── PeerConnection ─────────────────────────────────────────────────────
    function buildPeerConnection() {
        if (pc) { pc.close(); pc = null; }
        iceCandidateQueue = [];
        pc = new RTCPeerConnection(RTC_CONFIG);

        pc.ontrack = e => {
            const remoteVid = document.getElementById('remoteVideo');
            if (e.streams && e.streams[0]) {
                remoteVid.srcObject = e.streams[0];
            } else {
                if (!remoteVid.srcObject) remoteVid.srcObject = new MediaStream();
                remoteVid.srcObject.addTrack(e.track);
            }
            remoteVid.play().catch(() => {});
        };

        pc.onicecandidate = async e => {
            if (e.candidate && callPeerId) {
                await sendSignal('ice', e.candidate, callPeerId);
            }
        };

        pc.onconnectionstatechange = () => {
            const s = pc.connectionState;
            const statusEl = document.getElementById('callStatusText');
            if (statusEl) statusEl.textContent = s.charAt(0).toUpperCase() + s.slice(1);
            if (s === 'connected') {
                const overlay = document.getElementById('callOverlay');
                if (overlay) overlay.style.display = 'none';
                const chip = document.getElementById('vcStatusChip');
                if (chip) chip.style.display = 'none';
                callStartTime = callStartTime || Date.now();
                try {
                    sessionStorage.setItem('vcReconnect', JSON.stringify({
                        peerId: callPeerId,
                        peerName: document.getElementById('callRemoteName').textContent,
                        peerInitials: document.getElementById('callRemoteAvatar').textContent
                    }));
                } catch(e) {}
            }
            if (s === 'failed') endCall();
        };

        return pc;
    }

    async function addQueuedIce() {
        for (const c of iceCandidateQueue) {
            try { await pc.addIceCandidate(c); } catch(e){}
        }
        iceCandidateQueue = [];
    }

    function showCallModal(peerName, peerInitials) {
        document.getElementById('callRemoteName').textContent = peerName;
        document.getElementById('callRemoteAvatar').textContent = peerInitials;
        document.getElementById('vcPeerName').textContent = peerName;
        document.getElementById('vcCallTimer').textContent = '0:00';
        document.getElementById('callOverlay').style.display = '';
        document.getElementById('callStatusText').textContent = 'Connecting';
        document.getElementById('videoCallModal').classList.remove('hidden');
        isMuted = false; isCamOff = false;
        updateMuteBtn(); updateCamBtn();
        callStartTime = null;
        if (callTimerInterval) clearInterval(callTimerInterval);
        callTimerInterval = setInterval(() => {
            if (!callStartTime) return;
            const s = Math.floor((Date.now() - callStartTime) / 1000);
            const m = Math.floor(s / 60), sec = s % 60;
            document.getElementById('vcCallTimer').textContent = m + ':' + String(sec).padStart(2,'0');
        }, 1000);
        let hideTimer;
        const modal = document.getElementById('videoCallModal');
        const controls = document.getElementById('vcControls');
        function resetHide() {
            controls.classList.remove('hidden-controls');
            clearTimeout(hideTimer);
            hideTimer = setTimeout(() => controls.classList.add('hidden-controls'), 4000);
        }
        modal.onmousemove = modal.ontouchstart = resetHide;
        resetHide();
    }

    // ── Caller side ────────────────────────────────────────────────────────
    // messages.php exposes window.vcActiveChatId + window.vcActivePeerName before calling this
    window.startVideoCall = async function() {
        const chatId   = window.vcActiveChatId;
        const peerName = window.vcActivePeerName || 'Unknown';
        if (!chatId || chatId === 'all') return;
        callPeerId = parseInt(chatId);
        const peerInitials = peerName.substring(0,2).toUpperCase();

        localStream = await requestMedia();
        if (!localStream) return;
        document.getElementById('localVideo').srcObject = localStream;

        buildPeerConnection();
        localStream.getTracks().forEach(t => pc.addTrack(t, localStream));

        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);
        await sendSignal('offer', offer, callPeerId);

        logCall('\uD83D\uDCF9 Video call started');

        showCallModal(peerName, peerInitials);

        callPollTimer = setInterval(async () => {
            const res = await fetch(BASE + `/api/messages.php?action=call_poll&with=${callPeerId}`);
            const data = await res.json();
            if (!data.ok) return;
            for (const sig of data.signals) {
                if (sig.signal_type === 'answer' && pc && !pc.currentRemoteDescription) {
                    await pc.setRemoteDescription(JSON.parse(sig.payload));
                    await addQueuedIce();
                } else if (sig.signal_type === 'ice') {
                    const c = JSON.parse(sig.payload);
                    if (pc && pc.remoteDescription) await pc.addIceCandidate(c);
                    else iceCandidateQueue.push(c);
                } else if (sig.signal_type === 'offer') {
                    await handleReconnectOffer(JSON.parse(sig.payload));
                } else if (sig.signal_type === 'end') {
                    endCall();
                }
            }
        }, 1000);
    };

    // ── Callee side ────────────────────────────────────────────────────────
    window.acceptCall = async function() {
        if (!incomingOffer) return;
        callPeerId = parseInt(incomingOffer.from_user_id);
        const peerName = incomingOffer.from_name;
        const peerInitials = peerName.substring(0,2).toUpperCase();

        document.getElementById('incomingCallBar').classList.add('hidden');

        localStream = await requestMedia();
        if (!localStream) {
            document.getElementById('incomingCallBar').classList.remove('hidden');
            return;
        }
        document.getElementById('localVideo').srcObject = localStream;

        try {
            buildPeerConnection();
            localStream.getTracks().forEach(t => pc.addTrack(t, localStream));

            await pc.setRemoteDescription(JSON.parse(incomingOffer.payload));
            await addQueuedIce();

            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            await sendSignal('answer', answer, callPeerId);

            showCallModal(peerName, peerInitials);
            incomingOffer = null;
        } catch(e) {
            console.error('acceptCall error:', e);
            showMediaError('Failed to connect the call: ' + e.message + '<br>Please try again.');
            if (localStream) { localStream.getTracks().forEach(t => t.stop()); localStream = null; }
            if (pc) { pc.close(); pc = null; }
            callPeerId = null;
            document.getElementById('incomingCallBar').classList.add('hidden');
        }

        if (!callPeerId) return;

        callPollTimer = setInterval(async () => {
            const res = await fetch(BASE + `/api/messages.php?action=call_poll&with=${callPeerId}`);
            const data = await res.json();
            if (!data.ok) return;
            for (const sig of data.signals) {
                if (sig.signal_type === 'ice') {
                    const c = JSON.parse(sig.payload);
                    if (pc && pc.remoteDescription) await pc.addIceCandidate(c).catch(()=>{});
                    else iceCandidateQueue.push(c);
                } else if (sig.signal_type === 'offer') {
                    await handleReconnectOffer(JSON.parse(sig.payload));
                } else if (sig.signal_type === 'end') {
                    endCall();
                }
            }
        }, 1000);
    };

    window.rejectCall = async function() {
        if (incomingOffer) {
            await sendSignal('end', {}, parseInt(incomingOffer.from_user_id));
            incomingOffer = null;
        }
        document.getElementById('incomingCallBar').classList.add('hidden');
    };

    window.endCall = async function() {
        sessionStorage.removeItem('vcReconnect');
        if (callTimerInterval) { clearInterval(callTimerInterval); callTimerInterval = null; }
        if (callPeerId && callStartTime) {
            const secs = Math.floor((Date.now() - callStartTime) / 1000);
            const mm = Math.floor(secs / 60), ss = String(secs % 60).padStart(2, '0');
            logCall(`\uD83D\uDCF9 Video call ended \u00B7 ${mm}:${ss}`);
        } else if (callPeerId && !callStartTime) {
            logCall('\uD83D\uDCF9 Missed video call');
        }
        callStartTime = null;
        if (callPollTimer) { clearInterval(callPollTimer); callPollTimer = null; }
        if (callPeerId) { await sendSignal('end', {}, callPeerId).catch(()=>{}); }
        if (pc) { pc.close(); pc = null; }
        if (localStream) { localStream.getTracks().forEach(t => t.stop()); localStream = null; }
        callPeerId = null; incomingOffer = null;
        document.getElementById('videoCallModal').classList.add('hidden');
        const overlay = document.getElementById('callOverlay');
        if (overlay) overlay.style.display = '';
        document.getElementById('remoteVideo').srcObject = null;
        document.getElementById('localVideo').srcObject = null;
    };

    // ── Incoming poll (runs on every page, every 2 s) ──────────────────────
    function startIncomingPoll() {
        incomingPollTimer = setInterval(async () => {
            if (callPeerId) return; // already in a call
            try {
                const res = await fetch(BASE + '/api/messages.php?action=call_poll&incoming=1');
                const data = await res.json();
                if (!data.ok || !data.offer) return;
                incomingOffer = data.offer;
                document.getElementById('callerName').textContent = data.offer.from_name;
                document.getElementById('callerAvatar').textContent = data.offer.from_name.substring(0,2).toUpperCase();
                document.getElementById('incomingCallBar').classList.remove('hidden');
            } catch(e) {}
        }, 2000);
    }

    // ── Reconnect renegotiation ────────────────────────────────────────────
    async function handleReconnectOffer(offerDesc) {
        try {
            if (pc) { pc.close(); pc = null; }
            buildPeerConnection();
            if (localStream) localStream.getTracks().forEach(t => pc.addTrack(t, localStream));
            await pc.setRemoteDescription(offerDesc);
            await addQueuedIce();
            const answer = await pc.createAnswer();
            await pc.setLocalDescription(answer);
            await sendSignal('answer', answer, callPeerId);
            const statusEl = document.getElementById('callStatusText');
            if (statusEl) statusEl.textContent = 'Reconnecting...';
            const overlay = document.getElementById('callOverlay');
            if (overlay) overlay.style.display = '';
        } catch(e) { console.error('Reconnect renegotiate error:', e); }
    }

    async function reconnectCall(peerId, peerName, peerInitials) {
        if (callPeerId) return;
        callPeerId = peerId;
        localStream = await requestMedia();
        if (!localStream) { callPeerId = null; return; }
        document.getElementById('localVideo').srcObject = localStream;
        buildPeerConnection();
        localStream.getTracks().forEach(t => pc.addTrack(t, localStream));
        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);
        await sendSignal('offer', {type: offer.type, sdp: offer.sdp}, peerId);
        showCallModal(peerName, peerInitials);
        const statusEl = document.getElementById('callStatusText');
        if (statusEl) statusEl.textContent = 'Reconnecting...';
        callPollTimer = setInterval(async () => {
            const res = await fetch(BASE + `/api/messages.php?action=call_poll&with=${callPeerId}`);
            const data = await res.json();
            if (!data.ok) return;
            for (const sig of data.signals) {
                if (sig.signal_type === 'answer' && pc && !pc.currentRemoteDescription) {
                    await pc.setRemoteDescription(JSON.parse(sig.payload));
                    await addQueuedIce();
                } else if (sig.signal_type === 'ice') {
                    const c = JSON.parse(sig.payload);
                    if (pc && pc.remoteDescription) await pc.addIceCandidate(c).catch(()=>{});
                    else iceCandidateQueue.push(c);
                } else if (sig.signal_type === 'end') {
                    endCall();
                }
            }
        }, 1000);
    }

    // ── Auto-reconnect if page was reloaded during a call ─────────────────
    async function checkVcReconnect() {
        try {
            const saved = sessionStorage.getItem('vcReconnect');
            if (!saved) return;
            const {peerId, peerName, peerInitials} = JSON.parse(saved);
            sessionStorage.removeItem('vcReconnect');
            await new Promise(r => setTimeout(r, 2000));
            await reconnectCall(peerId, peerName, peerInitials);
        } catch(e) {}
    }

    document.addEventListener('DOMContentLoaded', () => {
        startIncomingPoll();
        checkVcReconnect();
    });
})();
</script>
