---
description: Commit la branche courante, merge dans develop, push develop, supprime la branche, merge develop dans main, push main (déclenche le déploiement OVH), rebascule sur develop
---

Exécute cette séquence git complète, dans l'ordre, sans t'arrêter pour confirmation entre les étapes (l'utilisateur a explicitement autorisé l'automatisation complète de cette commande, y compris le push final sur `main`).

Avant de commencer : lance `git status` et `git branch --show-current`. Si la branche `develop` n'existe pas encore (ni en local ni sur `origin`), crée-la depuis `main` (`git branch develop main`) avant la suite. Cette commande part d'une branche de travail dédiée ; si la branche courante est `develop` ou `main` :
- **Des changements sont en attente** : crée d'abord une branche de travail à partir de la branche courante avec `git checkout -b <type>/<description-courte>` (les modifications non commitées la suivent), puis enchaîne normalement la séquence ci-dessous depuis cette branche. Préfixe `new/`, `update/` ou `bugfix/` selon la nature dominante des changements (cohérent avec les puces NEW/UPDATE/BUGFIX ajoutées dans `version.txt`), suivi d'une description courte en kebab-case sans accents (ex. `update/filtres-swaps`). Signale le nom de la branche créée dans le résumé final.
- **Aucun changement en attente** (`git status` propre) : arrête-toi et préviens l'utilisateur, il n'y a rien à commiter ni de branche de travail à merger.

1. **Commit sur la branche courante** (si des changements sont en attente — `git status` propre = étape sautée) :
   - Vérifie que `version.txt` contient bien des puces pour les changements commités (voir CLAUDE.md) ; sinon ajoute-les avant de commiter.
   - `git add` des fichiers pertinents (jamais un `git add -A`/`git add .` aveugle — vérifier `git status` pour ne pas inclure de fichier suspect : `db_config.php`, photos de `uploads/`, `.claude/settings.local.json`).
   - Rédige un message de commit concis en français, dans le style des commits existants du repo (`git log --oneline -10`) : `NEW : … ; UPDATE : … ; BUGFIX : …`, qui reflète le "pourquoi" des changements.
   - N'ajoute aucune ligne `Co-Authored-By` (ni autre mention d'attribution) au message.

2. **Merge dans develop** :
   - `git checkout develop`
   - `git pull` (si un remote suit develop, pour éviter un merge sur une base périmée)
   - `git merge --no-ff <branche-de-travail>`

3. **Push develop** :
   - `git push -u origin develop`

4. **Suppression de la branche de travail (local uniquement)** :
   - `git branch -d <branche-de-travail>` (jamais `-D` : si la suppression est refusée car la branche n'est pas mergée, c'est le signal qu'il faut investiguer avant de forcer, pas de forcer directement).

5. **Merge develop dans main** :
   - `git checkout main`
   - `git pull` (si un remote suit main)
   - `git merge --no-ff develop`

6. **Push main** :
   - `git push origin main`

7. **Rebascule sur develop** :
   - `git checkout develop` (laisser le dépôt sur `develop`, pas sur `main`, pour la suite du travail).

Fin : affiche un résumé court (branche mergée, commit(s) inclus, statut final des branches) et rappelle que le push sur `main` déclenche le déploiement FTP vers OVH (GitHub Actions, onglet Actions du dépôt) ; si des migrations ont été ajoutées (`sql/migrations/`), les appliquer ensuite depuis Admin → Migrations sur le site en ligne.

Si une étape échoue (conflit de merge, push rejeté, etc.), arrête-toi immédiatement, explique clairement l'erreur et attends les instructions de l'utilisateur avant de continuer ou de tenter une résolution.
