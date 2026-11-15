<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shared per-host website inspection pacing state.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE website_host_access_gate (host VARCHAR(253) NOT NULL, next_allowed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, last_seen_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(host))');
        $this->addSql('CREATE INDEX idx_website_host_access_last_seen ON website_host_access_gate (last_seen_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE website_host_access_gate');
    }
}
