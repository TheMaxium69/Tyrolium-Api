<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014233009 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registre des sites web : serveur hébergeur (website.server_id, détaché si le serveur est supprimé).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE website ADD server_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE website ADD CONSTRAINT FK_476F5DE71844E6B7 FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_476F5DE71844E6B7 ON website (server_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE website DROP FOREIGN KEY FK_476F5DE71844E6B7');
        $this->addSql('DROP INDEX IDX_476F5DE71844E6B7 ON website');
        $this->addSql('ALTER TABLE website DROP server_id');
    }
}
