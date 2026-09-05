<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Security;

use T3Hub\EudiWalletIntegration\Configuration\ExtensionSettings;

final readonly class RelyingPartyCredentialLoader
{
    public function __construct(
        private ExtensionSettings $settings,
        private FilePathResolver $filePathResolver,
    ) {
    }

    public function privateKeyPem(): string
    {
        return $this->filePathResolver->readPrivateFile($this->settings->string('requestPrivateKeyPath'));
    }

    /** @return list<string> */
    public function certificateChainPem(): array
    {
        $pem = $this->filePathResolver->readPrivateFile($this->settings->string('requestCertificateChainPath'));
        preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $pem, $matches);
        $certificates = array_values(array_filter(array_map('trim', $matches[0] ?? [])));
        if ($certificates === []) {
            throw new \RuntimeException('The configured certificate chain contains no PEM certificates.');
        }
        return $certificates;
    }

    public function signingAlgorithm(): string
    {
        return $this->settings->string('requestSigningAlgorithm', 'ES256');
    }

    public function clientId(): string
    {
        $configured = $this->settings->string('clientId');
        if ($configured !== '') {
            return $configured;
        }
        return 'x509_hash:' . $this->base64UrlEncode(hash('sha256', $this->pemCertificateToDer($this->certificateChainPem()[0]), true));
    }

    private function pemCertificateToDer(string $pem): string
    {
        $body = preg_replace('/-----BEGIN CERTIFICATE-----|-----END CERTIFICATE-----|\s+/', '', $pem);
        $der = is_string($body) ? base64_decode($body, true) : false;
        if (!is_string($der) || $der === '') {
            throw new \RuntimeException('Unable to decode leaf certificate for x509_hash client_id.');
        }
        return $der;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
