<?php

namespace App\Entity;

use App\Entity\Enum\PaymentMethod;
use App\Entity\Enum\PaymentStatus;
use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payments')]
class Payment
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private User $user;

    #[Assert\PositiveOrZero]
    #[ORM\Column]
    private int $amount = 0;

    #[Assert\Length(min: 3, max: 3)]
    #[ORM\Column(length: 3, options: ['default' => 'XOF'])]
    private string $currency = 'XOF';

    #[ORM\Column(enumType: PaymentMethod::class)]
    private PaymentMethod $method = PaymentMethod::OrangeMoney;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $subscriptionType = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 255)]
    private string $transactionId = '';

    #[ORM\Column(enumType: PaymentStatus::class)]
    private PaymentStatus $status = PaymentStatus::Pending;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDeleted = false;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }
    public function getId(): ?UuidInterface { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }
    public function getAmount(): int { return $this->amount; }
    public function setAmount(int $amount): self { $this->amount = $amount; return $this; }
    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): self { $this->currency = $currency; return $this; }
    public function getMethod(): PaymentMethod { return $this->method; }
    public function setMethod(PaymentMethod $method): self { $this->method = $method; return $this; }
    public function getSubscriptionType(): ?string { return $this->subscriptionType; }
    public function setSubscriptionType(?string $subscriptionType): self { $this->subscriptionType = $subscriptionType; return $this; }
    public function getTransactionId(): string { return $this->transactionId; }
    public function setTransactionId(string $transactionId): self { $this->transactionId = $transactionId; return $this; }
    public function getStatus(): PaymentStatus { return $this->status; }
    public function setStatus(PaymentStatus $status): self { $this->status = $status; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
    public function isDeleted(): bool { return $this->isDeleted; }
    public function setIsDeleted(bool $isDeleted): self { $this->isDeleted = $isDeleted; return $this; }
}
