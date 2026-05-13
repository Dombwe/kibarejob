# Mise en ligne KIBARE-JOB

Ce document sert de plan de production pour le backend Symfony, l'application mobile, les imports automatiques, les emails et les sauvegardes.

## 1. Architecture recommandée

- Backend Symfony 6.4 en `APP_ENV=prod`.
- PHP 8.2 ou plus récent avec `intl`, `mbstring`, `pdo_mysql` ou `pdo_pgsql`, `zip`, `sodium`, `opcache`.
- Base de données production dédiée : MySQL 8 ou PostgreSQL 16.
- Redis pour cache, locks, quotas et scores de matching.
- Reverse proxy HTTPS : Nginx ou Apache.
- Stockage persistant pour `backend/var/storage`.
- SMTP réel pour confirmations email et candidatures.
- Cron système toutes les minutes pour exécuter les commandes automatisées.

## 2. Variables d'environnement

Créer un fichier secret côté serveur à partir de :

```bash
cp backend/.env.prod.example backend/.env.local
```

À modifier impérativement :

- `APP_SECRET`
- `APP_PUBLIC_URL`
- `DATABASE_URL`
- `REDIS_URL`
- `MAILER_DSN`
- `MAIL_FROM`
- `CONTACT_TO`
- `JWT_PASSPHRASE`
- `GOOGLE_CLIENT_ID`
- `GOOGLE_CLIENT_SECRET`
- `RAPIDAPI_KEY`

Ne jamais commiter `.env.local`, `.env.prod` ou des clés JWT privées.

## 3. Déploiement backend

Depuis `backend/` :

```bash
composer install --no-dev --optimize-autoloader
php bin/console cache:clear --env=prod
php bin/console doctrine:migrations:migrate --no-interaction --env=prod
php bin/console assets:install public --env=prod
```

Vérifier ensuite :

```bash
php bin/console about --env=prod
php bin/console lint:container --env=prod
```

Le dossier suivant doit être persistant et inscriptible par PHP :

```text
backend/var/storage
```

Il contient les CV, documents candidats, CV adaptés, lettres de motivation et fichiers générés.

Variables utiles :

```text
KIBARE_STORAGE_PATH=var/storage
KIBARE_STORAGE_BASE_URL=/storage
```

Pour un serveur dédié, `KIBARE_STORAGE_PATH` peut être un chemin absolu, par exemple :

```text
KIBARE_STORAGE_PATH=/var/lib/kibarejob/storage
```

Dans ce cas, le dossier doit être créé, appartenir à l'utilisateur PHP et être inclus dans les sauvegardes. Les fichiers restent servis par la route contrôlée `/storage/...`, ce qui évite de dépendre d'un lien symbolique fragile dans `public/`.

Vérifier le stockage :

```bash
php bin/console app:storage:check --env=prod
```

Puis ouvrir l'URL affichée par la commande depuis un navigateur ou depuis le téléphone utilisé pour tester l'application mobile.

## 4. Emails SMTP réels

Configurer `MAILER_DSN`, par exemple :

```text
smtp://user:password@smtp.example.com:587?encryption=tls
```

À valider avant mise en ligne :

- email de confirmation de compte candidat
- email de réinitialisation de mot de passe
- email de contact
- email de candidature avec pièces jointes
- copie candidat en CC

Important : l'expéditeur technique doit être `MAIL_FROM`. Le candidat doit apparaître en `Reply-To`, car la plupart des SMTP n'autorisent pas l'envoi direct avec une adresse expéditeur externe non vérifiée.

## 5. Import automatique des offres

Les sources d'import se configurent dans l'admin :

```text
/admin/job-import-source
```

Commande manuelle :

```bash
php bin/console jobs:import-external --limit=50 --env=prod
```

Commande pour une source :

```bash
php bin/console jobs:import-external --source="Nom exact" --limit=50 --env=prod
```

Les offres importées doivent être surveillées dans :

```text
/admin/job-offer
/admin/external-job-offer
```

## 6. Cron production

Le fichier `backend/config/cron.yaml` liste les tâches à automatiser :

- `jobs:import-external --limit=50`
- `matching:precompute-scores`
- `subscription:reset-daily`
- `subscription:reset-monthly`
- `document:cleanup`
- `app:scheduled-commands:run`

Cron Linux recommandé :

```cron
* * * * * cd /var/www/kibarejob/backend && php bin/console app:scheduled-commands:run --env=prod >> var/log/cron.log 2>&1
15 */4 * * * cd /var/www/kibarejob/backend && php bin/console jobs:import-external --limit=50 --env=prod >> var/log/imports.log 2>&1
0 */6 * * * cd /var/www/kibarejob/backend && php bin/console matching:precompute-scores --env=prod >> var/log/matching.log 2>&1
0 0 * * * cd /var/www/kibarejob/backend && php bin/console subscription:reset-daily --env=prod >> var/log/quotas.log 2>&1
0 0 1 * * cd /var/www/kibarejob/backend && php bin/console subscription:reset-monthly --env=prod >> var/log/quotas.log 2>&1
30 2 * * * cd /var/www/kibarejob/backend && php bin/console document:cleanup --env=prod >> var/log/documents-cleanup.log 2>&1
```

Planificateur Windows : créer une tâche qui lance toutes les 5 minutes :

```powershell
php C:\path\to\kibarejob\backend\bin\console app:scheduled-commands:run --env=prod
```

Créer des tâches séparées pour les imports et les scores si nécessaire.

## 7. Sauvegardes

À sauvegarder au minimum :

- base de données
- `backend/var/storage`
- `.env.local` ou secrets du serveur
- `backend/config/jwt/private.pem`
- `backend/config/jwt/public.pem`

Fréquence minimale :

- base de données : quotidienne
- fichiers candidats : quotidienne
- conservation : 7 jours journaliers, 4 semaines hebdomadaires, 6 mois mensuels

Exemple MySQL :

```bash
mysqldump -u kibarejob_user -p kibarejob_prod | gzip > backups/db/kibarejob_$(date +%F).sql.gz
tar -czf backups/storage/kibarejob_storage_$(date +%F).tar.gz backend/var/storage
```

Exemple PostgreSQL :

```bash
pg_dump "$DATABASE_URL" | gzip > backups/db/kibarejob_$(date +%F).sql.gz
tar -czf backups/storage/kibarejob_storage_$(date +%F).tar.gz backend/var/storage
```

Tester une restauration avant le lancement officiel.

## 8. Mobile production

Configurer l'URL active dans l'admin :

```text
/admin/application-setting
```

L'application mobile lit ensuite `/api/app-config` pour utiliser la même URL que le backend. En production, compiler aussi l'application avec l'URL publique du backend :

```bash
flutter build apk --release --dart-define=API_BASE_URL=https://api.kibarejob.com
```

Pour Android App Bundle :

```bash
flutter build appbundle --release --dart-define=API_BASE_URL=https://api.kibarejob.com
```

À vérifier :

- connexion email
- connexion Google
- chargement des offres
- upload document
- prévisualisation PDF
- swipe candidature
- page candidatures
- mode hors ligne

## 9. Checklist finale

- HTTPS actif et certificat valide.
- `APP_ENV=prod`, `APP_DEBUG=0`.
- Migrations appliquées.
- JWT générés et protégés.
- SMTP testé.
- Redis actif.
- Cron actif.
- Imports externes testés.
- Sauvegardes automatiques testées.
- Upload et preview documents testés.
- Logs surveillés.
- Application mobile compilée avec l'URL backend publique.
