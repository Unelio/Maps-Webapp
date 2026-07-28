<?php

$poiAction = $_GET['action'] ?? null;
$poiRequestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (!function_exists('poi_data_directory')) {
  function poi_data_directory(): string {
    $directory = realpath(__DIR__ . '/../data');

    if ($directory === false) {
      $directory = __DIR__ . '/../data';
    }

    return $directory;
  }
}

if (!function_exists('poi_display_name')) {
  function poi_display_name(string $fileName): string {
    $base = preg_replace('/\.gpx$/i', '', basename($fileName));
    $base = preg_replace('/^poi[_-]?/i', '', $base);
    $base = str_replace(['_', '-'], ' ', $base);

    return trim(ucwords($base));
  }
}

if (!function_exists('poi_sort_key')) {
  function poi_sort_key(string $value): string {
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
}

if (!function_exists('poi_default_config')) {
  function poi_default_config(): array {
    return [
      'version' => 1,
      'folderVisible' => true,
      'points' => [],
    ];
  }
}

if (!function_exists('poi_count_gpx_points')) {
  function poi_count_gpx_points(string $path): int {
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
}

if (!function_exists('poi_child_text')) {
  function poi_child_text(DOMXPath $xpath, DOMNode $node, string $tagName): string {
    $query = './*[local-name()="' . $tagName . '"][1]';
    $result = $xpath->query($query, $node);

    if (!$result || $result->length === 0) {
      return '';
    }

    return trim($result->item(0)->textContent);
  }
}

if (!function_exists('poi_parse_gpx_file')) {
  function poi_parse_gpx_file(string $path): array {
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

        $label = poi_child_text($xpath, $node, 'name');
        if ($label === '') {
          $label = 'Point ' . ($index + 1);
        }

        $points[] = [
          'id' => 'poi-' . substr(sha1(basename($path) . '|' . $index . '|' . $lat . '|' . $lng . '|' . $label), 0, 16),
          'label' => $label,
          'description' => poi_child_text($xpath, $node, 'desc'),
          'symbol' => poi_child_text($xpath, $node, 'sym'),
          'time' => poi_child_text($xpath, $node, 'time'),
          'lat' => $lat,
          'lng' => $lng,
        ];
      }
    }

    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $points;
  }
}

if (!function_exists('poi_load_config')) {
  function poi_load_config(string $configPath, array $points = []): array {
    $config = poi_default_config();

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
}

if (!function_exists('poi_ensure_config')) {
  function poi_ensure_config(string $configPath, array $config): void {
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

if ($poiAction === 'load') {
  header('Content-Type: application/json; charset=utf-8');

  $file = basename((string)($_GET['file'] ?? ''));
  if ($file === '' || !preg_match('/\.gpx$/i', $file)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_file'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  $gpxPath = poi_data_directory() . '/' . $file;
  if (!is_file($gpxPath)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'file_not_found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  $points = poi_parse_gpx_file($gpxPath);
  $configPath = preg_replace('/\.gpx$/i', '.json', $gpxPath);
  $config = poi_load_config($configPath, $points);
  poi_ensure_config($configPath, $config);

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

if ($poiAction === 'save') {
  header('Content-Type: application/json; charset=utf-8');

  if ($poiRequestMethod !== 'POST') {
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

  $dataDir = realpath(__DIR__ . '/../data');
  if ($dataDir === false) {
    $dataDir = __DIR__ . '/../data';
  }

  $configPath = $dataDir . '/' . preg_replace('/\.gpx$/i', '.json', $file);
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

$dataDirectory = poi_data_directory();
$gpxFiles = glob($dataDirectory . '/*.gpx') ?: [];
sort($gpxFiles, SORT_NATURAL | SORT_FLAG_CASE);

$poiFiles = [];
$totalPoints = 0;

foreach ($gpxFiles as $gpxPath) {
  if (!is_file($gpxPath)) {
    continue;
  }

  $totalCount = poi_count_gpx_points($gpxPath);
  if ($totalCount <= 0) {
    continue;
  }

  $configPath = preg_replace('/\.gpx$/i', '.json', $gpxPath);
  $config = poi_load_config($configPath);
  poi_ensure_config($configPath, $config);

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
    'label' => poi_display_name($gpxPath),
    'visibleCount' => $visibleCount,
    'totalCount' => $totalCount,
    'folderVisible' => $folderVisible,
  ];

  $totalPoints += $totalCount;
}

usort($poiFiles, static function (array $firstFile, array $secondFile): int {
  $firstLabel = poi_sort_key((string)($firstFile['label'] ?? ''));
  $secondLabel = poi_sort_key((string)($secondFile['label'] ?? ''));

  $comparison = $firstLabel <=> $secondLabel;
  if ($comparison !== 0) {
    return $comparison;
  }

  return strcmp((string)($firstFile['file'] ?? ''), (string)($secondFile['file'] ?? ''));
});
?>

<div class="poi-panel">
  <div class="poi-header">
    <h2>Points d'intérêt</h2>
    <!--<div class="poi-summary"><?= count($poiFiles) ?> dossier(s), <?= $totalPoints ?> point(s)</div>-->
    <div class="poi-summary"><?= count($poiFiles) ?> dossier(s)</div>
  </div>

  <?php if (empty($poiFiles)): ?>
    <div class="poi-empty">Aucun GPX trouvé dans le dossier data.</div>
  <?php else: ?>
    <div class="poi-tree-scroll">
      <div class="poi-tree" id="poiTree">
        <?php foreach ($poiFiles as $fileIndex => $fileData): ?>
          <?php $fileId = 'poi-file-' . $fileIndex; ?>
          <section class="poi-file" data-file="<?= htmlspecialchars($fileData['file'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="poi-file-header">
              <button class="poi-folder-toggle" type="button" aria-label="<?= !empty($fileData['folderVisible']) ? 'Masquer tous les points' : 'Afficher tous les points' ?>" title="<?= !empty($fileData['folderVisible']) ? 'Masquer tous les points' : 'Afficher tous les points' ?>"><span class="poi-eye-icon<?= !empty($fileData['folderVisible']) ? '' : ' is-hidden' ?>" aria-hidden="true"><span class="poi-eye-glyph"></span><span class="poi-eye-slash"></span></span></button>
              <div class="poi-file-meta">
                <div class="poi-file-title"><?= htmlspecialchars($fileData['label'], ENT_QUOTES, 'UTF-8') ?></div>
                <div class="poi-file-count"><span class="poi-visible-count"><?= (int)$fileData['visibleCount'] ?></span>/<span class="poi-total-count"><?= (int)$fileData['totalCount'] ?></span> points</div>
              </div>
            </div>
            <ul class="poi-point-list is-collapsed" id="<?= htmlspecialchars($fileId, ENT_QUOTES, 'UTF-8') ?>" data-loaded="false"></ul>
          </section>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<script id="poiCatalogData" type="application/json"><?php echo json_encode($poiFiles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>