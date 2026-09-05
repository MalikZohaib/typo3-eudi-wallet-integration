<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'EUDI requested claim',
        'label' => 'claim_name',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'delete' => 'deleted',
        'enablecolumns' => ['disabled' => 'hidden'],
        'hideTable' => true,
        'sortby' => 'sorting',
    ],
    'types' => [
        '1' => ['showitem' => 'claim_name, target_field, required, overwrite_existing, hidden'],
    ],
    'columns' => [
        'hidden' => [
            'label' => 'Disabled',
            'config' => ['type' => 'check', 'renderType' => 'checkboxToggle'],
        ],
        'configuration' => [
            'config' => ['type' => 'passthrough'],
        ],
        'claim_name' => [
            'label' => 'Claim name/path',
            'description' => 'For the current SDK SD-JWT helper this is a top-level claim name, for example given_name or age_over_18.',
            'config' => ['type' => 'input', 'required' => true, 'max' => 255],
        ],
        'target_field' => [
            'label' => 'Map into fe_users field',
            'description' => 'Leave empty to request/verify the claim without writing it to fe_users. This mapping is ignored entirely in claims-only mode.',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [['label' => '(do not map)', 'value' => '']],
                'itemsProcFunc' => 'T3Hub\\EudiWalletIntegration\\Tca\\FeUserFieldItems->addItems',
            ],
        ],
        'required' => [
            'label' => 'Required',
            'description' => 'The current SDK requires configured DCQL claims. Kept explicit for future optional-claim support.',
            'config' => ['type' => 'check', 'renderType' => 'checkboxToggle', 'default' => 1],
        ],
        'overwrite_existing' => [
            'label' => 'Overwrite existing fe_users value (login mode only)',
            'config' => ['type' => 'check', 'renderType' => 'checkboxToggle', 'default' => 1],
        ],
    ],
];
