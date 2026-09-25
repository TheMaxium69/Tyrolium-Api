<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260925181810 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Retire le DEFAULT CURRENT_TIMESTAMP temporaire de users.created_at (servait juste au backfill de la migration précédente, l'ORM fournit toujours la vraie valeur à l'insertion).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users CHANGE created_at created_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
    }
}
