# Besoin fonctionnel

Ce document décrit **ce que l'application doit faire**. Le modèle de données et les formules sont dans
[`README-DONNEES.md`](README-DONNEES.md) ; l'installation est dans [`README.md`](README.md).

Légende : une mention **(à confirmer)** signale une hypothèse proposée, non encore validée par le responsable.
La liste complète est au [chapitre 13](#13-points-à-confirmer).

## 1. Contexte et objectif

Le responsable achète des matériaux dans des boutiques, compose des **kits de construction** et les revend à des
**clients** (des particuliers ou petites entreprises, appelés « TPE » dans le classeur existant). Les clients sont
répartis sur 5 sous-préfectures. Aujourd'hui, 56 clients sont prévus (jusqu'à 58), soit 168 kits. Le suivi se fait
dans un classeur Excel.

L'application doit :

1. remplacer le classeur par un outil fiable, sans erreur de formule ;
2. gérer les clients, les produits, les kits, les commandes, les achats et les livraisons ;
3. produire les **documents** commerciaux (proforma, bon de commande, bon de livraison, reçu, facture, bordereau
   mensuel) en PDF ;
4. **tracer** chaque étape du processus ;
5. calculer le **bénéfice réel**, transport et imprévus compris ;
6. conserver les **documents administratifs** (registre de commerce, FNE, etc.) ;
7. rester **entièrement paramétrable** : rien de ce qui peut changer n'est écrit en dur.

## 2. Principes directeurs

| Principe | Conséquence |
| --- | --- |
| **Tout est paramétrable** | Montant de l'avance, rythme (hebdomadaire par défaut), kits par client, plafond de clients, zones, modes de paiement, types de frais, préfixes de numérotation : réglables sans développement. |
| **Les prix changent** | Un produit garde l'**historique** de ses prix. Une commande **fige** les prix du moment : modifier un prix ne change jamais une commande passée. |
| **Un produit s'enregistre librement** | Il n'existe aucun produit « réservé » ; le produit 13 du classeur (à compléter) se crée comme les autres. |
| **Les clients n'ont pas d'accès** | Le personnel les enregistre au moyen d'un **formulaire client**. |
| **Traçabilité** | Qui a fait quoi, quand, sur quel objet, est conservé et consultable. |
| **Deux langues, deux thèmes** | Français et anglais, thème clair, sombre ou système, au choix de chaque utilisateur (`playbook/06`, I18N-13 et UI-67). |
| **Monnaie** | FCFA, entiers, sans décimale. |

## 3. Acteurs et droits

Les clients ne sont **pas** des utilisateurs. Trois profils d'utilisateurs sont proposés **(à confirmer)** :

| Profil | Rôle | Droits principaux |
| --- | --- | --- |
| **Responsable** | Le propriétaire | Tout : paramètres, utilisateurs, prix, achats, bénéfices, suppression, documents administratifs |
| **Employé** | Personnel de bureau | Clients, commandes, achats, documents, encaissements, boutiques, produits et prix ; pas les paramètres, les sous-préfectures ni la gestion des utilisateurs ; ne voit pas les bénéfices par défaut |
| **Agent de terrain** | Affecté à une ou plusieurs sous-préfectures | Voit **uniquement** les clients et livraisons de ses sous-préfectures ; enregistre un client, un encaissement, une livraison et une signature ; ne voit ni les prix d'achat ni les bénéfices |

Règles :

- Ces trois profils sont des **profils système** : ils ne se modifient, ne se renomment et ne se suppriment pas. Pour un profil sur mesure, le responsable en **crée un nouveau** (de zéro ou « à partir de » un existant) dans *Profils*, en cochant les droits parmi une liste fermée (gérer les clients, les commandes, les achats, les frais, le catalogue, voir les bénéfices) et en le limitant ou non aux sous-préfectures affectées. Un profil sur mesure se modifie ; il ne se supprime que si plus personne ne l'utilise. La gestion des utilisateurs, des profils et des paramètres reste toujours réservée au responsable. Les droits se règlent à deux niveaux : le **menu** (ce qui s'ouvre : clients, commandes, achats, frais, caisse, catalogue, bénéfices) et les **actions** de chaque menu (par exemple, pour les commandes : créer, encaisser, enregistrer les livraisons, faire avancer, valider les étapes critiques, annuler, générer les documents…). Le menu **Clients** a ses actions : créer, modifier, changer le statut, exporter ; un profil limité aux zones ne voit que les clients de ses zones, et les commandes restent visibles sur la même règle sans ce menu. Cocher un menu donne toutes ses actions, que l'on décoche une à une ; le tableau « Droits par profil » permet de régler chaque case sur place. Le serveur refuse toute action non accordée, même si le bouton était visible.
- Les comptes sont **créés par le responsable**. L'inscription publique du kit de démarrage est désactivée.
- La double authentification et les clés d'accès déjà présentes sont conservées.
- Une permission se vérifie **côté serveur** à chaque action, jamais seulement en masquant un bouton.

## 4. Processus métier

### 4.1 Vue d'ensemble

```
Client enregistré ──> Proforma ──> Avance encaissée ──> Bon de commande (boutiques)
                                                              │
                                  Livraison + relicat <── Achat et réception des produits
                                          │
                       Bon de livraison signé ──> Facture ──> Clôture et calcul du bénéfice réel
                                                     │
                                          Bordereau mensuel (liste des factures de la période)
```

### 4.2 Cycle hebdomadaire

La **semaine** est l'unité de travail : **chaque semaine, chaque client verse son avance et reçoit ses kits** (3 par
défaut). Le rythme (hebdomadaire par défaut) et le nombre de kits sont des paramètres, réglables par client.

1. **Enregistrer le client** par le formulaire client (sous-préfecture, identité, contact, documents).
2. **Définir sa livraison automatique** (voir 5.5) : kit, nombre de kits par livraison, avance par livraison, rythme, date de
   début, date de fin ou nombre de cycles.
3. **Créer la commande de la semaine** : elle est **générée automatiquement** à partir de la livraison automatique
   (ou créée à la main pour un cas particulier). Les prix sont figés à ce moment.
3. **Émettre la proforma** (devis) au client, avec le prix de vente, l'avance demandée et le relicat.
4. **Encaisser l'avance** (200 000 FCFA par défaut) **avant** la livraison : le montant, la date, le mode de
   paiement et l'agent sont enregistrés et un **reçu** est émis. Sans avance, la commande ne passe pas à l'achat.
