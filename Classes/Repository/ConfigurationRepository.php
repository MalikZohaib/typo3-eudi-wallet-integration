<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Repository;

use Doctrine\DBAL\ParameterType;
use T3Hub\EudiWalletIntegration\Domain\ClaimMapping;
use T3Hub\EudiWalletIntegration\Domain\Configuration;
use T3Hub\EudiWalletIntegration\Domain\TrustAnchorConfiguration;
use T3Hub\EudiWalletIntegration\Domain\VerificationMode;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class ConfigurationRepository
{
    public function __construct(private ConnectionPool $connectionPool)
    {
    }

    public function get(int $uid): Configuration
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_eudiwalletintegration_configuration');
        $row = $queryBuilder
            ->select('*')
            ->from('tx_eudiwalletintegration_configuration')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('hidden', 0),
            )
            ->executeQuery()
            ->fetchAssociative();

        if (!is_array($row)) {
            throw new \RuntimeException('EUDI Wallet configuration not found or disabled: ' . $uid);
        }

        return new Configuration(
            uid: (int)$row['uid'],
            title: (string)$row['title'],
            identifier: (string)$row['identifier'],
            mode: VerificationMode::tryFrom((string)($row['mode'] ?? 'login')) ?? VerificationMode::Login,
            credentialQueryId: (string)($row['credential_query_id'] ?: 'pid'),
            vct: (string)$row['vct'],
            purpose: trim((string)$row['purpose']) !== '' ? (string)$row['purpose'] : null,
            requireHolderBinding: (bool)$row['require_holder_binding'],
            createMissingUsers: (bool)$row['create_missing_users'],
            updateExistingUsers: (bool)$row['update_existing_users'],
            userStoragePid: (int)$row['user_storage_pid'],
            defaultUsergroup: (int)$row['default_usergroup'],
            identityClaim: trim((string)($row['identity_claim'] ?? '')) !== '' ? trim((string)$row['identity_claim']) : null,
            matchClaim: trim((string)$row['match_claim']) !== '' ? (string)$row['match_claim'] : null,
            matchField: trim((string)$row['match_field']) !== '' ? (string)$row['match_field'] : null,
            usernameClaim: trim((string)$row['username_claim']) !== '' ? (string)$row['username_claim'] : null,
            storeVerificationResult: (bool)$row['store_verification_result'],
            claimMappings: $this->getClaimMappings($uid),
            trustAnchors: $this->getTrustAnchors($uid),
        );
    }

    /** @return list<ClaimMapping> */
    private function getClaimMappings(int $configurationUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_eudiwalletintegration_claim_mapping');
        $rows = $queryBuilder
            ->select('claim_name', 'target_field', 'required', 'overwrite_existing')
            ->from('tx_eudiwalletintegration_claim_mapping')
            ->where(
                $queryBuilder->expr()->eq('configuration', $queryBuilder->createNamedParameter($configurationUid, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('hidden', 0),
            )
            ->orderBy('sorting', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(static fn(array $row): ClaimMapping => new ClaimMapping(
            claimName: trim((string)$row['claim_name']),
            targetField: trim((string)$row['target_field']) !== '' ? trim((string)$row['target_field']) : null,
            required: (bool)$row['required'],
            overwriteExisting: (bool)$row['overwrite_existing'],
        ), $rows);
    }

    /** @return list<TrustAnchorConfiguration> */
    private function getTrustAnchors(int $configurationUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_eudiwalletintegration_trust_anchor');
        $rows = $queryBuilder
            ->select('issuer', 'key_id', 'public_key_path', 'x509_trust_anchor_path', 'allowed_algorithms', 'authority_key_identifier')
            ->from('tx_eudiwalletintegration_trust_anchor')
            ->where(
                $queryBuilder->expr()->eq('configuration', $queryBuilder->createNamedParameter($configurationUid, ParameterType::INTEGER)),
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('hidden', 0),
            )
            ->orderBy('sorting', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(static function(array $row): TrustAnchorConfiguration {
            $algorithms = array_values(array_filter(array_map('trim', explode(',', (string)$row['allowed_algorithms']))));
            return new TrustAnchorConfiguration(
                issuer: trim((string)$row['issuer']),
                keyId: trim((string)$row['key_id']) !== '' ? trim((string)$row['key_id']) : null,
                publicKeyPath: trim((string)$row['public_key_path']) !== '' ? trim((string)$row['public_key_path']) : null,
                x509TrustAnchorPath: trim((string)($row['x509_trust_anchor_path'] ?? '')) !== '' ? trim((string)$row['x509_trust_anchor_path']) : null,
                allowedAlgorithms: $algorithms ?: ['ES256'],
                authorityKeyIdentifier: trim((string)($row['authority_key_identifier'] ?? '')) !== '' ? trim((string)$row['authority_key_identifier']) : null,
            );
        }, $rows);
    }
}
