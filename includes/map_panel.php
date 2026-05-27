<!-- ═══════════════════════════════════════════════
     BUILT-IN MAP PANEL  (shared include)
     openMapPanel(address, name, gmUrl, visitData)
       visitData = { id, patient_id, visit_type, visit_subtype, startFn }
       startFn defaults to 'startVisit' if omitted
═══════════════════════════════════════════════ -->
<style>
#mapPanel {
    position: fixed; left: 0; right: 0; bottom: 0; z-index: 1000;
    display: flex; flex-direction: column;
    max-height: 88vh;
    border-radius: 20px 20px 0 0;
    box-shadow: 0 -8px 40px rgba(0,0,0,0.25);
    transform: translateY(100%);
    transition: transform 0.32s cubic-bezier(.4,0,.2,1);
    overflow: hidden;
    background: white;
}
#mapPanel.map-open { transform: translateY(0); }
#mapOverlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.45);
    z-index: 999; display: none;
    backdrop-filter: blur(2px);
}
#mapOverlay.map-open { display: block; }
#mapPanelHeader {
    background: #0f172a; color: white;
    padding: 14px 16px 12px;
    display: flex; align-items: center; gap: 12px;
    flex-shrink: 0;
    cursor: grab;
}
#builtinMap { flex: 1; min-height: 300px; }
#mapStatus {
    background: white; border-top: 1px solid #e2e8f0;
    padding: 10px 16px; font-size: 0.82rem; color: #475569;
    display: flex; align-items: center; gap: 8px;
    flex-shrink: 0;
}
#mapStatus .spin { animation: spin 1s linear infinite; display: inline-block; }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
@media (min-width: 768px) {
    #mapPanel { left: var(--sidebar-w, 240px); border-radius: 20px 20px 0 0; }
    #builtinMap { min-height: 420px; }
}
</style>

<div id="mapOverlay" onclick="closeMapPanel()"></div>

<div id="mapPanel" role="dialog" aria-modal="true" aria-label="Map Navigation">

  <!-- Header / drag handle -->
  <div id="mapPanelHeader">
    <div style="width:36px;height:36px;background:rgba(255,255,255,0.1);border-radius:10px;display:grid;place-items:center;flex-shrink:0;">
      <i class="bi bi-map-fill" style="font-size:1.1rem;color:#93c5fd;"></i>
    </div>
    <div style="flex:1;min-width:0;">
      <div style="font-weight:700;font-size:0.9rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" id="mapPanelName"></div>
      <div style="font-size:0.72rem;color:#94a3b8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" id="mapPanelAddr"></div>
    </div>
    <a id="mapPanelGmBtn" href="#" target="_blank" rel="noopener"
       style="display:flex;align-items:center;gap:6px;background:#2563eb;color:white;border:none;border-radius:10px;padding:7px 13px;font-size:0.78rem;font-weight:700;text-decoration:none;white-space:nowrap;flex-shrink:0;transition:background 0.15s;"
       onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
      <i class="bi bi-google"></i> Open in Maps
    </a>
    <button onclick="closeMapPanel()"
            style="width:34px;height:34px;background:rgba(255,255,255,0.08);border:none;border-radius:8px;color:#94a3b8;font-size:1rem;cursor:pointer;display:grid;place-items:center;flex-shrink:0;margin-left:4px;transition:background 0.12s;"
            onmouseover="this.style.background='rgba(255,255,255,0.16)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'"
            title="Close">
      <i class="bi bi-x-lg"></i>
    </button>
  </div>

  <div id="builtinMap"></div>

  <div id="mapStatus">
    <i class="bi bi-hourglass-split spin" style="color:#3b82f6;"></i> Loading map…
  </div>

  <div id="mapStartVisitBar" style="display:none;padding:12px 16px;border-top:1px solid rgba(255,255,255,0.08);">
    <button id="mapStartVisitBtn"
            style="width:100%;display:flex;align-items:center;justify-content:center;gap:8px;
                   padding:14px 20px;background:#2563eb;color:#fff;border:none;border-radius:50px;
                   font-size:15px;font-weight:800;cursor:pointer;transition:background 0.15s;
                   box-shadow:0 4px 16px rgba(37,99,235,0.45);"
            onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
      <i class="bi bi-play-fill"></i> Start Visit &nbsp;→
    </button>
  </div>

</div>

