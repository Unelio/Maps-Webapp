<?php

class PoiManager
{
  /** @var string */
  private $baseDir;

  public function __construct(string $baseDir)
  {
    $this->baseDir = rtrim($baseDir, '/');
  }

  public function dispatchAction(?string $action, string $requestMethod): bool
  {
    if ($action === 'load') {
      $this->respondLoad();
      return true;
    }

    if ($action === 'save') {
      $this->respondSave($requestMethod);
      return true;
    }

    return false;
  }

  public function getCatalog(): array
  {
    $dataDirectory = $this->dataDirectory();
    $gpxFiles = glob($dataDirectory . '/*.gpx') ?: [];
    sort($gpxFiles, SORT_NATURAL | SORT_FLAG_CASE);

    $poiFiles = [];

    foreach ($gpxFiles as $gpxPath) {
      if (!is_file($gpxPath)) {
        continue;
      }

      $totalCount = $this->countGpxPoints($gpxPath);
      if ($totalCount <= 0) {
        continue;
      }

      $configPath = preg_replace('/\.gpx$/i', '.json', $gpxPath);
      $config = $this->loadConfig($configPath, []);
      $this->ensureConfig($configPath, $config);

      $folderVisible = !array_key_exists('folderVisible', $config) || (bool)$config['folderVisible'];
      $visibleCount = $folderVisible ? count($config['points']) : 0;

      if (empty($config['points'])) {
        $visibleCount = $folderVisible ? $totalCount : 0;
      } else {
        $visibleCount = 0;

        foreach ($config['points'] as $pointState) {
          if (!isset($pointState['visible']) || $pointState['visible']) {
            $visibleCount++;
          }
        }

        if (!$folderVisible) {
          $visibleCount = 0;
        }
      }

      $poiFiles[] = [
        'file' => basename($gpxPath),
        'label' => $this->displayName($gpxPath),
        'visibleCount' => $visibleCount,
        'totalCount' => $totalCount,
        'folderVisible' => $folderVisible,
      ];
    }

    usort($poiFiles, static function (array $firstFile, array $secondFile): int {
      $firstLabel = PoiManager::sortKey((string)($firstFile['label'] ?? ''));
      $secondLabel = PoiManager::sortKey((string)($secondFile['label'] ?? ''));

      $comparison = $firstLabel <=> $secondLabel;
      if ($comparison !== 0) {
        return $comparison;
      }

      return strcmp((string)($firstFile['file'] ?? ''), (string)($secondFile['file'] ?? ''));
    });

    return $poiFiles;
  }

