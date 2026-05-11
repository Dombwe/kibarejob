<?php

namespace App\Repository;

use App\Entity\ApplicationSetting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ApplicationSettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApplicationSetting::class);
    }

    public function getActiveSettings(): ApplicationSetting
    {
        $settings = $this->findOneBy(['active' => true], ['createdAt' => 'ASC']);
        if ($settings instanceof ApplicationSetting) {
            return $settings;
        }

        return new ApplicationSetting();
    }
}
