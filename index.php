<?php
session_start();

$file = ".env";

if (!file_exists($file)) die('Un fichier est manquant');

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

$array = parse_env_file(".env");

$login = $array['LOGIN'];
$password = $array['PASSWORD'];

// Récupère la liste des cartes à masquer
$hideMaps = [];
if (!empty($array['HIDE_MAPS'])) {
  $hideMaps = array_map('trim', explode(',', $array['HIDE_MAPS']));
}

if (!$_SESSION["maps"]) {
  $inputLogin = $_POST["login"] ?? '';
  $inputPassword = $_POST["password"] ?? '';
  
  if ($inputLogin === $login && $inputPassword === $password) {
      $_SESSION["maps"] = true;
      header('Location: /');
      exit;
  } else if ($inputLogin || $inputPassword) {
      $error = "Identifiant ou mot de passe incorrect";
  }
  
  include __DIR__ . '/inc/head.php';
  include __DIR__ . '/inc/login.php';
  include __DIR__ . '/inc/end.php';
  die();
}
$maps = [];

// Liste les cartes en ligne
$mapsDirOnline = __DIR__ . '/maps/maps_online';
foreach (glob($mapsDirOnline.'/tiles_*.js') as $file) {
  if (!is_file($file)) continue;
  $filename = basename($file);
  if (in_array($filename, $hideMaps)) continue; // <-- Cacher des cartes
  $name = preg_replace('/^tiles_|\.js$/','',$filename);
  $displayName = ucwords(str_replace('_',' ',$name));
  $maps[$filename] = $displayName;
}

// Liste les cartes locales
$mapsDirLocal = __DIR__ . '/maps/maps_local';
foreach (glob($mapsDirLocal.'/tiles_*.php') as $file) {
  if (!is_file($file)) continue;
  $filename = basename($file);
  if (in_array($filename, $hideMaps)) continue; // <-- Cacher des cartes
  $name = preg_replace('/^tiles_|\.php$/','',$filename);
  $displayName = ucwords(str_replace('_',' ',$name));
  $maps[$filename] = $displayName;
}

// Carte à afficher par défaut
$fallbackMap = 'tiles_osm_classic_online.js';
$configMap = $array['DEFAULT_MAP'] ?? '';
$defaultMap = '';
if (!empty($maps)) {
    // Vérifie si la valeur de .env existe et correspond à une carte connue
    if (!empty($configMap) && array_key_exists($configMap, $maps)) {
        $defaultMap = $configMap;
    } 
    // Sinon on applique le fallback
    elseif (!empty($fallbackMap) && array_key_exists($fallbackMap, $maps)) {
        $defaultMap = $fallbackMap;
    } 
    // Sinon on prend la première carte trouvée
    else {
        $defaultMap = array_key_first($maps);
    }
}

include __DIR__ . '/inc/head.php';
?>

<body>
<header>
  <div id="mapInfo">
    <img id="mapIcon" src="" alt="map icon">
    <div>
      <div id="mapTitle"></div>
      <div id="mapZoom">Zoom : --</div>
    </div>
  </div>
  <button id="mapBtn"></button>
</header>

<!-- Overlay -->
<div id="mapOverlay">
<span id="closeOverlay">&times;</span>
  <ul>
    <?php foreach ($maps as $file => $name): $displayName = preg_replace('/online/i', '', $name); // supprime "online" ?>
    <li data-file="<?= htmlspecialchars($file) ?>"><?= htmlspecialchars($displayName) ?></li>
    <?php endforeach; ?>
  </ul>
</div>

<!-- Cartes -->
<iframe id="mapFrame"></iframe>

<footer>
  <div id="footerControls">
    <button id="locateBtn">
      <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"></circle>
        <circle cx="12" cy="12" r="3"></circle>
      </svg>;
    </button>
    <div id="footerZoom">
      <button id="zoomInBtn">
        <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="square" stroke-linejoin="miter">
          <path d="M12 5v14M5 12h14"/>
        </svg>
      </button>
      <button id="zoomOutBtn">
        <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="square" stroke-linejoin="miter">
          <path d="M5 12h14"/>
        </svg>
      </button>
    </div>
  </div>
</footer>

<script>
  window.defaultMap = "<?= $defaultMap ?>";
</script>
<script src="js/app.js"></script>

</body>

<?php
include __DIR__ . '/inc/end.php';
?>