<?php
// tiles.php pour MBTiles OpenAndroMaps
$mbtilesFile = __DIR__ . "/maps/Oam_Classic_2025_Raster.mbtiles";

if (!file_exists($mbtilesFile)) {
    die("Fichier MBTiles introuvable !");
}

try {
    $db = new PDO("sqlite:" . $mbtilesFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $z = intval($_GET['z']);
    $x = intval($_GET['x']);
    $y = intval($_GET['y']);

    // Inversion Y pour MBTiles standard (origine en bas à gauche)
    $y = pow(2, $z) - 1 - $y;

    $stmt = $db->prepare("SELECT tile_data FROM tiles WHERE zoom_level = ? AND tile_column = ? AND tile_row = ?");
    $stmt->execute([$z, $x, $y]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row && !empty($row['tile_data'])) {
        header("Content-Type: image/png");
        echo $row['tile_data'];
    } else {
        // Générer tuile vide si manquante
        header("Content-Type: image/png");
        $im = imagecreatetruecolor(256, 256);
        $bg = imagecolorallocate($im, 220, 220, 255);
        imagefill($im, 0, 0, $bg);
        imagepng($im);
        imagedestroy($im);
    }
} catch (Exception $e) {
    header("Content-Type: text/plain");
    echo "Erreur : " . $e->getMessage();
}
