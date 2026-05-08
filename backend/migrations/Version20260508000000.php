<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification and password reset fields to users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD is_email_verified TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE users ADD email_verification_token_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql("ALTER TABLE users ADD email_verification_token_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE users ADD password_reset_token_hash VARCHAR(64) DEFAULT NULL');
        $this->addSql("ALTER TABLE users ADD password_reset_token_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE INDEX idx_users_email_verification_token_hash ON users (email_verification_token_hash)');
        $this->addSql('CREATE INDEX idx_users_password_reset_token_hash ON users (password_reset_token_hash)');
        $this->addSql('UPDATE users SET is_email_verified = 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_users_email_verification_token_hash ON users');
        $this->addSql('DROP INDEX idx_users_password_reset_token_hash ON users');
        $this->addSql('ALTER TABLE users DROP is_email_verified');
        $this->addSql('ALTER TABLE users DROP email_verification_token_hash');
        $this->addSql('ALTER TABLE users DROP email_verification_token_expires_at');
        $this->addSql('ALTER TABLE users DROP password_reset_token_hash');
        $this->addSql('ALTER TABLE users DROP password_reset_token_expires_at');
    }
}
