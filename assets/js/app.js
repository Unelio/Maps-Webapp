const mapOverlay = document.getElementById('mapOverlay');
const schOverlay = document.getElementById('schOverlay');
const iframe = document.getElementById('mapFrame');
const mapBtn = document.getElementById('mapBtn');
const mapSch = document.getElementById('mapSch');
const mapPoi = document.getElementById('mapPoi');
const poiOverlay = document.getElementById('poiOverlay');
const closeMapOverlay = document.getElementById('closeMapOverlay');
const closeSchOverlay = document.getElementById('closeSchOverlay');
const closePoiOverlay = document.getElementById('closePoiOverlay');
const mapTitle = document.getElementById('mapTitle');
const mapIcon = document.getElementById('mapIcon');
const mapZoom = document.getElementById('mapZoom');
const schSearchInput = document.getElementById('schSearchInput');
const schSearchStatus = document.getElementById('schSearchStatus');
const locateBtn = document.getElementById('locateBtn');
const zoomInBtn = document.getElementById('zoomInBtn');
const zoomOutBtn = document.getElementById('zoomOutBtn');
const placeholderMapIcon = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1 1'%3E%3C/svg%3E";

function selectMap(file, overlay) {
  loadMap(file);
  updateTitle(file);
  persistSelectedMap(file);
  overlay.classList.remove('show');
}

// Fonction pour charger une carte
function loadMap(file) {
  if(file.endsWith('.js')){
    iframe.src = "iframe/map_base_online.html?tile=" + encodeURIComponent("../data/maps/maps_online/" + file);
  } else if(file.endsWith('.txt')){
    iframe.src = "iframe/map_base_local.html?tile=" + encodeURIComponent("../data/maps/maps_local/" + file);
  }
}

function persistSelectedMap(file) {
  localStorage.setItem('selectedMap', file);

  fetch('?action=settings', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ selectedMap: file })
  }).catch(function () {
    // La persistance navigateur reste disponible si l'écriture serveur échoue.
  });
}

// Met à jour le titre et l'icône
function updateTitle(file) {
  const li = mapOverlay.querySelector(`li[data-file="${file}"]`);
  if (li) {
    mapTitle.textContent = li.textContent.trim();

    const iconFile = li.dataset.logo || '';

    mapIcon.src = iconFile;

    // Fallback si l'image n'existe pas
    mapIcon.onerror = () => {
      mapIcon.src = 'data/maps/default.png';
    };

  } else {
    mapTitle.textContent = '';
    mapIcon.src = placeholderMapIcon;
  }
}

function isMobileLikeDevice() {
  if (window.matchMedia && window.matchMedia('(pointer: coarse)').matches) {
    return true;
  }

  return /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '');
}

function updateLocateButtonVisibility() {
  if (!locateBtn) return;

  if (!('geolocation' in navigator) || !isMobileLikeDevice()) {
    locateBtn.style.display = 'none';
    return;
  }

  function showButton() {
    locateBtn.style.display = 'block';
  }

  function probeGeolocation() {
    navigator.geolocation.getCurrentPosition(
      function () {
        showButton();
      },
      function () {
        // On laisse le bouton caché si aucun point de géolocalisation réel n'est obtenu.
      },
      { timeout: 5000, maximumAge: 60000, enableHighAccuracy: false }
    );
  }

  if (navigator.permissions && navigator.permissions.query) {
    navigator.permissions.query({ name: 'geolocation' }).then(function (status) {
      if (status.state !== 'denied') {
        probeGeolocation();
      }
    }).catch(probeGeolocation);
  } else {
    probeGeolocation();
  }
}

// Ouvrir l'overlay choix des cartes
mapBtn.addEventListener('click', () => {
  mapOverlay.classList.add('show');
});

// Ouvrir l'overlay de recherche
mapSch.addEventListener('click', () => {
  schOverlay.classList.add('show');
  if (schSearchInput) {
    schSearchInput.value = '';
    schSearchInput.focus();
  }
  if (schSearchStatus) {
    schSearchStatus.textContent = '';
  }
});

// Ouvrir l'overlay des points d'intérêt
mapPoi.addEventListener('click', () => {
  poiOverlay.classList.add('show');
});

// Fermer l'overlay choix des cartes
closeMapOverlay.addEventListener('click', () => {
  mapOverlay.classList.remove('show');
});

// Fermer l'overlay de recherche
closeSchOverlay.addEventListener('click', () => {
  schOverlay.classList.remove('show');
});

// Fermer l'overlay des points d'intérêt
closePoiOverlay.addEventListener('click', () => {
  poiOverlay.classList.remove('show');
});

// Sélection d'une carte
mapOverlay.querySelectorAll('li').forEach(li => {
  li.addEventListener('click', () => {
    selectMap(li.dataset.file, mapOverlay);
  });
});

schOverlay.querySelectorAll('li').forEach(li => {
  li.addEventListener('click', () => {
    selectMap(li.dataset.file, schOverlay);
  });
});

// Charger la carte initiale
loadMap(window.defaultMap);
updateTitle(window.defaultMap);

// Recevoir zoom depuis l'iframe
window.addEventListener("message", (event) => {
  if(event.data.type === "zoom") {
    mapZoom.textContent = "Zoom : " + event.data.value;
  }
});

// Désactiver tant que l'iframe n'est pas chargée
[locateBtn, zoomInBtn, zoomOutBtn].forEach(b => {
  if (b) {
    b.disabled = true;
  }
});

iframe.addEventListener('load', () => {

  [locateBtn, zoomInBtn, zoomOutBtn].forEach(b => {
    if (b) {
      b.disabled = false;
    }
  });

});

function postToIframe(msg){
  try {
    // Préférer window.location.origin (sécurité)
    iframe.contentWindow.postMessage(msg, window.location.origin);
  } catch(e) {
    // fallback permissif si besoin
    iframe.contentWindow.postMessage(msg, '*');
  }
}

window.mapBridge = {
  postToIframe
};

updateLocateButtonVisibility();

if (locateBtn) {
  locateBtn.addEventListener('click', () => {
    postToIframe({ type: 'locate' });
  });
}

if (zoomInBtn) {
  zoomInBtn.addEventListener('click', () => {
    postToIframe({ type: 'zoomIn' });
  });
}

if (zoomOutBtn) {
  zoomOutBtn.addEventListener('click', () => {
    postToIframe({ type: 'zoomOut' });
  });
}