5. **Passer commande aux boutiques** : un bon de commande par boutique regroupe les produits de toutes les
   commandes de la semaine.
6. **Réceptionner** les produits achetés et enregistrer ce qui a été payé aux boutiques.
7. **Livrer** : l'agent remet les kits, encaisse le **relicat**, le client **signe** le bon de livraison.
8. **Émettre la facture** de la livraison (voir chapitre 6 pour le lien avec la facture normalisée électronique).
9. **Clôturer** : le bénéfice réel de la commande est calculé avec les frais de logistique.
10. **En fin de période** (mensuelle par défaut) : éditer le **bordereau**, qui liste les factures de la période.

### 4.3 États d'une commande

| État | Signification | Passage suivant |
| --- | --- | --- |
| Brouillon | Saisie en cours | Proforma émise |
| Proforma émise | Devis remis au client | Avance reçue |
| Avance reçue | Avance encaissée (totale ou partielle selon le paramètre) | Commandée |
| Commandée | Bon de commande envoyé aux boutiques | Reçue |
| Reçue | Produits disponibles | Livrée |
| Livrée | Kits remis, bon de livraison signé | Soldée |
| Soldée | Relicat encaissé | Clôturée |
| Clôturée | Frais saisis, bénéfice réel calculé | (fin) |
| Annulée | Depuis tout état avant « Livrée » ; l'avance est remboursée ou conservée selon la décision | (fin) |

Chaque changement d'état est enregistré (qui, quand, motif). Un retour en arrière est un événement tracé, jamais
une modification silencieuse.

## 5. Modules

### 5.0 Données de base chargées au déploiement

