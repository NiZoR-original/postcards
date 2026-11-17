# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

L'utilisateur communique en français ; le code (identifiants, commentaires, messages) est aussi en français.

## Projet

Collection personnelle de cartes postales reçues/envoyées via Postcrossing et Cardzilla.
- **Public** (FR/EN) : `index.php` carte du monde cliquable, `carte.php` fiche d'une carte, `swaps.php` catalogue de cartes à échanger (lien partagé sur le Discord Postcrossing, lecture seule).
- **Admin** (`admin/`, FR uniquement, login par mot de passe) : saisie des cartes, des swaps et de leurs photos.

PHP 8.5 natif sans framework ni Composer, MySQL via PDO, servi par Apache + PHP-FPM depuis `~/public_html` (Fedora, SELinux actif). Pas de build, pas de tests automatisés. Les autres dossiers de `~/public_html` (stamps, timbres…) sont des projets distincts sur le même domaine.

## Installation et mises à jour

Le serveur de production n'a **pas d'accès SSH** : les fichiers y sont déposés en FTP, et tout ce qui doit tourner côté serveur doit être faisable depuis le navigateur.
- **Installation** : `install.php` (installeur web). Il vérifie l'environnement, écrit `db_config.php` (ou affiche son contenu à déposer en FTP si la racine n'est pas inscriptible), applique les migrations et crée le premier admin. Il se verrouille dès qu'un admin existe et ne modifie jamais un `db_config.php` existant. Tant que `db_config.php` est absent, `config.php` redirige toutes les pages vers `install.php`.
- **Déploiement** : hébergement mutualisé OVH. `.github/workflows/deploiement.yml` envoie en FTP les fichiers modifiés à chaque push sur `main` (secrets `FTP_SERVER`/`FTP_USERNAME`/`FTP_PASSWORD`, variables `FTP_DIR`/`FTP_PROTOCOL`). `scripts/`, `db_config.php` et les photos ne sont jamais envoyés ; les dossiers `uploads/full|thumbs` sont créés par `install.php`.
- **Mises à jour** : après le déploiement, appliquer les migrations depuis la page admin `admin/migrations.php` (un bandeau dans l'admin signale celles en attente).
- Les scripts `scripts/*.php` en ligne de commande font la même chose pour un usage local.

## Commandes (local)

```bash
php -S 127.0.0.1:8765 -t .                        # serveur de test (ignore les .htaccess)
php scripts/installer.php                         # équivalent CLI de install.php
php scripts/migrer.php                            # applique les migrations en attente (à lancer après chaque mise à jour du code)
php scripts/migrer.php statut                     # état des migrations
php scripts/migrer.php creer ajout_tags [php]     # crée sql/migrations/NNNN_ajout_tags.sql (ou .php)
php scripts/creer_utilisateur.php <identifiant>   # crée l'admin / réinitialise son mot de passe
for f in $(git ls-files '*.php'); do php -l "$f"; done   # vérification syntaxique
node --check assets/map.js
```

### Migrations

Le schéma est défini **uniquement** par `sql/migrations/NNNN_*.sql|php`, appliquées dans l'ordre et enregistrées dans la table `migrations` (`includes/migrations.php`). `0001_schema_initial.sql` est le schéma de départ. Règles :
- Toute modification de schéma passe par une **nouvelle** migration (`migrer.php creer …`) ; ne jamais modifier une migration existante, elle est peut-être déjà appliquée ailleurs.
- Les `.sql` sont découpées sur `;` hors chaînes et commentaires (`decouper_sql()`) ; pas de `DELIMITER` ni de procédures stockées.
- Les `.php` reçoivent `$pdo` : à utiliser pour les migrations de données ou de fichiers (ex. régénérer les miniatures).
- En MySQL, le DDL valide implicitement la transaction : une migration qui échoue en cours de route peut rester partiellement appliquée. Faire une modification par migration.
- L'admin affiche un bandeau quand des migrations sont en attente.

## Versions et commits

- Chaque changement notable est noté dans `version.txt` (journal en français, entrée la plus récente en haut, format `X.Y.Z.W du JJ/MM/AAAA`). Une seule entrée par jour : s'il y en a déjà une à la date du jour, y ajouter les puces ; sinon créer une nouvelle version. Chaque puce commence par exactement `NEW`, `BUGFIX`, `UPDATE` ou `REVIEW`.
- Commits et mise en ligne via `/pushabc` : branche de travail `new/…`, `update/…` ou `bugfix/…`, merge dans `develop`, puis dans `main`. Le push sur `main` déploie en production. Messages de commit en français au format `NEW : … ; UPDATE : …`, sans ligne `Co-Authored-By`.

## Architecture

- `config.php` est le point d'entrée de chaque page : session (`includes/session.php`, nom `POSTCARDS_SESS`, distinct des autres applis du domaine), constantes, et inclusion de `includes/db.php` (`db()` = PDO singleton), `helpers.php`, `pays.php`. Les pages publiques ajoutent `includes/i18n.php` + `layout_public.php` ; les pages admin incluent uniquement `includes/admin.php`, qui fait le contrôle d'accès dès son inclusion et fournit la mise en page et le bloc photos.
- **Visibilité** : à trois niveaux, à respecter dans toute nouvelle requête publique. `cartes.publique` masque une carte entière ; `photos.publique` masque une photo ; les champs `notes` (cartes, swaps) et `reserve_pour` (swaps) ne sont jamais affichés publiquement. Un swap `echangee` disparaît du public.
- **Photos** : table unique `photos` liée soit à `carte_id`, soit à `swap_id` (FK en cascade). Les fichiers sont dans `uploads/full` et `uploads/thumbs` sous un nom aléatoire, ré-encodés en JPEG par `includes/photos.php` (redressement EXIF, suppression des métadonnées dont le GPS). `uploads/` est interdit d'accès direct (`.htaccess`) : tout passe par `photo.php`, qui vérifie la visibilité de la photo **et** de son parent, ou une session admin. La suppression d'une carte ou d'un swap doit d'abord appeler `supprimer_photos_de()` (la cascade SQL ne supprime pas les fichiers).
- **Carte du monde** : jsVectorMap 1.6.0 (CDN jsdelivr). `index.php` injecte `window.PC` (compteurs par pays, coordonnées des petits pays) et `assets/map.js` construit la carte, puis interroge `api/pays.php` au clic. Le fond « world » ne contient que 173 pays : les autres (SG, HK, MT, RE…) sont dessinés en marqueurs grâce à `COORDS_PETITS_PAYS` dans `includes/pays.php`. Tout pays saisissable mais absent du fond **et** de cette table n'apparaît que dans la liste sous la carte.
- **Pays** : codes ISO alpha-2 dans `CODES_PAYS` ; noms traduits à la volée par ICU (`Locale::getDisplayRegion`, `Intl.DisplayNames` côté JS). Ne pas stocker de noms de pays en base.
- **Liens plateforme** : `lien_plateforme()` utilise `cartes.lien` s'il est renseigné, sinon déduit l'URL Postcrossing de la référence. Le format d'URL Cardzilla n'est pas déduit : il faut saisir le lien.
- **i18n** : `t('cle')` avec dictionnaire FR/EN dans `includes/i18n.php` ; langue = `?lang=`, puis cookie `pc_lang`, puis Accept-Language. Toute nouvelle chaîne publique doit être ajoutée dans les deux langues.
- Les formulaires admin passent par `csrf_field()` / `csrf_verifier()`, et les redirections avec message par `flash()`.

## Environnement serveur

- PHP-FPM tourne sous l'utilisateur `apache`. `uploads/full` et `uploads/thumbs` lui sont ouverts en écriture par ACL (`setfacl -m u:apache:rwx -d -m u:apache:rwx`), avec le contexte SELinux `httpd_sys_rw_content_t`. Tout nouveau dossier inscriptible doit recevoir les deux.
- Taille d'envoi limitée par `upload_max_filesize` / `post_max_size` de PHP.
- `includes/`, `sql/` et `scripts/` sont bloqués par `.htaccess`. `db_config*.php`, `*.sql` et `*.md` sont aussi refusés à la racine.
