<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Infrastructure;

use Doctrine\DBAL\ParameterType;
use Eudi\VerifierCore\Contract\SessionStoreInterface;
use Eudi\VerifierCore\Domain\VerificationSession;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class DatabaseSessionStore implements SessionStoreInterface
{
    public function __construct(private ConnectionPool $connectionPool)
    {
    }

    public function create(VerificationSession $session): void
    {
        $now = time();
        $this->connectionPool->getConnectionForTable('tx_eudiwalletintegration_session')->insert(
            'tx_eudiwalletintegration_session',
            [
                'session_id' => $session->id,
                'state' => $session->state,
                'response_encryption_kid' => $session->responseEncryptionKey?->kid() ?? '',
                'configuration_uid' => 0,
                'session_json' => json_encode($session->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'status' => $session->status->value,
                'version' => $session->version,
                'browser_token_hash' => '',
                'browser_consumed' => 0,
                'return_url' => '',
                'fe_user_uid' => 0,
                'created_at' => $session->createdAt->getTimestamp(),
                'expires_at' => $session->expiresAt->getTimestamp(),
                'updated_at' => $now,
            ],
        );
    }

    public function get(string $sessionId): ?VerificationSession
    {
        return $this->findOne('session_id', $sessionId);
    }

    public function findByState(string $state): ?VerificationSession
    {
        return $this->findOne('state', $state);
    }

    public function findByResponseEncryptionKid(string $kid): ?VerificationSession
    {
        return $this->findOne('response_encryption_kid', $kid);
    }

    public function save(VerificationSession $session, int $expectedVersion): bool
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_eudiwalletintegration_session');
        $affected = $connection->update(
            'tx_eudiwalletintegration_session',
            [
                'session_json' => json_encode($session->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'status' => $session->status->value,
                'version' => $session->version,
                'response_encryption_kid' => $session->responseEncryptionKey?->kid() ?? '',
                'updated_at' => time(),
            ],
            [
                'session_id' => $session->id,
                'version' => $expectedVersion,
            ],
        );
        return $affected === 1;
    }

    private function findOne(string $field, string $value): ?VerificationSession
    {
        if (!in_array($field, ['session_id', 'state', 'response_encryption_kid'], true)) {
            throw new \InvalidArgumentException('Unsupported EUDI session lookup field.');
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_eudiwalletintegration_session');
        $json = $queryBuilder
            ->select('session_json')
            ->from('tx_eudiwalletintegration_session')
            ->where($queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter($value, ParameterType::STRING)))
            ->executeQuery()
            ->fetchOne();
        if (!is_string($json) || $json === '') {
            return null;
        }
        $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            return null;
        }
        return VerificationSession::fromArray($data);
    }
}
