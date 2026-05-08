<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add positions count to job offers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_offers ADD positions INT DEFAULT 1 NOT NULL AFTER title');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_offers DROP positions');
    }
}
