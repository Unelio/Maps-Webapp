(function () {
  const catalogNode = document.getElementById('poiCatalogData');
  let poiCatalog = [];

  if (catalogNode && catalogNode.textContent) {
    try {
      const parsed = JSON.parse(catalogNode.textContent);
      if (Array.isArray(parsed)) {
        poiCatalog = parsed;
      }
    } catch (error) {
      poiCatalog = [];
    }
  }

  const poiState = {};
  const overlay = document.getElementById('poiOverlay');
  const iframe = document.getElementById('mapFrame');
  const storageKey = 'poiStateByFile';

  function readPersistedState() {
    try {
      const raw = localStorage.getItem(storageKey);
      return raw ? JSON.parse(raw) : {};
    } catch (error) {
      return {};
    }
  }

  function writePersistedState() {
    try {
      localStorage.setItem(storageKey, JSON.stringify(poiState));
    } catch (error) {}
  }

  function escapeSelectorValue(value) {
    return String(value).replace(/\\/g, '\\\\').replace(/"/g, '\\"');
  }

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function normalizeSortValue(value) {
    return String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLocaleLowerCase('fr');
  }

  function comparePoiLabels(firstPoint, secondPoint) {
    const firstLabel = normalizeSortValue(firstPoint && firstPoint.label);
    const secondLabel = normalizeSortValue(secondPoint && secondPoint.label);

    const labelComparison = firstLabel.localeCompare(secondLabel, 'fr', {
      sensitivity: 'base',
      numeric: true,
    });

    if (labelComparison !== 0) {
      return labelComparison;
    }

    return normalizeSortValue(firstPoint && firstPoint.id).localeCompare(
      normalizeSortValue(secondPoint && secondPoint.id),
      'fr',
      { sensitivity: 'base', numeric: true }
    );
  }

  function renderEyeIcon(visible) {
    return '<span class="poi-eye-icon' + (visible ? '' : ' is-hidden') + '" aria-hidden="true"><span class="poi-eye-glyph"></span><span class="poi-eye-slash"></span></span>';
  }

  function cloneStateForFile(fileKey) {
    const catalogEntry = poiCatalog.find(entry => entry.file === fileKey);
    const persisted = readPersistedState()[fileKey] || {};

    return {
      expanded: false,
      loading: false,
      loaded: false,
      folderVisible: typeof persisted.folderVisible === 'boolean'
        ? persisted.folderVisible
        : !!(catalogEntry && catalogEntry.folderVisible),
      points: persisted.points && typeof persisted.points === 'object' ? persisted.points : {},
      pointOrder: Array.isArray(persisted.pointOrder) ? persisted.pointOrder : [],
    };
  }

  function ensureState() {
    poiCatalog.forEach(entry => {
      if (!poiState[entry.file]) {
        poiState[entry.file] = cloneStateForFile(entry.file);
      }
    });
  }

  function getFileEntry(fileKey) {
    return poiCatalog.find(entry => entry.file === fileKey) || null;
  }

  function getFileState(fileKey) {
    ensureState();
    return poiState[fileKey] || null;
  }

  function updateVisibilityCount(fileKey) {
    const container = overlay ? overlay.querySelector('.poi-file[data-file="' + escapeSelectorValue(fileKey) + '"]') : null;
    if (!container) return;

    const entry = getFileEntry(fileKey);
    const state = getFileState(fileKey);
    const countNode = container.querySelector('.poi-visible-count');
    const totalNode = container.querySelector('.poi-total-count');

    if (!state || !entry || !countNode || !totalNode) return;

    const visibleCount = state.loaded
      ? Object.values(state.points).filter(pointState => state.folderVisible && pointState.visible !== false).length
      : (state.folderVisible ? entry.visibleCount : 0);

    countNode.textContent = String(visibleCount);
    totalNode.textContent = String(entry.totalCount);
  }

  function pointIsVisible(state, point) {
    return !!state.folderVisible && point.visible !== false;
  }

  function applyFolderVisibilityToDom(fileKey) {
    const container = overlay ? overlay.querySelector('.poi-file[data-file="' + escapeSelectorValue(fileKey) + '"]') : null;
    const state = getFileState(fileKey);
    if (!container || !state) return;

    const list = container.querySelector('.poi-point-list');
    const eyeButton = container.querySelector('.poi-folder-toggle');

    if (!state.loaded) {
      if (eyeButton) {
        eyeButton.innerHTML = renderEyeIcon(state.folderVisible);
        eyeButton.setAttribute('aria-label', state.folderVisible ? 'Masquer tous les points' : 'Afficher tous les points');
        eyeButton.title = state.folderVisible ? 'Masquer tous les points' : 'Afficher tous les points';
      }

      updateVisibilityCount(fileKey);
      return;
    }

    if (list) {
      list.querySelectorAll('.poi-point').forEach(function (row) {
        const pointId = row.dataset.pointId;
        const pointState = state.points[pointId];
        const visible = pointState ? pointState.visible !== false : true;
        row.classList.toggle('is-hidden', !visible);
        const pointEye = row.querySelector('.poi-point-eye');
        if (pointEye) {
          pointEye.setAttribute('aria-pressed', visible ? 'true' : 'false');
          pointEye.innerHTML = renderEyeIcon(visible);
        }
      });
    }

    if (eyeButton) {
      eyeButton.innerHTML = renderEyeIcon(state.folderVisible);
      eyeButton.setAttribute('aria-label', state.folderVisible ? 'Masquer tous les points' : 'Afficher tous les points');
      eyeButton.title = state.folderVisible ? 'Masquer tous les points' : 'Afficher tous les points';
    }

    updateVisibilityCount(fileKey);
  }

  function persistFileState(fileKey) {
    const state = getFileState(fileKey);
    if (!state) return;

    writePersistedState();

    fetch('/index.php?action=save', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        file: fileKey,
        state: state,
      }),
    }).catch(() => {});
  }

  function renderPointRows(fileKey, points, options = {}) {
    const container = overlay ? overlay.querySelector('.poi-file[data-file="' + escapeSelectorValue(fileKey) + '"]') : null;
    const state = getFileState(fileKey);
    if (!container || !state) return;

    const list = container.querySelector('.poi-point-list');
    if (!list) return;

    const shouldExpand = options.shouldExpand !== false;

    list.innerHTML = '';
    state.points = {};
    state.pointOrder = [];

    const sortedPoints = points.slice().sort(comparePoiLabels);

    sortedPoints.forEach(function (point) {
      const pointVisible = point.visible !== false;
      state.points[point.id] = {
        visible: pointVisible,
        label: point.label,
        description: point.description,
        symbol: point.symbol,
        time: point.time,
        lat: point.lat,
        lng: point.lng,
      };
      state.pointOrder.push(point.id);

      const item = document.createElement('li');
      item.className = 'poi-point' + (pointVisible ? '' : ' is-hidden');
      item.dataset.pointId = point.id;
      item.innerHTML = '<button class="poi-point-eye" type="button" aria-pressed="' + (pointVisible ? 'true' : 'false') + '" title="Afficher ou masquer">' + renderEyeIcon(pointVisible) + '</button>' +
        '<div class="poi-point-body">' +
        '<div class="poi-point-label">' + escapeHtml(point.label) + '</div>' +
        '<div class="poi-point-meta">' + escapeHtml(Number(point.lat).toFixed(6)) + ', ' + escapeHtml(Number(point.lng).toFixed(6)) + '</div>' +
        (point.description ? '<div class="poi-point-description">' + escapeHtml(point.description) + '</div>' : '') +
        '</div>';
      list.appendChild(item);
    });

    state.loaded = true;
    state.loading = false;
    list.dataset.loaded = 'true';
    list.classList.toggle('is-collapsed', !shouldExpand);
    state.expanded = shouldExpand;

    applyFolderVisibilityToDom(fileKey);
    syncFileToMap(fileKey);
    persistFileState(fileKey);
  }

  async function loadFileDetails(fileKey, options = {}) {
    const state = getFileState(fileKey);
    const entry = getFileEntry(fileKey);
    if (!state || !entry) return;

    const shouldExpand = options.shouldExpand !== false;

    if (state.loaded) {
      return;
    }

    if (state.loading) {
      return state.loading;
    }

    state.loading = fetch('/index.php?action=load&file=' + encodeURIComponent(fileKey), {
      headers: {
        'Accept': 'application/json',
      },
    })
      .then(function (response) {
        if (!response.ok) {
          throw new Error('Chargement impossible');
        }
        return response.json();
      })
      .then(function (payload) {
        if (!payload || !Array.isArray(payload.points)) {
          throw new Error('Chargement impossible');
        }

        state.folderVisible = payload.folderVisible !== false;
        renderPointRows(fileKey, payload.points, { shouldExpand: shouldExpand });
      })
      .catch(function () {
        state.loading = false;
      })
      .finally(function () {
        state.loading = false;
      });

    return state.loading;
  }

  function syncFileToMap(fileKey) {
    if (!window.mapBridge || typeof window.mapBridge.postToIframe !== 'function') {
      return;
    }

    const state = getFileState(fileKey);
    if (!state) {
      return;
    }

    if (!state.loaded) {
      return loadFileDetails(fileKey, { shouldExpand: false }).then(function () {
        return syncFileToMap(fileKey);
      });
    }

    const points = state.pointOrder.map(function (pointId) {
      const point = state.points[pointId];
      return {
        id: pointId,
        label: point.label,
        description: point.description,
        symbol: point.symbol,
        time: point.time,
        lat: point.lat,
        lng: point.lng,
        visible: pointIsVisible(state, point),
      };
    });

    window.mapBridge.postToIframe({
      type: 'poiSyncFile',
      file: fileKey,
      points: points,
    });
  }

  function syncAllPoiFiles() {
    ensureState();
    poiCatalog.forEach(entry => {
      syncFileToMap(entry.file);
    });
  }

  async function toggleFolderExpansion(fileKey) {
    const state = getFileState(fileKey);
    if (!state) return;

    const nextExpanded = !state.expanded;

    const container = overlay ? overlay.querySelector('.poi-file[data-file="' + escapeSelectorValue(fileKey) + '"]') : null;
    if (container) {
      const list = container.querySelector('.poi-point-list');
      if (list) {
        if (!state.loaded) {
          list.innerHTML = '<li class="poi-point poi-loading">Chargement...</li>';
          list.classList.remove('is-collapsed');
        } else {
          list.classList.toggle('is-collapsed', !nextExpanded);
        }
      }
    }

    if (!state.loaded) {
      await loadFileDetails(fileKey, { shouldExpand: true });
    }

    state.expanded = nextExpanded;
    if (container) {
      const list = container.querySelector('.poi-point-list');
      if (list) {
        list.classList.toggle('is-collapsed', !nextExpanded);
      }
    }

    persistFileState(fileKey);
  }

  function toggleFolderVisibility(fileKey) {
    const state = getFileState(fileKey);
    if (!state) return;

    state.folderVisible = !state.folderVisible;
    applyFolderVisibilityToDom(fileKey);
    syncFileToMap(fileKey);
    persistFileState(fileKey);
  }

  function togglePoint(fileKey, pointId) {
    const state = getFileState(fileKey);
    if (!state || !state.points[pointId]) return;

    state.points[pointId].visible = !state.points[pointId].visible;
    applyFolderVisibilityToDom(fileKey);
    syncFileToMap(fileKey);
    persistFileState(fileKey);
  }

  document.addEventListener('click', function (event) {
    const folderButton = event.target.closest('.poi-folder-toggle');
    if (folderButton && overlay && overlay.contains(folderButton)) {
      const fileNode = folderButton.closest('.poi-file');
      if (fileNode && fileNode.dataset.file) {
        toggleFolderVisibility(fileNode.dataset.file);
      }
      return;
    }

    const fileHeader = event.target.closest('.poi-file-header');
    if (fileHeader && overlay && overlay.contains(fileHeader)) {
      const fileNode = fileHeader.closest('.poi-file');
      if (fileNode && fileNode.dataset.file && !event.target.closest('.poi-folder-toggle')) {
        toggleFolderExpansion(fileNode.dataset.file);
      }
      return;
    }

    const eyeButton = event.target.closest('.poi-point-eye');
    if (eyeButton && overlay && overlay.contains(eyeButton)) {
      const pointNode = eyeButton.closest('.poi-point');
      const fileNode = eyeButton.closest('.poi-file');
      if (pointNode && fileNode && fileNode.dataset.file && pointNode.dataset.pointId) {
        togglePoint(fileNode.dataset.file, pointNode.dataset.pointId);
      }
    }
  });

  window.addEventListener('load', syncAllPoiFiles);

  if (iframe) {
    iframe.addEventListener('load', syncAllPoiFiles);
  }

  ensureState();
  poiCatalog.forEach(entry => {
    const state = getFileState(entry.file);
    if (state && !state.loaded && state.folderVisible) {
      loadFileDetails(entry.file, { shouldExpand: false });
      return;
    }
    updateVisibilityCount(entry.file);
  });

  window.poiBridge = {
    syncAll: syncAllPoiFiles,
    syncFile: syncFileToMap,
  };
})();