<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260925181629 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Clés API (api_key + api_key_permission) — voir .doc/permissions.md.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE api_key (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(255) NOT NULL, content LONGTEXT DEFAULT NULL, environment VARCHAR(20) NOT NULL, key_hash VARCHAR(64) NOT NULL, key_preview VARCHAR(40) NOT NULL, expires_at DATETIME DEFAULT NULL, revoked_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, created_by_id INT NOT NULL, UNIQUE INDEX UNIQ_C912ED9D57BFB971 (key_hash), INDEX IDX_C912ED9DB03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE api_key_permission (id INT AUTO_INCREMENT NOT NULL, granted_at DATETIME NOT NULL, api_key_id INT NOT NULL, permission_id INT NOT NULL, granted_by_id INT DEFAULT NULL, UNIQUE INDEX uniq_api_key_permission (api_key_id, permission_id), INDEX IDX_B4426EAB8BE312B3 (api_key_id), INDEX IDX_B4426EABFED90CCA (permission_id), INDEX IDX_B4426EAB3151C11F (granted_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE api_key ADD CONSTRAINT FK_C912ED9DB03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE api_key_permission ADD CONSTRAINT FK_B4426EAB8BE312B3 FOREIGN KEY (api_key_id) REFERENCES api_key (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE api_key_permission ADD CONSTRAINT FK_B4426EABFED90CCA FOREIGN KEY (permission_id) REFERENCES permission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE api_key_permission ADD CONSTRAINT FK_B4426EAB3151C11F FOREIGN KEY (granted_by_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE api_key DROP FOREIGN KEY FK_C912ED9DB03A8386');
        $this->addSql('ALTER TABLE api_key_permission DROP FOREIGN KEY FK_B4426EAB8BE312B3');
        $this->addSql('ALTER TABLE api_key_permission DROP FOREIGN KEY FK_B4426EABFED90CCA');
        $this->addSql('ALTER TABLE api_key_permission DROP FOREIGN KEY FK_B4426EAB3151C11F');
        $this->addSql('DROP TABLE api_key');
        $this->addSql('DROP TABLE api_key_permission');
    }
}
