<?php
// Chemin vers ton fichier MBTiles
$dbFile = __DIR__ . "/maps/Maptiler_Satelitte_2017_Raster.mbtiles";

// Connexion SQLite
$db = new PDO("sqlite:" . $dbFile);

// Paramètres envoyés par Leaflet
$z = intval($_GET['z']);
$x = intval($_GET['x']);
$y = intval($_GET['y']);

// Inverser Y (MBTiles stocke avec origine en bas à gauche)
$maxTile = pow(2, $z) - 1;
$y = $maxTile - $y;

// Requête pour récupérer la tuile
$stmt = $db->prepare("SELECT tile_data FROM tiles WHERE zoom_level = ? AND tile_column = ? AND tile_row = ?");
$stmt->execute([$z, $x, $y]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    header("Content-Type: image/png");
    echo $row['tile_data'];
} else {
    header("HTTP/1.0 404 Not Found");
}