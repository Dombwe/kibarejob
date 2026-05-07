<?php

namespace App\Controller\Api;

use App\Entity\Employer;
use App\Entity\Enum\SwipeDirection;
use App\Entity\Enum\SwipeStatus;
use App\Entity\Swipe;
use App\Entity\User;
use App\Repository\SwipeRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/employer/applications')]
class EmployerApplicationController extends AbstractController
{
    public function __construct(
        private readonly SwipeRepository $swipeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly NotificationService $notificationService,
    ) {
    }

    #[Route('', name: 'api_employer_applications_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $employer = $this->getEmployer();
        $swipes = $this->swipeRepository->createQueryBuilder('swipe')
            ->join('swipe.offer', 'offer')
            ->andWhere('offer.employer = :employerUser')
            ->andWhere('swipe.isDeleted = :deleted')
            ->andWhere('swipe.direction != :dislike')
            ->setParameter('employerUser', $employer->getUser())
            ->setParameter('deleted', false)
            ->setParameter('dislike', SwipeDirection::Dislike)
            ->orderBy('swipe.sentAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->json([
            'applications' => array_map(fn (Swipe $swipe): array => $this->serializeApplication($swipe, false), $swipes),
        ]);
    }

    #[Route('/{id}', name: 'api_employer_applications_show', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $swipe = $this->findOwnedApplication($id);

        if (null === $swipe->getViewedAt()) {
            $swipe
                ->setViewedAt(new \DateTimeImmutable())
                ->setStatus(SwipeStatus::Viewed);
            $this->entityManager->flush();
        }

        return $this->json(['application' => $this->serializeApplication($swipe, true)]);
    }

    #[Route('/{id}/status', name: 'api_employer_applications_status', methods: ['POST'])]
    public function changeStatus(string $id, Request $request): JsonResponse
    {
        $swipe = $this->findOwnedApplication($id);
        $payload = $this->jsonPayload($request);
        $status = SwipeStatus::from((string) ($payload['status'] ?? 'viewed'));

        $swipe->setStatus($status);
        if ($status === SwipeStatus::Viewed && null === $swipe->getViewedAt()) {
            $swipe->setViewedAt(new \DateTimeImmutable());
        }

        $this->notificationService->notify(
            $swipe->getCandidate(),
            'application_status',
            'Statut de candidature mis a jour',
            sprintf('Votre candidature pour %s est maintenant: %s.', $swipe->getOffer()->getTitle(), $status->value),
            ['swipeId' => (string) $swipe->getId(), 'status' => $status->value],
        );

        $this->entityManager->flush();

        return $this->json(['application' => $this->serializeApplication($swipe, true)]);
    }

    private function getEmployer(): Employer
    {
        $user = $this->getUser();
        if (!$user instanceof User || !$user->getEmployer() instanceof Employer) {
            throw $this->createAccessDeniedException('Profil employeur requis.');
        }

        return $user->getEmployer();
    }

    private function findOwnedApplication(string $id): Swipe
    {
        $swipe = $this->swipeRepository->find($id);
        $employer = $this->getEmployer();

        if (!$swipe instanceof Swipe || $swipe->isDeleted() || $swipe->getOffer()->getEmployer()->getId()?->toString() !== $employer->getUser()->getId()?->toString()) {
            throw $this->createNotFoundException('Candidature introuvable.');
        }

        return $swipe;
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonPayload(Request $request): array
    {
        $payload = json_decode($request->getContent(), true);

        return is_array($payload) ? $payload : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeApplication(Swipe $swipe, bool $includeDetails): array
    {
        $candidateProfile = $swipe->getCandidate()->getCandidateProfile();
        $offer = $swipe->getOffer();

        $data = [
            'id' => (string) $swipe->getId(),
            'status' => $swipe->getStatus()->value,
            'direction' => $swipe->getDirection()->value,
            'matchScore' => $swipe->getMatchScore(),
            'sentAt' => $swipe->getSentAt()->format(DATE_ATOM),
            'viewedAt' => $swipe->getViewedAt()?->format(DATE_ATOM),
            'offer' => [
                'id' => (string) $offer->getId(),
                'title' => $offer->getTitle(),
            ],
            'candidate' => null === $candidateProfile ? null : [
                'id' => (string) $swipe->getCandidate()->getId(),
                'firstName' => $candidateProfile->getFirstName(),
                'lastName' => $candidateProfile->getLastName(),
                'city' => $candidateProfile->getCity(),
                'educationLevel' => $candidateProfile->getEducationLevel(),
                'skills' => $candidateProfile->getSkills(),
            ],
        ];

        if ($includeDetails) {
            $data['cvUsedUrl'] = $swipe->getCvUsedUrl();
            $data['motivationLetterText'] = $swipe->getMotivationLetterText();
            $data['documentsSent'] = $swipe->getDocumentsSent();
        }

        return $data;
    }
}
