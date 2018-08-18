<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Cart - Saferpay',
    'description' => 'Shopping Cart(s) for TYPO3 - Saferpay Payment Provider',
    'category' => 'services',
    'author' => 'Daniel Lorenz',
    'author_email' => 'ext.cart@extco.de',
    'author_company' => 'extco.de UG (haftungsbeschränkt)',
    'shy' => '',
    'priority' => '',
    'module' => '',
    'state' => 'stable',
    'internal' => '',
    'uploadfolder' => '0',
    'createDirs' => '',
    'modify_tables' => '',
    'clearCacheOnLoad' => 0,
    'lockType' => '',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-8.7.99',
            'php' => '7.0.0',
            'cart' => '4.8.0',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
