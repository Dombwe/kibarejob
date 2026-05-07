<?php

namespace App\Controller\Api;

use App\Entity\Swipe;
use App\Entity\User;
use App\Repository\SwipeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class CandidatureController extends AbstractController
{
    public function __construct(private readonly SwipeRepository $swipeRepository)
    {
    }

    #[Route('/api/matches', name: 'api_candidate_matches', methods: ['GET'])]
    public function matches(): JsonResponse
    {
        $user = $this->authenticatedUser();
        $swipes = $this->swipeRepository->findBy([
            'candidate' => $user,
            'isDeleted' => false,
        ], ['sentAt' => 'DESC']);

        return $this->json([
            'matches' => array_map(fn (Swipe $swipe): array => $this->serializeSwipe($swipe), $swipes),
        ]);
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
    private function serializeSwipe(Swipe $swipe): array
    {
        $offer = $swipe->getOffer();
        $employer = $offer->getEmployer()->getEmployer();

        return [
            'id' => (string) $swipe->getId(),
            'direction' => $swipe->getDirection()->value,
            'status' => $swipe->getStatus()->value,
            'matchScore' => $swipe->getMatchScore(),
            'sentAt' => $swipe->getSentAt()->format(DATE_ATOM),
            'viewedAt' => $swipe->getViewedAt()?->format(DATE_ATOM),
            'cvUsedUrl' => $swipe->getCvUsedUrl(),
            'documentsSent' => $swipe->getDocumentsSent(),
            'offer' => [
                'id' => (string) $offer->getId(),
                'title' => $offer->getTitle(),
                'location' => $offer->getLocation(),
                'contractType' => $offer->getContractType()->value,
                'companyName' => $employer?->getCompanyName(),
            ],
        ];
    }
}
