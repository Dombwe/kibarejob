<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250101000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create KIBARE-JOB core schema with UUID identifiers.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE users (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            email VARCHAR(255) NOT NULL,
            phone VARCHAR(20) DEFAULT NULL,
            password_hash VARCHAR(255) NOT NULL,
            roles JSON NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            profile_completed_percent INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME DEFAULT NULL,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            last_login DATETIME DEFAULT NULL,
            subscription_tier ENUM('free','premium') NOT NULL DEFAULT 'free',
            subscription_expiry DATE DEFAULT NULL,
            subscription_started_at DATE DEFAULT NULL,
            swipes_used_today INT NOT NULL DEFAULT 0,
            last_swipe_reset DATE NOT NULL,
            UNIQUE INDEX uniq_users_email (email),
            UNIQUE INDEX uniq_users_phone (phone),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE candidate_profiles (
            user_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            photo_url LONGTEXT DEFAULT NULL,
            birth_date DATE DEFAULT NULL,
            city VARCHAR(100) NOT NULL,
            education_level VARCHAR(50) NOT NULL,
            education_field VARCHAR(100) DEFAULT NULL,
            skills JSON NOT NULL,
            languages JSON NOT NULL,
            driving_license TINYINT(1) NOT NULL DEFAULT 0,
            driving_license_category VARCHAR(10) DEFAULT NULL,
            availability VARCHAR(50) NOT NULL,
            salary_expectation INT DEFAULT NULL,
            cv_original_url LONGTEXT DEFAULT NULL,
            cv_generated_url LONGTEXT DEFAULT NULL,
            cv_last_updated DATETIME DEFAULT NULL,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY(user_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE employeurs (
            user_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            company_name VARCHAR(255) NOT NULL,
            nif VARCHAR(50) DEFAULT NULL,
            sector VARCHAR(100) NOT NULL,
            company_size VARCHAR(50) DEFAULT NULL,
            cities JSON NOT NULL,
            logo_url LONGTEXT DEFAULT NULL,
            description LONGTEXT DEFAULT NULL,
            website VARCHAR(255) DEFAULT NULL,
            subscription_tier ENUM('free','standard','pro') NOT NULL DEFAULT 'free',
            subscription_expiry DATE DEFAULT NULL,
            is_validated TINYINT(1) NOT NULL DEFAULT 0,
            offers_used_this_month INT NOT NULL DEFAULT 0,
            applications_viewed_this_month INT NOT NULL DEFAULT 0,
            last_offer_reset DATE NOT NULL,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY(user_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE job_offers (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            employer_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            title VARCHAR(255) NOT NULL,
            description LONGTEXT NOT NULL,
            required_skills JSON NOT NULL,
            required_education VARCHAR(50) NOT NULL,
            required_experience_years INT NOT NULL DEFAULT 0,
            contract_type ENUM('CDI','CDD','Stage','Freelance','Contrat local') NOT NULL,
            location VARCHAR(100) NOT NULL,
            salary_min INT DEFAULT NULL,
            salary_max INT DEFAULT NULL,
            is_remote_allowed TINYINT(1) NOT NULL DEFAULT 0,
            required_documents JSON DEFAULT NULL,
            recommended_documents JSON DEFAULT NULL,
            deadline DATE NOT NULL,
            is_boosted TINYINT(1) NOT NULL DEFAULT 0,
            views_count INT NOT NULL DEFAULT 0,
            applications_count INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            status ENUM('active','closed','draft') NOT NULL DEFAULT 'active',
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE candidate_documents (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            candidate_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            type ENUM('diploma','certificate','attestation','other') NOT NULL,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            issuing_organization VARCHAR(255) DEFAULT NULL,
            issue_date DATE DEFAULT NULL,
            expiry_date DATE DEFAULT NULL,
            document_number VARCHAR(100) DEFAULT NULL,
            file_url LONGTEXT NOT NULL,
            file_hash VARCHAR(64) NOT NULL,
            is_verified TINYINT(1) NOT NULL DEFAULT 0,
            confidence_score INT NOT NULL DEFAULT 0,
            is_public TINYINT(1) NOT NULL DEFAULT 1,
            is_pinned TINYINT(1) NOT NULL DEFAULT 0,
            tags JSON DEFAULT NULL,
            uploaded_at DATETIME NOT NULL,
            last_verified_at DATETIME DEFAULT NULL,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE swipes (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            candidate_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            offer_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            direction ENUM('like','dislike','superlike') NOT NULL,
            match_score INT DEFAULT NULL,
            cv_used_url LONGTEXT DEFAULT NULL,
            motivation_letter_text LONGTEXT DEFAULT NULL,
            documents_sent JSON DEFAULT NULL,
            status ENUM('sent','viewed','interview','rejected','hired') NOT NULL DEFAULT 'sent',
            sent_at DATETIME NOT NULL,
            viewed_at DATETIME DEFAULT NULL,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE INDEX uniq_swipe_candidate_offer (candidate_id, offer_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE document_requests (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            employer_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            candidate_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            offer_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            document_type VARCHAR(100) NOT NULL,
            status ENUM('pending','provided','expired') NOT NULL DEFAULT 'pending',
            requested_at DATETIME NOT NULL,
            provided_at DATETIME DEFAULT NULL,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE notifications (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            user_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message LONGTEXT NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            data JSON DEFAULT NULL,
            created_at DATETIME NOT NULL,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE payments (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            user_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            amount INT NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT 'XOF',
            method ENUM('orange_money','moov_money','card') NOT NULL,
            subscription_type VARCHAR(50) DEFAULT NULL,
            transaction_id VARCHAR(255) NOT NULL,
            status ENUM('pending','success','failed') NOT NULL,
            created_at DATETIME NOT NULL,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE document_verification_logs (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            document_id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            verification_date DATETIME NOT NULL,
            ai_score INT NOT NULL,
            admin_override TINYINT(1) NOT NULL DEFAULT 0,
            admin_id CHAR(36) DEFAULT NULL COMMENT '(DC2Type:uuid)',
            action ENUM('auto_verified','flagged','admin_validated','admin_rejected') NOT NULL,
            reason LONGTEXT DEFAULT NULL,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE subscription_plans (
            id CHAR(36) NOT NULL COMMENT '(DC2Type:uuid)',
            name VARCHAR(50) NOT NULL,
            target ENUM('candidat','employeur') NOT NULL,
            price INT NOT NULL,
            price_3months INT DEFAULT NULL,
            price_6months INT DEFAULT NULL,
            price_12months INT DEFAULT NULL,
            features JSON DEFAULT NULL,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("CREATE TABLE stats_cache (
            id INT AUTO_INCREMENT NOT NULL,
            stat_key VARCHAR(100) NOT NULL,
            stat_value JSON NOT NULL,
            calculated_at DATETIME NOT NULL,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            UNIQUE INDEX uniq_stats_cache_key (stat_key),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql('ALTER TABLE candidate_profiles ADD CONSTRAINT FK_CANDIDATE_PROFILE_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE employeurs ADD CONSTRAINT FK_EMPLOYER_USER FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE job_offers ADD CONSTRAINT FK_JOB_OFFER_EMPLOYER FOREIGN KEY (employer_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE candidate_documents ADD CONSTRAINT FK_CANDIDATE_DOCUMENT_USER FOREIGN KEY (candidate_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE swipes ADD CONSTRAINT FK_SWIPE_CANDIDATE FOREIGN KEY (candidate_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE swipes ADD CONSTRAINT FK_SWIPE_OFFER FOREIGN KEY (offer_id) REFERENCES job_offers (id)');
        $this->addSql('ALTER TABLE document_requests ADD CONSTRAINT FK_DOCUMENT_REQUEST_EMPLOYER FOREIGN KEY (employer_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE document_requests ADD CONSTRAINT FK_DOCUMENT_REQUEST_CANDIDATE FOREIGN KEY (candidate_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE document_requests ADD CONSTRAINT FK_DOCUMENT_REQUEST_OFFER FOREIGN KEY (offer_id) REFERENCES job_offers (id)');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_NOTIFICATION_USER FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE payments ADD CONSTRAINT FK_PAYMENT_USER FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE document_verification_logs ADD CONSTRAINT FK_VERIFICATION_DOCUMENT FOREIGN KEY (document_id) REFERENCES candidate_documents (id)');
        $this->addSql('ALTER TABLE document_verification_logs ADD CONSTRAINT FK_VERIFICATION_ADMIN FOREIGN KEY (admin_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE document_verification_logs');
        $this->addSql('DROP TABLE payments');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE document_requests');
        $this->addSql('DROP TABLE swipes');
        $this->addSql('DROP TABLE candidate_documents');
        $this->addSql('DROP TABLE job_offers');
        $this->addSql('DROP TABLE employeurs');
        $this->addSql('DROP TABLE candidate_profiles');
        $this->addSql('DROP TABLE subscription_plans');
        $this->addSql('DROP TABLE stats_cache');
        $this->addSql('DROP TABLE users');
    }
}
