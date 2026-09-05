<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Service;

use Eudi\CredentialSdJwt\SdJwtCredentialHandler;
use Eudi\QrCode\QrCodeRendererInterface;
use Eudi\Trust\Domain\TrustAnchor;
use Eudi\Trust\StaticTrustResolver;
use Eudi\VerifierCore\Application\CredentialHandlerRegistry;
use Eudi\VerifierCore\Application\VerifierService;
use Eudi\VerifierCore\Authorization\HaipAuthorizationRequestParameters;
use Eudi\VerifierCore\Config\VerifierConfig;
use Eudi\VerifierCore\Infrastructure\SecureRandomValueGenerator;
use Eudi\VerifierCore\Infrastructure\SystemClock;
use Eudi\VerifierCore\Jose\EphemeralResponseKeyGenerator;
use Eudi\VerifierCore\OpenId4Vp\AuthorizationRequestFactory;
use Eudi\VerifierCore\Response\DirectPostJwtProtectedHeader;
use Eudi\VerifierCore\Response\DirectPostJwtResponseParser;
use Eudi\VerifierCore\Security\X509RequestObjectSigner;
use Psr\Http\Message\ServerRequestInterface;
use T3Hub\EudiWalletIntegration\Configuration\ExtensionSettings;
use T3Hub\EudiWalletIntegration\Domain\Configuration;
use T3Hub\EudiWalletIntegration\Http\Typo3StatusListTokenFetcher;
use T3Hub\EudiWalletIntegration\Infrastructure\DatabaseSessionStore;
use T3Hub\EudiWalletIntegration\Security\FilePathResolver;
use T3Hub\EudiWalletIntegration\Security\RelyingPartyCredentialLoader;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Site\Entity\Site;

final readonly class VerifierFactory
{
    public function __construct(
        private ExtensionSettings $settings,
        private DatabaseSessionStore $sessionStore,
        private RelyingPartyCredentialLoader $credentialLoader,
        private FilePathResolver $filePathResolver,
        private QrCodeRendererInterface $qrCodeRenderer,
        private RequestFactory $requestFactory,
    ) {
    }

    public function create(Configuration $configuration, ServerRequestInterface $request): VerifierService
    {
        $responseMode = $this->settings->string('responseMode', 'direct_post.jwt');
        $profile = $this->settings->string('profile', 'haip_1_0');
        $isHaip10 = in_array(strtolower($profile), ['haip_1_0', 'haip-1.0', 'haip1.0', 'haip'], true);

        $trustAnchors = [];
        foreach ($configuration->trustAnchors as $anchor) {
            $publicKeyPem = $anchor->publicKeyPath !== null
                ? $this->filePathResolver->readPrivateFile($anchor->publicKeyPath)
                : null;
            $x509TrustAnchorCertificatePem = $anchor->x509TrustAnchorPath !== null
                ? $this->filePathResolver->readPrivateFile($anchor->x509TrustAnchorPath)
                : null;

            if ($isHaip10 && $x509TrustAnchorCertificatePem === null) {
                throw new \RuntimeException(sprintf(
                    'HAIP issuer "%s" requires an X.509 trust-anchor certificate path.',
                    $anchor->issuer,
                ));
            }

            $trustAnchors[] = new TrustAnchor(
                issuer: $anchor->issuer,
                publicKeyPem: $publicKeyPem,
                x509TrustAnchorCertificatePem: $x509TrustAnchorCertificatePem,
                keyId: $anchor->keyId,
                allowedAlgorithms: $anchor->allowedAlgorithms,
            );
        }
        $trustResolver = new StaticTrustResolver($trustAnchors);
        $handler = new SdJwtCredentialHandler(
            trustResolver: $trustResolver,
            requireX509IssuerChain: $isHaip10,
            statusListTokenFetcher: new Typo3StatusListTokenFetcher($this->requestFactory),
        );

        $verifierConfig = new VerifierConfig(
            baseUrl: $this->baseUrl($request),
            clientId: $this->credentialLoader->clientId(),
            walletAuthorizationEndpoint: $this->settings->string('walletAuthorizationEndpoint', 'haip-vp://'),
            responseMode: $responseMode,
            profile: $profile,
            requestUriMethod: $this->settings->string('requestUriMethod', 'get'),
            sessionTtlSeconds: max(30, $this->settings->int('sessionTtlSeconds', 300)),
        );

        $protectedHeader = new DirectPostJwtProtectedHeader();

        return new VerifierService(
            config: $verifierConfig,
            sessionStore: $this->sessionStore,
            credentialHandlers: new CredentialHandlerRegistry([$handler]),
            requestObjectSigner: new X509RequestObjectSigner(
                privateKeyPem: $this->credentialLoader->privateKeyPem(),
                certificateChainPem: $this->credentialLoader->certificateChainPem(),
                algorithm: $this->credentialLoader->signingAlgorithm(),
            ),
            clock: new SystemClock(),
            random: new SecureRandomValueGenerator(),
            authorizationRequestFactory: new AuthorizationRequestFactory(
                $verifierConfig,
                new HaipAuthorizationRequestParameters(),
            ),
            responseKeyGenerator: new EphemeralResponseKeyGenerator(),
            directPostJwtProtectedHeader: $protectedHeader,
            directPostJwtResponseParser: new DirectPostJwtResponseParser($protectedHeader),
        );
    }

    public function qrCodeDataUri(string $payload): string
    {
        return $this->qrCodeRenderer->renderDataUri($payload);
    }

    private function baseUrl(ServerRequestInterface $request): string
    {
        $configured = rtrim($this->settings->string('publicBaseUrl'), '/');
        if ($configured !== '') {
            if (!str_starts_with($configured, 'https://')) {
                throw new \RuntimeException('EUDI publicBaseUrl must use HTTPS for real-wallet communication.');
            }
            return $configured;
        }
        $site = $request->getAttribute('site');
        if ($site instanceof Site) {
            return rtrim((string)$site->getBase(), '/');
        }
        $uri = $request->getUri();
        return rtrim($uri->getScheme() . '://' . $uri->getAuthority(), '/');
    }
}
