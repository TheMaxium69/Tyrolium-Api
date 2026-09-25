<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260925181751 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'users.created_at — pour UseritiumAdminController::getAllUser().';
    }

    public function up(Schema $schema): void
    {
        // DEFAULT CURRENT_TIMESTAMP obligatoire pour les lignes existantes
        // (colonne NOT NULL) — approximation pour les comptes déjà créés,
        // les nouveaux comptes auront toujours la vraie date via l'ORM.
        $this->addSql('ALTER TABLE users ADD created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP created_at');
    }
}
