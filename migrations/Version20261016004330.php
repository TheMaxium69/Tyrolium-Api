<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016004330 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notes personnelles du dashboard Useritium (façon Keep) : note, étiquette et table de liaison, supprimées avec le compte.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE note (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) DEFAULT NULL, content LONGTEXT DEFAULT NULL, type VARCHAR(20) DEFAULT \'text\' NOT NULL, items JSON NOT NULL, color VARCHAR(20) DEFAULT \'default\' NOT NULL, is_pinned TINYINT DEFAULT 0 NOT NULL, is_archived TINYINT DEFAULT 0 NOT NULL, trashed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX idx_note_user_state (user_id, is_archived, trashed_at), INDEX IDX_CFBDFA14A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE note_note_label (note_id INT NOT NULL, label_id INT NOT NULL, INDEX IDX_B10DA0F26ED0855 (note_id), INDEX IDX_B10DA0F33B92F39 (label_id), PRIMARY KEY (note_id, label_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE note_label (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX uniq_note_label_user_name (user_id, name), INDEX IDX_6BB7F33FA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE note ADD CONSTRAINT FK_CFBDFA14A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE note_note_label ADD CONSTRAINT FK_B10DA0F26ED0855 FOREIGN KEY (note_id) REFERENCES note (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE note_note_label ADD CONSTRAINT FK_B10DA0F33B92F39 FOREIGN KEY (label_id) REFERENCES note_label (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE note_label ADD CONSTRAINT FK_6BB7F33FA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE note DROP FOREIGN KEY FK_CFBDFA14A76ED395');
        $this->addSql('ALTER TABLE note_note_label DROP FOREIGN KEY FK_B10DA0F26ED0855');
        $this->addSql('ALTER TABLE note_note_label DROP FOREIGN KEY FK_B10DA0F33B92F39');
        $this->addSql('ALTER TABLE note_label DROP FOREIGN KEY FK_6BB7F33FA76ED395');
        $this->addSql('DROP TABLE note_note_label');
        $this->addSql('DROP TABLE note');
        $this->addSql('DROP TABLE note_label');
    }
}
