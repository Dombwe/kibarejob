<?php

namespace App\Command;

use App\Entity\User;
use App\Service\AuthEmailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:auth-email:test',
    description: 'Send a real account confirmation email through AuthEmailService.'
)]
class TestAuthEmailCommand extends Command
{
    public function __construct(private readonly AuthEmailService $authEmailService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'Recipient email address');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = trim((string) $input->getArgument('email'));
        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $output->writeln('<error>Email invalide.</error>');

            return Command::INVALID;
        }

        $user = (new User())
            ->setEmail($email)
            ->setFirstName('Test')
            ->setLastName('KIBARE Job');

        $token = $this->authEmailService->createEmailVerificationToken($user);
        $this->authEmailService->sendEmailVerification($user, $token);

        $output->writeln(sprintf('<info>Email de confirmation envoye a %s.</info>', $email));

        return Command::SUCCESS;
    }
}
