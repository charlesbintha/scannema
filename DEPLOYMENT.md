# Mise a jour paiements et agents

Ce depot contient maintenant le dashboard Blade ET l'API Laravel. Le site web
et le mobile utilisent la meme base Laravel et les memes regles metier.
Next.js n'est plus necessaire pour la production. L'application Flutter reste
un client distinct dont le binaire doit etre distribue separement.

Le dashboard est servi sur `/`, la connexion sur `/login` et l'API mobile
conserve ses routes `/api/...`. Les assets CSS, JavaScript, logo et polices
sont inclus dans `public/` : aucun npm install/build ou service Node requis.

## Avant la mise a jour

- Sauvegarder la base MySQL et verifier que la sauvegarde peut etre restauree.
- Conserver le fichier `.env` de production et les fichiers persistants.
- Planifier une interruption des controles et coordonner la mise a jour mobile :
  les anciens jetons et les requetes sans session ne sont plus acceptes.
- Verifier que le checkout serveur est propre avec `git status`.

## Commandes sur le serveur

Depuis le dossier de l'API, apres sauvegarde :

```sh
php artisan down
git pull --ff-only origin main
composer install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan up
```

Executer les commandes une par une et arreter en cas d'erreur. Ne pas lancer
`migrate:fresh`, `db:seed` ou `migrate --seed` sur les donnees de production.
La migration de paiement est volontairement irreversible : un rollback de
schema necessite une restauration de sauvegarde verifiee, pas migrate:rollback.

## Configuration web

Le document root doit etre le dossier `public/` de ce projet, jamais sa racine
(qui contient `.env` et `.git`). Conserver APP_KEY et les identifiants MySQL
existants. Pour le site HTTPS :

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://scannema.emmaluxury.store
SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
CACHE_STORE=file
```

`storage/` et `bootstrap/cache/` doivent etre inscriptibles par PHP, sans droits
777. SESSION_DRIVER=database est aussi supporte via la nouvelle migration
`2026_10_02_000001_add_web_sessions`. CACHE_STORE=file evite de dependre d'une
table cache absente du schema historique. Si les assets ou formulaires sortent
en HTTP derriere un reverse proxy, corriger sa configuration HTTPS et ne faire
confiance qu'aux proxies effectivement utilises.

Les comptes Laravel existants servent a la connexion web. Il n'y a ni compte
par defaut ajoute, ni inscription publique. SUPER_ADMIN gere les agents,
MANAGER gere ses evenements et leurs paiements, CHECKER est en lecture seule
sur le web et utilise le mobile pour scanner. Les formulaires web utilisent
la protection CSRF de Laravel et un cookie de session HttpOnly ; aucun jeton
n'est expose au JavaScript. Les sessions revoquees par l'API ne donnent plus
acces au web non plus.

## Verification fonctionnelle

- Reconnecter les utilisateurs : les sessions opaques expirent apres 12 heures.
- Affecter explicitement les evenements aux CHECKER et MANAGER dans event_users.
  Sans affectation, ils ne voient aucun evenement. SUPER_ADMIN voit ceux de son
  organisation. L'API agents est reservee au SUPER_ADMIN.
- Les invitations existantes deviennent UNPAID, sans inventer un paiement.
  Confirmer uniquement les reglements reellement recus avant les controles.
- Tester avec un ticket dedie : impaye refuse, paiement confirme, premier scan
  accepte, deuxieme scan refuse. Tester aussi un evenement non affecte.
- Modifier une affectation revoque les sessions de l'agent concerne.
- Verifier `/login`, le dashboard, les filtres/pagination, la confirmation d'un
  paiement multiple, l'import CSV et la page Agents. Ne pas utiliser un ticket
  reel pour un test de scan destructif.

Le concert et le CSV importes dans la base locale ne sont pas inclus dans Git.
L'import en production est une operation distincte. Ne pas appliquer le schema
SQL de l'ancienne implementation Next.js sur la base Laravel : les schemas
different. Utiliser `concert_st_kisito_2026_laravel.sql` fourni separement.
