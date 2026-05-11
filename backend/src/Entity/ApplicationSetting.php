<?php

namespace App\Entity;

use App\Repository\ApplicationSettingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ApplicationSettingRepository::class)]
#[ORM\Table(name: 'application_settings')]
class ApplicationSetting
{
    public const ENVIRONMENT_LOCAL = 'local';
    public const ENVIRONMENT_ONLINE = 'online';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 120)]
    private string $name = 'Configuration principale';

    #[Assert\Choice([self::ENVIRONMENT_LOCAL, self::ENVIRONMENT_ONLINE])]
    #[ORM\Column(length: 20, options: ['default' => self::ENVIRONMENT_LOCAL])]
    private string $activeEnvironment = self::ENVIRONMENT_LOCAL;

    #[Assert\NotBlank]
    #[Assert\Url]
    #[ORM\Column(length: 255, options: ['default' => 'https://127.0.0.1:8000'])]
    private string $localBaseUrl = 'https://127.0.0.1:8000';

    #[Assert\Url]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $onlineBaseUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $active = true;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getId(): ?UuidInterface { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = trim($name); return $this; }
    public function getActiveEnvironment(): string { return $this->activeEnvironment; }
    public function setActiveEnvironment(string $activeEnvironment): self { $this->activeEnvironment = $activeEnvironment; return $this; }
    public function getLocalBaseUrl(): string { return $this->localBaseUrl; }
    public function setLocalBaseUrl(string $localBaseUrl): self { $this->localBaseUrl = $this->normalizeBaseUrl($localBaseUrl); return $this; }
    public function getOnlineBaseUrl(): ?string { return $this->onlineBaseUrl; }
    public function setOnlineBaseUrl(?string $onlineBaseUrl): self
    {
        $onlineBaseUrl = trim((string) $onlineBaseUrl);
        $this->onlineBaseUrl = '' === $onlineBaseUrl ? null : $this->normalizeBaseUrl($onlineBaseUrl);

        return $this;
    }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function isActive(): bool { return $this->active; }
    public function setActive(bool $active): self { $this->active = $active; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

    public function getActiveBaseUrl(): string
    {
        if (self::ENVIRONMENT_ONLINE === $this->activeEnvironment && null !== $this->onlineBaseUrl) {
            return $this->onlineBaseUrl;
        }

        return $this->localBaseUrl;
    }

    public function touch(): self
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        return rtrim(trim($baseUrl), '/');
    }
}
