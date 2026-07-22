const mapOverlay = document.getElementById('mapOverlay');
const schOverlay = document.getElementById('schOverlay');
const iframe = document.getElementById('mapFrame');
const mapBtn = document.getElementById('mapBtn');
const mapSch = document.getElementById('mapSch');
const closeMapOverlay = document.getElementById('closeMapOverlay');
const closeSchOverlay = document.getElementById('closeSchOverlay');
const mapTitle = document.getElementById('mapTitle');
const mapIcon = document.getElementById('mapIcon');
const mapZoom = document.getElementById('mapZoom');
const schSearchInput = document.getElementById('schSearchInput');

function selectMap(file, overlay) {
  loadMap(file);
  updateTitle(file);
  overlay.classList.remove('show');
}

// Fonction pour charger une carte
function loadMap(file) {
  if(file.endsWith('.js')){
    iframe.src = "../maps/maps_online/map_base_online.php?tile=" + encodeURIComponent(file);
  } else if(file.endsWith('.php')){
    iframe.src = "../maps/maps_local/map_base_local.php?tile=" + encodeURIComponent(file);
  }
  localStorage.setItem('selectedMap', file);
}

// Met à jour le titre et l'icône
function updateTitle(file) {
  const li = mapOverlay.querySelector(`li[data-file="${file}"]`);
  if (li) {
    mapTitle.textContent = li.textContent.trim();

    // Génère le logo à partir du nom du fichier
    const iconFile = file.endsWith('.js')
      ? '../maps/maps_online/logos/' + file.replace('.js','.png')
      : '../maps/maps_local/logos/' + file.replace('.php','.png');

    mapIcon.src = iconFile;

    // Fallback si l'image n'existe pas
    mapIcon.onerror = () => {
      mapIcon.src = '../maps/default.png';
    };

  } else {
    mapTitle.textContent = '';
    mapIcon.src = '';
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
    schOverlay.querySelectorAll('li').forEach(li => li.classList.remove('hidden'));
  }
});

// Fermer l'overlay choix des cartes
closeMapOverlay.addEventListener('click', () => {
  mapOverlay.classList.remove('show');
});

// Fermer l'overlay de recherche
closeSchOverlay.addEventListener('click', () => {
  schOverlay.classList.remove('show');
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

if (schSearchInput) {
  schSearchInput.addEventListener('input', () => {
    const query = schSearchInput.value.trim().toLowerCase();

    schOverlay.querySelectorAll('li').forEach(li => {
      const text = li.textContent.trim().toLowerCase();
      li.classList.toggle('hidden', query !== '' && !text.includes(query));
    });
  });
}

// Charger la carte initiale
const savedMap = localStorage.getItem('selectedMap');

if (savedMap && mapOverlay.querySelector(`li[data-file="${savedMap}"]`)) {
  loadMap(savedMap);
  updateTitle(savedMap);
} else {
  loadMap(window.defaultMap);
  updateTitle(window.defaultMap);
}

// Recevoir zoom depuis l'iframe
window.addEventListener("message", (event) => {
  if(event.data.type === "zoom") {
    mapZoom.textContent = "Zoom : " + event.data.value;
  }
});

// Affichage de boutons stylisés pour le contrôle de la carte
const locateBtn = document.getElementById('locateBtn');
const zoomInBtn = document.getElementById('zoomInBtn');
const zoomOutBtn = document.getElementById('zoomOutBtn');

// Désactiver tant que l'iframe n'est pas chargée
[locateBtn, zoomInBtn, zoomOutBtn].forEach(b => {
  b.disabled = true;
});

iframe.addEventListener('load', () => {

  [locateBtn, zoomInBtn, zoomOutBtn].forEach(b => {
    b.disabled = false;
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

locateBtn.addEventListener('click', () => {
  postToIframe({ type: 'locate' });
});

zoomInBtn.addEventListener('click', () => {
  postToIframe({ type: 'zoomIn' });
});

zoomOutBtn.addEventListener('click', () => {
  postToIframe({ type: 'zoomOut' });
});
