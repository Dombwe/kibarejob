# Charte graphique KIBARE-JOB

## Positionnement visuel

KIBARE-JOB doit paraitre moderne, professionnel, calme et premium. L'interface evite les couleurs tres vives et privilegie des tons doux, lisibles et fiables.

Le theme actuel est le theme Light. Le theme Dark reprend les memes intentions avec des fonds profonds, des contrastes controles et des accents sobres.

## Palette principale

| Role | Couleur | Hex | Usage |
| --- | --- | --- | --- |
| Primary | Ardoise premium | `#263849` | Titres, boutons principaux, surfaces fortes |
| Secondary | Bleu-gris | `#5F7F99` | Liens, actions secondaires, graphiques |
| Accent | Sauge clair | `#B8C7B4` | Badges doux, fonds subtils, confirmations |
| Light background | Gris froid | `#F8FAFC` | Fond principal en Light Mode |
| Dark background | Nuit ardoise | `#111827` | Fond principal en Dark Mode |
| Dark surface | Ardoise profonde | `#1E293B` | Cartes et panneaux en Dark Mode |

## Regles de couleur

- Eviter les rouges, oranges, jaunes, roses et violets satures dans les interfaces courantes.
- Utiliser `primary` pour les actions importantes.
- Utiliser `secondary` pour les liens, boutons de navigation et accents actifs.
- Utiliser `accent` pour les etats positifs et les fonds doux.
- Les erreurs doivent rester lisibles mais sobres : preferer un fond gris clair ou ardoise doux avec texte fonce, sauf besoin critique.
- Les graphiques utilisent des variations de `secondary`, `accent`, `primary` et `slate`.

## Light Mode

- Fond de page : degrade tres leger `slate-50 -> white -> stone-50`.
- Cartes : blanc, bordure `slate-100`, ombre douce.
- Texte principal : `primary` ou `slate-950`.
- Texte secondaire : `slate-500` a `slate-700`.
- Bouton principal : degrade `secondary -> primary` ou fond `primary`.

## Dark Mode

- Fond de page : `#111827`.
- Cartes : `#1E293B` avec transparence legere.
- Texte principal : `#F8FAFC`.
- Texte secondaire : `#CBD5E1`.
- Bordures : `rgba(148, 163, 184, 0.18)`.
- Accents : `accent` et `secondary` en faible intensite.

## Typographie

- Famille : system UI sans-serif.
- Titres : font-weight `900` ou `800`.
- Corps : font-weight `500` a `600`.
- Les titres doivent rester courts et directs.
- Eviter les tailles trop grandes dans les dashboards. Les grands titres sont reserves aux pages d'accueil et pages d'authentification.

## Composants

### Boutons

- Rayon : `rounded-2xl` ou `rounded-full` selon le contexte.
- Bouton principal : fond `primary` ou degrade `secondary -> primary`, texte blanc.
- Bouton secondaire : fond blanc, bordure `slate-200`, texte `slate-600`.
- En Dark Mode, les boutons secondaires deviennent des surfaces ardoise.

### Cartes

- Rayon : `rounded-2xl` a `rounded-3xl`.
- Fond Light : blanc.
- Fond Dark : ardoise profonde.
- Ombre : douce, jamais agressive.
- Pas de couleurs vives en fond de carte.

### Formulaires

- Champs avec bordure `slate-200`, focus `secondary/20`.
- Texte clair, libelles courts.
- Les erreurs doivent etre explicites et lisibles.

### Theme switch

- Le bouton de theme est global et fixe en bas a droite.
- Il utilise `localStorage` avec la cle `kibarejob-theme`.
- Valeurs possibles : `light`, `dark`.
- Si aucune preference n'est stockee, la preference systeme peut etre appliquee.

## Accessibilite

- Toujours garder un contraste suffisant entre texte et fond.
- Ne pas transmettre une information seulement par la couleur.
- Les boutons iconiques doivent avoir un libelle accessible.
- Les zones interactives doivent rester assez grandes sur mobile.

## Direction future

- Garder cette palette pour le backend web.
- Le mobile Flutter doit reutiliser les memes tokens de couleur.
- Toute nouvelle page doit etre testee en Light Mode et Dark Mode avant validation.
