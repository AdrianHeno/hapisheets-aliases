<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260214160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop redundant IDX_message_inbound_raw (UNIQ_message_inbound_raw already indexes the column).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_message_inbound_raw');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE INDEX IDX_message_inbound_raw ON message (inbound_raw_id)');
    }
}
