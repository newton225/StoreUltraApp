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
    ],

    'create' => [
        'head' => 'New user',
        'title' => 'New user',
        'description' => 'The person logs in with this email address and password',
        'submit' => 'Create user',
    ],

    'form' => [
        'role_help' => 'The profile decides what the user is allowed to do (see the Profiles screen). A profile limited to sub-prefectures only sees the clients, orders and deliveries of the assigned sub-prefectures.',
        'name' => 'Name',
        'name_placeholder' => 'Full name',
        'email' => 'Email address',
        'role' => 'Role',
        'profile' => 'Profile',
        'password' => 'Password',
        'password_confirmation' => 'Confirm password',
        'password_optional' => 'Leave empty to keep the current password',
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
