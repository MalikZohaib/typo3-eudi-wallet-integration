<?php

declare(strict_types=1);

namespace T3Hub\EudiWalletIntegration\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use T3Hub\EudiWalletIntegration\Configuration\ExtensionSettings;
use TYPO3\CMS\Core\Database\ConnectionPool;

#[AsCommand(
    name: 'eudi-wallet:cleanup',
    description: 'Remove expired EUDI Wallet verification sessions from the TYPO3 database.',
)]
final class CleanupSessionsCommand extends Command
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly ExtensionSettings $settings,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $retention = max(0, $this->settings->int('sessionRetentionSeconds', 86400));
        $threshold = time() - $retention;
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_eudiwalletintegration_session');
        $deleted = $queryBuilder->delete('tx_eudiwalletintegration_session')
            ->where($queryBuilder->expr()->lt('expires_at', $queryBuilder->createNamedParameter($threshold)))
            ->executeStatement();

        $output->writeln(sprintf('<info>Removed %d expired EUDI Wallet session(s).</info>', $deleted));
        return Command::SUCCESS;
    }
}
