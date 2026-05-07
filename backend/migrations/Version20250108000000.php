<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250108000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create reports table for admin moderation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE reports (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            reporter_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            target_type VARCHAR(50) NOT NULL,
            target_id VARCHAR(36) NOT NULL,
            reason VARCHAR(100) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            resolved_at DATETIME DEFAULT NULL,
            resolved_by_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            INDEX idx_reports_target (target_type, target_id),
            INDEX idx_reports_status_created (status, created_at),
            INDEX idx_reports_reporter (reporter_id),
            INDEX idx_reports_resolved_by (resolved_by_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE reports ADD CONSTRAINT FK_REPORT_REPORTER FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE reports ADD CONSTRAINT FK_REPORT_RESOLVED_BY FOREIGN KEY (resolved_by_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE reports');
    }
}
