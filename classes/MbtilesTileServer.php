<?php

class MbtilesTileServer
{
  private const TILE_CACHE_DIR = 'cache/tiles';

  /** @var string */
  private $baseDir;

  /** @var array */
  private $env;

  /** @var string */
  private $cacheDir;

  /** @var string */
  private $cacheRootDir;

  /** @var int */
  private $cacheMaxBytes;

  public function __construct(string $baseDir, array $env = [])
  {
    $this->baseDir = rtrim($baseDir, '/');
    $this->env = $env;
    $this->cacheRootDir = $this->resolveCacheDir(self::TILE_CACHE_DIR);
    $this->cacheDir = $this->cacheRootDir;
    $this->cacheMaxBytes = $this->resolveCacheMaxBytes((string)($env['TILE_CACHE_MAX_MB'] ?? '150'));
  }

  public function serve(string $mbtilesFile, string $mapName = ''): void
  {
    $resolvedPath = $this->resolveMbtilesPath($mbtilesFile);

    if ($resolvedPath === '' || !is_file($resolvedPath)) {
      http_response_code(404);
      header('Content-Type: text/plain; charset=utf-8');
      echo 'Fichier MBTiles introuvable';
      return;
    }

    $this->cacheDir = $this->resolveTileCacheDir($resolvedPath, $mapName);

    $z = intval($_GET['z'] ?? 0);
    $x = intval($_GET['x'] ?? 0);
    $y = intval($_GET['y'] ?? 0);
    $cachePath = $this->buildCachePath($resolvedPath, $z, $x, $y);

    if ($this->serveCachedTile($cachePath)) {
      return;
    }

    try {
      $db = new PDO('sqlite:' . $resolvedPath);
      $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

      $y = pow(2, $z) - 1 - $y;

      $stmt = $db->prepare('SELECT tile_data FROM tiles WHERE zoom_level = ? AND tile_column = ? AND tile_row = ?');
      $stmt->execute([$z, $x, $y]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);

      header('Content-Type: image/png');
      header('Cache-Control: public, max-age=604800, stale-while-revalidate=604800');
      header('X-Maps-Tile-Cache: MISS');

      $tileData = $row && !empty($row['tile_data']) ? $row['tile_data'] : $this->createPlaceholderTile();

      $this->storeCachedTile($cachePath, $tileData);

      echo $tileData;
      return;
    } catch (Throwable $e) {
      http_response_code(500);
      header('Content-Type: text/plain; charset=utf-8');
      echo 'Erreur : ' . $e->getMessage();
    }
  }

  private function serveCachedTile(string $cachePath): bool
  {
    if ($cachePath === '' || !is_file($cachePath)) {
      return false;
    }

    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=604800, stale-while-revalidate=604800');
    header('X-Maps-Tile-Cache: HIT');

    @touch($cachePath);
    readfile($cachePath);

    return true;
  }

  private function storeCachedTile(string $cachePath, string $tileData): void
  {
    if ($cachePath === '' || $tileData === '' || $this->cacheMaxBytes <= 0) {
      return;
    }

    $cacheDirectory = dirname($cachePath);
    if (!is_dir($cacheDirectory)) {
      @mkdir($cacheDirectory, 0775, true);
    }

    if (!is_dir($cacheDirectory) || !is_writable($cacheDirectory)) {
      return;
    }

    $temporaryPath = $cachePath . '.' . uniqid('tmp', true);
    if (file_put_contents($temporaryPath, $tileData, LOCK_EX) === false) {
      @unlink($temporaryPath);
      return;
    }

    @rename($temporaryPath, $cachePath);
    @touch($cachePath);
    $this->trimCacheIfNeeded();
  }

