<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014232651 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prestation : lien du dépôt git (prestation.git_repository).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE prestation ADD git_repository VARCHAR(500) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE prestation DROP git_repository');
    }
}
