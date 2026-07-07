<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Supprime la colonne user.uuid, orpheline depuis son retrait de l'entite User.
 * La migration initiale la creait encore, donc elle reapparaissait sur une install fraiche.
 */
final class Version20260707120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime user.uuid (colonne orpheline recreee a chaque fresh install)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_IDENTIFIER_UUID ON user');
        $this->addSql('ALTER TABLE user DROP uuid');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user ADD uuid VARCHAR(180) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_UUID ON user (uuid)');
    }
}
