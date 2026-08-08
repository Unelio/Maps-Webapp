<?php

class PoiFolderManager
{
  /** @var string */
  private $baseDir;

  public function __construct(string $baseDir)
  {
    $this->baseDir = rtrim($baseDir, '/');
  }

  public function dispatchAction(?string $action, string $requestMethod): bool
  {
    if ($action === 'rename-folder') {
      $this->respondRename($requestMethod);
      return true;
    }

    if ($action === 'delete-folder') {
      $this->respondDelete($requestMethod);
      return true;
    }

    return false;
  }

  private function respondRename(string $requestMethod): void
  {
    header('Content-Type: application/json; charset=utf-8');

    if ($requestMethod !== 'POST') {
      http_response_code(405);
      echo json_encode(['ok' => false, 'error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $payload = $this->readPayload();
    if ($payload === null) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'invalid_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $file = basename((string)($payload['file'] ?? ''));
    $newName = trim((string)($payload['newName'] ?? ''));

    if ($file === '' || !preg_match('/\.gpx$/i', $file) || $newName === '') {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'invalid_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $sourceGpxPath = $this->dataDirectory() . '/' . $file;
    if (!is_file($sourceGpxPath)) {
      http_response_code(404);
      echo json_encode(['ok' => false, 'error' => 'file_not_found'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $targetFile = $this->normalizeTargetFileName($newName);
    if ($targetFile === '') {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'invalid_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $targetGpxPath = $this->dataDirectory() . '/' . $targetFile;
    if ($targetFile !== $file && file_exists($targetGpxPath)) {
      http_response_code(409);
      echo json_encode(['ok' => false, 'error' => 'file_exists'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    if ($targetFile !== $file) {
      $sourceJsonPath = preg_replace('/\.gpx$/i', '.json', $sourceGpxPath);
      $targetJsonPath = preg_replace('/\.gpx$/i', '.json', $targetGpxPath);

      if (file_exists($targetJsonPath)) {
        http_response_code(409);
        echo json_encode(['ok' => false, 'error' => 'file_exists'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
      }

      if (!@rename($sourceGpxPath, $targetGpxPath)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'save_failed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
      }

      if (is_file($sourceJsonPath) && !@rename($sourceJsonPath, $targetJsonPath)) {
        @rename($targetGpxPath, $sourceGpxPath);
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'save_failed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
      }
    }

    echo json_encode([
      'ok' => true,
      'file' => $file,
      'newFile' => $targetFile,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  private function respondDelete(string $requestMethod): void
  {
    header('Content-Type: application/json; charset=utf-8');

    if ($requestMethod !== 'POST') {
      http_response_code(405);
      echo json_encode(['ok' => false, 'error' => 'method_not_allowed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $payload = $this->readPayload();
    if ($payload === null) {
      http_response_code(400);
      echo json_encode(['ok' => false, 'error' => 'invalid_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    $file = basename((string)($payload['file'] ?? ''));
    if ($file === '' || !preg_match('/\.gpx$/i', $file)) {
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

    $jsonPath = preg_replace('/\.gpx$/i', '.json', $gpxPath);

    if (!@unlink($gpxPath)) {
      http_response_code(500);
      echo json_encode(['ok' => false, 'error' => 'save_failed'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      exit;
    }

    if (is_file($jsonPath)) {
      @unlink($jsonPath);
    }

    echo json_encode([
      'ok' => true,
      'file' => $file,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  private function readPayload(): ?array
  {
    $rawBody = file_get_contents('php://input');
    $payload = json_decode($rawBody ?: '', true);

    return is_array($payload) ? $payload : null;
  }

  private function normalizeTargetFileName(string $value): string
  {
    $name = trim($value);
    if ($name === '') {
      return '';
    }

    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
    $name = trim($name);

    if ($name === '') {
      return '';
    }

    $name = preg_replace('/\.(gpx|json)$/i', '', $name) ?? $name;
    $name = trim($name);

    if ($name === '') {
      return '';
    }

    if (!preg_match('/\.gpx$/i', $name)) {
      $name .= '.gpx';
    }

    return $name;
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