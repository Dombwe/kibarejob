# Guide de deploiement KIBARE-JOB

Ce guide privilegie des services gratuits ou avec quota gratuit suffisant pour lancer la phase 1.

## Backend Symfony sur Render

1. Connecte le depot GitHub `Dombwe/kibarejob` a Render.
2. Cree un nouveau service Web.
3. Choisis le runtime Docker.
4. Root directory: `backend`.
5. Dockerfile path: `./Dockerfile`.
6. Ajoute les variables d'environnement:

```env
APP_ENV=prod
APP_SECRET=<valeur_generee>
DATABASE_URL=<url_mysql_distante>
REDIS_URL=<url_redis_distante>
REDIS_CACHE_TTL=120
SUBSCRIPTION_MODE=mock
PAYMENT_METHOD=none
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
JWT_SECRET_KEY=/app/config/jwt/private.pem
JWT_PUBLIC_KEY=/app/config/jwt/public.pem
JWT_PASSPHRASE=
```

7. Apres le premier deploiement, lance les migrations via le shell Render:

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

## Base de donnees MySQL gratuite

Options:

- Aiven MySQL: cree un service MySQL gratuit si l'offre est disponible dans ta region.
- PlanetScale: utile pour MySQL serverless si le plan gratuit est disponible.
- Railway ou Clever Cloud peuvent servir d'alternatives si les quotas gratuits changent.

Format `DATABASE_URL`:

```env
mysql://USER:PASSWORD@HOST:PORT/DB_NAME?serverVersion=8.0&charset=utf8mb4
```

## Redis gratuit avec Upstash

1. Cree une base Redis sur Upstash.
2. Copie l'URL Redis.
3. Ajoute-la dans Render:

```env
REDIS_URL=redis://default:PASSWORD@HOST:PORT
```

Si Upstash fournit une URL TLS, utilise `rediss://`.

## Web employeur sur Vercel

Phase 1: le backoffice et les vues employeur sont servies par Symfony.

Quand le frontend employeur sera separe:

1. Cree un projet Vercel connecte au depot.
2. Root directory: dossier du frontend web.
3. Ajoute `API_BASE_URL=https://kibarejob-backend.onrender.com`.
4. Deploie automatiquement depuis `main`.

Le fichier `vercel.json` actuel sert de placeholder compatible phase 1.

## Mobile APK via GitHub Actions

Le workflow `.github/workflows/build-mobile.yml`:

- installe Flutter,
- execute `flutter pub get`,
- execute `flutter analyze`,
- construit un APK release,
- publie l'APK en artefact.

Secret optionnel:

```text
API_BASE_URL=https://kibarejob-backend.onrender.com
```

## Cron et maintenance

GitHub Actions peut executer:

- reset quotidien des swipes,
- reset mensuel des quotas employeurs,
- nettoyage des documents supprimes,
- pre-calcul des scores.

Configure ces secrets GitHub:

```text
APP_SECRET
DATABASE_URL
REDIS_URL
RENDER_DEPLOY_HOOK_URL
```