Après les migrations, `php artisan db:seed --force` charge, sans rien dupliquer si on le relance : les boutiques,
produits et prix d'achat ; le kit de départ ; les modes de paiement ; les types de frais et de documents
administratifs ; puis les **sous-préfectures et les TPE** du classeur `database/seeders/data/Liste_TPE_Hambol.xlsx`
(57 TPE, un responsable chacune ; on ne travaille qu'avec les responsables : pas d'assistants). Le classeur peut être lu ailleurs avec la variable
`SEED_TPE_FILE` (utile s'il ne doit pas rester dans le dépôt : il contient des noms et téléphones). Le propriétaire
est créé à part (`php artisan app:create-owner`). Chaque élément chargé est écrit au journal d'audit.
Chaque TPE reçoit un téléphone qui lui est propre : quand le classeur n'en donne pas (« Ets REHOBOTH ») ou répète
celui d'une autre TPE, l'importation met un **numéro provisoire** `00 00 00 00 NN` et garde le numéro d'origine dans
les observations. Après chargement, à compléter à la main : ces numéros provisoires et le numéro commercial de chaque TPE.

**Données de démonstration.** `php artisan app:seed-demo` charge des données **fictives** pour montrer ou essayer l'application :
8 TPE nommées « [DÉMO] … » avec des commandes à chaque étape de la vente, leurs avances, des livraisons et des
mouvements de caisse. La commande est **refusée en production**, ne duplique rien si on la relance, et charge d'abord les
données de base si elles manquent. Ces enregistrements ne peuvent pas être supprimés ensuite (paiements et caisse
immuables) : à utiliser dans une base réservée aux démonstrations.

### 5.1 Clients

- **Formulaire client** : **numéro commercial** (registre de commerce, obligatoire et unique), nom ou raison
  sociale, **téléphone (obligatoire)**, sous-préfecture, localité, adresse de
  livraison, personne à contacter, pièces d'identité ou d'entreprise, observations.
- Rattachement obligatoire à **une sous-préfecture** (5 aujourd'hui, extensible).
- **Plafond de clients** facultatif : par défaut **aucune limite** (valeur 0). Le responsable peut fixer un nombre ; au
  dépassement, l'application avertit et laisse le responsable décider (jamais de blocage).
- Statuts : en attente, actif, suspendu, terminé.
- **Client TPE (petite entreprise)** : un client est représenté par son **responsable** (nom en « personne à contacter », métier et téléphone affichés sur sa fiche) ; il n'y a pas de gestion d'équipe ni d'assistants. La commande `php artisan clients:import-tpe <fichier.xlsx>` enregistre les TPE
  d'un classeur comme clients (une sous-préfecture par feuille ou colonne, village en localité, responsable en
  personne à contacter, département et second numéro en observations) ; sans `--apply` elle ne fait que simuler,
  et relancée elle ignore les TPE déjà enregistrées.
- Fiche client : commandes, versements, livraisons, solde, documents joints, historique.
- Recherche, filtres (sous-préfecture, statut, semaine), tri et pagination côté serveur.
- Les données personnelles sont limitées au nécessaire (`playbook/08`).

### 5.2 Boutiques (fournisseurs)

- Nom, contact, adresse, conditions de paiement, documents joints (registre de commerce, FNE, etc.).
- Nombre illimité (2 aujourd'hui).
- Fiche boutique : produits fournis, achats, sommes versées, **solde dû**.

### 5.3 Produits et prix

- Un produit : désignation, unité, boutique habituelle, statut actif ou archivé.
- **Historique des prix** : prix d'achat et prix de vente unitaire (facultatif, car un produit vendu seulement en kit
  n'a que le prix de sa ligne de kit), avec date d'effet. Un prix actuel se déduit de l'historique ; l'ancien reste
  consultable et n'est jamais modifié : un changement est une nouvelle ligne.
- Un même produit peut avoir un prix d'achat différent selon la boutique.
- Un produit archivé n'apparaît plus dans les nouvelles compositions mais reste dans les commandes passées.
- Import initial des 12 produits connus du classeur (le 13e est à créer par l'utilisateur).

### 5.4 Kits

- Un **kit** est une **composition** : une liste de produits avec leur quantité par kit.
- Une composition a une **version** : modifier un kit crée une nouvelle version, les commandes en cours gardent
  l'ancienne.
- L'application calcule le prix d'achat, le prix de vente et le bénéfice théorique d'un kit à partir des prix en
  vigueur.
- Le prix de vente peut être **calculé** (somme des produits) ou **fixé** pour le kit : choix du responsable
  **(à confirmer)**. Le classeur fixe le prix de vente de chaque ligne.
- Plusieurs types de kits sont possibles, un seul est utilisé aujourd'hui.

### 5.5 Commandes

- Une commande lie un client, un kit (version), une quantité, une semaine de livraison, des prix figés.
- **Livraison automatique** : par client, elle décrit ce qu'il reçoit à chaque livraison : kit, **nombre de kits par
  livraison** (3 par défaut), **avance par livraison** (200 000 F par défaut), rythme (hebdomadaire par défaut), date de
  début, date de fin ou nombre de livraisons, état (active, en pause, terminée). Les valeurs par défaut viennent des
  paramètres ; chaque client peut avoir les siennes.
- À chaque cycle, l'application **génère la commande** correspondante avec les prix du moment. Une commande
  générée peut être modifiée, reportée ou sautée (semaine sans livraison) avec le droit adéquat, et c'est tracé.
- Le formulaire de la fiche client dit en une phrase ce qu'il va faire (« Chaque semaine, à partir du 4 nov. 2026, ce client
  recevra 3 kits « Kit TPE », avec une avance de 200 000 F par livraison, sans date de fin »). L'**arrêt** est un seul choix :
  *jamais* (jusqu'à ce que le bureau l'arrête), *à une date* ou *après un nombre fixé de livraisons*. Les erreurs s'affichent
  sous le champ concerné, en français ou en anglais.
- Modifier la livraison automatique ne change que les **commandes à venir**.
- **Création en lot par sous-préfecture** (écran « Nouvelle commande », onglet **Toute une sous-préfecture**) : on choisit la sous-préfecture (le nombre de
  TPE actifs est indiqué), le kit, le nombre de kits, la semaine de livraison et l'avance, comme pour une commande seule. L'écran liste
  les **TPE actifs** de la sous-préfecture, tous cochés ; ceux qui ont déjà une commande cette semaine-là sont affichés, grisés, non cochables.
  Une case **« Émettre aussi les proformas »** (cochée par défaut) fait passer chaque commande à « Proforma émis » avec son document.
  Une phrase résume ce qui va se passer, la confirmation annonce le nombre, et le message final ne nomme que ce qui n'est pas zéro
  (« 4 commandes créées · 4 proformas émises »). Chaque commande est créée avec les prix du jour figés, comme une commande seule, et tracée
  (une ligne d'audit par commande, avec la sous-préfecture et la taille du groupe (la localité, ou village, reste un simple champ de la fiche client, non utilisé pour grouper)). Relancer ne crée rien de plus (une commande non annulée
  de la semaine suffit à ignorer le TPE) ; une erreur sur un TPE n'arrête pas les autres ; 60 TPE au plus à la fois. Seuls les clients
  actifs que l'utilisateur a le droit de voir sont concernés, et le serveur revérifie chacun. Le panneau « Proformas par sous-préfecture » de la
  page Commandes reste utile pour émettre plus tard les proformas de brouillons déjà créés.
- **Proformas par sous-préfecture** (page Commandes, panneau replié) : en un clic, émet la proforma de toutes les commandes
  en attente (brouillon) des clients d'une sous-préfecture et les passe à « Proforma émis », avec confirmation qui annonce
  le nombre. Une commande en échec n'arrête pas les autres ; relancer n'émet jamais une seconde proforma.
- **Encaisser des avances par sous-préfecture** (même page, panneau replié) : on choisit la sous-préfecture ; le **mode de paiement**, un
  **montant pour tous** (facultatif : vide, chaque ligne propose son reste à encaisser), la **date** et la personne qui **encaisse** sont des
  valeurs proposées à toutes les lignes, et chaque ligne peut s'en écarter. Toutes les commandes qui attendent leur avance sont proposées,
  cochées, une carte par commande : **montant**, **date**, **encaissé par**, **payé par** (le client par défaut), **téléphone du payeur**,
  **mode de paiement** et, si ce mode l'exige, la **référence de la transaction** (chaque ligne a donc son propre mode, et la référence est
  demandée ligne par ligne). Un clic enregistre tous les paiements, chacun avec son numéro de reçu et sa ligne d'audit, comme le ferait le
  formulaire de la commande ; une commande dont l'avance est couverte passe d'elle-même à « Avance reçue ». Un paiement en échec (montant
  au-dessus du prix) n'arrête pas les autres et est compté ; renvoyer le même lot ne prend jamais l'argent deux fois ; 60 paiements au plus
  à la fois. Un montant ne peut pas dépasser le **reste à encaisser** de l'avance de sa ligne : le champ est marqué en rouge avec le maximum
  autorisé, un message le rappelle et le bouton reste inactif tant qu'un montant dépasse ; le serveur refuse aussi ce cas (un montant plus
  élevé s'enregistre depuis la page de la commande). Réservé au bureau.
Les **factures restent générées individuellement**.
- Total, avance due, avances reçues, relicat dû : toujours visibles.

### 5.6 Paiements et encaissements

- **Avance** : un versement par semaine et par commande, avant la livraison. Le montant, la périodicité et le
  moment sont paramétrables.
- **Relicat** : encaissé à la livraison, égal au prix de vente moins les avances reçues.
- **Modes de paiement** : liste paramétrable (espèces, mobile money, virement, chèque, par exemple). Référence de
  transaction obligatoire pour un mode non espèces **(à confirmer)**.
- Un **reçu numéroté** est émis pour chaque encaissement (avance ou relicat).
- Trois personnes sont gardées sur chaque paiement : **qui a payé** (le client par défaut, ou un proche, un
  représentant, avec son téléphone), **qui a encaissé** (la personne de l'équipe qui a reçu l'argent, par défaut
  celle qui est connectée ; l'agent de terrain encaisse toujours pour lui-même) et **qui l'a enregistré** dans
  l'application (toujours le compte connecté). Les trois figurent dans l'onglet Paiements, et sur le reçu.
- Un versement ne se supprime pas : une erreur se corrige par une **écriture inverse** tracée.
- Retard : tableau des avances en retard par client et par sous-préfecture.

### 5.6 bis Caisse (mouvements divers)

- Menu **Caisse** (droit **« gérer la caisse »**, distinct de « gérer les frais de logistique » : on peut donner l'un sans l'autre ; à la mise à jour, tout profil qui avait les frais a reçu la caisse, rien ne change pour personne). Elle enregistre les mouvements d'argent qui ne
  sont ni un versement de client (5.6), ni un règlement de boutique (5.7), ni un frais de logistique (5.9) :
  **entrées** (apport du responsable, remboursement reçu…) et **sorties** (retrait, petite dépense…).
- Un mouvement porte : sens, montant en F CFA entier, date (jamais dans le futur), **motif**, personne concernée,
  référence de la pièce justificative, observations, **qui l'a saisi** et la date ; numéro `CAI-0001`.
- Il est **figé** : pas de modification ni de suppression. Une erreur s'**annule avec un motif** (confirmation
  demandée), le mouvement reste dans les livres, barré, et ne compte plus dans le solde.
- La page affiche le **solde d'ouverture** (avant la période choisie), les **entrées**, les **sorties** et le
  **solde de caisse**, avec filtres par période et par sens. Le formulaire est au-dessus de la liste et repliable.
- Chaque enregistrement et chaque annulation sont écrits au journal d'audit.
- **Clôture de caisse** : on compte l'argent réellement présent, on saisit la date et le montant compté ; l'application affiche
  ce que les livres attendent et enregistre la **clôture** (référence `CLO-0001`, attendu, compté, écart, qui, quand). Un
  **écart** (manquant ou excédent) est écrit en **un mouvement** daté du jour de la clôture (sortie pour un manquant, entrée pour
  un excédent), si bien que les livres rejoignent le comptage. La clôture est **figée** : ni modifiée ni supprimée.
- Après une clôture, **plus aucun mouvement daté de ce jour ou d'avant ne peut être enregistré ni annulé** (y compris un frais payé
  depuis la caisse, ou l'écart de clôture lui-même) ; une erreur se corrige par un mouvement daté après la clôture. Une date de
  clôture est postérieure à la précédente et jamais dans le futur.
- **Frais anciens** : les frais saisis avant le lien avec la caisse n'y figurent pas. Un panneau de la page Caisse (pour qui a aussi
  le droit sur les frais) les liste ; on **coche** ceux qui ont réellement été payés en espèces (rien n'est coché par défaut) : chacun
  crée sa sortie de caisse à la date du frais et reçoit la marque « payé depuis la caisse », une seule fois et sans retour. Les frais
  annulés, déjà rattachés ou datés d'une période clôturée sont ignorés. Une trace par frais est écrite au journal.
- Marquer un **nouveau** frais « payé depuis la caisse » exige aussi le droit sur la caisse.

### 5.7 Achats auprès des boutiques

- **Bon de commande** par boutique, calculé à partir des commandes de la semaine (quantités totales par produit).
- Enregistrement de la réception (complète ou partielle) et des écarts.
- **Paiements aux boutiques** : les avances clients couvrent une part des achats. La part affectée à chaque boutique
  se calcule au prorata du prix d'achat (méthode du classeur), et le **solde dû** reste visible.
- Le prix d'achat réel payé peut différer du prix théorique : c'est le réel qui sert au bénéfice réel.

### 5.8 Livraisons

- Une livraison par commande et par semaine, avec date prévue, date réelle, agent, état.
- Sur mobile, l'agent : voit sa liste du jour, confirme la remise, encaisse le relicat, fait **signer le client à
  l'écran** et peut photographier la remise **(à confirmer)**.
- Livraison partielle autorisée (kits restants reportés à une autre semaine).
- Le **bon de livraison** signé est joint automatiquement à la commande.

### 5.9 Logistique : transport et imprévus

- Saisie de **frais** rattachés à une livraison, une semaine, une sous-préfecture ou une boutique : transport,
  manutention, imprévu, autre.
- **Types de frais paramétrables** (nom, libellé dans les deux langues).
- Chaque frais : montant, date, justificatif joint (facultatif), auteur.
- La répartition des frais sur les commandes (au prorata du nombre de kits par défaut) permet le bénéfice réel par
  commande.

### 5.10 Documents administratifs

- Documents rattachables à **l'entreprise**, à une **boutique** ou à un **client**.
- Types paramétrables. Types proposés pour l'entreprise : registre de commerce (RCCM), numéro de compte
  contribuable, identifiant unique d'entreprise attribué à l'immatriculation, autre. Pour un client : identité,
  justificatif de domicile, et le registre de commerce s'il est une entreprise. Pour chaque type, le responsable
  indique s'il est **requis** ou non, et pour quel objet.
- **À propos du « FNE »** : en Côte d'Ivoire, ce sigle désigne la **facture normalisée électronique**, un dispositif
  de la Direction Générale des Impôts, et non un numéro délivré à la création d'une entreprise. Il est traité au
  chapitre 6 (documents). Si le responsable vise un autre numéro, il suffit de créer le type de document
  correspondant : c'est un paramètre.
- Chaque document : type, **numéro** (suffisant : registre de commerce et autres documents se saisissent par leur
  numéro), date d'émission et d'expiration facultatives, observations. Le **fichier joint est facultatif** : un PDF, une image JPEG ou PNG de 4 Mo au plus, vérifié d'après son contenu et non son nom. Une image est réenregistrée (sa position GPS et ses autres métadonnées sont supprimées) ; un PDF contenant un script ou un fichier intégré est refusé, et un PDF est toujours proposé en téléchargement. Chaque ouverture d'un fichier est inscrite au journal.
- **Alertes** avant expiration. Un document requis absent apparaît comme manquant, sans bloquer le travail sauf
  décision contraire.
- Toute information utile sur l'entreprise peut être stockée, même si non requise.
- Accès restreint par profil ; les fichiers ne sont pas publics.
- Mise en œuvre : réservé au propriétaire. Un document se supprime (avec trace dans le journal) mais ne se modifie pas : pour un renouvellement, on enregistre le nouveau document, et seule la plus récente d'un type pour un propriétaire compte dans les alertes. Le délai d'alerte est le paramètre « jours avant expiration ». Les types cochés « imprimer sur nos documents » (RCCM, compte contribuable, identifiant unique par défaut) voient leur numéro ajouté à l'en-tête des PDF.

### 5.11 Tableau de bord et rapports

Remplace et corrige le classeur. Filtres : période, sous-préfecture, kit et boutique. Les trois premiers limitent les
commandes comptées. Le filtre **boutique** ne limite que la **synthèse par boutique** et la dette envers elle : une
commande mélange les produits de plusieurs boutiques, elle n'appartient donc pas à une seule. Il est transmis aux
exports et à la synthèse PDF. Un agent de terrain ne le voit pas.

- **Indicateurs** : clients actifs, commandes par état, kits livrés et restants, avances encaissées, relicats
  encaissés, créances (dues par les clients), dettes (dues aux boutiques).
- **Bénéfice** : théorique (prix de vente moins prix d'achat), **réel** (encaissé moins achats réels moins frais),
  écart entre les deux, par kit, client, semaine, boutique et sous-préfecture.
- **Synthèse par boutique** : prix d'achat, prix de vente, avance affectée, pourcentage, solde après avance.
- **Alertes** : avances en retard, documents qui expirent, commandes bloquées, plafond de clients atteint.
- Exports **CSV et PDF** des listes et rapports.
- Mise en œuvre : le tableau de bord filtre par **période** (mois en cours par défaut, selon la semaine de livraison),
  **sous-préfecture** et **kit**. Les indicateurs sont calculés sur les commandes non annulées de la période, sauf le nombre
  de clients actifs (clients non terminés). Les **créances** sont les commandes livrées pas encore soldées (prix de vente
  moins encaissé) ; les **dettes** sont les marchandises reçues des boutiques moins ce qui leur a été versé. Les alertes ne
  dépendent pas de la période : avances en retard (semaine de livraison atteinte, avance non payée en entier), commandes
  **bloquées** (encore en préparation plus de N jours après leur semaine de livraison, N étant un paramètre, 7 par défaut), plafond de clients atteint, documents
  manquants ou qui expirent (propriétaire seulement).
- Un agent de terrain ne voit que les commandes et clients de ses sous-préfectures, et rien sur les boutiques, les dettes ni
  le bénéfice. Le bénéfice suit la règle `view-profit` (propriétaire, ou employés si le paramètre l'autorise).
- Exports **CSV** (UTF-8, séparateur point-virgule, ouvrable dans Excel) : commandes, clients, paiements, avances en retard,
  frais, synthèse par boutique, bénéfice par semaine, chacun limité à ce que le profil peut voir. Export **PDF** : la
  synthèse du tableau de bord (indicateurs, états, boutiques, bénéfice selon le profil), dans la langue de l'utilisateur.

### 5.11 ter Listes paginées

- Toute liste qui grandit est **paginée côté serveur** (30 lignes par page ; 12 pour les clôtures de caisse) : clients, commandes,
  paiements, **avances en retard**, caisse et ses **clôtures**, frais, livraisons, achats, **produits**, **documents
  administratifs**, documents, journaux. Le navigateur ne reçoit que la page affichée ; les filtres et la page sont dans
  l'adresse. Les listes courtes par nature (boutiques, sous-préfectures, kits, utilisateurs, profils, modes de paiement, types)
  tiennent sur une page.
- Les **totaux** (par exemple « Total encore dû » des avances en retard) portent sur **toute** la liste filtrée, pas sur la page.
- **Recherche** sur les produits (nom ou boutique) et sur les documents administratifs (numéro ou observation) ; les exports
  reprennent la même recherche. Les clôtures de caisse ont leur propre numéro de page, sans toucher à la liste des mouvements.
- **Tri par clic sur l'en-tête** (clients, commandes, paiements, produits) : un clic trie dans l'ordre croissant, le suivant dans
  l'ordre décroissant, sur **toute** la liste (pas seulement la page) ; le choix reste quand on filtre, est dans l'adresse, et
  l'export de la liste suit le même tri. Les autres listes gardent leur ordre naturel (le plus récent d'abord) tant qu'elles n'ont
  pas d'en-têtes triables.

### 5.11 bis Exports des listes

- Chaque liste principale a un menu **Exporter** (Excel, CSV, PDF) : clients, commandes, paiements, caisse, frais de
  logistique, livraisons, achats, produits, kits, journal, journal d'accès, documents, **avances en retard**, **clôtures de
  caisse**, **documents administratifs** (sans le fichier joint, pour le type affiché) et **utilisateurs** (nom, e-mail, profil,
  état : jamais de mot de passe, pour contrôler les accès). Les listes de référence courtes (boutiques, sous-préfectures,
  modes de paiement, types de frais et de documents) n'ont pas d'export : elles tiennent à l'écran.
- Le fichier reprend **les filtres affichés** et suit **les droits de la page** : un agent ne reçoit que les clients et
  commandes de ses sous-préfectures ; les prix d'achat et coûts de kit ne sortent qu'avec le droit « bénéfice ».
- Excel : une feuille, en-têtes en gras figés, montants numériques. CSV : UTF-8, séparateur « ; », formules
  neutralisées. PDF : paysage si le tableau est large, nom de l'entreprise en filigrane, tronqué à 2 000 lignes avec
  mention. Plafond de 20 000 lignes par export.
- Chaque export est écrit au journal d'audit (qui, quelle vue, quel format, combien de lignes, quels filtres).
- Les anciens exports CSV du tableau de bord (`/exports/{rapport}`) restent inchangés.

### 5.12 bis Journal d'accès (navigation)

- Menu **Journal d'accès** (propriétaire seulement) : chaque page ouverte par un utilisateur connecté est écrite avec
  **qui, quelle page, la réponse, l'adresse IP, le navigateur et l'heure** ; les **connexions refusées** (avec l'e-mail
  saisi, jamais le mot de passe) et les **blocages pour trop d'essais** y figurent aussi.
- Filtres par utilisateur, type, adresse IP et période ; export Excel, CSV ou PDF.
- Les valeurs sensibles de l'adresse (mot de passe, jeton, code, clé) sont masquées avant écriture. Les lignes sont
  gardées **365 jours** (variable `SECURITY_ACCESS_LOG_RETENTION_DAYS`), puis supprimées chaque nuit ; la purge est
  écrite au journal d'audit. Le contrôle de production refuse de démarrer si ce journal est coupé.
- Les **connexions, déconnexions, changements de mot de passe, de profil, de double authentification et de clés
  d'accès** sont écrits au **journal d'audit** (qui, quand, adresse, navigateur), jamais avec un secret.
- C'est un traitement de données personnelles (adresse IP, navigateur, pages vues) : le **texte de confidentialité**
  de l'application doit le mentionner, avec cette durée de conservation.

### 5.12 ter Copies mensuelles du journal d'audit

- Le premier jour de chaque mois (04 h), `app:archive-audit-log` écrit une **copie du mois terminé** : un fichier compressé
  (une ligne par entrée, avec son empreinte) et un **manifeste** (nombre de lignes, première et dernière entrée,
  empreinte précédente, empreintes du contenu et du fichier). Une copie n'est **jamais remplacée** : relancée, la
  commande ne fait rien si la copie est identique, et refuse si elle diffère. Chaque copie créée est écrite au journal.
- Chaque nuit (02 h 30), `app:verify-audit-archive` contrôle les copies : fichier intact, chaîne d'empreintes recalculée
  à partir des lignes, accord avec le journal, mois qui s'enchaînent (un mois manquant ou altéré est signalé).
- La page **Journal** (propriétaire) liste les copies et permet de les télécharger ; chaque téléchargement est tracé.
- **Les copies ne restent pas éternellement** : chaque nuit (03 h 30), `app:prune-audit-archive` supprime le fichier des copies
  plus vieilles que la durée de conservation (**36 mois (3 ans)** par défaut, `SECURITY_AUDIT_ARCHIVE_RETENTION_MONTHS`) et, quand le
  disque dépasse le seuil d'occupation (**85 %** par défaut, `SECURITY_AUDIT_ARCHIVE_DISK_MAX_PERCENT`, 0 pour ne pas
  limiter), supprime les plus anciennes d'abord, jamais les **3 plus récentes** (`SECURITY_AUDIT_ARCHIVE_MIN_KEEP`). Le petit
  manifeste d'une copie supprimée reste (les mois continuent de s'enchaîner), la suppression est écrite au journal d'audit et la
  page Journal l'indique. Si le disque reste trop plein alors que plus rien ne peut être supprimé, la commande échoue et
  une alerte critique est écrite.
