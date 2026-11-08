<?php

return [

    'head' => 'Users',
    'title' => 'Users',
    'description' => 'Create accounts and choose what each person can do',
    'new' => 'New user',
    'edit' => 'Edit',
    'empty' => 'No users yet',
    'created' => 'User created.',
    'updated' => 'User updated.',
    'created_invited' => 'User created. An invitation e-mail was sent to :email.',
    'created_not_sent' => 'User created, but the e-mail could not be sent. Show the link below to hand it over.',
    'mail_test_mode' => 'No e-mail is really sent: mail is in test mode (MAIL_MAILER=log). Set up the mail in the .env file, or show the link to hand it over.',
    'invitation_sent' => 'E-mail sent to :email.',
    'invitation_not_sent' => 'The e-mail could not be sent. Show the link to hand it over yourself.',

    'filters' => [
        'search' => 'Search by name or e-mail address',
        'apply' => 'Search',
        'all_profiles' => 'All profiles',
        'all_states' => 'All statuses',
        'no_match' => 'No user matches these filters',
    ],

    'invitation' => [
        'title' => 'Password',
        'pending' => 'The person has not chosen their password yet: they cannot log in.',
        'done' => 'The person chose their password. You do not know it, and that is intended.',
        'sent_at' => 'Last e-mail handed to the mail server on :date. If it does not arrive, the person should check their junk folder.',
        'never_sent' => 'No e-mail has been sent for this account yet.',
        'send' => 'Send an e-mail to choose the password',
        'send_help' => 'A new link, valid for 48 hours. It replaces the previous one. Also useful if the person forgot their password.',
        'show_link' => 'Show the link to hand over',
        'show_link_title' => 'Show the link?',
        'show_link_description' => 'This link lets whoever holds it set the password of this account. Hand it only to the person concerned. A new link replaces the previous one, and showing it is written to the log.',
        'link_label' => 'Link to hand over',
        'link_help' => 'Valid for 48 hours, usable once.',
        'copy' => 'Copy',
        'copied' => 'Link copied.',
    ],

    'columns' => [
        'name' => 'Name',
        'email' => 'Email address',
        'role' => 'Role',
        'profile' => 'Profile',
        'status' => 'Status',
    ],

    'roles' => [
        'owner' => 'Owner',
        'employee' => 'Employee',
        'agent' => 'Field agent',
    ],

    'status' => [
        'active' => 'Active',
        'inactive' => 'Deactivated',
        'pending' => 'Invitation pending',
    ],

    'create' => [
        'head' => 'New user',
        'title' => 'New user',
        'description' => 'The person receives an e-mail to choose their own password',
        'submit' => 'Create and send the invitation',
    ],

    'form' => [
        'role_help' => 'The profile decides what the user is allowed to do (see the Profiles screen). A profile limited to sub-prefectures only sees the clients, orders and deliveries of the assigned sub-prefectures.',
        'name' => 'Name',
        'name_placeholder' => 'Full name',
        'email' => 'Email address',
        'role' => 'Role',
        'profile' => 'Profile',
        'invitation_note' => 'An e-mail will be sent to this address: the person will choose their own password there, which you will not know.',
        'zones' => 'Sub-prefectures',
        'zones_help' => 'A field agent only sees the clients of these sub-prefectures',
        'zones_empty' => 'No sub-prefecture exists yet',
        'is_active' => 'Active account',
        'is_active_help' => 'A deactivated user can no longer log in',
        'submit' => 'Save',
    ],

    'edit_page' => [
        'head' => 'Edit user',
        'title' => 'Edit user',
        'description' => 'Change the details, the role or the status of this account',
    ],

    'errors' => [
        'own_role' => 'You cannot change your own role.',
        'own_status' => 'You cannot deactivate your own account.',
    ],

];
