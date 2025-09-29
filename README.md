Maps-Webapp
=================

Utiliser une application 'single-user' afin de profiter de cartes de différentes sources


Configuration
--------------
Créez un fichier .env (à placer à la racine du projet) avec au minimum :
```
    LOGIN="Utilisateur"
    PASSWORD="Mot_de_passe"
```

Vous devez configurer une carte par défaut dans le fichier .env :
*(Les cartes se trouvent soit dans maps/maps_local soit dans maps/maps_online)*
```
    DEFAULT_MAP=tiles_online.js
```

Il est aussi possible de masquer des cartes dans le fichier .env :
*(Séparez les entrées avec une virgule)*
```
    HIDE_MAPS=tiles_online.js,tiles_local.php
```


Installation
--------------
Copier-coller les fichiers sur votre serveur
