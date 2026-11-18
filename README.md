# postcards

## Première installation web

1. Générer une clé de 64 caractères hexadécimaux avec `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`.
2. Déposer à la racine, via FTP, un fichier `install_key.php` contenant `<?php return 'clé générée';`, avec la clé de l’étape précédente entre apostrophes.
3. Ouvrir `install.php` en HTTPS, saisir la clé et suivre les étapes jusqu’à la création du premier compte. Retirer ensuite `install_key.php` du serveur.

Une instance déjà installée se verrouille automatiquement. La connexion admin utilise `uploads/thumbs` pour conserver les compteurs temporaires de tentatives, partagés entre sessions. PHP doit pouvoir écrire dans ce dossier et Apache doit appliquer le refus d’accès direct à `uploads/`.

## Installation d’Atlas postal sur un téléphone

Servir le site public en HTTPS, à la racine du domaine ou dans son sous-dossier habituel. Conserver `manifest.webmanifest` et `sw.js` à la racine de l’application lors du déploiement.

- Sur Android, ouvrir le site dans Chrome et utiliser l’option d’installation du menu du navigateur.
- Sur iPhone, ouvrir le site dans Safari, utiliser le menu Partager puis « Sur l’écran d’accueil ».
- Pour un emballage Web2App, fournir l’URL HTTPS du site public au service choisi. Le site installé utilise le même serveur PHP et la même base.

Une connexion est nécessaire pour consulter la collection. Hors ligne, l’application affiche un écran avec un lien pour revenir à l’accueil.

L’administration s’ouvre depuis « Connexion » ou « Administration » dans le pied de page public, ou directement à l’adresse `admin/` du site. Pour utiliser « Prendre une photo » dans une application qui embarque le site, le conteneur doit autoriser le sélecteur de fichiers et la capture photo.

## Distance et mots clés

Sur une installation existante, appliquer la migration des mots clés dans **Admin → Migrations**.

Dans **Admin → Paramètres → Mon adresse**, renseigner son pays et sa ville ou son code postal. Dans une fiche carte, renseigner le pays et la ville du correspondant, puis toucher **Calculer**. La distance à vol d’oiseau est estimée entre localités et reste modifiable. Le serveur utilise l’extension PHP cURL pour joindre [le service de géocodage Open-Meteo](https://open-meteo.com/en/docs/geocoding-api), basé sur [GeoNames](https://www.geonames.org/). Seules les données de localité nécessaires à la recherche sont transmises.
