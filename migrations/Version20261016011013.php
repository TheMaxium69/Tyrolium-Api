<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016011013 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tâches personnelles du dashboard Useritium : tâche (avec sous-tâches), projet kanban, colonne, étiquette et table de liaison, supprimés avec le compte.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE task (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, is_done TINYINT DEFAULT 0 NOT NULL, completed_at DATETIME DEFAULT NULL, priority SMALLINT DEFAULT 0 NOT NULL, due_date DATE DEFAULT NULL, repeat_rule VARCHAR(10) DEFAULT \'none\' NOT NULL, position INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, parent_id INT DEFAULT NULL, project_id INT DEFAULT NULL, column_id INT DEFAULT NULL, INDEX idx_task_user_scope (user_id, parent_id, project_id, is_done), INDEX idx_task_column_position (column_id, position), INDEX IDX_527EDB25A76ED395 (user_id), INDEX IDX_527EDB25727ACA70 (parent_id), INDEX IDX_527EDB25166D1F9C (project_id), INDEX IDX_527EDB25BE8E8ED5 (column_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE task_task_label (task_id INT NOT NULL, label_id INT NOT NULL, INDEX IDX_D51000678DB60186 (task_id), INDEX IDX_D510006733B92F39 (label_id), PRIMARY KEY (task_id, label_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE task_column (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, color VARCHAR(20) DEFAULT \'default\' NOT NULL, position INT DEFAULT 0 NOT NULL, wip_limit INT DEFAULT NULL, is_done TINYINT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, project_id INT NOT NULL, INDEX IDX_46FA03AD166D1F9C (project_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE task_label (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, color VARCHAR(20) DEFAULT \'default\' NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX uniq_task_label_user_name (user_id, name), INDEX IDX_C9034BC8A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE task_project (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, color VARCHAR(20) DEFAULT \'default\' NOT NULL, is_archived TINYINT DEFAULT 0 NOT NULL, position INT DEFAULT 0 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_3052FA04A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25727ACA70 FOREIGN KEY (parent_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25166D1F9C FOREIGN KEY (project_id) REFERENCES task_project (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE task ADD CONSTRAINT FK_527EDB25BE8E8ED5 FOREIGN KEY (column_id) REFERENCES task_column (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE task_task_label ADD CONSTRAINT FK_D51000678DB60186 FOREIGN KEY (task_id) REFERENCES task (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_task_label ADD CONSTRAINT FK_D510006733B92F39 FOREIGN KEY (label_id) REFERENCES task_label (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_column ADD CONSTRAINT FK_46FA03AD166D1F9C FOREIGN KEY (project_id) REFERENCES task_project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_label ADD CONSTRAINT FK_C9034BC8A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE task_project ADD CONSTRAINT FK_3052FA04A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25A76ED395');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25727ACA70');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25166D1F9C');
        $this->addSql('ALTER TABLE task DROP FOREIGN KEY FK_527EDB25BE8E8ED5');
        $this->addSql('ALTER TABLE task_task_label DROP FOREIGN KEY FK_D51000678DB60186');
        $this->addSql('ALTER TABLE task_task_label DROP FOREIGN KEY FK_D510006733B92F39');
        $this->addSql('ALTER TABLE task_column DROP FOREIGN KEY FK_46FA03AD166D1F9C');
        $this->addSql('ALTER TABLE task_label DROP FOREIGN KEY FK_C9034BC8A76ED395');
        $this->addSql('ALTER TABLE task_project DROP FOREIGN KEY FK_3052FA04A76ED395');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE task_task_label');
        $this->addSql('DROP TABLE task_column');
        $this->addSql('DROP TABLE task_label');
        $this->addSql('DROP TABLE task_project');
    }
}
