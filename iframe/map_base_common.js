(function () {
  var mapBaseConfig = window.mapBaseConfig || {};
  var isLocalMap = mapBaseConfig.mode === 'local';
  var params = new URLSearchParams(window.location.search);
  var tileSource = params.get('tile') || '';
  var initialLat = Number(params.get('lat'));
  var initialLng = Number(params.get('lng'));

  if (window.matchMedia('(display-mode: standalone)').matches) {
    document.body.classList.add('standalone');
  }

  function normalizeCoordinate(value, fallback, minValue, maxValue) {
    var numericValue = Number(value);

    if (!Number.isFinite(numericValue)) {
      return fallback;
    }

    return Math.max(minValue, Math.min(maxValue, numericValue));
  }

  function normalizeCenter(latValue, lngValue, fallback) {
    var centerFallback = fallback || { lat: 48.854659, lng: 2.347872 };

    return {
      lat: normalizeCoordinate(latValue, centerFallback.lat, -90, 90),
      lng: normalizeCoordinate(lngValue, centerFallback.lng, -180, 180),
    };
  }

  function normalizeZoom(value, fallback) {
    var numericValue = Number(value);

    if (!Number.isFinite(numericValue)) {
      return fallback;
    }

    return Math.max(0, Math.min(22, Math.round(numericValue)));
  }

  var defaultZoom = 5;
  var initialCenter = normalizeCenter(initialLat, initialLng, { lat: 48.854659, lng: 2.347872 });
  var initialZoom = normalizeZoom(params.get('zoom'), defaultZoom);
  var map = L.map('map', { zoomControl: false }).setView([initialCenter.lat, initialCenter.lng], initialZoom);
  var userMarker = null;
  var searchMarker = null;
  var poiLayers = {};
  var poiMarkers = {};
  var poiLongPressTimer = null;
  var poiLongPressStart = null;
  var locationZoom = isLocalMap ? 11 : 16;

  window.map = map;

  function escapeHtml(text) {
    return String(text || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function syncPoiFile(data) {
    if (!data || !data.file) return;

    var fileKey = data.file;
    var points = Array.isArray(data.points) ? data.points : [];

    if (!poiLayers[fileKey]) {
      poiLayers[fileKey] = L.layerGroup().addTo(map);
    } else {
      poiLayers[fileKey].clearLayers();
    }

    poiMarkers[fileKey] = {};

    points.forEach(function (point) {
      var lat = Number(point && point.lat);
      var lng = Number(point && point.lng);

      if (Number.isNaN(lat) || Number.isNaN(lng)) return;

      var marker = L.marker([lat, lng]);
      var popupParts = [];

      if (point.label) {
        popupParts.push('<strong>' + escapeHtml(point.label) + '</strong>');
      }
      if (point.description) {
        popupParts.push('<div>' + escapeHtml(point.description) + '</div>');
      }
      if (point.time) {
        popupParts.push('<div>' + escapeHtml(point.time) + '</div>');
      }

      if (popupParts.length) {
        marker.bindPopup(popupParts.join('<br>'));
      }

      poiMarkers[fileKey][point.id] = marker;

      if (point.visible !== false) {
        marker.addTo(poiLayers[fileKey]);
      }
    });
  }

  function clearPoiLongPressTimer() {
    if (poiLongPressTimer) {
      clearTimeout(poiLongPressTimer);
      poiLongPressTimer = null;
    }

    poiLongPressStart = null;
  }

  function requestPoiAdd(latlng) {
    if (!latlng || Number.isNaN(latlng.lat) || Number.isNaN(latlng.lng)) return;

    if (window.parent) {
      window.parent.postMessage({
        type: 'poiAddRequested',
        lat: latlng.lat,
        lng: latlng.lng,
      }, '*');
    }
  }

  function startPoiLongPress(event) {
    if (!event) return;

    if (event.pointerType === 'mouse' && event.button !== 0) {
      return;
    }

    poiLongPressStart = {
      x: event.clientX,
      y: event.clientY,
      latlng: map.mouseEventToLatLng(event),
    };

    clearPoiLongPressTimer();
    poiLongPressTimer = setTimeout(function () {
      requestPoiAdd(poiLongPressStart && poiLongPressStart.latlng);
      clearPoiLongPressTimer();
    }, 650);
  }

  function movePoiLongPress(event) {
    if (!poiLongPressTimer || !poiLongPressStart) return;

    if (Math.abs(event.clientX - poiLongPressStart.x) > 10 || Math.abs(event.clientY - poiLongPressStart.y) > 10) {
      clearPoiLongPressTimer();
    }
  }

  function bindPoiLongPressHandlers() {
    var container = map.getContainer();

    container.addEventListener('pointerdown', startPoiLongPress, { passive: true });
    container.addEventListener('pointermove', movePoiLongPress, { passive: true });
    container.addEventListener('pointerup', clearPoiLongPressTimer);
    container.addEventListener('pointercancel', clearPoiLongPressTimer);
    container.addEventListener('pointerleave', clearPoiLongPressTimer);
    container.addEventListener('contextmenu', function (event) {
      event.preventDefault();
      requestPoiAdd(map.mouseEventToLatLng(event));
    });
  }

  function sendView() {
    if (window.parent) {
      var center = map.getCenter();
      window.parent.postMessage({
        type: 'view',
        center: { lat: center.lat, lng: center.lng },
        zoom: map.getZoom(),
      }, '*');
    }
  }

  function centerOnLocation() {
    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        function (pos) {
          var latlng = [pos.coords.latitude, pos.coords.longitude];
          map.flyTo(latlng, locationZoom);
          if (!userMarker) {
            userMarker = L.marker(latlng).addTo(map);
          } else {
            userMarker.setLatLng(latlng);
          }
        },
        function (err) {
          if (isLocalMap) return;
          console.warn('Erreur géolocalisation:', err.message);
        },
        { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
      );
    } else {
      alert("La géolocalisation n'est pas supportée par ce navigateur.");
    }
  }

  L.Control.Locate = L.Control.extend({
    onAdd: function () {
      var btn = L.DomUtil.create('div', 'leaflet-bar leaflet-control leaflet-control-locate');
      btn.innerHTML = `
            <svg viewBox="0 0 24 24" fill="none" stroke="black" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <circle cx="12" cy="12" r="3"></circle>
            </svg>`;
      btn.title = 'Recentrer sur ma position';

      L.DomEvent.on(btn, 'click', function (e) {
        L.DomEvent.stopPropagation(e);
        L.DomEvent.preventDefault(e);
        centerOnLocation();
      });

      return btn;
    }
  });

  L.control.locate = function (opts) { return new L.Control.Locate(opts); };
  L.control.locate({ position: 'bottomleft' }).addTo(map);
  L.control.zoom({ position: 'bottomright' }).addTo(map);
  bindPoiLongPressHandlers();

  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      function (pos) {
        L.marker([pos.coords.latitude, pos.coords.longitude]).addTo(map).openPopup();
      },
      isLocalMap ? function () { } : function (err) { console.warn('Erreur géolocalisation:', err.message); },
      { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
    );
  } else if (isLocalMap) {
    console.warn('Géolocalisation non supportée');
  }

  sendView();
  map.on('moveend', sendView);

  window.addEventListener('message', function (event) {
    if (event.origin !== window.location.origin) return;
    var data = event.data || {};

    if (data.type === 'zoomIn') {
      if (map && typeof map.zoomIn === 'function') {
        map.zoomIn();
      }
    } else if (data.type === 'zoomOut') {
      if (map && typeof map.zoomOut === 'function') {
        map.zoomOut();
      }
    } else if (data.type === 'setZoom') {
      if (map && typeof map.setZoom === 'function') {
        map.setZoom(normalizeZoom(data.value, map.getZoom()));
      }
    } else if (data.type === 'setView') {
      var center = normalizeCenter(data.center && data.center.lat, data.center && data.center.lng, map.getCenter());
      var zoom = normalizeZoom(data.zoom, map.getZoom());

      if (map && typeof map.setView === 'function') {
        map.setView([center.lat, center.lng], zoom);
      }
    } else if (data.type === 'locate') {
      if (typeof centerOnLocation === 'function') centerOnLocation();
    } else if (data.type === 'search') {
      var lat = Number(data.lat);
      var lng = Number(data.lng);

      if (Number.isNaN(lat) || Number.isNaN(lng)) return;

      var latlng = [lat, lng];
      var targetZoom = map && typeof map.getMaxZoom === 'function' && map.getMaxZoom() ? Math.min(map.getMaxZoom(), 16) : 16;

      if (searchMarker) {
        searchMarker.setLatLng(latlng);
      } else {
        searchMarker = L.marker(latlng).addTo(map);
      }

      if (data.label) {
        searchMarker.bindPopup(data.label);
      }

      map.flyTo(latlng, targetZoom);
      if (data.label) {
        searchMarker.openPopup();
      }
    } else if (data.type === 'poiSyncFile') {
      syncPoiFile(data);
    }
  });

  function parseKeyValueConfig(text) {
    var config = {};
    String(text || '').split(/\r?\n/).forEach(function (line) {
      var trimmed = line.trim();
      if (!trimmed || trimmed.charAt(0) === '#') return;
      var separatorIndex = trimmed.indexOf('=');
      if (separatorIndex === -1) return;
      var key = trimmed.slice(0, separatorIndex).trim();
      var value = trimmed.slice(separatorIndex + 1).trim();
      if (key) config[key] = value;
    });
    return config;
  }

  function deriveMapCacheKey(tilePath) {
    var fileName = String(tilePath || '').split('/').pop();

    if (!fileName) return '';

    return fileName
      .replace(/\.(txt|js)$/i, '')
      .replace(/^tiles_/i, '')
      .replace(/[^A-Za-z0-9._-]+/g, '_')
      .replace(/^\.+|\.+$/g, '');
  }

  function loadLocalTiles(config) {
    var mbtilesFile = config.mbtiles || '';
    if (!mbtilesFile) {
      console.error('Configuration MBTiles invalide');
      return;
    }

    var mapCacheKey = deriveMapCacheKey(tileSource);
    var tileUrl = '../index.php?action=tile&file=' + encodeURIComponent(mbtilesFile) + '&map=' + encodeURIComponent(mapCacheKey) + '&z={z}&x={x}&y={y}';
    L.tileLayer(tileUrl, {
      minZoom: 3,
      maxZoom: 11,
      tms: false
    }).addTo(map);
  }

  function loadOnlineTiles(scriptUrl) {
    if (!scriptUrl) return;

    var script = document.createElement('script');
    script.src = scriptUrl;
    document.body.appendChild(script);
  }

  if (tileSource) {
    if (isLocalMap) {
      fetch(tileSource, { cache: 'no-store' })
        .then(function (response) {
          if (!response.ok) {
            throw new Error('Impossible de charger la configuration locale');
          }
          return response.text();
        })
        .then(function (text) {
          loadLocalTiles(parseKeyValueConfig(text));
        })
        .catch(function (error) {
          console.error(error);
        });
    } else {
      loadOnlineTiles(tileSource);
    }
  }
}());