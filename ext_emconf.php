<?php

declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title' => 'EUDI Wallet Integration',
    'description' => 'Connect TYPO3 with EUDI Wallets to verify and claim credentials, with support for securely autofilling credential data into TYPO3 forms.',
    'category' => 'fe',
    'author' => 'T3 Hub',
    'author_email' => '',
    'state' => 'alpha',
    'clearCacheOnLoad' => true,
    'version' => '0.3.4',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.3.99',
            'felogin' => '14.3.0-14.3.99',
            'php' => '8.2.0-8.5.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
