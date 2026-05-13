<?php

namespace App\Controller\Api;

use App\Entity\CandidateDocument;
use App\Entity\Enum\DocumentType;
use App\Entity\Enum\VerificationAction;
use App\Entity\User;
use App\Repository\CandidateDocumentRepository;
use App\Service\CacheService;
use App\Service\ChunkedUploadService;
use App\Service\CandidateProfileCompletionService;
use App\Service\DocumentVerificationService;
use App\Service\DocumentExtractorService;
use App\Service\FileUploadService;
use App\Service\ModernCvPdfWriter;
use App\Service\ScoreCacheService;
use App\Service\SubscriptionService;
use App\Service\ValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/documents')]
class DocumentController extends AbstractController
{
    public function __construct(
        private readonly CandidateDocumentRepository $documentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly FileUploadService $fileUploadService,
        private readonly ChunkedUploadService $chunkedUploadService,
        private readonly DocumentVerificationService $verificationService,
        private readonly DocumentExtractorService $extractorService,
        private readonly SubscriptionService $subscriptionService,
        private readonly ValidationService $validationService,
        private readonly CandidateProfileCompletionService $completionService,
        private readonly ScoreCacheService $scoreCacheService,
        private readonly CacheService $cacheService,
        private readonly ModernCvPdfWriter $modernCvPdfWriter,
        private readonly string $projectDir,
        private readonly string $storagePath,
    ) {
    }

    #[Route('', name: 'api_documents_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $criteria = ['candidate' => $user, 'isDeleted' => false];

        if ($request->query->has('type')) {
            $criteria['type'] = $this->documentTypeFromInput($request->query->get('type'));
        }

        $limit = $this->boundedInt($request->query->get('limit'), 60, 1, 120);
        $cursor = $this->boundedInt($request->query->get('cursor'), 0, 0, 1000000);
        $documents = $this->documentRepository->findBy($criteria, ['uploadedAt' => 'DESC'], $limit + 1, $cursor);
        $hasMore = count($documents) > $limit;
        $documents = array_slice($documents, 0, $limit);

