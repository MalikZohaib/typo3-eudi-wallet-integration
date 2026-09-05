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
        '1' => ['showitem' => 'claim_name, required, hidden'],
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
        'required' => [
            'label' => 'Required',
            'description' => 'The current SDK requires configured DCQL claims. Kept explicit for future optional-claim support.',
            'config' => ['type' => 'check', 'renderType' => 'checkboxToggle', 'default' => 1],
        ],
    ],
];
