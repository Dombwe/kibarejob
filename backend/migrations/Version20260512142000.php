<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512142000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les informations de suivi email aux candidatures.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE swipes ADD email_sent TINYINT(1) DEFAULT 0 NOT NULL, ADD email_recipient VARCHAR(255) DEFAULT NULL, ADD email_sender VARCHAR(255) DEFAULT NULL, ADD email_reply_to VARCHAR(255) DEFAULT NULL, ADD email_subject VARCHAR(255) DEFAULT NULL, ADD email_body LONGTEXT DEFAULT NULL, ADD email_error LONGTEXT DEFAULT NULL, ADD email_sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE swipes DROP email_sent, DROP email_recipient, DROP email_sender, DROP email_reply_to, DROP email_subject, DROP email_body, DROP email_error, DROP email_sent_at');
    }
}
