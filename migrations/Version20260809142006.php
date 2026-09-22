<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260809142006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add user_email (multi-email support, one default per user enforced at DB level via generated column) and password reset token on users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_email (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, is_default TINYINT DEFAULT 0 NOT NULL, is_verified TINYINT DEFAULT 0 NOT NULL, verified_at DATETIME DEFAULT NULL, verification_token VARCHAR(64) DEFAULT NULL, verification_token_expires_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_550872CC1CC006B (verification_token), INDEX IDX_550872CA76ED395 (user_id), UNIQUE INDEX uniq_user_email (user_id, email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_email ADD CONSTRAINT FK_550872CA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');

        // MySQL n'a pas d'index unique partiel (contrairement à Postgres "WHERE is_default").
        // On émule la contrainte "un seul email default par utilisateur, globalement, tous
        // comptes confondus" avec une colonne générée : NULL quand is_default = false (les
        // NULL ne sont jamais en conflit dans un index unique MySQL), sinon = l'email.
        $this->addSql('ALTER TABLE user_email ADD default_email_if_set VARCHAR(180) GENERATED ALWAYS AS (IF(is_default = 1, email, NULL)) STORED');
        $this->addSql('CREATE UNIQUE INDEX uniq_default_email ON user_email (default_email_if_set)');

        $this->addSql('ALTER TABLE users ADD reset_token VARCHAR(64) DEFAULT NULL, ADD reset_token_expires_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9D7C8DC19 ON users (reset_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_email DROP FOREIGN KEY FK_550872CA76ED395');
        $this->addSql('DROP TABLE user_email');
        $this->addSql('DROP INDEX UNIQ_1483A5E9D7C8DC19 ON users');
        $this->addSql('ALTER TABLE users DROP reset_token, DROP reset_token_expires_at');
    }
}
