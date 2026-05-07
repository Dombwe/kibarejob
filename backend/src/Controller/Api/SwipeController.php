<?php

namespace App\Controller\Api;

use App\Entity\CandidateProfile;
use App\Entity\Enum\SwipeDirection;
use App\Entity\Enum\SwipeStatus;
use App\Entity\JobOffer;
use App\Entity\Swipe;
use App\Entity\User;
use App\Message\GenerateApplicationJob;
use App\Repository\JobOfferRepository;
use App\Repository\SwipeRepository;
use App\Service\CacheService;
use App\Service\ScoreCacheService;
use App\Service\SubscriptionService;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly SubscriptionService $subscriptionService,
        private readonly ScoreCacheService $scoreCacheService,
        private readonly CacheService $cacheService,
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
            return $this->json(['error' => 'Le super swipe est reserve au plan Premium.'], JsonResponse::HTTP_FORBIDDEN);
        }

        if ($this->swipeRepository->findOneBy(['candidate' => $user, 'offer' => $offer, 'isDeleted' => false]) instanceof Swipe) {
            return $this->json(['error' => 'Cette offre a deja ete swipee.'], JsonResponse::HTTP_CONFLICT);
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

        if ($direction !== SwipeDirection::Dislike) {
            $this->messageBus->dispatch(new GenerateApplicationJob((string) $swipe->getId()));
        }

        return $this->json([
            'accepted' => true,
            'swipeId' => (string) $swipe->getId(),
            'direction' => $direction->value,
            'matchScore' => $swipe->getMatchScore(),
            'queued' => $direction !== SwipeDirection::Dislike,
        ], JsonResponse::HTTP_ACCEPTED);
    }

    private function authenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Utilisateur non authentifie.');
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
}
