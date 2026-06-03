<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Pose un index unique sur server_invitation.identifiant.
 */
final class Version20260603184148 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rend server_invitation.identifiant unique (index UNIQUE)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX UNIQ_23D2F105C90409EC ON server_invitation (identifiant)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_23D2F105C90409EC ON server_invitation');
    }
}
