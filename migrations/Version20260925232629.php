<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260925232629 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Offre (catalogue) + Prestation (lien offre <-> client) — TyroliumPrestationController.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE offre (id INT AUTO_INCREMENT NOT NULL, tag_name VARCHAR(100) NOT NULL, display_name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, visibility VARCHAR(20) NOT NULL, price INT DEFAULT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_AF86866FB02CC1B0 (tag_name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE prestation (id INT AUTO_INCREMENT NOT NULL, client_name VARCHAR(255) DEFAULT NULL, client_email VARCHAR(180) DEFAULT NULL, status VARCHAR(20) NOT NULL, content LONGTEXT DEFAULT NULL, progress INT DEFAULT 0 NOT NULL, price INT DEFAULT NULL, started_at DATETIME NOT NULL, ended_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, offre_id INT NOT NULL, user_id INT DEFAULT NULL, created_by_id INT NOT NULL, INDEX IDX_51C88FAD4CC8505A (offre_id), INDEX IDX_51C88FADA76ED395 (user_id), INDEX IDX_51C88FADB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE prestation ADD CONSTRAINT FK_51C88FAD4CC8505A FOREIGN KEY (offre_id) REFERENCES offre (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE prestation ADD CONSTRAINT FK_51C88FADA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE prestation ADD CONSTRAINT FK_51C88FADB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE prestation DROP FOREIGN KEY FK_51C88FAD4CC8505A');
        $this->addSql('ALTER TABLE prestation DROP FOREIGN KEY FK_51C88FADA76ED395');
        $this->addSql('ALTER TABLE prestation DROP FOREIGN KEY FK_51C88FADB03A8386');
        $this->addSql('DROP TABLE offre');
        $this->addSql('DROP TABLE prestation');
    }
}
