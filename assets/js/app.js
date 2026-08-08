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
const poiAddOverlay = document.getElementById('poiAddOverlay');
const closePoiAddOverlay = document.getElementById('closePoiAddOverlay');
const poiFolderRenameOverlay = document.getElementById('poiFolderRenameOverlay');
const closePoiFolderRenameOverlay = document.getElementById('closePoiFolderRenameOverlay');
const poiFolderRenameForm = document.getElementById('poiFolderRenameForm');
const poiFolderRenameInput = document.getElementById('poiFolderRenameInput');
const poiFolderRenameCancel = document.getElementById('poiFolderRenameCancel');
const poiFolderRenameConfirm = document.getElementById('poiFolderRenameConfirm');
const poiFolderRenameTitle = document.getElementById('poiFolderRenameTitle');
const poiFolderRenameSummary = document.getElementById('poiFolderRenameSummary');
const poiFolderRenameStatus = document.getElementById('poiFolderRenameStatus');
const poiDeleteOverlay = document.getElementById('poiDeleteOverlay');
const closePoiDeleteOverlay = document.getElementById('closePoiDeleteOverlay');
const poiDeleteCancel = document.getElementById('poiDeleteCancel');
const poiDeleteConfirm = document.getElementById('poiDeleteConfirm');
const poiDeleteQuestion = document.getElementById('poiDeleteQuestion');
const poiDeleteStatus = document.getElementById('poiDeleteStatus');
const poiAddForm = document.getElementById('poiAddForm');
const poiAddLabel = document.getElementById('poiAddLabel');
const poiAddFile = document.getElementById('poiAddFile');
const poiAddNewFileWrap = document.getElementById('poiAddNewFileWrap');
const poiAddNewFile = document.getElementById('poiAddNewFile');
const poiAddLat = document.getElementById('poiAddLat');
const poiAddLng = document.getElementById('poiAddLng');
const poiAddCoords = document.getElementById('poiAddCoords');
const poiAddStatus = document.getElementById('poiAddStatus');
const poiAddCancel = document.getElementById('poiAddCancel');
const openPoiAddOverlay = document.getElementById('openPoiAddOverlay');
const mapTitle = document.getElementById('mapTitle');
const mapIcon = document.getElementById('mapIcon');
const mapZoom = document.getElementById('mapZoom');
const schSearchInput = document.getElementById('schSearchInput');
const schSearchStatus = document.getElementById('schSearchStatus');
const locateBtn = document.getElementById('locateBtn');
const zoomInBtn = document.getElementById('zoomInBtn');
const zoomOutBtn = document.getElementById('zoomOutBtn');
const placeholderMapIcon = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 1 1'%3E%3C/svg%3E";
const newPoiFileValue = '__new__';
let currentMapFile = window.defaultMap || '';
let currentZoom = Number.isFinite(window.defaultZoom) ? window.defaultZoom : 5;
let currentCenter = normalizeCenter(window.defaultCenter, { lat: 48.854659, lng: 2.347872 });
let poiAddMode = 'add';
let poiModifyContext = null;
let poiDeleteContext = null;
let poiDeleteAction = null;
let poiDeleteBusy = false;
let poiFolderRenameAction = null;
let poiFolderRenameBusy = false;

function normalizeCoordinatePair(latValue, lngValue, fallback) {
  const fallbackCenter = fallback || { lat: 48.854659, lng: 2.347872 };
  const lat = Number(latValue);
  const lng = Number(lngValue);

  if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
    return fallbackCenter;
  }

  return {
    lat: Math.max(-90, Math.min(90, lat)),
    lng: Math.max(-180, Math.min(180, lng)),
  };
}

function normalizeCenter(value, fallback) {
  if (Array.isArray(value) && value.length >= 2) {
    return normalizeCoordinatePair(value[0], value[1], fallback);
  }

  if (value && typeof value === 'object') {
    if ('lat' in value && 'lng' in value) {
      return normalizeCoordinatePair(value.lat, value.lng, fallback);
    }

    if ('lat' in value && 'lon' in value) {
      return normalizeCoordinatePair(value.lat, value.lon, fallback);
    }
  }

  return fallback || { lat: 48.854659, lng: 2.347872 };
}

