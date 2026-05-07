# EasyAdmin installation

The admin controllers are ready, but this repository currently has no `backend/composer.json`.

When the Symfony skeleton is present, run:

```bash
cd backend
composer require easycorp/easyadmin-bundle
```

Then ensure `backend/config/routes/admin.yaml` is imported by Symfony route loading, run Doctrine migrations, and open:

```text
/admin
```
