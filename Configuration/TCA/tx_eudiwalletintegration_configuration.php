<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:eudi_wallet_integration/Resources/Private/Language/locallang_db.xlf:configuration',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'delete' => 'deleted',
        'enablecolumns' => ['disabled' => 'hidden'],
        'iconfile' => 'EXT:eudi_wallet_integration/Resources/Public/Icons/Extension.svg',
        'searchFields' => 'title,identifier,vct,purpose',
    ],
    'types' => [
        '1' => [
            'showitem' => '
                --palette--;;general,
                --div--;Credential request,
                    credential_query_id, vct, purpose, require_holder_binding,
                    claim_mappings,
                --div--;Frontend user mapping (login mode only),
                    create_missing_users, update_existing_users, user_storage_pid, default_usergroup,
                    identity_claim, match_claim, match_field, username_claim,
                --div--;Credential issuer trust,
                    trust_anchors,
                --div--;Audit / persistence,
                    store_verification_result,
                --div--;Access,
                    hidden
            ',
        ],
    ],
    'palettes' => [
        'general' => [
            'showitem' => 'title, identifier, --linebreak--, mode',
        ],
    ],
    'columns' => [
        'hidden' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.visible',
            'config' => ['type' => 'check', 'renderType' => 'checkboxToggle', 'default' => 0],
        ],
        'title' => [
            'label' => 'Title',
            'config' => ['type' => 'input', 'required' => true, 'max' => 255],
        ],
        'identifier' => [
            'label' => 'Identifier',
            'description' => 'Stable human-readable identifier, for example customer-login or age-verification.',
            'config' => ['type' => 'input', 'required' => true, 'max' => 120, 'eval' => 'trim,unique'],
        ],
        'mode' => [
            'label' => 'Verification mode',
            'description' => 'Login maps/links the verified wallet identity to fe_users. Claims only returns requested claims. Age over 18 requests only the boolean age_over_18 claim and exposes the verified result to other TYPO3 extensions; it does not create an age cookie or FE login.',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => 'login',
                'onChange' => 'reload',
                'items' => [
                    ['label' => 'Wallet login (fe_users)', 'value' => 'login'],
                    ['label' => 'Verify claims only (no login)', 'value' => 'claims_only'],
                    ['label' => 'Age over 18 (integration API, no cookie)', 'value' => 'age_over_18'],
                ],
            ],
        ],
        'credential_query_id' => [
            'label' => 'DCQL credential query ID',
            'config' => ['type' => 'input', 'required' => true, 'default' => 'pid', 'max' => 80],
        ],
        'vct' => [
            'label' => 'SD-JWT VCT',
            'description' => 'The Verifiable Credential Type expected in the real wallet.',
            'config' => ['type' => 'input', 'required' => true, 'max' => 255],
        ],
        'purpose' => [
            'label' => 'Purpose',
            'config' => ['type' => 'text', 'rows' => 3],
        ],
        'require_holder_binding' => [
            'label' => 'Require cryptographic holder binding',
            'config' => ['type' => 'check', 'renderType' => 'checkboxToggle', 'default' => 1],
        ],
        'claim_mappings' => [
            'label' => 'Requested claims / optional fe_users mappings',
            'description' => 'All rows are requested from the wallet in login/claims-only modes. Age over 18 ignores these rows and requests only age_over_18. fe_users target fields are used only in Wallet login mode.',
            'displayCond' => [
                'OR' => [
                    'FIELD:mode:=:login',
                    'FIELD:mode:=:claims_only',
                ],
            ],
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_eudiwalletintegration_claim_mapping',
                'foreign_field' => 'configuration',
                'foreign_sortby' => 'sorting',
                'appearance' => [
                    'collapseAll' => true,
                    'newRecordLinkTitle' => 'Add requested claim',
                    'useSortable' => true,
                ],
            ],
        ],
        'trust_anchors' => [
            'label' => 'Credential issuer trust anchors',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_eudiwalletintegration_trust_anchor',
                'foreign_field' => 'configuration',
                'foreign_sortby' => 'sorting',
                'appearance' => [
                    'collapseAll' => true,
                    'newRecordLinkTitle' => 'Add issuer trust anchor',
                    'useSortable' => true,
                ],
            ],
        ],
        'create_missing_users' => [
            'label' => 'Create fe_users when no account is linked/matched',
            'displayCond' => 'FIELD:mode:=:login',
            'config' => ['type' => 'check', 'renderType' => 'checkboxToggle', 'default' => 0],
        ],
        'update_existing_users' => [
            'label' => 'Update mapped fe_users fields on every verification',
            'displayCond' => 'FIELD:mode:=:login',
            'config' => ['type' => 'check', 'renderType' => 'checkboxToggle', 'default' => 1],
        ],
        'user_storage_pid' => [
            'label' => 'Frontend user storage PID',
            'displayCond' => 'FIELD:mode:=:login',
            'config' => ['type' => 'number', 'range' => ['lower' => 0]],
        ],
        'default_usergroup' => [
            'label' => 'Default frontend user group for auto-created users',
            'displayCond' => 'FIELD:mode:=:login',
            'config' => [
                'type' => 'group',
                'allowed' => 'fe_groups',
                'maxitems' => 1,
                'size' => 1,
            ],
        ],
        'identity_claim' => [
            'label' => 'Stable wallet identity claim',
            'description' => 'Used only when the verified credential has no JWT sub claim. Configure a stable issuer-scoped identifier, for example personal_administrative_number for a PID when the issuer provides it. Do not use mutable profile data such as name, address or birth date.',
            'displayCond' => 'FIELD:mode:=:login',
            'config' => ['type' => 'input', 'max' => 255],
        ],
        'match_claim' => [
            'label' => 'Fallback account matching claim',
            'description' => 'Optional. Used only when no wallet identity link exists, for example email.',
            'displayCond' => 'FIELD:mode:=:login',
            'config' => ['type' => 'input', 'max' => 255],
        ],
        'match_field' => [
            'label' => 'Fallback fe_users matching field',
            'displayCond' => 'FIELD:mode:=:login',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [['label' => '(none)', 'value' => '']],
                'itemsProcFunc' => 'T3Hub\\EudiWalletIntegration\\Tca\\FeUserFieldItems->addItems',
            ],
        ],
        'username_claim' => [
            'label' => 'Username claim for new accounts',
            'description' => 'Optional. If empty, a deterministic eudi_<hash> username is generated.',
            'displayCond' => 'FIELD:mode:=:login',
            'config' => ['type' => 'input', 'max' => 255],
        ],
        'store_verification_result' => [
            'label' => 'Store verified claims/results for auditing',
            'description' => 'Optional audit persistence. In claims-only and age-over-18 modes fe_user_uid remains 0. Age mode requests only age_over_18. Enable only if your privacy/data-retention policy allows storing the verification result.',
            'config' => ['type' => 'check', 'renderType' => 'checkboxToggle', 'default' => 0],
        ],
    ],
];