- Les copies vont par défaut dans un dossier privé du serveur. Pour une vraie protection, pointer
  `SECURITY_AUDIT_ARCHIVE_DISK` vers un stockage où l'application ne peut qu'ajouter (stockage objet versionné).
- **Rien n'est supprimé** de la table : la purge des anciennes lignes viendra dans un second temps, seulement pour les
  mois déjà copiés et vérifiés, avec un point de contrôle pour que la chaîne se vérifie toujours.

### 5.12 Traçabilité (journal)

- Journal en **ajout seul** : utilisateur, date et heure, action, objet, valeurs avant et après.
- Chronologie lisible sur chaque commande, client et boutique.
- Le journal n'est ni modifiable ni supprimable depuis l'application.

## 6. Documents

Chaque document est généré en **PDF** (dompdf), **numéroté** automatiquement, **archivé** (stockage privé, jamais public) et rattaché à son objet. Le reçu garde le numéro de son paiement et le bon de commande sa référence ; les autres ont leur propre suite (`PRO-2026-0001`, `BL-…`, `FAC-…`, `BOR-…`), au préfixe réglable. Un seul document valide existe par objet et par type : on l'annule (motif obligatoire) et on peut le remplacer en une étape, le nouveau pointant vers l'ancien. Sa langue
est choisie à la génération (`playbook/06`, I18N-14). Il porte l'identité de l'entreprise et reste en thème clair.

