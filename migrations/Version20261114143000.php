<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store workspace-scoped MailOS inbox headers without message bodies or attachments.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mailbox_message (id SERIAL NOT NULL, workspace_id INT NOT NULL, provider_message_id VARCHAR(160) NOT NULL, provider_thread_id VARCHAR(160) NOT NULL, mailos_mailbox_id VARCHAR(160) NOT NULL, from_address TEXT NOT NULL, to_address TEXT NOT NULL, subject VARCHAR(998) NOT NULL, received_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, is_read BOOLEAN NOT NULL, thread_count INT NOT NULL, synced_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_mailbox_message_external ON mailbox_message (workspace_id, mailos_mailbox_id, provider_message_id)');
        $this->addSql('CREATE INDEX idx_mailbox_message_workspace_date ON mailbox_message (workspace_id, received_at)');
        $this->addSql('CREATE INDEX idx_mailbox_message_thread ON mailbox_message (workspace_id, mailos_mailbox_id, provider_thread_id)');
        $this->addSql('ALTER TABLE mailbox_message ADD CONSTRAINT FK_8E6A9135D6E2F3A7 FOREIGN KEY (workspace_id) REFERENCES workspace (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE mailbox_message');
    }
}
