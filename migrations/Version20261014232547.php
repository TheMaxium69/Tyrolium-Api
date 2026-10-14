<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014232547 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registre des sites web : certificat SSL (échéance, renouvellement auto, émetteur) et dépôt git.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE website ADD ssl_expires_at DATETIME DEFAULT NULL, ADD ssl_auto_renew TINYINT DEFAULT 0 NOT NULL, ADD ssl_provider VARCHAR(100) DEFAULT NULL, ADD git_repository VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE website DROP ssl_expires_at, DROP ssl_auto_renew, DROP ssl_provider, DROP git_repository');
    }
}
