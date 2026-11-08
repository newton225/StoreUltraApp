<?php

return [

    'head' => 'Santé du système',
    'title' => 'Santé du système',
    'description' => 'Ce qui permet de se fier au système : sauvegardes, tâches automatiques, file d\'attente, journal, base de données, disque et configuration. Rien ici ne modifie quoi que ce soit.',
    'unit_mb' => 'Mo',
    'recheck' => 'Revérifier',
    'checked_at' => 'Vérifié à :time',
    'details' => 'Détails techniques',
    'todo' => 'À faire',

    'summary' => [
        'ok' => 'Tout va bien : aucun point à traiter.',
        'attention' => ':errors à corriger, :warnings à surveiller.',
    ],

    'status' => [
        'ok' => 'Bon',
        'warning' => 'À surveiller',
        'error' => 'À corriger',
    ],

    'checks' => [
        'backup' => [
            'title' => 'Sauvegarde',
            'ok' => 'Dernière sauvegarde : :ago (:size).',
            'late' => 'La dernière sauvegarde date de :ago (:size) : une sauvegarde par jour est attendue.',
            'never' => 'Aucune sauvegarde n\'a encore été faite.',
            'not_configured' => 'La sauvegarde n\'est pas configurée : aucun mot de passe n\'est défini, donc rien n\'est sauvegardé.',
            'local_disk' => 'Dernière sauvegarde : :ago (:size), mais elle est gardée sur le serveur lui-même : elle serait perdue avec lui.',
            'todo' => 'Définir BACKUP_PASSWORD dans le fichier .env, pointer BACKUP_DISK vers un stockage extérieur au serveur, puis lancer « php artisan app:backup ». Garder le mot de passe ailleurs que sur le serveur.',
        ],
        'scheduler' => [
            'title' => 'Tâches automatiques',
            'ok' => 'Le planificateur tourne : les tâches du jour (sauvegarde, commandes automatiques, contrôles du journal) se lancent.',
            'never' => 'Le planificateur n\'a jamais donné signe de vie : aucune tâche automatique ne se lance.',
            'stopped' => 'Le planificateur est arrêté depuis :minutes minutes : plus aucune tâche automatique ne se lance.',
            'todo' => 'Vérifier que la tâche planifiée du serveur exécute « php artisan schedule:run » chaque minute (cron, ou « php artisan schedule:work » en développement).',
        ],
        'queue' => [
            'title' => 'File d\'attente',
            'ok' => 'La file d\'attente est vide ou avance normalement.',
            'sync' => 'Les tâches s\'exécutent tout de suite, sans file d\'attente.',
            'failed' => ':count tâche(s) en échec dans la file d\'attente.',
            'stuck' => 'Des tâches attendent depuis plus de :minutes minutes : le traitement de la file est peut-être arrêté.',
            'unknown' => 'L\'état de la file d\'attente n\'a pas pu être lu.',
            'todo' => 'Vérifier que « php artisan queue:work » tourne (superviseur du serveur), puis relancer ou purger les tâches en échec (« php artisan queue:retry all »).',
        ],
        'audit_chain' => [
            'title' => 'Journal d\'audit',
            'ok' => 'Chaîne du journal intacte (:entries entrées), vérifiée :ago.',
            'never' => 'La chaîne du journal n\'a pas encore été vérifiée.',
            'late' => 'La dernière vérification de la chaîne du journal date de :ago : elle est attendue chaque jour.',
            'broken' => 'La chaîne du journal est rompue (vérifiée :ago) : une entrée a été modifiée ou retirée.',
            'todo' => 'Lancer « php artisan app:verify-audit-log » pour voir le détail. Une chaîne rompue est grave : ne pas toucher à la base et restaurer la sauvegarde la plus récente saine si nécessaire.',
        ],
        'migrations' => [
            'title' => 'Base de données',
            'ok' => 'La structure de la base est à jour.',
            'pending' => 'Des mises à jour de la base n\'ont pas été exécutées : les profils peuvent manquer de leurs nouveaux droits.',
            'todo' => 'Lancer « php artisan migrate » (en production : avec le rôle migrateur, option --database=pgsql_migrator --force).',
        ],
        'configuration' => [
            'title' => 'Configuration',
            'ok' => 'Aucun réglage dangereux.',
            'errors' => ':count réglage(s) dangereux pour un serveur en production.',
            'warnings' => ':count réglage(s) à regarder (sans danger immédiat).',
            'todo' => 'Lancer « php artisan app:check-production » pour la liste, puis corriger le fichier .env.',
        ],
        'disk' => [
            'title' => 'Espace disque',
            'ok' => ':percent % libre (:free).',
            'low' => 'Plus que :percent % d\'espace libre (:free).',
            'full' => 'Disque presque plein : :percent % libre (:free). Les fichiers et sauvegardes risquent d\'échouer.',
            'unknown' => 'L\'espace disque n\'a pas pu être mesuré.',
            'todo' => 'Libérer de la place ou agrandir le disque ; vérifier que les sauvegardes partent bien hors du serveur.',
        ],
        'legal' => [
            'title' => 'Textes légaux',
            'ok' => 'Les mentions légales, la politique de confidentialité et les conditions d\'utilisation sont complètes et relues.',
            'blanks' => ':count information(s) manquent encore dans les textes légaux, qui affichent « [à compléter] ».',
            'not_reviewed' => 'Les textes légaux sont complets mais leur relecture par un juriste n\'est pas confirmée.',
            'todo' => 'Compléter la page « Mon entreprise » et les réglages LEGAL_* du fichier .env, faire relire les textes par un juriste, puis renseigner LEGAL_REVIEWED_VERSION.',
        ],
        'mail' => [
            'title' => 'Courriel',
            'ok' => 'L\'envoi de courriels est configuré.',
            'not_sending' => 'Aucun courriel n\'est réellement envoyé : les liens de réinitialisation de mot de passe n\'arriveraient jamais.',
            'todo' => 'Configurer MAIL_MAILER et les réglages MAIL_* dans le fichier .env.',
        ],
    ],

];
