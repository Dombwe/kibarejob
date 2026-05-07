<?php

namespace App\Entity;

use App\Entity\Enum\EmployerSubscriptionTier;
use App\Repository\EmployerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EmployerRepository::class)]
#[ORM\Table(name: 'employeurs')]
class Employer
{
    #[ORM\Id]
    #[ORM\OneToOne(inversedBy: 'employer', targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[ORM\Column(length: 255)]
    private string $companyName = '';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $nif = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 100)]
    private string $sector = '';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $companySize = null;

    #[ORM\Column(type: Types::JSON)]
    private array $cities = [];

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $logoUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(enumType: EmployerSubscriptionTier::class, options: ['default' => 'free'])]
    private EmployerSubscriptionTier $subscriptionTier = EmployerSubscriptionTier::Free;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $subscriptionExpiry = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isValidated = false;

    #[ORM\Column(options: ['default' => 0])]
    private int $offersUsedThisMonth = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $applicationsViewedThisMonth = 0;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $lastOfferReset;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDeleted = false;

    public function __construct() { $this->lastOfferReset = new \DateTimeImmutable('today'); }
    public function getUser(): User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }
    public function getCompanyName(): string { return $this->companyName; }
    public function setCompanyName(string $companyName): self { $this->companyName = $companyName; return $this; }
    public function getNif(): ?string { return $this->nif; }
    public function setNif(?string $nif): self { $this->nif = $nif; return $this; }
    public function getSector(): string { return $this->sector; }
    public function setSector(string $sector): self { $this->sector = $sector; return $this; }
    public function getCompanySize(): ?string { return $this->companySize; }
    public function setCompanySize(?string $companySize): self { $this->companySize = $companySize; return $this; }
    public function getCities(): array { return $this->cities; }
    public function setCities(array $cities): self { $this->cities = $cities; return $this; }
    public function getLogoUrl(): ?string { return $this->logoUrl; }
    public function setLogoUrl(?string $logoUrl): self { $this->logoUrl = $logoUrl; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function getWebsite(): ?string { return $this->website; }
    public function setWebsite(?string $website): self { $this->website = $website; return $this; }
    public function getSubscriptionTier(): EmployerSubscriptionTier { return $this->subscriptionTier; }
    public function setSubscriptionTier(EmployerSubscriptionTier $subscriptionTier): self { $this->subscriptionTier = $subscriptionTier; return $this; }
    public function getSubscriptionExpiry(): ?\DateTimeImmutable { return $this->subscriptionExpiry; }
    public function setSubscriptionExpiry(?\DateTimeImmutable $subscriptionExpiry): self { $this->subscriptionExpiry = $subscriptionExpiry; return $this; }
    public function isValidated(): bool { return $this->isValidated; }
    public function setIsValidated(bool $isValidated): self { $this->isValidated = $isValidated; return $this; }
    public function getOffersUsedThisMonth(): int { return $this->offersUsedThisMonth; }
    public function setOffersUsedThisMonth(int $offersUsedThisMonth): self { $this->offersUsedThisMonth = $offersUsedThisMonth; return $this; }
    public function getApplicationsViewedThisMonth(): int { return $this->applicationsViewedThisMonth; }
    public function setApplicationsViewedThisMonth(int $applicationsViewedThisMonth): self { $this->applicationsViewedThisMonth = $applicationsViewedThisMonth; return $this; }
    public function getLastOfferReset(): \DateTimeImmutable { return $this->lastOfferReset; }
    public function setLastOfferReset(\DateTimeImmutable $lastOfferReset): self { $this->lastOfferReset = $lastOfferReset; return $this; }
    public function isDeleted(): bool { return $this->isDeleted; }
    public function setIsDeleted(bool $isDeleted): self { $this->isDeleted = $isDeleted; return $this; }
}
