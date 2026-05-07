<?php

namespace App\Entity;

use App\Repository\StatsCacheRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: StatsCacheRepository::class)]
#[ORM\Table(name: 'stats_cache')]
#[ORM\UniqueConstraint(name: 'uniq_stats_cache_key', columns: ['stat_key'])]
class StatsCache
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[ORM\Column(length: 100)]
    private string $statKey = '';

    #[ORM\Column(type: Types::JSON)]
    private array $statValue = [];

    #[ORM\Column]
    private \DateTimeImmutable $calculatedAt;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDeleted = false;

    public function __construct() { $this->calculatedAt = new \DateTimeImmutable(); }
    public function getId(): ?int { return $this->id; }
    public function getStatKey(): string { return $this->statKey; }
    public function setStatKey(string $statKey): self { $this->statKey = $statKey; return $this; }
    public function getStatValue(): array { return $this->statValue; }
    public function setStatValue(array $statValue): self { $this->statValue = $statValue; return $this; }
    public function getCalculatedAt(): \DateTimeImmutable { return $this->calculatedAt; }
    public function setCalculatedAt(\DateTimeImmutable $calculatedAt): self { $this->calculatedAt = $calculatedAt; return $this; }
    public function isDeleted(): bool { return $this->isDeleted; }
    public function setIsDeleted(bool $isDeleted): self { $this->isDeleted = $isDeleted; return $this; }
}
