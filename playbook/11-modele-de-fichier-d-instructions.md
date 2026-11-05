# 11. Modèle de fichier d'instructions de projet

Le fichier d'instructions (`CLAUDE.md`, `AGENTS.md`, `CONTRIBUTING.md`, selon l'outil) est lu à **chaque
session** par l'assistant ou l'équipe. Il fixe les règles à respecter en permanence et porte les décisions
**propres au projet**.

Ce fichier-ci en donne le **squelette**. On le copie à la racine du nouveau projet, on remplace les
`<...>`, on **supprime ce qui ne s'applique pas**, on cite les règles du recueil par leur identifiant plutôt que de
les recopier.

## Comment le remplir

1. Garder **court** : un fichier de cinq cents lignes n'est plus lu. Renvoyer vers `playbook/` pour les règles
   générales, ne mettre ici que ce qui est **spécifique**.
2. Chaque **décision** porte sa date et sa raison.
3. Chaque règle est **vérifiable** (un test, une commande, un contrôle de revue).
4. Le mettre à jour **dans le même commit** que le comportement qu'il décrit.

---

## Squelette à copier

```markdown
# Instructions de développement : <nom du projet>

Lu à chaque session. La spécification fonctionnelle est dans `<chemin>` : la lire avant toute implémentation.
Les règles générales sont dans `playbook/` ; ce fichier ne porte que ce qui est propre à ce projet.

## 1. Consignes du responsable

- Responsable du projet : <rôle>. Il tranche tout choix de produit.
- Langue des échanges : <langue>. Langue du code : <langue>. Langue des textes affichés : <langues>.
- Suivre la logique et signaler ce qui ne colle pas (`playbook/01`, PRI-02).
- <Autres consignes datées, avec leur raison.>

## 2. Pile imposée

| Domaine | Choix | Décidé le | Pourquoi |
| --- | --- | --- | --- |
| Langage et framework | <...> | <date> | <...> |
| Interface | <...> | | |
| Base de données (production) | <...> | | |
| Base de données (développement, tests) | <...> | | |
| File, cache | <...> | | |
| Stockage | <...> | | |
| Authentification, 2FA | <...> | | |
| Envois (courriel, messagerie, SMS) | <...> | | |
| Exports, PDF | <...> | | |

Règle pour ajouter une dépendance : `playbook/01` PRI-09 (comparer, présenter, attendre l'accord).

## 3. Composants et conventions propres au projet

- Bibliothèque d'interface : <...>. Icônes : <...>. Tableaux : <...>. Formulaires : <...>. Graphiques : <...>.
  Dates : <...>.
- Structure du code : <dossiers, rôles>.
- Formatage : <outil>. Analyse statique : <outil et niveau>.
- Règle de style des textes : <par exemple aucun emoji ni tiret cadratin>.

## 4. Méthode de test

- Le test avant le code (`playbook/03`).
- **Politique de lancement** : <qui lance, quand, avec quel accord ; lots ; créneaux>.
- Commandes : <test ciblé, suite complète, interface, bout en bout>.
- Pièges d'exécution connus : <liste>.

## 5. Base de données

- Moteur(s) et leurs particularités : <...> (`playbook/05`).
- **Ne pas modifier la configuration de base sans demande explicite.**
- Migrations : additives et portables.
- <Modèle d'isolation entre clients et ses règles.>

## 6. Sécurité : exigences non négociables

- Renvoyer vers `playbook/04` ; ne lister ici que les **écarts** et les décisions du projet.
- <Points critiques propres au produit : compte de paiement, jetons signés, etc.>
- Grille de test : `<chemin>`.

## 7. Limitation de débit

| Point d'entrée | Limite indicative | Clé |
| --- | --- | --- |
| Connexion | <...> | <...> |
| <...> | <...> | <...> |

Règles de réglage : `playbook/04` SEC-70 à SEC-77.

## 8. Interface et langues

- Principes : `playbook/06`.
- Groupes de textes : <liste des fichiers de langue>.
- Langue par défaut : <...>. Résolution : <ordre>.
- Vocabulaire à l'écran (ce qui s'appelle autrement qu'en code) : <glossaire>.

## 9. Livraison et exploitation

- Procédure : `<chemin du document de livraison>` et scripts `<chemin>`.
- Serveur : <description sans secret>. Accès : <comment>.
- Sauvegardes : <commande, destination, rétention>.
- Surveillance : <écran de santé, alertes>.

## 10. Confidentialité

- Textes légaux : `<chemin>`, version : `<constante>`. Durées annoncées accrochées aux constantes du code.
- Identité de l'éditeur : configuration. Relecture juridique : <statut>.

## 11. Services externes

Inventaire : `<chemin>`. Règles : `playbook/09`.

## 12. Décisions datées

| Date | Décision | Raison | Portée |
| --- | --- | --- | --- |
| <date> | <...> | <...> | <...> |

## 13. Ce qui ne se commite jamais

- Secrets, fichiers d'environnement, dumps de base, données réelles.
- <Fichiers de résultats de campagne, brouillons : liste.>

## 14. Ordre de construction

1. <...>
```

---

## Conseils d'usage

- **Un fichier d'instructions par projet**, rangé à la racine. Un fichier général (`playbook/`) à côté, copié tel
  quel, ne se modifie pas projet par projet.
- Quand une règle du recueil **ne convient pas** à un projet, on l'écrit dans la section « Décisions datées »
  avec l'écart et la raison : l'écart est alors une décision, pas un oubli.
- Revoir le fichier **à chaque fin de chantier** : retirer ce qui est devenu faux, ajouter ce que l'expérience a
  appris (un piège rencontré, une règle de révision).
- Garder dans un second fichier, hors de celui-ci, la **spécification fonctionnelle** : ce fichier dit **comment
  travailler**, la spécification dit **quoi construire**.
