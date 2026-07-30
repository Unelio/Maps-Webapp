<?php

class PoiDeleteManager
{
  /** @var string */
  private $baseDir;

  public function __construct(string $baseDir)
  {
    $this->baseDir = rtrim($baseDir, '/');
  }

  public function dispatchAction(?string $action, string $requestMethod): bool
  {
    if ($action === 'delete') {
      $this->respondDelete($requestMethod);
      return true;
    }

    return false;
  }

  private function respondDelete(string $requestMethod): void
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
    $pointId = trim((string)($payload['pointId'] ?? ''));

    if ($file === '' || !preg_match('/\.gpx$/i', $file) || $pointId === '') {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'invalid_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $gpxPath = $this->dataDirectory() . '/' . $file;
    if (!is_file($gpxPath)) {
      http_response_code(404);
      echo json_encode(['ok' => false, 'error' => 'file_not_found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $document = $this->loadDocument($gpxPath);
    if (!$document) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => 'save_failed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $points = $this->collectWaypoints($document, $gpxPath);
    $deleteIndex = $this->findPointIndex($points, $pointId);

    if ($deleteIndex === null) {
      http_response_code(404);
      echo json_encode(['ok' => false, 'error' => 'point_not_found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $root = $document->documentElement;
    $node = $points[$deleteIndex]['node'] ?? null;

    if (!$root instanceof DOMElement || !$node instanceof DOMElement) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => 'save_failed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $oldConfig = $this->loadConfig(preg_replace('/\.gpx$/i', '.json', $gpxPath));

    $root->removeChild($node);
    $document->formatOutput = true;
    $encoded = $document->saveXML();

    if ($encoded === false || file_put_contents($gpxPath, $encoded . PHP_EOL, LOCK_EX) === false) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => 'save_failed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $config = $this->buildConfigAfterDeletion($points, $deleteIndex, $oldConfig, $file);
    $configPath = preg_replace('/\.gpx$/i', '.json', $gpxPath);
    $encodedConfig = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);

    if ($encodedConfig === false || file_put_contents($configPath, $encodedConfig . PHP_EOL, LOCK_EX) === false) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => 'save_failed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    echo json_encode([
      'ok' => true,
      'file' => $file,
      'pointId' => $pointId,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  private function loadDocument(string $gpxPath): ?DOMDocument
  {
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->preserveWhiteSpace = true;
    $document->formatOutput = true;

    $previous = libxml_use_internal_errors(true);

    if (!$document->load($gpxPath, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
      libxml_clear_errors();
      libxml_use_internal_errors($previous);
      return null;
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $document;
  }

  /**
   * @return array<int, array{node: DOMElement, id: string, lat: float, lng: float, label: string}>
   */
  private function collectWaypoints(DOMDocument $document, string $path): array
  {
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

        $points[] = [
          'node' => $node,
          'id' => $this->buildPointId($path, $index, $lat, $lng),
          'lat' => $lat,
          'lng' => $lng,
          'label' => $this->readWaypointLabel($xpath, $node, $index),
        ];
      }
    }

    return $points;
  }

  /**
   * @param array<int, array{id: string}> $points
   */
  private function findPointIndex(array $points, string $pointId): ?int
  {
    foreach ($points as $index => $point) {
      if (($point['id'] ?? '') === $pointId) {
        return $index;
      }
    }

    return null;
  }

  private function buildConfigAfterDeletion(array $points, int $deleteIndex, array $oldConfig, string $file): array
  {
    $config = [
      'version' => 1,
      'folderVisible' => array_key_exists('folderVisible', $oldConfig) ? (bool)$oldConfig['folderVisible'] : true,
      'points' => [],
    ];

    $newIndex = 0;

    foreach ($points as $index => $point) {
      if ($index === $deleteIndex) {
        continue;
      }

      $legacyId = $this->buildLegacyPointId($file, $index, (float)$point['lat'], (float)$point['lng'], (string)$point['label']);
      $oldState = isset($oldConfig['points'][$point['id']]) && is_array($oldConfig['points'][$point['id']])
        ? $oldConfig['points'][$point['id']]
        : (isset($oldConfig['points'][$legacyId]) && is_array($oldConfig['points'][$legacyId])
          ? $oldConfig['points'][$legacyId]
          : []);

      $newId = $this->buildPointId($file, $newIndex, (float)$point['lat'], (float)$point['lng']);
      $config['points'][$newId] = [
        'visible' => array_key_exists('visible', $oldState) ? (bool)$oldState['visible'] : true,
      ];
      $newIndex++;
    }

    return $config;
  }

  private function loadConfig(?string $configPath): array
  {
    $config = [
      'version' => 1,
      'folderVisible' => true,
      'points' => [],
    ];

    if (!$configPath || !is_readable($configPath)) {
      return $config;
    }

    $decoded = json_decode((string)file_get_contents($configPath), true);

    if (is_array($decoded)) {
      if (array_key_exists('folderVisible', $decoded)) {
        $config['folderVisible'] = (bool)$decoded['folderVisible'];
      }

      if (isset($decoded['points']) && is_array($decoded['points'])) {
        $config['points'] = $decoded['points'];
      }
    }

    return $config;
  }

  private function buildPointId(string $path, int $index, float $lat, float $lng): string
  {
    return 'poi-' . substr(sha1(basename($path) . '|' . $index . '|' . $lat . '|' . $lng), 0, 16);
  }

  private function buildLegacyPointId(string $path, int $index, float $lat, float $lng, string $label): string
  {
    return 'poi-' . substr(sha1(basename($path) . '|' . $index . '|' . $lat . '|' . $lng . '|' . $label), 0, 16);
  }

  private function readWaypointLabel(DOMXPath $xpath, DOMElement $waypoint, int $index): string
  {
    $query = './*[local-name()="name"][1]';
    $result = $xpath->query($query, $waypoint);

    if ($result && $result->length > 0) {
      $label = trim($result->item(0)->textContent);
      if ($label !== '') {
        return $label;
      }
    }

    return 'Point ' . ($index + 1);
  }

  private function dataDirectory(): string
  {
    $directory = realpath($this->baseDir . '/data/poi');

    if ($directory === false) {
      $directory = $this->baseDir . '/data/poi';
    }

    return $directory;
  }
}