<?php

class SearchManager
{
  public function search(string $query): array
  {
    $query = trim($query);
    if ($query === '') {
      throw new InvalidArgumentException('query_missing');
    }

    $url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&accept-language=fr&q=' . rawurlencode($query);
    $results = $this->fetch($url);
    $first = $results[0] ?? null;

    if (!$first) {
      return ['found' => false];
    }

    return [
      'found' => true,
      'lat' => isset($first['lat']) ? (float)$first['lat'] : null,
      'lng' => isset($first['lon']) ? (float)$first['lon'] : null,
      'label' => $first['display_name'] ?? $query,
    ];
  }

  private function fetch(string $url): array
  {
    if (function_exists('curl_init')) {
      $ch = curl_init($url);
      curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_HTTPHEADER => [
          'Accept: application/json',
          'User-Agent: Maps-Webapp/1.0 (+local)',
        ],
      ]);

      $body = curl_exec($ch);
      $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
      $error = curl_error($ch);
      curl_close($ch);

      if ($body === false || $status >= 400) {
        throw new RuntimeException($error ?: 'nominatim_http_error');
      }

      $decoded = json_decode($body, true);
      if (!is_array($decoded)) {
        throw new RuntimeException('invalid_json');
      }

      return $decoded;
    }

    $context = stream_context_create([
      'http' => [
        'method' => 'GET',
        'timeout' => 10,
        'header' => implode("\r\n", [
          'Accept: application/json',
          'User-Agent: Maps-Webapp/1.0 (+local)',
        ]) . "\r\n",
      ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
      throw new RuntimeException('nominatim_fetch_error');
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
      throw new RuntimeException('invalid_json');
    }

    return $decoded;
  }
}