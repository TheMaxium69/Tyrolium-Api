<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260809145248 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add users.tokens_valid_since (logout-all-devices: rejects any JWT whose "iat" predates it).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD tokens_valid_since DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP tokens_valid_since');
    }
}
