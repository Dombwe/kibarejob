<?php

namespace App\Controller;

use App\Entity\Enum\ContractType;
use App\Entity\Enum\JobOfferStatus;
use App\Entity\JobOffer;
use App\Repository\JobOfferRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PublicJobController extends AbstractController
{
    #[Route('/offres-emploi', name: 'public_jobs', methods: ['GET'])]
    public function index(Request $request, JobOfferRepository $jobOfferRepository): Response
    {
        $filters = [
            'q' => trim((string) $request->query->get('q')),
            'location' => trim((string) $request->query->get('location')),
            'country' => trim((string) $request->query->get('country')),
            'sector' => trim((string) $request->query->get('sector')),
            'contractType' => trim((string) $request->query->get('contractType')),
            'remote' => trim((string) $request->query->get('remote')),
            'source' => trim((string) $request->query->get('source')),
            'sort' => trim((string) $request->query->get('sort', 'recent')),
        ];

        $queryBuilder = $this->baseOffersQuery($jobOfferRepository);
        $this->applyFilters($queryBuilder, $filters);
        $this->applySorting($queryBuilder, $filters['sort']);

        $offers = $queryBuilder
            ->setMaxResults(60)
            ->getQuery()
            ->getResult();

        return $this->render('jobs/index.html.twig', [
            'offers' => $offers,
            'filters' => $filters,
            'filterOptions' => $this->filterOptions($jobOfferRepository),
            'activeFiltersCount' => $this->activeFiltersCount($filters),
            'likedOfferIds' => $this->likedOfferIds($request),
            'contractTypes' => ContractType::cases(),
        ]);
    }

    #[Route('/offres-emploi/{id}', name: 'public_job_detail', methods: ['GET'])]
    public function detail(JobOffer $offer, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyInvisibleOffer($offer);

        $viewedKey = 'public_viewed_offer_' . (string) $offer->getId();
        if (!$request->getSession()->get($viewedKey)) {
            $offer->setViewsCount($offer->getViewsCount() + 1);
            $request->getSession()->set($viewedKey, true);
            $entityManager->flush();
        }

        return $this->render('jobs/detail.html.twig', [
            'offer' => $offer,
            'isLiked' => in_array((string) $offer->getId(), $this->likedOfferIds($request), true),
            'similarOffers' => $this->similarOffers($offer, $entityManager),
        ]);
    }

    #[Route('/offres-emploi/{id}/like', name: 'public_job_like', methods: ['POST'])]
    public function like(JobOffer $offer, Request $request, EntityManagerInterface $entityManager): JsonResponse|RedirectResponse
    {
        $this->denyInvisibleOffer($offer);

        if (!$this->isCsrfTokenValid('like_offer_' . (string) $offer->getId(), (string) $request->request->get('_csrf_token'))) {
            if ($this->expectsJson($request)) {
                return $this->json([
                    'success' => false,
                    'message' => 'Votre session a expire. Merci de reessayer.',
                ], Response::HTTP_FORBIDDEN);
            }

            $this->addFlash('error', 'Votre session a expire. Merci de reessayer.');
            return $this->redirectToRoute('public_job_detail', ['id' => (string) $offer->getId()]);
        }

        $likedOfferIds = $this->likedOfferIds($request);
        $offerId = (string) $offer->getId();
        $liked = !in_array($offerId, $likedOfferIds, true);

        if ($liked) {
            $likedOfferIds[] = $offerId;
            $offer->setLikesCount($offer->getLikesCount() + 1);
            $message = 'Offre ajoutee a vos favoris.';
        } else {
            $likedOfferIds = array_values(array_filter($likedOfferIds, static fn (string $likedOfferId): bool => $likedOfferId !== $offerId));
            $offer->setLikesCount($offer->getLikesCount() - 1);
            $message = 'Offre retiree de vos favoris.';
        }

        $request->getSession()->set('public_liked_offer_ids', $likedOfferIds);
        $entityManager->flush();

        if ($this->expectsJson($request)) {
            return $this->json([
                'success' => true,
                'liked' => $liked,
                'likesCount' => $offer->getLikesCount(),
                'message' => $message,
            ]);
        }

        if (!in_array($offerId, $likedOfferIds, true)) {
            $this->addFlash('success', 'Offre retiree de vos favoris.');
        } else {
            $this->addFlash('success', 'Offre ajoutee a vos favoris.');
        }

        return $this->redirectToRoute('public_job_detail', ['id' => $offerId]);
    }

    private function baseOffersQuery(JobOfferRepository $jobOfferRepository): QueryBuilder
    {
        return $jobOfferRepository->createQueryBuilder('offer')
            ->leftJoin('offer.employer', 'employerUser')
            ->leftJoin('employerUser.employer', 'employerProfile')
            ->andWhere('offer.status = :status')
            ->andWhere('offer.isDeleted = false')
            ->andWhere('offer.deadline >= :today')
            ->setParameter('status', JobOfferStatus::Active)
            ->setParameter('today', new \DateTimeImmutable('today'));
    }

    /**
     * @param array<string, string> $filters
     */
    private function applyFilters(QueryBuilder $queryBuilder, array $filters): void
    {
        if ('' !== $filters['q']) {
            $queryBuilder
                ->andWhere('LOWER(offer.title) LIKE :query OR LOWER(offer.description) LIKE :query OR LOWER(employerProfile.companyName) LIKE :query OR LOWER(offer.externalSourceName) LIKE :query')
                ->setParameter('query', '%' . mb_strtolower($filters['q']) . '%');
        }

        if ('' !== $filters['location']) {
            $queryBuilder
                ->andWhere('LOWER(offer.location) LIKE :location')
                ->setParameter('location', '%' . mb_strtolower($filters['location']) . '%');
        }

        if ('' !== $filters['country']) {
            $queryBuilder
                ->andWhere('LOWER(employerProfile.countryName) = :country OR LOWER(offer.location) LIKE :countryLike')
                ->setParameter('country', mb_strtolower($filters['country']))
                ->setParameter('countryLike', '%' . mb_strtolower($filters['country']) . '%');
        }

        if ('' !== $filters['sector']) {
            $queryBuilder
                ->andWhere('LOWER(employerProfile.sector) = :sector')
                ->setParameter('sector', mb_strtolower($filters['sector']));
        }

        if ('' !== $filters['contractType']) {
            $contractType = ContractType::tryFrom($filters['contractType']);
            if ($contractType instanceof ContractType) {
                $queryBuilder
                    ->andWhere('offer.contractType = :contractType')
                    ->setParameter('contractType', $contractType);
            }
        }

        if ('yes' === $filters['remote']) {
            $queryBuilder->andWhere('offer.isRemoteAllowed = true');
        }

        if ('external' === $filters['source']) {
            $queryBuilder->andWhere('offer.sourceType IS NOT NULL OR offer.externalSourceName IS NOT NULL');
        } elseif ('internal' === $filters['source']) {
            $queryBuilder->andWhere('offer.sourceType IS NULL AND offer.externalSourceName IS NULL');
        }
    }

    private function applySorting(QueryBuilder $queryBuilder, string $sort): void
    {
        match ($sort) {
            'popular' => $queryBuilder->orderBy('offer.viewsCount', 'DESC'),
            'applications' => $queryBuilder->orderBy('offer.applicationsCount', 'DESC'),
            'liked' => $queryBuilder->orderBy('offer.likesCount', 'DESC'),
            'deadline' => $queryBuilder->orderBy('offer.deadline', 'ASC'),
            default => $queryBuilder->orderBy('offer.createdAt', 'DESC'),
        };

        $queryBuilder->addOrderBy('offer.createdAt', 'DESC');
    }

    /**
     * @return array{countries: string[], locations: string[], sectors: string[]}
     */
    private function filterOptions(JobOfferRepository $jobOfferRepository): array
    {
        $baseQuery = $this->baseOffersQuery($jobOfferRepository);

        return [
            'countries' => $this->distinctValues(clone $baseQuery, 'employerProfile.countryName'),
            'locations' => $this->distinctValues(clone $baseQuery, 'offer.location'),
            'sectors' => $this->distinctValues(clone $baseQuery, 'employerProfile.sector'),
        ];
    }

    /**
     * @return string[]
     */
    private function distinctValues(QueryBuilder $queryBuilder, string $field): array
    {
        $rows = $queryBuilder
            ->select(sprintf('DISTINCT %s AS value', $field))
            ->andWhere(sprintf('%s IS NOT NULL', $field))
            ->andWhere(sprintf("%s != ''", $field))
            ->orderBy($field, 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_values(array_filter(array_map(static fn (array $row): string => trim((string) $row['value']), $rows)));
    }

    /**
     * @param array<string, string> $filters
     */
    private function activeFiltersCount(array $filters): int
    {
        $ignoredDefaults = ['sort' => 'recent'];
        $count = 0;

        foreach ($filters as $key => $value) {
            if (array_key_exists($key, $ignoredDefaults) && $ignoredDefaults[$key] === $value) {
                continue;
            }

            if ('' !== $value) {
                ++$count;
            }
        }

        return $count;
    }

    private function expectsJson(Request $request): bool
    {
        return $request->isXmlHttpRequest() || str_contains((string) $request->headers->get('Accept'), 'application/json');
    }

    private function denyInvisibleOffer(JobOffer $offer): void
    {
        if ($offer->isDeleted() || JobOfferStatus::Active !== $offer->getStatus() || $offer->getDeadline() < new \DateTimeImmutable('today')) {
            throw $this->createNotFoundException('Offre introuvable.');
        }
    }

    /**
     * @return string[]
     */
    private function likedOfferIds(Request $request): array
    {
        $value = $request->getSession()->get('public_liked_offer_ids', []);

        return is_array($value) ? array_values(array_filter(array_map('strval', $value))) : [];
    }

    /**
     * @return JobOffer[]
     */
    private function similarOffers(JobOffer $offer, EntityManagerInterface $entityManager): array
    {
        return $entityManager->getRepository(JobOffer::class)
            ->createQueryBuilder('similar')
            ->andWhere('similar.id != :id')
            ->andWhere('similar.status = :status')
            ->andWhere('similar.isDeleted = false')
            ->andWhere('similar.deadline >= :today')
            ->andWhere('LOWER(similar.location) LIKE :location')
            ->setParameter('id', $offer->getId())
            ->setParameter('status', JobOfferStatus::Active)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->setParameter('location', '%' . mb_strtolower($offer->getLocation()) . '%')
            ->orderBy('similar.createdAt', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();
    }
}
