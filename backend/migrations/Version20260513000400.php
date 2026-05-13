<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513000400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add maximum required experience years to job offers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_offers ADD required_experience_years_max INT DEFAULT NULL AFTER required_experience_years');
        $this->addSql("UPDATE job_offers SET required_education = 'Bac +3' WHERE required_education = 'Licence'");
        $this->addSql("UPDATE job_offers SET required_education = 'Bac +5' WHERE required_education = 'Master'");
        $this->addSql("UPDATE job_offers SET required_education = 'Bac +8' WHERE required_education = 'Doctorat'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_offers DROP required_experience_years_max');
    }
}
