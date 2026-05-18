<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260514234233 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE user_server (user_id INT NOT NULL, server_id INT NOT NULL, INDEX IDX_3F3FCECBA76ED395 (user_id), INDEX IDX_3F3FCECB1844E6B7 (server_id), PRIMARY KEY (user_id, server_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_server ADD CONSTRAINT FK_3F3FCECBA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_server ADD CONSTRAINT FK_3F3FCECB1844E6B7 FOREIGN KEY (server_id) REFERENCES server (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY `FK_8D93D6491844E6B7`');
        $this->addSql('DROP INDEX IDX_8D93D6491844E6B7 ON user');
        $this->addSql('ALTER TABLE user DROP server_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE user_server DROP FOREIGN KEY FK_3F3FCECBA76ED395');
        $this->addSql('ALTER TABLE user_server DROP FOREIGN KEY FK_3F3FCECB1844E6B7');
        $this->addSql('DROP TABLE user_server');
        $this->addSql('ALTER TABLE user ADD server_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT `FK_8D93D6491844E6B7` FOREIGN KEY (server_id) REFERENCES server (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_8D93D6491844E6B7 ON user (server_id)');
    }
}
