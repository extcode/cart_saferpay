<?php

defined('TYPO3_MODE') or die();

$dispatcher = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
    \TYPO3\CMS\Extbase\SignalSlot\Dispatcher::class
);

$dispatcher->connect(
    \Extcode\Cart\Utility\OrderUtility::class,
    'handlePayment',
    \Extcode\CartSaferpay\Utility\OrderUtility::class,
    'handlePayment'
);
