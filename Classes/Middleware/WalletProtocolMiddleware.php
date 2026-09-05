<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Middleware;

use Eudi\VerifierCore\Response\DirectPostJwtProtectedHeader;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use T3Hub\EudiWalletIntegration\Http\FrontendEndpointUrlBuilder;
use T3Hub\EudiWalletIntegration\Domain\Repository\ConfigurationRepository;
use T3Hub\EudiWalletIntegration\Domain\Repository\SessionMetadataRepository;
use T3Hub\EudiWalletIntegration\Security\SameDeviceResponseCodeService;
use T3Hub\EudiWalletIntegration\Service\VerifierFactory;

final readonly class WalletProtocolMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private ConfigurationRepository $configurationRepository,
        private SessionMetadataRepository $sessionMetadataRepository,
        private FrontendEndpointUrlBuilder $endpointUrlBuilder,
        private SameDeviceResponseCodeService $responseCodeService,
        private VerifierFactory $verifierFactory,
        private LoggerInterface $logger,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (preg_match('#(?:^|/)wallet/request\.jwt/([^/]+)$#', $path, $matches) === 1) {
            return $this->requestObject($request, rawurldecode($matches[1]));
        }
        if ($path === '/wallet/direct_post' || str_ends_with($path, '/wallet/direct_post')) {
            return $this->directPost($request);
        }
        return $handler->handle($request);
    }

    private function requestObject(ServerRequestInterface $request, string $sessionId): ResponseInterface
    {
        try {
            $configuration = $this->configurationRepository->get(
                $this->sessionMetadataRepository->configurationUidForSession($sessionId)
            );
            $walletNonce = null;
            if (strtoupper($request->getMethod()) === 'POST') {
                $body = $request->getParsedBody();
                $walletNonce = is_array($body) && is_string($body['wallet_nonce'] ?? null)
                    ? $body['wallet_nonce']
                    : null;
            } elseif (strtoupper($request->getMethod()) !== 'GET') {
                return $this->text('Method not allowed', 405)->withHeader('Allow', 'GET, POST');
            }

            $jwt = $this->verifierFactory
                ->create($configuration, $request)
                ->getRequestObject($sessionId, $walletNonce);

            return $this->text($jwt, 200)
                ->withHeader('Content-Type', 'application/oauth-authz-req+jwt')
                ->withHeader('Cache-Control', 'no-store')
                ->withHeader('Pragma', 'no-cache');
        } catch (\Throwable $exception) {
            $this->logger->warning('EUDI request-object delivery failed: {message}', [
                'message' => $exception->getMessage(),
            ]);
            return $this->text('Invalid or expired presentation request', 400);
        }
    }

    private function directPost(ServerRequestInterface $request): ResponseInterface
    {
        if (strtoupper($request->getMethod()) !== 'POST') {
            return $this->text('Method not allowed', 405)->withHeader('Allow', 'POST');
        }

        try {
            $body = $request->getParsedBody();
            if (!is_array($body)) {
                throw new \RuntimeException('Wallet response is not form-encoded.');
            }

            // Select the TYPO3 configuration before constructing VerifierService because
            // credential trust anchors are configuration-specific. With direct_post.jwt,
            // state is encrypted, so only the untrusted protected-header kid is used for
            // routing. The SDK subsequently authenticates the JWE and verifies state.
            if (is_string($body['response'] ?? null) && $body['response'] !== '') {
                if (strlen($body['response']) > 3_000_000) {
                    throw new \RuntimeException('Encrypted wallet response exceeds the safety limit.');
                }
                $kid = (new DirectPostJwtProtectedHeader())->kid($body['response']);
                $metadata = $this->sessionMetadataRepository->getByResponseEncryptionKid($kid);
            } else {
                $state = is_string($body['state'] ?? null) ? $body['state'] : '';
                if ($state === '') {
                    throw new \RuntimeException('Wallet response contains neither encrypted response nor state.');
                }
                $metadata = $this->sessionMetadataRepository->getByState($state);
            }

            if ($metadata === null || (int)($metadata['configuration_uid'] ?? 0) <= 0) {
                throw new \RuntimeException('No EUDI verification transaction matches the wallet response.');
            }

            $sessionId = (string)$metadata['session_id'];
            $configuration = $this->configurationRepository->get((int)$metadata['configuration_uid']);

            // For an explicitly initiated same-device flow, prepare the fresh one-time
            // response code before consuming the encrypted response. OpenID4VP requires
            // the redirect URI returned to the Wallet to contain fresh random entropy.
            $responseCode = null;
            if ((bool)($metadata['same_device'] ?? false)) {
                $responseCode = $this->responseCodeService->create();
                $this->sessionMetadataRepository->storeResponseCode(
                    $sessionId,
                    $this->responseCodeService->hash($responseCode),
                );
            }

            $session = $this->verifierFactory
                ->create($configuration, $request)
                ->handleDirectPost($body);

            $payload = [
                'status' => $session->status->value,
            ];
            if ($responseCode !== null) {
                $payload['redirect_uri'] = $this->endpointUrlBuilder->url($request, '/eudi-wallet/redirect', [
                    'session' => $sessionId,
                    'response_code' => $responseCode,
                ]);
            }

            return $this->json($payload, 200)
                ->withHeader('Cache-Control', 'no-store')
                ->withHeader('Pragma', 'no-cache');
        } catch (\Throwable $exception) {
            $this->logger->warning('EUDI direct_post validation failed: {message}', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
            return $this->json(['status' => 'rejected'], 400)
                ->withHeader('Cache-Control', 'no-store')
                ->withHeader('Pragma', 'no-cache');
        }
    }

    private function text(string $body, int $status): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status);
        $response->getBody()->write($body);
        return $response;
    }

    /** @param array<string, mixed> $payload */
    private function json(array $payload, int $status): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8');
        $response->getBody()->write(json_encode($payload, JSON_THROW_ON_ERROR));
        return $response;
    }
}
