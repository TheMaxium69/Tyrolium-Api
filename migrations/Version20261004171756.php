<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004171756 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permissions de l\'administration Useritium (useritium.user.{view,update,ban,revoke,manage}) avec implication du parapluie manage.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'useritium.user.view', 'Voir les comptes Useritium', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'useritium.user.view')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'useritium.user.update', 'Modifier les comptes Useritium (pseudo, emails)', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'useritium.user.update')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'useritium.user.ban', 'Suspendre et réactiver un compte Useritium', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'useritium.user.ban')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'useritium.user.revoke', 'Révoquer les sessions d\'un compte Useritium', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'useritium.user.revoke')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'useritium.user.manage', 'Gestion complète des comptes Useritium', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'useritium.user.manage')");
        $this->addSql("INSERT INTO permission_implication (permission_id, implied_permission_id) SELECT m.id, c.id FROM permission m JOIN permission c ON c.name IN ('useritium.user.view','useritium.user.update','useritium.user.ban','useritium.user.revoke') WHERE m.name = 'useritium.user.manage' AND NOT EXISTS (SELECT 1 FROM permission_implication pi WHERE pi.permission_id = m.id AND pi.implied_permission_id = c.id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE pi FROM permission_implication pi JOIN permission p ON p.id = pi.permission_id WHERE p.name = 'useritium.user.manage'");
        $this->addSql("DELETE FROM user_permission WHERE permission_id IN (SELECT id FROM permission WHERE name LIKE 'useritium.user.%')");
        $this->addSql("DELETE FROM api_key_permission WHERE permission_id IN (SELECT id FROM permission WHERE name LIKE 'useritium.user.%')");
        $this->addSql("DELETE FROM permission WHERE name LIKE 'useritium.user.%'");
    }
}
