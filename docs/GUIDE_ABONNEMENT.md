# Guide abonnement KIBARE-JOB

Phase 1: l'application est en mode `SUBSCRIPTION_MODE=mock`. Tous les utilisateurs peuvent tester les fonctions Premium/Pro gratuitement pendant la periode de lancement.

## Tarifs en FCFA

| Public | Plan | Prix mensuel | Usage principal |
|---|---:|---:|---|
| Candidat | Gratuit | 0 FCFA | Decouvrir les offres et postuler avec limites |
| Candidat | Premium | 2 000 FCFA | Swipes illimites, super swipe, CV designer |
| Employeur | Gratuit | 0 FCFA | Publier une offre active et tester la plateforme |
| Employeur | Standard | 20 000 FCFA | Recrutement PME avec 5 offres actives |
| Employeur | Pro | 75 000 FCFA | Recrutement intensif, API, support prioritaire |

## Comparaison des plans candidats

| Fonction | Gratuit | Premium |
|---|---:|---:|
| Swipes par jour | 10 | Illimites |
| Documents | 10 | 50 |
| CV | Standard | Templates pro |
| Super swipe | Non | Oui |
| Statistiques personnelles | Non | Oui |
| Publicites | Possible | Non |

## Comparaison des plans employeurs

| Fonction | Gratuit | Standard | Pro |
|---|---:|---:|---:|
| Offres actives | 1 | 5 | Illimitees |
| Candidatures consultables par offre | 10 | 100 | Illimitees |
| Export CSV | Non | Oui | Oui |
| Statistiques avancees | Non | Oui | Oui |
| API access | Non | Non | Oui |
| Support prioritaire | Non | Non | Oui |
| Marque blanche | Non | Non | Optionnelle |

## Comment s'abonner

### Depuis l'application mobile candidat

1. Ouvre `Profil`.
2. Appuie sur `Abonnement`.
3. Choisis `Candidat Premium`.
4. Selectionne le moyen de paiement: Orange Money ou Moov Money.
5. Confirme le paiement.

En phase 1, le paiement est simule et retourne toujours un succes pour permettre les tests.

### Depuis l'espace employeur

1. Connecte-toi comme employeur.
2. Va sur la page `Tarifs`.
3. Choisis `Standard` ou `Pro`.
4. Selectionne Orange Money ou Moov Money.
5. Confirme l'abonnement.

## Paiement Orange Money

Quand le mode production sera active:

1. L'utilisateur choisira Orange Money.
2. KIBARE-JOB initiera une transaction.
3. L'utilisateur recevra une demande de confirmation sur son telephone.
4. L'abonnement sera active apres confirmation.

Variables prevues:

```env
PAYMENT_METHOD=orange_money
ORANGE_MONEY_API_URL=
ORANGE_MONEY_CLIENT_ID=
```

## Paiement Moov Money

Le parcours sera identique:

1. Choix de Moov Money.
2. Saisie du numero.
3. Confirmation sur mobile.
4. Activation de l'abonnement.

Variables prevues:

```env
PAYMENT_METHOD=moov_money
MOOV_MONEY_API_URL=
MOOV_MONEY_CLIENT_ID=
```

## Comment resilier

### Mobile candidat

1. Ouvre `Abonnement`.
2. Appuie sur `Resilier`.
3. Confirme.

Endpoint utilise:

```http
POST /api/subscription/cancel
```

### Employeur

1. Va dans l'espace abonnement.
2. Appuie sur `Resilier`.
3. Confirme.

La resiliation remet le compte au plan gratuit a la fin de la periode active ou immediatement en mode mock.

## Politique phase 1

Pendant les 6 a 12 premiers mois:

- `SUBSCRIPTION_MODE=mock`
- tous les candidats ont les droits Premium,
- tous les employeurs peuvent tester les droits Pro,
- aucun paiement reel n'est preleve.
