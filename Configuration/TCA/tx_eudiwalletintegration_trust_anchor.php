<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'EUDI issuer trust anchor',
        'label' => 'issuer',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'delete' => 'deleted',
        'enablecolumns' => ['disabled' => 'hidden'],
        'hideTable' => true,
        'sortby' => 'sorting',
    ],
    'types' => [
        '1' => ['showitem' => 'issuer, key_id, authority_key_identifier, public_key_path, x509_trust_anchor_path, allowed_algorithms, hidden'],
    ],
    'columns' => [
        'hidden' => [
            'label' => 'Disabled',
            'config' => ['type' => 'check', 'renderType' => 'checkboxToggle'],
        ],
        'configuration' => [
            'config' => ['type' => 'passthrough'],
        ],
        'issuer' => [
            'label' => 'Issuer identifier (iss)',
            'config' => ['type' => 'input', 'required' => true, 'max' => 500],
        ],
        'key_id' => [
            'label' => 'Key ID (kid)',
            'description' => 'Optional. Leave empty to accept the configured key for the issuer regardless of kid.',
            'config' => ['type' => 'input', 'max' => 255],
        ],
        'authority_key_identifier' => [
            'label' => 'Authority Key Identifier (AKI)',
            'description' => 'Optional HAIP 1.0 DCQL trusted_authorities value. Enter the RFC 5280 AuthorityKeyIdentifier KeyIdentifier bytes encoded as base64url.',
            'config' => ['type' => 'input', 'max' => 255],
        ],
        'public_key_path' => [
            'label' => 'Issuer public key PEM path',
            'description' => 'Optional legacy/direct-key trust. Absolute or EXT: path to a PUBLIC KEY PEM. HAIP 1.0 X.509 mode uses the separate trust-anchor certificate field below.',
            'config' => ['type' => 'input', 'max' => 1024],
        ],
        'x509_trust_anchor_path' => [
            'label' => 'X.509 trust anchor certificate PEM path',
            'description' => 'Required for HAIP 1.0 SD-JWT VC verification. Absolute or EXT: path to the trusted CA/root CERTIFICATE PEM. The credential x5c chain must chain to this certificate and must not include the trust anchor itself.',
            'config' => ['type' => 'input', 'max' => 1024],
        ],
        'allowed_algorithms' => [
            'label' => 'Allowed JWS algorithms',
            'description' => 'Comma-separated allow-list: ES256 and/or RS256.',
            'config' => ['type' => 'input', 'required' => true, 'default' => 'ES256', 'max' => 255],
        ],
    ],
];