function normalizeZoom(value, fallback) {
  const numericValue = Number(value);

  if (!Number.isFinite(numericValue)) {
    return fallback;
  }

  return Math.max(0, Math.min(22, Math.round(numericValue)));
}

function formatPoiCoordinate(value) {
  const numericValue = Number(value);

  if (Number.isNaN(numericValue)) {
    return '';
  }

  return numericValue.toFixed(6);
}

function setPoiAddStatus(message, isError) {
  if (!poiAddStatus) return;

  poiAddStatus.textContent = message || '';
  poiAddStatus.classList.toggle('is-error', !!isError);
}

function setPoiAddOverlayMode(mode, context) {
  poiAddMode = mode === 'modify' ? 'modify' : 'add';
  poiModifyContext = poiAddMode === 'modify' && context ? context : null;

  if (poiAddForm) {
    poiAddForm.noValidate = poiAddMode === 'modify';
  }

  const titleNode = poiAddOverlay ? poiAddOverlay.querySelector('h2') : null;
  const submitButton = poiAddForm ? poiAddForm.querySelector('.poi-add-submit') : null;
  const cancelButton = poiAddCancel;
  const fileField = poiAddFile ? poiAddFile.closest('.poi-add-field') : null;
  const newFileField = poiAddNewFileWrap ? poiAddNewFileWrap : null;
  const targetLabel = poiAddCoords;

  if (poiAddMode === 'modify') {
    if (titleNode) {
      titleNode.textContent = 'Modifier un point';
    }

    if (submitButton) {
      submitButton.textContent = 'Modifier';
    }

    if (cancelButton) {
      cancelButton.hidden = false;
    }

    if (fileField) {
      fileField.hidden = true;
    }

    if (newFileField) {
      newFileField.hidden = true;
    }

    if (poiAddFile) {
      poiAddFile.required = false;
      poiAddFile.disabled = true;
    }

    if (poiAddNewFile) {
      poiAddNewFile.required = false;
      poiAddNewFile.disabled = true;
    }

    if (targetLabel) {
      const modifyLat = poiModifyContext && poiModifyContext.coords ? poiModifyContext.coords.lat : null;
      const modifyLng = poiModifyContext && poiModifyContext.coords ? poiModifyContext.coords.lng : null;

      targetLabel.textContent = modifyLat != null && modifyLng != null
        ? 'Position : ' + formatPoiCoordinate(modifyLat) + ', ' + formatPoiCoordinate(modifyLng)
        : poiModifyContext && poiModifyContext.label
          ? 'Point à modifier : ' + poiModifyContext.label
          : 'Modifier le nom du point.';
    }

    if (poiAddFile && poiModifyContext && poiModifyContext.file) {
      poiAddFile.value = poiModifyContext.file;
    }

    return;
  }

  if (titleNode) {
    titleNode.textContent = 'Ajouter un point';
  }

  if (submitButton) {
    submitButton.textContent = 'Ajouter';
  }

  if (cancelButton) {
    cancelButton.hidden = false;
  }

  if (fileField) {
    fileField.hidden = false;
  }

  if (newFileField) {
    newFileField.hidden = false;
  }

  if (poiAddFile) {
    poiAddFile.disabled = false;
    poiAddFile.value = newPoiFileValue;
  }

  if (poiAddNewFile) {
    poiAddNewFile.disabled = false;
    poiAddNewFile.value = '';
  }

  if (targetLabel) {
    targetLabel.textContent = 'Appui long sur la carte pour choisir la position.';
  }

  if (poiAddLabel) {
    poiAddLabel.value = '';
  }

  if (poiAddLat) {
    poiAddLat.value = '';
  }

  if (poiAddLng) {
    poiAddLng.value = '';
  }

  if (poiAddFile) {
    updatePoiAddFileMode();
  }
}

