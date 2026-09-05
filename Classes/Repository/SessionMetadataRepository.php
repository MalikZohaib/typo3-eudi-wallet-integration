<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Repository;

use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class SessionMetadataRepository
{
    public function __construct(private ConnectionPool $connectionPool)
    {
    }

    public function attachBrowserContext(string $sessionId, int $configurationUid, string $browserTokenHash, string $returnUrl, string $returnMode = 'page'): void
    {
        $affected = $this->connectionPool->getConnectionForTable('tx_eudiwalletintegration_session')->update(
            'tx_eudiwalletintegration_session',
            [
                'configuration_uid' => $configurationUid,
                'browser_token_hash' => $browserTokenHash,
                'return_url' => $returnUrl,
                'return_mode' => $returnMode,
                'updated_at' => time(),
            ],
            ['session_id' => $sessionId],
        );
        if ($affected !== 1) {
            throw new \RuntimeException('Unable to attach browser context to the EUDI verification session.');
        }
    }

    /** @return array<string, mixed>|null */
    public function get(string $sessionId): ?array
    {
        return $this->findOne('session_id', $sessionId);
    }

    /** @return array<string, mixed>|null */
    public function getByState(string $state): ?array
    {
        return $this->findOne('state', $state);
    }

    /** @return array<string, mixed>|null */
    public function getByResponseEncryptionKid(string $kid): ?array
    {
        return $this->findOne('response_encryption_kid', $kid);
    }

    public function configurationUidForSession(string $sessionId): int
    {
        return $this->requireConfigurationUid($this->get($sessionId), 'verification session ' . $sessionId);
    }

    public function configurationUidForState(string $state): int
    {
        return $this->requireConfigurationUid($this->getByState($state), 'returned state');
    }

    public function configurationUidForResponseEncryptionKid(string $kid): int
    {
        if ($kid === '') {
            throw new \RuntimeException('Missing EUDI response-encryption kid.');
        }
        return $this->requireConfigurationUid($this->getByResponseEncryptionKid($kid), 'response-encryption kid');
    }

    public function markSameDevice(string $sessionId): void
    {
        $affected = $this->connectionPool->getConnectionForTable('tx_eudiwalletintegration_session')->update(
            'tx_eudiwalletintegration_session',
            [
                'same_device' => 1,
                'same_device_confirmed' => 0,
                'response_code_hash' => '',
                'response_code_consumed' => 0,
                'updated_at' => time(),
            ],
            ['session_id' => $sessionId],
        );
        if ($affected !== 1) {
            $existing = $this->get($sessionId);
            if ($existing === null || !(bool)($existing['same_device'] ?? false)) {
                throw new \RuntimeException('Unable to mark EUDI verification as same-device.');
            }
        }
    }

    public function storeResponseCode(string $sessionId, string $responseCodeHash): void
    {
        if ($responseCodeHash === '') {
            throw new \InvalidArgumentException('Response-code hash must not be empty.');
        }
        $affected = $this->connectionPool->getConnectionForTable('tx_eudiwalletintegration_session')->update(
            'tx_eudiwalletintegration_session',
            [
                'response_code_hash' => $responseCodeHash,
                'response_code_consumed' => 0,
                'updated_at' => time(),
            ],
            [
                'session_id' => $sessionId,
                'same_device' => 1,
            ],
        );
        if ($affected !== 1) {
            throw new \RuntimeException('Unable to persist same-device EUDI response code.');
        }
    }

    public function confirmSameDevice(string $sessionId, string $responseCodeHash): bool
    {
        if ($responseCodeHash === '') {
            return false;
        }
        return $this->connectionPool->getConnectionForTable('tx_eudiwalletintegration_session')->update(
            'tx_eudiwalletintegration_session',
            [
                'same_device_confirmed' => 1,
                'response_code_consumed' => 1,
                'updated_at' => time(),
            ],
            [
                'session_id' => $sessionId,
                'same_device' => 1,
                'response_code_hash' => $responseCodeHash,
                'response_code_consumed' => 0,
            ],
        ) === 1;
    }

    public function consumeBrowserToken(string $sessionId, int $feUserUid): bool
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_eudiwalletintegration_session');
        return $connection->update(
            'tx_eudiwalletintegration_session',
            [
                'browser_consumed' => 1,
                'fe_user_uid' => $feUserUid,
                'updated_at' => time(),
            ],
            [
                'session_id' => $sessionId,
                'browser_consumed' => 0,
            ],
        ) === 1;
    }

    /** @return array<string,mixed>|null */
    private function findOne(string $field, string $value): ?array
    {
        if (!in_array($field, ['session_id', 'state', 'response_encryption_kid'], true) || $value === '') {
            return null;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_eudiwalletintegration_session');
        $row = $queryBuilder
            ->select('*')
            ->from('tx_eudiwalletintegration_session')
            ->where($queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter($value)))
            ->executeQuery()
            ->fetchAssociative();
        return is_array($row) ? $row : null;
    }

    /** @param array<string,mixed>|null $row */
    private function requireConfigurationUid(?array $row, string $context): int
    {
        if ($row === null || (int)($row['configuration_uid'] ?? 0) <= 0) {
            throw new \RuntimeException('No EUDI configuration is associated with the ' . $context . '.');
        }
        return (int)$row['configuration_uid'];
    }
}
