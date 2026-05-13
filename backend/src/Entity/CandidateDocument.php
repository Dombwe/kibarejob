<?php

namespace App\Entity;

use App\Entity\Enum\DocumentType;
use App\Repository\CandidateDocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CandidateDocumentRepository::class)]
#[ORM\Table(name: 'candidate_documents')]
#[ORM\Index(name: 'idx_candidate_documents_owner_uploaded', columns: ['candidate_id', 'is_deleted', 'uploaded_at'])]
#[ORM\Index(name: 'idx_candidate_documents_type', columns: ['candidate_id', 'type', 'is_deleted'])]
class CandidateDocument
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;

    #[ORM\ManyToOne(inversedBy: 'candidateDocuments', targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'candidate_id', referencedColumnName: 'id', nullable: false)]
    private User $candidate;

    #[ORM\Column(enumType: DocumentType::class)]
    private DocumentType $type = DocumentType::Other;

    #[Assert\NotBlank]
    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $issuingOrganization = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $issueDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiryDate = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $documentNumber = null;

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::TEXT)]
    private string $fileUrl = '';

    #[Assert\NotBlank]
    #[Assert\Length(min: 64, max: 64)]
    #[ORM\Column(length: 64)]
    private string $fileHash = '';

    #[ORM\Column(options: ['default' => false])]
    private bool $isVerified = false;

    #[Assert\Range(min: 0, max: 100)]
    #[ORM\Column(options: ['default' => 0])]
    private int $confidenceScore = 0;

    #[ORM\Column(options: ['default' => true])]
    private bool $isPublic = true;

    #[ORM\Column(options: ['default' => false])]
    private bool $isPinned = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $tags = null;

    #[ORM\Column]
    private \DateTimeImmutable $uploadedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastVerifiedAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDeleted = false;

    #[ORM\OneToMany(mappedBy: 'document', targetEntity: DocumentVerificationLog::class)]
    private Collection $verificationLogs;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
        $this->verificationLogs = new ArrayCollection();
    }

    public function getId(): ?UuidInterface { return $this->id; }
    public function getCandidate(): User { return $this->candidate; }
    public function setCandidate(User $candidate): self { $this->candidate = $candidate; return $this; }
    public function getType(): DocumentType { return $this->type; }
    public function setType(DocumentType $type): self { $this->type = $type; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }
    public function getIssuingOrganization(): ?string { return $this->issuingOrganization; }
    public function setIssuingOrganization(?string $issuingOrganization): self { $this->issuingOrganization = $issuingOrganization; return $this; }
    public function getIssueDate(): ?\DateTimeImmutable { return $this->issueDate; }
    public function setIssueDate(?\DateTimeImmutable $issueDate): self { $this->issueDate = $issueDate; return $this; }
    public function getExpiryDate(): ?\DateTimeImmutable { return $this->expiryDate; }
    public function setExpiryDate(?\DateTimeImmutable $expiryDate): self { $this->expiryDate = $expiryDate; return $this; }
    public function getDocumentNumber(): ?string { return $this->documentNumber; }
    public function setDocumentNumber(?string $documentNumber): self { $this->documentNumber = $documentNumber; return $this; }
    public function getFileUrl(): string { return $this->fileUrl; }
    public function setFileUrl(string $fileUrl): self { $this->fileUrl = $fileUrl; return $this; }
    public function getFileHash(): string { return $this->fileHash; }
    public function setFileHash(string $fileHash): self { $this->fileHash = $fileHash; return $this; }
    public function isVerified(): bool { return $this->isVerified; }
    public function setIsVerified(bool $isVerified): self { $this->isVerified = $isVerified; return $this; }
    public function getConfidenceScore(): int { return $this->confidenceScore; }
    public function setConfidenceScore(int $confidenceScore): self { $this->confidenceScore = $confidenceScore; return $this; }
    public function isPublic(): bool { return $this->isPublic; }
    public function setIsPublic(bool $isPublic): self { $this->isPublic = $isPublic; return $this; }
    public function isPinned(): bool { return $this->isPinned; }
    public function setIsPinned(bool $isPinned): self { $this->isPinned = $isPinned; return $this; }
    public function getTags(): ?array { return $this->tags; }
    public function setTags(?array $tags): self { $this->tags = $tags; return $this; }
    public function getUploadedAt(): \DateTimeImmutable { return $this->uploadedAt; }
    public function setUploadedAt(\DateTimeImmutable $uploadedAt): self { $this->uploadedAt = $uploadedAt; return $this; }
    public function getLastVerifiedAt(): ?\DateTimeImmutable { return $this->lastVerifiedAt; }
    public function setLastVerifiedAt(?\DateTimeImmutable $lastVerifiedAt): self { $this->lastVerifiedAt = $lastVerifiedAt; return $this; }
    public function isDeleted(): bool { return $this->isDeleted; }
    public function setIsDeleted(bool $isDeleted): self { $this->isDeleted = $isDeleted; return $this; }
    public function getVerificationLogs(): Collection { return $this->verificationLogs; }
}
