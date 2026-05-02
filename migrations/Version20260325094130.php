<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260325094130 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE channel (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(77) NOT NULL, type VARCHAR(255) NOT NULL, category VARCHAR(47) DEFAULT NULL, server_id INT NOT NULL, INDEX IDX_A2F98E471844E6B7 (server_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE message (id INT AUTO_INCREMENT NOT NULL, timestamp DATETIME NOT NULL, content LONGTEXT NOT NULL, attachment VARCHAR(500) DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_B6BD307FA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE server (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, icon VARCHAR(500) DEFAULT NULL, roles JSON NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE theme (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(70) NOT NULL, css_rules LONGTEXT NOT NULL, usage_count INT NOT NULL, user_id INT NOT NULL, INDEX IDX_9775E708A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE channel ADD CONSTRAINT FK_A2F98E471844E6B7 FOREIGN KEY (server_id) REFERENCES server (id)');
        $this->addSql('ALTER TABLE message ADD CONSTRAINT FK_B6BD307FA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE theme ADD CONSTRAINT FK_9775E708A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user ADD server_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D6491844E6B7 FOREIGN KEY (server_id) REFERENCES server (id)');
        $this->addSql('CREATE INDEX IDX_8D93D6491844E6B7 ON user (server_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE channel DROP FOREIGN KEY FK_A2F98E471844E6B7');
        $this->addSql('ALTER TABLE message DROP FOREIGN KEY FK_B6BD307FA76ED395');
        $this->addSql('ALTER TABLE theme DROP FOREIGN KEY FK_9775E708A76ED395');
        $this->addSql('DROP TABLE channel');
        $this->addSql('DROP TABLE message');
        $this->addSql('DROP TABLE server');
        $this->addSql('DROP TABLE theme');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D6491844E6B7');
        $this->addSql('DROP INDEX IDX_8D93D6491844E6B7 ON user');
        $this->addSql('ALTER TABLE user DROP server_id');
    }
}
