'use strict';
/* ─────────────────────────────────────────────────────────────────────────
 * PaperlessMD — Service Worker
 * Provides offline-first caching and triggers form queue sync on reconnect.
 * ──────────────────────────────────────────────────────────────────────── */

const STATIC_CACHE   = 'pd-static-v3';
const PAGES_CACHE    = 'pd-pages-v3';
const SYNC_TAG       = 'pd-form-sync';
const LOC_SYNC_TAG   = 'pd-location-sync';
const LOC_IDB_NAME   = 'pd-location-queue';
const LOC_IDB_STORE  = 'queue';

// Derive base path from sw.js location (/pd on local, '' on production)
const BASE = self.location.pathname.replace(/\/sw\.js$/, '');

const PRECACHE_URLS = [
    BASE + '/offline.html',
    BASE + '/assets/css/style.css',
    BASE + '/assets/css/tailwind.css',
    BASE + '/assets/js/app.js',
    BASE + '/assets/js/offline.js',
];

// ── Install: pre-cache critical shell (ignore individual failures) ────────
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
              .then(c => Promise.all(
                  PRECACHE_URLS.map(url => c.add(url).catch(() => { /* skip missing */ }))
              ))
              .then(() => self.skipWaiting())
    );
});

// ── Activate: purge old caches ────────────────────────────────────────────
self.addEventListener('activate', event => {
    const KEEP = [STATIC_CACHE, PAGES_CACHE];
    event.waitUntil(
        caches.keys()
              .then(keys => Promise.all(
                  keys.filter(k => !KEEP.includes(k)).map(k => caches.delete(k))
              ))
              .then(() => self.clients.claim())
    );
});

// ── Fetch ─────────────────────────────────────────────────────────────────
self.addEventListener('fetch', event => {
    const req = event.request;
    if (req.method !== 'GET') return;

    // Skip non-http(s) requests (chrome-extension://, etc.)
    if (!req.url.startsWith('http')) return;

    const url = new URL(req.url);

    // External CDN (Tailwind, fonts, Bootstrap Icons): stale-while-revalidate
    if (url.origin !== self.location.origin) {
        event.respondWith(cdnStrategy(req));
        return;
    }

    // HTML page navigations AND .php fetches: network-first, cache fallback → offline.html
    if (req.mode === 'navigate' || url.pathname.endsWith('.php')) {
        event.respondWith(pageStrategy(req));
        return;
    }

    // Local static assets (CSS / JS / images): cache-first
    event.respondWith(staticStrategy(req));
});

async function cdnStrategy(req) {
    const cache  = await caches.open(STATIC_CACHE);
    const cached = await cache.match(req);
    const online = fetch(req)
        .then(r => { if (r.ok) cache.put(req, r.clone()); return r; })
        .catch(() => null);
    return cached || await online || new Response('', { status: 504 });
}

async function pageStrategy(req) {
    const cache = await caches.open(PAGES_CACHE);
    try {
        const response = await fetch(req);
        // Only cache successful responses — never cache 4xx/5xx errors
        if (response.ok) cache.put(req, response.clone());
        return response;
    } catch {
        const cached   = await cache.match(req);
        if (cached) return cached;
        const fallback = await caches.match(BASE + '/offline.html');
        return fallback || new Response('Offline', {
            status: 503,
            headers: { 'Content-Type': 'text/plain' },
        });
    }
}

async function staticStrategy(req) {
    const cache  = await caches.open(STATIC_CACHE);
    const cached = await cache.match(req);
    if (cached) return cached;
    try {
        const response = await fetch(req);
        // Only cache successful responses — never persist error responses
        if (response.ok) cache.put(req, response.clone());
        return response;
    } catch {
        return new Response('', { status: 504, statusText: 'Gateway Timeout' });
    }
}

// ── Background Sync ───────────────────────────────────────────────────────
// pd-form-sync  → tell open windows to process their offline form queue
// pd-location-sync → SW flushes the location IDB queue directly (no page needed)
self.addEventListener('sync', event => {
    if (event.tag === SYNC_TAG) {
        event.waitUntil(notifyClients());
    }
    if (event.tag === LOC_SYNC_TAG) {
        event.waitUntil(flushLocationQueue());
    }
});

async function notifyClients() {
    const clients = await self.clients.matchAll({
        includeUncontrolled: true,
        type: 'window',
    });
    clients.forEach(c => c.postMessage({ type: 'SYNC_FORMS' }));
}

// ── Location queue helpers (IndexedDB) ───────────────────────────────────
function openLocDB() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(LOC_IDB_NAME, 1);
        req.onupgradeneeded = e => {
            e.target.result.createObjectStore(LOC_IDB_STORE, { keyPath: 'id', autoIncrement: true });
        };
        req.onsuccess = e => resolve(e.target.result);
        req.onerror   = e => reject(e.target.error);
    });
}
function locGetAll(db) {
    return new Promise((resolve, reject) => {
        const req = db.transaction(LOC_IDB_STORE, 'readonly').objectStore(LOC_IDB_STORE).getAll();
        req.onsuccess = e => resolve(e.target.result);
        req.onerror   = e => reject(e.target.error);
    });
}
function locDelete(db, id) {
    return new Promise((resolve, reject) => {
        const req = db.transaction(LOC_IDB_STORE, 'readwrite').objectStore(LOC_IDB_STORE).delete(id);
        req.onsuccess = () => resolve();
        req.onerror   = e => reject(e.target.error);
    });
}

async function flushLocationQueue() {
    const db      = await openLocDB();
    const pending = await locGetAll(db);
    const cutoff  = Date.now() - 3600 * 1000; // discard entries older than 1 h (CSRF expired)

    for (const item of pending) {
        if (item.ts < cutoff) {
            await locDelete(db, item.id);
            continue;
        }
        try {
            const res = await fetch(item.url, {
                method:    'POST',
                headers:   { 'Content-Type': 'application/json' },
                body:      JSON.stringify(item.payload),
                keepalive: true,
            });
            // 2xx = success; 4xx = bad request / CSRF expired — remove, no point retrying
            if (res.ok || (res.status >= 400 && res.status < 500)) {
                await locDelete(db, item.id);
            }
            // 5xx → leave in queue, SW will retry on next sync event
        } catch {
            break; // network error — stop processing; browser will fire sync again when online
        }
    }
}
