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

Les cartes vectorielles ne sont pas prises en charge.

## Ajouter des cartes en ligne

- Pour ajouter une carte en ligne, créez un fichier `tiles*.js` dans `data/maps/maps_online`.
- Vous pouvez aussi personnaliser le logo avec un commentaire en tête de fichier: `// logo=logos/mon_logo.png`

Exemple :

```js
// logo=logos/tiles_openrailwaymap_train_online.png
L.tileLayer('https://tiles.openrailwaymap.org/standard/{z}/{x}/{y}.png', {
    maxZoom: 19
}).addTo(map);
```

## Ajouter des points d'intérêts (POI)

Pour ajouter des points d'intérêt, il suffit de déposer vos fichiers GPX dans `data/poi/`.

- Chaque fichier `.gpx` peut contenir un ensemble de points à afficher sur la carte
- Le fichier GPX donne son nom au dossier qui regroupe les points qu'il contient
