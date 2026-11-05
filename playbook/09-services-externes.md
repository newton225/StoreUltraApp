# 09. Services externes

Choisir, brancher, surveiller et pouvoir remplacer un service tiers (courriel, messagerie, SMS, paiement,
hébergement, stockage, anti-robot, mesure d'audience, surveillance).

## 1. Choisir un service

- **EXT-01.** **Vérifier avant de recommander.** Un prestataire se vérifie sur des sources indépendantes :
  existence légale et **société identifiée**, ancienneté, **avis d'utilisateurs réels**, conditions de
  remboursement, pays de traitement des données, présence dans le pays visé. Un service inconnu qui demande un
  paiement d'avance est traité comme **suspect** jusqu'à preuve du contraire (incident réel : un paiement perdu
  chez un prestataire jamais vérifié).
- **EXT-02.** **Ne pas citer de mémoire ce qui change** : prix, quotas, disponibilité dans un pays, versions,
  conditions. Chercher la source du moment, **dater** l'information, et faire **confirmer les prix par le
  responsable** avant de bâtir un budget dessus. Deux erreurs de prix dans une recommandation suffisent à perdre
  la confiance.
- **EXT-03.** **Comparer les alternatives sérieuses** : maintenance, adoption, documentation, coût, adéquation au
  besoin, **possibilité de partir** (portabilité des données, absence de verrouillage).
- **EXT-04.** **Disponibilité locale** : un service très répandu ailleurs peut ne pas exister dans le pays du
  produit (paiement en ligne indisponible, SMS sans accord avec les opérateurs locaux). Le vérifier **avant** de
  concevoir autour de lui ; prévoir l'alternative manuelle (paiement à la main, par exemple).
- **EXT-05.** **Une contrainte réglementaire du fournisseur est une exigence produit** : vérification d'entreprise
  d'une messagerie, modèles de messages à faire approuver, numéro dédié, politique de confidentialité publique
  exigée pour publier une application. Les connaître **tôt**, car elles bloquent la mise en production sans
  rapport avec le code.
- **EXT-06.** **Coût à l'usage** : chaque envoi payant (SMS, message) a un plafond mensuel appliqué par plan et un
  compteur ; un code de vérification payant a un plafond par destinataire et par heure.
- **EXT-07.** **Dépendre peu** : trois lignes de code ne justifient pas une dépendance ; un service gratuit
  n'est pas sans coût (limite d'envoi, réputation, fermeture).

## 2. Brancher un service : l'interface avant le fournisseur

- **EXT-10.** Chaque service tiers est **derrière une interface dédiée** (`MessageSender::send(to, message)`),
  liée à une implémentation dans le conteneur de services. Le jour où le fournisseur change, **seule la liaison
  change**, jamais les actions ni les notifications.
- **EXT-11.** **Plusieurs pilotes sélectionnables par la configuration** (`DRIVER=twilio | meta | log`), un pilote
  de **journal** par défaut quand les identifiants manquent : développement et tests n'envoient rien de réel.
- **EXT-12.** **Aucun paquet installé pour une implémentation qui n'appellerait personne.** On branche le
  fournisseur quand son compte est prêt ; en attendant, le pilote de journal.
- **EXT-13.** **Client HTTP du framework** pour les connecteurs simples, plutôt qu'un SDK par service. Un refus du
  service **lève une exception** : l'envoi échoue visiblement et se relance depuis la console.
- **EXT-14.** Un canal de notification est enregistré par **le point d'extension du framework**, pas par un envoi
  improvisé dans une action.
- **EXT-15.** Les **destinataires sans compte** (invités) se routent **à la demande** (`route('mail', …)`) : ils
  ne sont pas des utilisateurs authentifiés.
