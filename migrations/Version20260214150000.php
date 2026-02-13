<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260214150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add message.preview_snippet and message.has_html_body for inbox list.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD COLUMN preview_snippet VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE message ADD COLUMN has_html_body BOOLEAN DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message DROP COLUMN preview_snippet');
        $this->addSql('ALTER TABLE message DROP COLUMN has_html_body');
    }
}
