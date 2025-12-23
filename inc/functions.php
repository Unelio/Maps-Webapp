<?php
function parse_env_file(string $file): array {
  if (!is_readable($file)) return [];
  
  $lines = file($file, FILE_IGNORE_NEW_LINES);
  $env = [];
  
  foreach ($lines as $rawLine) {
    // Retirer BOM
    $line = preg_replace('/^\x{FEFF}/u', '', $rawLine);
    $line = trim($line);
    
    if ($line === '') continue;
    
    // Ignorer les commentaires en début de ligne (avec ou sans espaces)
    $ltrim = ltrim($line);
    if (strpos($ltrim, '#') === 0 || strpos($ltrim, ';') === 0 || strpos($ltrim, '//') === 0) continue;
    
    // Accepter "export KEY=VALUE"
    if (stripos($ltrim, 'export ') === 0) {
        $ltrim = trim(substr($ltrim, 7));
    }
    
    if (strpos($ltrim, '=') === false) continue;
    
    list($name, $value) = explode('=', $ltrim, 2);
    $name = trim($name);
    $value = trim($value);
    
    // Si la valeur est entre guillemets (simple ou double), on la décapsule
    if (strlen($value) >= 2 && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))) {
        $value = substr($value, 1, -1);
        $value = str_replace(['\\"', "\\'"], ['"', "'"], $value);
    } else {
        // Enlever commentaire inline qui vient après un espace : " valeur #comment" ou "valeur ;comment" ou "valeur //comment"
        $value = preg_replace('/\s+(#|;|\/\/).*$/', '', $value);
        $value = rtrim($value);
    }
    $env[$name] = $value;
  }
  return $env;
}

