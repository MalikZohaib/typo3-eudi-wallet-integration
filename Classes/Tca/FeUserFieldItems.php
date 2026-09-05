<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Tca;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final readonly class FeUserFieldItems
{
    private const DENIED = [
        'uid', 'pid', 'password', 'usergroup', 'deleted', 'disable', 'starttime', 'endtime',
        'tstamp', 'crdate', 'lastlogin', 'uc', 'felogin_forgotHash', 'tx_extbase_type',
    ];

    private ConnectionPool $connectionPool;

    public function __construct()
    {
        $this->connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
    }

    /** @param array<string, mixed> $configuration */
    public function addItems(array &$configuration): void
    {
        $columns = $this->connectionPool->getConnectionForTable('fe_users')->createSchemaManager()->listTableColumns('fe_users');
        $items = [];
        foreach ($columns as $column) {
            $name = $column->getName();
            if (in_array($name, self::DENIED, true)) {
                continue;
            }
            $items[] = ['label' => $name, 'value' => $name];
        }
        usort($items, static fn(array $a, array $b): int => strcmp((string)$a['label'], (string)$b['label']));
        $configuration['items'] = array_merge($configuration['items'] ?? [], $items);
    }
}
