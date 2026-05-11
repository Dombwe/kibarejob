<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260511000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix application settings UUID column length';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE application_settings MODIFY id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE application_settings MODIFY id BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\'');
    }
}
