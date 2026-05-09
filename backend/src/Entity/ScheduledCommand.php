<?php

namespace App\Entity;

use App\Repository\ScheduledCommandRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ScheduledCommandRepository::class)]
#[ORM\Table(name: 'scheduled_commands')]
class ScheduledCommand
{
    public const FREQUENCY_EVERY_15_MINUTES = 'every_15_minutes';
    public const FREQUENCY_HOURLY = 'hourly';
    public const FREQUENCY_EVERY_6_HOURS = 'every_6_hours';
    public const FREQUENCY_DAILY = 'daily';
    public const FREQUENCY_WEEKLY = 'weekly';
    public const FREQUENCY_MONTHLY = 'monthly';
    public const FREQUENCY_CUSTOM_CRON = 'custom_cron';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 140)]
    private string $name = '';

    #[Assert\NotBlank]
    #[ORM\Column(length: 140)]
    private string $commandName = '';

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $arguments = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $options = null;

    #[ORM\Column(length: 40, options: ['default' => self::FREQUENCY_DAILY])]
    private string $frequency = self::FREQUENCY_DAILY;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $customCronExpression = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $preferredTime = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastRunAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSuccessAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastExitCode = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastDurationMs = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastOutput = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->preferredTime = new \DateTimeImmutable('02:00');
    }

    public function __toString(): string
    {
        return $this->name ?: $this->commandName;
    }

    public function getId(): ?UuidInterface { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getCommandName(): string { return $this->commandName; }
    public function setCommandName(string $commandName): self { $this->commandName = trim($commandName); return $this; }
    public function getArguments(): ?array { return $this->arguments; }
    public function setArguments(?array $arguments): self { $this->arguments = $arguments; return $this; }
    public function getArgumentsJson(): string { return $this->encodeJson($this->arguments ?? []); }
    public function setArgumentsJson(?string $argumentsJson): self { $this->arguments = $this->decodeJson($argumentsJson); return $this; }
    public function getOptions(): ?array { return $this->options; }
    public function setOptions(?array $options): self { $this->options = $options; return $this; }
    public function getOptionsJson(): string { return $this->encodeJson($this->options ?? []); }
    public function setOptionsJson(?string $optionsJson): self { $this->options = $this->decodeJson($optionsJson); return $this; }
    public function getFrequency(): string { return $this->frequency; }
    public function setFrequency(string $frequency): self { $this->frequency = $frequency; return $this; }
    public function getCustomCronExpression(): ?string { return $this->customCronExpression; }
    public function setCustomCronExpression(?string $customCronExpression): self { $this->customCronExpression = '' !== trim((string) $customCronExpression) ? trim((string) $customCronExpression) : null; return $this; }
    public function getPreferredTime(): ?\DateTimeImmutable { return $this->preferredTime; }
    public function setPreferredTime(?\DateTimeImmutable $preferredTime): self { $this->preferredTime = $preferredTime; return $this; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): self { $this->enabled = $enabled; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function getLastRunAt(): ?\DateTimeImmutable { return $this->lastRunAt; }
    public function setLastRunAt(?\DateTimeImmutable $lastRunAt): self { $this->lastRunAt = $lastRunAt; return $this; }
    public function getLastSuccessAt(): ?\DateTimeImmutable { return $this->lastSuccessAt; }
    public function setLastSuccessAt(?\DateTimeImmutable $lastSuccessAt): self { $this->lastSuccessAt = $lastSuccessAt; return $this; }
    public function getLastExitCode(): ?int { return $this->lastExitCode; }
    public function setLastExitCode(?int $lastExitCode): self { $this->lastExitCode = $lastExitCode; return $this; }
    public function getLastDurationMs(): ?int { return $this->lastDurationMs; }
    public function setLastDurationMs(?int $lastDurationMs): self { $this->lastDurationMs = $lastDurationMs; return $this; }
    public function getLastOutput(): ?string { return $this->lastOutput; }
    public function setLastOutput(?string $lastOutput): self { $this->lastOutput = $lastOutput; return $this; }
    public function getLastError(): ?string { return $this->lastError; }
    public function setLastError(?string $lastError): self { $this->lastError = $lastError; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getFrequencyLabel(): string
    {
        return match ($this->frequency) {
            self::FREQUENCY_EVERY_15_MINUTES => 'Toutes les 15 minutes',
            self::FREQUENCY_HOURLY => 'Toutes les heures',
            self::FREQUENCY_EVERY_6_HOURS => 'Toutes les 6 heures',
            self::FREQUENCY_DAILY => 'Chaque jour',
            self::FREQUENCY_WEEKLY => 'Chaque semaine',
            self::FREQUENCY_MONTHLY => 'Chaque mois',
            self::FREQUENCY_CUSTOM_CRON => 'Expression cron personnalisee',
            default => $this->frequency,
        };
    }

    public function getStatusLabel(): string
    {
        if (!$this->enabled) {
            return 'Desactivee';
        }

        if (null !== $this->lastError && '' !== trim($this->lastError)) {
            return 'Erreur : ' . mb_strimwidth($this->lastError, 0, 80, '...');
        }

        if (null === $this->lastRunAt) {
            return 'Jamais executee';
        }

        return sprintf('Derniere execution : code %s', null === $this->lastExitCode ? '-' : (string) $this->lastExitCode);
    }

    public function getRunLink(): string
    {
        return '';
    }

    public function getPlanLink(): string
    {
        return '';
    }

    public function getRunnerCommand(): string
    {
        return 'cd /d C:\\wamp64\\www\\solutions\\kibarejob\\backend && php bin\\console app:scheduled-commands:run';
    }

    public function getWindowsTaskCommand(): string
    {
        return 'schtasks /Create /TN "KIBARE-JOB Cron" /SC MINUTE /MO 15 /TR "' . $this->getRunnerCommand() . '" /F';
    }

    public function getLinuxCronLine(): string
    {
        return '*/15 * * * * cd /var/www/kibarejob/backend && php bin/console app:scheduled-commands:run >> var/log/scheduled_commands.log 2>&1';
    }

    public function getPlanningGuide(): string
    {
        return '<strong>Windows</strong><br><code>' . htmlspecialchars($this->getWindowsTaskCommand(), ENT_QUOTES) . '</code><br><br><strong>Linux / cron</strong><br><code>' . htmlspecialchars($this->getLinuxCronLine(), ENT_QUOTES) . '</code><br><br>Planifiez une seule fois le runner ci-dessus. Il executera automatiquement les commandes actives lorsque leur frequence est due.';
    }

    private function encodeJson(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function decodeJson(?string $json): ?array
    {
        $json = trim((string) $json);
        if ('' === $json) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }
}
