<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260509000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add scheduled publication date to job offers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_offers ADD scheduled_publish_at DATETIME DEFAULT NULL AFTER created_at');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_offers DROP scheduled_publish_at');
    }
}
