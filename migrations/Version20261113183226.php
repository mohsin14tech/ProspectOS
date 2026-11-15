<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261113183226 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE evidence_item ADD source_url TEXT DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_evidence_run_prospect ON evidence_item (research_run_id, prospect_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_evidence_run_prospect_type ON evidence_item (research_run_id, prospect_id, finding_type)');
        $this->addSql('CREATE INDEX idx_prospect_workspace_updated ON prospect (workspace_id, updated_at)');
        $this->addSql('CREATE UNIQUE INDEX uniq_prospect_workspace_slug ON prospect (workspace_id, slug)');
        $this->addSql('CREATE INDEX idx_research_run_workspace_created ON research_run (workspace_id, created_at)');
        $this->addSql('ALTER TABLE score_snapshot ALTER score DROP NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_score_run_prospect ON score_snapshot (research_run_id, prospect_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_workspace_slug ON workspace (slug)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_evidence_run_prospect');
        $this->addSql('DROP INDEX uniq_evidence_run_prospect_type');
        $this->addSql('ALTER TABLE evidence_item DROP source_url');
        $this->addSql('DROP INDEX idx_prospect_workspace_updated');
        $this->addSql('DROP INDEX uniq_prospect_workspace_slug');
        $this->addSql('DROP INDEX idx_research_run_workspace_created');
        $this->addSql('DROP INDEX uniq_score_run_prospect');
        $this->addSql('ALTER TABLE score_snapshot ALTER score SET NOT NULL');
        $this->addSql('DROP INDEX uniq_workspace_slug');
    }
}
