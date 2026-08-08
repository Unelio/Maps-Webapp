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
  let openPointMenuKey = null;
  let pointMenuElement = null;
  let pointMenuAnchor = null;
  let openFolderMenuKey = null;
  let folderMenuElement = null;
  let folderMenuAnchor = null;

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

  function createPointMenuContent() {
    return '' +
      '<button class="poi-point-menu-item" type="button" data-point-action="modify">Modifier</button>' +
      '<button class="poi-point-menu-item is-danger" type="button" data-point-action="delete">Supprimer</button>';
  }

  function createFolderMenuContent() {
    return '' +
      '<button class="poi-folder-menu-item" type="button" data-folder-action="rename">Renommer</button>' +
      '<button class="poi-folder-menu-item is-danger" type="button" data-folder-action="delete">Supprimer</button>';
  }

  function ensurePointMenuElement() {
    if (pointMenuElement) {
      return pointMenuElement;
    }

    pointMenuElement = document.createElement('div');
    pointMenuElement.className = 'poi-point-menu poi-point-menu-floating';
    pointMenuElement.hidden = true;
    pointMenuElement.setAttribute('role', 'menu');
    pointMenuElement.innerHTML = createPointMenuContent();
    overlay.appendChild(pointMenuElement);

    return pointMenuElement;
  }

  function ensureFolderMenuElement() {
    if (folderMenuElement) {
      return folderMenuElement;
    }

    folderMenuElement = document.createElement('div');
    folderMenuElement.className = 'poi-folder-menu poi-point-menu-floating';
    folderMenuElement.hidden = true;
    folderMenuElement.setAttribute('role', 'menu');
    folderMenuElement.innerHTML = createFolderMenuContent();
    overlay.appendChild(folderMenuElement);

    return folderMenuElement;
  }

  function resolvePointMenuPosition(button) {
    const menu = ensurePointMenuElement();
    const buttonRect = button.getBoundingClientRect();
    const menuRect = menu.getBoundingClientRect();
    const overlayRect = overlay.getBoundingClientRect();
    const margin = 8;
    const gap = 6;
    const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
    const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
    const overlayScrollLeft = overlay.scrollLeft || 0;
    const overlayScrollTop = overlay.scrollTop || 0;

    let left = buttonRect.right - menuRect.width;
    left = Math.max(margin, Math.min(left, viewportWidth - menuRect.width - margin));

    let top = buttonRect.bottom + gap;
    if (top + menuRect.height > viewportHeight - margin) {
      top = buttonRect.top - gap - menuRect.height;
    }

    top = Math.max(margin, Math.min(top, viewportHeight - menuRect.height - margin));

    menu.style.left = Math.round(left - overlayRect.left + overlayScrollLeft) + 'px';
    menu.style.top = Math.round(top - overlayRect.top + overlayScrollTop) + 'px';
  }

  function makePointMenuKey(fileKey, pointId) {
    return fileKey + '::' + pointId;
  }

  function makeFolderMenuKey(fileKey) {
    return fileKey;
  }

  function closePointMenu() {
    if (pointMenuAnchor) {
      pointMenuAnchor.setAttribute('aria-expanded', 'false');
    }

    if (pointMenuElement) {
      pointMenuElement.hidden = true;
    }

    openPointMenuKey = null;
    pointMenuAnchor = null;
  }

  function closeFolderMenu() {
    if (folderMenuAnchor) {
      folderMenuAnchor.setAttribute('aria-expanded', 'false');
    }

    if (folderMenuElement) {
      folderMenuElement.hidden = true;
    }

    openFolderMenuKey = null;
    folderMenuAnchor = null;
  }

  function openPointMenu(fileKey, pointId, triggerButton) {
    if (!overlay || !triggerButton) return;

    closePointMenu();

    const rowKey = makePointMenuKey(fileKey, pointId);
    const row = overlay.querySelector('.poi-point[data-point-key="' + escapeSelectorValue(rowKey) + '"]');
    if (!row) return;

    const button = row.querySelector('.poi-point-menu-toggle');
    const menu = ensurePointMenuElement();

    if (button) {
      button.setAttribute('aria-expanded', 'true');
    }

    menu.innerHTML = createPointMenuContent();
    menu.hidden = false;
    resolvePointMenuPosition(triggerButton);

    openPointMenuKey = rowKey;
    pointMenuAnchor = button || triggerButton;
  }

  function togglePointMenu(fileKey, pointId, triggerButton) {
    const rowKey = makePointMenuKey(fileKey, pointId);

    if (openPointMenuKey === rowKey) {
      closePointMenu();
      return;
    }

    openPointMenu(fileKey, pointId, triggerButton);
  }

  function resolveFolderMenuPosition(button) {
    const menu = ensureFolderMenuElement();
    const buttonRect = button.getBoundingClientRect();
    const menuRect = menu.getBoundingClientRect();
    const overlayRect = overlay.getBoundingClientRect();
    const margin = 8;
    const gap = 6;
    const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
    const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
    const overlayScrollLeft = overlay.scrollLeft || 0;
    const overlayScrollTop = overlay.scrollTop || 0;

    let left = buttonRect.right - menuRect.width;
    left = Math.max(margin, Math.min(left, viewportWidth - menuRect.width - margin));

    let top = buttonRect.bottom + gap;
    if (top + menuRect.height > viewportHeight - margin) {
      top = buttonRect.top - gap - menuRect.height;
    }

    top = Math.max(margin, Math.min(top, viewportHeight - menuRect.height - margin));

    menu.style.left = Math.round(left - overlayRect.left + overlayScrollLeft) + 'px';
    menu.style.top = Math.round(top - overlayRect.top + overlayScrollTop) + 'px';
  }

  function openFolderMenu(fileKey, triggerButton) {
    if (!overlay || !triggerButton) return;

    closePointMenu();
    closeFolderMenu();

    const row = overlay.querySelector('.poi-folder[data-file="' + escapeSelectorValue(fileKey) + '"]');
    if (!row) return;

    const button = row.querySelector('.poi-folder-menu-toggle');
    const menu = ensureFolderMenuElement();

    if (button) {
      button.setAttribute('aria-expanded', 'true');
    }

    menu.innerHTML = createFolderMenuContent();
    menu.hidden = false;
    resolveFolderMenuPosition(triggerButton);

    openFolderMenuKey = makeFolderMenuKey(fileKey);
    folderMenuAnchor = button || triggerButton;
  }

  function toggleFolderMenu(fileKey, triggerButton) {
    const rowKey = makeFolderMenuKey(fileKey);

    if (openFolderMenuKey === rowKey) {
      closeFolderMenu();
      return;
    }

    openFolderMenu(fileKey, triggerButton);
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
    const container = overlay ? overlay.querySelector('.poi-folder[data-file="' + escapeSelectorValue(fileKey) + '"]') : null;
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
    const container = overlay ? overlay.querySelector('.poi-folder[data-file="' + escapeSelectorValue(fileKey) + '"]') : null;
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

  function refreshFileDetails(fileKey) {
    const state = getFileState(fileKey);
    if (!state) return Promise.resolve();

    state.loaded = false;
    state.loading = false;
    state.points = {};
    state.pointOrder = [];

    return loadFileDetails(fileKey, { shouldExpand: state.expanded !== false });
  }

  async function mutatePoint(action, payload) {
    const response = await fetch('/index.php?action=' + encodeURIComponent(action), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(payload),
    });

    if (!response.ok) {
      throw new Error('Action impossible');
    }

    return response.json();
  }

  function modifyPoint(fileKey, pointId) {
    const state = getFileState(fileKey);
    const pointState = state && state.points ? state.points[pointId] : null;
    if (!pointState) return;

    if (!window.poiAddBridge || typeof window.poiAddBridge.openModifyOverlay !== 'function') {
      return;
    }

    closePointMenu();
    window.poiAddBridge.openModifyOverlay({
      file: fileKey,
      pointId: pointId,
      label: pointState.label || '',
      coords: {
        lat: pointState.lat,
        lng: pointState.lng,
      },
    });
  }

  async function deletePoint(fileKey, pointId) {
    const state = getFileState(fileKey);
    const pointState = state && state.points ? state.points[pointId] : null;
    const label = pointState && pointState.label ? pointState.label : 'ce point';

    if (!window.poiAddBridge || typeof window.poiAddBridge.openDeleteOverlay !== 'function') {
      return;
    }

    closePointMenu();
    window.poiAddBridge.openDeleteOverlay({
      file: fileKey,
      pointId: pointId,
      label: label,
    }, async function () {
      await mutatePoint('delete', {
        file: fileKey,
        pointId: pointId,
      });

      await refreshFileDetails(fileKey);
    });
  }

  function renderPointRows(fileKey, points, options = {}) {
    const container = overlay ? overlay.querySelector('.poi-folder[data-file="' + escapeSelectorValue(fileKey) + '"]') : null;
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
      item.dataset.pointKey = makePointMenuKey(fileKey, point.id);
      item.innerHTML = '<button class="poi-point-eye" type="button" aria-pressed="' + (pointVisible ? 'true' : 'false') + '" title="Afficher ou masquer">' + renderEyeIcon(pointVisible) + '</button>' +
        '<div class="poi-point-body">' +
        '<div class="poi-point-label">' + escapeHtml(point.label) + '</div>' +
        '<div class="poi-point-meta">' + escapeHtml(Number(point.lat).toFixed(6)) + ', ' + escapeHtml(Number(point.lng).toFixed(6)) + '</div>' +
        (point.description ? '<div class="poi-point-description">' + escapeHtml(point.description) + '</div>' : '') +
        '</div>';
      item.innerHTML +=
        '<div class="poi-point-actions">' +
        '<button class="poi-point-menu-toggle" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="Actions du point">⋮</button>' +
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
        entry.folderVisible = state.folderVisible;
        if (Number.isFinite(Number(payload.totalCount))) {
          entry.totalCount = Number(payload.totalCount);
        }
        if (Number.isFinite(Number(payload.visibleCount))) {
          entry.visibleCount = Number(payload.visibleCount);
        }
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

    const container = overlay ? overlay.querySelector('.poi-folder[data-file="' + escapeSelectorValue(fileKey) + '"]') : null;
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

  async function renameFolder(fileKey) {
    const entry = getFileEntry(fileKey);
    const currentLabel = entry && entry.label ? entry.label : fileKey.replace(/\.gpx$/i, '');
    if (!window.poiAddBridge || typeof window.poiAddBridge.openFolderRenameOverlay !== 'function') {
      return;
    }

    closeFolderMenu();

    window.poiAddBridge.openFolderRenameOverlay({
      file: fileKey,
      label: currentLabel,
    }, async function (newLabel) {
      const response = await fetch('/index.php?action=rename-folder', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          file: fileKey,
          newName: newLabel,
        }),
      });

      if (!response.ok) {
        throw new Error('Action impossible');
      }
    });
  }

  async function deleteFolder(fileKey) {
    const entry = getFileEntry(fileKey);
    const label = entry && entry.label ? entry.label : fileKey;
    if (!window.poiAddBridge || typeof window.poiAddBridge.openDeleteOverlay !== 'function') {
      return;
    }

    closeFolderMenu();

    window.poiAddBridge.openDeleteOverlay({
      type: 'folder',
      file: fileKey,
      label: label,
      successMessage: 'Dossier supprimé. Rechargement...',
      errorMessage: 'Impossible de supprimer ce dossier.',
      busyMessage: 'Suppression en cours...',
    }, async function () {
      const response = await fetch('/index.php?action=delete-folder', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          file: fileKey,
        }),
      });

      if (!response.ok) {
        throw new Error('Action impossible');
      }
    });
  }

  document.addEventListener('click', function (event) {
    const folderMenuToggle = event.target.closest('.poi-folder-menu-toggle');
    if (folderMenuToggle && overlay && overlay.contains(folderMenuToggle)) {
      closePointMenu();
      const fileNode = folderMenuToggle.closest('.poi-folder');
      if (fileNode && fileNode.dataset.file) {
        toggleFolderMenu(fileNode.dataset.file, folderMenuToggle);
      }
      return;
    }

    const pointMenuToggle = event.target.closest('.poi-point-menu-toggle');
    if (pointMenuToggle && overlay && overlay.contains(pointMenuToggle)) {
      closeFolderMenu();
      const pointNode = pointMenuToggle.closest('.poi-point');
      if (pointNode && pointNode.dataset.pointKey) {
        const [fileKey, ...pointIdParts] = pointNode.dataset.pointKey.split('::');
        togglePointMenu(fileKey, pointIdParts.join('::'), pointMenuToggle);
      }
      return;
    }

    const pointMenuAction = event.target.closest('.poi-point-menu [data-point-action]');
    if (pointMenuAction && pointMenuElement && pointMenuElement.contains(pointMenuAction)) {
      closeFolderMenu();
      const [fileKey, pointId] = openPointMenuKey ? openPointMenuKey.split('::') : ['',''];

      if (pointMenuAction.dataset.pointAction === 'modify') {
        modifyPoint(fileKey, pointId);
      }

      if (pointMenuAction.dataset.pointAction === 'delete') {
        deletePoint(fileKey, pointId).catch(function () {
          console.error('Impossible de supprimer ce point.');
        });
      }
      return;
    }

    const folderMenuAction = event.target.closest('.poi-folder-menu [data-folder-action]');
    if (folderMenuAction && folderMenuElement && folderMenuElement.contains(folderMenuAction)) {
      const fileKey = openFolderMenuKey || '';

      if (folderMenuAction.dataset.folderAction === 'rename') {
        renameFolder(fileKey).catch(function () {
          console.error('Impossible de renommer ce dossier.');
        });
      }

      if (folderMenuAction.dataset.folderAction === 'delete') {
        deleteFolder(fileKey).catch(function () {
          console.error('Impossible de supprimer ce dossier.');
        });
      }
      return;
    }

    if (openPointMenuKey && !event.target.closest('.poi-point-menu') && !event.target.closest('.poi-point-menu-toggle')) {
      closePointMenu();
    }

    if (openFolderMenuKey && !event.target.closest('.poi-folder-menu') && !event.target.closest('.poi-folder-menu-toggle')) {
      closeFolderMenu();
    }

    const folderButton = event.target.closest('.poi-folder-toggle');
    if (folderButton && overlay && overlay.contains(folderButton)) {
      closeFolderMenu();
      closePointMenu();
      const fileNode = folderButton.closest('.poi-folder');
      if (fileNode && fileNode.dataset.file) {
        toggleFolderVisibility(fileNode.dataset.file);
      }
      return;
    }

    const fileHeader = event.target.closest('.poi-folder-header');
    if (fileHeader && overlay && overlay.contains(fileHeader)) {
      if (event.target.closest('.poi-folder-menu-toggle') || event.target.closest('.poi-folder-menu')) {
        return;
      }

      closePointMenu();
      closeFolderMenu();
      const fileNode = fileHeader.closest('.poi-folder');
      if (fileNode && fileNode.dataset.file && !event.target.closest('.poi-folder-toggle')) {
        toggleFolderExpansion(fileNode.dataset.file);
      }
      return;
    }

    const eyeButton = event.target.closest('.poi-point-eye');
    if (eyeButton && overlay && overlay.contains(eyeButton)) {
      closePointMenu();
      const pointNode = eyeButton.closest('.poi-point');
      const fileNode = eyeButton.closest('.poi-folder');
      if (pointNode && fileNode && fileNode.dataset.file && pointNode.dataset.pointId) {
        togglePoint(fileNode.dataset.file, pointNode.dataset.pointId);
      }
    }
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closePointMenu();
      closeFolderMenu();
    }
  });

  window.addEventListener('resize', function () {
    if (pointMenuElement && !pointMenuElement.hidden && pointMenuAnchor) {
      resolvePointMenuPosition(pointMenuAnchor);
    }

    if (folderMenuElement && !folderMenuElement.hidden && folderMenuAnchor) {
      resolveFolderMenuPosition(folderMenuAnchor);
    }
  });

  document.addEventListener('scroll', function () {
    if (openPointMenuKey || openFolderMenuKey) {
      closePointMenu();
      closeFolderMenu();
    }
  }, true);

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