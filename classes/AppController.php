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

  private function handleAction(array $env): bool
  {
    $action = $_GET['action'] ?? null;

    if ($action === 'tile') {
      $tileServer = new MbtilesTileServer($this->baseDir, $env);
      $tileServer->serve((string)($_GET['file'] ?? ''), (string)($_GET['map'] ?? ''));

      return true;
    }

    if (!in_array($action, ['load', 'save', 'search', 'settings'], true)) {
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
    $defaultMap = $this->resolveDefaultMap($mapManager, $maps);

    $poiManager = new PoiManager($this->baseDir);
    $poiFiles = $poiManager->getCatalog();

    $templateRenderer = new TemplateRenderer();

    echo $templateRenderer->render($this->baseDir . '/assets/html/index.html', [
      'ASSET_VERSION' => time(),
      'DEFAULT_MAP_JSON' => json_encode($defaultMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'MAPS_COUNT' => (string)count($maps),
      'MAP_OPTIONS' => $this->buildMapOptionsHtml($maps),
      'POI_PANEL' => $this->buildPoiPanelHtml($poiFiles),
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

        $poiPanelHtml .= '<section class="poi-file" data-file="' . $fileName . '">'
          . '<div class="poi-file-header">'
          . '<button class="poi-folder-toggle" type="button" aria-label="' . $ariaLabel . '" title="' . $ariaLabel . '"><span class="poi-eye-icon' . $eyeClass . '" aria-hidden="true"><span class="poi-eye-glyph"></span><span class="poi-eye-slash"></span></span></button>'
          . '<div class="poi-file-meta">'
          . '<div class="poi-file-title">' . $label . '</div>'
          . '<div class="poi-file-count"><span class="poi-visible-count">' . $visibleCount . '</span>/<span class="poi-total-count">' . $totalCount . '</span> points</div>'
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

  private function resolveDefaultMap(MapManager $mapManager, array $maps): string
  {
    $savedMap = $this->loadSavedMap($maps);

    if ($savedMap !== '') {
      return $savedMap;
    }

    return $mapManager->getDefaultMap($maps);
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

    if ($this->writeSettings($settings)) {
      echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      return;
    }

    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'save_failed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }

  private function loadSavedMap(array $maps): string
  {
    $settings = $this->readSettings();
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
      return ['version' => 1, 'selectedMap' => ''];
    }

    $content = file_get_contents($settingsPath);
    if ($content === false || trim($content) === '') {
      return ['version' => 1, 'selectedMap' => ''];
    }

    $settings = json_decode($content, true);
    if (!is_array($settings)) {
      return ['version' => 1, 'selectedMap' => ''];
    }

    return array_merge(['version' => 1, 'selectedMap' => ''], $settings);
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