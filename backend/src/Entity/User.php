<?php

namespace App\Entity;

use App\Entity\Enum\UserSubscriptionTier;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\Index(name: 'idx_users_email_verification_token_hash', columns: ['email_verification_token_hash'])]
#[ORM\Index(name: 'idx_users_password_reset_token_hash', columns: ['password_reset_token_hash'])]
#[ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'uniq_users_phone', columns: ['phone'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 255)]
    #[ORM\Column(length: 255)]
    private string $email = '';

    #[Assert\Length(max: 100)]
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $firstName = null;

    #[Assert\Length(max: 100)]
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isEmailVerified = false;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $emailVerificationTokenHash = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $emailVerificationTokenExpiresAt = null;

    #[Assert\Length(max: 20)]
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(name: 'password_hash', length: 255)]
    private string $passwordHash = '';

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $passwordResetTokenHash = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $passwordResetTokenExpiresAt = null;

    #[ORM\Column(type: Types::JSON, options: ['default' => '["candidat"]'])]
    private array $roles = ['candidat'];

    #[ORM\Column(options: ['default' => true])]
    private bool $isActive = true;

    #[Assert\Range(min: 0, max: 100)]
    #[ORM\Column(options: ['default' => 0])]
    private int $profileCompletedPercent = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDeleted = false;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastLogin = null;

    #[ORM\Column(enumType: UserSubscriptionTier::class, options: ['default' => 'free'])]
    private UserSubscriptionTier $subscriptionTier = UserSubscriptionTier::Free;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $subscriptionExpiry = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $subscriptionStartedAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $swipesUsedToday = 0;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $lastSwipeReset;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: CandidateProfile::class, cascade: ['persist', 'remove'])]
    private ?CandidateProfile $candidateProfile = null;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: Employer::class, cascade: ['persist', 'remove'])]
    private ?Employer $employer = null;

    #[ORM\OneToMany(mappedBy: 'candidate', targetEntity: CandidateDocument::class)]
    private Collection $candidateDocuments;

    #[ORM\OneToMany(mappedBy: 'employer', targetEntity: JobOffer::class)]
    private Collection $jobOffers;

    #[ORM\OneToMany(mappedBy: 'candidate', targetEntity: Swipe::class)]
    private Collection $swipes;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->lastSwipeReset = new \DateTimeImmutable('today');
        $this->candidateDocuments = new ArrayCollection();
        $this->jobOffers = new ArrayCollection();
        $this->swipes = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->getDisplayName();
    }

    public function getId(): ?UuidInterface { return $this->id; }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): self { $this->email = $email; return $this; }
    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(?string $firstName): self { $this->firstName = $firstName; return $this; }
    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(?string $lastName): self { $this->lastName = $lastName; return $this; }
    public function getDisplayName(): string
    {
        $fullName = trim((string) $this->firstName . ' ' . (string) $this->lastName);

        return '' !== $fullName ? $fullName : $this->email;
    }
    public function isEmailVerified(): bool { return $this->isEmailVerified; }
    public function setIsEmailVerified(bool $isEmailVerified): self { $this->isEmailVerified = $isEmailVerified; return $this; }
    public function getEmailVerificationTokenHash(): ?string { return $this->emailVerificationTokenHash; }
    public function setEmailVerificationTokenHash(?string $emailVerificationTokenHash): self { $this->emailVerificationTokenHash = $emailVerificationTokenHash; return $this; }
    public function getEmailVerificationTokenExpiresAt(): ?\DateTimeImmutable { return $this->emailVerificationTokenExpiresAt; }
    public function setEmailVerificationTokenExpiresAt(?\DateTimeImmutable $emailVerificationTokenExpiresAt): self { $this->emailVerificationTokenExpiresAt = $emailVerificationTokenExpiresAt; return $this; }
    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): self { $this->phone = $phone; return $this; }
    public function getPassword(): string { return $this->passwordHash; }
    public function getPasswordHash(): string { return $this->passwordHash; }
    public function setPasswordHash(string $passwordHash): self { $this->passwordHash = $passwordHash; return $this; }
    public function getPasswordResetTokenHash(): ?string { return $this->passwordResetTokenHash; }
    public function setPasswordResetTokenHash(?string $passwordResetTokenHash): self { $this->passwordResetTokenHash = $passwordResetTokenHash; return $this; }
    public function getPasswordResetTokenExpiresAt(): ?\DateTimeImmutable { return $this->passwordResetTokenExpiresAt; }
    public function setPasswordResetTokenExpiresAt(?\DateTimeImmutable $passwordResetTokenExpiresAt): self { $this->passwordResetTokenExpiresAt = $passwordResetTokenExpiresAt; return $this; }
    public function getUserIdentifier(): string { return $this->email; }
    public function getRoles(): array { return array_values(array_unique([...$this->roles, 'ROLE_USER'])); }
    public function setRoles(array $roles): self { $this->roles = $roles; return $this; }
    public function eraseCredentials(): void {}
    public function isActive(): bool { return $this->isActive; }
    public function setIsActive(bool $isActive): self { $this->isActive = $isActive; return $this; }
    public function getProfileCompletedPercent(): int { return $this->profileCompletedPercent; }
    public function setProfileCompletedPercent(int $profileCompletedPercent): self { $this->profileCompletedPercent = $profileCompletedPercent; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
    public function isDeleted(): bool { return $this->isDeleted; }
    public function setIsDeleted(bool $isDeleted): self { $this->isDeleted = $isDeleted; return $this; }
    public function getLastLogin(): ?\DateTimeImmutable { return $this->lastLogin; }
    public function setLastLogin(?\DateTimeImmutable $lastLogin): self { $this->lastLogin = $lastLogin; return $this; }
    public function getSubscriptionTier(): UserSubscriptionTier { return $this->subscriptionTier; }
    public function setSubscriptionTier(UserSubscriptionTier $subscriptionTier): self { $this->subscriptionTier = $subscriptionTier; return $this; }
    public function getSubscriptionExpiry(): ?\DateTimeImmutable { return $this->subscriptionExpiry; }
    public function setSubscriptionExpiry(?\DateTimeImmutable $subscriptionExpiry): self { $this->subscriptionExpiry = $subscriptionExpiry; return $this; }
    public function getSubscriptionStartedAt(): ?\DateTimeImmutable { return $this->subscriptionStartedAt; }
    public function setSubscriptionStartedAt(?\DateTimeImmutable $subscriptionStartedAt): self { $this->subscriptionStartedAt = $subscriptionStartedAt; return $this; }
    public function getSwipesUsedToday(): int { return $this->swipesUsedToday; }
    public function setSwipesUsedToday(int $swipesUsedToday): self { $this->swipesUsedToday = $swipesUsedToday; return $this; }
    public function getLastSwipeReset(): \DateTimeImmutable { return $this->lastSwipeReset; }
    public function setLastSwipeReset(\DateTimeImmutable $lastSwipeReset): self { $this->lastSwipeReset = $lastSwipeReset; return $this; }
    public function getCandidateProfile(): ?CandidateProfile { return $this->candidateProfile; }
    public function setCandidateProfile(?CandidateProfile $candidateProfile): self { $this->candidateProfile = $candidateProfile; return $this; }
    public function getEmployer(): ?Employer { return $this->employer; }
    public function setEmployer(?Employer $employer): self { $this->employer = $employer; return $this; }
    public function getCandidateDocuments(): Collection { return $this->candidateDocuments; }
    public function getJobOffers(): Collection { return $this->jobOffers; }
    public function getSwipes(): Collection { return $this->swipes; }
}
