<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260509000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add persisted import status details to external job sources.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_import_sources ADD last_skipped_count INT DEFAULT 0 NOT NULL, ADD last_status_message LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE job_import_sources DROP last_skipped_count, DROP last_status_message');
    }
}
