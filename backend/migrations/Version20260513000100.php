<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Give employers a dedicated UUID primary key for admin CRUD actions.';
    }

    public function up(Schema $schema): void
    {
        if (!$this->hasColumn('id')) {
            $this->addSql("ALTER TABLE employeurs ADD id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)' FIRST");
        }

        $hasUserForeignKey = $this->hasForeignKey('FK_EMPLOYER_USER');
        $hasUserUniqueIndex = $this->hasIndex('UNIQ_EMPLOYEURS_USER_ID');
        $primaryColumns = $this->primaryColumns();

        $this->addSql('UPDATE employeurs SET id = UUID() WHERE id IS NULL');
        $this->addSql("ALTER TABLE employeurs CHANGE id id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)'");

        if ($hasUserForeignKey) {
            $this->addSql('ALTER TABLE employeurs DROP FOREIGN KEY FK_EMPLOYER_USER');
        }

        if (['user_id'] === $primaryColumns) {
            $this->addSql('ALTER TABLE employeurs DROP PRIMARY KEY');
        }

        if (['id'] !== $primaryColumns) {
            $this->addSql('ALTER TABLE employeurs ADD PRIMARY KEY (id)');
        }

        if (!$hasUserUniqueIndex) {
            $this->addSql('CREATE UNIQUE INDEX UNIQ_EMPLOYEURS_USER_ID ON employeurs (user_id)');
        }

        $this->addSql('ALTER TABLE employeurs ADD CONSTRAINT FK_EMPLOYER_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        if ($this->hasForeignKey('FK_EMPLOYER_USER')) {
            $this->addSql('ALTER TABLE employeurs DROP FOREIGN KEY FK_EMPLOYER_USER');
        }

        if ($this->hasIndex('UNIQ_EMPLOYEURS_USER_ID')) {
            $this->addSql('DROP INDEX UNIQ_EMPLOYEURS_USER_ID ON employeurs');
        }

        if (['id'] === $this->primaryColumns()) {
            $this->addSql('ALTER TABLE employeurs DROP PRIMARY KEY');
        }

        if ($this->hasColumn('id')) {
            $this->addSql('ALTER TABLE employeurs DROP id');
        }

        if (['user_id'] !== $this->primaryColumns()) {
            $this->addSql('ALTER TABLE employeurs ADD PRIMARY KEY (user_id)');
        }

        if (!$this->hasForeignKey('FK_EMPLOYER_USER')) {
            $this->addSql('ALTER TABLE employeurs ADD CONSTRAINT FK_EMPLOYER_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        }
    }

    private function hasColumn(string $column): bool
    {
        return false !== $this->connection->fetchOne(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['employeurs', $column]
        );
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

    /**
     * @return list<string>
     */
    private function primaryColumns(): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? ORDER BY SEQ_IN_INDEX',
            ['employeurs', 'PRIMARY']
        );
    }
}
