<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow Prestation as a job offer contract type.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE job_offers MODIFY contract_type ENUM('CDI','CDD','Stage','Freelance','Prestation','Contrat local') NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE job_offers MODIFY contract_type ENUM('CDI','CDD','Stage','Freelance','Contrat local') NOT NULL");
    }
}
