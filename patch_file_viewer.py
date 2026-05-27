path = '/var/www/paperlessmd/messages.php'
with open(path, 'r') as f:
    src = f.read()

# ── 1. Add modal HTML + CSS just before </style> ──────────────────────────
old_style_end = """#msgHistory { scroll-behavior: auto; overflow-y: auto; }
.unread-badge { min-width: 20px; height: 20px; padding: 0 6px; border-radius: 10px; font-size: 11px; display: inline-flex; align-items: center; justify-content: center; }
</style>"""

new_style_end = """#msgHistory { scroll-behavior: auto; overflow-y: auto; }
.unread-badge { min-width: 20px; height: 20px; padding: 0 6px; border-radius: 10px; font-size: 11px; display: inline-flex; align-items: center; justify-content: center; }
/* File viewer modal */
#fileViewerModal { display:none; position:fixed; inset:0; z-index:99999; background:rgba(0,0,0,.85); flex-direction:column; align-items:center; justify-content:center; }
#fileViewerModal.open { display:flex; }
#fileViewerModal .fv-bar { display:flex; align-items:center; gap:10px; width:100%; max-width:900px; padding:10px 14px; background:rgba(255,255,255,.07); border-radius:12px 12px 0 0; flex-shrink:0; }
#fileViewerModal .fv-name { flex:1; font-size:13px; font-weight:600; color:#e2e8f0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
#fileViewerModal .fv-btn { background:rgba(255,255,255,.12); border:none; color:#e2e8f0; border-radius:8px; padding:6px 12px; font-size:12px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:5px; text-decoration:none; transition:background .15s; }
#fileViewerModal .fv-btn:hover { background:rgba(255,255,255,.22); }
#fileViewerModal .fv-btn.close-btn { background:rgba(239,68,68,.25); }
#fileViewerModal .fv-btn.close-btn:hover { background:rgba(239,68,68,.5); }
#fileViewerModal .fv-body { flex:1; width:100%; max-width:900px; min-height:0; background:#1e293b; border-radius:0 0 12px 12px; overflow:hidden; display:flex; align-items:center; justify-content:center; }
#fileViewerModal .fv-body img { max-width:100%; max-height:100%; object-fit:contain; display:block; }
#fileViewerModal .fv-body iframe { width:100%; height:100%; border:none; background:#fff; }
#fileViewerModal .fv-body .fv-other { text-align:center; padding:40px 20px; color:#94a3b8; }
#fileViewerModal .fv-body .fv-other i { font-size:52px; display:block; margin-bottom:12px; color:#64748b; }
#fileViewerModal .fv-body .fv-other p { font-size:14px; margin-bottom:16px; }
@media (max-width:767px) {
    #fileViewerModal .fv-bar, #fileViewerModal .fv-body { max-width:100%; border-radius:0; }
    #fileViewerModal .fv-body { height:calc(100svh - 56px); }
}
</style>"""

assert old_style_end in src, 'style end not found'
src = src.replace(old_style_end, new_style_end, 1)
print('✓ Modal CSS added')

# ── 2. Add modal HTML just before closing </div><!-- /msgWrap --> ──────────
# The msgWrap closing tag — find the line after the right panel's last </div>
old_after_wrap = """</div>

<script>"""
new_after_wrap = """</div>

<!-- ■ File Viewer Modal ■ -->
<div id="fileViewerModal" role="dialog" aria-modal="true" aria-label="File Viewer">
    <div class="fv-bar">
        <i class="bi bi-file-earmark-fill" id="fvIcon" style="color:#94a3b8;font-size:16px;flex-shrink:0"></i>
        <span class="fv-name" id="fvName"></span>
        <a id="fvDownload" href="#" download class="fv-btn" title="Download">
            <i class="bi bi-download"></i> Download
        </a>
        <button class="fv-btn close-btn" onclick="closeFileViewer()" title="Close (Esc)">
            <i class="bi bi-x-lg"></i> Close
        </button>
    </div>
    <div class="fv-body" id="fvBody"></div>
</div>

<script>"""

assert old_after_wrap in src, 'msgWrap closing not found'
src = src.replace(old_after_wrap, new_after_wrap, 1)
print('✓ Modal HTML added')

