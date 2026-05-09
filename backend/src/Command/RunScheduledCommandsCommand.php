<?php

namespace App\Command;

use App\Service\ScheduledCommandRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:scheduled-commands:run', description: 'Execute les commandes automatisees dues.')]
class RunScheduledCommandsCommand extends Command
{
    public function __construct(private readonly ScheduledCommandRunner $runner)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $summary = $this->runner->runDue();

        $io->success(sprintf(
            '%d commande(s) verifiee(s), %d executee(s), %d erreur(s).',
            $summary['checked'],
            $summary['executed'],
            $summary['errors']
        ));

        return 0 === $summary['errors'] ? Command::SUCCESS : Command::FAILURE;
    }
}
