# 01. Principes et méthode

Les principes qui gouvernent tout le reste. Si un cas n'est prévu nulle part ailleurs, ce fichier tranche.

## 1. Les dix principes

| Id | Principe | En une phrase |
| --- | --- | --- |
| PRI-01 | Le responsable décide | L'assistant ou le développeur propose, éclaire, exécute ; le choix de produit revient au responsable. |
| PRI-02 | Signaler ce qui ne colle pas | Une demande qui heurte une décision ou le code existant se discute avant d'être exécutée. |
| PRI-03 | Respecter l'existant | On lit les conventions du projet et on s'y conforme avant d'ajouter quoi que ce soit. |
| PRI-04 | Le test avant le code | Un comportement vérifiable commence par un test qui échoue. |
| PRI-05 | Aucune régression | Une évolution ne retire ni n'affaiblit un comportement ou un contrôle déjà livré sans décision écrite. |
| PRI-06 | Une chose à la fois | Une livraison, un sujet ; jamais une mise à jour d'infrastructure dans la livraison d'une fonctionnalité. |
| PRI-07 | Écrire ce qui est décidé | Une décision non écrite se re-débat ; elle est datée, motivée, rangée au même endroit. |
| PRI-08 | Dire ce qui est vérifié | Un résultat non rejoué n'est pas une preuve ; on le dit tel quel. |
| PRI-09 | Réutiliser plutôt que réécrire | Une brique éprouvée de l'écosystème bat une brique maison. |
| PRI-10 | Confirmer l'irréversible | Une action qui ne s'annule pas demande une confirmation qui nomme sa conséquence. |

## 2. Le responsable décide (PRI-01)

Le responsable du projet tranche ce qui relève du produit : périmètre, règle métier, compromis entre confort et
sécurité, coût. Les autres rôles apportent l'analyse.

- Face à un choix, présenter **les options, ce que chacune implique, et une recommandation motivée**. Pas
  d'exposé exhaustif de ce qu'on n'envisage pas.
- Un choix ne se fait **jamais en silence**. Si l'on a tranché par défaut parce que l'information manquait, on le
  dit dans le compte rendu.
- Une décision prise n'est pas remise en cause à chaque conversation : on la cite (date, raison) et on s'y tient
  jusqu'à ce que le responsable la change.

## 3. Signaler ce qui ne colle pas (PRI-02)

Avant d'exécuter une demande, la confronter à trois choses : les décisions déjà prises, la spécification, et ce
que le code fait réellement.

- **Demande large** (« partout », « tous les autres ») : la décomposer d'abord en cas concrets, signaler ceux qui
  heurtent une décision ou qui n'ont pas le même sens, attendre l'arbitrage sur ceux-là avant de toucher au code.
- **Contradiction** entre deux documents, ou entre un document et le code : ne pas la corriger de sa propre
  initiative. Citer le passage, expliquer en quoi il ne tient pas, proposer une solution avec la raison de ce
  choix plutôt qu'un autre, attendre la validation.
- **Demande qui affaiblit un contrôle** (sécurité, intégrité) : le dire explicitement, avec la conséquence, avant
  tout.
- Se **contredire soi-même** d'un message à l'autre est une faute : si une analyse change, on dit qu'elle change
  et pourquoi.

**Pourquoi.** Une correction « évidente » faite seul tranche à la place du responsable, et la divergence qu'on
voulait éviter devient invisible.

## 4. Respecter l'existant (PRI-03)

- Lire d'abord le code voisin : nommage, structure, gestion d'erreurs, densité de commentaires, usage des
  bibliothèques. S'y conformer, même si l'on aurait écrit autrement.
- Ne pas introduire un second style, une seconde façon de faire la même chose, ni une seconde bibliothèque pour le
  même besoin.
- Si une bonne pratique générale heurte une règle du projet, **la règle du projet l'emporte**, et la raison du
  compromis s'écrit en commentaire à l'endroit concerné.
- Ne rien renommer ni déplacer « au passage » : un changement de forme se fait dans son propre commit.

## 5. Le test avant le code (PRI-04)

Détail dans `03-tests-et-qualite.md`. Le cycle : test qui échoue, code minimal qui le fait passer, remaniement.
Une correction de bogue commence par un test qui **reproduit** le bogue.

## 6. Aucune régression (PRI-05)

Surtout une fois le produit en ligne, avec des utilisateurs réels.

- Une évolution ne change ni ne retire un comportement livré sans décision explicite.
- Un contrôle de sécurité ne s'affaiblit jamais pour faciliter un ajout.
- **Ce qui est déjà distribué ne bouge pas** : adresses publiques, jetons, documents émis, délais promis. Les
  changer casserait ce que des tiers ont en main.
- Les migrations **ajoutent** ; elles ne renomment ni ne suppriment une structure existante sans accord.
- Avant de retirer une fonction : qui l'utilise, qu'est-ce qui en dépend, que devient ce qui a déjà été produit
  avec elle.

