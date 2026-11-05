# Playbook : règles générales de projet

Un recueil de règles, de pratiques et de contrôles **indépendants de tout produit**, tirés de l'expérience
d'un projet réel (application web multi-organisations, avec paiements, billets et accès hors ligne) et
réécrits pour s'appliquer à n'importe quel projet logiciel.

Rien ici ne parle d'un métier, d'un client ni d'un produit. Quand un exemple technique aide (Laravel, React,
PostgreSQL, Docker), il est donné entre parenthèses ou dans un encadré « Exemple » : le principe reste valable
avec une autre technologie.

## Comment l'utiliser

1. **Copier le dossier `playbook/`** dans le nouveau projet (ou le garder dans un dépôt à part et le citer).
2. **Lire `01-principes-et-methode.md`**, puis ne lire des autres fichiers que ce dont le moment a besoin.
3. **Générer le fichier d'instructions du projet** (celui que lit l'assistant ou l'équipe à chaque session) à
   partir de `11-modele-de-fichier-d-instructions.md`.
4. **Dérouler les listes de `10-checklists.md`** avant un commit, une livraison, une ouverture au public.
5. **Écrire les décisions propres au projet à part** (produit, métier, choix de pile). Ce dossier ne les
   contient pas, et ne doit pas les contenir : c'est ce qui le rend réutilisable.

## Les fichiers

| Fichier | À lire quand... |
| --- | --- |
| [`01-principes-et-methode.md`](01-principes-et-methode.md) | on démarre un projet ou une session, et à chaque décision qui engage |
| [`02-conventions-de-code.md`](02-conventions-de-code.md) | on écrit ou relit du code |
| [`03-tests-et-qualite.md`](03-tests-et-qualite.md) | on écrit un test, ou on décide quoi tester |
| [`04-securite.md`](04-securite.md) | on conçoit, on développe ou on revoit une fonction exposée |
| [`05-donnees-concurrence-multi-organisation.md`](05-donnees-concurrence-multi-organisation.md) | on touche à la base, aux migrations, aux verrous, à l'isolation entre clients |
| [`06-interface-accessibilite-langues.md`](06-interface-accessibilite-langues.md) | on construit un écran, un formulaire, un texte affiché |
| [`07-livraison-et-exploitation.md`](07-livraison-et-exploitation.md) | on livre, on exploite, on sauvegarde, on surveille |
| [`08-confidentialite-et-conformite.md`](08-confidentialite-et-conformite.md) | on collecte des données personnelles, on rédige des textes légaux |
| [`09-services-externes.md`](09-services-externes.md) | on choisit ou on branche un service tiers |
| [`10-checklists.md`](10-checklists.md) | avant de commiter, livrer, ouvrir au public |
| [`11-modele-de-fichier-d-instructions.md`](11-modele-de-fichier-d-instructions.md) | on démarre un projet et on veut son fichier d'instructions |
| [`12-travailler-avec-un-assistant.md`](12-travailler-avec-un-assistant.md) | on confie du travail à un assistant de programmation |

## Conventions de lecture

- **Règle** : ce qui s'applique sans négociation, sauf décision écrite du responsable du projet.
- **Recommandation** : le bon choix par défaut ; on s'en écarte en sachant pourquoi, et on le note.
- **Pourquoi** : la raison, ou l'incident qui l'a motivée. Une règle dont on ne connaît plus la raison
  finit contournée.
- **Contrôle** : comment vérifier que la règle est tenue. Une règle sans moyen de contrôle est un vœu.
- Les identifiants du type `SEC-24` ou `TEST-12` servent à citer une règle dans une revue, un commit ou un
  ticket.
- Les listes à cocher se trouvent dans `10-checklists.md`.

## Ce que ce recueil n'est pas

- Pas une spécification produit : il ne dit pas **quoi** construire.
- Pas un cours : il suppose un lecteur qui connaît son métier et veut des garde-fous.
- Pas figé : quand un incident révèle une règle manquante, on l'ajoute avec son « Pourquoi ».

## Faire vivre le recueil

- Une règle ajoutée porte son identifiant, sa raison et, si possible, son contrôle.
- Une règle qui ne sert plus se supprime : mieux vaut dix règles tenues que cinquante ignorées.
- Une règle propre à un projet ne vient pas ici ; elle va dans le fichier d'instructions de ce projet.
