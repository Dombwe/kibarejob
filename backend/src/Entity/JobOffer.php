<?php

namespace App\Entity;

use App\Entity\Enum\ContractType;
use App\Entity\Enum\JobOfferStatus;
use App\Repository\JobOfferRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: JobOfferRepository::class)]
#[ORM\Table(name: 'job_offers')]
#[ORM\Index(name: 'idx_job_offers_feed', columns: ['is_deleted', 'status', 'deadline', 'created_at'])]
#[ORM\Index(name: 'idx_job_offers_employer_created', columns: ['employer_id', 'is_deleted', 'created_at'])]
#[ORM\Index(name: 'idx_job_offers_source_external', columns: ['source_type', 'external_id'])]
class JobOffer
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;

    #[ORM\ManyToOne(inversedBy: 'jobOffers', targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'employer_id', referencedColumnName: 'id', nullable: false)]
    private User $employer;

    #[Assert\NotBlank]
    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(options: ['default' => 1])]
    private int $positions = 1;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::TEXT)]
    private string $description = '';

    #[ORM\Column(type: Types::JSON)]
    private array $requiredSkills = [];

    #[Assert\NotBlank]
    #[ORM\Column(length: 50)]
    private string $requiredEducation = '';

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $educationField = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $requiredExperienceYears = 0;

    #[ORM\Column(nullable: true)]
    private ?int $requiredExperienceYearsMax = null;

    #[ORM\Column(enumType: ContractType::class)]
    private ContractType $contractType = ContractType::Cdi;

    #[Assert\NotBlank]
    #[ORM\Column(length: 100)]
    private string $location = '';

    #[ORM\Column(nullable: true)]
    private ?int $salaryMin = null;

    #[ORM\Column(nullable: true)]
    private ?int $salaryMax = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isRemoteAllowed = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $requiredDocuments = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $recommendedDocuments = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $deadline;

    #[ORM\Column(options: ['default' => false])]
    private bool $isBoosted = false;

    #[ORM\Column(options: ['default' => 0])]
    private int $viewsCount = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $applicationsCount = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $likesCount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $scheduledPublishAt = null;

    #[ORM\Column(enumType: JobOfferStatus::class, options: ['default' => 'active'])]
    private JobOfferStatus $status = JobOfferStatus::Active;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDeleted = false;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $sourceType = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $externalSourceName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $externalUrl = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $applicationEmail = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $reliabilityScore = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $importedAt = null;

    #[ORM\OneToMany(mappedBy: 'offer', targetEntity: Swipe::class)]
    private Collection $swipes;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->deadline = new \DateTimeImmutable('+30 days');
        $this->swipes = new ArrayCollection();
    }

    public function getId(): ?UuidInterface { return $this->id; }
    public function getEmployer(): User { return $this->employer; }
    public function setEmployer(User $employer): self { $this->employer = $employer; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function getPositions(): int { return $this->positions; }
    public function setPositions(int $positions): self { $this->positions = max(1, $positions); return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): self { $this->description = $description; return $this; }
    public function getRequiredSkills(): array { return $this->requiredSkills; }
    public function setRequiredSkills(array $requiredSkills): self { $this->requiredSkills = $requiredSkills; return $this; }
    public function getRequiredEducation(): string { return $this->requiredEducation; }
    public function setRequiredEducation(string $requiredEducation): self { $this->requiredEducation = $requiredEducation; return $this; }
    public function getEducationField(): ?string { return $this->educationField; }
    public function setEducationField(?string $educationField): self { $this->educationField = $educationField; return $this; }
    public function getRequiredExperienceYears(): int { return $this->requiredExperienceYears; }
    public function setRequiredExperienceYears(int $requiredExperienceYears): self { $this->requiredExperienceYears = max(0, $requiredExperienceYears); return $this; }
    public function getRequiredExperienceYearsMax(): ?int { return $this->requiredExperienceYearsMax; }
    public function setRequiredExperienceYearsMax(?int $requiredExperienceYearsMax): self
    {
        $this->requiredExperienceYearsMax = null === $requiredExperienceYearsMax ? null : max(0, $requiredExperienceYearsMax);

        return $this;
    }
    public function getRequiredExperienceLabel(): string
    {
        if (null !== $this->requiredExperienceYearsMax && $this->requiredExperienceYearsMax > $this->requiredExperienceYears) {
            return sprintf('%d - %d ans', $this->requiredExperienceYears, $this->requiredExperienceYearsMax);
        }

        return 0 === $this->requiredExperienceYears
            ? 'Debutant accepte'
            : $this->requiredExperienceYears . ' an' . ($this->requiredExperienceYears > 1 ? 's' : '') . ' minimum';
    }
    public function getContractType(): ContractType { return $this->contractType; }
    public function setContractType(ContractType $contractType): self { $this->contractType = $contractType; return $this; }
    public function getLocation(): string { return $this->location; }
    public function setLocation(string $location): self { $this->location = $location; return $this; }
    public function getSalaryMin(): ?int { return $this->salaryMin; }
    public function setSalaryMin(?int $salaryMin): self { $this->salaryMin = $salaryMin; return $this; }
    public function getSalaryMax(): ?int { return $this->salaryMax; }
    public function setSalaryMax(?int $salaryMax): self { $this->salaryMax = $salaryMax; return $this; }
    public function isRemoteAllowed(): bool { return $this->isRemoteAllowed; }
    public function setIsRemoteAllowed(bool $isRemoteAllowed): self { $this->isRemoteAllowed = $isRemoteAllowed; return $this; }
    public function getRequiredDocuments(): ?array { return $this->requiredDocuments; }
    public function setRequiredDocuments(?array $requiredDocuments): self { $this->requiredDocuments = $requiredDocuments; return $this; }
    public function getRecommendedDocuments(): ?array { return $this->recommendedDocuments; }
    public function setRecommendedDocuments(?array $recommendedDocuments): self { $this->recommendedDocuments = $recommendedDocuments; return $this; }
    public function getDeadline(): \DateTimeImmutable { return $this->deadline; }
    public function setDeadline(\DateTimeImmutable $deadline): self { $this->deadline = $deadline; return $this; }
    public function isBoosted(): bool { return $this->isBoosted; }
    public function setIsBoosted(bool $isBoosted): self { $this->isBoosted = $isBoosted; return $this; }
    public function getViewsCount(): int { return $this->viewsCount; }
    public function setViewsCount(int $viewsCount): self { $this->viewsCount = $viewsCount; return $this; }
    public function getApplicationsCount(): int { return $this->applicationsCount; }
    public function setApplicationsCount(int $applicationsCount): self { $this->applicationsCount = $applicationsCount; return $this; }
    public function getLikesCount(): int { return $this->likesCount; }
    public function setLikesCount(int $likesCount): self { $this->likesCount = max(0, $likesCount); return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
    public function getScheduledPublishAt(): ?\DateTimeImmutable { return $this->scheduledPublishAt; }
    public function setScheduledPublishAt(?\DateTimeImmutable $scheduledPublishAt): self { $this->scheduledPublishAt = $scheduledPublishAt; return $this; }
    public function getStatus(): JobOfferStatus { return $this->status; }
    public function setStatus(JobOfferStatus $status): self { $this->status = $status; return $this; }
    public function isDeleted(): bool { return $this->isDeleted; }
    public function setIsDeleted(bool $isDeleted): self { $this->isDeleted = $isDeleted; return $this; }
    public function getSourceType(): ?string { return $this->sourceType; }
    public function setSourceType(?string $sourceType): self { $this->sourceType = $sourceType; return $this; }
    public function getExternalSourceName(): ?string { return $this->externalSourceName; }
    public function setExternalSourceName(?string $externalSourceName): self { $this->externalSourceName = $externalSourceName; return $this; }
    public function getExternalId(): ?string { return $this->externalId; }
    public function setExternalId(?string $externalId): self { $this->externalId = $externalId; return $this; }
    public function getExternalUrl(): ?string { return $this->externalUrl; }
    public function setExternalUrl(?string $externalUrl): self { $this->externalUrl = $externalUrl; return $this; }
    public function getApplicationEmail(): ?string { return $this->applicationEmail; }
    public function setApplicationEmail(?string $applicationEmail): self { $this->applicationEmail = $applicationEmail ?: null; return $this; }
    public function getReliabilityScore(): int { return $this->reliabilityScore; }
    public function setReliabilityScore(int $reliabilityScore): self { $this->reliabilityScore = max(0, min(100, $reliabilityScore)); return $this; }
    public function getImportedAt(): ?\DateTimeImmutable { return $this->importedAt; }
    public function setImportedAt(?\DateTimeImmutable $importedAt): self { $this->importedAt = $importedAt; return $this; }
    public function getSwipes(): Collection { return $this->swipes; }
}
