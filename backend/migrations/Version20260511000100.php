<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260511000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add application settings for mobile API server configuration';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE application_settings (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(120) NOT NULL, active_environment VARCHAR(20) DEFAULT \'local\' NOT NULL, local_base_url VARCHAR(255) DEFAULT \'https://127.0.0.1:8000\' NOT NULL, online_base_url VARCHAR(255) DEFAULT NULL, description LONGTEXT DEFAULT NULL, active TINYINT(1) DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE application_settings');
    }
}
