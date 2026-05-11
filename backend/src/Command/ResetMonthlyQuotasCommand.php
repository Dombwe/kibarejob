<?php

namespace App\Command;

use App\Service\SubscriptionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'subscription:reset-monthly',
    description: 'Réinitialise les compteurs mensuels des employeurs.',
)]
class ResetMonthlyQuotasCommand extends Command
{
    public function __construct(private readonly SubscriptionService $subscriptionService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->subscriptionService->resetMonthlyCounters();

        (new SymfonyStyle($input, $output))->success('Compteurs mensuels réinitialisés.');

        return Command::SUCCESS;
    }
}
