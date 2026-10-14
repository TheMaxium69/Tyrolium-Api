<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014230126 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Registre des sites web (website) et permissions tyrolium.website.* avec implication du parapluie manage.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE website (id INT AUTO_INCREMENT NOT NULL, domain_name VARCHAR(253) NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, owner_type VARCHAR(20) NOT NULL, filiale VARCHAR(20) DEFAULT NULL, client_name VARCHAR(255) DEFAULT NULL, status VARCHAR(20) DEFAULT \'active\' NOT NULL, registrar VARCHAR(100) DEFAULT NULL, domain_expires_at DATETIME DEFAULT NULL, auto_renew TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, prestation_id INT DEFAULT NULL, user_id INT DEFAULT NULL, created_by_id INT NOT NULL, UNIQUE INDEX UNIQ_476F5DE7F3FF5361 (domain_name), INDEX IDX_476F5DE79E45C554 (prestation_id), INDEX IDX_476F5DE7A76ED395 (user_id), INDEX IDX_476F5DE7B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE website ADD CONSTRAINT FK_476F5DE79E45C554 FOREIGN KEY (prestation_id) REFERENCES prestation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE website ADD CONSTRAINT FK_476F5DE7A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE website ADD CONSTRAINT FK_476F5DE7B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'tyrolium.website.view', 'Voir le registre des sites web', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'tyrolium.website.view')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'tyrolium.website.create', 'Ajouter un site web au registre', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'tyrolium.website.create')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'tyrolium.website.update', 'Modifier un site web du registre', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'tyrolium.website.update')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'tyrolium.website.delete', 'Supprimer un site web du registre', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'tyrolium.website.delete')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'tyrolium.website.manage', 'Gestion complète du registre des sites web', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'tyrolium.website.manage')");
        $this->addSql("INSERT INTO permission_implication (permission_id, implied_permission_id) SELECT m.id, c.id FROM permission m JOIN permission c ON c.name IN ('tyrolium.website.view','tyrolium.website.create','tyrolium.website.update','tyrolium.website.delete') WHERE m.name = 'tyrolium.website.manage' AND NOT EXISTS (SELECT 1 FROM permission_implication pi WHERE pi.permission_id = m.id AND pi.implied_permission_id = c.id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM user_permission WHERE permission_id IN (SELECT id FROM permission WHERE name LIKE 'tyrolium.website.%')");
        $this->addSql("DELETE FROM api_key_permission WHERE permission_id IN (SELECT id FROM permission WHERE name LIKE 'tyrolium.website.%')");
        $this->addSql("DELETE FROM permission WHERE name LIKE 'tyrolium.website.%'");
        $this->addSql('ALTER TABLE website DROP FOREIGN KEY FK_476F5DE79E45C554');
        $this->addSql('ALTER TABLE website DROP FOREIGN KEY FK_476F5DE7A76ED395');
        $this->addSql('ALTER TABLE website DROP FOREIGN KEY FK_476F5DE7B03A8386');
        $this->addSql('DROP TABLE website');
    }
}
