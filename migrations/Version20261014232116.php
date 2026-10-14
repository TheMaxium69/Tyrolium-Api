<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014232116 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Parc de serveurs SolidServ : types (server_type, avec les 7 types historiques) et serveurs (server), permissions solidserv.server.* et solidserv.servertype.*.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE server (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(60) NOT NULL, alias VARCHAR(100) DEFAULT NULL, description LONGTEXT DEFAULT NULL, location VARCHAR(255) DEFAULT NULL, hostname VARCHAR(253) DEFAULT NULL, project VARCHAR(50) DEFAULT NULL, status VARCHAR(20) DEFAULT \'active\' NOT NULL, started_at DATETIME DEFAULT NULL, client_name VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, type_id INT NOT NULL, product_id INT DEFAULT NULL, user_id INT DEFAULT NULL, created_by_id INT NOT NULL, UNIQUE INDEX UNIQ_5A6DD5F65E237E06 (name), INDEX IDX_5A6DD5F6C54C8C93 (type_id), INDEX IDX_5A6DD5F64584665A (product_id), INDEX IDX_5A6DD5F6A76ED395 (user_id), INDEX IDX_5A6DD5F6B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE server_type (id INT AUTO_INCREMENT NOT NULL, abbreviation VARCHAR(10) NOT NULL, name_prefix VARCHAR(20) NOT NULL, name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_75E26C9BCF3411D (abbreviation), UNIQUE INDEX UNIQ_75E26C995C915C3 (name_prefix), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE server ADD CONSTRAINT FK_5A6DD5F6C54C8C93 FOREIGN KEY (type_id) REFERENCES server_type (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE server ADD CONSTRAINT FK_5A6DD5F64584665A FOREIGN KEY (product_id) REFERENCES solidserv_product (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE server ADD CONSTRAINT FK_5A6DD5F6A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE server ADD CONSTRAINT FK_5A6DD5F6B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql("INSERT INTO server_type (abbreviation, name_prefix, name, description, created_at) SELECT 'int', 'int', 'Serveur Interne', 'Il s''agit des serveurs hébergés dans les locaux de Tyrolium.', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM server_type WHERE abbreviation = 'int')");
        $this->addSql("INSERT INTO server_type (abbreviation, name_prefix, name, description, created_at) SELECT 'vps', 'vps', 'VPS', 'Il s''agit de VPS hébergés dans un data center.', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM server_type WHERE abbreviation = 'vps')");
        $this->addSql("INSERT INTO server_type (abbreviation, name_prefix, name, description, created_at) SELECT 'cloud', 'cloud', 'Cloud', 'Il s''agit de serveurs conçus pour héberger exclusivement des sites web.', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM server_type WHERE abbreviation = 'cloud')");
        $this->addSql("INSERT INTO server_type (abbreviation, name_prefix, name, description, created_at) SELECT 'proxy', 'proxy', 'Proxy', 'Il s''agit de serveurs proxy qui permettent de rediriger le trafic vers les sauvegardes.', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM server_type WHERE abbreviation = 'proxy')");
        $this->addSql("INSERT INTO server_type (abbreviation, name_prefix, name, description, created_at) SELECT 'serv', 'serv', 'Serveur dédié', 'Il s''agit de serveurs dédiés hébergés dans un data center.', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM server_type WHERE abbreviation = 'serv')");
        $this->addSql("INSERT INTO server_type (abbreviation, name_prefix, name, description, created_at) SELECT 'db', 'database', 'Base de données', 'Il s''agit de serveurs conçus pour héberger exclusivement des bases de données.', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM server_type WHERE abbreviation = 'db')");
        $this->addSql("INSERT INTO server_type (abbreviation, name_prefix, name, description, created_at) SELECT 'vpn', 'vpn', 'VPN', 'Il s''agit de serveurs utilisés pour les VPN, souvent pour notre sécurité.', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM server_type WHERE abbreviation = 'vpn')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'solidserv.server.view', 'Voir le parc de serveurs', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'solidserv.server.view')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'solidserv.server.create', 'Ajouter un serveur', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'solidserv.server.create')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'solidserv.server.update', 'Modifier un serveur', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'solidserv.server.update')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'solidserv.server.delete', 'Supprimer un serveur', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'solidserv.server.delete')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'solidserv.server.manage', 'Gestion complète du parc de serveurs', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'solidserv.server.manage')");
        $this->addSql("INSERT INTO permission_implication (permission_id, implied_permission_id) SELECT m.id, c.id FROM permission m JOIN permission c ON c.name IN ('solidserv.server.view','solidserv.server.create','solidserv.server.update','solidserv.server.delete') WHERE m.name = 'solidserv.server.manage' AND NOT EXISTS (SELECT 1 FROM permission_implication pi WHERE pi.permission_id = m.id AND pi.implied_permission_id = c.id)");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'solidserv.servertype.view', 'Voir les types de serveurs', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'solidserv.servertype.view')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'solidserv.servertype.create', 'Créer un type de serveur', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'solidserv.servertype.create')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'solidserv.servertype.update', 'Modifier un type de serveur', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'solidserv.servertype.update')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'solidserv.servertype.delete', 'Supprimer un type de serveur', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'solidserv.servertype.delete')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'solidserv.servertype.manage', 'Gestion complète des types de serveurs', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'solidserv.servertype.manage')");
        $this->addSql("INSERT INTO permission_implication (permission_id, implied_permission_id) SELECT m.id, c.id FROM permission m JOIN permission c ON c.name IN ('solidserv.servertype.view','solidserv.servertype.create','solidserv.servertype.update','solidserv.servertype.delete') WHERE m.name = 'solidserv.servertype.manage' AND NOT EXISTS (SELECT 1 FROM permission_implication pi WHERE pi.permission_id = m.id AND pi.implied_permission_id = c.id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM user_permission WHERE permission_id IN (SELECT id FROM permission WHERE name LIKE 'solidserv.server.%')");
        $this->addSql("DELETE FROM api_key_permission WHERE permission_id IN (SELECT id FROM permission WHERE name LIKE 'solidserv.server.%')");
        $this->addSql("DELETE FROM permission_implication WHERE permission_id IN (SELECT id FROM permission WHERE name LIKE 'solidserv.server.%')");
        $this->addSql("DELETE FROM permission WHERE name LIKE 'solidserv.server.%'");
        $this->addSql("DELETE FROM user_permission WHERE permission_id IN (SELECT id FROM permission WHERE name LIKE 'solidserv.servertype.%')");
        $this->addSql("DELETE FROM api_key_permission WHERE permission_id IN (SELECT id FROM permission WHERE name LIKE 'solidserv.servertype.%')");
        $this->addSql("DELETE FROM permission_implication WHERE permission_id IN (SELECT id FROM permission WHERE name LIKE 'solidserv.servertype.%')");
        $this->addSql("DELETE FROM permission WHERE name LIKE 'solidserv.servertype.%'");
        $this->addSql('ALTER TABLE server DROP FOREIGN KEY FK_5A6DD5F6C54C8C93');
        $this->addSql('ALTER TABLE server DROP FOREIGN KEY FK_5A6DD5F64584665A');
        $this->addSql('ALTER TABLE server DROP FOREIGN KEY FK_5A6DD5F6A76ED395');
        $this->addSql('ALTER TABLE server DROP FOREIGN KEY FK_5A6DD5F6B03A8386');
        $this->addSql('DROP TABLE server');
        $this->addSql('DROP TABLE server_type');
    }
}
