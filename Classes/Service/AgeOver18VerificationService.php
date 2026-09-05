<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Service;

use Eudi\VerifierCore\Domain\SessionStatus;
use Psr\Http\Message\ServerRequestInterface;
use T3Hub\EudiWalletIntegration\Contract\AgeOver18VerificationInterface;
use T3Hub\EudiWalletIntegration\Domain\AgeOver18VerificationResult;
use T3Hub\EudiWalletIntegration\Http\FrontendEndpointUrlBuilder;
use T3Hub\EudiWalletIntegration\Repository\ConfigurationRepository;
use T3Hub\EudiWalletIntegration\Repository\SessionMetadataRepository;
use T3Hub\EudiWalletIntegration\Security\BrowserTokenService;
use T3Hub\EudiWalletIntegration\Security\ReturnUrlValidator;

final readonly class AgeOver18VerificationService implements AgeOver18VerificationInterface
{
    public const CALLBACK_PARAMETER = 'eudi_age_verification';

    public function __construct(
        private FrontendEndpointUrlBuilder $endpointUrlBuilder,
        private ConfigurationRepository $configurationRepository,
        private SessionMetadataRepository $sessionMetadataRepository,
        private BrowserTokenService $browserTokenService,
        private ReturnUrlValidator $returnUrlValidator,
        private VerifierFactory $verifierFactory,
    ) {
    }

    public function startUrl(
        ServerRequestInterface $request,
        int $configurationUid,
        string $returnUrl,
    ): string {
        $configuration = $this->configurationRepository->get($configurationUid);
        if (!$configuration->isAgeOver18Mode()) {
            throw new \InvalidArgumentException(
                'The selected EUDI configuration is not an age_over_18 verification configuration.'
            );
        }

        return $this->endpointUrlBuilder->url($request, '/eudi-wallet/start', [
            'configuration' => $configurationUid,
            'return_url' => $this->returnUrlValidator->validate($returnUrl, $request),
        ]);
    }

    public function resolveFromRequest(ServerRequestInterface $request): ?AgeOver18VerificationResult
    {
        $sessionId = $request->getQueryParams()[self::CALLBACK_PARAMETER] ?? null;
        if (!is_string($sessionId) || trim($sessionId) === '') {
            return null;
        }
        $sessionId = trim($sessionId);

        $metadata = $this->sessionMetadataRepository->get($sessionId);
        if ($metadata === null) {
            throw new \RuntimeException('Unknown EUDI age-verification session.');
        }
        if ((int)($metadata['expires_at'] ?? 0) <= time()) {
            throw new \RuntimeException('The EUDI age-verification session has expired.');
        }
        if (!(bool)($metadata['browser_consumed'] ?? false)) {
            throw new \RuntimeException('The EUDI age-verification result has not been completed by this browser.');
        }

        $browserToken = $request->getCookieParams()['eudi_wallet_integration_binding'] ?? null;
        if (!is_string($browserToken) || !$this->browserTokenService->verify(
            $browserToken,
            (string)($metadata['browser_token_hash'] ?? ''),
        )) {
            throw new \RuntimeException('The EUDI age-verification result belongs to a different browser session.');
        }

        $configurationUid = (int)($metadata['configuration_uid'] ?? 0);
        if ($configurationUid <= 0) {
            throw new \RuntimeException('The EUDI age-verification session has no configuration.');
        }
        $configuration = $this->configurationRepository->get($configurationUid);
        if (!$configuration->isAgeOver18Mode()) {
            throw new \RuntimeException('The EUDI verification result is not an age_over_18 result.');
        }

        $session = $this->verifierFactory->create($configuration, $request)->getSession($sessionId);
        if ($session->status !== SessionStatus::Verified || $session->result === null) {
            throw new \RuntimeException('The EUDI age-verification session is not verified.');
        }

        $claims = $session->result->claims();
        if (!array_key_exists('age_over_18', $claims) || !is_bool($claims['age_over_18'])) {
            throw new \RuntimeException('The verified credential does not contain a boolean age_over_18 claim.');
        }

        return new AgeOver18VerificationResult(
            sessionId: $sessionId,
            over18: $claims['age_over_18'],
            verifiedAt: $session->result->verifiedAt,
        );
    }
}