function setPoiAddTarget(coords) {
  if (!poiAddLat || !poiAddLng || !poiAddCoords) return;

  const lat = coords && coords.lat;
  const lng = coords && coords.lng;

  poiAddLat.value = lat != null ? String(lat) : '';
  poiAddLng.value = lng != null ? String(lng) : '';
  poiAddCoords.textContent = lat != null && lng != null
    ? 'Position : ' + formatPoiCoordinate(lat) + ', ' + formatPoiCoordinate(lng)
    : 'Appui long sur la carte pour choisir la position.';
}

function updatePoiAddFileMode() {
  if (poiAddMode !== 'add') return;

  if (!poiAddFile || !poiAddNewFileWrap || !poiAddNewFile) return;

  const createNewFile = poiAddFile.value === newPoiFileValue;
  poiAddNewFileWrap.hidden = !createNewFile;
  poiAddNewFile.required = createNewFile;
}

function formatPoiFolderLabel(fileName) {
  return String(fileName || '')
    .replace(/[_-]+/g, ' ')
    .trim();
}

function ensurePoiAddFileOption(fileKey, displayLabel) {
  if (!poiAddFile || !fileKey) return;

  const labelText = String(displayLabel || '').trim() || formatPoiFolderLabel(fileKey) || fileKey;
  const existingOption = Array.from(poiAddFile.options).find((option) => option.value === fileKey);

  if (existingOption) {
    existingOption.textContent = labelText;
    return;
  }

  const option = document.createElement('option');
  option.value = fileKey;
  option.textContent = labelText;

  const newFileOption = Array.from(poiAddFile.options).find((entry) => entry.value === newPoiFileValue);
  if (newFileOption) {
    poiAddFile.insertBefore(option, newFileOption);
  } else {
    poiAddFile.appendChild(option);
  }
}

function openPoiAddOverlayWithTarget(coords) {
  if (!poiAddOverlay) return;

  setPoiAddOverlayMode('add', null);
  setPoiAddTarget(coords || null);
  setPoiAddStatus(coords ? '' : 'Déclenche un appui long sur la carte pour définir la position.', false);
  poiAddOverlay.classList.add('show');

  if (poiAddLabel) {
    poiAddLabel.focus();
  }
}

function openPoiModifyOverlay(context) {
  if (!poiAddOverlay) return;

  const safeContext = context && typeof context === 'object' ? context : {};
  setPoiAddOverlayMode('modify', {
    file: String(safeContext.file || ''),
    pointId: String(safeContext.pointId || ''),
    label: String(safeContext.label || ''),
    coords: safeContext.coords && typeof safeContext.coords === 'object'
      ? {
          lat: safeContext.coords.lat,
          lng: safeContext.coords.lng,
        }
      : null,
  });
  setPoiAddTarget(safeContext.coords || null);
  setPoiAddStatus('', false);
  poiAddOverlay.classList.add('show');

  if (poiAddLabel) {
    poiAddLabel.value = String(safeContext.label || '');
    poiAddLabel.focus();
    poiAddLabel.select();
  }
}

function closePoiAddOverlayPanel() {
  if (!poiAddOverlay) return;

  poiAddOverlay.classList.remove('show');
  setPoiAddStatus('', false);
  poiModifyContext = null;
  setPoiAddOverlayMode('add', null);
}

function setPoiDeleteStatus(message, isError) {
  if (!poiDeleteStatus) return;

  poiDeleteStatus.textContent = message || '';
  poiDeleteStatus.classList.toggle('is-error', !!isError);
}

function setPoiDeleteBusy(isBusy) {
  poiDeleteBusy = !!isBusy;

  if (poiDeleteConfirm) {
    poiDeleteConfirm.disabled = poiDeleteBusy;
  }

  if (poiDeleteCancel) {
    poiDeleteCancel.disabled = poiDeleteBusy;
  }

  if (closePoiDeleteOverlay) {
    closePoiDeleteOverlay.style.pointerEvents = poiDeleteBusy ? 'none' : 'auto';
  }
}

function setPoiFolderRenameStatus(message, isError) {
  if (!poiFolderRenameStatus) return;

  poiFolderRenameStatus.textContent = message || '';
  poiFolderRenameStatus.classList.toggle('is-error', !!isError);
}

