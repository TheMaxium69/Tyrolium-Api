<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261004163335 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Analytics : description optionnelle sur les projets (analytics_project.description).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE analytics_project ADD description LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE analytics_project DROP description');
    }
}
