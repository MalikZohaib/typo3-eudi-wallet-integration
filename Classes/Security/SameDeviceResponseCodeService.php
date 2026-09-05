<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Security;

/**
 * Generates the one-time response code embedded in the redirect_uri returned
 * to the Wallet after a same-device direct_post response.
 */
final readonly class SameDeviceResponseCodeService
{
    public function create(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function hash(string $code): string
    {
        return hash('sha256', $code);
    }
}
