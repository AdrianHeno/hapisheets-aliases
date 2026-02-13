<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260214130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add message.inbound_raw_id (optional link to raw MIME for parsed display).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE message ADD COLUMN inbound_raw_id INTEGER DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_message_inbound_raw ON message (inbound_raw_id)');
        $this->addSql('CREATE INDEX IDX_message_inbound_raw ON message (inbound_raw_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_message_inbound_raw');
        $this->addSql('DROP INDEX IDX_message_inbound_raw');
        $this->addSql('ALTER TABLE message DROP COLUMN inbound_raw_id');
    }
}
