<?php

/*
 * Legal texts (legal notice, privacy policy, terms of use).
 * They describe what the application really does; they are NOT legal advice and must be read by a lawyer
 * (config/legal.php). The {values} come from the "Mon entreprise" page, from config/legal.php and from the
 * settings the code enforces (durations): see App\Support\LegalTexts. When what the application does changes, the
 * text changes in the same commit and the version in config/legal.php goes up.
 */

return [

    'blank' => '[to be completed]',

    'notice' => [
        'title' => 'Legal notice',
        'sections' => [
            [
                'heading' => 'Publisher of the application',
                'paragraphs' => [
                    'The StoreUltra application is published and used by {company}.',
                ],
                'rows' => [
                    ['Company name', '{company}'],
                    ['Legal form', '{legal_form}'],
                    ['Capital', '{capital}'],
                    ['Address', '{address}'],
                    ['Phone', '{phone}'],
                    ['E-mail', '{email}'],
                    ['Website', '{website}'],
                    ['Identification numbers', '{identifiers}'],
                    ['Publication director', '{director}'],
                ],
            ],
            [
                'heading' => 'Hosting',
                'paragraphs' => [
                    'The application and its data are hosted by {host}, {host_address} ({host_country}).',
                ],
            ],
            [
                'heading' => 'Access',
                'paragraphs' => [
                    'The application is an internal management tool. It is open only to the people for whom {company} has created an account. The terms of use and the privacy policy can be read from the sign-in page.',
                ],
            ],
            [
                'heading' => 'Contact',
                'paragraphs' => [
                    'For any question about the application or these texts: {email}. For a request about your personal data: {privacy_email}.',
                ],
            ],
        ],
    ],

    'privacy' => [
        'title' => 'Privacy policy',
        'sections' => [
            [
                'heading' => 'Who is responsible for your data',
                'paragraphs' => [
                    '{company} ({address}) decides what the application records and why: it is the data controller. {host} hosts the data on its behalf; it does not use it for itself.',
                    'For any request about your data: {privacy_email}.',
                ],
            ],
            [
                'heading' => 'The data the application records',
                'paragraphs' => ['Only the data useful to the work of the company is recorded:'],
                'items' => [
                    'Users (employees, agents): name, e-mail address, password (stored in a form that cannot be read back), profile and rights, chosen language and appearance, and, if you turn them on, two-factor authentication and passkeys.',
                    'Clients: name, phone, zone, locality, delivery address, contact person, notes and status; their orders, payments, deliveries (with the signature of reception when it is taken) and the documents issued (proforma, invoice, receipt, delivery note).',
                    'The administrative documents and proofs uploaded (PDF or images), for example an identity document, a trade register or the proof of a cost. They are kept in a private space, never public; the metadata of images is removed on upload; every opening is recorded.',
                    'Technical traces: the audit journal (who did what, when, the value before and after, the IP address and the browser) and the access journal (pages viewed, failed sign-ins, IP address and browser).',
                ],
            ],
            [
                'heading' => 'Why, and for how long',
                'paragraphs' => ['Every piece of data has a purpose and a duration:'],
                'rows' => [
                    ['User account', 'As long as the account exists. A deactivated account can no longer sign in; its past actions stay in the audit journal.'],
                    ['Clients, orders, payments, deliveries', 'The time of the business relationship, then the duration required by accounting and the law.'],
                    ['Documents issued (invoices, receipts, notes)', 'They can be neither changed nor deleted; a correction is a new document. Kept for the duration required by accounting.'],
                    ['Audit journal', 'Kept with no automatic deletion: it is the proof of what was done, protected against any change. A monthly copy is kept for {audit_months} months.'],
                    ['Access journal', '{access_days} days, then deleted automatically.'],
                    ['Sign-in session', 'It expires after {session_minutes} minutes of inactivity.'],
                    ['Backup copies', '{backup_days} days at most: a deleted record can survive in them until then.'],
                ],
            ],
            [
                'heading' => 'Who sees your data',
                'paragraphs' => [
                    'Only the people to whom {company} has given an account, according to their profile: everyone only sees and does what their profile allows, and a field agent only sees the clients and orders of their zones. Access to the administrative documents is recorded.',
                    'Two providers receive data to run the service: {host} ({host_country}), which hosts the application and its backups, and {mail_provider}, which sends the e-mails (for example the link to reset a password). No data is sold or used for advertising.',
                ],
            ],
            [
                'heading' => 'Cookies and trackers',
                'paragraphs' => [
                    'The application only sets cookies it needs to work: the session cookie and the security token of the forms, plus two comfort cookies that remember the light or dark theme and the state of the side menu. There is no audience measurement, no advertising and no third-party tracker: no consent is therefore requested.',
                ],
            ],
            [
                'heading' => 'Security',
                'items' => [
                    'Passwords are stored in an irreversible form and the content of sessions is encrypted.',
                    'Two-factor authentication is required from the owner and offered to the other users.',
                    'The audit journal can be neither changed nor deleted, and its chain is checked every day.',
                    'Files are in a private space and only served to authorised people.',
                    'An encrypted backup is made every day.',
                ],
                'paragraphs' => [
                    'In case of a data breach, {company} would inform the people concerned and the competent authority within the time the law gives.',
                ],
            ],
            [
                'heading' => 'Where the data is',
                'paragraphs' => [
                    'The data is hosted by {host}, country: {host_country}. It is passed to no provider other than the ones named above.',
                ],
            ],
            [
                'heading' => 'Your rights',
                'paragraphs' => [
                    'You can ask {company} for access to your data, for its correction or erasure, or object to its use, by writing to {privacy_email}. The answer is given within the time the law gives: {law} ({country}).',
                    'Limits: data that accounting or the law requires to keep, or that the audit journal protects, cannot be erased before its deadline; backup copies disappear when their duration ends.',
                    'If you think your rights are not respected, you can contact the data protection authority: {authority}.',
                ],
            ],
            [
                'heading' => 'Version of this text',
                'paragraphs' => [
                    'This text is dated {version}. It is updated whenever what the application records, its retention periods or its providers change.',
                ],
            ],
        ],
    ],

    'terms' => [
        'title' => 'Terms of use',
        'sections' => [
            [
                'heading' => 'Who the application is for',
                'paragraphs' => [
                    'StoreUltra is the management tool of {company}: clients, orders, purchases from shops, payments, cash box, costs and documents. It is reserved for the people for whom {company} has created an account.',
                ],
            ],
            [
                'heading' => 'Your account',
                'items' => [
                    'Your account is personal: do not share your password and do not let anyone use your session.',
                    'Choose a password you use nowhere else and turn on two-factor authentication when it is offered.',
                    'Tell {privacy_email} immediately if you think someone else had access to your account.',
                ],
            ],
            [
                'heading' => 'What you may do',
                'paragraphs' => [
                    'Each account has a profile that limits what it sees and does. Do not try to get around these limits. Use the application for the work of {company} only, and record only accurate and useful information.',
                    'Client data is confidential: do not copy it or pass it on beyond what your work requires.',
                ],
            ],
            [
                'heading' => 'What is recorded',
                'paragraphs' => [
                    'Your actions (creating, changing, cancelling, opening an administrative document) are recorded in the audit journal with your name, the time, the IP address and the browser. They can be neither changed nor erased. A mistake is corrected by a new operation (cancellation, refund), never by erasing.',
                ],
            ],
            [
                'heading' => 'Suspension',
                'paragraphs' => [
                    '{company} may suspend or close an account at any time, in particular when someone leaves, on use against these terms or on a security risk. The data entered belongs to {company}.',
                ],
            ],
            [
                'heading' => 'Availability and responsibility',
                'paragraphs' => [
                    'The application is provided as it is: {company} does its best to keep it available and backed up, without guaranteeing there will be no interruption. The amounts and the documents produced stay under the responsibility of the people who validate them.',
                ],
            ],
            [
                'heading' => 'Applicable law',
                'paragraphs' => [
                    'These terms are governed by the law of: {country}.',
                    'Version of {version}.',
                ],
            ],
        ],
    ],

];