# ── 3. Replace attachment chip rendering with smart viewer ─────────────────
old_att = """            if (m.attachments && m.attachments.length) {
                m.attachments.forEach(a => {
                    const dlLink = `<?= BASE_URL ?>/api/messages.php?action=download&file_id=${a.id}`;
                    attHtml += `<a href="${dlLink}" target="_blank" class="att-chip">
                        <i class="bi bi-file-earmark-arrow-down"></i> <span class="truncate max-w-[150px]" title="${a.original_name}">${a.original_name}</span>
                    </a>`;
                });
            }"""

new_att = """            if (m.attachments && m.attachments.length) {
                m.attachments.forEach(a => {
                    const dlLink = `<?= BASE_URL ?>/api/messages.php?action=download&file_id=${a.id}`;
                    const mime = (a.mime_type || '').toLowerCase();
                    const isImg = mime.startsWith('image/');
                    const isPdf = mime === 'application/pdf';
                    const canPreview = isImg || isPdf;
                    const icon = isImg ? 'bi-image' : isPdf ? 'bi-file-earmark-pdf' : 'bi-file-earmark-arrow-down';
                    const nameEsc = a.original_name.replace(/'/g, "\\\\'").replace(/"/g, '&quot;');
                    if (canPreview) {
                        attHtml += `<button type="button" onclick="openFileViewer('${dlLink}','${nameEsc}','${mime}')" class="att-chip" style="width:100%">
                            <i class="bi ${icon}"></i> <span class="truncate max-w-[150px]" title="${a.original_name}">${a.original_name}</span>
                            <i class="bi bi-eye-fill" style="margin-left:auto;opacity:.7;font-size:11px"></i>
                        </button>`;
                    } else {
                        attHtml += `<a href="${dlLink}" download class="att-chip">
                            <i class="bi ${icon}"></i> <span class="truncate max-w-[150px]" title="${a.original_name}">${a.original_name}</span>
                        </a>`;
                    }
                });
            }"""

assert old_att in src, 'attachment rendering not found'
src = src.replace(old_att, new_att, 1)
print('✓ Attachment chips updated with preview/download split')

# ── 4. Add openFileViewer / closeFileViewer JS before closing </script> ─────
old_script_end = """    // Poll every 3 seconds
    syncChat(); // Initial load
    setInterval(syncChat, 3000);
});
</script>"""

new_script_end = """    // Poll every 3 seconds
    syncChat(); // Initial load
    setInterval(syncChat, 3000);
});

// ── File Viewer ──────────────────────────────────────────────────────────
window.openFileViewer = function(url, name, mime) {
    const modal  = document.getElementById('fileViewerModal');
    const fvName = document.getElementById('fvName');
    const fvBody = document.getElementById('fvBody');
    const fvDl   = document.getElementById('fvDownload');
    const fvIcon = document.getElementById('fvIcon');

    fvName.textContent = name;
    fvDl.href = url;
    fvDl.download = name;

    const isImg = mime.startsWith('image/');
    const isPdf = mime === 'application/pdf';

    fvIcon.className = 'bi ' + (isImg ? 'bi-image' : isPdf ? 'bi-file-earmark-pdf-fill' : 'bi-file-earmark-fill');
    fvIcon.style.color = isImg ? '#60a5fa' : isPdf ? '#f87171' : '#94a3b8';

    fvBody.innerHTML = '';
    if (isImg) {
        const img = document.createElement('img');
        img.src = url;
        img.alt = name;
        fvBody.appendChild(img);
    } else if (isPdf) {
        const frame = document.createElement('iframe');
        frame.src = url;
        frame.title = name;
        fvBody.appendChild(frame);
    }

    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
};

window.closeFileViewer = function() {
    const modal = document.getElementById('fileViewerModal');
    modal.classList.remove('open');
    document.getElementById('fvBody').innerHTML = ''; // stop iframe/img loading
    document.body.style.overflow = '';
};

// Close on backdrop click
document.getElementById('fileViewerModal').addEventListener('click', function(e) {
    if (e.target === this) closeFileViewer();
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeFileViewer();
});
</script>"""

assert old_script_end in src, 'script end not found'
src = src.replace(old_script_end, new_script_end, 1)
print('✓ openFileViewer / closeFileViewer JS added')

with open(path, 'w') as f:
    f.write(src)
print('\n✅ Patched successfully')
