# 08. Confidentialité et conformité

Comment traiter des données personnelles et rédiger les textes légaux d'un produit sans les laisser diverger de
ce que le code fait réellement.

> Ces règles sont des pratiques d'ingénierie, **pas un avis juridique**. Les textes rédigés à partir du
> fonctionnement du service se **font relire par un juriste** avant l'ouverture au public. Les lois applicables
> dépendent du pays de l'éditeur et de celui des personnes concernées (loi nationale sur les données
> personnelles, RGPD pour des visiteurs européens, etc.).

## 1. Principes

- **PRIV-01.** **Minimiser** : ne collecter, ne conserver et n'afficher que ce qui sert. Une donnée dont on n'a
  plus besoin s'efface.
- **PRIV-02.** **Savoir pourquoi** chaque donnée est collectée (finalité), **combien de temps** elle est gardée
  (durée), **qui** y accède (rôle, prestataire), **où** elle va (transferts hors du pays).
- **PRIV-03.** **Ce qui est écrit est ce qui est fait.** Les textes légaux décrivent le comportement réel du
  code. Quand une durée, une règle, un prestataire ou une donnée collectée change dans le code, le texte change
  **dans le même commit**, dans toutes les langues, et son **numéro de version avance**.
- **PRIV-04.** **Un test accroche les durées annoncées aux constantes du code** : changer l'un sans l'autre fait
  échouer le test.
- **PRIV-05.** **L'identité de l'éditeur et de ses prestataires** vient de la **configuration**, jamais d'un texte
  en dur. Une valeur vide s'affiche « [à compléter] », et le **contrôle de production refuse de partir** tant
  qu'il en reste ou que la relecture juridique n'est pas confirmée.
- **PRIV-06.** **Rôles** : écrire qui est **responsable du traitement** et qui est **sous-traitant**. Dans un
  produit où chaque client gère les données de ses propres utilisateurs, le client est responsable de ses
  utilisateurs finaux et l'éditeur son sous-traitant ; l'éditeur est responsable pour les comptes, la
  facturation et le site.
- **PRIV-07.** **L'acceptation est gardée** : date et **version** des conditions acceptées, case obligatoire à
  la création du compte.

## 2. Documents à publier

Trois pages liées depuis le pied de site, la création de compte et tout formulaire public qui collecte des
données :

| Document | Contenu minimal |
| --- | --- |
| Politique de confidentialité | Données collectées par catégorie, finalités, durées, prestataires et pays, droits des personnes, contact, cookies et mesure d'audience |
| Conditions d'utilisation | Service, obligations, responsabilité, suspension, résiliation, loi applicable |
| Mentions légales | Identité de l'éditeur et de l'hébergeur, directeur de publication, contact |

- **PRIV-10.** Les textes vivent dans des **fichiers de langue côté serveur**, lus par une classe dédiée et
  transmis en props : on ne les envoie pas dans la charge utile de **chaque** page.
- **PRIV-11.** Les textes ne portent **aucun bandeau « non relu »** sur les pages ; seul le contrôle de
  production rappelle la relecture.
