<?php

declare(strict_types=1);

use T3Hub\EudiWalletIntegration\Middleware\WalletFrontendMiddleware;
use T3Hub\EudiWalletIntegration\Middleware\WalletProtocolMiddleware;

return [
    'frontend' => [
        't3hub/eudi-wallet-integration/protocol' => [
            'target' => WalletProtocolMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
        't3hub/eudi-wallet-integration/frontend' => [
            'target' => WalletFrontendMiddleware::class,
            'after' => [
                'typo3/cms-frontend/authentication',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