function setPoiFolderRenameBusy(isBusy) {
  poiFolderRenameBusy = !!isBusy;

  if (poiFolderRenameConfirm) {
    poiFolderRenameConfirm.disabled = poiFolderRenameBusy;
  }

  if (poiFolderRenameCancel) {
    poiFolderRenameCancel.disabled = poiFolderRenameBusy;
  }

  if (closePoiFolderRenameOverlay) {
    closePoiFolderRenameOverlay.style.pointerEvents = poiFolderRenameBusy ? 'none' : 'auto';
  }
}

function closePoiDeleteOverlayPanel(forceClose) {
  if (!poiDeleteOverlay || (poiDeleteBusy && !forceClose)) return;

  poiDeleteOverlay.classList.remove('show');
  poiDeleteAction = null;
  poiDeleteContext = null;
  setPoiDeleteBusy(false);
  setPoiDeleteStatus('', false);
}

function openPoiDeleteOverlay(context, onConfirm) {
  if (!poiDeleteOverlay) return;

  const safeContext = context && typeof context === 'object' ? context : {};
  poiDeleteContext = safeContext;
  poiDeleteAction = typeof onConfirm === 'function' ? onConfirm : null;
  setPoiDeleteBusy(false);
  setPoiDeleteStatus('', false);

  if (poiDeleteQuestion) {
    const itemType = safeContext.type === 'folder' ? 'dossier' : 'point';
    poiDeleteQuestion.textContent = safeContext.label
      ? 'Êtes-vous sûr de vouloir supprimer le ' + itemType + ' "' + safeContext.label + '" ?'
      : 'Êtes-vous sûr de vouloir supprimer ce ' + itemType + ' ?';
  }

  poiDeleteOverlay.classList.add('show');

  if (poiDeleteCancel) {
    poiDeleteCancel.focus();
  } else if (poiDeleteConfirm) {
    poiDeleteConfirm.focus();
  }
}

function closePoiFolderRenameOverlayPanel(forceClose) {
  if (!poiFolderRenameOverlay || (poiFolderRenameBusy && !forceClose)) return;

  poiFolderRenameOverlay.classList.remove('show');
  poiFolderRenameAction = null;
  setPoiFolderRenameBusy(false);
  setPoiFolderRenameStatus('', false);
}

function openPoiFolderRenameOverlay(context, onConfirm) {
  if (!poiFolderRenameOverlay) return;

  const safeContext = context && typeof context === 'object' ? context : {};
  poiFolderRenameAction = typeof onConfirm === 'function' ? onConfirm : null;
  setPoiFolderRenameBusy(false);
  setPoiFolderRenameStatus('', false);

  if (poiFolderRenameTitle) {
    poiFolderRenameTitle.textContent = 'Renommer un dossier';
  }

  if (poiFolderRenameSummary) {
    poiFolderRenameSummary.textContent = safeContext.label
      ? 'Ancien nom : "' + safeContext.label + '"'
      : 'Saisis le nouveau nom du dossier.';
  }

  if (poiFolderRenameInput) {
    poiFolderRenameInput.value = safeContext.label || '';
  }

  poiFolderRenameOverlay.classList.add('show');

  if (poiFolderRenameInput) {
    poiFolderRenameInput.focus();
    poiFolderRenameInput.select();
  }
}

function selectMap(file, overlay) {
  currentMapFile = file;
  loadMap(file, currentCenter, currentZoom);
  updateTitle(file);
  persistSettings(file, currentCenter, currentZoom);
  overlay.classList.remove('show');
}

function closeOverlay(panel, afterClose) {
  if (!panel) return false;

  const wasOpen = panel.classList.contains('show');
  panel.classList.remove('show');

  if (wasOpen && typeof afterClose === 'function') {
    afterClose();
  }

  return wasOpen;
}

