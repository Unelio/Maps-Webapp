<?php

class AppController
{
  private string $baseDir;

  public function __construct(string $baseDir)
  {
    $this->baseDir = $baseDir;
  }

  public function run(): void
  {
    $env = $this->parseEnv();

    if ($this->handleAction($env)) {
      return;
    }

    if (!$this->ensureAuthenticated($env)) {
      return;
    }

    $this->renderHomePage($env);
  }

  private function parseEnv(): array
  {
    $envParser = new EnvParser();

    return $envParser->parse($this->baseDir . '/.env');
  }

  private function readBooleanEnv(array $env, string $name, bool $default = false): bool
  {
    if (!array_key_exists($name, $env)) {
      return $default;
    }

    $value = filter_var($env[$name], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($value === null) {
      return $default;
    }

    return $value;
  }

  private function handleAction(array $env): bool
  {
    $action = $_GET['action'] ?? null;

    if ($action === 'tile') {
      $tileServer = new MbtilesTileServer($this->baseDir, $env);
      $tileServer->serve((string)($_GET['file'] ?? ''), (string)($_GET['map'] ?? ''));

      return true;
    }

    if (!in_array($action, ['load', 'save', 'add', 'modify', 'delete', 'rename-folder', 'delete-folder', 'search', 'settings'], true)) {
      return false;
    }

    header('Content-Type: application/json; charset=utf-8');

    try {
      if ($action === 'search') {
        $query = trim((string)filter_input(INPUT_GET, 'q', FILTER_UNSAFE_RAW));
        $searchManager = new SearchManager();

        echo json_encode($searchManager->search($query), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return true;
      }

      if ($action === 'settings') {
        $this->handleSettingsAction($_SERVER['REQUEST_METHOD'] ?? 'GET');
        return true;
      }

      if ($action === 'add' || $action === 'modify') {
        $poiWriter = new PoiWriterManager($this->baseDir);

        if ($poiWriter->dispatchAction($action, $_SERVER['REQUEST_METHOD'] ?? 'GET')) {
          return true;
        }
      }

      if ($action === 'delete') {
        $poiDeleteManager = new PoiDeleteManager($this->baseDir);

        if ($poiDeleteManager->dispatchAction($action, $_SERVER['REQUEST_METHOD'] ?? 'GET')) {
          return true;
        }
      }

      if ($action === 'rename-folder' || $action === 'delete-folder') {
        $poiFolderManager = new PoiFolderManager($this->baseDir);

        if ($poiFolderManager->dispatchAction($action, $_SERVER['REQUEST_METHOD'] ?? 'GET')) {
          return true;
        }
      }

      $poiManager = new PoiManager($this->baseDir);
      if ($poiManager->dispatchAction($action, $_SERVER['REQUEST_METHOD'] ?? 'GET')) {
        return true;
      }
    } catch (InvalidArgumentException $e) {
      http_response_code(400);
      echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      return true;
    } catch (Throwable $e) {
      http_response_code(502);
      echo json_encode([
        'error' => 'request_failed',
        'message' => $e->getMessage(),
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      return true;
    }

    return true;
  }

  private function ensureAuthenticated(array $env): bool
  {
    if (!isset($_SESSION['maps']) && isset($_COOKIE['maps_auth'])) {
      $_SESSION['maps'] = true;
    }

    if (!empty($_SESSION['maps'])) {
      return true;
    }

    $login = (string)($env['LOGIN'] ?? '');
    $password = (string)($env['PASSWORD'] ?? '');
    $inputLogin = (string)($_POST['login'] ?? '');
    $inputPassword = (string)($_POST['password'] ?? '');

    if ($inputLogin === $login && $inputPassword === $password) {
      $_SESSION['maps'] = true;
      setcookie('maps_auth', '1', time() + (86400 * 365 * 2), '/');

      header('Location: /');
      exit;
    }

    $error = '';
    if ($inputLogin !== '' || $inputPassword !== '') {
      $error = 'Identifiant ou mot de passe incorrect';
    }

    $templateRenderer = new TemplateRenderer();
    $errorHtml = $error !== '' ? '<p id="error">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>' : '';

    echo $templateRenderer->render($this->baseDir . '/assets/html/login.html', [
      'ERROR_HTML' => $errorHtml,
    ]);

    return false;
  }

  private function renderHomePage(array $env): void
  {
    $mapManager = new MapManager($this->baseDir, $env);
    $maps = $mapManager->getMaps();
    $settings = $this->readSettings();
    $defaultMap = $this->resolveDefaultMap($mapManager, $maps, $settings);
    $defaultZoom = $this->resolveDefaultZoom($settings);
    $defaultCenter = $this->resolveDefaultCenter($settings);
    $showEmptyFolders = $this->readBooleanEnv($env, 'SHOW_EMPTY_GPX', false);

    $poiManager = new PoiManager($this->baseDir);
    $poiFiles = $poiManager->getCatalog($showEmptyFolders);

    $templateRenderer = new TemplateRenderer();

    echo $templateRenderer->render($this->baseDir . '/assets/html/index.html', [
      'ASSET_VERSION' => time(),
      'DEFAULT_MAP_JSON' => json_encode($defaultMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'DEFAULT_ZOOM_JSON' => json_encode($defaultZoom, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'DEFAULT_CENTER_JSON' => json_encode($defaultCenter, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'MAPS_COUNT' => (string)count($maps),
      'MAP_OPTIONS' => $this->buildMapOptionsHtml($maps),
      'POI_PANEL' => $this->buildPoiPanelHtml($poiFiles),
      'POI_ADD_OVERLAY' => $this->buildPoiAddOverlayHtml($poiFiles),
      'POI_FOLDER_RENAME_OVERLAY' => $this->buildPoiFolderRenameOverlayHtml(),
      'POI_DELETE_OVERLAY' => $this->buildPoiDeleteOverlayHtml(),
    ]);
  }

  private function buildPoiPanelHtml(array $poiFiles): string
  {
    $poiPanelHtml = '<div class="poi-panel">'
      . '<div class="poi-header">'
      . '<h2>Points d\'intérêt</h2>'
      . '<div class="poi-summary">' . count($poiFiles) . ' dossier(s)</div>'
      . '</div>';

    if (empty($poiFiles)) {
      $poiPanelHtml .= '<div class="poi-empty">Aucun GPX trouvé dans le dossier data.</div>';
    } else {
      $poiPanelHtml .= '<div class="poi-tree-scroll"><div class="poi-tree" id="poiTree">';

      foreach ($poiFiles as $fileIndex => $fileData) {
        $fileId = 'poi-file-' . $fileIndex;
        $fileName = htmlspecialchars((string)$fileData['file'], ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars((string)$fileData['label'], ENT_QUOTES, 'UTF-8');
        $visibleCount = (int)($fileData['visibleCount'] ?? 0);
        $totalCount = (int)($fileData['totalCount'] ?? 0);
        $folderVisible = !empty($fileData['folderVisible']);
        $ariaLabel = $folderVisible ? 'Masquer tous les points' : 'Afficher tous les points';
        $eyeClass = $folderVisible ? '' : ' is-hidden';

        $poiPanelHtml .= '<section class="poi-folder" data-file="' . $fileName . '">'
          . '<div class="poi-folder-header">'
          . '<button class="poi-folder-toggle" type="button" aria-label="' . $ariaLabel . '" title="' . $ariaLabel . '"><span class="poi-eye-icon' . $eyeClass . '" aria-hidden="true"><span class="poi-eye-glyph"></span><span class="poi-eye-slash"></span></span></button>'
          . '<div class="poi-folder-meta">'
          . '<div class="poi-folder-title">' . $label . '</div>'
          . '<div class="poi-folder-count"><span class="poi-visible-count">' . $visibleCount . '</span>/<span class="poi-total-count">' . $totalCount . '</span> points</div>'
          . '</div>'
          . '<div class="poi-folder-actions">'
          . '<button class="poi-folder-menu-toggle" type="button" aria-haspopup="menu" aria-expanded="false" aria-label="Actions du dossier">⋮</button>'
          . '</div>'
          . '</div>'
          . '<ul class="poi-point-list is-collapsed" id="' . htmlspecialchars($fileId, ENT_QUOTES, 'UTF-8') . '" data-loaded="false"></ul>'
          . '</section>';
      }

      $poiPanelHtml .= '</div></div>';
    }

    $poiPanelHtml .= '</div>';
    $poiPanelHtml .= '<script id="poiCatalogData" type="application/json">' . json_encode($poiFiles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';

    return $poiPanelHtml;
  }

  private function buildPoiAddOverlayHtml(array $poiFiles): string
  {
    $fileOptionsHtml = $this->buildPoiFileOptionsHtml($poiFiles);

    return '<div id="poiAddOverlay">'
      . '<span id="closePoiAddOverlay">&times;</span>'
      . '<div class="poi-add-panel">'
      . '<div class="poi-add-header">'
      . '<div>'
      . '<h2>Ajouter un point</h2>'
      . '<div class="poi-add-summary" id="poiAddCoords">Appui long sur la carte pour choisir une position.</div>'
      . '</div>'
      . '</div>'
      . '<form id="poiAddForm">'
      . '<label class="poi-add-field">'
      . '<span>Nom du point</span>'
      . '<input id="poiAddLabel" type="text" maxlength="120" autocomplete="off" placeholder="Nom du point" required>'
      . '</label>'
      . '<label class="poi-add-field">'
      . '<span>Dossier</span>'
      . '<select id="poiAddFile" required>'
      . $fileOptionsHtml
      . '</select>'
      . '</label>'
      . '<label class="poi-add-field" id="poiAddNewFileWrap" hidden>'
      . '<span>Nouveau dossier</span>'
      . '<input id="poiAddNewFile" type="text" maxlength="120" autocomplete="off" placeholder="Nom du nouveau dossier">'
      . '</label>'
      . '<input id="poiAddLat" type="hidden">'
      . '<input id="poiAddLng" type="hidden">'
      . '<div class="poi-add-actions">'
      . '<button type="button" class="poi-delete-cancel" id="poiAddCancel">Annuler</button>'
      . '<button type="submit" class="poi-add-submit">Ajouter</button>'
      . '</div>'
      . '<div id="poiAddStatus" class="poi-add-status" aria-live="polite"></div>'
      . '</form>'
      . '</div>'
      . '</div>';
  }

  private function buildPoiDeleteOverlayHtml(): string
  {
    return '<div id="poiDeleteOverlay" role="dialog" aria-modal="true" aria-labelledby="poiDeleteTitle">'
      . '<span id="closePoiDeleteOverlay">&times;</span>'
      . '<div class="poi-delete-panel">'
      . '<div class="poi-delete-header">'
      . '<div>'
      . '<h2 id="poiDeleteTitle">Supprimer un point</h2>'
      . '</div>'
      . '</div>'
      . '<div class="poi-delete-body">'
      . '<p class="poi-delete-question" id="poiDeleteQuestion">Êtes-vous sûr de vouloir supprimer ce point ?</p>'
      . '<div id="poiDeleteStatus" class="poi-delete-status" aria-live="polite"></div>'
      . '</div>'
      . '<div class="poi-delete-actions">'
      . '<button type="button" class="poi-delete-cancel" id="poiDeleteCancel">Annuler</button>'
      . '<button type="button" class="poi-delete-confirm" id="poiDeleteConfirm">Supprimer</button>'
      . '</div>'
      . '</div>'
      . '</div>';
  }

  private function buildPoiFolderRenameOverlayHtml(): string
  {
    return '<div id="poiFolderRenameOverlay" role="dialog" aria-modal="true" aria-labelledby="poiFolderRenameTitle">'
      . '<span id="closePoiFolderRenameOverlay">&times;</span>'
      . '<div class="poi-add-panel">'
      . '<div class="poi-add-header">'
      . '<div>'
      . '<h2 id="poiFolderRenameTitle">Renommer un dossier</h2>'
      . '<div class="poi-add-summary" id="poiFolderRenameSummary">Saisis le nouveau nom du dossier.</div>'
      . '</div>'
      . '</div>'
      . '<form id="poiFolderRenameForm">'
      . '<label class="poi-add-field">'
      . '<span>Nouveau nom du dossier</span>'
      . '<input id="poiFolderRenameInput" type="text" maxlength="120" autocomplete="off" placeholder="Nouveau nom du dossier" required>'
      . '</label>'
      . '<div class="poi-add-actions">'
      . '<button type="button" class="poi-delete-cancel" id="poiFolderRenameCancel">Annuler</button>'
      . '<button type="submit" class="poi-add-submit" id="poiFolderRenameConfirm">Renommer</button>'
      . '</div>'
      . '<div id="poiFolderRenameStatus" class="poi-add-status" aria-live="polite"></div>'
      . '</form>'
      . '</div>'
      . '</div>';
  }

  private function buildPoiFileOptionsHtml(array $poiFiles): string
  {
    $options = '<option value="__new__">Créer un dossier</option>';

    foreach ($poiFiles as $fileData) {
      $file = (string)($fileData['file'] ?? '');
      if ($file === '') {
        continue;
      }

      $label = trim((string)($fileData['label'] ?? ''));
      $display = $label !== '' ? $label : $file;

      $options .= '<option value="' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($display, ENT_QUOTES, 'UTF-8') . '</option>';
    }

    return $options;
  }

  private function buildMapOptionsHtml(array $maps): string
  {
    $mapOptionsHtml = '';

    foreach ($maps as $file => $mapData) {
      $name = (string)($mapData['name'] ?? '');
      $logo = (string)($mapData['logo'] ?? '');
      $displayName = preg_replace('/online/i', '', $name);
      $mapOptionsHtml .= '<li data-file="' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '" data-logo="' . htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . '</li>' . "\n";
    }

    return $mapOptionsHtml;
  }

  private function resolveDefaultMap(MapManager $mapManager, array $maps, array $settings): string
  {
    $savedMap = $this->loadSavedMap($maps, $settings);

    if ($savedMap !== '') {
      return $savedMap;
    }

    return $mapManager->getDefaultMap($maps);
  }

  private function resolveDefaultZoom(array $settings): int
  {
    return $this->normalizeZoom($settings['zoom'] ?? null, 5);
  }

  private function resolveDefaultCenter(array $settings): array
  {
    return [
      'lat' => $this->normalizeLatitude($settings['centerLat'] ?? null, 48.854659),
      'lng' => $this->normalizeLongitude($settings['centerLng'] ?? null, 2.347872),
    ];
  }

  private function handleSettingsAction(string $requestMethod): void
  {
    if ($requestMethod === 'GET') {
      echo json_encode($this->readSettings(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      return;
    }

    if ($requestMethod !== 'POST') {
      http_response_code(405);
      echo json_encode(['ok' => false, 'error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      return;
    }

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody ?: '', true);

    if (!is_array($payload)) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'invalid_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      return;
    }

    $mapFile = basename((string)($payload['selectedMap'] ?? ''));
    if ($mapFile === '') {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'invalid_map'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      return;
    }

    $settings = $this->readSettings();
    $settings['selectedMap'] = $mapFile;
    $settings['zoom'] = $this->normalizeZoom($payload['zoom'] ?? ($settings['zoom'] ?? null), (int)($settings['zoom'] ?? 5));
    $settings['centerLat'] = $this->normalizeLatitude($payload['centerLat'] ?? ($settings['centerLat'] ?? null), 48.854659);
    $settings['centerLng'] = $this->normalizeLongitude($payload['centerLng'] ?? ($settings['centerLng'] ?? null), 2.347872);
    $settings['version'] = 2;

    if ($this->writeSettings($settings)) {
      echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      return;
    }

    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'save_failed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }

  private function loadSavedMap(array $maps, array $settings): string
  {
    $savedMap = basename((string)($settings['selectedMap'] ?? ''));

    if ($savedMap !== '' && array_key_exists($savedMap, $maps)) {
      return $savedMap;
    }

    return '';
  }

  private function readSettings(): array
  {
    $settingsPath = $this->settingsPath();

    if (!is_readable($settingsPath)) {
      return ['version' => 2, 'selectedMap' => '', 'zoom' => 5, 'centerLat' => 48.854659, 'centerLng' => 2.347872];
    }

    $content = file_get_contents($settingsPath);
    if ($content === false || trim($content) === '') {
      return ['version' => 2, 'selectedMap' => '', 'zoom' => 5, 'centerLat' => 48.854659, 'centerLng' => 2.347872];
    }

    $settings = json_decode($content, true);
    if (!is_array($settings)) {
      return ['version' => 2, 'selectedMap' => '', 'zoom' => 5, 'centerLat' => 48.854659, 'centerLng' => 2.347872];
    }

    return array_merge(['version' => 2, 'selectedMap' => '', 'zoom' => 5, 'centerLat' => 48.854659, 'centerLng' => 2.347872], $settings);
  }

  private function normalizeZoom($value, int $fallback): int
  {
    $zoom = filter_var($value, FILTER_VALIDATE_INT);

    if ($zoom === false) {
      return $fallback;
    }

    return max(0, min(22, (int)$zoom));
  }

  private function normalizeLatitude($value, float $fallback): float
  {
    $lat = filter_var($value, FILTER_VALIDATE_FLOAT);

    if ($lat === false) {
      return $fallback;
    }

    return max(-90.0, min(90.0, (float)$lat));
  }

  private function normalizeLongitude($value, float $fallback): float
  {
    $lng = filter_var($value, FILTER_VALIDATE_FLOAT);

    if ($lng === false) {
      return $fallback;
    }

    return max(-180.0, min(180.0, (float)$lng));
  }

  private function writeSettings(array $settings): bool
  {
    $settingsPath = $this->settingsPath();
    $encoded = json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    if ($encoded === false) {
      return false;
    }

    return file_put_contents($settingsPath, $encoded . PHP_EOL, LOCK_EX) !== false;
  }

  private function settingsPath(): string
  {
    return $this->baseDir . '/data/settings.json';
  }
}