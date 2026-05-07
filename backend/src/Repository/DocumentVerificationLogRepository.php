<?php

namespace App\Repository;

use App\Entity\DocumentVerificationLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DocumentVerificationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, DocumentVerificationLog::class); }
}