<script>
(function () {
'use strict';

var mapInstance  = null;
var destMarker   = null;
var userMarker   = null;
var routeLayer   = null;
var leafletReady = false;

window.openMapPanel = function (address, name, gmUrl, visitData) {
    document.getElementById('mapPanelName').textContent = name || 'Patient';
    document.getElementById('mapPanelAddr').textContent = address || '';
    document.getElementById('mapPanelGmBtn').href = gmUrl || '#';
    setMapStatus('<i class="bi bi-hourglass-split spin" style="color:#3b82f6;"></i> Loading map…');

    // Start Visit button — calls whichever start function the caller specifies
    var bar = document.getElementById('mapStartVisitBar');
    var btn = document.getElementById('mapStartVisitBtn');
    if (visitData && visitData.id) {
        btn.onclick = function () {
            var fn = (visitData.startFn && window[visitData.startFn]) || window.startVisit;
            if (fn) fn(visitData.id, visitData.patient_id, visitData.visit_type, visitData.visit_subtype || '', btn);
        };
        bar.style.display = '';
    } else {
        bar.style.display = 'none';
    }

    document.getElementById('mapPanel').classList.add('map-open');
    document.getElementById('mapOverlay').classList.add('map-open');
    document.body.style.overflow = 'hidden';

    loadLeaflet(function () {
        // Wait for panel slide-in animation (320ms) before init so Leaflet sees the real height
        setTimeout(function () { initMap(address); }, 340);
    });
};

window.closeMapPanel = function () {
    document.getElementById('mapPanel').classList.remove('map-open');
    document.getElementById('mapOverlay').classList.remove('map-open');
    document.body.style.overflow = '';
};

document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    // Don't close the map while a confirm dialog is open
    if (window.Alpine && Alpine.store && Alpine.store('pdConfirm') && Alpine.store('pdConfirm').visible) return;
    closeMapPanel();
});

function setMapStatus(html) {
    document.getElementById('mapStatus').innerHTML = html;
}

function loadLeaflet(cb) {
    if (window.L && leafletReady) { cb(); return; }
    if (window.L) { leafletReady = true; cb(); return; }

    var css = document.createElement('link');
    css.rel = 'stylesheet';
    css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    document.head.appendChild(css);

    var js = document.createElement('script');
    js.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    js.onload = function () { leafletReady = true; cb(); };
    document.head.appendChild(js);
}

