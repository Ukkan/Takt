<?php

namespace App\Command;

use App\Service\StaleTimeEntryCloser;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:close-stale-entries',
    description: 'Close active time entries left open past midnight (run daily, e.g. via cron)',
)]
class CloseStaleEntriesCommand extends Command
{
    public function __construct(
        private readonly StaleTimeEntryCloser $closer,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $count = $this->closer->closeAllStale();

        $io->success(sprintf('Closed %d stale time entr%s.', $count, $count === 1 ? 'y' : 'ies'));

        return Command::SUCCESS;
    }
}
