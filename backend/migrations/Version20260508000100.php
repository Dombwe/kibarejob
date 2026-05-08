<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add country fields to employer profiles.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE employeurs ADD country_code VARCHAR(2) NOT NULL DEFAULT 'BF', ADD country_name VARCHAR(100) NOT NULL DEFAULT 'Burkina Faso'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE employeurs DROP country_code, DROP country_name');
    }
}
