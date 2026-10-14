<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004182758 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permissions d\'offre, prestation et analytics (tyrolium.{offre,prestation,analytics}.*) avec implication du parapluie manage.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'tyrolium.offre.view', 'Voir le catalogue des offres', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'tyrolium.offre.view')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'tyrolium.offre.create', 'Créer une offre', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'tyrolium.offre.create')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'tyrolium.offre.update', 'Modifier une offre', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'tyrolium.offre.update')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'tyrolium.offre.delete', 'Supprimer une offre', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'tyrolium.offre.delete')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'tyrolium.offre.manage', 'Gestion complète des offres', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'tyrolium.offre.manage')");
        $this->addSql("INSERT INTO permission_implication (permission_id, implied_permission_id) SELECT m.id, c.id FROM permission m JOIN permission c ON c.name IN ('tyrolium.offre.view','tyrolium.offre.create','tyrolium.offre.update','tyrolium.offre.delete') WHERE m.name = 'tyrolium.offre.manage' AND NOT EXISTS (SELECT 1 FROM permission_implication pi WHERE pi.permission_id = m.id AND pi.implied_permission_id = c.id)");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'tyrolium.prestation.view', 'Voir les prestations', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'tyrolium.prestation.view')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'tyrolium.prestation.create', 'Créer une prestation', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'tyrolium.prestation.create')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'tyrolium.prestation.update', 'Modifier une prestation', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'tyrolium.prestation.update')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'tyrolium.prestation.delete', 'Supprimer une prestation', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'tyrolium.prestation.delete')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'tyrolium.prestation.manage', 'Gestion complète des prestations', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'tyrolium.prestation.manage')");
        $this->addSql("INSERT INTO permission_implication (permission_id, implied_permission_id) SELECT m.id, c.id FROM permission m JOIN permission c ON c.name IN ('tyrolium.prestation.view','tyrolium.prestation.create','tyrolium.prestation.update','tyrolium.prestation.delete') WHERE m.name = 'tyrolium.prestation.manage' AND NOT EXISTS (SELECT 1 FROM permission_implication pi WHERE pi.permission_id = m.id AND pi.implied_permission_id = c.id)");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'tyrolium.analytics.view', 'Voir les analytics', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'tyrolium.analytics.view')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'tyrolium.analytics.create', 'Créer un projet analytics', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'tyrolium.analytics.create')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'tyrolium.analytics.update', 'Modifier un projet analytics', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'tyrolium.analytics.update')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'tyrolium.analytics.delete', 'Supprimer un projet analytics', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'tyrolium.analytics.delete')");
        $this->addSql("INSERT INTO permission (name, label, created_at) SELECT 'tyrolium.analytics.manage', 'Gestion complète des analytics', NOW() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM permission WHERE name = 'tyrolium.analytics.manage')");
        $this->addSql("INSERT INTO permission_implication (permission_id, implied_permission_id) SELECT m.id, c.id FROM permission m JOIN permission c ON c.name IN ('tyrolium.analytics.view','tyrolium.analytics.create','tyrolium.analytics.update','tyrolium.analytics.delete') WHERE m.name = 'tyrolium.analytics.manage' AND NOT EXISTS (SELECT 1 FROM permission_implication pi WHERE pi.permission_id = m.id AND pi.implied_permission_id = c.id)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM user_permission WHERE permission_id IN (SELECT id FROM permission WHERE name LIKE 'tyrolium.offre.%')");
        $this->addSql("DELETE FROM api_key_permission WHERE permission_id IN (SELECT id FROM permission WHERE name LIKE 'tyrolium.offre.%')");
        $this->addSql("DELETE FROM permission WHERE name LIKE 'tyrolium.offre.%'");
        $this->addSql("DELETE FROM user_permission WHERE permission_id IN (SELECT id FROM permission WHERE name LIKE 'tyrolium.prestation.%')");
        $this->addSql("DELETE FROM api_key_permission WHERE permission_id IN (SELECT id FROM permission WHERE name LIKE 'tyrolium.prestation.%')");
        $this->addSql("DELETE FROM permission WHERE name LIKE 'tyrolium.prestation.%'");
        $this->addSql("DELETE FROM user_permission WHERE permission_id IN (SELECT id FROM permission WHERE name LIKE 'tyrolium.analytics.%')");
        $this->addSql("DELETE FROM api_key_permission WHERE permission_id IN (SELECT id FROM permission WHERE name LIKE 'tyrolium.analytics.%')");
        $this->addSql("DELETE FROM permission WHERE name LIKE 'tyrolium.analytics.%'");
    }
}
