<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Service;

use Doctrine\DBAL\ParameterType;
use Eudi\VerifierCore\Domain\VerificationResult;
use T3Hub\EudiWalletIntegration\Domain\ClaimMapping;
use T3Hub\EudiWalletIntegration\Domain\Configuration;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Database\ConnectionPool;

final readonly class FrontendUserProvisioningService
{
    private const DENIED_TARGET_FIELDS = [
        'uid', 'pid', 'password', 'usergroup', 'deleted', 'disable', 'starttime', 'endtime',
        'tstamp', 'crdate', 'lastlogin', 'uc', 'felogin_forgotHash', 'tx_extbase_type',
    ];

    public function __construct(
        private ConnectionPool $connectionPool,
        private PasswordHashFactory $passwordHashFactory,
    ) {
    }

    /** @return array{uid:int,mappedFields:array<string, scalar|null>,created:bool} */
    public function provision(Configuration $configuration, VerificationResult $result): array
    {
        $credential = $result->credentials[0] ?? null;
        if ($credential === null) {
            throw new \RuntimeException('Verified wallet response contains no credential.');
        }
        $credentialType = $credential->credentialType ?? '';
        $claims = $result->claims();
        [$identitySource, $identityValue] = $this->resolveStableIdentity($configuration, $credential->subject, $claims);

        $userUid = $this->findLinkedIdentity($credential->issuer, $identitySource, $identityValue, $credentialType);
        if ($userUid <= 0) {
            $userUid = $this->findByConfiguredClaim($configuration, $claims);
        }

        $created = false;
        if ($userUid <= 0) {
            if (!$configuration->createMissingUsers) {
                throw new \RuntimeException('No TYPO3 frontend account is linked to this wallet identity and automatic user creation is disabled.');
            }
            $userUid = $this->createUser($configuration, $claims, $credential->issuer, $identitySource, $identityValue);
            $created = true;
        }

        $mappedFields = [];
        if ($created || $configuration->updateExistingUsers) {
            $mappedFields = $this->mapFields($configuration->claimMappings, $claims, $userUid);
        }
        $this->linkIdentity($userUid, $credential->issuer, $identitySource, $identityValue, $credentialType);
        return ['uid' => $userUid, 'mappedFields' => $mappedFields, 'created' => $created];
    }

    private function findLinkedIdentity(string $issuer, string $identitySource, string $identityValue, string $credentialType): int
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_eudiwalletintegration_identity');
        $uid = $qb->select('fe_user_uid')->from('tx_eudiwalletintegration_identity')->where(
            $qb->expr()->eq('identity_hash', $qb->createNamedParameter($this->identityHash($issuer, $identitySource, $identityValue, $credentialType))),
        )->executeQuery()->fetchOne();
        return (int)$uid;
    }

    /** @param array<string, mixed> $claims @return array{0:string,1:string} */
    private function resolveStableIdentity(Configuration $configuration, ?string $subject, array $claims): array
    {
        if (is_string($subject) && trim($subject) !== '') {
            return ['sub', trim($subject)];
        }

        $claimName = $configuration->identityClaim;
        if (!is_string($claimName) || trim($claimName) === '') {
            throw new \RuntimeException(
                'The verified credential has no sub claim. Configure a Stable wallet identity claim for login, using a stable issuer-scoped identifier such as personal_administrative_number when available.'
            );
        }

        $value = $claims[$claimName] ?? null;
        if (!is_scalar($value) || is_bool($value) || trim((string)$value) === '') {
            throw new \RuntimeException('The configured stable wallet identity claim is missing or not a non-empty scalar value: ' . $claimName);
        }

        return ['claim:' . $claimName, trim((string)$value)];
    }

    /** @param array<string, mixed> $claims */
    private function findByConfiguredClaim(Configuration $configuration, array $claims): int
    {
        if ($configuration->matchClaim === null || $configuration->matchField === null) {
            return 0;
        }
        $value = $claims[$configuration->matchClaim] ?? null;
        if (!is_scalar($value) || (string)$value === '') {
            return 0;
        }
        $field = $this->assertAllowedExistingFeUserField($configuration->matchField);
        $qb = $this->connectionPool->getQueryBuilderForTable('fe_users');
        $now = time();
        $uid = $qb->select('uid')->from('fe_users')->where(
            $qb->expr()->eq($field, $qb->createNamedParameter((string)$value)),
            $qb->expr()->eq('pid', $qb->createNamedParameter($configuration->userStoragePid, ParameterType::INTEGER)),
            $qb->expr()->eq('deleted', 0),
            $qb->expr()->eq('disable', 0),
            $qb->expr()->or(
                $qb->expr()->eq('starttime', 0),
                $qb->expr()->lte('starttime', $qb->createNamedParameter($now, ParameterType::INTEGER)),
            ),
            $qb->expr()->or(
                $qb->expr()->eq('endtime', 0),
                $qb->expr()->gt('endtime', $qb->createNamedParameter($now, ParameterType::INTEGER)),
            ),
        )->setMaxResults(2)->executeQuery()->fetchFirstColumn();
        if (count($uid) > 1) {
            throw new \RuntimeException('The configured wallet matching claim maps to multiple fe_users records. Refusing ambiguous login.');
        }
        return isset($uid[0]) ? (int)$uid[0] : 0;
    }

    /** @param array<string, mixed> $claims */
    private function createUser(Configuration $configuration, array $claims, string $issuer, string $identitySource, string $identityValue): int
    {
        if ($configuration->userStoragePid <= 0) {
            throw new \RuntimeException('Automatic EUDI user creation requires a positive frontend user storage PID.');
        }
        $username = '';
        if ($configuration->usernameClaim !== null && is_scalar($claims[$configuration->usernameClaim] ?? null)) {
            $username = trim((string)$claims[$configuration->usernameClaim]);
        }
        if ($username === '') {
            $username = 'eudi_' . substr(hash('sha256', $issuer . '|' . $identitySource . '|' . $identityValue), 0, 24);
        }
        if ($this->usernameExists($username, $configuration->userStoragePid)) {
            $username .= '_' . substr(bin2hex(random_bytes(4)), 0, 8);
        }
        $plainRandomPassword = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $password = $this->passwordHashFactory->getDefaultHashInstance('FE')->getHashedPassword($plainRandomPassword);
        if (!is_string($password) || $password === '') {
            throw new \RuntimeException('TYPO3 could not create a frontend-user password hash.');
        }
        $now = time();
        $data = [
            'pid' => $configuration->userStoragePid,
            'tstamp' => $now,
            'crdate' => $now,
            'username' => $username,
            'password' => $password,
            'disable' => 0,
            'deleted' => 0,
            'usergroup' => $configuration->defaultUsergroup > 0 ? (string)$configuration->defaultUsergroup : '',
        ];
        $connection = $this->connectionPool->getConnectionForTable('fe_users');
        $connection->insert('fe_users', $data);
        return (int)$connection->lastInsertId();
    }

    /**
     * @param list<ClaimMapping> $mappings
     * @param array<string, mixed> $claims
     * @return array<string, scalar|null>
     */
    private function mapFields(array $mappings, array $claims, int $userUid): array
    {
        $current = $this->fetchUser($userUid);
        $updates = [];
        foreach ($mappings as $mapping) {
            if ($mapping->targetField === null) {
                continue;
            }
            if (!array_key_exists($mapping->claimName, $claims)) {
                if ($mapping->required) {
                    throw new \RuntimeException('Required verified claim is missing during fe_users mapping: ' . $mapping->claimName);
                }
                continue;
            }
            $value = $claims[$mapping->claimName];
            if (!is_scalar($value) && $value !== null) {
                throw new \RuntimeException('Only scalar/null wallet claims can be mapped directly into fe_users fields.');
            }
            $field = $this->assertAllowedExistingFeUserField($mapping->targetField);
            if (!$mapping->overwriteExisting && trim((string)($current[$field] ?? '')) !== '') {
                continue;
            }
            $updates[$field] = is_bool($value) ? (int)$value : $value;
        }
        if ($updates !== []) {
            $updates['tstamp'] = time();
            $this->connectionPool->getConnectionForTable('fe_users')->update('fe_users', $updates, ['uid' => $userUid]);
            unset($updates['tstamp']);
        }
        return $updates;
    }

    /** @return array<string, mixed> */
    private function fetchUser(int $uid): array
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('fe_users');
        $row = $qb->select('*')->from('fe_users')->where(
            $qb->expr()->eq('uid', $qb->createNamedParameter($uid, ParameterType::INTEGER)),
            $qb->expr()->eq('deleted', 0),
        )->executeQuery()->fetchAssociative();
        if (!is_array($row)) {
            throw new \RuntimeException('Linked TYPO3 frontend user no longer exists.');
        }
        return $row;
    }

    private function usernameExists(string $username, int $pid): bool
    {
        $qb = $this->connectionPool->getQueryBuilderForTable('fe_users');
        return (bool)$qb->count('uid')->from('fe_users')->where(
            $qb->expr()->eq('username', $qb->createNamedParameter($username)),
            $qb->expr()->eq('pid', $qb->createNamedParameter($pid, ParameterType::INTEGER)),
            $qb->expr()->eq('deleted', 0),
        )->executeQuery()->fetchOne();
    }

    private function linkIdentity(int $feUserUid, string $issuer, string $identitySource, string $identityValue, string $credentialType): void
    {
        $connection = $this->connectionPool->getConnectionForTable('tx_eudiwalletintegration_identity');
        $identityHash = $this->identityHash($issuer, $identitySource, $identityValue, $credentialType);
        $qb = $this->connectionPool->getQueryBuilderForTable('tx_eudiwalletintegration_identity');
        $existing = $qb->select('uid')->from('tx_eudiwalletintegration_identity')->where(
            $qb->expr()->eq('identity_hash', $qb->createNamedParameter($identityHash)),
        )->executeQuery()->fetchOne();
        $now = time();
        if ((int)$existing > 0) {
            $connection->update('tx_eudiwalletintegration_identity', [
                'fe_user_uid' => $feUserUid,
                'last_verified_at' => $now,
            ], ['uid' => (int)$existing]);
            return;
        }
        $connection->insert('tx_eudiwalletintegration_identity', [
            'identity_hash' => $identityHash,
            'fe_user_uid' => $feUserUid,
            'issuer' => $issuer,
            'subject' => $identityValue,
            'identity_source' => $identitySource,
            'credential_type' => $credentialType,
            'created_at' => $now,
            'last_verified_at' => $now,
        ]);
    }

    private function identityHash(string $issuer, string $identitySource, string $identityValue, string $credentialType): string
    {
        // Preserve hashes created by earlier releases when the JWT sub claim is used.
        if ($identitySource === 'sub') {
            return hash('sha256', $issuer . "\0" . $identityValue . "\0" . $credentialType);
        }

        return hash('sha256', $issuer . "\0" . $identitySource . "\0" . $identityValue . "\0" . $credentialType);
    }

    private function assertAllowedExistingFeUserField(string $field): string
    {
        if (in_array($field, self::DENIED_TARGET_FIELDS, true)) {
            throw new \RuntimeException('Mapping into sensitive fe_users field is forbidden: ' . $field);
        }
        $schemaManager = $this->connectionPool->getConnectionForTable('fe_users')->createSchemaManager();
        $columns = $schemaManager->listTableColumns('fe_users');
        if (!array_key_exists(strtolower($field), array_change_key_case($columns, CASE_LOWER))) {
            throw new \RuntimeException('Configured fe_users mapping field does not exist: ' . $field);
        }
        return $field;
    }
}
