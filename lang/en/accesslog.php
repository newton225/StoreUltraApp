<?php

return [

    'head' => 'Access journal',
    'title' => 'Access journal',
    'description' => 'Who opened which page, when, from which address and browser, with the refused sign-ins. Lines are kept :days days and then deleted. For what people changed, see the Journal.',
    'empty' => 'No line for these filters.',
    'kinds' => [
        'page' => 'Page opened',
        'login_failed' => 'Sign-in refused',
        'lockout' => 'Locked out (too many attempts)',
    ],
    'filters' => [
        'all_users' => 'All users',
        'all_kinds' => 'All kinds',
        'from' => 'From',
        'to' => 'To',
        'ip' => 'IP address',
        'apply' => 'Filter',
    ],
    'columns' => [
        'when' => 'When',
        'who' => 'Who',
        'page' => 'Page',
        'status' => 'Response',
        'ip' => 'IP address',
        'browser' => 'Browser',
    ],

];
