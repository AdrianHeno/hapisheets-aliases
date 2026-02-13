<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260214120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create inbound_raw table for raw MIME storage (Mailgun inbound).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE inbound_raw (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, received_at DATETIME NOT NULL, raw_mime CLOB NOT NULL, alias_id INTEGER NOT NULL, CONSTRAINT FK_INBOUND_RAW_ALIAS FOREIGN KEY (alias_id) REFERENCES alias (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_inbound_raw_alias_received_at ON inbound_raw (alias_id, received_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE inbound_raw');
    }
}
