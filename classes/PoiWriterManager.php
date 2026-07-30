<?php

class PoiWriterManager
{
  /** @var string */
  private $baseDir;

  public function __construct(string $baseDir)
  {
    $this->baseDir = rtrim($baseDir, '/');
  }

  public function dispatchAction(?string $action, string $requestMethod): bool
  {
    if ($action === 'add') {
      $this->respondAdd($requestMethod);
      return true;
    }

    if ($action === 'modify') {
      $this->respondModify($requestMethod);
      return true;
    }

    return false;
  }

  private function respondAdd(string $requestMethod): void
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

    $lat = $this->toFloatOrNull($payload['lat'] ?? null);
    $lng = $this->toFloatOrNull($payload['lng'] ?? null);
    $label = trim((string)($payload['label'] ?? ''));
    $description = trim((string)($payload['description'] ?? ''));
    $file = $this->resolveTargetFile((string)($payload['file'] ?? ''), (string)($payload['newFile'] ?? ''));

    if ($lat === null || $lng === null || $file === '') {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'invalid_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    if ($label === '') {
      $label = 'Nouveau point';
    }

    $dataDirectory = $this->dataDirectory();
    if (!is_dir($dataDirectory) || !is_writable($dataDirectory)) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => 'data_directory_unavailable'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $gpxPath = $dataDirectory . '/' . $file;
    $document = $this->loadOrCreateDocument($gpxPath, $file);

    if (!$document) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => 'save_failed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $root = $document->documentElement;
    if (!$root instanceof DOMElement) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => 'save_failed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $namespace = $root->namespaceURI ?: 'http://www.topografix.com/GPX/1/1';
    $waypoint = $document->createElementNS($namespace, 'wpt');
    $waypoint->setAttribute('lat', rtrim(rtrim(number_format($lat, 6, '.', ''), '0'), '.'));
    $waypoint->setAttribute('lon', rtrim(rtrim(number_format($lng, 6, '.', ''), '0'), '.'));

    $nameNode = $document->createElementNS($namespace, 'name');
    $nameNode->appendChild($document->createTextNode($label));
    $waypoint->appendChild($nameNode);

    if ($description !== '') {
      $descriptionNode = $document->createElementNS($namespace, 'desc');
      $descriptionNode->appendChild($document->createTextNode($description));
      $waypoint->appendChild($descriptionNode);
    }

    $root->appendChild($waypoint);

    $document->formatOutput = true;
    $encoded = $document->saveXML();

    if ($encoded === false || file_put_contents($gpxPath, $encoded . PHP_EOL, LOCK_EX) === false) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => 'save_failed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    echo json_encode([
      'ok' => true,
      'file' => $file,
      'label' => $label,
      'lat' => $lat,
      'lng' => $lng,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  private function respondModify(string $requestMethod): void
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
    $label = trim((string)($payload['label'] ?? ''));

    if ($file === '' || !preg_match('/\.gpx$/i', $file) || $pointId === '' || $label === '') {
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
    $target = $this->findPointById($points, $pointId);

    if ($target === null) {
      http_response_code(404);
      echo json_encode(['ok' => false, 'error' => 'point_not_found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $node = $points[$target]['node'] ?? null;
    if (!$node instanceof DOMElement) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => 'save_failed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $this->setWaypointLabel($document, $node, $label);

    $document->formatOutput = true;
    $encoded = $document->saveXML();

    if ($encoded === false || file_put_contents($gpxPath, $encoded . PHP_EOL, LOCK_EX) === false) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => 'save_failed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    echo json_encode([
      'ok' => true,
      'file' => $file,
      'pointId' => $pointId,
      'label' => $label,
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
   * @return array<int, array{node: DOMElement, id: string, lat: float, lng: float}>
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
        ];
      }
    }

    return $points;
  }

  /**
   * @param array<int, array{id: string}> $points
   */
  private function findPointById(array $points, string $pointId): ?int
  {
    foreach ($points as $index => $point) {
      if (($point['id'] ?? '') === $pointId) {
        return $index;
      }
    }

    return null;
  }

  private function setWaypointLabel(DOMDocument $document, DOMElement $waypoint, string $label): void
  {
    $xpath = new DOMXPath($document);
    $nameNodes = $xpath->query('./*[local-name()="name"]', $waypoint);

    if ($nameNodes && $nameNodes->length > 0) {
      $nameNodes->item(0)->textContent = $label;
      return;
    }

    $namespace = $waypoint->namespaceURI ?: 'http://www.topografix.com/GPX/1/1';
    $nameNode = $document->createElementNS($namespace, 'name');
    $nameNode->appendChild($document->createTextNode($label));
    $waypoint->appendChild($nameNode);
  }

  private function buildPointId(string $path, int $index, float $lat, float $lng): string
  {
    return 'poi-' . substr(sha1(basename($path) . '|' . $index . '|' . $lat . '|' . $lng), 0, 16);
  }

  private function loadOrCreateDocument(string $gpxPath, string $file): ?DOMDocument
  {
    $document = new DOMDocument('1.0', 'UTF-8');
    $document->preserveWhiteSpace = true;
    $document->formatOutput = true;

    if (is_file($gpxPath)) {
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

    $root = $document->createElementNS('http://www.topografix.com/GPX/1/1', 'gpx');
    $root->setAttribute('version', '1.1');
    $root->setAttribute('creator', 'Maps');

    $metadata = $document->createElementNS('http://www.topografix.com/GPX/1/1', 'metadata');
    $nameNode = $document->createElementNS('http://www.topografix.com/GPX/1/1', 'name');
    $nameNode->appendChild($document->createTextNode(preg_replace('/\.gpx$/i', '', $file)));
    $metadata->appendChild($nameNode);
    $root->appendChild($metadata);
    $document->appendChild($root);

    return $document;
  }

  private function resolveTargetFile(string $file, string $newFile): string
  {
    $file = basename(trim($file));

    if ($file !== '' && $file !== '__new__') {
      if (preg_match('/\.gpx$/i', $file)) {
        return $file;
      }

      return '';
    }

    return $this->sanitizeGpxFileName($newFile);
  }

  private function sanitizeGpxFileName(string $fileName): string
  {
    $fileName = trim($fileName);
    $fileName = preg_replace('/\.gpx$/i', '', $fileName);
    $fileName = str_replace(['/', '\\'], '-', $fileName);
    $fileName = preg_replace('/[^\pL\pN._ -]+/u', '-', $fileName);
    $fileName = preg_replace('/\s+/u', ' ', $fileName);
    $fileName = trim((string)$fileName, " .-_\t\n\r\0\x0B");

    if ($fileName === '') {
      return '';
    }

    return $fileName . '.gpx';
  }

  private function toFloatOrNull($value): ?float
  {
    if (is_int($value) || is_float($value)) {
      return is_finite((float)$value) ? (float)$value : null;
    }

    $value = trim((string)$value);

    if ($value === '') {
      return null;
    }

    $normalized = str_replace(',', '.', $value);

    if (!is_numeric($normalized)) {
      return null;
    }

    $number = (float)$normalized;

    return is_finite($number) ? $number : null;
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
