<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Service;

use Eudi\VerifierCore\Dcql\CredentialQuery;
use Eudi\VerifierCore\Dcql\DcqlQuery;
use Eudi\VerifierCore\Domain\PresentationRequest;
use T3Hub\EudiWalletIntegration\Domain\Configuration;

final readonly class PresentationRequestFactory
{
    public function create(Configuration $configuration): PresentationRequest
    {
        $claims = $configuration->requestedClaims();
        if ($claims === []) {
            throw new \RuntimeException('EUDI configuration must contain at least one requested claim.');
        }
        if ($configuration->vct === '') {
            throw new \RuntimeException('EUDI configuration requires an SD-JWT VCT.');
        }

        $akis = [];
        foreach ($configuration->trustAnchors as $anchor) {
            if (is_string($anchor->authorityKeyIdentifier) && $anchor->authorityKeyIdentifier !== '') {
                if (preg_match('/^[A-Za-z0-9_-]+$/', $anchor->authorityKeyIdentifier) !== 1) {
                    throw new \RuntimeException('HAIP authority key identifiers must be base64url encoded.');
                }
                $akis[] = $anchor->authorityKeyIdentifier;
            }
        }
        $trustedAuthorities = $akis === [] ? [] : [[
            'type' => 'aki',
            'values' => array_values(array_unique($akis)),
        ]];

        $credential = new CredentialQuery(
            id: $configuration->credentialQueryId,
            format: 'dc+sd-jwt',
            meta: ['vct_values' => [$configuration->vct]],
            claims: array_map(
                static fn (string $claim): \Eudi\VerifierCore\Dcql\ClaimQuery => new \Eudi\VerifierCore\Dcql\ClaimQuery([$claim]),
                $claims,
            ),
            requireCryptographicHolderBinding: $configuration->requireHolderBinding,
            trustedAuthorities: $trustedAuthorities,
        );

        return new PresentationRequest(new DcqlQuery([$credential]), $configuration->purpose);
    }
}
