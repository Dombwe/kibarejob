<?php

namespace App\Controller\Api;

use App\Entity\CandidateProfile;
use App\Entity\Enum\JobOfferStatus;
use App\Entity\JobOffer;
use App\Entity\User;
use App\Repository\JobOfferRepository;
use App\Repository\SwipeRepository;
use App\Service\CacheService;
use App\Service\ScoreCacheService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/jobs')]
class FeedController extends AbstractController
{
    public function __construct(
        private readonly JobOfferRepository $offerRepository,
        private readonly SwipeRepository $swipeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ScoreCacheService $scoreCacheService,
        private readonly CacheService $cacheService,
    ) {
    }

    #[Route('/feed', name: 'api_jobs_feed', methods: ['GET'])]
    public function feed(Request $request): JsonResponse
    {
        $candidate = $this->getCandidateProfile();
        $cursor = max(0, (int) $request->query->get('cursor', 0));
        $limit = min(30, max(1, (int) $request->query->get('limit', 10)));
        $page = intdiv($cursor, $limit) + 1;
        $cacheKeyUser = (string) $candidate->getUser()->getId();

        $cached = $this->cacheService->getFeed($cacheKeyUser, $page);
        if (null !== $cached && ($cached['schemaVersion'] ?? null) === 2 && ($cached['cursor'] ?? null) === $cursor && ($cached['limit'] ?? null) === $limit) {
            return $this->json($cached);
        }

        $swipedOfferIds = $this->getSwipedOfferIds($candidate->getUser());
        $queryBuilder = $this->offerRepository->createQueryBuilder('offer')
            ->andWhere('offer.status = :status')
            ->andWhere('offer.isDeleted = :deleted')
            ->andWhere('offer.deadline >= :today')
            ->setParameter('status', JobOfferStatus::Active)
            ->setParameter('deleted', false)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('offer.isBoosted', 'DESC')
            ->addOrderBy('offer.createdAt', 'DESC')
            ->setFirstResult($cursor)
            ->setMaxResults($limit + 1);

        if ([] !== $swipedOfferIds) {
            $queryBuilder
                ->andWhere('offer.id NOT IN (:swipedOfferIds)')
                ->setParameter('swipedOfferIds', $swipedOfferIds);
        }

        $offers = $queryBuilder->getQuery()->getResult();
        $hasMore = count($offers) > $limit;
        $offers = array_slice($offers, 0, $limit);

        $items = array_map(function (JobOffer $offer) use ($candidate): array {
            $offer->setViewsCount($offer->getViewsCount() + 1);

            return [
                'offer' => $this->serializeOffer($offer),
                'matchScore' => $this->scoreCacheService->getScore($candidate, $offer),
            ];
        }, $offers);

        $this->entityManager->flush();

        $payload = [
            'schemaVersion' => 2,
            'cursor' => $cursor,
            'limit' => $limit,
            'nextCursor' => $hasMore ? $cursor + $limit : null,
            'hasMore' => $hasMore,
            'items' => $items,
        ];
        $this->cacheService->setFeed($cacheKeyUser, $page, $payload);

        return $this->json($payload);
    }

    private function getCandidateProfile(): CandidateProfile
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Profil candidat requis.');
        }

        if ($user->getCandidateProfile() instanceof CandidateProfile) {
            return $user->getCandidateProfile();
        }

        $roles = array_values(array_filter(
            $user->getRoles(),
            static fn (string $role): bool => 'ROLE_USER' !== $role
        ));

        if (!in_array('ROLE_CANDIDATE', $roles, true)) {
            $roles[] = 'ROLE_CANDIDATE';
            $user->setRoles(array_values(array_unique($roles)));
        }

        $profile = (new CandidateProfile())
            ->setUser($user)
            ->setFirstName($user->getFirstName() ?: 'Candidat')
            ->setLastName($user->getLastName() ?: 'KIBARE-JOB')
            ->setCity('Ouagadougou')
            ->setEducationLevel('Aucun')
            ->setSkills([])
            ->setLanguages([['name' => 'Francais', 'level' => 'Debutant']])
            ->setAvailability('Immediate');

        $user->setCandidateProfile($profile);
        $this->entityManager->persist($profile);
        $this->entityManager->flush();

        return $profile;
    }

    /**
     * @return string[]
     */
    private function getSwipedOfferIds(User $candidate): array
    {
        return $this->swipeRepository->findOfferIdsByCandidate($candidate);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOffer(JobOffer $offer): array
    {
        $employer = $offer->getEmployer()->getEmployer();

        return [
            'id' => (string) $offer->getId(),
            'title' => $offer->getTitle(),
            'description' => $offer->getDescription(),
            'requiredSkills' => $offer->getRequiredSkills(),
            'requiredEducation' => $offer->getRequiredEducation(),
            'educationField' => $offer->getEducationField(),
            'requiredExperienceYears' => $offer->getRequiredExperienceYears(),
            'requiredExperienceYearsMax' => $offer->getRequiredExperienceYearsMax(),
            'requiredExperienceLabel' => $offer->getRequiredExperienceLabel(),
            'contractType' => $offer->getContractType()->value,
            'location' => $offer->getLocation(),
            'salaryMin' => $offer->getSalaryMin(),
            'salaryMax' => $offer->getSalaryMax(),
            'isRemoteAllowed' => $offer->isRemoteAllowed(),
            'positions' => $offer->getPositions(),
            'requiredDocuments' => $offer->getRequiredDocuments() ?? [],
            'recommendedDocuments' => $offer->getRecommendedDocuments() ?? [],
            'deadline' => $offer->getDeadline()->format('Y-m-d'),
            'isBoosted' => $offer->isBoosted(),
            'externalUrl' => $offer->getExternalUrl(),
            'applicationEmail' => $offer->getApplicationEmail(),
            'sourceType' => $offer->getSourceType(),
            'externalSourceName' => $offer->getExternalSourceName(),
            'viewsCount' => $offer->getViewsCount(),
            'applicationsCount' => $offer->getApplicationsCount(),
            'likesCount' => $offer->getLikesCount(),
            'createdAt' => $offer->getCreatedAt()->format(DATE_ATOM),
            'company' => null === $employer ? null : [
                'name' => $employer->getCompanyName(),
                'sector' => $employer->getSector(),
                'logoUrl' => $employer->getLogoUrl(),
                'isValidated' => $employer->isValidated(),
            ],
        ];
    }
}
