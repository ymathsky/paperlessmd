<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/db.php';
requireAdmin();

// Allow geolocation on this page
header('Permissions-Policy: geolocation=(self)');

$pageTitle = 'MA Location Monitor';
$activeNav = 'ma_locations';

// Get all active MA/admin staff (for the sidebar list)
$staff = $pdo->query("
    SELECT id, full_name, role
    FROM staff
    WHERE active = 1 AND role IN ('ma','admin')
    ORDER BY full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<!-- Leaflet CSS + MarkerCluster -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<style>
    @keyframes pulse-dot { 0%,100%{opacity:1} 50%{opacity:.4} }
    .dot-loading { animation: pulse-dot 1.2s ease-in-out infinite; }
    .staff-row.row-active { background-color: #eff6ff !important; }
    html.dark .staff-row.row-active { background-color: #1e3a5f !important; }
    @keyframes spin { to { transform: rotate(360deg); } }
    .icon-spin { animation: spin 0.7s linear infinite; display:inline-block; }
    .leaflet-control-zoom { border:1px solid #e2e8f0 !important; border-radius:10px !important; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.08) !important; }
    .leaflet-control-zoom a { font-size:16px !important; font-weight:700 !important; color:#334155 !important; }
    #maMap { height:520px; width:100%; }
    @media (min-height:800px) { #maMap { height:620px; } }
    @media (min-height:1000px) { #maMap { height:720px; } }
    #staffList { max-height:440px; overflow-y:auto; }
</style>

<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
    <div>
        <h2 class="text-2xl font-extrabold text-slate-800 dark:text-slate-100">MA Location Monitor</h2>
        <p class="text-slate-500 text-sm mt-0.5">Real-time GPS locations of active MAs &mdash; auto-refresh every 60 s</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
        <span id="locLastUpdated" class="text-xs text-slate-400 italic">Loading&hellip;</span>
        <button id="locFitBtn"
                class="inline-flex items-center gap-2 px-3 py-2 bg-slate-100 hover:bg-slate-200
                       text-slate-600 font-semibold text-sm rounded-xl transition-all"
                title="Fit all markers in view">
            <i class="bi bi-fullscreen"></i>
        </button>
        <button id="locRefreshBtn"
                class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700
                       text-white font-semibold text-sm rounded-xl transition-all shadow-sm">
            <i id="refreshIcon" class="bi bi-arrow-clockwise"></i> Refresh
        </button>
    </div>
</div>

<!-- Stats summary bar -->
<div class="grid grid-cols-3 gap-3 mb-5">
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm px-3 sm:px-4 py-3 flex items-center gap-2 sm:gap-3">
        <span class="hidden sm:flex w-10 h-10 rounded-xl bg-emerald-50 items-center justify-center shrink-0">
            <i class="bi bi-wifi text-emerald-500 text-lg"></i>
        </span>
        <div>
            <p class="text-xs text-slate-400 font-medium">Online</p>
            <p class="text-xl sm:text-2xl font-extrabold text-emerald-600 leading-none mt-0.5" id="statOnline">&mdash;</p>
        </div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm px-3 sm:px-4 py-3 flex items-center gap-2 sm:gap-3">
        <span class="hidden sm:flex w-10 h-10 rounded-xl bg-amber-50 items-center justify-center shrink-0">
            <i class="bi bi-clock text-amber-500 text-lg"></i>
        </span>
        <div>
            <p class="text-xs text-slate-400 font-medium">Away</p>
            <p class="text-xl sm:text-2xl font-extrabold text-amber-500 leading-none mt-0.5" id="statAway">&mdash;</p>
        </div>
    </div>
    <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-100 dark:border-slate-700 shadow-sm px-3 sm:px-4 py-3 flex items-center gap-2 sm:gap-3">
        <span class="hidden sm:flex w-10 h-10 rounded-xl bg-slate-50 items-center justify-center shrink-0">
            <i class="bi bi-wifi-off text-slate-400 text-lg"></i>
        </span>
        <div>
            <p class="text-xs text-slate-400 font-medium">Offline</p>
            <p class="text-xl sm:text-2xl font-extrabold text-slate-500 leading-none mt-0.5" id="statOffline">&mdash;</p>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-5">

    <!-- ── Sidebar: staff list ─────────────────────────────────── -->
    <div class="lg:col-span-1 flex flex-col gap-3 order-2 lg:order-1">
        <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-700 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex items-center gap-2">
                <i class="bi bi-people-fill text-blue-500"></i>
                <span class="font-bold text-sm text-slate-700 dark:text-slate-200">Staff</span>
                <span class="ml-auto text-xs text-slate-400"><?= count($staff) ?> members</span>
            </div>
            <!-- Search -->
            <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-700">
                <div class="relative">
                    <i class="bi bi-search absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400 text-xs pointer-events-none"></i>
                    <input id="staffSearch" type="text" placeholder="Search staff…"
                           class="w-full pl-7 pr-3 py-1.5 text-xs border border-slate-200 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-400 transition">
                </div>
            </div>
            <ul id="staffList" class="divide-y divide-slate-50 dark:divide-slate-700">
                <?php foreach ($staff as $m): ?>
                <li class="staff-row flex items-center gap-3 px-4 py-3 cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
                    data-id="<?= (int)$m['id'] ?>"
                    data-name="<?= strtolower(htmlspecialchars($m['full_name'])) ?>">
                    <span class="w-9 h-9 rounded-full flex items-center justify-center shrink-0 font-bold text-sm text-white
                                 <?= $m['role'] === 'admin' ? 'bg-purple-500' : 'bg-blue-500' ?>">
                        <?= mb_strtoupper(mb_substr($m['full_name'], 0, 2)) ?>
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200 truncate"><?= htmlspecialchars($m['full_name']) ?></p>
                        <p class="staff-status text-xs text-slate-400 mt-0.5">&mdash;</p>
                        <p class="staff-gps text-[11px] text-slate-400 mt-0.5 hidden"><i class="bi bi-geo-alt"></i> <span></span></p>
                    </div>
                    <span class="staff-dot w-2.5 h-2.5 rounded-full bg-slate-200 shrink-0 dot-loading" title="Loading…"></span>
                </li>
                <?php endforeach; ?>
                <?php if (empty($staff)): ?>
                <li class="px-4 py-6 text-center text-slate-400 text-sm">No active MAs found.</li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Legend -->
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 px-4 py-3 text-xs text-slate-500 space-y-1.5">
            <p class="font-bold text-slate-600 mb-2">Legend</p>
            <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold mb-1">Session status</p>
            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-emerald-500 shrink-0"></span> Online (&lt; 10 min)</div>
            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-amber-400 shrink-0"></span> Away (10–60 min)</div>
            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-slate-300 shrink-0"></span> Offline (&gt; 60 min)</div>
            <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold mt-3 mb-1">Map pin = GPS freshness</p>
            <div class="flex items-center gap-2"><i class="bi bi-geo-alt-fill text-emerald-500"></i> Fresh (&lt; 10 min)</div>
            <div class="flex items-center gap-2"><i class="bi bi-geo-alt-fill text-amber-400"></i> Stale (10&ndash;60 min)</div>
            <div class="flex items-center gap-2"><i class="bi bi-geo-alt-fill text-slate-400"></i> Old / no data</div>
        </div>
    </div>

    <!-- ── Map ────────────────────────────────────────────────── -->
    <div class="lg:col-span-3 flex flex-col gap-3 order-1 lg:order-2">
        <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
            <div id="maMap"></div>
        </div>

        <!-- No-location notice -->
        <div id="noLocNotice"
             class="hidden flex items-start gap-3 bg-amber-50 border border-amber-200 text-amber-700 px-4 py-3 rounded-xl text-sm">
            <i class="bi bi-exclamation-triangle-fill shrink-0 mt-0.5"></i>
            <div>
                <strong class="block font-bold mb-0.5">No location data yet</strong>
                MAs must be logged in and have location sharing enabled in their browser.
                Locations appear here as soon as an MA opens any page.
            </div>
        </div>
    </div>

</div>

<!-- Leaflet JS + MarkerCluster -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<script>
(function () {
    var BASE = window._pdBase || '';
    var map  = L.map('maMap', { zoomControl: true }).setView([39.5, -98.35], 4);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(map);

    // Cluster group — nearby pins merge when zoomed out
    var clusterGroup = L.markerClusterGroup({
        maxClusterRadius: 55,
        showCoverageOnHover: false,
        iconCreateFunction: function (cluster) {
            var n = cluster.getChildCount();
            return L.divIcon({
                html: '<div style="background:#3b82f6;color:#fff;border-radius:50%;'
                    + 'width:38px;height:38px;display:flex;align-items:center;justify-content:center;'
                    + 'font-weight:800;font-size:14px;border:3px solid #fff;'
                    + 'box-shadow:0 2px 8px rgba(59,130,246,.45)">' + n + '</div>',
                iconSize: [38, 38],
                iconAnchor: [19, 19],
                className: ''
            });
        }
    });
    clusterGroup.addTo(map);

    var markers   = {};   // staff_id → L.marker
    var allBounds = [];   // last known bounds for Fit All
    var lastUpdEl  = document.getElementById('locLastUpdated');
    var noLocEl    = document.getElementById('noLocNotice');
    var refreshBtn = document.getElementById('locRefreshBtn');
    var refreshIcon= document.getElementById('refreshIcon');
    var fitBtn     = document.getElementById('locFitBtn');
    var statOnline = document.getElementById('statOnline');
    var statAway   = document.getElementById('statAway');
    var statOffline= document.getElementById('statOffline');

    function makeIcon(color, initials) {
        var colors = { green: '#10b981', amber: '#f59e0b', grey: '#94a3b8' };
        var c = colors[color] || colors.grey;
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="36" height="44" viewBox="0 0 36 44">'
            + '<path d="M18 0C8.059 0 0 8.059 0 18c0 13.5 18 26 18 26S36 31.5 36 18C36 8.059 27.941 0 18 0z" fill="' + c + '"/>'
            + '<circle cx="18" cy="18" r="12" fill="white" opacity="0.95"/>'
            + '<text x="18" y="22.5" text-anchor="middle" font-size="11" font-family="sans-serif" font-weight="700" fill="' + c + '">'
            + initials + '</text></svg>';
        return L.divIcon({ html: svg, iconSize: [36, 44], iconAnchor: [18, 44], popupAnchor: [0, -44], className: '' });
    }

    function statusColor(ts) {
        if (!ts) return 'grey';
        var d = (Date.now() - new Date(ts).getTime()) / 60000;
        return d < 10 ? 'green' : d < 60 ? 'amber' : 'grey';
    }

    function relTime(ts) {
        if (!ts) return 'No data';
        var d = Math.round((Date.now() - new Date(ts).getTime()) / 60000);
        if (d < 1) return 'Just now';
        if (d < 60) return d + ' min ago';
        return Math.round(d / 60) + ' hr ago';
    }

    function dotCls(color) {
        return color === 'green' ? 'bg-emerald-500' : color === 'amber' ? 'bg-amber-400' : 'bg-slate-300';
    }

    function sessionColor(ts) {
        if (!ts) return 'grey';
        var d = (Date.now() - new Date(ts).getTime()) / 60000;
        return d < 10 ? 'green' : d < 60 ? 'amber' : 'grey';
    }

    function sessionLabel(ts) {
        if (!ts) return 'Never seen';
        var d = Math.round((Date.now() - new Date(ts).getTime()) / 60000);
        if (d < 1)  return 'Online now';
        if (d < 60) return 'Active ' + d + ' min ago';
        return 'Active ' + Math.round(d / 60) + ' hr ago';
    }

    function makePopup(loc) {
        var lat = parseFloat(loc.latitude);
        var lng = parseFloat(loc.longitude);
        var sesCol = sessionColor(loc.last_active_at);
        var sesLabel = sessionLabel(loc.last_active_at);
        var gpsLabel = relTime(loc.recorded_at);
        var gpsCol = statusColor(loc.recorded_at);
        var accuracy = loc.accuracy ? parseFloat(loc.accuracy).toFixed(0) + ' m' : 'N/A';
        var dotColors = { green: '#10b981', amber: '#f59e0b', grey: '#94a3b8' };
        var gpsDotColors = { green: '#10b981', amber: '#f59e0b', grey: '#94a3b8' };
        var sesDot = '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' + dotColors[sesCol] + ';margin-right:5px;vertical-align:middle"></span>';
        var gpsDot = '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:' + gpsDotColors[gpsCol] + ';margin-right:5px;vertical-align:middle"></span>';
        return '<div style="min-width:220px;font-family:inherit">'
            + '<div style="padding:10px 12px 8px;border-bottom:1px solid #f1f5f9">'
            + '<p style="font-weight:800;font-size:14px;color:#0f172a;margin:0 0 1px">' + loc.full_name + '</p>'
            + '<p style="font-size:11px;color:#94a3b8;text-transform:capitalize;margin:0">' + loc.role + '</p>'
            + '</div>'
            + '<div style="padding:8px 12px 10px;font-size:12px;line-height:1.7">'
            + '<div>' + sesDot + '<span style="color:#475569">' + sesLabel + '</span></div>'
            + '<div>' + gpsDot + '<span style="color:#475569">GPS: ' + gpsLabel + '</span></div>'
            + '<div style="margin-top:6px;font-size:11px;color:#94a3b8">Accuracy: ' + accuracy
            + ' &nbsp;&bull;&nbsp; ' + lat.toFixed(5) + ', ' + lng.toFixed(5) + '</div>'
            + '</div></div>';
    }

    // Active row tracking
    var activeRow = null;
    function setActiveRow(row) {
        if (activeRow) activeRow.classList.remove('row-active');
        activeRow = row;
        if (row) row.classList.add('row-active');
    }

    // Loading state helpers
    function setLoading(on) {
        refreshBtn.disabled = on;
        refreshIcon.className = 'bi bi-arrow-clockwise' + (on ? ' icon-spin' : '');
    }

    function loadLocations() {
        setLoading(true);
        fetch(BASE + '/api/get_locations.php')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                setLoading(false);
                if (!data.ok) return;
                var locs = data.locations;

                // Count stats
                var nOnline = 0, nAway = 0, nOffline = 0;
                locs.forEach(function (l) {
                    var c = sessionColor(l.last_active_at);
                    if (c === 'green') nOnline++;
                    else if (c === 'amber') nAway++;
                    else nOffline++;
                });
                statOnline.textContent  = nOnline;
                statAway.textContent    = nAway;
                statOffline.textContent = nOffline;

                // Update sidebar rows
                var rows = Array.from(document.querySelectorAll('.staff-row'));
                rows.forEach(function (row) {
                    var id  = parseInt(row.dataset.id, 10);
                    var dot = row.querySelector('.staff-dot');
                    var loc = locs.find(function (l) { return parseInt(l.staff_id, 10) === id; });
                    var col = loc ? sessionColor(loc.last_active_at) : 'grey';

                    // Remove loading pulse after first data
                    dot.classList.remove('dot-loading');
                    dot.className = 'staff-dot w-2.5 h-2.5 rounded-full shrink-0 ' + dotCls(col);
                    dot.title = loc ? sessionLabel(loc.last_active_at) : 'No session data';

                    var statusEl = row.querySelector('.staff-status');
                    if (statusEl) {
                        statusEl.textContent = loc ? sessionLabel(loc.last_active_at) : 'No data';
                        statusEl.className = 'staff-status text-xs mt-0.5 '
                            + (col === 'green' ? 'text-emerald-600 font-semibold'
                            :  col === 'amber'  ? 'text-amber-500'
                            : 'text-slate-400');
                    }

                    // GPS age line
                    var gpsEl = row.querySelector('.staff-gps');
                    if (gpsEl && loc && loc.latitude) {
                        gpsEl.classList.remove('hidden');
                        var gpsSpan = gpsEl.querySelector('span');
                        if (gpsSpan) gpsSpan.textContent = 'GPS ' + relTime(loc.recorded_at);
                    } else if (gpsEl) {
                        gpsEl.classList.add('hidden');
                    }

                    row.onclick = function () {
                        var mid = parseInt(row.dataset.id, 10);
                        if (markers[mid]) {
                            clusterGroup.zoomToShowLayer(markers[mid], function () {
                                markers[mid].openPopup();
                            });
                            setActiveRow(row);
                        }
                    };
                });

                // Sort sidebar: online first, then away, then offline
                var order = { green: 0, amber: 1, grey: 2 };
                var list = document.getElementById('staffList');
                rows.sort(function (a, b) {
                    var la = locs.find(function (l) { return parseInt(l.staff_id, 10) === parseInt(a.dataset.id, 10); });
                    var lb = locs.find(function (l) { return parseInt(l.staff_id, 10) === parseInt(b.dataset.id, 10); });
                    var ca = la ? sessionColor(la.last_active_at) : 'grey';
                    var cb = lb ? sessionColor(lb.last_active_at) : 'grey';
                    return (order[ca] || 2) - (order[cb] || 2);
                });
                rows.forEach(function (r) { list.appendChild(r); });

                // Place / update GPS markers
                allBounds = [];
                locs.forEach(function (loc) {
                    if (!loc.latitude || !loc.longitude) return;
                    var lat = parseFloat(loc.latitude);
                    var lng = parseFloat(loc.longitude);
                    var col = statusColor(loc.recorded_at);
                    var initials = loc.full_name.split(' ').map(function (w) { return w[0]; }).join('').toUpperCase().substring(0, 2);
                    var icon = makeIcon(col, initials);
                    var popup = makePopup(loc);
                    var popupOpts = { minWidth: 240, autoPanPadding: [30, 30] };
                    if (markers[loc.staff_id]) {
                        markers[loc.staff_id].setLatLng([lat, lng]).setIcon(icon).getPopup().setContent(popup);
                    } else {
                        var m = L.marker([lat, lng], { icon: icon })
                            .bindPopup(popup, popupOpts)
                            .on('popupopen', (function (sid) {
                                return function () {
                                    var r = document.querySelector('.staff-row[data-id="' + sid + '"]');
                                    setActiveRow(r);
                                };
                            })(loc.staff_id))
                            .on('popupclose', function () { setActiveRow(null); });
                        clusterGroup.addLayer(m);
                        markers[loc.staff_id] = m;
                    }
                    allBounds.push([lat, lng]);
                });

                if (allBounds.length > 0) {
                    map.fitBounds(allBounds, { padding: [40, 40], maxZoom: 14 });
                    noLocEl.classList.add('hidden');
                } else {
                    noLocEl.classList.remove('hidden');
                }

                var now = new Date();
                var hh = now.getHours(), mm = String(now.getMinutes()).padStart(2,'0');
                var ampm = hh >= 12 ? 'PM' : 'AM';
                hh = hh % 12 || 12;
                lastUpdEl.textContent = 'Updated ' + hh + ':' + mm + ' ' + ampm;
            })
            .catch(function () {
                setLoading(false);
                lastUpdEl.textContent = 'Update failed';
            });
    }

    // Fit All button
    fitBtn.addEventListener('click', function () {
        if (allBounds.length > 0) map.fitBounds(allBounds, { padding: [40, 40], maxZoom: 14 });
    });

    // Staff search filter
    document.getElementById('staffSearch').addEventListener('input', function () {
        var q = this.value.toLowerCase().trim();
        document.querySelectorAll('.staff-row').forEach(function (row) {
            var name = row.dataset.name || '';
            row.style.display = (!q || name.indexOf(q) !== -1) ? '' : 'none';
        });
    });

    // Initial load + auto-refresh every 60 s
    loadLocations();
    setInterval(loadLocations, 60000);

    refreshBtn.addEventListener('click', loadLocations);
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
