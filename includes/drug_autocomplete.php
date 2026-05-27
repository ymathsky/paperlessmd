<?php
/**
 * Drug Bank Autocomplete Component
 * Include once per page. Attaches to any input[name^="med_name_"] inside
 * .med-rows-tbody via event delegation — works for both PHP-rendered rows
 * and JS-dynamically added rows.
 *
 * Requires: BASE_URL constant, user logged in.
 */
?>
<style>
#drugSuggestBox {
    position: fixed;
    z-index: 9100;
    background: #fff;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,.13);
    max-height: 260px;
    overflow-y: auto;
    min-width: 220px;
    max-width: 380px;
    display: none;
    padding: 4px 0;
    font-size: 13px;
}
#drugSuggestBox .ds-item {
    display: flex;
    align-items: baseline;
    gap: 8px;
    padding: 8px 14px;
    cursor: pointer;
    transition: background .1s;
    border-radius: 0;
}
#drugSuggestBox .ds-item:first-child { border-radius: 10px 10px 0 0; }
#drugSuggestBox .ds-item:last-child  { border-radius: 0 0 10px 10px; }
#drugSuggestBox .ds-item:hover,
#drugSuggestBox .ds-item.ds-active {
    background: #eff6ff;
}
#drugSuggestBox .ds-item .ds-name {
    flex: 1;
    color: #1e293b;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
#drugSuggestBox .ds-item .ds-cat {
    font-size: 10px;
    color: #94a3b8;
    white-space: nowrap;
    flex-shrink: 0;
}
#drugSuggestBox .ds-item .ds-icon {
    color: #7c3aed;
    font-size: 12px;
    flex-shrink: 0;
}
#drugSuggestBox .ds-empty {
    padding: 10px 14px;
    color: #94a3b8;
    font-size: 12px;
    font-style: italic;
}
#drugSuggestBox .ds-header {
    padding: 6px 14px 4px;
    font-size: 10px;
    font-weight: 700;
    color: #7c3aed;
    text-transform: uppercase;
    letter-spacing: .06em;
    border-bottom: 1px solid #f1f5f9;
    margin-bottom: 2px;
}
</style>

<div id="drugSuggestBox" role="listbox" aria-label="Drug suggestions"></div>

<script>
(function () {
    var BASE   = window._pdBase || '';
    var box    = document.getElementById('drugSuggestBox');
    var active = -1;
    var items  = [];
    var currentInput = null;
    var debounceTimer = null;

    // ── Attach event delegation on any .med-rows-tbody ──────────────
    document.addEventListener('input', function (e) {
        var inp = e.target;
        if (!inp || inp.tagName !== 'INPUT') return;
        if (!/^med_name_/.test(inp.name || '')) return;
        currentInput = inp;
        clearTimeout(debounceTimer);
        var q = inp.value.trim();
        if (q.length < 2) { hideBox(); return; }
        debounceTimer = setTimeout(function () { fetchSuggestions(q); }, 220);
    });

    document.addEventListener('keydown', function (e) {
        if (box.style.display === 'none' || !currentInput) return;
        if (e.key === 'ArrowDown')  { e.preventDefault(); moveActive(1);  return; }
        if (e.key === 'ArrowUp')    { e.preventDefault(); moveActive(-1); return; }
        if (e.key === 'Enter' && active >= 0) { e.preventDefault(); selectItem(active); return; }
        if (e.key === 'Escape')     { hideBox(); return; }
    });

    document.addEventListener('click', function (e) {
        if (!box.contains(e.target) && e.target !== currentInput) hideBox();
    });

    function fetchSuggestions(q) {
        fetch(BASE + '/api/drug_search.php?q=' + encodeURIComponent(q))
            .then(function (r) { return r.ok ? r.json() : []; })
            .then(function (data) {
                items = data;
                active = -1;
                renderBox();
            })
            .catch(function () { hideBox(); });
    }

    function renderBox() {
        if (!currentInput) { hideBox(); return; }

        box.innerHTML = '';

        if (!items.length) {
            var empty = document.createElement('div');
            empty.className = 'ds-empty';
            empty.textContent = 'No matches — type freely';
            box.appendChild(empty);
        } else {
            var hdr = document.createElement('div');
            hdr.className = 'ds-header';
            hdr.innerHTML = '<i class="bi bi-capsule mr-1"></i> Drug Bank';
            box.appendChild(hdr);

            items.forEach(function (item, idx) {
                var el = document.createElement('div');
                el.className = 'ds-item';
                el.setAttribute('role', 'option');
                el.innerHTML =
                    '<i class="bi bi-plus-circle ds-icon"></i>' +
                    '<span class="ds-name">' + escHtml(item.name) + '</span>' +
                    (item.category ? '<span class="ds-cat">' + escHtml(item.category) + '</span>' : '');
                el.addEventListener('mousedown', function (e) {
                    e.preventDefault(); // prevent blur on input
                    selectItem(idx);
                });
                box.appendChild(el);
            });
        }

        positionBox();
        box.style.display = 'block';
    }

    function positionBox() {
        if (!currentInput) return;
        var rect = currentInput.getBoundingClientRect();
        var spaceBelow = window.innerHeight - rect.bottom;
        var boxH = Math.min(260, box.scrollHeight);
        if (spaceBelow >= boxH + 8 || spaceBelow > 80) {
            box.style.top  = (rect.bottom + window.scrollY + 4) + 'px';
        } else {
            box.style.top  = (rect.top + window.scrollY - boxH - 4) + 'px';
        }
        box.style.left  = Math.max(8, rect.left + window.scrollX) + 'px';
        box.style.width = Math.min(380, Math.max(220, rect.width)) + 'px';
        box.style.maxWidth = (window.innerWidth - 16) + 'px';
    }

    function selectItem(idx) {
        if (!currentInput || !items[idx]) return;
        currentInput.value = items[idx].name;
        currentInput.dispatchEvent(new Event('change', { bubbles: true }));
        hideBox();
        // Move focus to the frequency field in the same row
        var tr = currentInput.closest('tr');
        if (tr) {
            var freq = tr.querySelector('[name^="med_freq_"]');
            if (freq) freq.focus();
        }
    }

    function moveActive(dir) {
        var els = box.querySelectorAll('.ds-item');
        if (!els.length) return;
        active = Math.max(0, Math.min(els.length - 1, active + dir));
        els.forEach(function (el, i) {
            el.classList.toggle('ds-active', i === active);
        });
    }

    function hideBox() {
        box.style.display = 'none';
        active = -1;
        items  = [];
    }

    function escHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // Reposition on scroll/resize
    window.addEventListener('scroll', function () { if (box.style.display !== 'none') positionBox(); }, true);
    window.addEventListener('resize', function () { if (box.style.display !== 'none') positionBox(); });
}());
</script>
