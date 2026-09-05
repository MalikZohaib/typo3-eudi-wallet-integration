<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Domain;

final readonly class ClaimMapping
{
    public function __construct(
        public string $claimName,
        public bool $required,
    ) {
    }
}
