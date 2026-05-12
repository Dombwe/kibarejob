<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512133500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rend obligatoires les nouvelles listes JSON du profil candidat apres initialisation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE candidate_profiles SET experiences = JSON_ARRAY() WHERE experiences IS NULL");
        $this->addSql("UPDATE candidate_profiles SET interests = JSON_ARRAY() WHERE interests IS NULL");
        $this->addSql("UPDATE candidate_profiles SET profile_references = JSON_ARRAY() WHERE profile_references IS NULL");
        $this->addSql("ALTER TABLE candidate_profiles CHANGE experiences experiences JSON NOT NULL, CHANGE interests interests JSON NOT NULL, CHANGE profile_references profile_references JSON NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE candidate_profiles CHANGE experiences experiences JSON DEFAULT NULL, CHANGE interests interests JSON DEFAULT NULL, CHANGE profile_references profile_references JSON DEFAULT NULL");
    }
}
