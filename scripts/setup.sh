#!/usr/bin/env sh
set -eu

echo "KIBARE-JOB setup"

if command -v docker >/dev/null 2>&1; then
  echo "Starting Docker services..."
  docker compose up -d --build
  echo "Backend: http://localhost:8000"
else
  echo "Docker is not installed. Falling back to local backend setup."
  cd backend
  composer install
  php bin/console doctrine:migrations:migrate --no-interaction
  php -S 0.0.0.0:8000 -t public
fi