function closeVisibleOverlays() {
  const closedMapOverlay = closeOverlay(mapOverlay);
  const closedSearchOverlay = closeOverlay(schOverlay);
  const closedPoiOverlay = closeOverlay(poiOverlay);
  const closedPoiAddOverlay = closeOverlay(poiAddOverlay, closePoiAddOverlayPanel);
  const closedPoiFolderRenameOverlay = closeOverlay(poiFolderRenameOverlay, closePoiFolderRenameOverlayPanel);
  const closedPoiDeleteOverlay = closeOverlay(poiDeleteOverlay, closePoiDeleteOverlayPanel);

  return closedMapOverlay || closedSearchOverlay || closedPoiOverlay || closedPoiAddOverlay || closedPoiFolderRenameOverlay || closedPoiDeleteOverlay;
}

// Fonction pour charger une carte
function loadMap(file, center, zoom) {
  const targetZoom = normalizeZoom(zoom, currentZoom);
  const targetCenter = normalizeCenter(center, currentCenter);
  const centerQuery = '&lat=' + encodeURIComponent(String(targetCenter.lat)) + '&lng=' + encodeURIComponent(String(targetCenter.lng));

  if(file.endsWith('.js')){
    iframe.src = "iframe/map_base_online.html?tile=" + encodeURIComponent("../data/maps/maps_online/" + file) + "&zoom=" + encodeURIComponent(String(targetZoom)) + centerQuery;
  } else if(file.endsWith('.txt')){
    iframe.src = "iframe/map_base_local.html?tile=" + encodeURIComponent("../data/maps/maps_local/" + file) + "&zoom=" + encodeURIComponent(String(targetZoom)) + centerQuery;
  }
}

function persistSettings(file, center, zoom) {
  const targetZoom = normalizeZoom(zoom, currentZoom);
  const targetCenter = normalizeCenter(center, currentCenter);

  localStorage.setItem('selectedMap', file);
  localStorage.setItem('selectedZoom', String(targetZoom));
  localStorage.setItem('selectedCenterLat', String(targetCenter.lat));
  localStorage.setItem('selectedCenterLng', String(targetCenter.lng));

  fetch('?action=settings', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      selectedMap: file,
      zoom: targetZoom,
      centerLat: targetCenter.lat,
      centerLng: targetCenter.lng,
    })
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

if (openPoiAddOverlay) {
  openPoiAddOverlay.addEventListener('click', () => {
    openPoiAddOverlayWithTarget(null);
  });
}

// Fermer l'overlay choix des cartes
closeMapOverlay.addEventListener('click', () => {
  closeOverlay(mapOverlay);
});

// Fermer l'overlay de recherche
closeSchOverlay.addEventListener('click', () => {
  closeOverlay(schOverlay);
});

// Fermer l'overlay des points d'intérêt
closePoiOverlay.addEventListener('click', () => {
  closeOverlay(poiOverlay);
});

if (closePoiAddOverlay) {
  closePoiAddOverlay.addEventListener('click', closePoiAddOverlayPanel);
}

if (poiAddCancel) {
  poiAddCancel.addEventListener('click', closePoiAddOverlayPanel);
}

if (closePoiFolderRenameOverlay) {
  closePoiFolderRenameOverlay.addEventListener('click', closePoiFolderRenameOverlayPanel);
}

if (poiFolderRenameCancel) {
  poiFolderRenameCancel.addEventListener('click', closePoiFolderRenameOverlayPanel);
}

if (closePoiDeleteOverlay) {
  closePoiDeleteOverlay.addEventListener('click', closePoiDeleteOverlayPanel);
}

if (poiDeleteCancel) {
  poiDeleteCancel.addEventListener('click', closePoiDeleteOverlayPanel);
}

