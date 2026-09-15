<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Fresh database baseline. Review existing prototype data before migrating it. */
final class Version20260915023000 extends AbstractMigration
{
    public function getDescription(): string { return 'Create the revived GlobalGiving catalog with natural source identifiers'; }

    public function up(Schema $schema): void
    {
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform, 'This baseline is for PostgreSQL.');
        $this->addSql('CREATE TABLE index_info (index_name VARCHAR(255) NOT NULL, last_indexed TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, document_count INT NOT NULL, settings JSONB NOT NULL, task_id VARCHAR(255) DEFAULT NULL, primary_key VARCHAR(255) NOT NULL, batch_id VARCHAR(255) DEFAULT NULL, status VARCHAR(20) DEFAULT NULL, label VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, aggregator VARCHAR(255) DEFAULT NULL, institution VARCHAR(255) DEFAULT NULL, country VARCHAR(255) DEFAULT NULL, locale VARCHAR(255) DEFAULT NULL, PRIMARY KEY (index_name))');
        $this->addSql('CREATE TABLE theme (code VARCHAR(32) NOT NULL, label VARCHAR(255) NOT NULL, PRIMARY KEY (code))');
        $this->addSql('CREATE TABLE project (id BIGINT NOT NULL, title TEXT NOT NULL, summary TEXT DEFAULT NULL, need TEXT DEFAULT NULL, activities TEXT DEFAULT NULL, long_term_impact TEXT DEFAULT NULL, active BOOLEAN NOT NULL, status VARCHAR(64) DEFAULT NULL, country_code VARCHAR(3) DEFAULT NULL, countries JSON NOT NULL, themes JSON NOT NULL, funding DOUBLE PRECISION DEFAULT NULL, goal DOUBLE PRECISION DEFAULT NULL, latitude DOUBLE PRECISION DEFAULT NULL, longitude DOUBLE PRECISION DEFAULT NULL, image_url TEXT DEFAULT NULL, project_url TEXT DEFAULT NULL, modified_date VARCHAR(64) DEFAULT NULL, source JSON NOT NULL, imported_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, import_run VARCHAR(64) NOT NULL, organization_id BIGINT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_2FB3D0EE32C8A3DE ON project (organization_id)');
        $this->addSql('CREATE TABLE organization (id BIGINT NOT NULL, name VARCHAR(1024) NOT NULL, mission TEXT DEFAULT NULL, ein VARCHAR(32) DEFAULT NULL, country_code VARCHAR(3) DEFAULT NULL, countries JSON NOT NULL, themes JSON NOT NULL, total_projects INT NOT NULL, logo_url TEXT DEFAULT NULL, url TEXT DEFAULT NULL, source JSON NOT NULL, imported_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, research JSON DEFAULT NULL, researched_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE project ADD CONSTRAINT FK_2FB3D0EE32C8A3DE FOREIGN KEY (organization_id) REFERENCES organization (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Catalog and research data require an explicit backup/restore decision.');
    }
}
