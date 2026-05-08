<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508000400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add education field to job offers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_offers ADD education_field VARCHAR(150) DEFAULT NULL AFTER required_education');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_offers DROP education_field');
    }
}
