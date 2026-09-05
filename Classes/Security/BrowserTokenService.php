<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Security;

final readonly class BrowserTokenService
{
    public function create(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function verify(string $token, string $expectedHash): bool
    {
        return $token !== '' && $expectedHash !== '' && hash_equals($expectedHash, $this->hash($token));
    }
}
