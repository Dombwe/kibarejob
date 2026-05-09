<?php

namespace App\Service;

use App\Entity\ScheduledCommand;
use App\Repository\ScheduledCommandRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;

class ScheduledCommandRunner
{
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly ScheduledCommandRepository $scheduledCommandRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{checked: int, executed: int, errors: int}
     */
    public function runDue(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $summary = ['checked' => 0, 'executed' => 0, 'errors' => 0];

        foreach ($this->scheduledCommandRepository->findEnabled() as $scheduledCommand) {
            $summary['checked']++;
            if (!$this->isDue($scheduledCommand, $now)) {
                continue;
            }

            $result = $this->run($scheduledCommand);
            $summary['executed']++;
            if (Command::SUCCESS !== $result['exitCode']) {
                $summary['errors']++;
            }
        }

        return $summary;
    }

    /**
     * @return array{exitCode: int, output: string}
     */
    public function run(ScheduledCommand $scheduledCommand): array
    {
        $startedAt = microtime(true);
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $inputData = ['command' => $scheduledCommand->getCommandName()];
        foreach ($scheduledCommand->getArguments() ?? [] as $name => $value) {
            $inputData[$name] = $value;
        }
        foreach ($scheduledCommand->getOptions() ?? [] as $name => $value) {
            $inputData['--' . ltrim((string) $name, '-')] = $value;
        }

        $output = new BufferedOutput();
        $exitCode = Command::FAILURE;
        $error = null;

        try {
            $exitCode = $application->run(new ArrayInput($inputData), $output);
        } catch (\Throwable $exception) {
            $error = $exception->getMessage();
            $output->writeln($exception->getMessage());
        }

        $content = mb_strimwidth($output->fetch(), 0, 12000, "\n...");
        $scheduledCommand
            ->setLastRunAt(new \DateTimeImmutable())
            ->setLastExitCode($exitCode)
            ->setLastDurationMs((int) round((microtime(true) - $startedAt) * 1000))
            ->setLastOutput($content)
            ->setLastError($error);

        if (Command::SUCCESS === $exitCode) {
            $scheduledCommand->setLastSuccessAt(new \DateTimeImmutable());
        }

        $this->entityManager->flush();

        return ['exitCode' => $exitCode, 'output' => $content];
    }

    public function isDue(ScheduledCommand $scheduledCommand, \DateTimeImmutable $now): bool
    {
        if (!$scheduledCommand->isEnabled()) {
            return false;
        }

        $lastRunAt = $scheduledCommand->getLastRunAt();
        if (null === $lastRunAt) {
            return true;
        }

        return match ($scheduledCommand->getFrequency()) {
            ScheduledCommand::FREQUENCY_EVERY_15_MINUTES => $lastRunAt <= $now->modify('-15 minutes'),
            ScheduledCommand::FREQUENCY_HOURLY => $lastRunAt <= $now->modify('-1 hour'),
            ScheduledCommand::FREQUENCY_EVERY_6_HOURS => $lastRunAt <= $now->modify('-6 hours'),
            ScheduledCommand::FREQUENCY_DAILY => $lastRunAt <= $now->modify('-1 day') && $this->isAfterPreferredTime($scheduledCommand, $now),
            ScheduledCommand::FREQUENCY_WEEKLY => $lastRunAt <= $now->modify('-1 week') && $this->isAfterPreferredTime($scheduledCommand, $now),
            ScheduledCommand::FREQUENCY_MONTHLY => $lastRunAt <= $now->modify('-1 month') && $this->isAfterPreferredTime($scheduledCommand, $now),
            ScheduledCommand::FREQUENCY_CUSTOM_CRON => $this->matchesCron((string) $scheduledCommand->getCustomCronExpression(), $now),
            default => false,
        };
    }

    private function isAfterPreferredTime(ScheduledCommand $scheduledCommand, \DateTimeImmutable $now): bool
    {
        $preferredTime = $scheduledCommand->getPreferredTime();
        if (null === $preferredTime) {
            return true;
        }

        return $now->format('H:i') >= $preferredTime->format('H:i');
    }

    private function matchesCron(string $expression, \DateTimeImmutable $now): bool
    {
        $parts = preg_split('/\s+/', trim($expression));
        if (5 !== count($parts)) {
            return false;
        }

        return $this->matchesCronPart($parts[0], (int) $now->format('i'))
            && $this->matchesCronPart($parts[1], (int) $now->format('G'))
            && $this->matchesCronPart($parts[2], (int) $now->format('j'))
            && $this->matchesCronPart($parts[3], (int) $now->format('n'))
            && $this->matchesCronPart($parts[4], (int) $now->format('w'));
    }

    private function matchesCronPart(string $part, int $value): bool
    {
        if ('*' === $part) {
            return true;
        }

        if (str_starts_with($part, '*/')) {
            $step = (int) substr($part, 2);

            return $step > 0 && 0 === $value % $step;
        }

        foreach (explode(',', $part) as $candidate) {
            if ((string) $value === trim($candidate)) {
                return true;
            }
        }

        return false;
    }
}
