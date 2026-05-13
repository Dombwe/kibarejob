<?php

namespace App\Repository;

use App\Entity\Swipe;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SwipeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, Swipe::class); }

    /**
     * @return string[]
     */
    public function findOfferIdsByCandidate(object $candidate): array
    {
        $rows = $this->createQueryBuilder('swipe')
            ->select('IDENTITY(swipe.offer) AS offerId')
            ->andWhere('swipe.candidate = :candidate')
            ->andWhere('swipe.isDeleted = false')
            ->setParameter('candidate', $candidate)
            ->getQuery()
            ->getScalarResult();

        return array_values(array_filter(array_map(
            static fn (array $row): string => (string) ($row['offerId'] ?? ''),
            $rows,
        )));
    }
}
