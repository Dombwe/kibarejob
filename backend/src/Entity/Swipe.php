<?php

namespace App\Entity;

use App\Entity\Enum\SwipeDirection;
use App\Entity\Enum\SwipeStatus;
use App\Repository\SwipeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SwipeRepository::class)]
#[ORM\Table(name: 'swipes')]
#[ORM\UniqueConstraint(name: 'uniq_swipe_candidate_offer', columns: ['candidate_id', 'offer_id'])]
#[ORM\Index(name: 'idx_swipes_candidate_sent', columns: ['candidate_id', 'is_deleted', 'direction', 'sent_at'])]
#[ORM\Index(name: 'idx_swipes_offer_status', columns: ['offer_id', 'is_deleted', 'status', 'sent_at'])]
class Swipe
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;

    #[ORM\ManyToOne(inversedBy: 'swipes', targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'candidate_id', referencedColumnName: 'id', nullable: false)]
    private User $candidate;

    #[ORM\ManyToOne(inversedBy: 'swipes', targetEntity: JobOffer::class)]
    #[ORM\JoinColumn(name: 'offer_id', referencedColumnName: 'id', nullable: false)]
    private JobOffer $offer;

    #[ORM\Column(enumType: SwipeDirection::class)]
    private SwipeDirection $direction = SwipeDirection::Like;

    #[Assert\Range(min: 0, max: 100)]
    #[ORM\Column(nullable: true)]
    private ?int $matchScore = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cvUsedUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motivationLetterText = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $documentsSent = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $emailSent = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emailRecipient = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emailSender = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emailReplyTo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emailSubject = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $emailBody = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $emailError = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $emailSentAt = null;

    #[ORM\Column(enumType: SwipeStatus::class, options: ['default' => 'sent'])]
    private SwipeStatus $status = SwipeStatus::Sent;

    #[ORM\Column]
    private \DateTimeImmutable $sentAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $viewedAt = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDeleted = false;

    public function __construct() { $this->sentAt = new \DateTimeImmutable(); }
    public function getId(): ?UuidInterface { return $this->id; }
    public function getCandidate(): User { return $this->candidate; }
    public function setCandidate(User $candidate): self { $this->candidate = $candidate; return $this; }
    public function getOffer(): JobOffer { return $this->offer; }
    public function setOffer(JobOffer $offer): self { $this->offer = $offer; return $this; }
    public function getDirection(): SwipeDirection { return $this->direction; }
    public function setDirection(SwipeDirection $direction): self { $this->direction = $direction; return $this; }
    public function getMatchScore(): ?int { return $this->matchScore; }
    public function setMatchScore(?int $matchScore): self { $this->matchScore = $matchScore; return $this; }
    public function getCvUsedUrl(): ?string { return $this->cvUsedUrl; }
    public function setCvUsedUrl(?string $cvUsedUrl): self { $this->cvUsedUrl = $cvUsedUrl; return $this; }
    public function getMotivationLetterText(): ?string { return $this->motivationLetterText; }
    public function setMotivationLetterText(?string $motivationLetterText): self { $this->motivationLetterText = $motivationLetterText; return $this; }
    public function getDocumentsSent(): ?array { return $this->documentsSent; }
    public function setDocumentsSent(?array $documentsSent): self { $this->documentsSent = $documentsSent; return $this; }
    public function isEmailSent(): bool { return $this->emailSent; }
    public function setEmailSent(bool $emailSent): self { $this->emailSent = $emailSent; return $this; }
    public function getEmailRecipient(): ?string { return $this->emailRecipient; }
    public function setEmailRecipient(?string $emailRecipient): self { $this->emailRecipient = $emailRecipient; return $this; }
    public function getEmailSender(): ?string { return $this->emailSender; }
    public function setEmailSender(?string $emailSender): self { $this->emailSender = $emailSender; return $this; }
    public function getEmailReplyTo(): ?string { return $this->emailReplyTo; }
    public function setEmailReplyTo(?string $emailReplyTo): self { $this->emailReplyTo = $emailReplyTo; return $this; }
    public function getEmailSubject(): ?string { return $this->emailSubject; }
    public function setEmailSubject(?string $emailSubject): self { $this->emailSubject = $emailSubject; return $this; }
    public function getEmailBody(): ?string { return $this->emailBody; }
    public function setEmailBody(?string $emailBody): self { $this->emailBody = $emailBody; return $this; }
    public function getEmailError(): ?string { return $this->emailError; }
    public function setEmailError(?string $emailError): self { $this->emailError = $emailError; return $this; }
    public function getEmailSentAt(): ?\DateTimeImmutable { return $this->emailSentAt; }
    public function setEmailSentAt(?\DateTimeImmutable $emailSentAt): self { $this->emailSentAt = $emailSentAt; return $this; }
    public function getStatus(): SwipeStatus { return $this->status; }
    public function setStatus(SwipeStatus $status): self { $this->status = $status; return $this; }
    public function getSentAt(): \DateTimeImmutable { return $this->sentAt; }
    public function setSentAt(\DateTimeImmutable $sentAt): self { $this->sentAt = $sentAt; return $this; }
    public function getViewedAt(): ?\DateTimeImmutable { return $this->viewedAt; }
    public function setViewedAt(?\DateTimeImmutable $viewedAt): self { $this->viewedAt = $viewedAt; return $this; }
    public function isDeleted(): bool { return $this->isDeleted; }
    public function setIsDeleted(bool $isDeleted): self { $this->isDeleted = $isDeleted; return $this; }
}
