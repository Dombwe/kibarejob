<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260509000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add external job import sources and trace imported job offers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE job_import_sources (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            name VARCHAR(120) NOT NULL,
            provider VARCHAR(80) DEFAULT 'generic_json' NOT NULL,
            api_url LONGTEXT NOT NULL,
            rapid_api_host VARCHAR(120) DEFAULT NULL,
            api_key_env_name VARCHAR(120) DEFAULT NULL,
            http_method VARCHAR(10) DEFAULT 'GET' NOT NULL,
            headers JSON DEFAULT NULL,
            query_params JSON DEFAULT NULL,
            items_path VARCHAR(160) DEFAULT 'data' NOT NULL,
            field_mapping JSON DEFAULT NULL,
            target_countries JSON NOT NULL,
            target_locations JSON NOT NULL,
            keywords JSON DEFAULT NULL,
            enabled TINYINT(1) DEFAULT 1 NOT NULL,
            auto_publish TINYINT(1) DEFAULT 1 NOT NULL,
            max_items_per_run INT DEFAULT 50 NOT NULL,
            min_reliability_score INT DEFAULT 55 NOT NULL,
            last_run_at DATETIME DEFAULT NULL,
            last_success_at DATETIME DEFAULT NULL,
            last_error LONGTEXT DEFAULT NULL,
            last_imported_count INT DEFAULT 0 NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("ALTER TABLE job_offers
            ADD source_type VARCHAR(80) DEFAULT NULL,
            ADD external_source_name VARCHAR(120) DEFAULT NULL,
            ADD external_id VARCHAR(255) DEFAULT NULL,
            ADD external_url LONGTEXT DEFAULT NULL,
            ADD reliability_score INT DEFAULT 0 NOT NULL,
            ADD imported_at DATETIME DEFAULT NULL");

        $this->addSql('CREATE INDEX idx_job_offers_external_source ON job_offers (external_source_name, external_id)');
        $this->addSql('CREATE INDEX idx_job_import_sources_enabled ON job_import_sources (enabled)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE job_import_sources');
        $this->addSql('DROP INDEX idx_job_offers_external_source ON job_offers');
        $this->addSql('ALTER TABLE job_offers DROP source_type, DROP external_source_name, DROP external_id, DROP external_url, DROP reliability_score, DROP imported_at');
    }
}
