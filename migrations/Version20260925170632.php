<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260925170632 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table permission_implication (permissions "parapluie") — voir .doc/permissions.md.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE permission_implication (permission_id INT NOT NULL, implied_permission_id INT NOT NULL, INDEX IDX_D9563D4FFED90CCA (permission_id), INDEX IDX_D9563D4F9B975777 (implied_permission_id), PRIMARY KEY (permission_id, implied_permission_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE permission_implication ADD CONSTRAINT FK_D9563D4FFED90CCA FOREIGN KEY (permission_id) REFERENCES permission (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE permission_implication ADD CONSTRAINT FK_D9563D4F9B975777 FOREIGN KEY (implied_permission_id) REFERENCES permission (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE permission_implication DROP FOREIGN KEY FK_D9563D4FFED90CCA');
        $this->addSql('ALTER TABLE permission_implication DROP FOREIGN KEY FK_D9563D4F9B975777');
        $this->addSql('DROP TABLE permission_implication');
    }
}
