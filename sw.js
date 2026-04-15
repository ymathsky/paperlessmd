'use strict';
/* ─────────────────────────────────────────────────────────────────────────
 * PaperlessMD — Service Worker
 * Provides offline-first caching and triggers form queue sync on reconnect.
 * ──────────────────────────────────────────────────────────────────────── */

const STATIC_CACHE = 'pd-static-v1';
const PAGES_CACHE  = 'pd-pages-v1';
const SYNC_TAG     = 'pd-form-sync';

// Derive base path from sw.js location (/pd on local, '' on production)
const BASE = self.location.pathname.replace(/\/sw\.js$/, '');

const PRECACHE_URLS = [
    BASE + '/offline.html',
    BASE + '/assets/css/style.css',
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
    if (req.method !== 'GET') return; // POST handled by offline.js on the client

    const url = new URL(req.url);

    // External CDN (Tailwind, fonts, Bootstrap Icons): stale-while-revalidate
    if (url.origin !== self.location.origin) {
        event.respondWith(cdnStrategy(req));
        return;
    }

    // HTML page navigations: network-first, cache fallback → offline.html
    if (req.mode === 'navigate') {
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
        .then(r => { cache.put(req, r.clone()); return r; })
        .catch(() => null);
    return cached || await online || new Response('', { status: 504 });
}

async function pageStrategy(req) {
    const cache = await caches.open(PAGES_CACHE);
    try {
        const response = await fetch(req);
        cache.put(req, response.clone());
        return response;
    } catch {
        const cached   = await cache.match(req);
        if (cached) return cached;
        const fallback = await caches.match('/pd/offline.html');
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
        cache.put(req, response.clone());
        return response;
    } catch {
        return new Response('', { status: 503 });
    }
}

// ── Background Sync ───────────────────────────────────────────────────────
// When the browser reconnects and has a pending sync tag, tell the open
// window(s) to process their IndexedDB queue.
self.addEventListener('sync', event => {
    if (event.tag === SYNC_TAG) {
        event.waitUntil(notifyClients());
    }
});

async function notifyClients() {
    const clients = await self.clients.matchAll({
        includeUncontrolled: true,
        type: 'window',
    });
    clients.forEach(c => c.postMessage({ type: 'SYNC_FORMS' }));
}
