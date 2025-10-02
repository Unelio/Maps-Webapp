Maps-Webapp
===========================

Utilisez une application 'single-user' afin de profiter de cartes de différentes sources


Configuration
---------------------------
Créez un fichier .env (à placer à la racine du projet) avec au minimum :
```
    LOGIN="Utilisateur"
    PASSWORD="Mot_de_passe"
```

Vous pouvez configurer une carte par défaut dans le fichier .env :
*(Les cartes se trouvent soit dans maps/maps_local soit dans maps/maps_online)*
```
    DEFAULT_MAP=tiles_online.js
```

Il est aussi possible de masquer des cartes dans le fichier .env :
*(Séparez les entrées avec une virgule)*
```
    HIDE_MAPS=tiles_online.js,tiles_local.php
```


WebApp
---------------------------
Dans le fichier sw.js, remplacez la variable suivante avec votre nom de domaine :
```
    const CACHE = "votre_nom_de_domaine.com";
```


Ajouter des cartes locales
---------------------------
Il est possible d'ajouter des cartes hors ligne au format Raster dont l'extension est mbtiles
- La carte devra être ajoutée dans le dossier maps/maps_local/maps
- Un fichier de configuration "tiles*.php" devra être créé et mis dans le dossier "maps/maps_local"

*(NE PREND PAS EN CHARGE LES CARTES VECTORIELLES)*

Ajouter des cartes en ligne
---------------------------
Pour ajouter des cartes en ligne dans le dossier "maps/maps_online", créez un fichier "tiles*.js",
en suivant la logique suivante :
```
    L.tileLayer('https://tiles.openrailwaymap.org/standard/{z}/{x}/{y}.png', {
        maxZoom: 19
    }).addTo(map);
```


Installation
---------------------------
Copier-coller les fichiers sur votre serveur
