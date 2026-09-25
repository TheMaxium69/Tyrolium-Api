<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260925163028 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "AccessLevel (user/interne/owner) sur users — voir .doc/permissions.md et App\\Enum\\AccessLevel.";
    }

    public function up(Schema $schema): void
    {
        // DEFAULT 'user' obligatoire : sans ça, échoue sur les comptes déjà en
        // base (colonne NOT NULL sans valeur pour les lignes existantes).
        // 'user' correspond au défaut PHP (User::$accessLevel).
        $this->addSql("ALTER TABLE users ADD access_level VARCHAR(20) NOT NULL DEFAULT 'user'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP access_level');
    }
}
