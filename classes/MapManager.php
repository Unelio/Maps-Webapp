<?php

class MapManager
{
  /** @var string */
  private $baseDir;

  /** @var array */
  private $env;

  public function __construct(string $baseDir, array $env = [])
  {
    $this->baseDir = rtrim($baseDir, '/');
    $this->env = $env;
  }

  public function getMaps(): array
  {
    $hideMaps = $this->getHiddenMaps();
    $maps = [];

    $mapsDirOnline = $this->baseDir . '/data/maps/maps_online';
    foreach ((glob($mapsDirOnline . '/tiles_*.js') ?: []) as $file) {
      if (!is_file($file)) {
        continue;
      }

      $filename = basename($file);
      if (in_array($filename, $hideMaps, true)) {
        continue;
      }

      $name = preg_replace('/^tiles_|\.js$/', '', $filename);
      $displayName = ucwords(str_replace('_', ' ', (string)$name));
      $logoFile = $this->extractMapLogoPath($file);
      if ($logoFile === '') {
        $logoFile = 'logos/' . preg_replace('/\.js$/', '.png', $filename);
      }

      $maps[$filename] = [
        'name' => $displayName,
        'logo' => 'data/maps/maps_online/' . ltrim($logoFile, '/'),
      ];
    }

    $mapsDirLocal = $this->baseDir . '/data/maps/maps_local';
    foreach ((glob($mapsDirLocal . '/tiles_*.txt') ?: []) as $file) {
      if (!is_file($file)) {
        continue;
      }

      $filename = basename($file);
      if (in_array($filename, $hideMaps, true)) {
        continue;
      }

      $content = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      if (!$content) {
        continue;
      }

      $config = [];
      foreach ($content as $line) {
        $line = trim((string)$line);
        if ($line === '' || strpos($line, '#') === 0) {
          continue;
        }

        if (strpos($line, '=') === false) {
          continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $config[$key] = $value;
      }

      $mbtilesFile = (string)($config['mbtiles'] ?? '');
      if ($mbtilesFile === '') {
        continue;
      }

      $logoFile = trim((string)($config['logo'] ?? ''));
      if ($logoFile === '') {
        $logoFile = 'logos/' . preg_replace('/\.txt$/', '.png', $filename);
      }

      $mbtilesPath = $this->baseDir . '/data/maps/maps_local/maps/' . basename($mbtilesFile);
      if (!is_file($mbtilesPath)) {
        continue;
      }

      $name = preg_replace('/^tiles_|\.txt$/', '', $filename);
      $displayName = ucwords(str_replace('_', ' ', (string)$name));
      $maps[$filename] = [
        'name' => $displayName,
        'logo' => 'data/maps/maps_local/' . ltrim($logoFile, '/'),
      ];
    }

    return $maps;
  }

  public function getDefaultMap(array $maps): string
  {
    if (empty($maps)) {
      return '';
    }

    foreach ($maps as $file => $_mapData) {
      if (preg_match('/\.js$/i', (string)$file)) {
        return (string)$file;
      }
    }

    return (string)array_key_first($maps);
  }

  private function getHiddenMaps(): array
  {
    $value = (string)($this->env['HIDE_MAPS'] ?? '');
    if ($value === '') {
      return [];
    }

    return array_values(array_filter(array_map('trim', explode(',', $value)), static function (string $mapFile): bool {
      return $mapFile !== '';
    }));
  }

  private function extractMapLogoPath(string $filePath): string
  {
    $content = @file_get_contents($filePath);
    if ($content === false || $content === '') {
      return '';
    }

    if (!preg_match('/^\s*\/\/\s*logo\s*=\s*(.+?)\s*$/mi', $content, $matches)) {
      return '';
    }

    return trim((string)$matches[1]);
  }
}