<?php

namespace App\Repository;

use App\Entity\ScheduledCommand;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ScheduledCommandRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScheduledCommand::class);
    }

    /**
     * @return ScheduledCommand[]
     */
    public function findEnabled(): array
    {
        return $this->findBy(['enabled' => true], ['createdAt' => 'ASC']);
    }
}
