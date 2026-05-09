<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260509000500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add scheduled commands admin configuration.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE scheduled_commands (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            name VARCHAR(140) NOT NULL,
            command_name VARCHAR(140) NOT NULL,
            arguments JSON DEFAULT NULL,
            options JSON DEFAULT NULL,
            frequency VARCHAR(40) DEFAULT 'daily' NOT NULL,
            custom_cron_expression VARCHAR(80) DEFAULT NULL,
            preferred_time TIME DEFAULT NULL,
            enabled TINYINT(1) DEFAULT 1 NOT NULL,
            description LONGTEXT DEFAULT NULL,
            last_run_at DATETIME DEFAULT NULL,
            last_success_at DATETIME DEFAULT NULL,
            last_exit_code INT DEFAULT NULL,
            last_duration_ms INT DEFAULT NULL,
            last_output LONGTEXT DEFAULT NULL,
            last_error LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE INDEX idx_scheduled_commands_enabled ON scheduled_commands (enabled)");
        $this->addSql("INSERT INTO scheduled_commands (id, name, command_name, arguments, options, frequency, preferred_time, enabled, description, created_at) VALUES
            (UUID(), 'Import automatique des offres externes', 'jobs:import-external', NULL, NULL, 'every_6_hours', '02:00:00', 1, 'Importe les offres depuis les sources actives configurees dans Sources d offres.', NOW()),
            (UUID(), 'Precalcul des scores de matching', 'matching:precompute-scores', NULL, NULL, 'daily', '03:00:00', 1, 'Recalcule les scores candidats/offres pour fluidifier le feed.', NOW()),
            (UUID(), 'Nettoyage des documents expires', 'document:cleanup', NULL, NULL, 'daily', '04:00:00', 1, 'Nettoie les documents et chunks expires.', NOW()),
            (UUID(), 'Reinitialisation quotas journaliers', 'subscription:reset-daily', NULL, NULL, 'daily', '00:05:00', 1, 'Remet a zero les compteurs quotidiens.', NOW()),
            (UUID(), 'Reinitialisation quotas mensuels', 'subscription:reset-monthly', NULL, NULL, 'monthly', '00:10:00', 1, 'Remet a zero les compteurs mensuels.', NOW())");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE scheduled_commands');
    }
}
