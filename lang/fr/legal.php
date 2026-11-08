<?php

/*
 * Textes légaux (mentions légales, politique de confidentialité, conditions d'utilisation).
 * Ils décrivent ce que l'application fait réellement ; ils ne sont PAS un avis juridique et doivent être relus par
 * un juriste (config/legal.php). Les {valeurs} viennent de la page « Mon entreprise », de config/legal.php et des
 * réglages que le code applique (durées) : voir App\Support\LegalTexts. Quand ce que fait l'application change,
 * le texte change dans le même commit et la version de config/legal.php avance.
 */

return [

    'blank' => '[à compléter]',

    'notice' => [
        'title' => 'Mentions légales',
        'sections' => [
            [
                'heading' => 'Éditeur de l\'application',
                'paragraphs' => [
                    'L\'application StoreUltra est éditée et utilisée par {company}.',
                ],
                'rows' => [
                    ['Raison sociale', '{company}'],
                    ['Forme juridique', '{legal_form}'],
                    ['Capital', '{capital}'],
                    ['Adresse', '{address}'],
                    ['Téléphone', '{phone}'],
                    ['E-mail', '{email}'],
                    ['Site web', '{website}'],
                    ['Numéros d\'identification', '{identifiers}'],
                    ['Responsable de la publication', '{director}'],
                ],
            ],
            [
                'heading' => 'Hébergement',
                'paragraphs' => [
                    'L\'application et ses données sont hébergées par {host}, {host_address} ({host_country}).',
                ],
            ],
            [
                'heading' => 'Accès',
                'paragraphs' => [
                    'L\'application est un outil de gestion interne. Elle n\'est ouverte qu\'aux personnes à qui {company} a créé un compte. Les conditions d\'utilisation et la politique de confidentialité sont consultables depuis la page de connexion.',
                ],
            ],
            [
                'heading' => 'Contact',
                'paragraphs' => [
                    'Pour toute question sur l\'application ou sur ces textes : {email}. Pour une demande concernant vos données personnelles : {privacy_email}.',
                ],
            ],
        ],
    ],

    'privacy' => [
        'title' => 'Politique de confidentialité',
        'sections' => [
            [
                'heading' => 'Qui est responsable de vos données',
                'paragraphs' => [
                    '{company} ({address}) décide de ce que l\'application enregistre et pourquoi : elle est la responsable du traitement. {host} héberge les données pour son compte ; il ne s\'en sert pas pour lui-même.',
                    'Pour toute demande sur vos données : {privacy_email}.',
                ],
            ],
            [
                'heading' => 'Les données que l\'application enregistre',
                'paragraphs' => ['Seules les données utiles au travail de l\'entreprise sont enregistrées :'],
                'items' => [
                    'Les utilisateurs (employés, agents) : nom, adresse e-mail, mot de passe (enregistré sous une forme qui ne permet pas de le relire), profil et droits, langue et apparence choisies, et, si vous les activez, la double authentification et les clés d\'accès.',
                    'Les clients : nom, téléphone, zone, localité, adresse de livraison, personne à contacter, notes et statut ; leurs commandes, paiements, livraisons (avec la signature de réception quand elle est prise) et les documents émis (proforma, facture, reçu, bon de livraison).',
                    'Les documents administratifs et les justificatifs déposés (PDF ou images), par exemple une pièce d\'identité, un registre de commerce ou la preuve d\'un frais. Ils sont rangés dans un espace privé, jamais publics ; les métadonnées des images sont retirées au dépôt ; chaque ouverture est enregistrée.',
                    'Les traces techniques : le journal d\'audit (qui a fait quoi, quand, la valeur avant et après, l\'adresse IP et le navigateur) et le journal d\'accès (pages consultées, échecs de connexion, adresse IP et navigateur).',
                ],
            ],
            [
                'heading' => 'Pourquoi, et pour combien de temps',
                'paragraphs' => ['Chaque donnée a un but et une durée :'],
                'rows' => [
                    ['Compte d\'un utilisateur', 'Tant que le compte existe. Un compte désactivé ne peut plus se connecter ; ses actions passées restent dans le journal d\'audit.'],
                    ['Clients, commandes, paiements, livraisons', 'Le temps de la relation commerciale puis la durée imposée par la comptabilité et la loi.'],
                    ['Documents émis (factures, reçus, bons)', 'Ils ne peuvent être ni modifiés ni supprimés ; une correction est un nouveau document. Conservés selon la durée imposée par la comptabilité.'],
                    ['Journal d\'audit', 'Conservé sans effacement automatique : c\'est la preuve de ce qui a été fait, protégée contre toute modification. Une copie mensuelle est gardée {audit_months} mois.'],
                    ['Journal d\'accès', '{access_days} jours, puis supprimé automatiquement.'],
                    ['Session de connexion', 'Elle expire après {session_minutes} minutes d\'inactivité.'],
                    ['Copies de sauvegarde', '{backup_days} jours au plus : une donnée supprimée peut y survivre jusqu\'à cette échéance.'],
                ],
            ],
            [
                'heading' => 'Qui voit vos données',
                'paragraphs' => [
                    'Seules les personnes à qui {company} a donné un compte, selon leur profil : chacun ne voit et ne fait que ce que son profil permet, et un agent de terrain ne voit que les clients et commandes de ses zones. Les accès aux documents administratifs sont enregistrés.',
                    'Deux prestataires reçoivent des données pour faire fonctionner le service : {host} ({host_country}), qui héberge l\'application et ses sauvegardes, et {mail_provider}, qui envoie les e-mails (par exemple le lien de réinitialisation d\'un mot de passe). Aucune donnée n\'est vendue ni utilisée pour de la publicité.',
                ],
            ],
            [
                'heading' => 'Cookies et traceurs',
                'paragraphs' => [
                    'L\'application ne dépose que des témoins nécessaires à son fonctionnement : le témoin de session et le jeton de sécurité des formulaires, ainsi que deux témoins de confort qui retiennent le thème clair ou sombre et l\'état du menu latéral. Il n\'y a ni mesure d\'audience, ni publicité, ni traceur de tiers : aucun consentement n\'est donc demandé.',
                ],
            ],
            [
                'heading' => 'Sécurité',
                'items' => [
                    'Les mots de passe sont enregistrés sous une forme irréversible et le contenu des sessions est chiffré.',
                    'La double authentification est exigée du propriétaire et proposée aux autres utilisateurs.',
                    'Le journal d\'audit ne peut être ni modifié ni supprimé, et sa chaîne est vérifiée chaque jour.',
                    'Les fichiers sont dans un espace privé et ne sont servis qu\'aux personnes autorisées.',
                    'Une sauvegarde chiffrée est faite chaque jour.',
                ],
                'paragraphs' => [
                    'En cas de violation de données, {company} préviendrait les personnes concernées et l\'autorité compétente dans les délais prévus par la loi.',
                ],
            ],
            [
                'heading' => 'Où sont les données',
                'paragraphs' => [
                    'Les données sont hébergées chez {host}, pays : {host_country}. Elles ne sont transmises à aucun autre prestataire que ceux nommés plus haut.',
                ],
            ],
            [
                'heading' => 'Vos droits',
                'paragraphs' => [
                    'Vous pouvez demander à {company} l\'accès à vos données, leur correction, leur effacement ou vous opposer à leur usage, en écrivant à {privacy_email}. La réponse est donnée dans les délais prévus par la loi : {law} ({country}).',
                    'Limites : une donnée que la comptabilité ou la loi oblige à garder, ou que le journal d\'audit protège, ne peut pas être effacée avant son échéance ; les copies de sauvegarde disparaissent à l\'expiration de leur durée.',
                    'Si vous estimez que vos droits ne sont pas respectés, vous pouvez vous adresser à l\'autorité de protection des données : {authority}.',
                ],
            ],
            [
                'heading' => 'Version de ce texte',
                'paragraphs' => [
                    'Ce texte est daté du {version}. Il est mis à jour chaque fois que ce que l\'application enregistre, ses durées de conservation ou ses prestataires changent.',
                ],
            ],
        ],
    ],

    'terms' => [
        'title' => 'Conditions d\'utilisation',
        'sections' => [
            [
                'heading' => 'À qui s\'adresse l\'application',
                'paragraphs' => [
                    'StoreUltra est l\'outil de gestion de {company} : clients, commandes, achats aux boutiques, paiements, caisse, frais et documents. Elle est réservée aux personnes à qui {company} a créé un compte.',
                ],
            ],
            [
                'heading' => 'Votre compte',
                'items' => [
                    'Votre compte est personnel : ne partagez pas votre mot de passe et ne laissez personne utiliser votre session.',
                    'Choisissez un mot de passe que vous n\'utilisez nulle part ailleurs et activez la double authentification quand elle vous est proposée.',
                    'Prévenez immédiatement {privacy_email} si vous pensez que quelqu\'un d\'autre a eu accès à votre compte.',
                ],
            ],
            [
                'heading' => 'Ce que vous pouvez faire',
                'paragraphs' => [
                    'Chaque compte a un profil qui limite ce qu\'il voit et ce qu\'il fait. N\'essayez pas de contourner ces limites. Utilisez l\'application pour le travail de {company} seulement, et n\'y enregistrez que des informations exactes et utiles.',
                    'Les données des clients sont confidentielles : ne les copiez pas et ne les transmettez pas en dehors de ce que votre travail exige.',
                ],
            ],
            [
                'heading' => 'Ce qui est enregistré',
                'paragraphs' => [
                    'Vos actions (création, modification, annulation, consultation d\'un document administratif) sont enregistrées dans le journal d\'audit avec votre nom, l\'heure, l\'adresse IP et le navigateur. Elles ne peuvent être ni modifiées ni effacées. Une erreur se corrige par une nouvelle opération (annulation, remboursement), jamais en effaçant.',
                ],
            ],
            [
                'heading' => 'Suspension',
                'paragraphs' => [
                    '{company} peut suspendre ou fermer un compte à tout moment, en particulier en cas de départ, d\'usage contraire à ces conditions ou de risque pour la sécurité. Les données saisies appartiennent à {company}.',
                ],
            ],
            [
                'heading' => 'Disponibilité et responsabilité',
                'paragraphs' => [
                    'L\'application est fournie telle quelle : {company} fait au mieux pour qu\'elle soit disponible et sauvegardée, sans garantir l\'absence d\'interruption. Les montants et les documents produits restent sous la responsabilité de ceux qui les valident.',
                ],
            ],
            [
                'heading' => 'Loi applicable',
                'paragraphs' => [
                    'Ces conditions sont soumises à la loi de : {country}.',
                    'Version du {version}.',
                ],
            ],
        ],
    ],

];
