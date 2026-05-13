<?php

namespace App\Controller\Api;

use App\Entity\JobOffer;
use App\Entity\Swipe;
use App\Entity\User;
use App\Entity\Enum\SwipeDirection;
use App\Message\GenerateApplicationJob;
use App\MessageHandler\GenerateApplicationJobHandler;
use App\Repository\CandidateDocumentRepository;
use App\Repository\SwipeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class CandidatureController extends AbstractController
{
    public function __construct(
        private readonly SwipeRepository $swipeRepository,
        private readonly CandidateDocumentRepository $documentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly GenerateApplicationJobHandler $applicationJobHandler,
        private readonly string $mailFrom,
    ) {
    }

    #[Route('/api/matches', name: 'api_candidate_matches', methods: ['GET'])]
    public function matches(): JsonResponse
    {
        $user = $this->authenticatedUser();
        $swipes = $this->swipeRepository->createQueryBuilder('swipe')
            ->andWhere('swipe.candidate = :candidate')
            ->andWhere('swipe.isDeleted = :deleted')
            ->andWhere('swipe.direction != :dislike')
            ->setParameter('candidate', $user)
            ->setParameter('deleted', false)
            ->setParameter('dislike', SwipeDirection::Dislike)
            ->orderBy('swipe.sentAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->json([
            'matches' => array_map(fn (Swipe $swipe): array => $this->serializeSwipe($swipe), $swipes),
        ]);
    }

    #[Route('/api/matches/{id}', name: 'api_candidate_match_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $user = $this->authenticatedUser();
        $swipe = $this->swipeRepository->find($id);

        if (!$swipe instanceof Swipe || $swipe->isDeleted() || $swipe->getDirection() === SwipeDirection::Dislike || $swipe->getCandidate()->getId()?->toString() !== $user->getId()?->toString()) {
            return $this->json(['message' => 'Candidature introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return $this->json(['match' => $this->serializeSwipe($swipe)]);
    }

    #[Route('/api/matches/{id}', name: 'api_candidate_match_delete', methods: ['DELETE'])]
    public function delete(string $id): JsonResponse
    {
        $user = $this->authenticatedUser();
        $swipe = $this->swipeRepository->find($id);

        if (!$swipe instanceof Swipe || $swipe->isDeleted() || $swipe->getCandidate()->getId()?->toString() !== $user->getId()?->toString()) {
            return $this->json(['message' => 'Candidature introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $swipe->setIsDeleted(true);
        $this->entityManager->flush();

        return $this->json(null, JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/api/matches/{id}/resend', name: 'api_candidate_match_resend', methods: ['POST'])]
    public function resend(string $id): JsonResponse
    {
        $user = $this->authenticatedUser();
        $swipe = $this->swipeRepository->find($id);

        if (!$swipe instanceof Swipe || $swipe->isDeleted() || $swipe->getDirection() === SwipeDirection::Dislike || $swipe->getCandidate()->getId()?->toString() !== $user->getId()?->toString()) {
            return $this->json(['message' => 'Candidature introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        if ($swipe->isEmailSent()) {
            return $this->json([
                'message' => 'Cette candidature a deja ete envoyee avec succes.',
                'match' => $this->serializeSwipe($swipe),
            ]);
        }

        try {
            ($this->applicationJobHandler)(new GenerateApplicationJob((string) $swipe->getId()));
        } catch (\Throwable $exception) {
            $swipe
                ->setEmailSent(false)
                ->setEmailSentAt(null)
                ->setEmailError($exception->getMessage());
            $this->entityManager->flush();
        }

        return $this->json(['match' => $this->serializeSwipe($swipe)]);
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
    private function serializeSwipe(Swipe $swipe): array
    {
        $offer = $swipe->getOffer();
        $recipient = $swipe->getEmailRecipient() ?: $this->resolveRecipient($offer);
        $companyName = $this->resolveCompanyName($swipe);

        return [
            'id' => (string) $swipe->getId(),
            'direction' => $swipe->getDirection()->value,
            'status' => $swipe->getStatus()->value,
            'matchScore' => $swipe->getMatchScore(),
            'sentAt' => $swipe->getSentAt()->format(DATE_ATOM),
            'viewedAt' => $swipe->getViewedAt()?->format(DATE_ATOM),
            'cvUsedUrl' => $swipe->getCvUsedUrl(),
            'documentsSent' => $swipe->getDocumentsSent(),
            'attachments' => $this->serializeDocuments($swipe),
            'generatedAttachments' => array_values(array_filter([
                null === $swipe->getCvUsedUrl() ? null : [
                    'title' => 'CV',
                    'type' => 'cv',
                    'fileUrl' => $swipe->getCvUsedUrl(),
                ],
                null === $swipe->getMotivationLetterText() ? null : [
                    'title' => 'Lettre de motivation',
                    'type' => 'motivation_letter',
                    'fileUrl' => '/storage/candidates/' . (string) $swipe->getCandidate()->getId() . '/generated/lettre-motivation-' . (string) $swipe->getId() . '.pdf',
                    'content' => $swipe->getMotivationLetterText(),
                ],
            ])),
            'email' => [
                'sent' => $swipe->isEmailSent(),
                'recipient' => '' === trim($recipient) ? null : $recipient,
                'sender' => $swipe->getEmailSender() ?: $this->mailFrom,
                'replyTo' => $swipe->getEmailReplyTo() ?: $swipe->getCandidate()->getEmail(),
                'subject' => $swipe->getEmailSubject() ?: $offer->getTitle(),
                'body' => $swipe->getEmailBody() ?: $swipe->getMotivationLetterText(),
                'error' => $swipe->getEmailError(),
                'sentAt' => $swipe->getEmailSentAt()?->format(DATE_ATOM),
            ],
            'offer' => [
                'id' => (string) $offer->getId(),
                'title' => $offer->getTitle(),
                'description' => $offer->getDescription(),
                'location' => $offer->getLocation(),
                'contractType' => $offer->getContractType()->value,
                'companyName' => $companyName,
                'externalUrl' => $offer->getExternalUrl(),
                'applicationEmail' => $offer->getApplicationEmail(),
                'requiredDocuments' => $offer->getRequiredDocuments() ?? [],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function serializeDocuments(Swipe $swipe): array
    {
        $ids = array_values(array_filter(array_map('strval', $swipe->getDocumentsSent() ?? [])));
        if ([] === $ids) {
            return [];
        }

        $documents = [];
        foreach ($ids as $id) {
            $document = $this->documentRepository->find($id);
            $label = null === $document ? '' : mb_strtolower($document->getTitle() . ' ' . $document->getType()->value);
            if (null !== $document && null !== $swipe->getCvUsedUrl() && (str_contains($label, 'cv') || str_contains($label, 'curriculum'))) {
                continue;
            }

            if (null !== $document && !$document->isDeleted() && $document->getCandidate()->getId()?->toString() === $swipe->getCandidate()->getId()?->toString()) {
                $documents[] = $document;
            }
        }

        return array_map(static fn ($document): array => [
            'id' => (string) $document->getId(),
            'title' => $document->getTitle(),
            'type' => $document->getType()->value,
            'fileUrl' => $document->getFileUrl(),
        ], $documents);
    }

    private function resolveCompanyName(Swipe $swipe): ?string
    {
        $offer = $swipe->getOffer();
        $employerName = $offer->getEmployer()->getEmployer()?->getCompanyName();

        if ($offer->getSourceType() || $offer->getExternalSourceName()) {
            $sourceCompany = $this->sourceCompanyFromDescription($offer->getDescription());
            if (null !== $sourceCompany) {
                return $sourceCompany;
            }
        }

        return $employerName;
    }

    private function resolveRecipient(JobOffer $offer): string
    {
        $applicationEmail = trim((string) $offer->getApplicationEmail());
        if ('' !== $applicationEmail) {
            return $applicationEmail;
        }

        if ($offer->getSourceType() || $offer->getExternalSourceName()) {
            return '';
        }

        return trim((string) $offer->getEmployer()->getEmail());
    }

    private function sourceCompanyFromDescription(string $description): ?string
    {
        if (1 !== preg_match('/Entreprise source\s*:\s*(.+)/iu', $description, $matches)) {
            return null;
        }

        $company = trim((string) preg_split('/\R/', $matches[1])[0]);

        return '' === $company ? null : $company;
    }
}


