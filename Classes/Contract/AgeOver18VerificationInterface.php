<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Contract;

use Psr\Http\Message\ServerRequestInterface;
use T3Hub\EudiWalletIntegration\Domain\AgeOver18VerificationResult;

/**
 * Public integration API for TYPO3 extensions that want to consume an
 * age-over-18 wallet verification without coupling to the EUDI internals.
 *
 * The EUDI Wallet extension deliberately does not persist an age cookie.
 * A consuming extension can call resolveFromRequest() after the browser is
 * redirected back to its return URL and decide how/if it wants to cache the
 * result (for example in its own signed cookie).
 */
interface AgeOver18VerificationInterface
{
    /**
     * Build the URL that starts an age_over_18 wallet verification.
     *
     * The referenced configuration must use VerificationMode::AgeOver18.
     */
    public function startUrl(
        ServerRequestInterface $request,
        int $configurationUid,
        string $returnUrl,
    ): string;

    /**
     * Resolve the completed age verification attached to the current request.
     *
     * Returns null when the request is not an age-verification callback.
     * Throws when a callback reference is present but is invalid, expired,
     * belongs to another browser, or is not a verified age_over_18 session.
     */
    public function resolveFromRequest(ServerRequestInterface $request): ?AgeOver18VerificationResult;
}
