<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renomme la colonne references du profil candidat pour eviter le mot reserve SQL.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE candidate_profiles CHANGE `references` profile_references JSON DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE candidate_profiles CHANGE profile_references `references` JSON DEFAULT NULL");
    }
}