| Document | Émetteur vers destinataire | Contenu principal | Signature |
| --- | --- | --- | --- |
| **Proforma** | Entreprise vers client | Même feuille que la facture : produits ligne par ligne, avance à verser, reste à payer à la livraison, somme en lettres, rapprochement avec l'ancienne dette, validité | Caissier, magasinier et responsable TPE |
| **Reçu** | Entreprise vers client | Montant encaissé, motif (avance ou relicat), mode, solde restant | Agent |
| **Bon de commande** | Entreprise vers boutique | Produits et quantités à fournir, semaine, conditions | Responsable |
| **Bon de livraison** | Entreprise vers client | Kits remis, quantités, relicat encaissé | Client et agent |
| **Facture** | Entreprise vers client | Produits livrés ligne par ligne (unité, quantité, prix unitaire, montant), net à payer, avance, reste à payer, somme en lettres, rapprochement avec l'ancienne dette, contrôle avant sortie, numéro de facture normalisée si applicable | Caissier, magasinier et responsable TPE (cases à la main) |
| **Bordereau** | Entreprise (interne, comptable) | Liste des **factures d'une période** : numéro, date, client, montant, réglé, reste ; totaux | Responsable |

**Présentation de la facture et de la proforma** (une seule feuille, modèle fourni par le responsable). De haut en bas : le titre du projet et la liste des
sous-préfectures (réglages « Titre du projet (factures et proformas) » et sous-préfectures actives), le bandeau « Facture » avec la catégorie des
articles (« Catégorie des articles (factures et proformas) »), puis le fournisseur (l'entreprise), le client, son lieu de livraison (adresse de
livraison, à défaut la localité), sa sous-préfecture, le numéro de la facture, les téléphones et les dates de commande et de
livraison. Le tableau liste chaque produit de la commande avec son unité, sa quantité, son prix unitaire (le montant de la ligne
divisé par la quantité, arrondi) et son montant ; viennent ensuite **NET À PAYER**, **AVANCE** (les avances encaissées, nettes
des remboursements), le **relicat réglé** s'il y en a un, et **RESTE À PAYER**. La somme du net à payer est écrite **en toutes
lettres**. Le **rapprochement avant nouvelle commande** reprend, pour le même client, ses **anciennes dettes** (le solde impayé de
chacune de ses commandes précédentes déjà livrées, avec leur référence et « Non payé » ou « Payé en partie »), son **reliquat**
(ce qu'il a payé en trop sur une commande précédente), le montant de la nouvelle facture, l'avance reçue, le solde de la nouvelle
commande et le **total du reste à payer** (anciennes dettes + solde de la nouvelle commande − reliquat). Suivent la case à cocher
« Contrôle avant sortie » (à remplir à la main) et les signatures du caissier (qui a émis la facture), du magasinier et du
responsable TPE (la personne à contacter de la fiche client, à défaut le client). Le pied de page rappelle l'entreprise, le numéro
et les sous-préfectures. La proforma suit la même feuille : elle indique l'**avance à verser** et le **reste à payer à la livraison**, la date de livraison prévue et sa durée de validité, et n'a pas la liste de contrôle avant sortie. Le **bon de livraison** suit la même feuille (même en-tête, mêmes identités), sans prix ni rapprochement : le tableau donne, produit par produit, la **quantité commandée** et la **quantité livrée** (quantité par kit × kits livrés), puis le total des kits livrés, les remarques éventuelles et les signatures du magasinier, du livreur et de la personne qui a reçu (avec sa signature dessinée). La facture, la proforma et le bon de livraison restent en thème clair et existent en français et en anglais.

**Télécharger les proformas et les factures sans ouvrir les commandes.** Dans la liste des commandes, chaque ligne qui a une proforma ou
une facture valide (non annulée) montre un bouton **Proforma** et/ou **Facture** qui ouvre le PDF. On peut aussi **cocher des lignes** (ou
tout cocher sur la page) : une barre apparaît, avec « Proformas en zip (n) » et « Factures en zip (n) », n étant le nombre de documents
qui existent vraiment parmi les lignes cochées. Enfin le bloc replié **« Télécharger par sous-préfecture »** donne, d'un coup, le zip des
proformas ou des factures de toutes les commandes d'une sous-préfecture pour une semaine de livraison. Rien n'est jamais émis par ce
téléchargement ; les documents annulés n'y sont pas ; un utilisateur limité à certaines zones ne reçoit que celles-ci ; un zip contient
200 documents au plus ; chaque document remis est inscrit au journal d'audit. Les fichiers du zip sont nommés « numéro-client.pdf ».

Le **bordereau** liste les factures émises sur une période donnée, **mensuelle par défaut** (période
paramétrable), avec un filtre possible par sous-préfecture ou par client.

**Facture normalisée électronique (FNE).** En Côte d'Ivoire, la FNE est le dispositif de facturation
électronique de la Direction Générale des Impôts. Selon les sources consultées, les entreprises relevant du régime
normal d'imposition doivent y recourir depuis 2025 ; ce qui s'applique à cette entreprise dépend de son régime
fiscal et doit être confirmé avec son comptable ou la DGI. L'application prévoit donc :

- une **facture** numérotée en interne, avec un champ « numéro FNE » (et son état : non requise, à émettre,
  émise) ;
- dans la première version, la FNE est émise **sur la plateforme de la DGI** et son numéro est reporté dans
  l'application ; un raccordement automatique à cette plateforme est une évolution possible, à décider une fois la
  réglementation applicable confirmée.

Règles communes :

- **Numérotation** : préfixe, année et séquence sans trou, par exemple `PRO-2026-0001`. Préfixe, format et
  remise à zéro annuelle sont paramétrables. Un numéro n'est jamais réutilisé.
- Un document **émis** n'est plus modifiable : on l'**annule** et on en émet un nouveau, avec renvoi.
- La **signature** est capturée à l'écran (mobile ou tablette) et intégrée au PDF, avec date, heure et agent.
- Un modèle de document existant (logo, mentions légales) sera repris tel quel s'il est fourni.

## 7. Paramètres

Tout ce qui suit se règle dans un écran de paramètres réservé au responsable. Les valeurs initiales sont celles du
classeur.

| Paramètre | Valeur initiale | Remarque |
| --- | --- | --- |
| Kits par client et par livraison | 3 | Modifiable par client (livraison automatique) et par commande |
| Avance par cycle | 200 000 FCFA | Modifiable par client et par commande |
| Périodicité de l'avance | Hebdomadaire | Hebdomadaire, quinzaine, mensuelle, autre |
| Périodicité des livraisons | Hebdomadaire | Idem |
| Génération automatique des commandes | Activée | Une commande par cycle et par fourniture active |
| Période du bordereau | Mensuelle | Hebdomadaire, mensuelle, autre |
| Facture normalisée électronique | À confirmer | Non requise, requise (numéro saisi) ou raccordée |
| Avance avant livraison | Obligatoire | Peut devenir facultative |
| Plafond de clients | 0 (aucune limite) | Facultatif ; avertissement, jamais de blocage |
| Sous-préfectures | 5 | Liste extensible, avec agents affectés |
| Modes de paiement | Espèces, mobile money, virement | Liste libre |
| Types de frais | Transport, imprévu, autre | Liste libre |
| Types de documents administratifs | Registre de commerce, FNE, identité | Requises ou non, par objet |
| Numérotation des documents | Préfixe, année, séquence | Par type de document |
| Règle de répartition des frais | Au prorata des kits | Autres règles possibles |
| Monnaie | FCFA | Sans décimale |
| Langue par défaut | Français | Français ou anglais |
| Jour de début de semaine | Lundi | |
| Identité de l'entreprise | À saisir | Nom, adresse, logo, mentions légales |

Un paramètre modifié n'altère **jamais** les documents et commandes déjà émis.

## 8. Langues et thèmes

- Interface en **français et en anglais**, au choix de l'utilisateur, mémorisé dans son profil.
- **Thème** clair, sombre ou système, au choix de l'utilisateur, appliqué sans éclair au chargement.
- Aucun texte écrit en dur. Les libellés saisis par le responsable (modes de paiement, types de frais, types de
  documents administratifs, noms de kits) se saisissent dans **les deux langues**.
- Dates et nombres formatés selon la langue choisie. Montants en FCFA sans décimale, avec séparateur de milliers.
- **Visites guidées** : les écrans complexes (tableau de bord, commandes, nouvelle commande, fiche d'une commande, caisse,
  bénéfice) ont chacun une visite de 2 à 5 étapes. Elle démarre seule à la première ouverture de l'écran par un membre,
  puis plus jamais d'elle-même (l'information est gardée en base, donc la même sur tous ses appareils) ; le bouton « ? » de
  l'en-tête la rejoue. Une étape dont l'élément n'est pas à l'écran (menu replié sur téléphone, bloc réservé à un autre
  profil) est sautée.
