# Import automatique des offres externes

KIBARE-JOB peut importer des offres depuis des APIs JSON configurées dans l’espace admin.

## Configuration admin

Menu : `Admin > Sources d’offres`

Champs principaux :

- `Nom` : nom interne de la source, par exemple `RapidAPI JSearch BF`.
- `Fournisseur` : `JSON générique` ou `RapidAPI / JSearch`.
- `URL API` : endpoint HTTP qui retourne les offres.
- `RapidAPI Host` : valeur `X-RapidAPI-Host` si la source passe par RapidAPI.
- `Variable clé API` : nom de la variable d’environnement qui contient la clé, par exemple `RAPIDAPI_KEY`.
- `Paramètres` : paramètres GET envoyés à l’API.
- `Chemin des offres` : chemin JSON qui contient la liste des offres, par défaut `data`.
- `Mapping des champs` : correspondance entre les champs KIBARE-JOB et les champs retournés par l’API.
- `Pays ciblés` / `Localités ciblées` : filtre géographique priorisant le Burkina Faso et l’Afrique.
- `Score fiabilité min.` : score minimum avant publication.
- `Publier automatiquement` : si désactivé, les offres importées arrivent en brouillon.

## Mapping par défaut compatible JSearch

```json
{
  "externalId": "job_id",
  "title": "job_title",
  "company": "employer_name",
  "description": "job_description",
  "location": "job_city",
  "country": "job_country",
  "url": "job_apply_link",
  "contractType": "job_employment_type",
  "datePosted": "job_posted_at_datetime_utc"
}
```

## Import manuel

Depuis l’admin, ouvrir une source puis cliquer sur `Importer maintenant`.

Depuis la console :

```bash
php bin/console jobs:import-external
php bin/console jobs:import-external --source="RapidAPI JSearch BF" --limit=20
```

## Automatisation

Planifier la commande via cron ou le planificateur du serveur :

```bash
0 */6 * * * cd /chemin/vers/backend && php bin/console jobs:import-external --limit=50
```

## Fiabilité

Une offre est mieux notée si elle contient :

- un titre,
- une entreprise,
- une description suffisamment complète,
- un lien source valide,
- une localité correspondant aux pays ou villes ciblés.

Les offres déjà importées sont ignorées grâce au couple `source externe + identifiant externe`.
