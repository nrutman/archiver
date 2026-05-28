<?php

namespace App\Command;

use App\TemporaryStorage\TemporaryWorkspacePurger;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:temp:purge',
    description: 'Removes expired temporary archive workspaces.',
)]
final class PurgeTemporaryArchivesCommand extends Command
{
    public function __construct(
        private readonly TemporaryWorkspacePurger $purger,
        private readonly int $ttlSeconds,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('ttl-seconds', null, InputOption::VALUE_REQUIRED, 'Override the configured temporary workspace TTL.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $ttlSeconds = $this->ttlSeconds;
        $override = $input->getOption('ttl-seconds');

        if (null !== $override) {
            if (!is_numeric($override) || (int) $override < 0) {
                $io->error('The --ttl-seconds option must be a non-negative integer.');

                return Command::INVALID;
            }

            $ttlSeconds = (int) $override;
        }

        $result = $this->purger->purge($ttlSeconds);

        $io->success(sprintf(
            'Temporary archive purge complete. Removed: %d. Skipped active: %d. Skipped fresh: %d.',
            $result->removed,
            $result->skippedActive,
            $result->skippedFresh,
        ));

        return Command::SUCCESS;
    }
}
