# Mise a jour paiements et agents

Ce depot contient uniquement l'API Laravel. Un pull ne met pas a jour le
dashboard Next.js ni l'application Flutter, qui doivent etre publies separement.

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
php artisan up
```

Executer les commandes une par une et arreter en cas d'erreur. Ne pas lancer
`migrate:fresh`, `db:seed` ou `migrate --seed` sur les donnees de production.
La migration de paiement est volontairement irreversible : un rollback de
schema necessite une restauration de sauvegarde verifiee, pas migrate:rollback.

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

Le concert et le CSV importes dans la base locale ne sont pas inclus dans Git.
L'import en production est une operation distincte. Ne pas appliquer le schema
SQL de l'implementation Next.js sur la base Laravel : les schemas different.
