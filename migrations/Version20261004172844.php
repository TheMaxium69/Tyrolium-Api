<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004172844 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Nom affiché optionnel sur les comptes (users.display_name).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD display_name VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP display_name');
    }
}
