# API Reference KIBARE-JOB

Base URL locale:

```text
http://localhost:8000
```

Toutes les routes protegees utilisent:

```http
Authorization: Bearer <jwt>
```

## Auth

### POST `/api/auth/register`

Inscription candidat ou employeur.

Body candidat:

```json
{
  "accountType": "candidat",
  "email": "awa@example.com",
  "password": "Password123",
  "firstName": "Awa",
  "lastName": "Ouedraogo",
  "city": "Ouagadougou"
}
```

Body employeur:

```json
{
  "accountType": "employeur",
  "email": "rh@example.com",
  "password": "Password123",
  "companyName": "Faso Tech",
  "sector": "Technologie",
  "cities": ["Ouagadougou"]
}
```

### POST `/api/auth/login`

```json
{
  "email": "awa@example.com",
  "password": "Password123"
}
```

Reponse:

```json
{
  "token": "...",
  "user": {}
}
```

## Profil candidat

### GET `/api/profile`

Retourne le profil candidat connecte.

### PUT `/api/profile`

Met a jour le profil candidat.

### POST `/api/profile/upload-cv`

Multipart:

- `cv`: fichier PDF/DOC/DOCX.

## Profil employeur

### GET `/api/employer/profile`

Retourne le profil employeur connecte.

### PUT `/api/employer/profile`

Met a jour le profil employeur.

### POST `/api/employer/profile/upload-logo`

Multipart:

- `logo`: image JPG/PNG/WEBP.

## Documents candidat

### GET `/api/documents`

Liste les documents du candidat.

### POST `/api/documents/upload`

Multipart:

- `file`
- `title`
- `type`: `diploma`, `certificate`, `attestation`, `other`
- `description`

Supporte aussi l'upload chunked avec:

- `uploadId`
- `chunkIndex`
- `totalChunks`
- `originalName`

### GET `/api/documents/{id}`

Detail d'un document.

### PUT `/api/documents/{id}`

Met a jour les metadonnees.

### DELETE `/api/documents/{id}`

Suppression logique.

### POST `/api/documents/{id}/verify`

Verification locale/mock du document.

## Abonnements

### POST `/api/subscription/plans`

Liste les plans.

### POST `/api/subscription/subscribe`

Initie un abonnement mock.

```json
{
  "planCode": "candidate_premium",
  "method": "none"
}
```

### GET `/api/subscription/status`

Statut du compte et quotas.

### POST `/api/subscription/cancel`

Resilie l'abonnement.

## Offres employeur

### POST `/api/employer/offers`

Cree une offre avec verification quota.

```json
{
  "title": "Developpeur Symfony",
  "description": "Mission backend Symfony",
  "requiredSkills": ["PHP", "Symfony", "MySQL"],
  "requiredEducation": "Licence",
  "requiredExperienceYears": 2,
  "contractType": "CDI",
  "location": "Ouagadougou",
  "deadline": "2026-12-31"
}
```

### GET `/api/employer/offers`

Liste les offres de l'employeur.

### PUT `/api/employer/offers/{id}`

Modifie une offre.

### DELETE `/api/employer/offers/{id}`

Masque/ferme une offre.

## Feed et swipe candidat

### GET `/api/jobs/feed?cursor=0&limit=10`

Retourne les offres avec score de matching.

### POST `/api/jobs/{id}/swipe`

```json
{
  "direction": "like"
}
```

Directions:

- `like`
- `dislike`
- `superlike`

Reponse immediate `202 Accepted`.

## Candidatures candidat

### GET `/api/matches`

Liste les candidatures et statuts.

## Candidatures employeur

### GET `/api/employer/applications`

Liste les candidatures recues.

### GET `/api/employer/applications/{id}`

Detail d'une candidature.

### POST `/api/employer/applications/{id}/status`

```json
{
  "status": "interview"
}
```

Statuts:

- `sent`
- `viewed`
- `interview`
- `rejected`
- `hired`

## Admin

### GET `/admin`

Backoffice EasyAdmin.