- Les règles détaillées sont dans `playbook/06-interface-accessibilite-langues.md`.

## 9. Exigences non fonctionnelles

- **Mobile d'abord** pour l'agent de terrain, grands boutons, saisie rapide ; densité plus élevée au bureau.
- **Accessibilité** : clavier, focus visible, contraste dans les deux thèmes, couleur jamais seule.
- **Sécurité** : droits vérifiés côté serveur, accès filtré par sous-préfecture pour l'agent, fichiers non publics,
  limitation de débit sur la connexion et les actions sensibles, sauvegardes régulières (`playbook/04`, `07`). La
  **double authentification est obligatoire pour le propriétaire** : tant qu'elle n'est pas activée, seule la page de
  sécurité reste ouverte. Chaque page envoie des en-têtes de sécurité et une politique de contenu (CSP) à jeton ; un
  serveur de production **refuse de répondre** si sa configuration est dangereuse (`php artisan app:check-production`).
- **Intégrité financière** : montants en entiers, opérations d'encaissement en transaction, aucun versement ni
  document émis supprimé.
- **Performance** : listes paginées, filtrées et triées côté serveur.
- **Tests** : chaque règle de calcul et chaque transition d'état a son test ; le jeu de référence est celui du
  classeur (voir `README-DONNEES.md`).
