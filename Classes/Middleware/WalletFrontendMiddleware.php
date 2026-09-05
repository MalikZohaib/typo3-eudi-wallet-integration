<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Middleware;

use Eudi\VerifierCore\Domain\SessionStatus;
use Eudi\VerifierCore\Domain\VerificationSession;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use T3Hub\EudiWalletIntegration\Service\ClaimProviderInterface;
use T3Hub\EudiWalletIntegration\Domain\Configuration;
use T3Hub\EudiWalletIntegration\Http\FrontendEndpointUrlBuilder;
use T3Hub\EudiWalletIntegration\Repository\ConfigurationRepository;
use T3Hub\EudiWalletIntegration\Repository\SessionMetadataRepository;
use T3Hub\EudiWalletIntegration\Security\BrowserTokenService;
use T3Hub\EudiWalletIntegration\Security\ReturnUrlValidator;
use T3Hub\EudiWalletIntegration\Security\SameDeviceResponseCodeService;
use T3Hub\EudiWalletIntegration\Service\AgeOver18VerificationService;
use T3Hub\EudiWalletIntegration\Service\PresentationRequestFactory;
use T3Hub\EudiWalletIntegration\Service\VerificationAuditService;
use T3Hub\EudiWalletIntegration\Service\VerifierFactory;
use T3Hub\EudiWalletIntegration\View\WalletViewRenderer;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\SetCookieBehavior;
use TYPO3\CMS\Core\Http\SetCookieService;
use TYPO3\CMS\Core\Session\UserSessionManager;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final readonly class WalletFrontendMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private FrontendEndpointUrlBuilder $endpointUrlBuilder,
        private ConfigurationRepository $configurationRepository,
        private SessionMetadataRepository $sessionMetadataRepository,
        private PresentationRequestFactory $presentationRequestFactory,
        private VerifierFactory $verifierFactory,
        private BrowserTokenService $browserTokenService,
        private SameDeviceResponseCodeService $sameDeviceResponseCodeService,
        private ReturnUrlValidator $returnUrlValidator,
        private VerificationAuditService $auditService,
        private ClaimProviderInterface $claimProvider,
        private WalletViewRenderer $viewRenderer,
        private LoggerInterface $logger,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if ($this->endpointUrlBuilder->matches($path, '/eudi-wallet/start')) {
            return $this->start($request);
        }
        if ($this->endpointUrlBuilder->matches($path, '/eudi-wallet/scan')) {
            return $this->scan($request);
        }
        if ($this->endpointUrlBuilder->matches($path, '/eudi-wallet/open')) {
            return $this->openWallet($request);
        }
        if ($this->endpointUrlBuilder->matches($path, '/eudi-wallet/redirect')) {
            return $this->sameDeviceRedirect($request);
        }
        if ($this->endpointUrlBuilder->matches($path, '/eudi-wallet/status')) {
            return $this->status($request);
        }
        if ($this->endpointUrlBuilder->matches($path, '/eudi-wallet/finish')) {
            return $this->finish($request);
        }
        if ($this->endpointUrlBuilder->matches($path,'/eudi-wallet/claims')) {
            return $this->claims($request);
        }
        return $handler->handle($request);
    }

    private function start(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $configurationUid = (int)($request->getQueryParams()['configuration'] ?? 0);
            $configuration = $this->configurationRepository->get($configurationUid);
            $verifier = $this->verifierFactory->create($configuration, $request);
            $start = $verifier->start($this->presentationRequestFactory->create($configuration));
            $browserToken = $this->browserTokenService->create();
            $returnUrl = $this->returnUrlValidator->validate(
                is_string($request->getQueryParams()['return_url'] ?? null) ? $request->getQueryParams()['return_url'] : null,
                $request,
            );

            $returnMode = is_string(
                $request->getQueryParams()['return_mode'] ?? null
            )
                ? $request->getQueryParams()['return_mode']
                : 'page';

            if (!in_array($returnMode, ['page', 'claims'], true)) {
                throw new \RuntimeException('Invalid EUDI return mode.');
            }

            $this->sessionMetadataRepository->attachBrowserContext(
                $start->session->id,
                $configuration->uid,
                $this->browserTokenService->hash($browserToken),
                $returnUrl,
                $returnMode
            );
            $response = new RedirectResponse($this->endpointUrlBuilder->url($request, '/eudi-wallet/scan', [
                'session' => $start->session->id,
                'token' => $browserToken,
            ]), 303);
            $maxAge = max(30, $start->session->expiresAt->getTimestamp() - time());
            return $this->withBrowserBindingCookie($response, $browserToken, $request, $maxAge);
        } catch (\Throwable $exception) {
            $this->logger->error('Unable to start EUDI verification: {message}', ['message' => $exception->getMessage()]);
            return $this->renderError($request, 'Unable to start wallet verification.', 400);
        }
    }

    private function scan(ServerRequestInterface $request): ResponseInterface
    {
        try {
            [$sessionId, $token, $metadata] = $this->requireBrowserContext($request);
            $configuration = $this->configurationRepository->get((int)$metadata['configuration_uid']);
            $verifier = $this->verifierFactory->create($configuration, $request);
            $session = $verifier->getSession($sessionId);
            if ($session->status !== SessionStatus::Pending) {
                return new RedirectResponse($this->endpointUrlBuilder->url($request, '/eudi-wallet/finish', ['session' => $sessionId, 'token' => $token]), 303);
            }
            $authorizationUri = $verifier->getAuthorizationRequestUri($sessionId);
            $html = $this->viewRenderer->render('Wallet/Scan', $request, [
                'configuration' => $configuration,
                'requestedClaims' => $configuration->requestedClaims(),
                'mode' => $configuration->mode->value,
                'ageOver18Mode' => $configuration->isAgeOver18Mode(),
                'session' => $session,
                'qrCodeDataUri' => $this->verifierFactory->qrCodeDataUri($authorizationUri),
                'authorizationRequestUri' => $authorizationUri,
                'openWalletUrl' => $this->endpointUrlBuilder->url($request, '/eudi-wallet/open', ['session' => $sessionId, 'token' => $token]),
                'statusUrl' => $this->endpointUrlBuilder->url($request, '/eudi-wallet/status', ['session' => $sessionId, 'token' => $token]),
                'finishUrl' => $this->endpointUrlBuilder->url($request, '/eudi-wallet/finish', ['session' => $sessionId, 'token' => $token]),
            ]);
            return $this->html($html, 200);
        } catch (\Throwable $exception) {
            $this->logger->warning('EUDI scan screen failed: {message}', ['message' => $exception->getMessage()]);
            return $this->renderError($request, 'This wallet verification request is invalid or expired.', 400);
        }
    }

    private function openWallet(ServerRequestInterface $request): ResponseInterface
    {
        try {
            [$sessionId, , $metadata] = $this->requireBrowserContext($request);
            $configuration = $this->configurationRepository->get((int)$metadata['configuration_uid']);
            $verifier = $this->verifierFactory->create($configuration, $request);
            $session = $verifier->getSession($sessionId);
            if ($session->status !== SessionStatus::Pending) {
                throw new \RuntimeException('The EUDI verification session is no longer pending.');
            }

            // Mark only explicit "Open wallet on this device" invocations as same-device.
            // QR-code scans remain cross-device and therefore do not request a redirect_uri.
            $this->sessionMetadataRepository->markSameDevice($sessionId);

            return new RedirectResponse($verifier->getAuthorizationRequestUri($sessionId), 303);
        } catch (\Throwable $exception) {
            $this->logger->warning('Unable to open EUDI Wallet on this device: {message}', ['message' => $exception->getMessage()]);
            return $this->renderError($request, 'Unable to open the wallet request.', 400);
        }
    }

    private function sameDeviceRedirect(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $sessionId = is_string($request->getQueryParams()['session'] ?? null)
                ? $request->getQueryParams()['session']
                : '';
            $responseCode = is_string($request->getQueryParams()['response_code'] ?? null)
                ? $request->getQueryParams()['response_code']
                : '';
            if ($sessionId === '' || $responseCode === '') {
                throw new \RuntimeException('Missing same-device EUDI redirect parameters.');
            }

            $metadata = $this->sessionMetadataRepository->get($sessionId);
            if ($metadata === null || !(bool)($metadata['same_device'] ?? false)) {
                throw new \RuntimeException('This EUDI verification was not initiated as a same-device flow.');
            }
            if ((int)$metadata['expires_at'] <= time()) {
                throw new \RuntimeException('The EUDI verification session has expired.');
            }

            // HAIP requires the redirect to return in the same user session that
            // initiated the request. The browser-binding cookie is set at start()
            // and is deliberately not sent to the Wallet's back-channel POST.
            $browserToken = $this->browserBindingToken($request);
            if (!$this->browserTokenService->verify($browserToken, (string)$metadata['browser_token_hash'])) {
                throw new \RuntimeException('Same-device EUDI redirect arrived in a different browser session.');
            }

            if (!$this->sessionMetadataRepository->confirmSameDevice(
                $sessionId,
                $this->sameDeviceResponseCodeService->hash($responseCode),
            )) {
                throw new \RuntimeException('The same-device EUDI response code is invalid or has already been consumed.');
            }

            return new RedirectResponse($this->endpointUrlBuilder->url($request, '/eudi-wallet/finish', [
                'session' => $sessionId,
            ]), 303);
        } catch (\Throwable $exception) {
            $this->logger->warning('EUDI same-device redirect validation failed: {message}', ['message' => $exception->getMessage()]);
            return $this->renderError($request, 'Wallet verification could not be bound to this browser session.', 400);
        }
    }

    private function status(ServerRequestInterface $request): ResponseInterface
    {
        try {
            [$sessionId, , $metadata] = $this->requireBrowserContext($request);
            $configuration = $this->configurationRepository->get((int)$metadata['configuration_uid']);
            $session = $this->verifierFactory->create($configuration, $request)->getSession($sessionId);
            if ((bool)($metadata['same_device'] ?? false) && !(bool)($metadata['same_device_confirmed'] ?? false)) {
                return $this->json(['status' => SessionStatus::Pending->value], 200)->withHeader('Cache-Control', 'no-store');
            }
            $payload = ['status' => $session->status->value];
            if ($session->status === SessionStatus::Verified) {
                $payload['verified'] = true;
            } elseif ($session->status === SessionStatus::Rejected) {
                $payload['rejected'] = true;
            }
            return $this->json($payload, 200)->withHeader('Cache-Control', 'no-store');
        } catch (\Throwable) {
            return $this->json(['status' => 'invalid'], 400)->withHeader('Cache-Control', 'no-store');
        }
    }

    private function finish(ServerRequestInterface $request): ResponseInterface
    {
        try {
            [$sessionId, , $metadata] = $this->requireBrowserContext($request);
            $configuration = $this->configurationRepository->get((int)$metadata['configuration_uid']);
            $session = $this->verifierFactory->create($configuration, $request)->getSession($sessionId);

            if ((bool)($metadata['same_device'] ?? false) && !(bool)($metadata['same_device_confirmed'] ?? false)) {
                throw new \RuntimeException('The same-device wallet redirect has not been confirmed in this browser session.');
            }

            if ((bool)$metadata['browser_consumed']) {
                if ($configuration->isClaimsOnlyMode() && $session->status === SessionStatus::Verified && $session->result !== null) {
                    return $this->renderClaimsOnlyResult($request, $metadata, $configuration, $session);
                }
                if ($configuration->isAgeOver18Mode() && $session->status === SessionStatus::Verified && $session->result !== null) {
                    return $this->redirectAgeOver18Result((string)$metadata['return_url'], $sessionId);
                }
                throw new \RuntimeException('This browser verification has already been consumed.');
            }
            if ($session->status !== SessionStatus::Verified || $session->result === null) {
                if ($session->status === SessionStatus::Pending) {
                    return new RedirectResponse($this->endpointUrlBuilder->url($request, '/eudi-wallet/scan', [
                        'session' => $sessionId,
                        'token' => (string)($request->getQueryParams()['token'] ?? ''),
                    ]), 303);
                }
                throw new \RuntimeException('Wallet verification was not successful.');
            }

            if ($configuration->isClaimsOnlyMode()) {
                return $this->finishClaimsOnly($request, $sessionId, $metadata, $configuration, $session);
            }
            if ($configuration->isAgeOver18Mode()) {
                return $this->finishAgeOver18($sessionId, $metadata, $configuration, $session);
            }

        } catch (\Throwable $exception) {
            $this->logger->warning('EUDI wallet completion failed: {message}', ['message' => $exception->getMessage()]);
            return $this->renderError($request, 'Wallet verification could not be completed.', 400);
        }
    }

    /** @param array<string,mixed> $metadata */
    private function finishAgeOver18(
        string $sessionId,
        array $metadata,
        Configuration $configuration,
        VerificationSession $session,
    ): ResponseInterface {
        $claims = $session->result?->claims() ?? [];
        if (!array_key_exists('age_over_18', $claims) || !is_bool($claims['age_over_18'])) {
            throw new \RuntimeException('The verified credential does not contain a boolean age_over_18 claim.');
        }

        if (!$this->sessionMetadataRepository->consumeBrowserToken($sessionId)) {
            throw new \RuntimeException('Wallet browser completion was already consumed concurrently.');
        }

        try {
            $this->auditService->store($configuration, $session, []);
        } catch (\Throwable $auditException) {
            $this->logger->error('EUDI age-over-18 audit persistence failed: {message}', ['message' => $auditException->getMessage()]);
        }

        // Do not create an age-result cookie here. Return only an opaque session
        // reference to the originating extension. The consuming extension can
        // resolve it through AgeOver18VerificationInterface and choose its own
        // cookie/storage policy.
        return $this->redirectAgeOver18Result((string)$metadata['return_url'], $sessionId);
    }

    private function redirectAgeOver18Result(string $returnUrl, string $sessionId): ResponseInterface
    {
        return new RedirectResponse($this->appendQueryParameter(
            $returnUrl,
            AgeOver18VerificationService::CALLBACK_PARAMETER,
            $sessionId,
        ), 303);
    }

    private function appendQueryParameter(string $url, string $name, string $value): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new \RuntimeException('Unable to build EUDI age-verification return URL.');
        }

        $query = [];
        if (isset($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $query);
        }
        $query[$name] = $value;

        $authority = $parts['scheme'] . '://';
        if (isset($parts['user'])) {
            $authority .= $parts['user'];
            if (isset($parts['pass'])) {
                $authority .= ':' . $parts['pass'];
            }
            $authority .= '@';
        }
        $authority .= $parts['host'];
        if (isset($parts['port'])) {
            $authority .= ':' . $parts['port'];
        }

        $rebuilt = $authority . ($parts['path'] ?? '/');
        $encodedQuery = http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        if ($encodedQuery !== '') {
            $rebuilt .= '?' . $encodedQuery;
        }
        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $rebuilt .= '#' . $parts['fragment'];
        }
        return $rebuilt;
    }

    /** @param array<string,mixed> $metadata */
    private function finishClaimsOnly(
        ServerRequestInterface $request,
        string $sessionId,
        array $metadata,
        Configuration $configuration,
        VerificationSession $session,
    ): ResponseInterface {
        if (!$this->sessionMetadataRepository->consumeBrowserToken($sessionId)) {
            throw new \RuntimeException('Wallet browser completion was already consumed concurrently.');
        }

        try {
            $this->auditService->store($configuration, $session, []);
        } catch (\Throwable $auditException) {
            $this->logger->error('EUDI claims-only audit persistence failed: {message}', ['message' => $auditException->getMessage()]);
        }

        if (($metadata['return_mode'] ?? 'page') === 'claims') {
            return new RedirectResponse(
                $this->appendQueryParameter(
                    (string)$metadata['return_url'],
                    'eudi_session',
                    $sessionId
                ),
                303
            );
        }

        return $this->renderClaimsOnlyResult($request, $metadata, $configuration, $session);
    }

    /** @param array<string,mixed> $metadata */
    private function renderClaimsOnlyResult(
        ServerRequestInterface $request,
        array $metadata,
        Configuration $configuration,
        VerificationSession $session,
    ): ResponseInterface {
        $credential = $session->result?->credentials[0] ?? null;
        $html = $this->viewRenderer->render('Wallet/Result', $request, [
            'configuration' => $configuration,
            'mode' => $configuration->mode->value,
            'claims' => $session->result?->claims() ?? [],
            'claimRows' => $this->toDisplayClaimRows($session->result?->claims() ?? []),
            'credentials' => $session->result?->credentials ?? [],
            'credential' => $credential,
            'verifiedAt' => $session->result?->verifiedAt,
            'returnUrl' => (string)$metadata['return_url'],
        ]);

        return $this->html($html, 200);
    }

    /**
     * @param array<string,mixed> $claims
     * @return list<array{name:string,value:string}>
     */
    private function toDisplayClaimRows(array $claims): array
    {
        $rows = [];
        foreach ($claims as $name => $value) {
            if (is_bool($value)) {
                $displayValue = $value ? 'true' : 'false';
            } elseif ($value === null) {
                $displayValue = 'null';
            } elseif (is_scalar($value)) {
                $displayValue = (string)$value;
            } else {
                $displayValue = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
            $rows[] = ['name' => (string)$name, 'value' => $displayValue];
        }
        return $rows;
    }

    private function claims(
    ServerRequestInterface $request
    ): ResponseInterface {
        try {
            $sessionId = $request->getQueryParams()['session_id'] ?? '';

            if (!is_string($sessionId) || trim($sessionId) === '') {
                throw new \RuntimeException(
                    'Missing EUDI verification session.'
                );
            }

            $claims = $this->claimProvider->getClaims(
                $request,
                $sessionId
            );

            return $this->json([
                'success' => true,
                'claims' => $claims,
            ], 200)
                ->withHeader('Cache-Control', 'no-store');

        } catch (\Throwable $exception) {
            $this->logger->warning(
                'EUDI claims retrieval failed: {message}',
                [
                    'message' => $exception->getMessage(),
                ]
            );

            return $this->json([
                'success' => false,
            ], 400)
                ->withHeader('Cache-Control', 'no-store');
        }
    }

    /** @return array{0:string,1:string,2:array<string,mixed>} */
    private function requireBrowserContext(ServerRequestInterface $request): array
    {
        $sessionId = is_string($request->getQueryParams()['session'] ?? null) ? $request->getQueryParams()['session'] : '';
        $token = is_string($request->getQueryParams()['token'] ?? null) ? $request->getQueryParams()['token'] : '';
        if ($token === '') {
            $token = $this->browserBindingToken($request);
        }
        if ($sessionId === '' || $token === '') {
            throw new \RuntimeException('Missing EUDI browser session/token.');
        }
        $metadata = $this->sessionMetadataRepository->get($sessionId);
        if ($metadata === null || !$this->browserTokenService->verify($token, (string)$metadata['browser_token_hash'])) {
            throw new \RuntimeException('Invalid EUDI browser token.');
        }
        if ((int)$metadata['expires_at'] <= time()) {
            throw new \RuntimeException('EUDI browser verification session has expired.');
        }
        return [$sessionId, $token, $metadata];
    }

    private function browserBindingToken(ServerRequestInterface $request): string
    {
        $token = $request->getCookieParams()['eudi_wallet_integration_binding'] ?? null;
        return is_string($token) ? $token : '';
    }

    private function withBrowserBindingCookie(
        ResponseInterface $response,
        string $browserToken,
        ServerRequestInterface $request,
        int $maxAge,
    ): ResponseInterface {
        $cookie = 'eudi_wallet_integration_binding=' . $browserToken
            . '; Path=/; Max-Age=' . max(1, $maxAge) . '; HttpOnly; SameSite=Lax';
        if (strtolower($request->getUri()->getScheme()) === 'https') {
            $cookie .= '; Secure';
        }
        return $response->withAddedHeader('Set-Cookie', $cookie);
    }

    private function renderError(ServerRequestInterface $request, string $message, int $status): ResponseInterface
    {
        return $this->html($this->viewRenderer->render('Wallet/Error', $request, ['message' => $message]), $status);
    }

    private function html(string $body, int $status): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store, max-age=0')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('X-Content-Type-Options', 'nosniff');
        $response->getBody()->write($body);
        return $response;
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload, int $status): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status)->withHeader('Content-Type', 'application/json; charset=utf-8');
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));
        return $response;
    }
}
