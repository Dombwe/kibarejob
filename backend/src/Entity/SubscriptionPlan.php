<?php

namespace App\Entity;

use App\Entity\Enum\SubscriptionTarget;
use App\Repository\SubscriptionPlanRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SubscriptionPlanRepository::class)]
#[ORM\Table(name: 'subscription_plans')]
class SubscriptionPlan
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    #[ORM\Column(length: 50)]
    private string $name = '';

    #[ORM\Column(enumType: SubscriptionTarget::class)]
    private SubscriptionTarget $target = SubscriptionTarget::Candidat;

    #[Assert\PositiveOrZero]
    #[ORM\Column]
    private int $price = 0;

    #[ORM\Column(name: 'price_3months', nullable: true)]
    private ?int $price3months = null;

    #[ORM\Column(name: 'price_6months', nullable: true)]
    private ?int $price6months = null;

    #[ORM\Column(name: 'price_12months', nullable: true)]
    private ?int $price12months = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $features = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDeleted = false;

    public function getId(): ?UuidInterface { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getTarget(): SubscriptionTarget { return $this->target; }
    public function setTarget(SubscriptionTarget $target): self { $this->target = $target; return $this; }
    public function getPrice(): int { return $this->price; }
    public function setPrice(int $price): self { $this->price = $price; return $this; }
    public function getPrice3months(): ?int { return $this->price3months; }
    public function setPrice3months(?int $price3months): self { $this->price3months = $price3months; return $this; }
    public function getPrice6months(): ?int { return $this->price6months; }
    public function setPrice6months(?int $price6months): self { $this->price6months = $price6months; return $this; }
    public function getPrice12months(): ?int { return $this->price12months; }
    public function setPrice12months(?int $price12months): self { $this->price12months = $price12months; return $this; }
    public function getFeatures(): ?array { return $this->features; }
    public function setFeatures(?array $features): self { $this->features = $features; return $this; }
    public function isDeleted(): bool { return $this->isDeleted; }
    public function setIsDeleted(bool $isDeleted): self { $this->isDeleted = $isDeleted; return $this; }
}
