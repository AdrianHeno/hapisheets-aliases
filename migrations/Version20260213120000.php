<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260213120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create message table (email messages received by an Alias); index on (alias_id, received_at).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE message (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, received_at DATETIME NOT NULL, subject VARCHAR(255) NOT NULL, from_address VARCHAR(255) NOT NULL, body CLOB NOT NULL, alias_id INTEGER NOT NULL, CONSTRAINT FK_B6BD307F16F2B4F9 FOREIGN KEY (alias_id) REFERENCES alias (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_message_alias_received_at ON message (alias_id, received_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE message');
    }
}
