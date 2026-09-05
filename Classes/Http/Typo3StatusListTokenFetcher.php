<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Http;

use Eudi\CredentialSdJwt\Contract\StatusListTokenFetcherInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

final readonly class Typo3StatusListTokenFetcher implements StatusListTokenFetcherInterface
{
    public function __construct(private RequestFactory $requestFactory)
    {
    }

    public function fetch(string $uri): string
    {
        $this->assertSafeHttpsUri($uri);

        $response = $this->requestFactory->request($uri, 'GET', [
            'headers' => [
                'Accept' => 'application/statuslist+jwt',
            ],
            'connect_timeout' => 5,
            'timeout' => 10,
            'allow_redirects' => [
                'max' => 3,
                'strict' => true,
                'referer' => false,
                'protocols' => ['https'],
            ],
            'http_errors' => false,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException('Status List endpoint returned HTTP ' . $statusCode . '.');
        }

        $contentType = strtolower(trim(explode(';', $response->getHeaderLine('Content-Type'), 2)[0]));
        if ($contentType !== '' && $contentType !== 'application/statuslist+jwt') {
            throw new \RuntimeException('Status List endpoint returned unsupported Content-Type: ' . $contentType);
        }

        $body = (string)$response->getBody();
        if ($body === '' || strlen($body) > 2_000_000) {
            throw new \RuntimeException('Status List endpoint response is empty or exceeds the size limit.');
        }

        return $body;
    }

    private function assertSafeHttpsUri(string $uri): void
    {
        $parts = parse_url($uri);
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
            throw new \RuntimeException('Status List URI must use HTTPS.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new \RuntimeException('Status List URI must not contain user information.');
        }
        $host = $parts['host'] ?? null;
        if (!is_string($host) || $host === '' || strtolower($host) === 'localhost') {
            throw new \RuntimeException('Status List URI host is invalid.');
        }

        // Reject literal private/reserved IP addresses. DNS-level SSRF protection should
        // additionally be enforced by the deployment/network egress policy.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false
            && filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
        ) {
            throw new \RuntimeException('Status List URI must not target a private or reserved IP address.');
        }
    }
}