  private function respondLoad(): void
  {
    header('Content-Type: application/json; charset=utf-8');

    $file = basename((string)($_GET['file'] ?? ''));
    if ($file === '' || !preg_match('/\.gpx$/i', $file)) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'invalid_file'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $gpxPath = $this->dataDirectory() . '/' . $file;
    if (!is_file($gpxPath)) {
      http_response_code(404);
      echo json_encode(['ok' => false, 'error' => 'file_not_found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $points = $this->parseGpxFile($gpxPath);
    $configPath = preg_replace('/\.gpx$/i', '.json', $gpxPath);
    $config = $this->loadConfig($configPath, $points);
    $this->ensureConfig($configPath, $config);

    $folderVisible = !array_key_exists('folderVisible', $config) || (bool)$config['folderVisible'];
    $normalizedPoints = [];

    foreach ($points as $point) {
      if (isset($config['points'][$point['id']]) && is_array($config['points'][$point['id']])) {
        $pointVisible = !array_key_exists('visible', $config['points'][$point['id']]) || (bool)$config['points'][$point['id']]['visible'];
      } else {
        $pointVisible = true;
      }

      $normalizedPoints[] = [
        'id' => $point['id'],
        'label' => $point['label'],
        'description' => $point['description'],
        'symbol' => $point['symbol'],
        'time' => $point['time'],
        'lat' => $point['lat'],
        'lng' => $point['lng'],
        'visible' => $pointVisible,
      ];
    }

    echo json_encode([
      'ok' => true,
      'file' => $file,
      'folderVisible' => $folderVisible,
      'points' => $normalizedPoints,
      'totalCount' => count($normalizedPoints),
      'visibleCount' => $folderVisible ? count(array_filter($normalizedPoints, static function (array $point): bool {
        return !empty($point['visible']);
      })) : 0,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  private function respondSave(string $requestMethod): void
  {
    header('Content-Type: application/json; charset=utf-8');

    if ($requestMethod !== 'POST') {
      http_response_code(405);
      echo json_encode(['ok' => false, 'error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody ?: '', true);

    if (!is_array($payload)) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'invalid_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $file = basename((string)($payload['file'] ?? ''));
    $state = isset($payload['state']) && is_array($payload['state']) ? $payload['state'] : null;

    if ($file === '' || !preg_match('/\.gpx$/i', $file) || $state === null) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'invalid_file'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $configPath = $this->dataDirectory() . '/' . preg_replace('/\.gpx$/i', '.json', $file);
    $config = [
      'version' => 1,
      'folderVisible' => array_key_exists('folderVisible', $state) ? (bool)$state['folderVisible'] : true,
      'points' => [],
    ];

    $pointsState = isset($state['points']) && is_array($state['points']) ? $state['points'] : [];

    foreach ($pointsState as $pointId => $pointState) {
      if (!is_array($pointState)) {
        continue;
      }

      $config['points'][(string)$pointId] = [
        'visible' => isset($pointState['visible']) ? (bool)$pointState['visible'] : true,
      ];
    }

    $encoded = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    if ($encoded === false || file_put_contents($configPath, $encoded . PHP_EOL, LOCK_EX) === false) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => 'save_failed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  private function dataDirectory(): string
  {
    $directory = realpath($this->baseDir . '/data/poi');

    if ($directory === false) {
      $directory = $this->baseDir . '/data/poi';
    }

    return $directory;
  }

  private function displayName(string $fileName): string
  {
    $base = preg_replace('/\.gpx$/i', '', basename($fileName));
    $base = preg_replace('/^poi[_-]?/i', '', $base);
    $base = str_replace(['_', '-'], ' ', (string)$base);

    return trim(ucwords((string)$base));
  }

  private static function sortKey(string $value): string
  {
    if (function_exists('transliterator_transliterate')) {
      $transliterated = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $value);
      if (is_string($transliterated) && $transliterated !== '') {
        return $transliterated;
      }
    }

    $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if ($transliterated !== false && $transliterated !== '') {
      return strtolower($transliterated);
    }

    return strtolower($value);
  }

  private function defaultConfig(): array
  {
    return [
      'version' => 1,
      'folderVisible' => true,
      'points' => [],
    ];
  }

  private function countGpxPoints(string $path): int
  {
    if (!is_readable($path)) {
      return 0;
    }

    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument();

    if (!$document->load($path, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
      libxml_clear_errors();
      libxml_use_internal_errors($previous);
      return 0;
    }

    $xpath = new DOMXPath($document);
    $count = (int)$xpath->evaluate('count(//*[local-name()="wpt"])');

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return max(0, $count);
  }

  private function childText(DOMXPath $xpath, DOMNode $node, string $tagName): string
  {
    $query = './*[local-name()="' . $tagName . '"][1]';
    $result = $xpath->query($query, $node);

    if (!$result || $result->length === 0) {
      return '';
    }

    return trim($result->item(0)->textContent);
  }

  private function parseGpxFile(string $path): array
  {
    if (!is_readable($path)) {
      return [];
    }

    $previous = libxml_use_internal_errors(true);
    $document = new DOMDocument();

    if (!$document->load($path, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
      libxml_clear_errors();
      libxml_use_internal_errors($previous);
      return [];
    }

    $xpath = new DOMXPath($document);
    $nodes = $xpath->query('//*[local-name()="wpt"]');
    $points = [];

    if ($nodes) {
      foreach ($nodes as $index => $node) {
        if (!$node instanceof DOMElement) {
          continue;
        }

        $lat = (float)$node->getAttribute('lat');
        $lng = (float)$node->getAttribute('lon');

        if (!is_finite($lat) || !is_finite($lng)) {
          continue;
        }

        $label = $this->childText($xpath, $node, 'name');
        if ($label === '') {
          $label = 'Point ' . ($index + 1);
        }

        $points[] = [
          'id' => 'poi-' . substr(sha1(basename($path) . '|' . $index . '|' . $lat . '|' . $lng . '|' . $label), 0, 16),
          'label' => $label,
          'description' => $this->childText($xpath, $node, 'desc'),
          'symbol' => $this->childText($xpath, $node, 'sym'),
          'time' => $this->childText($xpath, $node, 'time'),
          'lat' => $lat,
          'lng' => $lng,
        ];
      }
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $points;
  }

  private function loadConfig(string $configPath, array $points = []): array
  {
    $config = $this->defaultConfig();

    if (is_readable($configPath)) {
      $decoded = json_decode((string)file_get_contents($configPath), true);

      if (is_array($decoded)) {
        if (array_key_exists('folderVisible', $decoded)) {
          $config['folderVisible'] = (bool)$decoded['folderVisible'];
        }

        if (isset($decoded['points']) && is_array($decoded['points'])) {
          $config['points'] = $decoded['points'];
        }
      }
    }

    if (empty($points)) {
      return $config;
    }

    $normalized = [];

    foreach ($points as $point) {
      $visible = true;

      if (isset($config['points'][$point['id']]) && is_array($config['points'][$point['id']])) {
        $visible = array_key_exists('visible', $config['points'][$point['id']]) ? (bool)$config['points'][$point['id']]['visible'] : true;
      }

      $normalized[$point['id']] = [
        'visible' => $visible,
      ];
    }

    $config['points'] = $normalized;

    return $config;
  }

  private function ensureConfig(string $configPath, array $config): void
  {
    if (is_file($configPath)) {
      return;
    }

    if (!is_writable(dirname($configPath))) {
      return;
    }

    $encoded = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($encoded !== false) {
      @file_put_contents($configPath, $encoded . PHP_EOL, LOCK_EX);
    }
  }
}