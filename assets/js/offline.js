'use strict';
/* ─────────────────────────────────────────────────────────────────────────
 * PaperlessMD — Offline Queue Manager
 *
 * Responsibilities:
 *  1. Intercept #mainForm submits when navigator.onLine === false
 *  2. Serialize form data → IndexedDB (pd-offline / form_queue)
 *  3. On reconnect (or SW SYNC_FORMS message): fetch fresh CSRF → POST queue
 *  4. Drive all offline UI (banner, dot, badge, toasts)
 * ──────────────────────────────────────────────────────────────────────── */
(function () {

    /* ── IndexedDB ────────────────────────────────────────────────── */
    var DB_NAME    = 'pd-offline';
    var DB_VERSION = 1;
    var STORE_NAME = 'form_queue';

    function openDb() {
        return new Promise(function (resolve, reject) {
            var req = indexedDB.open(DB_NAME, DB_VERSION);
            req.onupgradeneeded = function (e) {
                var store = e.target.result.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
                store.createIndex('status', 'status', { unique: false });
            };
            req.onsuccess = function (e) { resolve(e.target.result); };
            req.onerror   = function (e) { reject(e.target.error); };
        });
    }

    function dbAdd(entry) {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var req = db.transaction(STORE_NAME, 'readwrite')
                            .objectStore(STORE_NAME)
                            .add(entry);
                req.onsuccess = function () { resolve(req.result); };
                req.onerror   = function () { reject(req.error); };
            });
        });
    }

    function dbGetPending() {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var req = db.transaction(STORE_NAME, 'readonly')
                            .objectStore(STORE_NAME)
                            .index('status')
                            .getAll('pending');
                req.onsuccess = function () { resolve(req.result); };
                req.onerror   = function () { reject(req.error); };
            });
        });
    }

    function dbUpdate(id, patch) {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var tx    = db.transaction(STORE_NAME, 'readwrite');
                var store = tx.objectStore(STORE_NAME);
                var getR  = store.get(id);
                getR.onsuccess = function () {
                    var updated = Object.assign({}, getR.result, patch);
                    var putR    = store.put(updated);
                    putR.onsuccess = function () { resolve(updated); };
                    putR.onerror   = function () { reject(putR.error); };
                };
                getR.onerror = function () { reject(getR.error); };
            });
        });
    }

    /* ── Form serialization ─────────────────────────────────────── */
    function serializeForm(form) {
        var data = {};
        var els  = form.elements;
        for (var i = 0; i < els.length; i++) {
            var el = els[i];
            if (!el.name || el.disabled || el.type === 'file') continue;
            if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) continue;
            if (el.type === 'checkbox') {
                /* multi-value checkbox → array */
                if (Object.prototype.hasOwnProperty.call(data, el.name)) {
                    data[el.name] = [].concat(data[el.name], el.value);
                } else {
                    data[el.name] = el.value;
                }
            } else {
                data[el.name] = el.value;
            }
        }
        return data;
    }

    /* ── Toast ──────────────────────────────────────────────────── */
    var TOAST_COLORS = {
        success: 'bg-emerald-600',
        error:   'bg-red-600',
        info:    'bg-blue-600',
        warning: 'bg-amber-500',
    };
    var TOAST_ICONS = {
        success: 'bi-check-circle-fill',
        error:   'bi-x-circle-fill',
        info:    'bi-info-circle-fill',
        warning: 'bi-exclamation-triangle-fill',
    };

    function showToast(message, type) {
        type = type || 'info';
        var div = document.createElement('div');
        div.className = [
            'fixed bottom-6 left-1/2 z-[9999] -translate-x-1/2',
            'px-5 py-3 rounded-2xl shadow-2xl text-white text-sm font-semibold',
            'flex items-center gap-2 transition-all duration-300',
            TOAST_COLORS[type] || TOAST_COLORS.info,
        ].join(' ');
        div.style.transform = 'translateX(-50%) translateY(0)';
        div.innerHTML = '<i class="bi ' + (TOAST_ICONS[type] || TOAST_ICONS.info) + '"></i><span>' + message + '</span>';
        document.body.appendChild(div);
        setTimeout(function () {
            div.style.opacity  = '0';
            div.style.transform = 'translateX(-50%) translateY(10px)';
            setTimeout(function () { div.remove(); }, 300);
        }, 3800);
    }

    /* ── Offline banner & status dot ────────────────────────────── */
    function setOfflineUI(isOffline) {
        var banner = document.getElementById('offlineBanner');
        if (banner) {
            if (isOffline) banner.classList.remove('hidden');
            else           banner.classList.add('hidden');
        }
        var dot = document.getElementById('onlineStatusDot');
        if (dot) {
            if (isOffline) {
                dot.className = 'w-2 h-2 rounded-full bg-amber-400 ring-2 ring-amber-400/30 animate-pulse';
                dot.title     = 'Offline';
            } else {
                dot.className = 'w-2 h-2 rounded-full bg-emerald-400 ring-2 ring-emerald-400/30';
                dot.title     = 'Online';
            }
        }
    }

    /* ── Pending badge ──────────────────────────────────────────── */
    function updateBadge(count) {
        var badge = document.getElementById('offlinePendingBadge');
        if (!badge) return;
        if (count > 0) {
            badge.textContent = count;
            badge.classList.remove('hidden');
        } else {
            badge.classList.add('hidden');
        }
        /* Also update sync button label */
        var syncBtn = document.getElementById('offlineSyncBtn');
        if (syncBtn) {
            if (count > 0 && navigator.onLine) {
                syncBtn.classList.remove('hidden');
            } else {
                syncBtn.classList.add('hidden');
            }
        }
    }

    function refreshBadge() {
        return dbGetPending().then(function (items) {
            updateBadge(items.length);
            return items.length;
        }).catch(function () {
            return 0;
        });
    }

    /* ── Sync queue ─────────────────────────────────────────────── */
    var _syncing = false;

    function syncQueue() {
        if (_syncing) return Promise.resolve();
        _syncing = true;

        return dbGetPending().then(function (pending) {
            if (pending.length === 0) { _syncing = false; return; }

            /* Fetch a fresh CSRF token */
            return fetch(window._pdBase + '/api/csrf_token.php', {
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                var csrf = j.csrf || null;
                if (!csrf) {
                    showToast('Session expired — please sign in again.', 'error');
                    _syncing = false;
                    return;
                }

                /* Process each pending entry sequentially */
                return pending.reduce(function (chain, item) {
                    return chain.then(function (counts) {
                        return dbUpdate(item.id, { status: 'syncing' }).then(function () {
                            var body = new FormData();
                            body.append('csrf_token', csrf);
                            var fields = item.formData || {};
                            Object.keys(fields).forEach(function (k) {
                                if (k === 'csrf_token') return;
                                var v = fields[k];
                                if (Array.isArray(v)) {
                                    v.forEach(function (val) { body.append(k + '[]', val); });
                                } else {
                                    body.append(k, v);
                                }
                            });

                            return fetch(window._pdBase + '/api/save_form.php', {
                                method:      'POST',
                                body:        body,
                                credentials: 'same-origin',
                                redirect:    'manual',
                            })
                            .then(function (res) {
                                /* save_form.php returns a redirect on success */
                                if (res.ok || res.type === 'opaqueredirect' || res.status === 0) {
                                    return dbUpdate(item.id, { status: 'synced', syncedAt: Date.now() })
                                        .then(function () { counts.ok++; return counts; });
                                } else {
                                    return dbUpdate(item.id, { status: 'error', errorStatus: res.status })
                                        .then(function () { counts.fail++; return counts; });
                                }
                            })
                            .catch(function () {
                                return dbUpdate(item.id, { status: 'error' })
                                    .then(function () { counts.fail++; return counts; });
                            });
                        });
                    });
                }, Promise.resolve({ ok: 0, fail: 0 }))
                .then(function (counts) {
                    if (counts.ok > 0) {
                        showToast(counts.ok + ' form' + (counts.ok > 1 ? 's' : '') + ' synced!', 'success');
                        window.dispatchEvent(new Event('pd:synced'));
                    }
                    if (counts.fail > 0) {
                        showToast(counts.fail + ' form' + (counts.fail > 1 ? 's' : '') + ' failed to sync.', 'error');
                    }
                    _syncing = false;
                    return refreshBadge();
                });
            })
            .catch(function () {
                showToast('Sync failed — check your connection.', 'error');
                _syncing = false;
            });
        });
    }

    /* ── Intercept #mainForm when offline ────────────────────────── */
    function wireFormIntercept() {
        var form = document.getElementById('mainForm');
        if (!form) return;

        form.addEventListener('submit', function (e) {
            if (navigator.onLine) return; /* let it submit normally */
            e.preventDefault();
            e.stopImmediatePropagation();

            var data     = serializeForm(form);
            var formType = data.form_type || 'form';
            var patId    = data.patient_id || '';

            dbAdd({
                formData:   data,
                formType:   formType,
                patientId:  patId,
                status:     'pending',
                timestamp:  Date.now(),
            }).then(function () {
                return refreshBadge();
            }).then(function (count) {
                showToast('Saved offline — ' + count + ' pending sync', 'warning');

                /* Disable submit to prevent re-queuing */
                var submitBtn = form.querySelector('[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="bi bi-cloud-slash-fill mr-2 opacity-70"></i>Saved Offline';
                }
            });
        });
    }

    /* ── Service worker + background sync registration ──────────── */
    function registerSW() {
        if (!('serviceWorker' in navigator)) return;

        navigator.serviceWorker.register(window._pdBase + '/sw.js', { scope: '/pd/' })
            .then(function (reg) {
                console.log('[PWA] Service worker registered, scope:', reg.scope);

                /* Wire SW message → sync */
                navigator.serviceWorker.addEventListener('message', function (e) {
                    if (e.data && e.data.type === 'SYNC_FORMS') {
                        syncQueue();
                    }
                });
            })
            .catch(function (err) {
                console.warn('[PWA] SW registration failed:', err);
            });
    }

    /* ── Manual sync button ─────────────────────────────────────── */
    function wireSyncButton() {
        var btn = document.getElementById('offlineSyncBtn');
        if (!btn) return;
        btn.addEventListener('click', function () {
            if (!navigator.onLine) {
                showToast('Still offline — please wait for connection.', 'error');
                return;
            }
            btn.disabled    = true;
            btn.innerHTML   = '<i class="bi bi-arrow-repeat mr-1"></i>Syncing…';
            syncQueue().then(function () {
                btn.disabled  = false;
                btn.innerHTML = '<i class="bi bi-cloud-upload-fill mr-1"></i>Sync Now';
            });
        });
    }

    /* ── Init ───────────────────────────────────────────────────── */
    function init() {
        setOfflineUI(!navigator.onLine);
        refreshBadge();
        wireFormIntercept();
        wireSyncButton();
        registerSW();

        window.addEventListener('online', function () {
            setOfflineUI(false);
            showToast('Back online — syncing…', 'info');
            syncQueue();
            refreshBadge();
        });

        window.addEventListener('offline', function () {
            setOfflineUI(true);
            showToast("You're offline — forms will save automatically", 'warning');
            refreshBadge();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
