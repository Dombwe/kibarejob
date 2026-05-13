<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513000500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add performance indexes for feed, applications, documents and notifications.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX idx_job_offers_feed ON job_offers (is_deleted, status, deadline, created_at)');
        $this->addSql('CREATE INDEX idx_job_offers_employer_created ON job_offers (employer_id, is_deleted, created_at)');
        $this->addSql('CREATE INDEX idx_job_offers_source_external ON job_offers (source_type, external_id)');
        $this->addSql('CREATE INDEX idx_swipes_candidate_sent ON swipes (candidate_id, is_deleted, direction, sent_at)');
        $this->addSql('CREATE INDEX idx_swipes_offer_status ON swipes (offer_id, is_deleted, status, sent_at)');
        $this->addSql('CREATE INDEX idx_candidate_documents_owner_uploaded ON candidate_documents (candidate_id, is_deleted, uploaded_at)');
        $this->addSql('CREATE INDEX idx_candidate_documents_type ON candidate_documents (candidate_id, type, is_deleted)');
        $this->addSql('CREATE INDEX idx_notifications_user_created ON notifications (user_id, is_deleted, created_at)');
        $this->addSql('CREATE INDEX idx_notifications_user_unread ON notifications (user_id, is_deleted, is_read)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_job_offers_feed ON job_offers');
        $this->addSql('DROP INDEX idx_job_offers_employer_created ON job_offers');
        $this->addSql('DROP INDEX idx_job_offers_source_external ON job_offers');
        $this->addSql('DROP INDEX idx_swipes_candidate_sent ON swipes');
        $this->addSql('DROP INDEX idx_swipes_offer_status ON swipes');
        $this->addSql('DROP INDEX idx_candidate_documents_owner_uploaded ON candidate_documents');
        $this->addSql('DROP INDEX idx_candidate_documents_type ON candidate_documents');
        $this->addSql('DROP INDEX idx_notifications_user_created ON notifications');
        $this->addSql('DROP INDEX idx_notifications_user_unread ON notifications');
    }
}