- **EXT-16.** **Messagerie à modèles** : chaque notification déclare le sien (clé et variables dans l'ordre) ;
  l'identifiant réel du modèle approuvé est un **réglage**. Les textes à faire approuver sont documentés dans un
  fichier du dépôt.
- **EXT-17.** **Panne du service** : décider, service par service, entre « échouer fermé » (refuser) et « laisser
  passer en journalisant » (par exemple un défi anti-robot injoignable). L'écrire dans le fichier
  d'instructions.
- **EXT-18.** **Jamais d'appel réseau réel dans les tests** : toute requête sortante non simulée échoue (voir
  TEST-43).

## 3. Webhooks et rappels

- **EXT-20.** **Signature vérifiée** avant tout traitement, en temps constant ; secret d'application en
  configuration.
- **EXT-21.** **Une seule adresse de rappel**, exemptée de CSRF seulement pour son chemin, avec limiteur.
- **EXT-22.** **Traitement idempotent**, contenu personnel **effacé** une fois traité.
- **EXT-23.** **Vérifier l'expéditeur** quand le message prouve une identité : un code de vérification reçu par
  messagerie ne vaut que si l'**expéditeur est le numéro saisi** (comparaison tolérante aux variantes de format).
- **EXT-24.** Un **sondage côté client** pour attendre un événement externe a son limiteur (voir SEC-73).

## 4. Inventaire des services

Un fichier du dépôt liste, **sans secret**, tous les services extérieurs :

| Colonne | Contenu |
| --- | --- |
| Service | Nom et rôle |
| Statut | En service, en réserve, en attente, non branché |
| Où le gérer | Lien vers la console du fournisseur |
| Identifiants non secrets | Identifiants de compte, de numéro, d'application (ne permettent rien seuls) |
| Variables d'environnement | Noms uniquement |
| Reste à faire | Vérifications, approbations, paiement |

Une liste **« pas encore branchés »** évite d'oublier les services prévus (mesure d'audience, surveillance
extérieure, stockage externe, paiement d'abonnement).

## 5. Catégories et points d'attention

| Catégorie | Points d'attention |
| --- | --- |
| Courriel | Domaine d'envoi dédié, SPF, DKIM, DMARC ; limites d'envoi du fournisseur ; gabarit aux couleurs de l'organisation pour les messages aux invités, gabarit par défaut pour les messages d'équipe |
| Messagerie instantanée | Modèles approuvés hors fenêtre de 24 h, vérification d'entreprise, numéro dédié, mode « publié » de l'application avant les vrais messages |
| SMS | Coût par message, pays couverts, plafond par numéro et par heure ; à réserver au strict nécessaire |
| Paiement en ligne | Disponibilité dans le pays, conformité, cas du paiement manuel |
| Hébergement | Serveur dédié ou partagé, accès administrateur, sauvegarde du fournisseur, localisation |
| DNS et certificats | Jeton d'API limité, renouvellement automatique |
| Stockage objet | Compatible S3, chiffrement, identifiants distincts, politique de rétention |
| Défi anti-robot | Thème et largeur adaptés, affichage sur doute, panne décidée, mentionné dans la politique de confidentialité |
| Mesure d'audience | Consentement préalable, pages commerciales seulement |
| Surveillance | Appel à chaque passage du planificateur (heartbeat), alerte hors du serveur |
| Suivi d'erreurs | Données anonymisées avant envoi |

## 6. Cycle de vie d'un branchement

1. **Décision** (qui, quand, pourquoi) écrite dans le fichier d'instructions.
2. **Interface et pilote de journal** d'abord ; tests contre l'interface.
3. **Connecteur réel** derrière le même contrat ; tests avec simulation de requêtes sortantes.
4. **Configuration** : variables d'environnement documentées dans l'exemple de configuration (sans valeur
   secrète) et dans l'inventaire.
5. **Contrôle de production** : le service requis par le produit est vérifié (clés réelles, pas clés d'essai).
6. **Surveillance** : échec visible dans la console, alerte, relance possible.
7. **Réversibilité** : comment on repasse sur le pilote de journal ou sur un autre fournisseur sans toucher aux
   actions.