- **Fonctionnement hors connexion** pour les agents : non retenu pour l'instant **(à confirmer)**, le réseau en
  sous-préfecture étant inconnu.

## 10. Hors périmètre (version initiale)

- Accès des clients à l'application.
- Comptabilité générale complète et déclarations fiscales.
- Gestion de stock détaillée de plusieurs entrepôts : seule la réception par commande est suivie.
- Paiement en ligne intégré (les paiements mobile money sont **enregistrés**, non initiés par l'application).
- Application mobile native (l'interface web adaptée au mobile suffit).

## 11. Critères d'acceptation

Le produit répond au besoin lorsque :

1. les chiffres de référence du classeur (par kit, par client, pour 56 clients, par boutique) sont **reproduits à
   l'identique** par l'application, avec un bénéfice positif et des totaux généraux corrects ;
2. changer l'avance, le nombre de kits, un prix ou la périodicité ne demande **aucune modification de code** ;
3. un prix modifié **ne change pas** une commande déjà créée ;
4. le cycle complet d'un client est possible, semaine après semaine : fiche, livraison automatique, commande
   générée, proforma, avance et reçu, bon de commande, réception, livraison signée, relicat, facture, clôture ;
5. les six documents (proforma, reçu, bon de commande, bon de livraison, facture, bordereau mensuel) sortent en
   PDF, numérotés sans trou, dans la langue choisie ;
6. un agent ne voit que ses sous-préfectures et aucun prix d'achat ni bénéfice ;
7. le bénéfice **réel** intègre transport et imprévus et se compare au théorique ;
8. un document (registre de commerce, FNE, etc.) peut être jointe à l'entreprise, à une boutique et à un client, et
   alerte avant expiration ;
9. chaque action sensible apparaît dans le journal ;
10. tous les écrans sont utilisables en français et en anglais, en thème clair et sombre, sur mobile.

## 12. Ordre de construction proposé

Un sujet à la fois (`playbook/01`, PRI-06), chacun avec ses tests écrits d'abord.

| Étape | Contenu |
| --- | --- |
| 1 | Socle : désactiver l'inscription publique, rôles et droits, langues et thèmes, paramètres |
| 2 | Sous-préfectures, boutiques, produits avec historique de prix, import des produits du classeur |
| 3 | Kits (versions) et calculs achat, vente, bénéfice théorique |
| 4 | Clients : formulaire, fiche, plafond, affectation des agents |
| 5 | Livraison automatique, commandes générées et états, prix figés, journal de traçabilité |
| 6 | Encaissements, reçus, avance et relicat |
| 7 | Achats aux boutiques, réception, répartition de l'avance, solde dû |
| 8 | Livraisons, signature, mode terrain |
| 9 | Documents PDF et numérotation, factures, bordereau mensuel, champ FNE |
| 10 | Frais de logistique et bénéfice réel |
| 11 | Documents administratifs et alertes |
| 12 | Tableau de bord, rapports, exports |

## 13. Points à confirmer

| N° | Question | Hypothèse retenue |
| --- | --- | --- |
| 1 | ~~Rythme de l'avance et des kits~~ **Tranché** : chaque semaine, 3 kits, avance de 200 000 F ; réglable. Reste à fixer : **combien de semaines** dure la fourniture d'un client (nombre fixe, date de fin, ou sans fin) ? | Sans fin tant que la fourniture est active ; une date de fin ou un nombre de cycles peut être saisi |
| 2 | ~~Montant « 365 100 F »~~ **Tranché** : erreur de calcul de vos notes ; la référence est le classeur (vente de 3 kits : 336 600 F) | Sans objet |
| 3 | ~~« Bordereau »~~ **Tranché** : liste des factures d'une période, mensuelle | Mensuel, période paramétrable |
| 4 | « FNE » : en Côte d'Ivoire, c'est la **facture normalisée électronique** de la DGI, pas un numéro de création d'entreprise. Votre entreprise y est-elle soumise ? Quels sont ses numéros d'entreprise (registre de commerce, compte contribuable, identifiant unique) ? | Champ FNE sur la facture, émission sur la plateforme de la DGI ; documents d'entreprise selon la liste du chapitre 5.10 |
| 5 | Le prix de vente d'un kit est-il la somme des lignes ou un prix fixé ? | Fixé par ligne comme dans le classeur, avec calcul de contrôle |
| 6 | Profils et droits (chapitre 3) conviennent-ils ? L'employé voit-il les bénéfices ? | Non par défaut |
| 7 | Les agents travaillent-ils sans réseau ? | Non, connexion requise |
| 8 | Référence obligatoire pour un paiement non espèces ? Remboursement d'une avance en cas d'annulation ? | Référence obligatoire ; décision au cas par cas |
| 9 | Les clients du plafond (56, puis 58) : blocage ou simple avertissement ? | Décidé : aucune limite par défaut ; un plafond facultatif ne fait qu'avertir |
| 10 | Modèles de documents existants, logo et mentions légales à reprendre ? | Modèle simple à valider |
| 11 | Briques logicielles à ajouter (génération de PDF, signature à l'écran, stockage des fichiers, base de production) ? | Décision du responsable avant toute installation |
| 12 | Répartition des frais de transport : au prorata des kits, ou par livraison ? | Au prorata des kits |

**Mon entreprise.** Une page réservée au propriétaire (menu Configuration) rassemble ce qui identifie l’entreprise : nom, logo (PNG ou JPEG, 2 Mo au plus), forme juridique, capital, centre des impôts, adresse, téléphone, e-mail, site web, personne qui signe et sa fonction, titre du projet, catégorie des articles et une mention de pied de page. Les numéros légaux (registre de commerce, compte contribuable) restent dans les documents administratifs marqués « imprimé sur les documents » et sont repris tels quels. Tous les documents émis (proforma, facture, reçu, bon de livraison, bon de commande, bordereau) portent le même en-tête (logo et identité) et le même pied de page ; les documents déjà émis ne changent pas.

**Livrée et soldée.** Une commande ne passe à **Livrée** que lorsque tous ses kits sont enregistrés comme livrés, et à **Soldée** que lorsque le total est payé : le serveur refuse l’étape sinon, et la fiche montre ce qui reste (kits, montant) avec un bouton vers l’onglet Livraison ou Paiements. Ces deux étapes s’enchaînent d’elles-mêmes quand la livraison ou le paiement qui les complète est enregistré.

**Annulation d’une commande.** Le motif est obligatoire. Si de l’argent a déjà été encaissé sur la commande, la personne doit aussi saisir son mot de passe ; un mot de passe refusé est écrit au journal d’audit (`order.step_password_failed`). Sans paiement, le motif suffit. Les étapes **Livrée, Soldée et Clôturée**, quand on les passe à la main depuis la fiche, demandent aussi le mot de passe dans la fenêtre de confirmation ; celles que l’application enchaîne seule (livraison enregistrée, paiement qui solde la commande) n’en redemandent pas.