if (poiDeleteConfirm) {
  poiDeleteConfirm.addEventListener('click', async () => {
    if (poiDeleteBusy || !poiDeleteAction) {
      return;
    }

    setPoiDeleteBusy(true);
    setPoiDeleteStatus(poiDeleteContext && poiDeleteContext.busyMessage ? poiDeleteContext.busyMessage : 'Suppression en cours...', false);

    try {
      const deleteFile = poiDeleteContext && poiDeleteContext.file ? poiDeleteContext.file : '';
      const deleteType = poiDeleteContext && poiDeleteContext.type === 'folder' ? 'folder' : 'point';
      await poiDeleteAction();
      setPoiDeleteStatus(poiDeleteContext && poiDeleteContext.successMessage ? poiDeleteContext.successMessage : (deleteType === 'folder' ? 'Dossier supprimé.' : 'Point supprimé.'), false);
      closePoiDeleteOverlayPanel(true);

      if (window.poiBridge) {
        if (deleteType === 'folder' && typeof window.poiBridge.removeFile === 'function') {
          window.poiBridge.removeFile(deleteFile);
        } else if (deleteFile && typeof window.poiBridge.refreshFile === 'function') {
          window.poiBridge.refreshFile(deleteFile);
        }
      }
    } catch (error) {
      setPoiDeleteStatus(poiDeleteContext && poiDeleteContext.errorMessage ? poiDeleteContext.errorMessage : (poiDeleteContext && poiDeleteContext.type === 'folder' ? 'Impossible de supprimer ce dossier.' : 'Impossible de supprimer ce point.'), true);
      setPoiDeleteBusy(false);
    }
  });
}

if (poiFolderRenameForm) {
  poiFolderRenameForm.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (poiFolderRenameBusy || !poiFolderRenameAction) {
      return;
    }

    const label = poiFolderRenameInput ? poiFolderRenameInput.value.trim() : '';
    if (!label) {
      setPoiFolderRenameStatus('Le nom du dossier est obligatoire.', true);
      if (poiFolderRenameInput) {
        poiFolderRenameInput.focus();
      }
      return;
    }

    setPoiFolderRenameBusy(true);
    setPoiFolderRenameStatus('Renommage en cours...', false);

    try {
      await poiFolderRenameAction(label);
      setPoiFolderRenameStatus('Dossier renommé. Rechargement...', false);
      closePoiFolderRenameOverlayPanel(true);
      window.location.reload();
    } catch (error) {
      setPoiFolderRenameStatus('Impossible de renommer ce dossier.', true);
      setPoiFolderRenameBusy(false);
    }
  });
}

document.addEventListener('keydown', (event) => {
  if (event.key !== 'Escape') {
    return;
  }

  if (!closeVisibleOverlays()) {
    return;
  }

  event.preventDefault();
  event.stopPropagation();
});

if (poiAddFile) {
  poiAddFile.addEventListener('change', updatePoiAddFileMode);
}

