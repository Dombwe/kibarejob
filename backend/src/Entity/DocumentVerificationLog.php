<?php

namespace App\Entity;

use App\Entity\Enum\VerificationAction;
use App\Repository\DocumentVerificationLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DocumentVerificationLogRepository::class)]
#[ORM\Table(name: 'document_verification_logs')]
class DocumentVerificationLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;

    #[ORM\ManyToOne(inversedBy: 'verificationLogs', targetEntity: CandidateDocument::class)]
    #[ORM\JoinColumn(name: 'document_id', referencedColumnName: 'id', nullable: false)]
    private CandidateDocument $document;

    #[ORM\Column]
    private \DateTimeImmutable $verificationDate;

    #[Assert\Range(min: 0, max: 100)]
    #[ORM\Column]
    private int $aiScore = 0;

    #[ORM\Column(options: ['default' => false])]
    private bool $adminOverride = false;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'admin_id', referencedColumnName: 'id', nullable: true)]
    private ?User $admin = null;

    #[ORM\Column(enumType: VerificationAction::class)]
    private VerificationAction $action = VerificationAction::Flagged;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDeleted = false;

    public function __construct() { $this->verificationDate = new \DateTimeImmutable(); }
    public function getId(): ?UuidInterface { return $this->id; }
    public function getDocument(): CandidateDocument { return $this->document; }
    public function setDocument(CandidateDocument $document): self { $this->document = $document; return $this; }
    public function getVerificationDate(): \DateTimeImmutable { return $this->verificationDate; }
    public function setVerificationDate(\DateTimeImmutable $verificationDate): self { $this->verificationDate = $verificationDate; return $this; }
    public function getAiScore(): int { return $this->aiScore; }
    public function setAiScore(int $aiScore): self { $this->aiScore = $aiScore; return $this; }
    public function isAdminOverride(): bool { return $this->adminOverride; }
    public function setAdminOverride(bool $adminOverride): self { $this->adminOverride = $adminOverride; return $this; }
    public function getAdmin(): ?User { return $this->admin; }
    public function setAdmin(?User $admin): self { $this->admin = $admin; return $this; }
    public function getAction(): VerificationAction { return $this->action; }
    public function setAction(VerificationAction $action): self { $this->action = $action; return $this; }
    public function getReason(): ?string { return $this->reason; }
    public function setReason(?string $reason): self { $this->reason = $reason; return $this; }
    public function isDeleted(): bool { return $this->isDeleted; }
    public function setIsDeleted(bool $isDeleted): self { $this->isDeleted = $isDeleted; return $this; }
}
