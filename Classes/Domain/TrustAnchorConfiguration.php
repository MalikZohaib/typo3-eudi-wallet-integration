<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Domain;

final readonly class TrustAnchorConfiguration
{
    /** @param list<string> $allowedAlgorithms */
    public function __construct(
        public string $issuer,
        public ?string $keyId,
        public ?string $publicKeyPath,
        public ?string $x509TrustAnchorPath,
        public array $allowedAlgorithms,
        public ?string $authorityKeyIdentifier = null,
    ) {
    }
}
