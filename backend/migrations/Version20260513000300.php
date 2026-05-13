<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Repair missing employer user index and foreign key on local databases.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->hasIndex('UNIQ_EMPLOYEURS_USER_ID') && !$this->hasIndex('UNIQ_1E89DFF9A76ED395')) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_EMPLOYEURS_USER_ID ON employeurs (user_id)');
        }

        if (!$this->hasForeignKey('FK_EMPLOYER_USER') && !$this->hasForeignKey('FK_1E89DFF9A76ED395')) {
            $this->addSql('ALTER TABLE employeurs ADD CONSTRAINT FK_EMPLOYER_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        }
    }

    public function down(Schema $schema): void
    {
        if ($this->hasForeignKey('FK_EMPLOYER_USER')) {
            $this->addSql('ALTER TABLE employeurs DROP FOREIGN KEY FK_EMPLOYER_USER');
        }

        if ($this->hasIndex('UNIQ_EMPLOYEURS_USER_ID')) {
            $this->addSql('DROP INDEX UNIQ_EMPLOYEURS_USER_ID ON employeurs');
        }
    }

    private function hasIndex(string $index): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            ['employeurs', $index]
        );
    }

    private function hasForeignKey(string $constraint): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            ['employeurs', $constraint]
        );
    }
}