if (poiAddForm) {
  poiAddForm.addEventListener('submit', (event) => {
    event.preventDefault();

    const label = poiAddLabel ? poiAddLabel.value.trim() : '';

    if (!label) {
      setPoiAddStatus('Le nom du point est obligatoire.', true);
      if (poiAddLabel) {
        poiAddLabel.focus();
      }
      return;
    }

    if (poiAddMode === 'modify') {
      const pointId = poiModifyContext && poiModifyContext.pointId ? poiModifyContext.pointId : '';
      const file = poiModifyContext && poiModifyContext.file ? poiModifyContext.file : '';

      if (!file || !pointId) {
        setPoiAddStatus('Impossible d’identifier le point à modifier.', true);
        return;
      }

      setPoiAddStatus('Enregistrement en cours...', false);

      fetch('?action=modify', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          file: file,
          pointId: pointId,
          label: label,
        }),
      })
        .then((response) => response.json().then((payload) => ({ response, payload })))
        .then(({ response, payload }) => {
          if (!response.ok || !payload || payload.ok !== true) {
            throw new Error((payload && payload.error) || 'save_failed');
          }

          setPoiAddStatus('Point modifié.', false);
          closePoiAddOverlayPanel();

          if (window.poiBridge && typeof window.poiBridge.refreshFile === 'function') {
            window.poiBridge.refreshFile(file);
          }
        })
        .catch(() => {
          setPoiAddStatus('Impossible d’enregistrer la modification.', true);
        });

      return;
    }

    const lat = poiAddLat ? parseFloat(poiAddLat.value) : NaN;
    const lng = poiAddLng ? parseFloat(poiAddLng.value) : NaN;
    const file = poiAddFile ? poiAddFile.value : '';
    const newFile = poiAddNewFile ? poiAddNewFile.value.trim() : '';

    if (Number.isNaN(lat) || Number.isNaN(lng)) {
      setPoiAddStatus('Ajoute d’abord un point par appui long sur la carte.', true);
      return;
    }

    if (file === newPoiFileValue && !newFile) {
      setPoiAddStatus('Le nom du nouveau dossier est obligatoire.', true);
      if (poiAddNewFile) {
        poiAddNewFile.focus();
      }
      return;
    }

    setPoiAddStatus('Enregistrement en cours...', false);

    fetch('?action=add', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        file: file,
        newFile: file === newPoiFileValue ? newFile : '',
        label: label,
        lat: lat,
        lng: lng,
      }),
    })
      .then((response) => response.json().then((payload) => ({ response, payload })))
      .then(({ response, payload }) => {
        if (!response.ok || !payload || payload.ok !== true) {
          throw new Error((payload && payload.error) || 'save_failed');
        }

        setPoiAddStatus('Point ajouté.', false);
        closePoiAddOverlayPanel();

        const createdFile = payload.file || file;
        const isNewFolder = file === newPoiFileValue;

        if (isNewFolder) {
          ensurePoiAddFileOption(createdFile, newFile || createdFile);

          if (window.poiBridge && typeof window.poiBridge.registerFile === 'function') {
            window.poiBridge.registerFile(createdFile, {
              label: newFile || createdFile,
              folderVisible: true,
            });
          }

          if (poiAddFile) {
            poiAddFile.value = createdFile;
          }
        }

        if (window.poiBridge && typeof window.poiBridge.refreshFile === 'function') {
          window.poiBridge.refreshFile(createdFile);
        }
      })
      .catch(() => {
        setPoiAddStatus('Impossible d’enregistrer le point.', true);
      });
  });
}

window.poiAddBridge = {
  openAddOverlay: openPoiAddOverlayWithTarget,
  openModifyOverlay: openPoiModifyOverlay,
  close: closePoiAddOverlayPanel,
  openDeleteOverlay: openPoiDeleteOverlay,
  openFolderRenameOverlay: openPoiFolderRenameOverlay,
};

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
loadMap(currentMapFile, currentCenter, currentZoom);
updateTitle(currentMapFile);

function syncMapStateFromMessage(data) {
  if (!data) return;

  if (data.center) {
    currentCenter = normalizeCenter(data.center, currentCenter);
  }

  if (data.value != null && (data.type === 'zoom' || data.type === 'view')) {
    currentZoom = normalizeZoom(data.value, currentZoom);
  }

  if (currentMapFile) {
    persistSettings(currentMapFile, currentCenter, currentZoom);
  }
}

// Recevoir l'état de l'iframe
window.addEventListener("message", (event) => {
  const data = event.data || {};

  if(data.type === "zoom") {
    currentZoom = normalizeZoom(data.value, currentZoom);
    mapZoom.textContent = "Zoom : " + currentZoom;
    if (currentMapFile) {
      persistSettings(currentMapFile, currentCenter, currentZoom);
    }
  } else if (data.type === 'center') {
    currentCenter = normalizeCenter(data.value, currentCenter);
    if (currentMapFile) {
      persistSettings(currentMapFile, currentCenter, currentZoom);
    }
  } else if (data.type === 'view') {
    currentCenter = normalizeCenter(data.center, currentCenter);
    currentZoom = normalizeZoom(data.zoom, currentZoom);
    mapZoom.textContent = "Zoom : " + currentZoom;
    if (currentMapFile) {
      persistSettings(currentMapFile, currentCenter, currentZoom);
    }
  } else if (data.type === 'poiAddRequested') {
    openPoiAddOverlayWithTarget({
      lat: data.lat,
      lng: data.lng,
    });
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

  postToIframe({ type: 'setView', center: currentCenter, zoom: currentZoom });

});

updatePoiAddFileMode();

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
