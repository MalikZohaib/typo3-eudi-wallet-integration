<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Security;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

final readonly class ReturnUrlValidator
{
    public function validate(?string $candidate, ServerRequestInterface $request): string
    {
        $default = $this->siteBase($request);
        if (!is_string($candidate) || trim($candidate) === '') {
            return $default;
        }
        $candidate = trim($candidate);
        $target = parse_url($candidate);
        $base = parse_url($default);
        if ($target === false || $base === false) {
            return $default;
        }

        if (!isset($target['host'])) {
            if (str_starts_with($candidate, '/')) {
                return $this->origin($default) . $candidate;
            }
            return rtrim($default, '/') . '/' . ltrim($candidate, '/');
        }

        if (
            ($target['scheme'] ?? '') !== ($base['scheme'] ?? '')
            || ($target['host'] ?? '') !== ($base['host'] ?? '')
            || ($target['port'] ?? null) !== ($base['port'] ?? null)
        ) {
            return $default;
        }
        return $candidate;
    }

    private function siteBase(ServerRequestInterface $request): string
    {
        $site = $request->getAttribute('site');
        if ($site instanceof Site) {
            return (string)$site->getBase();
        }
        $uri = $request->getUri();
        return $uri->getScheme() . '://' . $uri->getAuthority() . '/';
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return $url;
        }
        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }
        return $origin;
    }
}
