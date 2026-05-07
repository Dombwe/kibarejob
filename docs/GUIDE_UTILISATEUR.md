# Guide utilisateur KIBARE-JOB

## Candidats

### Creer un compte

1. Ouvre l'application mobile.
2. Appuie sur `Creer un compte`.
3. Renseigne prenom, nom, ville, email, telephone et mot de passe.
4. L'application ouvre le feed d'offres.

### Completer son profil

1. Va dans `Profil`.
2. Verifie la ville, le niveau d'etudes, la disponibilite et les competences.
3. Ajoute un CV ou laisse KIBARE-JOB generer un CV plus tard.

### Ajouter des documents

1. Va dans `Documents`.
2. Appuie sur `Ajouter`.
3. Choisis le type: diplome, certificat, attestation ou autre.
4. Selectionne le fichier.
5. Envoie le document.

Les images sont compressees avant envoi pour economiser les donnees.

### Swiper les offres

Dans l'ecran `Offres`:

- bouton rouge: passer l'offre,
- bouton vert: postuler,
- bouton bleu: super like.

Si la connexion est faible, les swipes sont gardes localement et synchronises plus tard.

### Suivre les candidatures

1. Va dans `Candidatures`.
2. Consulte le statut: envoyee, vue, entretien, rejetee ou embauche.
3. Ouvre une candidature pour voir le CV, la lettre et les documents joints.

## Employeurs

### Creer un compte employeur

L'API permet la creation d'un compte employeur via `/api/auth/register` avec `accountType=employeur`.

### Publier une offre

1. Connecte-toi comme employeur.
2. Envoie une requete `POST /api/employer/offers`.
3. Renseigne titre, description, competences, niveau, contrat, lieu et deadline.

En phase 1, `SUBSCRIPTION_MODE=mock`: les quotas sont ouverts pour les tests.

### Consulter les candidatures

1. Utilise `GET /api/employer/applications`.
2. Ouvre une candidature avec `GET /api/employer/applications/{id}`.
3. Change le statut avec `POST /api/employer/applications/{id}/status`.

### Moderation/admin

Le backoffice est accessible via:

```text
/admin
```

Il permet de moderer:

- utilisateurs,
- employeurs,
- offres,
- documents,
- signalements.
