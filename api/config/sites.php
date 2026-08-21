<?php

$credentials = require __DIR__ . '/site_credentials.php';

return [
    'sites' => [
        'formulapaddock' => [
            'label' => 'Formula Paddock',
            'url' => 'https://www.formulapaddock.it',
            'username' => $credentials['formulapaddock']['username'] ?? '',
            'application_password' => $credentials['formulapaddock']['application_password'] ?? '',
            'default_category' => '',
            'default_parent_page' => '',
        ],

        'wec' => [
            'label' => 'Formula Paddock WEC',
            'url' => 'https://wec.formulapaddock.it',
            'username' => $credentials['wec']['username'] ?? '',
            'application_password' => $credentials['wec']['application_password'] ?? '',
            'default_category' => '',
            'default_parent_page' => '',
        ],

        'formula2' => [
            'label' => 'Formula Paddock Formula 2',
            'url' => 'https://formula2.formulapaddock.it',
            'username' => $credentials['formula2']['username'] ?? '',
            'application_password' => $credentials['formula2']['application_password'] ?? '',
            'default_category' => '',
            'default_parent_page' => '',
        ],
    ],
];
