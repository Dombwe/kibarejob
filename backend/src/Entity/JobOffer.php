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

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::TEXT)]
    private string $description = '';

    #[ORM\Column(type: Types::JSON)]
    private array $requiredSkills = [];

    #[Assert\NotBlank]
    #[ORM\Column(length: 50)]
    private string $requiredEducation = '';

    #[ORM\Column(options: ['default' => 0])]
    private int $requiredExperienceYears = 0;

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

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(enumType: JobOfferStatus::class, options: ['default' => 'active'])]
    private JobOfferStatus $status = JobOfferStatus::Active;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDeleted = false;

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
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): self { $this->description = $description; return $this; }
    public function getRequiredSkills(): array { return $this->requiredSkills; }
    public function setRequiredSkills(array $requiredSkills): self { $this->requiredSkills = $requiredSkills; return $this; }
    public function getRequiredEducation(): string { return $this->requiredEducation; }
    public function setRequiredEducation(string $requiredEducation): self { $this->requiredEducation = $requiredEducation; return $this; }
    public function getRequiredExperienceYears(): int { return $this->requiredExperienceYears; }
    public function setRequiredExperienceYears(int $requiredExperienceYears): self { $this->requiredExperienceYears = $requiredExperienceYears; return $this; }
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
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
    public function getStatus(): JobOfferStatus { return $this->status; }
    public function setStatus(JobOfferStatus $status): self { $this->status = $status; return $this; }
    public function isDeleted(): bool { return $this->isDeleted; }
    public function setIsDeleted(bool $isDeleted): self { $this->isDeleted = $isDeleted; return $this; }
    public function getSwipes(): Collection { return $this->swipes; }
}
