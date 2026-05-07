<?php

namespace App\Entity;

use App\Entity\Enum\DocumentRequestStatus;
use App\Repository\DocumentRequestRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DocumentRequestRepository::class)]
#[ORM\Table(name: 'document_requests')]
class DocumentRequest
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'employer_id', referencedColumnName: 'id', nullable: false)]
    private User $employer;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'candidate_id', referencedColumnName: 'id', nullable: false)]
    private User $candidate;

    #[ORM\ManyToOne(targetEntity: JobOffer::class)]
    #[ORM\JoinColumn(name: 'offer_id', referencedColumnName: 'id', nullable: false)]
    private JobOffer $offer;

    #[Assert\NotBlank]
    #[ORM\Column(length: 100)]
    private string $documentType = '';

    #[ORM\Column(enumType: DocumentRequestStatus::class, options: ['default' => 'pending'])]
    private DocumentRequestStatus $status = DocumentRequestStatus::Pending;

    #[ORM\Column]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $providedAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDeleted = false;

    public function __construct() { $this->requestedAt = new \DateTimeImmutable(); }
    public function getId(): ?UuidInterface { return $this->id; }
    public function getEmployer(): User { return $this->employer; }
    public function setEmployer(User $employer): self { $this->employer = $employer; return $this; }
    public function getCandidate(): User { return $this->candidate; }
    public function setCandidate(User $candidate): self { $this->candidate = $candidate; return $this; }
    public function getOffer(): JobOffer { return $this->offer; }
    public function setOffer(JobOffer $offer): self { $this->offer = $offer; return $this; }
    public function getDocumentType(): string { return $this->documentType; }
    public function setDocumentType(string $documentType): self { $this->documentType = $documentType; return $this; }
    public function getStatus(): DocumentRequestStatus { return $this->status; }
    public function setStatus(DocumentRequestStatus $status): self { $this->status = $status; return $this; }
    public function getRequestedAt(): \DateTimeImmutable { return $this->requestedAt; }
    public function setRequestedAt(\DateTimeImmutable $requestedAt): self { $this->requestedAt = $requestedAt; return $this; }
    public function getProvidedAt(): ?\DateTimeImmutable { return $this->providedAt; }
    public function setProvidedAt(?\DateTimeImmutable $providedAt): self { $this->providedAt = $providedAt; return $this; }
    public function isDeleted(): bool { return $this->isDeleted; }
    public function setIsDeleted(bool $isDeleted): self { $this->isDeleted = $isDeleted; return $this; }
}
