<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Service;

use Eudi\VerifierCore\Domain\SessionStatus;
use Psr\Http\Message\ServerRequestInterface;
use T3Hub\EudiWalletIntegration\Service\ClaimProviderInterface;
use T3Hub\EudiWalletIntegration\Domain\Repository\ConfigurationRepository;
use T3Hub\EudiWalletIntegration\Domain\Repository\SessionMetadataRepository;
use T3Hub\EudiWalletIntegration\Security\BrowserTokenService;

final readonly class WalletClaimService implements ClaimProviderInterface
{
    public function __construct(
        private SessionMetadataRepository $sessionMetadataRepository,
        private ConfigurationRepository $configurationRepository,
        private BrowserTokenService $browserTokenService,
        private VerifierFactory $verifierFactory,
    ) {
    }

    public function getClaims(
        ServerRequestInterface $request,
        string $sessionId
    ): array {
        $sessionId = trim($sessionId);

        if ($sessionId === '') {
            throw new \InvalidArgumentException(
                'EUDI session ID must not be empty.'
            );
        }

        $metadata = $this->sessionMetadataRepository->get($sessionId);

        if ($metadata === null) {
            throw new \RuntimeException(
                'Unknown EUDI verification session.'
            );
        }

        if ((int)($metadata['expires_at'] ?? 0) <= time()) {
            throw new \RuntimeException(
                'The EUDI verification session has expired.'
            );
        }

        /*
         * Bind the claims request to the same browser that started
         * the EUDI verification.
         */
        $browserToken = $request->getCookieParams()['eudi_wallet_integration_binding'] ?? null;

        if (
            !is_string($browserToken)
            || !$this->browserTokenService->verify(
                $browserToken,
                (string)($metadata['browser_token_hash'] ?? '')
            )
        ) {
            throw new \RuntimeException(
                'The EUDI verification result belongs to a different browser session.'
            );
        }

        $configurationUid = (int)($metadata['configuration_uid'] ?? 0);

        if ($configurationUid <= 0) {
            throw new \RuntimeException(
                'The EUDI verification session has no configuration.'
            );
        }

        $configuration = $this->configurationRepository->get(
            $configurationUid
        );

        if (!$configuration->isClaimsOnlyMode()) {
            throw new \RuntimeException(
                'The EUDI session is not configured for claims-only verification.'
            );
        }

        $session = $this->verifierFactory
            ->create($configuration, $request)
            ->getSession($sessionId);

        if (
            $session->status !== SessionStatus::Verified
            || $session->result === null
        ) {
            throw new \RuntimeException(
                'The EUDI verification session is not verified.'
            );
        }

        return $session->result->claims();
    }

    public function hasVerifiedClaims(
        ServerRequestInterface $request,
        string $sessionId
    ): bool {
        try {
            return $this->getClaims($request, $sessionId) !== [];
        } catch (\Throwable) {
            return false;
        }
    }
}