<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Service;

use Psr\Http\Message\ServerRequestInterface;

interface ClaimProviderInterface
{
    /**
     * Resolve verified claims for an EUDI session belonging to this browser.
     *
     * @return array<string, mixed>
     */
    public function getClaims(
        ServerRequestInterface $request,
        string $sessionId
    ): array;

    public function hasVerifiedClaims(
        ServerRequestInterface $request,
        string $sessionId
    ): bool;
}