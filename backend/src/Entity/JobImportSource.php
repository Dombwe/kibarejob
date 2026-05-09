<?php

namespace App\Entity;

use App\Repository\JobImportSourceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: JobImportSourceRepository::class)]
#[ORM\Table(name: 'job_import_sources')]
class JobImportSource
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?UuidInterface $id = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 120)]
    private string $name = '';

    #[Assert\NotBlank]
    #[ORM\Column(length: 80, options: ['default' => 'generic_json'])]
    private string $provider = 'generic_json';

    #[Assert\NotBlank]
    #[ORM\Column(type: Types::TEXT)]
    private string $apiUrl = '';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $rapidApiHost = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $apiKeyEnvName = null;

    #[ORM\Column(length: 10, options: ['default' => 'GET'])]
    private string $httpMethod = 'GET';

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $headers = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $queryParams = null;

    #[ORM\Column(length: 160, options: ['default' => 'data'])]
    private string $itemsPath = 'data';

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $fieldMapping = null;

    #[ORM\Column(type: Types::JSON)]
    private array $targetCountries = ['Burkina Faso'];

    #[ORM\Column(type: Types::JSON)]
    private array $targetLocations = ['Burkina Faso', 'Ouagadougou', 'Bobo-Dioulasso', 'Africa', 'Afrique'];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $keywords = null;

    #[ORM\Column(options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(options: ['default' => true])]
    private bool $autoPublish = true;

    #[ORM\Column(options: ['default' => 50])]
    private int $maxItemsPerRun = 50;

    #[ORM\Column(options: ['default' => 55])]
    private int $minReliabilityScore = 55;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastRunAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastSuccessAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $lastImportedCount = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $lastSkippedCount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $lastStatusMessage = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->fieldMapping = self::defaultFieldMapping();
    }

    public static function defaultFieldMapping(): array
    {
        return [
            'externalId' => 'job_id',
            'title' => 'job_title',
            'company' => 'employer_name',
            'description' => 'job_description',
            'location' => 'job_city',
            'country' => 'job_country',
            'url' => 'job_apply_link',
            'contractType' => 'job_employment_type',
            'datePosted' => 'job_posted_at_datetime_utc',
        ];
    }

    public function __toString(): string
    {
        return $this->name ?: 'Source externe';
    }

    public function getId(): ?UuidInterface { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getProvider(): string { return $this->provider; }
    public function setProvider(string $provider): self { $this->provider = $provider; return $this; }
    public function getApiUrl(): string { return $this->apiUrl; }
    public function setApiUrl(string $apiUrl): self { $this->apiUrl = $apiUrl; return $this; }
    public function getRapidApiHost(): ?string { return $this->rapidApiHost; }
    public function setRapidApiHost(?string $rapidApiHost): self { $this->rapidApiHost = $rapidApiHost; return $this; }
    public function getApiKeyEnvName(): ?string { return $this->apiKeyEnvName; }
    public function setApiKeyEnvName(?string $apiKeyEnvName): self { $this->apiKeyEnvName = $apiKeyEnvName; return $this; }
    public function getHttpMethod(): string { return $this->httpMethod; }
    public function setHttpMethod(string $httpMethod): self { $this->httpMethod = strtoupper($httpMethod); return $this; }
    public function getHeaders(): ?array { return $this->headers; }
    public function setHeaders(?array $headers): self { $this->headers = $headers; return $this; }
    public function getHeadersJson(): string { return $this->encodeJson($this->headers ?? []); }
    public function setHeadersJson(?string $headersJson): self { $this->headers = $this->decodeJson($headersJson); return $this; }
    public function getQueryParams(): ?array { return $this->queryParams; }
    public function setQueryParams(?array $queryParams): self { $this->queryParams = $queryParams; return $this; }
    public function getQueryParamsJson(): string { return $this->encodeJson($this->queryParams ?? []); }
    public function setQueryParamsJson(?string $queryParamsJson): self { $this->queryParams = $this->decodeJson($queryParamsJson); return $this; }
    public function getItemsPath(): string { return $this->itemsPath; }
    public function setItemsPath(string $itemsPath): self { $this->itemsPath = $itemsPath; return $this; }
    public function getFieldMapping(): ?array { return $this->fieldMapping; }
    public function setFieldMapping(?array $fieldMapping): self { $this->fieldMapping = $fieldMapping; return $this; }
    public function getFieldMappingJson(): string { return $this->encodeJson($this->fieldMapping ?? self::defaultFieldMapping()); }
    public function setFieldMappingJson(?string $fieldMappingJson): self { $this->fieldMapping = $this->decodeJson($fieldMappingJson) ?: self::defaultFieldMapping(); return $this; }
    public function getTargetCountries(): array { return $this->targetCountries; }
    public function setTargetCountries(array $targetCountries): self { $this->targetCountries = array_values($targetCountries); return $this; }
    public function getTargetLocations(): array { return $this->targetLocations; }
    public function setTargetLocations(array $targetLocations): self { $this->targetLocations = array_values($targetLocations); return $this; }
    public function getKeywords(): ?array { return $this->keywords; }
    public function setKeywords(?array $keywords): self { $this->keywords = $keywords; return $this; }
    public function isEnabled(): bool { return $this->enabled; }
    public function setEnabled(bool $enabled): self { $this->enabled = $enabled; return $this; }
    public function isAutoPublish(): bool { return $this->autoPublish; }
    public function setAutoPublish(bool $autoPublish): self { $this->autoPublish = $autoPublish; return $this; }
    public function getMaxItemsPerRun(): int { return $this->maxItemsPerRun; }
    public function setMaxItemsPerRun(int $maxItemsPerRun): self { $this->maxItemsPerRun = max(1, $maxItemsPerRun); return $this; }
    public function getMinReliabilityScore(): int { return $this->minReliabilityScore; }
    public function setMinReliabilityScore(int $minReliabilityScore): self { $this->minReliabilityScore = max(0, min(100, $minReliabilityScore)); return $this; }
    public function getLastRunAt(): ?\DateTimeImmutable { return $this->lastRunAt; }
    public function setLastRunAt(?\DateTimeImmutable $lastRunAt): self { $this->lastRunAt = $lastRunAt; return $this; }
    public function getLastSuccessAt(): ?\DateTimeImmutable { return $this->lastSuccessAt; }
    public function setLastSuccessAt(?\DateTimeImmutable $lastSuccessAt): self { $this->lastSuccessAt = $lastSuccessAt; return $this; }
    public function getLastError(): ?string { return $this->lastError; }
    public function setLastError(?string $lastError): self { $this->lastError = $lastError; return $this; }
    public function getLastImportedCount(): int { return $this->lastImportedCount; }
    public function setLastImportedCount(int $lastImportedCount): self { $this->lastImportedCount = $lastImportedCount; return $this; }
    public function getLastSkippedCount(): int { return $this->lastSkippedCount; }
    public function setLastSkippedCount(int $lastSkippedCount): self { $this->lastSkippedCount = $lastSkippedCount; return $this; }
    public function getLastStatusMessage(): ?string { return $this->lastStatusMessage; }
    public function setLastStatusMessage(?string $lastStatusMessage): self { $this->lastStatusMessage = $lastStatusMessage; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }

    public function getImportStatus(): string
    {
        if (null !== $this->lastError && '' !== trim($this->lastError)) {
            return 'Erreur : ' . mb_strimwidth($this->lastError, 0, 90, '...');
        }

        if (null !== $this->lastStatusMessage && '' !== trim($this->lastStatusMessage)) {
            return mb_strimwidth($this->lastStatusMessage, 0, 100, '...');
        }

        if (null === $this->lastRunAt) {
            return 'Jamais lance';
        }

        return sprintf('%d importee(s), %d ignoree(s)', $this->lastImportedCount, $this->lastSkippedCount);
    }

    public function getImportLink(): string
    {
        return '';
    }

    private function encodeJson(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    }

    private function decodeJson(?string $json): ?array
    {
        $json = trim((string) $json);
        if ('' === $json) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }
}
