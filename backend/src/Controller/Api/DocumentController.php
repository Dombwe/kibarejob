<?php

namespace App\Controller\Api;

use App\Entity\CandidateDocument;
use App\Entity\Enum\DocumentType;
use App\Entity\Enum\VerificationAction;
use App\Entity\User;
use App\Repository\CandidateDocumentRepository;
use App\Service\ChunkedUploadService;
use App\Service\CandidateProfileCompletionService;
use App\Service\DocumentVerificationService;
use App\Service\DocumentExtractorService;
use App\Service\FileUploadService;
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
        private readonly string $projectDir,
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

        $documents = $this->documentRepository->findBy($criteria, ['uploadedAt' => 'DESC']);

        return $this->json([
            'documents' => array_map(fn (CandidateDocument $document) => $this->serializeDocument($document), $documents),
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

        return $this->json(['document' => $this->serializeDocument($document)], JsonResponse::HTTP_CREATED);
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
        ];
        $found = [];
        $normalized = mb_strtolower($text);
        foreach ($catalog as $skill) {
            if (str_contains($normalized, mb_strtolower($skill))) {
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
        $normalized = mb_strtolower($text);
        $found = [];
        foreach ($languages as $needle => $label) {
            if (str_contains($normalized, $needle)) {
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
            $normalized = mb_strtolower(str_replace([':', '.', '-'], '', $clean));

            if (in_array($normalized, $headings, true) || $this->lineContainsHeading($normalized, $headings)) {
                $capture = true;
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
            if (str_contains($line, mb_strtolower($heading))) {
                return true;
            }
        }

        return false;
    }

    private function refreshCandidateProfileCompletion(User $user): void
    {
        $profile = $user->getCandidateProfile();
        if (null === $profile) {
            return;
        }

        $this->completionService->refresh($profile);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
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

    private function absolutePathFromUrl(string $url): string
    {
        $path = str_starts_with($url, '/storage/')
            ? 'var/storage/' . substr($url, strlen('/storage/'))
            : ltrim($url, '/\\');

        return $this->projectDir . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
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
}
