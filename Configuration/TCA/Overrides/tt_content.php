<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

ExtensionUtility::registerPlugin(
    'EudiWalletIntegration',
    'WalletLogin',
    'LLL:EXT:eudi_wallet_integration/Resources/Private/Language/locallang.xlf:plugin.walletLogin.title',
    'actions-user',
    'plugins',
    'LLL:EXT:eudi_wallet_integration/Resources/Private/Language/locallang.xlf:plugin.walletLogin.description',
);

ExtensionManagementUtility::addTCAcolumns('tt_content', [
    'tx_eudiwalletintegration_configuration' => [
        'label' => 'LLL:EXT:eudi_wallet_integration/Resources/Private/Language/locallang_db.xlf:tt_content.configuration',
        'config' => [
            'type' => 'group',
            'allowed' => 'tx_eudiwalletintegration_configuration',
            'minitems' => 1,
            'maxitems' => 1,
            'size' => 1,
        ],
    ],
]);

ExtensionManagementUtility::addToAllTCAtypes(
    'tt_content',
    'tx_eudiwalletintegration_configuration',
    'eudiwalletintegration_walletlogin',
    'after:header',
);
