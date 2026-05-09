<?php

namespace App\Service;

use App\Entity\Employer;
use App\Entity\Enum\ContractType;
use App\Entity\Enum\EmployerSubscriptionTier;
use App\Entity\Enum\JobOfferStatus;
use App\Entity\JobImportSource;
use App\Entity\JobOffer;
use App\Entity\User;
use App\Repository\JobImportSourceRepository;
use App\Repository\JobOfferRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ExternalJobImportService
{
    public function __construct(
        private readonly JobImportSourceRepository $sourceRepository,
        private readonly JobOfferRepository $jobOfferRepository,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function importAllEnabled(?int $limit = null): array
    {
        $summary = ['sources' => 0, 'imported' => 0, 'skipped' => 0, 'errors' => []];
        $sources = $this->sourceRepository->findBy(['enabled' => true], ['createdAt' => 'ASC']);

        foreach ($sources as $source) {
            $summary['sources']++;
            try {
                $result = $this->importSource($source, $limit);
                $summary['imported'] += $result['imported'];
                $summary['skipped'] += $result['skipped'];
            } catch (\Throwable $exception) {
                $source
                    ->setLastRunAt(new \DateTimeImmutable())
                    ->setLastError($exception->getMessage())
                    ->setLastImportedCount(0)
                    ->setLastSkippedCount(0)
                    ->setLastStatusMessage('Import echoue : ' . $exception->getMessage());
                $this->entityManager->flush();
                $summary['errors'][] = sprintf('%s: %s', $source->getName(), $exception->getMessage());
            }
        }

        return $summary;
    }

    public function importSource(JobImportSource $source, ?int $limit = null): array
    {
        $source
            ->setLastRunAt(new \DateTimeImmutable())
            ->setLastError(null)
            ->setLastStatusMessage('Import en cours...');

        $items = $this->fetchItems($source);
        $items = array_slice($items, 0, $limit ?? $source->getMaxItemsPerRun());
        $systemEmployer = $this->getOrCreateSystemEmployer();
        $imported = 0;
        $skipped = 0;
        $skipReasons = [
            'locality' => 0,
            'reliability' => 0,
            'duplicate' => 0,
            'invalid' => 0,
        ];

        foreach ($items as $item) {
            if (!is_array($item)) {
                $skipped++;
                $skipReasons['invalid']++;
                continue;
            }

            $normalized = $this->normalizeItem($source, $item);
            if ('' === $normalized['title'] || ('' === $normalized['company'] && '' === $normalized['description'] && '' === $normalized['url'])) {
                $skipped++;
                $skipReasons['invalid']++;
                continue;
            }

            $score = $this->calculateReliabilityScore($source, $normalized);

            if ($score < $source->getMinReliabilityScore()) {
                $skipReasons['reliability']++;
            }

            if (!$this->matchesTargetLocality($source, $normalized)) {
                $skipReasons['locality']++;
            }

            if ($this->alreadyImported($source, $normalized)) {
                $skipped++;
                $skipReasons['duplicate']++;
                continue;
            }

            $offer = (new JobOffer())
                ->setEmployer($systemEmployer)
                ->setTitle($normalized['title'])
                ->setDescription($this->buildDescription($source, $normalized))
                ->setRequiredSkills($normalized['skills'])
                ->setRequiredEducation('Non précisé')
                ->setRequiredExperienceYears(0)
                ->setContractType($this->mapContractType($normalized['contractType']))
                ->setLocation($normalized['location'])
                ->setDeadline(new \DateTimeImmutable('+30 days'))
                ->setStatus($source->isAutoPublish() ? JobOfferStatus::Active : JobOfferStatus::Draft)
                ->setRequiredDocuments([
                    ['name' => 'CV', 'required' => true],
                    ['name' => 'Lettre de motivation', 'required' => false],
                ])
                ->setSourceType('external_api')
                ->setExternalSourceName($source->getName())
                ->setExternalId($normalized['externalId'])
                ->setExternalUrl($normalized['url'])
                ->setApplicationEmail($normalized['applicationEmail'])
                ->setReliabilityScore($score)
                ->setImportedAt(new \DateTimeImmutable());

            $this->entityManager->persist($offer);
            $imported++;
        }

        $source
            ->setLastSuccessAt(new \DateTimeImmutable())
            ->setLastImportedCount($imported)
            ->setLastSkippedCount($skipped)
            ->setLastStatusMessage($this->buildStatusMessage($imported, $skipped, $skipReasons));

        $this->entityManager->flush();

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    private function buildStatusMessage(int $imported, int $skipped, array $skipReasons): string
    {
        if (0 === $skipped) {
            return sprintf('Dernier import : %d offre(s) importee(s), 0 ignoree.', $imported);
        }

        $details = [];
        if (($skipReasons['locality'] ?? 0) > 0) {
            $details[] = $skipReasons['locality'] . ' hors zone cible';
        }
        if (($skipReasons['reliability'] ?? 0) > 0) {
            $details[] = $skipReasons['reliability'] . ' score faible';
        }
        if (($skipReasons['duplicate'] ?? 0) > 0) {
            $details[] = $skipReasons['duplicate'] . ' doublon';
        }
        if (($skipReasons['invalid'] ?? 0) > 0) {
            $details[] = $skipReasons['invalid'] . ' format invalide';
        }

        return sprintf(
            'Dernier import : %d importee(s), %d ignoree(s)%s.',
            $imported,
            $skipped,
            [] !== $details ? ' (' . implode(', ', $details) . ')' : ''
        );
    }

    private function fetchItems(JobImportSource $source): array
    {
        $url = $this->buildUrl($source);
        if ($this->isRapidApiRootUrl($source->getApiUrl())) {
            throw new \RuntimeException('URL API incomplète : ajoutez le chemin de l’endpoint RapidAPI, par exemple /active-jb-7d pour LinkedIn Job Search API.');
        }

        $headers = $source->getHeaders() ?? [];

        if ($source->getRapidApiHost()) {
            $headers['X-RapidAPI-Host'] = $source->getRapidApiHost();
        }

        if ($source->getApiKeyEnvName()) {
            $apiKey = $_ENV[$source->getApiKeyEnvName()] ?? $_SERVER[$source->getApiKeyEnvName()] ?? null;
            if ($apiKey) {
                $headers['X-RapidAPI-Key'] = $apiKey;
            }
        }

        $context = stream_context_create([
            'http' => [
                'method' => $source->getHttpMethod(),
                'timeout' => 20,
                'ignore_errors' => true,
                'header' => $this->formatHeaders($headers),
            ],
        ]);

        $payload = @file_get_contents($url, false, $context);
        if (false === $payload) {
            throw new \RuntimeException('Impossible de contacter la source externe.');
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('La source externe ne retourne pas un JSON valide.');
        }

        foreach (['message', 'error', 'errors'] as $errorKey) {
            if (array_key_exists($errorKey, $decoded) && !empty($decoded[$errorKey])) {
                $message = is_scalar($decoded[$errorKey]) ? (string) $decoded[$errorKey] : json_encode($decoded[$errorKey], JSON_UNESCAPED_UNICODE);

                throw new \RuntimeException('Réponse API : ' . $message);
            }
        }

        $itemsPath = trim($source->getItemsPath());
        $items = '' === $itemsPath ? $decoded : $this->readPath($decoded, $itemsPath);
        if (!is_array($items)) {
            throw new \RuntimeException(sprintf('Aucune liste d’offres trouvée au chemin JSON "%s". Vérifiez le champ "Chemin des offres".', $source->getItemsPath()));
        }

        if ([] === $items) {
            throw new \RuntimeException('La source API a répondu correctement, mais aucune offre n’a été retournée pour ces paramètres.');
        }

        return $items;
    }

    private function isRapidApiRootUrl(string $apiUrl): bool
    {
        $parts = parse_url($apiUrl);
        if (!is_array($parts)) {
            return false;
        }

        $host = $parts['host'] ?? '';
        $path = trim($parts['path'] ?? '', '/');

        return str_ends_with($host, '.p.rapidapi.com') && '' === $path;
    }

    private function buildUrl(JobImportSource $source): string
    {
        $params = $source->getQueryParams() ?? [];
        $query = http_build_query($params);

        if ('' === $query) {
            return $source->getApiUrl();
        }

        return $source->getApiUrl() . (str_contains($source->getApiUrl(), '?') ? '&' : '?') . $query;
    }

    private function formatHeaders(array $headers): string
    {
        $lines = ['Accept: application/json'];
        foreach ($headers as $name => $value) {
            if (is_string($name) && null !== $value && '' !== (string) $value) {
                $lines[] = $name . ': ' . $value;
            }
        }

        return implode("\r\n", $lines);
    }

    private function normalizeItem(JobImportSource $source, array $item): array
    {
        $mapping = array_replace(JobImportSource::defaultFieldMapping(), $source->getFieldMapping() ?? []);
        $title = $this->stringValue($this->readPath($item, $mapping['title'] ?? 'title'));
        $company = $this->stringValue($this->readPath($item, $mapping['company'] ?? 'company'));
        $description = $this->stringValue($this->readPath($item, $mapping['description'] ?? 'description'));
        $city = $this->stringValue($this->readPath($item, $mapping['location'] ?? 'location'));
        $country = $this->stringValue($this->readPath($item, $mapping['country'] ?? 'country'));
        $url = $this->stringValue($this->readPath($item, $mapping['url'] ?? 'url'));
        $externalId = $this->stringValue($this->readPath($item, $mapping['externalId'] ?? 'id')) ?: sha1($source->getName() . $title . $company . $url);
        $contractType = $this->stringValue($this->readPath($item, $mapping['contractType'] ?? 'contractType'));
        $skills = $this->readPath($item, $mapping['skills'] ?? 'skills');
        $provider = $this->stringValue($this->readPath($item, $mapping['provider'] ?? 'provider'));
        $postedTimeAgo = $this->stringValue($this->readPath($item, $mapping['postedTimeAgo'] ?? 'postedTimeAgo'));
        $applicationEmail = $this->stringValue($this->readPath($item, $mapping['applicationEmail'] ?? 'applicationEmail'));
        $applicationEmail = $this->validEmail($applicationEmail) ?: $this->extractEmail($description);

        return [
            'externalId' => $externalId,
            'title' => '' !== $title ? $title : 'Offre importée',
            'company' => $company,
            'description' => $description,
            'location' => trim($city . ('' !== $country ? ', ' . $country : '')) ?: 'Burkina Faso',
            'country' => $country,
            'url' => $url,
            'contractType' => $contractType,
            'skills' => is_array($skills) ? array_values(array_filter($skills)) : $this->inferSkills($title . ' ' . $description),
            'provider' => $provider,
            'postedTimeAgo' => $postedTimeAgo,
            'applicationEmail' => $applicationEmail,
        ];
    }

    private function readPath(array $data, ?string $path): mixed
    {
        if (null === $path || '' === $path) {
            return null;
        }

        $value = $data;
        foreach (explode('.', $path) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
                continue;
            }

            return null;
        }

        return $value;
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function validEmail(string $value): string
    {
        return false !== filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
    }

    private function extractEmail(string $text): string
    {
        if (1 !== preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches)) {
            return '';
        }

        return $this->validEmail($matches[0]);
    }

    private function calculateReliabilityScore(JobImportSource $source, array $job): int
    {
        $score = 20;
        $score += '' !== $job['title'] ? 20 : 0;
        $score += '' !== $job['company'] ? 15 : 0;
        $score += strlen($job['description']) >= 120 ? 20 : 0;
        $score += filter_var($job['url'], FILTER_VALIDATE_URL) ? 15 : 0;
        $score += $this->matchesTargetLocality($source, $job) ? 10 : 0;

        return min(100, $score);
    }

    private function matchesTargetLocality(JobImportSource $source, array $job): bool
    {
        $haystack = mb_strtolower($job['location'] . ' ' . $job['country'] . ' ' . $job['description']);
        foreach ([...$source->getTargetCountries(), ...$source->getTargetLocations()] as $target) {
            if ('' !== (string) $target && str_contains($haystack, mb_strtolower((string) $target))) {
                return true;
            }
        }

        return false;
    }

    private function alreadyImported(JobImportSource $source, array $job): bool
    {
        if ('' !== $job['externalId']) {
            return null !== $this->jobOfferRepository->findOneBy([
                'externalSourceName' => $source->getName(),
                'externalId' => $job['externalId'],
            ]);
        }

        return null !== $this->jobOfferRepository->findOneBy([
            'externalSourceName' => $source->getName(),
            'title' => $job['title'],
            'location' => $job['location'],
        ]);
    }

    private function buildDescription(JobImportSource $source, array $job): string
    {
        $parts = [];
        if ('' !== $job['company']) {
            $parts[] = 'Entreprise source : ' . $job['company'];
        }
        if ('' !== $job['url']) {
            $parts[] = 'Lien source : ' . $job['url'];
        }
        if ('' !== ($job['provider'] ?? '')) {
            $parts[] = 'Plateforme source : ' . $job['provider'];
        }
        if ('' !== ($job['postedTimeAgo'] ?? '')) {
            $parts[] = 'Date indiquee par la source : ' . $job['postedTimeAgo'];
        }
        if ('' !== ($job['applicationEmail'] ?? '')) {
            $parts[] = 'Email de candidature : ' . $job['applicationEmail'];
        }
        $parts[] = $job['description'] ?: 'Description non précisée par la source externe.';
        $parts[] = 'Offre importée automatiquement depuis ' . $source->getName() . '.';

        return implode("\n\n", $parts);
    }

    private function mapContractType(string $contractType): ContractType
    {
        $value = mb_strtolower($contractType);

        return match (true) {
            str_contains($value, 'part'), str_contains($value, 'cdd'), str_contains($value, 'contract') => ContractType::Cdd,
            str_contains($value, 'intern'), str_contains($value, 'stage') => ContractType::Stage,
            str_contains($value, 'freelance') => ContractType::Freelance,
            default => ContractType::Cdi,
        };
    }

    private function inferSkills(string $text): array
    {
        $known = ['Excel', 'Comptabilité', 'Marketing', 'Vente', 'Communication', 'Gestion de projet', 'PHP', 'JavaScript', 'Python', 'Logistique', 'Ressources humaines'];
        $text = mb_strtolower($text);
        $skills = [];
        foreach ($known as $skill) {
            if (str_contains($text, mb_strtolower($skill))) {
                $skills[] = $skill;
            }
        }

        return $skills ?: ['Non précisé'];
    }

    private function getOrCreateSystemEmployer(): User
    {
        $email = 'aggregateur@kibarejob.local';
        $user = $this->userRepository->findOneBy(['email' => $email]);
        if ($user instanceof User) {
            return $user;
        }

        $user = (new User())
            ->setEmail($email)
            ->setFirstName('KIBARE-JOB')
            ->setLastName('Agrégateur')
            ->setRoles(['ROLE_EMPLOYER'])
            ->setIsEmailVerified(true);
        $user->setPasswordHash($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(24))));

        $employer = (new Employer())
            ->setUser($user)
            ->setCompanyName('KIBARE-JOB Agrégateur')
            ->setSector('Agrégation d\'offres')
            ->setCountryCode('BF')
            ->setCountryName('Burkina Faso')
            ->setCities(['Ouagadougou', 'Bobo-Dioulasso'])
            ->setSubscriptionTier(EmployerSubscriptionTier::Pro)
            ->setIsValidated(true);

        $user->setEmployer($employer);
        $this->entityManager->persist($user);
        $this->entityManager->persist($employer);

        return $user;
    }
}

