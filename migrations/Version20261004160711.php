<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004160711 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Analytics : projets suivis (analytics_project) et visites remontées (analytics_input).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE analytics_input (id INT AUTO_INCREMENT NOT NULL, ip VARCHAR(45) NOT NULL, page_name VARCHAR(255) NOT NULL, uri LONGTEXT NOT NULL, is_login TINYINT NOT NULL, created_at DATETIME NOT NULL, project_id INT NOT NULL, INDEX idx_analytics_input_project_created (project_id, created_at), INDEX IDX_7F56097A166D1F9C (project_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE analytics_project (id INT AUTO_INCREMENT NOT NULL, tag VARCHAR(180) NOT NULL, domain_names JSON NOT NULL, created_at DATETIME NOT NULL, created_by_id INT NOT NULL, UNIQUE INDEX UNIQ_85115C10389B783 (tag), INDEX IDX_85115C10B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE analytics_input ADD CONSTRAINT FK_7F56097A166D1F9C FOREIGN KEY (project_id) REFERENCES analytics_project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE analytics_project ADD CONSTRAINT FK_85115C10B03A8386 FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE RESTRICT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE analytics_input DROP FOREIGN KEY FK_7F56097A166D1F9C');
        $this->addSql('ALTER TABLE analytics_project DROP FOREIGN KEY FK_85115C10B03A8386');
        $this->addSql('DROP TABLE analytics_input');
        $this->addSql('DROP TABLE analytics_project');
    }
}