        return $this->json([
            'documents' => array_map(fn (CandidateDocument $document) => $this->serializeDocument($document), $documents),
            'nextCursor' => $hasMore ? $cursor + $limit : null,
            'hasMore' => $hasMore,
        ]);
    }

    #[Route('/upload', name: 'api_documents_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $file = $request->files->get('file') ?? $request->files->get('document');
        $isCvUpload = $this->isCvTypeInput($request->request->get('type'));

        if (!$this->subscriptionService->canUploadDocument($user, $this->countActiveDocuments($user))) {
            return $this->json([
                'message' => 'Quota de documents atteint.',
                'code' => 'document_quota_exceeded',
            ], JsonResponse::HTTP_TOO_MANY_REQUESTS);
        }

        if ($this->isChunkedRequest($request)) {
            return $this->handleChunkedUpload($request, $user, $file);
        }

        $errors = $this->validationService->validateDocumentFile($file);
        if ([] !== $errors) {
            return $this->json(['errors' => ['file' => $errors]], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $upload = $this->fileUploadService->upload($file, 'candidates/' . $user->getId() . '/documents');
        $document = $this->createDocumentFromRequest($request, $user)
            ->setFileUrl($upload['url'])
            ->setFileHash($upload['hash'])
            ->setUploadedAt(new \DateTimeImmutable());

        $validationErrors = $this->validationService->validateEntity($document);
        if ([] !== $validationErrors) {
            return $this->json(['errors' => $validationErrors], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->persist($document);
        $this->syncCandidateCv($user, $document, $isCvUpload);
        $this->enrichProfileFromCv($user, $document, $isCvUpload);
        $this->entityManager->flush();
        $this->refreshCandidateProfileCompletion($user);

        return $this->json([
            'document' => $this->serializeDocument($document),
            'profile' => null !== $user->getCandidateProfile() ? $this->serializeProfile($user->getCandidateProfile()) : null,
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/generate-cv', name: 'api_documents_generate_cv', methods: ['POST'])]
    public function generateCv(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $profile = $user->getCandidateProfile();
        if (null === $profile) {
            return $this->json(['message' => 'Profil candidat introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        if (!$this->subscriptionService->canUploadDocument($user, $this->countActiveDocuments($user))) {
            return $this->json([
                'message' => 'Quota de documents atteint.',
                'code' => 'document_quota_exceeded',
            ], JsonResponse::HTTP_TOO_MANY_REQUESTS);
        }

        $payload = $this->jsonPayload($request);
        $this->hydrateProfileFromGeneratedCv($profile, $payload);

        $directory = $this->resolveStorageRoot()
            . DIRECTORY_SEPARATOR . 'candidates' . DIRECTORY_SEPARATOR . (string) $user->getId()
            . DIRECTORY_SEPARATOR . 'generated';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return $this->json(['message' => 'Impossible de préparer le dossier du CV.'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        $fileName = 'cv-guide-' . (new \DateTimeImmutable())->format('YmdHis') . '.pdf';
        $absolutePath = $directory . DIRECTORY_SEPARATOR . $fileName;
        $this->modernCvPdfWriter->writeGenerated($absolutePath, $profile, $payload);
        if (!is_file($absolutePath)) {
            return $this->json(['message' => 'Impossible de générer le CV.'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        $fileUrl = '/storage/candidates/' . (string) $user->getId() . '/generated/' . $fileName;
        $document = (new CandidateDocument())
            ->setCandidate($user)
            ->setType(DocumentType::Other)
            ->setTitle('CV')
            ->setDescription('CV généré avec l’assistant Kibaré Job.')
            ->setFileUrl($fileUrl)
            ->setFileHash(hash_file('sha256', $absolutePath))
            ->setIsPublic(true)
            ->setIsPinned(true)
            ->setIsVerified(true)
            ->setConfidenceScore(90)
            ->setUploadedAt(new \DateTimeImmutable())
            ->setLastVerifiedAt(new \DateTimeImmutable())
            ->setTags(['cv', 'generated', 'kibare-job']);

        $profile
            ->setCvGeneratedUrl($fileUrl)
            ->setCvLastUpdated(new \DateTimeImmutable());

        $this->entityManager->persist($document);
        $this->entityManager->flush();
        $this->refreshCandidateProfileCompletion($user);

        return $this->json([
            'document' => $this->serializeDocument($document),
            'profile' => $this->serializeProfile($profile),
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_documents_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $document = $this->findOwnedDocument($id);

        return $this->json(['document' => $this->serializeDocument($document)]);
    }

    #[Route('/{id}/preview', name: 'api_documents_preview', methods: ['GET'])]
    public function preview(string $id): JsonResponse
    {
        $document = $this->findOwnedDocument($id);
        $absolutePath = $this->absolutePathFromUrl($document->getFileUrl());
        $mimeType = is_file($absolutePath) ? (new File($absolutePath))->getMimeType() : null;
        $extraction = $this->extractorService->extract($absolutePath, $mimeType);

        return $this->json([
            'document' => $this->serializeDocument($document),
            'mimeType' => $mimeType,
            'text' => $extraction['text'],
            'method' => $extraction['method'],
            'confidence' => $extraction['confidence'],
        ]);
    }

    #[Route('/{id}/file', name: 'api_documents_file', methods: ['GET'])]
    public function downloadFile(string $id): BinaryFileResponse
    {
        $document = $this->findOwnedDocument($id);
        $absolutePath = $this->absolutePathFromUrl($document->getFileUrl());

        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            throw $this->createNotFoundException('Fichier introuvable.');
        }

        $response = new BinaryFileResponse($absolutePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($absolutePath));

        return $response;
    }

    #[Route('/{id}', name: 'api_documents_update', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $document = $this->findOwnedDocument($id);
        $payload = $this->jsonPayload($request);

        if (array_key_exists('type', $payload)) {
            $document->setType($this->documentTypeFromInput($payload['type']));
        }
        if (array_key_exists('title', $payload)) {
            $document->setTitle((string) $payload['title']);
        }
        if (array_key_exists('description', $payload)) {
            $document->setDescription($this->nullableString($payload['description']));
        }
        if (array_key_exists('issuingOrganization', $payload) || array_key_exists('issuing_organization', $payload)) {
            $document->setIssuingOrganization($this->nullableString($payload['issuingOrganization'] ?? $payload['issuing_organization']));
        }
        if (array_key_exists('issueDate', $payload) || array_key_exists('issue_date', $payload)) {
            $document->setIssueDate($this->nullableDate($payload['issueDate'] ?? $payload['issue_date']));
        }
        if (array_key_exists('expiryDate', $payload) || array_key_exists('expiry_date', $payload)) {
            $document->setExpiryDate($this->nullableDate($payload['expiryDate'] ?? $payload['expiry_date']));
        }
        if (array_key_exists('documentNumber', $payload) || array_key_exists('document_number', $payload)) {
            $document->setDocumentNumber($this->nullableString($payload['documentNumber'] ?? $payload['document_number']));
        }
        if (array_key_exists('isPublic', $payload) || array_key_exists('is_public', $payload)) {
            $document->setIsPublic((bool) ($payload['isPublic'] ?? $payload['is_public']));
        }
        if (array_key_exists('isPinned', $payload) || array_key_exists('is_pinned', $payload)) {
            $document->setIsPinned((bool) ($payload['isPinned'] ?? $payload['is_pinned']));
        }
        if (array_key_exists('tags', $payload)) {
            $document->setTags(is_array($payload['tags']) ? $payload['tags'] : null);
        }

        $errors = $this->validationService->validateEntity($document);
        if ([] !== $errors) {
            return $this->json(['errors' => $errors], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->flush();
        $this->refreshCandidateProfileCompletion($document->getCandidate());

        return $this->json(['document' => $this->serializeDocument($document)]);
    }

    #[Route('/{id}', name: 'api_documents_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $document = $this->findOwnedDocument($id);
        $document->setIsDeleted(true);
        $this->entityManager->flush();
        $this->refreshCandidateProfileCompletion($document->getCandidate());

        return $this->json(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/verify', name: 'api_documents_verify', methods: ['POST'])]
    public function verify(string $id): JsonResponse
    {
        $document = $this->findOwnedDocument($id);
        $absolutePath = $this->absolutePathFromUrl($document->getFileUrl());
        $mimeType = is_file($absolutePath) ? (new File($absolutePath))->getMimeType() : null;
        $result = $this->verificationService->verify($document, $absolutePath, $mimeType);
        $log = $this->verificationService->createLog($document, $result);

        $document
            ->setConfidenceScore((int) $result['score'])
            ->setIsVerified($result['action'] === VerificationAction::AutoVerified)
            ->setLastVerifiedAt(new \DateTimeImmutable());

        $this->entityManager->persist($log);
        $this->entityManager->flush();

        return $this->json([
            'document' => $this->serializeDocument($document),
            'verification' => [
                'score' => $result['score'],
                'action' => $result['action']->value,
                'signals' => $result['signals'],
                'reason' => $result['reason'],
            ],
        ]);
    }

    private function handleChunkedUpload(Request $request, User $user, mixed $file): JsonResponse
    {
        $isCvUpload = $this->isCvTypeInput($request->request->get('type'));

        if (!$file instanceof UploadedFile) {
            return $this->json(['errors' => ['file' => ['Chunk manquant.']]], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $uploadId = (string) $request->request->get('uploadId', $request->request->get('upload_id', ''));
        $chunkIndex = (int) $request->request->get('chunkIndex', $request->request->get('chunk_index', 0));
        $totalChunks = (int) $request->request->get('totalChunks', $request->request->get('total_chunks', 1));
        $originalName = (string) $request->request->get('originalName', $request->request->get('original_name', $file->getClientOriginalName()));

        $result = $this->chunkedUploadService->receiveChunk($file, $uploadId, $chunkIndex, $totalChunks, $originalName);
        if (!$result['complete']) {
            return $this->json($result, JsonResponse::HTTP_ACCEPTED);
        }

        $document = $this->createDocumentFromRequest($request, $user)
            ->setFileUrl('/storage/documents/' . $result['fileName'])
            ->setFileHash($result['hash'])
            ->setUploadedAt(new \DateTimeImmutable());

        $this->entityManager->persist($document);
        $this->syncCandidateCv($user, $document, $isCvUpload);
        $this->enrichProfileFromCv($user, $document, $isCvUpload);
        $this->entityManager->flush();
        $this->refreshCandidateProfileCompletion($user);

        return $this->json([
            'upload' => $result,
            'document' => $this->serializeDocument($document),
            'profile' => null !== $user->getCandidateProfile() ? $this->serializeProfile($user->getCandidateProfile()) : null,
        ], JsonResponse::HTTP_CREATED);
    }

    private function isChunkedRequest(Request $request): bool
    {
        return $request->request->has('uploadId')
            || $request->request->has('upload_id')
            || $request->request->has('chunkIndex')
            || $request->request->has('chunk_index');
    }

    private function countActiveDocuments(User $user): int
    {
        return $this->documentRepository->count([
            'candidate' => $user,
            'isDeleted' => false,
        ]);
    }

    private function createDocumentFromRequest(Request $request, User $user): CandidateDocument
    {
        $type = (string) $request->request->get('type', 'other');

        return (new CandidateDocument())
            ->setCandidate($user)
            ->setType($this->documentTypeFromInput($type))
            ->setTitle((string) $request->request->get('title', 'Document'))
            ->setDescription($this->nullableString($request->request->get('description')))
            ->setIssuingOrganization($this->nullableString($request->request->get('issuingOrganization', $request->request->get('issuing_organization'))))
            ->setIssueDate($this->nullableDate($request->request->get('issueDate', $request->request->get('issue_date'))))
            ->setExpiryDate($this->nullableDate($request->request->get('expiryDate', $request->request->get('expiry_date'))))
            ->setDocumentNumber($this->nullableString($request->request->get('documentNumber', $request->request->get('document_number'))))
            ->setIsPublic(filter_var($request->request->get('isPublic', $request->request->get('is_public', true)), FILTER_VALIDATE_BOOL))
            ->setIsPinned(filter_var($request->request->get('isPinned', $request->request->get('is_pinned', false)), FILTER_VALIDATE_BOOL))
            ->setTags($this->jsonArray($request->request->get('tags')));
    }

    private function documentTypeFromInput(mixed $value): DocumentType
    {
        $type = strtolower(trim((string) $value));
        $type = str_replace([' ', '-', '.'], '_', $type);

        return match ($type) {
            'cv', 'resume', 'curriculum_vitae',
            'letter', 'lettre', 'lettre_motivation', 'motivation', 'motivation_letter' => DocumentType::Other,
            'diplome', 'diploma', 'degree' => DocumentType::Diploma,
            'certificat', 'certificate', 'certification' => DocumentType::Certificate,
            'attestation', 'work_certificate', 'work_attestation' => DocumentType::Attestation,
            'permis', 'permis_conduire', 'permis_de_conduire', 'driving_license', 'driver_license' => DocumentType::DrivingLicense,
            default => DocumentType::tryFrom($type) ?? DocumentType::Other,
        };
    }

    private function isCvTypeInput(mixed $value): bool
    {
        $type = strtolower(trim((string) $value));
        $type = str_replace([' ', '-', '.'], '_', $type);

        return in_array($type, ['cv', 'resume', 'curriculum_vitae'], true);
    }

    private function syncCandidateCv(User $user, CandidateDocument $document, bool $isCvUpload): void
    {
        if (!$isCvUpload) {
            return;
        }

        $profile = $user->getCandidateProfile();
        if (null === $profile) {
            return;
        }

        $profile
            ->setCvOriginalUrl($document->getFileUrl())
            ->setCvLastUpdated(new \DateTimeImmutable());
    }

    private function enrichProfileFromCv(User $user, CandidateDocument $document, bool $isCvUpload): void
    {
        if (!$isCvUpload) {
            return;
        }

        $profile = $user->getCandidateProfile();
        if (null === $profile) {
            return;
        }

        $absolutePath = $this->absolutePathFromUrl($document->getFileUrl());
        $mimeType = is_file($absolutePath) ? (new File($absolutePath))->getMimeType() : null;
        $result = $this->extractorService->extract($absolutePath, $mimeType);
        $text = $result['text'];
        if ('' === trim($text)) {
            return;
        }

        if (null === $document->getDescription() || '' === trim($document->getDescription())) {
            $document->setDescription(mb_substr($text, 0, 1800));
        }

        $skills = $this->mergeUnique($profile->getSkills(), $this->extractSkills($text));
        if ([] !== $skills) {
            $profile->setSkills($skills);
        }

        $languages = $this->mergeLanguageItems($profile->getLanguages(), $this->extractLanguages($text));
        if ([] !== $languages) {
            $profile->setLanguages($languages);
        }

        $experiences = $this->mergeUnique($profile->getExperiences(), $this->extractSectionItems($text, ['experience', 'experiences', 'experience professionnelle', 'expériences professionnelles']));
        if ([] !== $experiences) {
            $profile->setExperiences($experiences);
        }

        $interests = $this->mergeUnique($profile->getInterests(), $this->extractSectionItems($text, ['centres d interet', 'centres d’intérêt', 'loisirs', 'interets', 'intérêts']));
        if ([] !== $interests) {
            $profile->setInterests($interests);
        }

        $references = $this->mergeUnique($profile->getReferences(), $this->extractSectionItems($text, ['references', 'références', 'personnes de reference', 'personnes de référence']));
        if ([] !== $references) {
            $profile->setReferences($references);
        }

        if ('' === trim($profile->getEducationLevel()) || 'Aucun' === $profile->getEducationLevel()) {
            $educationLevel = $this->extractEducationLevel($text);
            if (null !== $educationLevel) {
                $profile->setEducationLevel($educationLevel);
            }
        }

        if (null === $profile->getEducationField() || '' === trim($profile->getEducationField())) {
            $educationField = $this->extractEducationField($text);
            if (null !== $educationField) {
                $profile->setEducationField($educationField);
            }
        }
    }

    /**
     * @param string[] $current
     * @param string[] $incoming
     * @return string[]
     */
    private function mergeUnique(array $current, array $incoming): array
    {
        $items = [];
        foreach (array_merge($current, $incoming) as $item) {
            $label = trim((string) $item);
            if ('' === $label) {
                continue;
            }

            $items[mb_strtolower($label)] = mb_convert_case($label, MB_CASE_TITLE, 'UTF-8');
        }

        return array_values($items);
    }

    /**
     * @param array<int, mixed> $current
     * @param array<int, array{name: string, level: string}> $incoming
     * @return array<int, array<string, string>>
     */
    private function mergeLanguageItems(array $current, array $incoming): array
    {
        $items = [];
        foreach ($current as $language) {
            if (is_array($language)) {
                $name = trim((string) ($language['name'] ?? $language['language'] ?? ''));
                if ('' !== $name) {
                    $items[mb_strtolower($name)] = ['name' => mb_convert_case($name, MB_CASE_TITLE, 'UTF-8'), 'level' => (string) ($language['level'] ?? 'Intermediaire')];
                }
            }
        }
        foreach ($incoming as $language) {
            $name = trim($language['name']);
            if ('' !== $name) {
                $items[mb_strtolower($name)] = $language;
            }
        }

        return array_values($items);
    }

    /**
     * @return string[]
     */
    private function extractSkills(string $text): array
    {
        $catalog = [
            'Excel', 'Word', 'PowerPoint', 'Communication', 'Leadership', 'Gestion de projet',
            'Comptabilite', 'Comptabilité', 'Marketing digital', 'Vente', 'Negociation', 'Négociation',
            'Relation client', 'Analyse de donnees', 'Analyse de données', 'Python', 'PHP', 'JavaScript',
            'React', 'Flutter', 'Dart', 'Laravel', 'Symfony', 'SQL', 'MySQL', 'PostgreSQL', 'Git',
            'Gestion administrative', 'Ressources humaines', 'Logistique', 'Finance', 'Budget',
            'Comptabilite analytique', 'Paie', 'Fiscalite', 'Secretariat', 'Assistanat',
            'Prospection', 'Community management', 'Canva', 'Photoshop', 'Illustrator',
            'Maintenance informatique', 'Reseaux', 'Cybersecurite', 'Support utilisateur',
            'Electricite', 'Mecanique', 'BTP', 'Suivi chantier', 'Approvisionnement',
            'Gestion de stock', 'Achat', 'Transport', 'Qualite', 'Hygiene securite',
            'Redaction', 'Reporting', 'Planification', 'Coordination', 'Management',
        ];
        $found = [];
        $normalized = $this->normalizeForSearch($text);
        foreach ($catalog as $skill) {
            if (str_contains($normalized, $this->normalizeForSearch($skill))) {
                $found[] = $skill;
            }
        }

        return array_values(array_unique(array_merge($found, $this->extractSectionItems($text, ['competences', 'compétences', 'skills']))));
    }

    /**
     * @return array<int, array{name: string, level: string}>
     */
    private function extractLanguages(string $text): array
    {
        $languages = ['francais' => 'Français', 'français' => 'Français', 'anglais' => 'Anglais', 'mooré' => 'Mooré', 'moore' => 'Mooré', 'dioula' => 'Dioula', 'fulfulde' => 'Fulfulde'];
        $normalized = $this->normalizeForSearch($text);
        $found = [];
        foreach ($languages as $needle => $label) {
            if (str_contains($normalized, $this->normalizeForSearch($needle))) {
                $found[mb_strtolower($label)] = ['name' => $label, 'level' => 'Intermediaire'];
            }
        }

        return array_values($found);
    }

    /**
     * @param string[] $headings
     * @return string[]
     */
    private function extractSectionItems(string $text, array $headings): array
    {
        $lines = preg_split('/\R+/', $text) ?: [];
        $items = [];
        $capture = false;
        $sectionHeadings = ['competences', 'compétences', 'skills', 'experience', 'experiences', 'expériences', 'formation', 'education', 'langues', 'languages', 'loisirs', 'interets', 'intérêts', 'references', 'références'];

        foreach ($lines as $line) {
            $clean = trim(preg_replace('/^[•\-\*\d\.\)\s]+/', '', $line) ?? $line);
            $normalized = $this->normalizeForSearch(str_replace(['.', '-'], '', $clean));

            if (in_array($normalized, $headings, true) || $this->lineContainsHeading($normalized, $headings)) {
                $capture = true;
                $inline = trim((string) preg_replace('/^.*?[:\-]\s*/u', '', $clean));
                if ($inline !== $clean && '' !== $inline && mb_strlen($inline) <= 160) {
                    foreach (preg_split('/[,;|]/', $inline) ?: [] as $part) {
                        $part = trim($part);
                        if ('' !== $part && mb_strlen($part) >= 2 && mb_strlen($part) <= 80) {
                            $items[] = $part;
                        }
                    }
                }
                continue;
            }

            if ($capture && $this->lineContainsHeading($normalized, $sectionHeadings)) {
                break;
            }

            if ($capture && '' !== $clean && mb_strlen($clean) <= 110) {
                foreach (preg_split('/[,;|]/', $clean) ?: [] as $part) {
                    $part = trim($part);
                    if ('' !== $part && mb_strlen($part) >= 2 && mb_strlen($part) <= 80) {
                        $items[] = $part;
                    }
                }
            }

            if (count($items) >= 12) {
                break;
            }
        }

        return array_values(array_unique($items));
    }

    /**
     * @param string[] $headings
     */
    private function lineContainsHeading(string $line, array $headings): bool
    {
        foreach ($headings as $heading) {
            if (str_contains($line, $this->normalizeForSearch($heading))) {
                return true;
            }
        }

        return false;
    }

    private function extractEducationLevel(string $text): ?string
    {
        $normalized = $this->normalizeForSearch($text);
        $levels = [
            'doctorat' => 'Doctorat',
            'master' => 'Master',
            'licence' => 'Licence',
            'bac 5' => 'Master',
            'bac +5' => 'Master',
            'bac 3' => 'Licence',
            'bac +3' => 'Licence',
            'baccalaureat' => 'Bac',
            'bac' => 'Bac',
            'bepc' => 'BEPC',
            'cep' => 'CEP',
        ];

        foreach ($levels as $needle => $level) {
            if (str_contains($normalized, $this->normalizeForSearch($needle))) {
                return $level;
            }
        }

        return null;
    }

    private function extractEducationField(string $text): ?string
    {
        $fields = [
            'genie logiciel' => 'Génie logiciel',
            'informatique' => 'Informatique',
            'reseaux' => 'Réseaux et télécommunications',
            'telecommunications' => 'Réseaux et télécommunications',
            'gestion' => 'Gestion',
            'comptabilite' => 'Comptabilité',
            'finance' => 'Finance',
            'marketing' => 'Marketing',
            'communication' => 'Communication',
            'ressources humaines' => 'Ressources humaines',
            'logistique' => 'Logistique',
            'droit' => 'Droit',
            'economie' => 'Économie',
            'agronomie' => 'Agronomie',
            'sante' => 'Santé',
            'electricite' => 'Électricité',
            'mecanique' => 'Mécanique',
            'btp' => 'BTP',
        ];
        $normalized = $this->normalizeForSearch($text);

        foreach ($fields as $needle => $field) {
            if (str_contains($normalized, $needle)) {
                return $field;
            }
        }

        return null;
    }

    private function normalizeForSearch(string $value): string
    {
        $value = mb_strtolower($value, 'UTF-8');
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = false === $converted ? $value : $converted;
        $value = str_replace(["'", '’', '`'], ' ', $value);
        $value = preg_replace('/[^a-z0-9+]+/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function refreshCandidateProfileCompletion(User $user): void
    {
        $profile = $user->getCandidateProfile();
        if (null === $profile) {
            return;
        }

        $this->completionService->refresh($profile);
        $this->scoreCacheService->invalidateCandidate($profile);
        $this->cacheService->invalidateFeed((string) $user->getId());
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    private function hydrateProfileFromGeneratedCv(\App\Entity\CandidateProfile $profile, array $payload): void
    {
        $profile
            ->setFirstName($this->stringFromPayload($payload, 'firstName', $profile->getFirstName()))
            ->setLastName($this->stringFromPayload($payload, 'lastName', $profile->getLastName()))
            ->setCity($this->stringFromPayload($payload, 'city', $profile->getCity()))
            ->setEducationLevel($this->stringFromPayload($payload, 'educationLevel', $profile->getEducationLevel() ?: 'Aucun'))
            ->setEducationField($this->nullableString($payload['educationField'] ?? $profile->getEducationField()))
            ->setAvailability($this->stringFromPayload($payload, 'availability', $profile->getAvailability() ?: 'Immédiate'));

        if (array_key_exists('birthDate', $payload)) {
            $profile->setBirthDate($this->nullableDate($payload['birthDate']));
        }
        if (array_key_exists('salaryExpectation', $payload)) {
            $salary = (int) $payload['salaryExpectation'];
            $profile->setSalaryExpectation($salary > 0 ? $salary : null);
        }

        $skills = $this->mergeUnique($profile->getSkills(), $this->arrayFromPayload($payload, 'skills'));
        if ([] !== $skills) {
            $profile->setSkills($skills);
        }

        $experiences = $this->mergeUnique($profile->getExperiences(), $this->arrayFromPayload($payload, 'experiences'));
        if ([] !== $experiences) {
            $profile->setExperiences($experiences);
        }

        $interests = $this->mergeUnique($profile->getInterests(), $this->arrayFromPayload($payload, 'interests'));
        if ([] !== $interests) {
            $profile->setInterests($interests);
        }

        $profile->setLanguages($this->mergeLanguageItems($profile->getLanguages(), $this->languageItemsFromPayload($payload)));
    }

    private function writeCvPdf(string $path, \App\Entity\CandidateProfile $profile, array $payload): void
    {
        $name = trim($profile->getFirstName() . ' ' . $profile->getLastName()) ?: 'Candidat Kibaré Job';
        $lines = [
            mb_strtoupper($name, 'UTF-8'),
            $this->stringFromPayload($payload, 'jobTitle', 'Candidat'),
            '',
            'Coordonnées',
            'Email : ' . $profile->getUser()->getEmail(),
            'Ville : ' . ($profile->getCity() ?: 'Non renseignée'),
            'Disponibilité : ' . ($profile->getAvailability() ?: 'Non renseignée'),
        ];

        $summary = trim((string) ($payload['summary'] ?? ''));
        if ('' !== $summary) {
            array_push($lines, '', 'Profil', $summary);
        }

        $skills = $this->arrayFromPayload($payload, 'skills');
        if ([] !== $skills) {
            array_push($lines, '', 'Compétences clés', implode(', ', $skills));
        }

        $experiences = $this->arrayFromPayload($payload, 'experiences');
        if ([] !== $experiences) {
            array_push($lines, '', 'Expériences');
            foreach ($experiences as $experience) {
                $lines[] = '- ' . $experience;
            }
        }

        $education = $this->arrayFromPayload($payload, 'education');
        array_push($lines, '', 'Formation');
        $lines[] = trim($profile->getEducationLevel() . ' - ' . (string) $profile->getEducationField(), ' -') ?: 'Non renseignée';
        foreach ($education as $item) {
            $lines[] = '- ' . $item;
        }

        $languages = $this->arrayFromPayload($payload, 'languages');
        if ([] !== $languages) {
            array_push($lines, '', 'Langues', implode(', ', $languages));
        }

        $interests = $this->arrayFromPayload($payload, 'interests');
        if ([] !== $interests) {
            array_push($lines, '', 'Centres d’intérêt', implode(', ', $interests));
        }

        $this->writeTextPdf($path, $lines);
    }

    /**
     * @param string[] $lines
     */
    private function writeTextPdf(string $path, array $lines): void
    {
        $wrapped = [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ('' === $line) {
                $wrapped[] = '';
                continue;
            }

            foreach (explode("\n", wordwrap($line, 86, "\n", true)) as $chunk) {
                $wrapped[] = $chunk;
            }
        }

        $pages = array_chunk($wrapped, 42) ?: [[]];
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pageObjectIds = [];
        $nextObjectId = 4;

        foreach ($pages as $pageLines) {
            $contentObjectId = $nextObjectId++;
            $pageObjectId = $nextObjectId++;
            $pageObjectIds[] = $pageObjectId;

            $stream = "BT\n/F1 11 Tf\n50 790 Td\n15 TL\n";
            foreach ($pageLines as $index => $line) {
                if (0 === $index && '' !== $line) {
                    $stream .= "/F1 17 Tf\n";
                } elseif ('' !== $line && in_array($line, ['Coordonnées', 'Profil', 'Compétences clés', 'Expériences', 'Formation', 'Langues', 'Centres d’intérêt'], true)) {
                    $stream .= "/F1 13 Tf\n";
                } else {
                    $stream .= "/F1 11 Tf\n";
                }
                $stream .= '(' . $this->pdfEscape($line) . ") Tj\nT*\n";
            }
            $stream .= "ET\n";

            $objects[$contentObjectId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
            $objects[$pageObjectId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentObjectId . ' 0 R >>';
        }

        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(static fn (int $id): string => $id . ' 0 R', $pageObjectIds)) . '] /Count ' . count($pageObjectIds) . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0 => 0];
        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $maxId = max(array_keys($objects));
        $pdf .= "xref\n0 " . ($maxId + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$id] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";

        file_put_contents($path, $pdf);
    }

    private function pdfEscape(string $value): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        $encoded = false === $encoded ? $value : $encoded;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }

    private function stringFromPayload(array $payload, string $key, string $fallback = ''): string
    {
        $value = trim((string) ($payload[$key] ?? $fallback));

        return '' === $value ? $fallback : $value;
    }

    /**
     * @return string[]
     */
    private function arrayFromPayload(array $payload, string $key): array
    {
        $value = $payload[$key] ?? [];
        if (is_string($value)) {
            $value = preg_split('/[,;\n]+/', $value) ?: [];
        }
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (mixed $item): string => trim((string) $item), $value)));
    }

    /**
     * @return array<int, array{name: string, level: string}>
     */
    private function languageItemsFromPayload(array $payload): array
    {
        $languages = [];
        foreach ($this->arrayFromPayload($payload, 'languages') as $language) {
            $languages[] = ['name' => $language, 'level' => 'Intermédiaire'];
        }

        return $languages;
    }

    private function findOwnedDocument(string $id): CandidateDocument
    {
        $document = $this->documentRepository->find($id);
        $user = $this->authenticatedUser();

        if (!$document instanceof CandidateDocument || $document->isDeleted() || $document->getCandidate()->getId()?->toString() !== $user->getId()?->toString()) {
            throw $this->createNotFoundException('Document introuvable.');
        }

        return $document;
    }

    private function authenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifié.');
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : [];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return '' === $value ? null : $value;
    }

    private function nullableDate(mixed $value): ?\DateTimeImmutable
    {
        $value = trim((string) $value);

        return '' === $value ? null : new \DateTimeImmutable($value);
    }

    private function jsonArray(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (!is_string($value) || '' === trim($value)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function boundedInt(mixed $value, int $default, int $min, int $max): int
    {
        $parsed = filter_var($value, FILTER_VALIDATE_INT);
        if (false === $parsed) {
            $parsed = $default;
        }

        return max($min, min($max, (int) $parsed));
    }

    private function absolutePathFromUrl(string $url): string
    {
        if (str_starts_with($url, '/storage/')) {
            return $this->resolveStorageRoot() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim(substr($url, strlen('/storage/')), '/\\'));
        }

        return $this->projectDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($url, '/\\'));
    }

    private function resolveStorageRoot(): string
    {
        if (str_starts_with($this->storagePath, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $this->storagePath)) {
            return rtrim($this->storagePath, '/\\');
        }

        return $this->projectDir . DIRECTORY_SEPARATOR . trim($this->storagePath, '/\\');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDocument(CandidateDocument $document): array
    {
        return [
            'id' => (string) $document->getId(),
            'type' => $document->getType()->value,
            'title' => $document->getTitle(),
            'description' => $document->getDescription(),
            'issuingOrganization' => $document->getIssuingOrganization(),
            'issueDate' => $document->getIssueDate()?->format('Y-m-d'),
            'expiryDate' => $document->getExpiryDate()?->format('Y-m-d'),
            'documentNumber' => $document->getDocumentNumber(),
            'fileUrl' => $document->getFileUrl(),
            'fileHash' => $document->getFileHash(),
            'isVerified' => $document->isVerified(),
            'confidenceScore' => $document->getConfidenceScore(),
            'isPublic' => $document->isPublic(),
            'isPinned' => $document->isPinned(),
            'tags' => $document->getTags(),
            'uploadedAt' => $document->getUploadedAt()->format(DATE_ATOM),
            'lastVerifiedAt' => $document->getLastVerifiedAt()?->format(DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProfile(\App\Entity\CandidateProfile $profile): array
    {
        return [
            'userId' => (string) $profile->getUser()->getId(),
            'firstName' => $profile->getFirstName(),
            'lastName' => $profile->getLastName(),
            'photoUrl' => $profile->getPhotoUrl(),
            'birthDate' => $profile->getBirthDate()?->format('Y-m-d'),
            'city' => $profile->getCity(),
            'educationLevel' => $profile->getEducationLevel(),
            'educationField' => $profile->getEducationField(),
            'skills' => $profile->getSkills(),
            'languages' => $profile->getLanguages(),
            'experiences' => $profile->getExperiences(),
            'interests' => $profile->getInterests(),
            'references' => $profile->getReferences(),
            'drivingLicense' => $profile->hasDrivingLicense(),
            'drivingLicenseCategory' => $profile->getDrivingLicenseCategory(),
            'availability' => $profile->getAvailability(),
            'salaryExpectation' => $profile->getSalaryExpectation(),
            'cvOriginalUrl' => $profile->getCvOriginalUrl(),
            'cvGeneratedUrl' => $profile->getCvGeneratedUrl(),
            'cvLastUpdated' => $profile->getCvLastUpdated()?->format(DATE_ATOM),
            'profileCompletedPercent' => $profile->getUser()->getProfileCompletedPercent(),
        ];
    }
}