function initMap(address) {
    var container = document.getElementById('builtinMap');

    if (!mapInstance) {
        mapInstance = L.map(container, { zoomControl: true });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://openstreetmap.org">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(mapInstance);
    } else {
        if (destMarker)  { mapInstance.removeLayer(destMarker);  destMarker  = null; }
        if (userMarker)  { mapInstance.removeLayer(userMarker);  userMarker  = null; }
        if (routeLayer)  { mapInstance.removeLayer(routeLayer);  routeLayer  = null; }
    }
    mapInstance.invalidateSize();

    function doGeocode(query) {
        return fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(query), {
            headers: { 'Accept': 'application/json', 'Accept-Language': 'en' }
        }).then(function (r) { return r.json(); });
    }

    /* Build a chain of progressively simpler address queries */
    function buildFallbacks(addr) {
        var queries = [addr];
        // 1. Strip leading building/unit descriptor before first comma
        var noBuilding = addr.replace(/^[^,]*(?:bldg|building|unit|rm|room|fl|floor|blk|block|apt|suite|ste)[^,]*,\s*/i, '').trim();
        if (noBuilding && noBuilding !== addr) queries.push(noBuilding);
        // 2. Drop everything before the first comma (building name / apt)
        var afterFirstComma = addr.replace(/^[^,]+,\s*/, '').trim();
        if (afterFirstComma && afterFirstComma !== addr && afterFirstComma !== noBuilding) queries.push(afterFirstComma);
        // 3. Street + city/state: keep only the last two comma-parts (e.g. "123 Main St, Naperville, IL 60540" → "Naperville, IL 60540")
        var parts = addr.split(',').map(function(s){ return s.trim(); }).filter(Boolean);
        if (parts.length >= 3) {
            // street + last two parts
            queries.push(parts[0] + ', ' + parts.slice(-2).join(', '));
            // just last two parts (city + state/zip)
            queries.push(parts.slice(-2).join(', '));
        }
        // Deduplicate
        return queries.filter(function(q, i, arr) { return arr.indexOf(q) === i && q.length > 3; });
    }

    /* Try each fallback in sequence, stop on first hit */
    function geocodeWithFallbacks(queries, idx) {
        if (idx >= queries.length) return Promise.resolve(null);
        return doGeocode(queries[idx]).then(function(results) {
            if (results && results.length) return results;
            return geocodeWithFallbacks(queries, idx + 1);
        });
    }

    /* Build Google Maps directions URL, optionally with origin coords */
    function gmDirectionsUrl(destAddr, originLat, originLon) {
        var base = 'https://www.google.com/maps/dir/?api=1';
        if (originLat != null) base += '&origin=' + originLat + ',' + originLon;
        base += '&destination=' + encodeURIComponent(destAddr);
        return base;
    }

    function showDirectionsBtn(destAddr, originLat, originLon) {
        var url = gmDirectionsUrl(destAddr, originLat, originLon);
        // Update the Open in Maps button to include origin if we have it
        if (originLat != null) {
            document.getElementById('mapPanelGmBtn').href = url;
        }
        setMapStatus(
            '<i class="bi bi-exclamation-circle" style="color:#f59e0b;"></i> Could not pin address on map &mdash; '
            + '<a href="' + url + '" target="_blank" rel="noopener" style="color:#2563eb;font-weight:700;">'
            + '<i class="bi bi-google"></i> Get Directions in Google Maps</a>'
        );
    }

    setMapStatus('<i class="bi bi-search" style="color:#3b82f6;"></i> Locating address…');

    var fallbacks = buildFallbacks(address);
    geocodeWithFallbacks(fallbacks, 0).then(function(results) {
        if (!results) {
            /* Geocoding totally failed — still try to get user location for a better GM link */
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(pos) { showDirectionsBtn(address, pos.coords.latitude, pos.coords.longitude); },
                    function()    { showDirectionsBtn(address, null, null); },
                    { timeout: 6000, enableHighAccuracy: true }
                );
            } else {
                showDirectionsBtn(address, null, null);
            }
            return;
        }

        var dLat = parseFloat(results[0].lat);
        var dLon = parseFloat(results[0].lon);

        var destIcon = L.divIcon({
            className: '',
            html: '<div style="width:18px;height:18px;background:#ef4444;border-radius:50% 50% 50% 0;transform:rotate(-45deg);border:2.5px solid white;box-shadow:0 2px 8px rgba(0,0,0,0.35);"></div>',
            iconSize: [18, 18],
            iconAnchor: [9, 18]
        });
        destMarker = L.marker([dLat, dLon], { icon: destIcon })
            .addTo(mapInstance)
            .bindPopup('<strong>Destination</strong><br><span style="font-size:0.8rem;color:#475569;">' + address + '</span>', { maxWidth: 220 })
            .openPopup();

        // Re-invalidate here — panel is fully open by now
        mapInstance.invalidateSize();
        mapInstance.setView([dLat, dLon], 15);

        if (navigator.geolocation) {
            setMapStatus('<i class="bi bi-crosshair spin" style="color:#3b82f6;"></i> Getting your location…');
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    var uLat = pos.coords.latitude;
                    var uLon = pos.coords.longitude;

                    // Update GM button to include origin
                    document.getElementById('mapPanelGmBtn').href = gmDirectionsUrl(address, uLat, uLon);

                    userMarker = L.circleMarker([uLat, uLon], {
                        radius: 9, fillColor: '#3b82f6', color: '#1d4ed8',
                        fillOpacity: 0.9, weight: 2.5
                    }).addTo(mapInstance).bindPopup('<strong>You are here</strong>');

                    mapInstance.fitBounds([[dLat, dLon], [uLat, uLon]], { padding: [50, 50] });

                    // Fetch route from OSRM
                    fetch('https://router.project-osrm.org/route/v1/driving/'
                        + uLon + ',' + uLat + ';'
                        + dLon + ',' + dLat
                        + '?overview=full&geometries=geojson')
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.routes && data.routes.length) {
                            var dist = (data.routes[0].distance / 1609.34).toFixed(1);
                            var mins = Math.round(data.routes[0].duration / 60);
                            routeLayer = L.geoJSON(data.routes[0].geometry, {
                                style: { color: '#3b82f6', weight: 5, opacity: 0.75 }
                            }).addTo(mapInstance);
                            mapInstance.fitBounds(routeLayer.getBounds(), { padding: [50, 50] });
                            setMapStatus(
                                '<i class="bi bi-car-front-fill" style="color:#3b82f6;"></i>&nbsp;'
                                + dist + ' mi &nbsp;'
                                + '<i class="bi bi-clock" style="color:#64748b;"></i>&nbsp;~' + mins + ' min'
                                + '&nbsp;&nbsp;&middot;&nbsp;&nbsp;'
                                + '<a href="' + document.getElementById('mapPanelGmBtn').href + '" target="_blank" rel="noopener" style="color:#2563eb;font-weight:600;"><i class="bi bi-google"></i> Turn-by-turn in Google Maps</a>'
                            );
                        } else {
                            setMapStatus('<i class="bi bi-map" style="color:#3b82f6;"></i> Destination shown &mdash; <a href="' + document.getElementById('mapPanelGmBtn').href + '" target="_blank" rel="noopener" style="color:#2563eb;font-weight:600;"><i class="bi bi-google"></i> Get Directions</a>');
                        }
                    })
                    .catch(function () {
                        setMapStatus('<i class="bi bi-map" style="color:#3b82f6;"></i> Destination shown &mdash; <a href="' + document.getElementById('mapPanelGmBtn').href + '" target="_blank" rel="noopener" style="color:#2563eb;font-weight:600;"><i class="bi bi-google"></i> Get Directions</a>');
                    });
                },
                function (err) {
                    var denied = err && err.code === 1; // PERMISSION_DENIED
                    if (denied) {
                        setMapStatus(
                            '<i class="bi bi-geo-alt-fill" style="color:#ef4444;"></i> Location blocked &mdash; '
                            + '<span style="color:#64748b;">enable in browser settings then </span>'
                            + '<a href="#" onclick="event.preventDefault();retryLocation()" style="color:#2563eb;font-weight:700;">tap to retry</a>'
                        );
                    } else {
                        setMapStatus('<i class="bi bi-map-fill" style="color:#3b82f6;"></i> Destination shown &mdash; <a href="' + document.getElementById('mapPanelGmBtn').href + '" target="_blank" rel="noopener" style="color:#2563eb;font-weight:600;"><i class="bi bi-google"></i> Open in Google Maps</a>');
                    }
                },
                { timeout: 8000, enableHighAccuracy: true }
            );
        } else {
            setMapStatus('<i class="bi bi-map-fill" style="color:#3b82f6;"></i> Destination shown &mdash; <a href="' + document.getElementById('mapPanelGmBtn').href + '" target="_blank" rel="noopener" style="color:#2563eb;font-weight:600;"><i class="bi bi-google"></i> Open in Google Maps</a>');
        }
    })
    .catch(function () {
        showDirectionsBtn(address, null, null);
    });
}

