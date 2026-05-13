# Qualité produit KIBARE-JOB

Ce document sert de checklist avant démonstration, recette client ou mise en production.

## Routes critiques à vérifier

- `GET /`
- `GET /candidats`
- `GET /offres-emploi`
- `GET /faq`
- `GET /contact`
- `GET /confidentialite`
- `GET /conditions-utilisation`
- `GET /login`
- `GET /recruteur`
- `GET /recruteur/offres`
- `GET /recruteur/candidatures`
- `GET /admin`
- `GET /admin/job-offer`
- `GET /admin/job-import-source`

Commande locale :

```powershell
.\scripts\check_critical_routes.ps1
```

## Tests mobile multi-écrans

Tester au minimum :

- petit écran Android : 720 x 1516 ou équivalent ;
- écran moyen Android : 1080 x 2400 ;
- grand écran Android ;
- émulateur Android ;
- téléphone physique sur le même réseau que le backend ;
- mode clair ;
- mode sombre ;
- connexion Wi-Fi ;
- connexion mobile ;
- mode hors ligne.

Parcours à valider :

- inscription candidat avec consentement données CV ;
- connexion email ;
- connexion Google ;
- chargement des offres ;
- swipe gauche, favoris, swipe droite ;
- affichage du popup de candidature ;
- upload CV ;
- prévisualisation document ;
- génération CV ;
- suivi d'une candidature ;
- retour arrière depuis chaque écran.

## Nettoyage textes et accents

Avant livraison :

- chercher les textes encodés ou non accentués ;
- corriger les chaînes visibles utilisateur ;
- vérifier les pages en mode sombre ;
- vérifier les messages d'erreur API ;
- vérifier les emails envoyés.

Commandes utiles :

```powershell
rg -n "Ã|Â|â|reessayer|Verifiez|Creer|Telecharger|connexion impossible" backend/templates backend/src mobile/lib
```

## Performance offres

Objectif :

- feed mobile initial inférieur à 2 secondes sur réseau local stable ;
- pagination activée ;
- pas de chargement complet inutile des swipes ;
- descriptions longues nettoyées avant affichage ;
- cache de feed invalidé après upload/profil/swipe important.

Points de contrôle :

- `/api/jobs/feed?limit=10` ;
- `/offres-emploi` avec filtres ;
- import externe limité par source ;
- sources externes filtrées par fiabilité et localité.

## Consentement et données CV

Le candidat doit comprendre que ses données peuvent être utilisées pour :

- compléter son profil ;
- calculer un score de matching ;
- générer un CV adapté ;
- générer une lettre de motivation ;
- envoyer une candidature avec pièces jointes.

La politique de confidentialité est disponible sur :

- `/confidentialite`
- `/conditions-utilisation`

