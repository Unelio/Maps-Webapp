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
  function createMarkerIcon(color) {
    return L.icon({
      iconUrl: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(`
        <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
          <path fill="${color}" d="M50.002 0C30.763 0 15 15.718 15 34.902c0 7.432 2.374 14.34 6.392 20.019L45.73 96.994c3.409 4.453 5.675 3.607 8.51-.235l26.843-45.683c.542-.981.967-2.026 1.338-3.092A34.446 34.446 0 0 0 85 34.902C85 15.718 69.24 0 50.002 0zm0 16.354c10.359 0 18.597 8.218 18.597 18.548c0 10.33-8.238 18.544-18.597 18.544c-10.36 0-18.601-8.215-18.601-18.544c0-10.33 8.241-18.548 18.6-18.548z"/>
        </svg>
      `),
      iconSize: [25, 41],
      iconAnchor: [12, 41],
      popupAnchor: [1, -34],
      tooltipAnchor: [16, -28]
    });
  }

  var userLocationIcon = createMarkerIcon('#d56b6b');
  var poiIcon = createMarkerIcon('#0b336e');
  var searchIcon = createMarkerIcon('#19792f');

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

      var marker = L.marker([lat, lng], {
        icon: poiIcon
      });
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

  function setUserLocation(latlng) {
    if (!latlng) return;

    if (userMarker) {
      userMarker.setLatLng(latlng);
      return;
    }

    userMarker = L.marker(latlng, { icon: userLocationIcon }).addTo(map);
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
          setUserLocation(latlng);
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

  function focusPoiPoint(data) {
    if (!data) return;

    var lat = Number(data.lat);
    var lng = Number(data.lng);

    if (Number.isNaN(lat) || Number.isNaN(lng)) return;

    var latlng = [lat, lng];
    var targetZoom = map && typeof map.getMaxZoom === 'function' && map.getMaxZoom() ? Math.min(map.getMaxZoom(), 16) : 16;
    var marker = data.file && data.pointId && poiMarkers[data.file] ? poiMarkers[data.file][data.pointId] : null;

    map.flyTo(latlng, targetZoom);

    if (marker && map.hasLayer && map.hasLayer(marker) && typeof marker.openPopup === 'function') {
      marker.openPopup();
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
        var latlng = [pos.coords.latitude, pos.coords.longitude];
        setUserLocation(latlng);
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
        searchMarker = L.marker(latlng, {
          icon: searchIcon
        }).addTo(map);
      }

      if (data.label) {
        searchMarker.bindPopup(data.label);
      }

      map.flyTo(latlng, targetZoom);
      if (data.label) {
        searchMarker.openPopup();
      }
    } else if (data.type === 'poiFocus') {
      focusPoiPoint(data);
    } else if (data.type === 'poiSyncFile') {
      syncPoiFile(data);
    } else if (data.type === 'poiRemoveFile') {
      if (!data.file) return;

      if (poiLayers[data.file]) {
        poiLayers[data.file].clearLayers();
        if (map && typeof map.removeLayer === 'function' && map.hasLayer && map.hasLayer(poiLayers[data.file])) {
          map.removeLayer(poiLayers[data.file]);
        }
        delete poiLayers[data.file];
      }

      if (poiMarkers[data.file]) {
        delete poiMarkers[data.file];
      }
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
