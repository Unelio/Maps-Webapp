<?php
session_start();

$file = ".env";

if (!file_exists($file)) die('Un fichier est manquant');

include __DIR__ . '/inc/functions.php';

$array = parse_env_file(".env");

include __DIR__ . '/login.php';

// Récupère la liste des cartes à masquer
$hideMaps = [];
if (!empty($array['HIDE_MAPS'])) {
  $hideMaps = array_map('trim', explode(',', $array['HIDE_MAPS']));
}

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
  
  // Vérifier la présence de la carte locale
  $content = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  if (!$content) continue;
  $contentStr = implode("\n", $content);
  if (!preg_match('/\$mbtilesFile\s*=\s*__DIR__\s*\.\s*"\/maps\/([^"]+)";/', $contentStr, $matches)) continue; // Chercher le nom de la carte
  $mbtilesFile = $matches[1];
  $mbtilesPath = __DIR__ . '/maps/maps_local/maps/' . $mbtilesFile;
  if (!is_file($mbtilesPath)) continue; // <-- fichier absent, on ignore la carte
  
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
