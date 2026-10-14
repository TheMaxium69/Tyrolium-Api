<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014231259 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Catalogue des produits SolidServ (solidserv_product) et permissions solidserv.product.* avec implication du parapluie manage.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE solidserv_product (id INT AUTO_INCREMENT NOT NULL, tag_name VARCHAR(100) NOT NULL, display_name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, visibility VARCHAR(20) NOT NULL, price_monthly INT DEFAULT NULL, price_yearly INT DEFAULT NULL, cpu_cores INT DEFAULT NULL, ram_mb INT DEFAULT NULL, storage_gb INT DEFAULT NULL, stock INT DEFAULT NULL, position INT DEFAULT 0 NOT NULL, is_active TINYINT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, offre_id INT DEFAULT NULL, created_by_id INT NOT NULL, UNIQUE INDEX UNIQ_63121E6CB02CC1B0 (tag_name), INDEX IDX_63121E6C4CC8505A (offre_id), INDEX IDX_63121E6CB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE solidserv_product ADD CONSTRAINT FK_63121E6C4CC8505A FOREIGN KEY (offre_id) REFERENCES offre (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE solidserv_product ADD CONSTRAINT FK_63121E6CB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'solidserv.product.view', 'Voir le catalogue des produits SolidServ', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'solidserv.product.view')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'solidserv.product.create', 'Créer un produit SolidServ', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'solidserv.product.create')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'solidserv.product.update', 'Modifier un produit SolidServ', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'solidserv.product.update')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'solidserv.product.delete', 'Supprimer un produit SolidServ', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'solidserv.product.delete')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'solidserv.product.manage', 'Gestion complète des produits SolidServ', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'solidserv.product.manage')");
        $this->addSql("INSERT INTO permission_implication (permission_id, implied_permission_id) SELECT m.id, c.id FROM permission m JOIN permission c ON c.name IN ('solidserv.product.view','solidserv.product.create','solidserv.product.update','solidserv.product.delete') WHERE m.name = 'solidserv.product.manage' AND NOT EXISTS (SELECT 1 FROM permission_implication pi WHERE pi.permission_id = m.id AND pi.implied_permission_id = c.id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM user_permission WHERE permission_id IN (SELECT id FROM permission WHERE name LIKE 'solidserv.product.%')");
        $this->addSql("DELETE FROM api_key_permission WHERE permission_id IN (SELECT id FROM permission WHERE name LIKE 'solidserv.product.%')");
        $this->addSql("DELETE FROM permission WHERE name LIKE 'solidserv.product.%'");
        $this->addSql('ALTER TABLE solidserv_product DROP FOREIGN KEY FK_63121E6C4CC8505A');
        $this->addSql('ALTER TABLE solidserv_product DROP FOREIGN KEY FK_63121E6CB03A8386');
        $this->addSql('DROP TABLE solidserv_product');
    }
}