- **PRIV-12.** **Les prestataires figurent au texte** dès qu'un service tiers reçoit des données personnelles
  (hébergeur, courriel, messagerie, SMS, défi anti-robot qui reçoit adresse IP et informations du navigateur,
  paiement, suivi d'erreurs).

## 3. Durées de conservation

Écrire, pour **chaque catégorie**, la durée et le moyen de la faire respecter :

| Catégorie | Exemple de durée | Mécanisme |
| --- | --- | --- |
| Compte | Jusqu'à suppression par l'utilisateur | Suppression à la demande |
| Contenu d'un client | Tant que le client existe | Il décide de la durée pour ses utilisateurs |
| Dossiers non finalisés | À l'échéance fixée par le client, avec les pièces déposées | Purge planifiée |
| Client supprimé | Invisible aussitôt, effacé 30 jours plus tard | Corbeille puis effacement |
| Journal d'actions | 24 mois | Purge planifiée |
| Journal de sécurité | 90 jours | Purge planifiée |
| Adresse IP de l'utilisateur final | 12 mois | Effacement séparé sans casser le chaînage |
| Session de connexion | 12 heures au plus | Expiration absolue |
| Copies de sauvegarde | Un an au plus | Rétention de l'outil de sauvegarde |
| Factures et pièces comptables | Durée imposée par la comptabilité | Conservation légale |
| Appels de webhook | Effacés dès traitement ; en échec : 30 jours | Purge planifiée |

- **PRIV-20.** La **suppression à la demande** a une procédure prévue (qui, comment, quel délai) et **annonce** que
  des copies de sauvegarde peuvent subsister jusqu'à l'expiration de leur rétention.
- **PRIV-21.** Les **traces de lutte contre la fraude** conservées après effacement d'un dossier sont
  **anonymes** (référence normalisée, empreinte, motif), sans nom, numéro ni image, et **disparaissent avec le
  client**. Elles sont mentionnées dans la politique.

## 4. Données minimales dans les systèmes

- **PRIV-30.** Aucune donnée personnelle dans les **adresses**, les **journaux applicatifs**, les **messages
  d'erreur** ni les services de suivi (voir SEC-92).
- **PRIV-31.** Un **journal de purge** ne garde que des **références techniques**.
- **PRIV-32.** Le **numéro de téléphone** d'un tiers n'est affiché qu'à ceux qui en ont besoin ; une liste
  consultable depuis un appareil de terrain **ne permet pas de parcourir** toutes les personnes (recherche
  bornée).
- **PRIV-33.** Un **contact d'organisation** n'est pas divulgué à un tiers : un formulaire de réclamation
  remplace la publication d'un numéro, et c'est l'organisation qui rappelle.
- **PRIV-34.** **Pièces justificatives** (reçus, captures) : données financières personnelles ; URL signées
  courtes, accès journalisé, effacement avec le dossier, réencodage pour retirer les métadonnées.

## 5. Consentement, cookies, mesure d'audience

- **PRIV-40.** **Mesure d'audience** seulement sur les pages commerciales, **après consentement explicite** :
  rien n'est chargé ni déposé avant « Accepter » ; un refus efface les cookies éventuels ; le choix est mémorisé
  et **se révise à tout moment** depuis le pied de page.
- **PRIV-41.** **Jamais** sur le back-office ni sur les parcours dont les adresses portent des jetons, des
  organisations ou des signatures.
- **PRIV-42.** **Données minimales** envoyées : origine et chemin seulement, jamais les paramètres d'adresse ;
  signaux publicitaires et personnalisation **désactivés**.
- **PRIV-43.** La **politique de sécurité du contenu** reste fermée au tiers tant que l'identifiant n'est pas
  réglé ; elle ne l'autorise que lorsque le service est activé.
- **PRIV-44.** Le traitement figure dans la politique (finalité, durée réglée chez le fournisseur, transfert
  hors du pays).

## 6. Droits des personnes

À prévoir et à tester :

- **Accès et portabilité** : un export des données d'une personne, sur demande.
- **Rectification** : modification par la personne ou par le client.
- **Effacement** : voir PRIV-20, avec ses limites annoncées.
- **Opposition et retrait du consentement** : mesure d'audience, communications.
- **Réclamation** : une adresse de contact dédiée à la confidentialité, renseignée dans la configuration.

## 7. Violation de données

- **PRIV-50.** Une **procédure écrite** : qui décide, qui prévient les personnes et l'autorité compétente, dans
  quel délai, avec quels éléments.
- **PRIV-51.** Les **journaux de sécurité** et d'audit permettent de reconstituer **qui a accédé à quoi** ; c'est
  leur raison d'être.
- **PRIV-52.** Un secret exposé est **remplacé** (voir SEC-32) ; une exposition de la base entraîne rotation des
  clés et information des personnes.

## 8. Communications envoyées aux utilisateurs

- **PRIV-60.** **Envoi seulement vers des coordonnées liées à un enregistrement du client courant**, jamais vers une
  liste libre : ce serait un relais de spam sous la réputation de la plateforme.
- **PRIV-61.** **Quotas d'envoi par plan**, appliqués côté serveur, avec compteur visible ; détection des pics.
- **PRIV-62.** **Messageries soumises à modèles approuvés** (hors fenêtre de réponse) : chaque message déclare son
  modèle ; le texte à faire approuver est documenté.
- **PRIV-63.** Domaine d'envoi dédié, **SPF, DKIM, DMARC** configurés, plaintes surveillées.
