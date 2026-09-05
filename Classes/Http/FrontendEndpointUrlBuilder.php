<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Http;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

final readonly class FrontendEndpointUrlBuilder
{
    /** @param array<string, scalar> $query */
    public function url(ServerRequestInterface $request, string $path, array $query = []): string
    {
        $site = $request->getAttribute('site');
        $base = $site instanceof Site
            ? (string)$site->getBase()
            : $request->getUri()->getScheme() . '://' . $request->getUri()->getAuthority() . '/';
        $url = rtrim($base, '/') . '/' . ltrim($path, '/');
        if ($query !== []) {
            $url .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        }
        return $url;
    }

    public function matches(string $requestPath, string $route): bool
    {
        $requestPath = '/' . trim($requestPath, '/');
        $route = '/' . trim($route, '/');
        return $requestPath === $route || str_ends_with($requestPath, $route);
    }
}
