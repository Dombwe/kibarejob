<?php

namespace App\Controller\Api;

use App\Entity\CandidateProfile;
use App\Entity\Enum\SwipeDirection;
use App\Entity\Enum\SwipeStatus;
use App\Entity\JobOffer;
use App\Entity\Swipe;
use App\Entity\User;
use App\Message\GenerateApplicationJob;
use App\Repository\CandidateDocumentRepository;
use App\Repository\JobOfferRepository;
use App\Repository\SwipeRepository;
use App\Service\CacheService;
use App\Service\ScoreCacheService;
use App\Service\SubscriptionService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/jobs')]
class SwipeController extends AbstractController
{
    public function __construct(
        private readonly JobOfferRepository $offerRepository,
        private readonly SwipeRepository $swipeRepository,
        private readonly CandidateDocumentRepository $documentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly SubscriptionService $subscriptionService,
        private readonly ScoreCacheService $scoreCacheService,
        private readonly CacheService $cacheService,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/{id}/swipe', name: 'api_jobs_swipe', methods: ['POST'])]
    public function swipe(string $id, Request $request): JsonResponse
    {
        $user = $this->authenticatedUser();
        $candidateProfile = $user->getCandidateProfile();

        if (!$candidateProfile instanceof CandidateProfile) {
            return $this->json(['error' => 'Profil candidat requis.'], JsonResponse::HTTP_FORBIDDEN);
        }

        if (!$this->subscriptionService->canSwipe($user)) {
            return $this->json([
                'error' => 'Quota journalier atteint. Passez au Premium pour swiper plus.',
            ], JsonResponse::HTTP_FORBIDDEN);
        }

        $offer = $this->offerRepository->find($id);
        if (!$offer instanceof JobOffer || $offer->isDeleted()) {
            return $this->json(['error' => 'Offre introuvable.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->jsonPayload($request);
        $direction = SwipeDirection::from((string) ($payload['direction'] ?? 'like'));

        if ($direction === SwipeDirection::Superlike && !$this->subscriptionService->canSuperSwipe($user)) {
            return $this->json(['error' => 'Le super swipe est réservé au plan Premium.'], JsonResponse::HTTP_FORBIDDEN);
        }

        if ($direction !== SwipeDirection::Dislike) {
            $readiness = $this->applicationReadiness($candidateProfile, $offer);
            if (!$readiness['ready']) {
                return $this->json([
                    'code' => 'profile_completion_required',
                    'message' => 'Votre profil doit etre complete avant de postuler a cette offre.',
                    'missingProfileItems' => $readiness['missingProfileItems'],
                    'missingDocuments' => $readiness['missingDocuments'],
                    'requiredDocuments' => $this->documentLabels($offer->getRequiredDocuments() ?? []),
                    'canCreateCv' => true,
                ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        if ($this->swipeRepository->findOneBy(['candidate' => $user, 'offer' => $offer, 'isDeleted' => false]) instanceof Swipe) {
            return $this->json(['error' => 'Cette offre a déjà été swipée.'], JsonResponse::HTTP_CONFLICT);
        }

        $swipe = (new Swipe())
            ->setCandidate($user)
            ->setOffer($offer)
            ->setDirection($direction)
            ->setMatchScore($this->scoreCacheService->getScore($candidateProfile, $offer))
            ->setStatus(SwipeStatus::Sent);

        $user->setSwipesUsedToday($user->getSwipesUsedToday() + 1);

        $this->entityManager->persist($swipe);
        $this->entityManager->flush();
        $this->cacheService->incrementDailyQuota((string) $user->getId());
        $this->cacheService->invalidateFeed((string) $user->getId());

        $applicationQueued = false;
        if ($direction !== SwipeDirection::Dislike) {
            try {
                $this->messageBus->dispatch(new GenerateApplicationJob((string) $swipe->getId()));
                $applicationQueued = true;
            } catch (\Throwable $exception) {
                $this->logger->warning('La génération automatique de candidature n’a pas pu être planifiée après un swipe.', [
                    'swipeId' => (string) $swipe->getId(),
                    'offerId' => (string) $offer->getId(),
                    'candidateId' => (string) $user->getId(),
                    'exception' => $exception,
                ]);
            }
        }

        return $this->json([
            'accepted' => true,
            'swipeId' => (string) $swipe->getId(),
            'direction' => $direction->value,
            'matchScore' => $swipe->getMatchScore(),
            'queued' => $applicationQueued,
        ], JsonResponse::HTTP_ACCEPTED);
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
     * @return array{ready: bool, missingProfileItems: string[], missingDocuments: string[]}
     */
    private function applicationReadiness(CandidateProfile $profile, JobOffer $offer): array
    {
        $missingProfileItems = [];

        if ('' === trim($profile->getFirstName()) || '' === trim($profile->getLastName())) {
            $missingProfileItems[] = 'Nom et prenom';
        }

        if ('' === trim($profile->getCity())) {
            $missingProfileItems[] = 'Ville de residence';
        }

        if ('' === trim($profile->getEducationLevel())) {
            $missingProfileItems[] = 'Niveau d etudes';
        }

        if ([] === array_filter($profile->getSkills(), static fn (mixed $skill): bool => '' !== trim((string) $skill))) {
            $missingProfileItems[] = 'Competences';
        }

        if ([] === $profile->getLanguages()) {
            $missingProfileItems[] = 'Langues parlees';
        }

        if ('' === trim($profile->getAvailability())) {
            $missingProfileItems[] = 'Disponibilite';
        }

        if (null === $profile->getCvOriginalUrl() && null === $profile->getCvGeneratedUrl()) {
            $missingProfileItems[] = 'CV original ou CV guide';
        }

        $missingDocuments = [];
        $availableDocuments = $this->candidateDocumentHaystacks($profile->getUser());

        foreach ($this->documentLabels($offer->getRequiredDocuments() ?? []) as $requiredDocument) {
            $normalized = $this->normalizeDocumentLabel($requiredDocument);

            if ($this->isCvDocument($normalized)) {
                if (null === $profile->getCvOriginalUrl() && null === $profile->getCvGeneratedUrl()) {
                    $missingDocuments[] = 'CV';
                }
                continue;
            }

            if ($this->isGeneratedDocument($normalized)) {
                continue;
            }

            if (!$this->hasMatchingDocument($normalized, $availableDocuments)) {
                $missingDocuments[] = $requiredDocument;
            }
        }

        return [
            'ready' => [] === $missingProfileItems && [] === $missingDocuments,
            'missingProfileItems' => array_values(array_unique($missingProfileItems)),
            'missingDocuments' => array_values(array_unique($missingDocuments)),
        ];
    }

    /**
     * @return string[]
     */
    private function documentLabels(array $documents): array
    {
        return array_values(array_filter(array_map(
            static function (mixed $document): string {
                if (is_array($document)) {
                    return trim((string) ($document['label'] ?? $document['name'] ?? $document['title'] ?? ''));
                }

                return trim((string) $document);
            },
            $documents,
        ), static fn (string $label): bool => '' !== $label));
    }

    /**
     * @return string[]
     */
    private function candidateDocumentHaystacks(User $candidate): array
    {
        $documents = $this->documentRepository->findBy([
            'candidate' => $candidate,
            'isDeleted' => false,
            'isPublic' => true,
        ]);

        return array_map(
            fn ($document): string => $this->normalizeDocumentLabel(
                $document->getTitle() . ' ' . (string) $document->getDescription() . ' ' . $document->getType()->value . ' ' . implode(' ', $document->getTags() ?? []),
            ),
            $documents,
        );
    }

    /**
     * @param string[] $availableDocuments
     */
    private function hasMatchingDocument(string $requiredDocument, array $availableDocuments): bool
    {
        foreach ($availableDocuments as $availableDocument) {
            if (str_contains($availableDocument, $requiredDocument) || str_contains($requiredDocument, $availableDocument)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeDocumentLabel(string $label): string
    {
        $normalized = mb_strtolower($label);
        $normalized = strtr($normalized, [
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $normalized));
    }

    private function isCvDocument(string $normalized): bool
    {
        return 'cv' === $normalized || str_contains($normalized, 'curriculum');
    }

    private function isGeneratedDocument(string $normalized): bool
    {
        return str_contains($normalized, 'lettre') || str_contains($normalized, 'motivation');
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : [];
    }
}


