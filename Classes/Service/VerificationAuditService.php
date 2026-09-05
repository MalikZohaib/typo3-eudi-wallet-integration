<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Service;

use Eudi\VerifierCore\Domain\VerificationSession;
use T3Hub\EudiWalletIntegration\Domain\Configuration;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class VerificationAuditService
{
    public function __construct(private ConnectionPool $connectionPool)
    {
    }

    /** @param array<string, scalar|null> $mappedFields */
    public function store(Configuration $configuration, VerificationSession $session, array $mappedFields): void
    {
        if (!$configuration->storeVerificationResult || $session->result === null) {
            return;
        }
        $credential = $session->result->credentials[0] ?? null;
        $this->connectionPool->getConnectionForTable('tx_eudiwalletintegration_verification')->insert(
            'tx_eudiwalletintegration_verification',
            [
                'session_id' => $session->id,
                'configuration_uid' => $configuration->uid,
                'verified_at' => $session->result->verifiedAt->getTimestamp(),
                'issuer' => $credential?->issuer ?? '',
                'subject' => $credential?->subject ?? '',
                'credential_type' => $credential?->credentialType ?? '',
                'claims_json' => json_encode($session->result->claims(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'mapped_fields_json' => json_encode($mappedFields, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ],
        );
    }
}
