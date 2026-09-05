<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Domain;

final readonly class Configuration
{
    /**
     * @param list<ClaimMapping> $claimMappings
     * @param list<TrustAnchorConfiguration> $trustAnchors
     */
    public function __construct(
        public int $uid,
        public string $title,
        public string $identifier,
        public VerificationMode $mode,
        public string $credentialQueryId,
        public string $vct,
        public ?string $purpose,
        public bool $requireHolderBinding,
        public bool $storeVerificationResult,
        public array $claimMappings,
        public array $trustAnchors,
    ) {
    }

    public function isClaimsOnlyMode(): bool
    {
        return $this->mode === VerificationMode::ClaimsOnly;
    }

    public function isAgeOver18Mode(): bool
    {
        return $this->mode === VerificationMode::AgeOver18;
    }

    /** @return list<string> */
    public function requestedClaims(): array
    {
        // The age_over_18 mode is intentionally fixed to the minimum-disclosure
        // boolean claim. Claim-mapping rows cannot make this mode request name,
        // birth date, personal identifiers, or other unnecessary PID data.
        if ($this->isAgeOver18Mode()) {
            return ['age_over_18'];
        }

        $claims = [];
        foreach ($this->claimMappings as $mapping) {
            if ($mapping->claimName !== '') {
                $claims[] = $mapping->claimName;
            }
        }

        return array_values(array_unique($claims));
    }
}