## 7. Une chose à la fois (PRI-06)

- Un commit = un sujet lisible. Une livraison = un sujet. Les corrections de forme, les évolutions et les
  réparations ne se mélangent pas.
- Pas de mise à jour de système, de moteur de base de données, de conteneurisation ou de dépendance majeure dans
  la même livraison qu'une fonctionnalité : en cas de panne, on ne saurait pas laquelle des deux accuser.
- Une rotation de secrets ne se fait pas dans la même livraison qu'un changement de code.

## 8. Écrire ce qui est décidé (PRI-07)

Trois niveaux de documents, chacun à sa place :

| Niveau | Contenu | Stabilité |
| --- | --- | --- |
| Spécification | Ce que fait le produit, règles métier, écrans | Change par décision du responsable |
| Instructions du projet | Règles de travail, pile, conventions, décisions techniques datées | Lu à chaque session |
| Journal | Ce qui a été fait, dans l'ordre, commande par commande | Ne se réécrit pas, on ajoute |

- Une décision s'écrit **avec sa date, sa raison et sa portée** : « décision du 2026-10-04 : X, parce que Y ».
- Un document décrit **ce que le code fait réellement**. Quand le comportement change, le document change **dans
  le même commit** (textes légaux et leur version compris).
- Une information ne vit qu'à un endroit ; ailleurs, on renvoie vers elle.

## 9. Dire ce qui est vérifié (PRI-08)

- Un compte rendu distingue : **fait et vérifié**, **fait mais non vérifié**, **non fait**.
- « Les tests passent » n'est vrai que de ceux qui ont tourné, sur le code livré. Les autres sont **supposés
  faux** jusqu'à preuve du contraire.
- Un échec non expliqué n'est pas rangé en « instable » sans enquête : on note ce qui a été tenté, ce qui a été
  écarté, ce qui reste à surveiller.
- Ne jamais afficher un chiffre ou un résultat reconstitué de mémoire : un résultat qui n'a pas été conservé est
  signalé comme tel.

## 10. Réutiliser plutôt que réécrire (PRI-09)

- Chaque brique transverse (authentification, permissions, journal d'audit, files, sauvegardes, exports, tableaux
  de données, formulaires, dates, icônes) a une solution mûre. On la cherche avant d'écrire la sienne.
- Une brique maison ne se justifie que pour ce qui est propre au métier.
- **Ajouter une dépendance** : comparer les alternatives sérieuses du moment (maintenance récente, compatibilité
  avec les versions du projet, adoption, documentation, adéquation précise au besoin), présenter le choix et les
  alternatives écartées, **attendre l'accord** avant d'installer.
- Utiliser un outil **comme son écosystème attend qu'on l'utilise** : points d'extension prévus, configuration
  publiée. Détourner son comportement interne est une dette, même quand ça marche.

## 11. Confirmer l'irréversible (PRI-10)

- Suppression, purge, rejet, envoi en masse, validation d'un paiement, clôture : confirmation avec la conséquence
  exacte et le nom de ce qu'on touche.
- Le focus s'ouvre sur « Annuler », jamais sur l'action.
- Préférer **désactiver** à supprimer quand l'historique en dépend ; une suppression passe par une corbeille avec
  délai quand c'est possible (voir `05-donnees-concurrence-multi-organisation.md`).
- Côté exécution (scripts, administration) : montrer ce qui va se passer et demander confirmation avant d'agir sur
  quelque chose qu'on ne rattrape pas.

## 12. Ordre de construction

Recommandation générale, à adapter :

1. **Fondations** : identités, accès, rôles, double authentification, isolation entre clients.
2. **Le noyau critique tôt**, avec ses tests de concurrence : c'est ce qui coûte cher à reprendre.
3. **Parcours de bout en bout par tranches verticales** (de l'écran à la base) plutôt que couche par couche.
4. **Effets de bord** : messages, rappels, exports, rapports.
5. **Exploitation** : surveillance, sauvegardes, console d'administration.

Variante assumée : construire **tous les écrans d'abord**, avec des données d'exemple, pour juger l'ensemble avant
d'investir côté serveur. Dans ce cas :

- les données d'exemple **se voient** (bandeau « données d'exemple », indicateur dans les props) ;
- chaque point provisoire est marqué (`PROVISOIRE`) et sera **remplacé**, pas complété ;
- aucune logique métier écrite à la hâte « pour faire illusion » ;
- chaque écran reste **atteignable** par un lien dès sa création (voir `06-interface-accessibilite-langues.md`).

## 13. Quand on ne sait pas

- Le dire. Une hypothèse présentée comme un fait coûte plus cher qu'une question.
- Vérifier avant de recommander : une version, un prix, une conformité, la fiabilité d'un prestataire (voir
  `09-services-externes.md`). Ne pas citer de mémoire ce qui change.
- Si deux lectures d'une demande sont plausibles et mènent à des actions différentes, poser la question. Si
  elles mènent à la même action, ne pas la poser.
