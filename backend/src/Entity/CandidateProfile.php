<?php

namespace App\Entity;

use App\Repository\CandidateProfileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CandidateProfileRepository::class)]
#[ORM\Table(name: 'candidate_profiles')]
class CandidateProfile
{
    #[ORM\Id]
    #[ORM\OneToOne(inversedBy: 'candidateProfile', targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[ORM\Column(length: 100)]
    private string $firstName = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[ORM\Column(length: 100)]
    private string $lastName = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $photoUrl = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $birthDate = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[ORM\Column(length: 100)]
    private string $city = '';

    #[Assert\NotBlank]
    #[Assert\Choice(['Aucun', 'CEP', 'BEPC', 'Bac', 'Bac +1', 'Bac +2', 'Bac +3', 'Bac +4', 'Bac +5', 'Bac +6', 'Bac +7', 'Bac +8', 'Bac +9', 'Bac +10', 'Bac +11', 'Bac +12', 'Licence', 'Master', 'Doctorat'])]
    #[ORM\Column(length: 50)]
    private string $educationLevel = '';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $educationField = null;

    #[ORM\Column(type: Types::JSON)]
    private array $skills = [];

    #[ORM\Column(type: Types::JSON)]
    private array $languages = [];

    #[ORM\Column(type: Types::JSON)]
    private array $experiences = [];

    #[ORM\Column(type: Types::JSON)]
    private array $interests = [];

    #[ORM\Column(name: 'profile_references', type: Types::JSON)]
    private array $references = [];

    #[ORM\Column(options: ['default' => false])]
    private bool $drivingLicense = false;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $drivingLicenseCategory = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 50)]
    private string $availability = '';

    #[ORM\Column(nullable: true)]
    private ?int $salaryExpectation = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cvOriginalUrl = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cvGeneratedUrl = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $cvLastUpdated = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $isDeleted = false;

    public function getUser(): User { return $this->user; }
    public function setUser(User $user): self { $this->user = $user; return $this; }
    public function getFirstName(): string { return $this->firstName; }
    public function setFirstName(string $firstName): self { $this->firstName = $firstName; return $this; }
    public function getLastName(): string { return $this->lastName; }
    public function setLastName(string $lastName): self { $this->lastName = $lastName; return $this; }
    public function getPhotoUrl(): ?string { return $this->photoUrl; }
    public function setPhotoUrl(?string $photoUrl): self { $this->photoUrl = $photoUrl; return $this; }
    public function getBirthDate(): ?\DateTimeImmutable { return $this->birthDate; }
    public function setBirthDate(?\DateTimeImmutable $birthDate): self { $this->birthDate = $birthDate; return $this; }
    public function getCity(): string { return $this->city; }
    public function setCity(string $city): self { $this->city = $city; return $this; }
    public function getEducationLevel(): string { return $this->educationLevel; }
    public function setEducationLevel(string $educationLevel): self { $this->educationLevel = $educationLevel; return $this; }
    public function getEducationField(): ?string { return $this->educationField; }
    public function setEducationField(?string $educationField): self { $this->educationField = $educationField; return $this; }
    public function getSkills(): array { return $this->skills; }
    public function setSkills(array $skills): self { $this->skills = $skills; return $this; }
    public function getLanguages(): array { return $this->languages; }
    public function setLanguages(array $languages): self { $this->languages = $languages; return $this; }
    public function getExperiences(): array { return $this->experiences; }
    public function setExperiences(array $experiences): self { $this->experiences = $experiences; return $this; }
    public function getInterests(): array { return $this->interests; }
    public function setInterests(array $interests): self { $this->interests = $interests; return $this; }
    public function getReferences(): array { return $this->references; }
    public function setReferences(array $references): self { $this->references = $references; return $this; }
    public function hasDrivingLicense(): bool { return $this->drivingLicense; }
    public function setDrivingLicense(bool $drivingLicense): self { $this->drivingLicense = $drivingLicense; return $this; }
    public function getDrivingLicenseCategory(): ?string { return $this->drivingLicenseCategory; }
    public function setDrivingLicenseCategory(?string $drivingLicenseCategory): self { $this->drivingLicenseCategory = $drivingLicenseCategory; return $this; }
    public function getAvailability(): string { return $this->availability; }
    public function setAvailability(string $availability): self { $this->availability = $availability; return $this; }
    public function getSalaryExpectation(): ?int { return $this->salaryExpectation; }
    public function setSalaryExpectation(?int $salaryExpectation): self { $this->salaryExpectation = $salaryExpectation; return $this; }
    public function getCvOriginalUrl(): ?string { return $this->cvOriginalUrl; }
    public function setCvOriginalUrl(?string $cvOriginalUrl): self { $this->cvOriginalUrl = $cvOriginalUrl; return $this; }
    public function getCvGeneratedUrl(): ?string { return $this->cvGeneratedUrl; }
    public function setCvGeneratedUrl(?string $cvGeneratedUrl): self { $this->cvGeneratedUrl = $cvGeneratedUrl; return $this; }
    public function getCvLastUpdated(): ?\DateTimeImmutable { return $this->cvLastUpdated; }
    public function setCvLastUpdated(?\DateTimeImmutable $cvLastUpdated): self { $this->cvLastUpdated = $cvLastUpdated; return $this; }
    public function isDeleted(): bool { return $this->isDeleted; }
    public function setIsDeleted(bool $isDeleted): self { $this->isDeleted = $isDeleted; return $this; }
}
