<?php

declare(strict_types=1);

namespace Buddy\Repman\Migrations;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200723105216 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, "Migration can only be executed safely on 'postgresql'.");

        $this->addSql("INSERT INTO config (key, value) VALUES ('telemetry', 'disabled')");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(!$this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform, "Migration can only be executed safely on 'postgresql'.");

        $this->addSql("DELETE FROM config WHERE key = 'telemetry'");
    }
}
