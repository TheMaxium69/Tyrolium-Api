<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004170414 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Photo de profil utilisateur (users.pp), nullable.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD pp VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP pp');
    }
}
