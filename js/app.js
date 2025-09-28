const overlay = document.getElementById('mapOverlay');
const iframe = document.getElementById('mapFrame');
const btn = document.getElementById('mapBtn');
const closeOverlay = document.getElementById('closeOverlay');
const mapTitle = document.getElementById('mapTitle');
const mapIcon = document.getElementById('mapIcon');
const mapZoom = document.getElementById('mapZoom');

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
  const li = overlay.querySelector(`li[data-file="${file}"]`);
  if (li) {
    mapTitle.textContent = li.textContent.trim();
    
    // Génère le logo à partir du nom du fichier
    const iconFile = file.endsWith('.js')
      ? '../maps/maps_online/logos/' + file.replace('.js','.png')
      : '../maps/maps_local/logos/' + file.replace('.php','.png');
    mapIcon.src = iconFile;
    
    // Fallback si l'image n'existe pas
    mapIcon.onerror = () => mapIcon.src = '../maps/default.png';
  } else {
    mapTitle.textContent = '';
    mapIcon.src = '';
  }
}

// Ouvrir l'overlay avec transition
btn.addEventListener('click', () => {
  overlay.classList.add('show');
});

// Fermer l'overlay
closeOverlay.addEventListener('click', () => {
  overlay.classList.remove('show');
});

// Sélection d'une carte
overlay.querySelectorAll('li').forEach(li => {
  li.addEventListener('click', () => {
    const file = li.dataset.file;
    loadMap(file);
    updateTitle(file);
    overlay.classList.remove('show'); // <-- NE PAS toucher à style.display
  });
});

// Charger la carte initiale
const savedMap = localStorage.getItem('selectedMap');
if (savedMap && overlay.querySelector(`li[data-file="${savedMap}"]`)) {
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
[locateBtn, zoomInBtn, zoomOutBtn].forEach(b => b.disabled = true);

iframe.addEventListener('load', () => {
  [locateBtn, zoomInBtn, zoomOutBtn].forEach(b => b.disabled = false);
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

locateBtn.addEventListener('click', () => postToIframe({ type: 'locate' }));
zoomInBtn.addEventListener('click', () => postToIframe({ type: 'zoomIn' }));
zoomOutBtn.addEventListener('click', () => postToIframe({ type: 'zoomOut' }));