  private function trimCacheIfNeeded(): void
  {
    if ($this->cacheMaxBytes <= 0 || !is_dir($this->cacheDir)) {
      return;
    }

    $files = glob($this->cacheDir . '/*.png') ?: [];
    $entries = [];
    $totalSize = 0;

    foreach ($files as $filePath) {
      if (!is_file($filePath)) {
        continue;
      }

      $size = filesize($filePath);
      if ($size === false) {
        continue;
      }

      $entries[] = [
        'path' => $filePath,
        'mtime' => filemtime($filePath) ?: 0,
        'size' => $size,
      ];
      $totalSize += $size;
    }

    if ($totalSize <= $this->cacheMaxBytes) {
      return;
    }

    usort($entries, static function (array $first, array $second): int {
      if ($first['mtime'] === $second['mtime']) {
        return $first['path'] <=> $second['path'];
      }

      return $first['mtime'] <=> $second['mtime'];
    });

    foreach ($entries as $entry) {
      if ($totalSize <= $this->cacheMaxBytes) {
        break;
      }

      if (@unlink($entry['path'])) {
        $totalSize -= (int)$entry['size'];
      }
    }
  }

  private function createPlaceholderTile(): string
  {
    if (!function_exists('imagecreatetruecolor')) {
      return '';
    }

    $image = imagecreatetruecolor(256, 256);
    $background = imagecolorallocate($image, 220, 220, 255);
    imagefill($image, 0, 0, $background);

    ob_start();
    imagepng($image);
    imagedestroy($image);

    return (string)ob_get_clean();
  }

  private function buildCachePath(string $resolvedPath, int $z, int $x, int $y): string
  {
    if ($this->cacheDir === '') {
      return '';
    }

    $sourceStamp = (string)@filemtime($resolvedPath);
    $cacheKey = sha1($resolvedPath . '|' . $sourceStamp . '|' . $z . '|' . $x . '|' . $y);

    return $this->cacheDir . '/' . $cacheKey . '.png';
  }

  private function resolveCacheDir(string $relativePath): string
  {
    $relativePath = trim($relativePath);
    if ($relativePath === '') {
      return '';
    }

    $relativePath = ltrim($relativePath, '/');
    $cacheDir = $this->baseDir . '/' . $relativePath;

    if (!is_dir($cacheDir)) {
      @mkdir($cacheDir, 0775, true);
    }

    return is_dir($cacheDir) ? $cacheDir : '';
  }

  private function resolveTileCacheDir(string $resolvedPath, string $mapName = ''): string
  {
    if ($this->cacheRootDir === '') {
      return '';
    }

    $cacheKey = $this->sanitizeCacheKey($mapName);
    if ($cacheKey === '') {
      $cacheKey = $this->sanitizeCacheKey(pathinfo($resolvedPath, PATHINFO_FILENAME));
    }

    if ($cacheKey === '') {
      return $this->cacheRootDir;
    }

    $cacheDir = $this->cacheRootDir . '/' . $cacheKey;

    if (!is_dir($cacheDir)) {
      @mkdir($cacheDir, 0775, true);
    }

    return is_dir($cacheDir) ? $cacheDir : $this->cacheRootDir;
  }

  private function sanitizeCacheKey(string $value): string
  {
    $value = trim($value);
    if ($value === '') {
      return '';
    }

    $value = basename($value);
    $value = preg_replace('/\.(mbtiles|txt|js)$/i', '', $value);
    $value = preg_replace('/^tiles_/i', '', $value);
    $value = preg_replace('/[^A-Za-z0-9._-]+/', '_', $value);
    $value = trim((string)$value, '._-');

    return $value;
  }

  private function resolveCacheMaxBytes(string $value): int
  {
    $maxMb = (int)trim($value);

    if ($maxMb <= 0) {
      return 0;
    }

    return $maxMb * 1024 * 1024;
  }

  private function resolveMbtilesPath(string $mbtilesFile): string
  {
    $relativePath = trim($mbtilesFile);
    if ($relativePath === '') {
      return '';
    }

    $relativePath = ltrim($relativePath, '/');
    $candidate = $this->baseDir . '/data/maps/maps_local/' . $relativePath;

    if (is_file($candidate)) {
      return $candidate;
    }

    $filename = basename($relativePath);
    $fallback = $this->baseDir . '/data/maps/maps_local/maps/' . $filename;

    if (is_file($fallback)) {
      return $fallback;
    }

    return '';
  }
}