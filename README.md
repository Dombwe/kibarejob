# KIBARE-JOB

KIBARE-JOB est une plateforme de recrutement pour le Burkina Faso et l'Afrique. Elle combine un backend Symfony, une application mobile Flutter et une logique de matching locale pour permettre aux candidats de swiper des offres et aux employeurs de recevoir des candidatures pre-qualifiees.

## Stack

- Backend: Symfony 6.4, PHP 8.2, Doctrine, MySQL 8
- Cache/queues: Redis, Symfony Messenger
- Admin: EasyAdminBundle
- Mobile: Flutter, Riverpod, GoRouter, Dio, Hive
- Paiements phase 1: mode mock, 100% gratuit

## Prerequis

- Docker et Docker Compose
- Git
- Optionnel local: PHP 8.2, Composer 2, MySQL 8, Redis 7
- Optionnel mobile: Flutter stable

## Installation en 5 minutes avec Docker

```bash
git clone https://github.com/Dombwe/kibarejob.git
cd kibarejob
docker compose up -d --build
```

Backend:

```text
http://localhost:8000
```

Admin:

```text
http://localhost:8000/admin
```

API:

```text
http://localhost:8000/api
```

## Installation locale backend

```bash
cd backend
composer install
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
php -S 127.0.0.1:8000 -t public
```

## Installation mobile

```bash
cd mobile
flutter pub get
flutter analyze
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000
```

Sur un telephone physique, remplace `10.0.2.2` par l'adresse IP locale de ton ordinateur.

## Variables d'environnement principales

Backend:

```env
APP_ENV=dev
APP_SECRET=change-me
DATABASE_URL=mysql://kibarejob:kibarejob@mysql:3306/kibarejob?serverVersion=8.0&charset=utf8mb4
REDIS_URL=redis://redis:6379
REDIS_CACHE_TTL=120
SUBSCRIPTION_MODE=mock
PAYMENT_METHOD=none
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
JWT_PASSPHRASE=
```

Mobile:

```bash
flutter run --dart-define=API_BASE_URL=https://ton-backend.onrender.com
```

## Migrations

```bash
cd backend
php bin/console doctrine:migrations:migrate
```

## Taches planifiees

```bash
php bin/console subscription:reset-daily
php bin/console subscription:reset-monthly
php bin/console document:cleanup --days=90
php bin/console matching:precompute-scores
```

## URLs apres deploiement

- Backend Render: `https://kibarejob-backend.onrender.com`
- Admin Render: `https://kibarejob-backend.onrender.com/admin`
- Mobile APK: artefact GitHub Actions `kibarejob-apk`
- Web employeur phase 1: servi par Symfony ou deploiement Vercel futur

## Documentation

- [Guide deploiement](docs/GUIDE_DEPLOIEMENT.md)
- [Guide utilisateur](docs/GUIDE_UTILISATEUR.md)
- [Reference API](docs/API_REFERENCE.md)
