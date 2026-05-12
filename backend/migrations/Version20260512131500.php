<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512131500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les experiences, centres interet et references aux profils candidats.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE candidate_profiles ADD experiences JSON DEFAULT NULL, ADD interests JSON DEFAULT NULL, ADD `references` JSON DEFAULT NULL");
        $this->addSql("UPDATE candidate_profiles SET experiences = JSON_ARRAY(), interests = JSON_ARRAY(), `references` = JSON_ARRAY()");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE candidate_profiles DROP experiences, DROP interests, DROP `references`');
    }
}
