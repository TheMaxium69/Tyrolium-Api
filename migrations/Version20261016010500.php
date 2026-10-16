<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261016010500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notes : texte des cases à plat (note.items_text) pour une recherche insensible à la casse et aux accents.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE note ADD items_text LONGTEXT DEFAULT NULL');
        $this->addSql("UPDATE note SET items_text = (SELECT GROUP_CONCAT(jt.item_text SEPARATOR '\\n') FROM JSON_TABLE(note.items, '$[*]' COLUMNS (item_text VARCHAR(500) PATH '$.text')) AS jt) WHERE JSON_LENGTH(items) > 0");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE note DROP items_text');
    }
}
