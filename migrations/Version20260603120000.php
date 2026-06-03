<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute le champ handle (non null, unique) sur user en remplissant les lignes existantes.
 */
final class Version20260603120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute user.handle (NOT NULL, UNIQUE) avec remplissage des donnees existantes';
    }

    public function up(Schema $schema): void
    {
        // 1. ajout de la colonne en nullable pour ne pas casser les lignes existantes
        $this->addSql('ALTER TABLE user ADD handle VARCHAR(77) DEFAULT NULL');

        // 2. remplissage avec une valeur UNIQUE par ligne (id = PK, jamais de collision)
        $this->addSql("UPDATE user SET handle = CONCAT('user_', id)");

        // 3. passage en NOT NULL une fois toutes les lignes remplies
        $this->addSql('ALTER TABLE user CHANGE handle handle VARCHAR(77) NOT NULL');

        // 4. pose de l'index unique
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_HANDLE ON user (handle)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_IDENTIFIER_HANDLE ON user');
        $this->addSql('ALTER TABLE user DROP handle');
    }
}
