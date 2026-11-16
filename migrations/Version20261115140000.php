<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow multiple retained AI provider connections per workspace and mark one active route.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ai_provider_setting ADD active BOOLEAN DEFAULT FALSE NOT NULL');
        $this->addSql('UPDATE ai_provider_setting SET active = TRUE');
        $this->addSql('DROP INDEX uniq_ai_provider_workspace');
        $this->addSql('CREATE UNIQUE INDEX uniq_ai_provider_workspace_provider ON ai_provider_setting (workspace_id, provider)');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('A workspace may now contain several encrypted provider connections; automatically removing them would discard credentials.');
    }
}
