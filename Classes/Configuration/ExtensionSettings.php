<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Configuration;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final readonly class ExtensionSettings
{
    public function __construct(private ExtensionConfiguration $extensionConfiguration)
    {
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $configuration = $this->extensionConfiguration->get('eudi_wallet_integration');
        return is_array($configuration) ? $configuration : [];
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->all()[$key] ?? $default;
        return is_scalar($value) ? trim((string)$value) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        return (int)($this->all()[$key] ?? $default);
    }

    public function bool(string $key, bool $default = false): bool
    {
        return (bool)($this->all()[$key] ?? $default);
    }
}
