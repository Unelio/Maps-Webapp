# Maps-Webapp

**Maps-Webapp** est une application web de cartographie destinée à un utilisateur unique, prenant en charge plusieurs fournisseurs de cartes.

## Installation

Copiez les fichiers sur votre serveur web, puis configurez l'authentification.

## Prérequis

Serveur web avec PHP

## Configuration

Créez un fichier `.env` à la racine du projet avec au minimum :

```env
LOGIN="Utilisateur"
PASSWORD="Mot_de_passe"
```

Le fichier .env_example contient les autres options de configuration que vous pouvez personnaliser.

## Ajouter des cartes locales

Les cartes hors ligne fonctionnent seulement avec le format raster `.mbtiles`.

- Placez le fichier de carte `.mbtiles` dans `data/maps/maps_local/maps`
- Vous pouvez aussi personnaliser le logo avec `logo=logos/mon_logo.png` dans le fichier `tiles_*.txt`

Exemple :

```txt
logo=logos/oam.png
mbtiles=maps/Oam_Classic_2025_Raster.mbtiles
```

Les cartes vectorielles ne sont pas prises en charge.

## Ajouter des cartes en ligne

- Pour ajouter une carte en ligne, créez un fichier `tiles_*.js` dans `data/maps/maps_online`.
- Vous pouvez aussi personnaliser le logo avec un commentaire en tête de fichier: `// logo=logos/mon_logo.png`

Exemple :

```js
// logo=logos/osm.png
L.tileLayer('https://tiles.openrailwaymap.org/standard/{z}/{x}/{y}.png', {
    maxZoom: 19
}).addTo(map);
```

## Ajouter des points d'intérêt (POI)

### Via l'interface

Il suffit de faire un appui long sur le lieu que vous voulez enregistrer.

### Via des fichiers GPX

Vous pouvez déposer vos fichiers GPX dans `data/poi/`.

- Chaque fichier `.gpx` peut contenir un ensemble de points à afficher sur la carte
- Le fichier GPX donne son nom au dossier qui regroupe les points qu'il contient
