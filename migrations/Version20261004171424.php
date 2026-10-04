<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004171424 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Suspension de compte : banned_at et ban_reason sur users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD banned_at DATETIME DEFAULT NULL, ADD ban_reason LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP banned_at, DROP ban_reason');
    }
}
