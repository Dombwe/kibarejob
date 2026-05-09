# Automatisation des commandes KIBARE-JOB

L'espace admin contient un menu `Commandes / Cron` pour enregistrer les commandes Symfony a automatiser.

## Principe

1. Creez ou activez une commande dans `/admin/scheduled-command`.
2. Definissez sa frequence : toutes les 15 minutes, toutes les heures, toutes les 6 heures, chaque jour, chaque semaine, chaque mois ou cron personnalise.
3. Testez avec le bouton `Executer`.
4. Planifiez une seule commande runner sur le serveur :

```bash
php bin/console app:scheduled-commands:run
```

Ce runner verifie les commandes actives et execute uniquement celles qui sont dues.

## Windows

Depuis PowerShell ou CMD, adaptez le chemin si necessaire :

```bat
schtasks /Create /TN "KIBARE-JOB Cron" /SC MINUTE /MO 15 /TR "cd /d C:\wamp64\www\solutions\kibarejob\backend && php bin\console app:scheduled-commands:run" /F
```

## Linux / cron

```cron
*/15 * * * * cd /var/www/kibarejob/backend && php bin/console app:scheduled-commands:run >> var/log/scheduled_commands.log 2>&1
```

## Commandes preconfigurees

- `jobs:import-external` : importe les offres externes depuis les sources actives.
- `matching:precompute-scores` : precalcule les scores de matching.
- `document:cleanup` : nettoie les documents expires.
- `subscription:reset-daily` : remet a zero les quotas journaliers.
- `subscription:reset-monthly` : remet a zero les quotas mensuels.