// Retry geolocation after user enables permission in browser settings
window.retryLocation = function () {
    if (!mapInstance || !destMarker) return;
    setMapStatus('<i class="bi bi-crosshair spin" style="color:#3b82f6;"></i> Getting your location…');
    var destLL = destMarker.getLatLng();
    navigator.geolocation.getCurrentPosition(
        function (pos) {
            var uLat = pos.coords.latitude;
            var uLon = pos.coords.longitude;
            if (userMarker) { mapInstance.removeLayer(userMarker); }
            if (routeLayer) { mapInstance.removeLayer(routeLayer); }
            userMarker = L.circleMarker([uLat, uLon], {
                radius: 9, fillColor: '#3b82f6', color: '#1d4ed8',
                fillOpacity: 0.9, weight: 2.5
            }).addTo(mapInstance).bindPopup('<strong>You are here</strong>');
            mapInstance.fitBounds([[destLL.lat, destLL.lng], [uLat, uLon]], { padding: [50, 50] });
            var gmUrl = 'https://www.google.com/maps/dir/?api=1&origin=' + uLat + ',' + uLon
                + '&destination=' + encodeURIComponent(document.getElementById('mapPanelAddr').textContent);
            document.getElementById('mapPanelGmBtn').href = gmUrl;
            setMapStatus('<i class="bi bi-map-fill" style="color:#3b82f6;"></i> Location found &mdash; <a href="' + gmUrl + '" target="_blank" rel="noopener" style="color:#2563eb;font-weight:600;"><i class="bi bi-google"></i> Get Directions</a>');
        },
        function () {
            setMapStatus('<i class="bi bi-geo-alt-fill" style="color:#ef4444;"></i> Still blocked — check browser site settings and try again.');
        },
        { timeout: 10000, enableHighAccuracy: true }
    );
};

})();
</script>
