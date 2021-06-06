<?php

defined('TYPO3_MODE') or die();

// configure plugins

\TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin(
    'CartSaferpay',
    'Cart',
    [
        \Extcode\CartSaferpay\Controller\Order\PaymentController::class => 'success, cancel',
    ],
    [
        \Extcode\CartSaferpay\Controller\Order\PaymentController::class => 'success, cancel',
    ]
);
