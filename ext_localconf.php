<?php

declare(strict_types=1);

use T3Hub\EudiWalletIntegration\Controller\WalletController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

ExtensionUtility::configurePlugin(
    'EudiWalletIntegration',
    'WalletLogin',
    [WalletController::class => 'index'],
    [],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
);


$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'] =
    array_unique(
        array_merge(
            $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'] ?? [],
            [
                'eudi_session',
            ]
        )
    );