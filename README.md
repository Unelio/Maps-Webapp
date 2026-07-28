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

Vous pouvez aussi définir une carte affichée par défaut. Les cartes sont chargées depuis `maps/maps_local` ou `maps/maps_online` selon leur type.

```env
DEFAULT_MAP=tiles_online.js
```

Il est également possible de masquer certaines cartes dans l'interface. Séparez les fichiers avec une virgule.

```env
HIDE_MAPS=tiles_online.js,tiles_local.php
```

## Service worker

Dans `sw.js`, remplacez la valeur de `CACHE` par votre nom de domaine :

```js
const CACHE = "votre_nom_de_domaine.com";
```

## Ajouter des cartes locales

Les cartes hors ligne sont prises en charge au format raster `.mbtiles`.

- Placez le fichier de carte dans `maps/maps_local/maps`
- Créez un fichier de configuration `tiles*.php` dans `maps/maps_local`

Les cartes vectorielles ne sont pas prises en charge.

## Ajouter des cartes en ligne

Pour ajouter une carte en ligne, créez un fichier `tiles*.js` dans `maps/maps_online`.

Exemple :

```js
L.tileLayer('https://tiles.openrailwaymap.org/standard/{z}/{x}/{y}.png', {
    maxZoom: 19
}).addTo(map);
```
