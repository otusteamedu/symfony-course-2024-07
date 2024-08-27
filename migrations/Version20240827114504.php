<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240827114504 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE email_user (email VARCHAR(255) NOT NULL, id BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE TABLE phone_user (phone VARCHAR(255) NOT NULL, id BIGINT NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE email_user ADD CONSTRAINT FK_12A5F6CCBF396750 FOREIGN KEY (id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE phone_user ADD CONSTRAINT FK_6E97845BBF396750 FOREIGN KEY (id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "user" ALTER communication_channel SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE email_user DROP CONSTRAINT FK_12A5F6CCBF396750');
        $this->addSql('ALTER TABLE phone_user DROP CONSTRAINT FK_6E97845BBF396750');
        $this->addSql('DROP TABLE email_user');
        $this->addSql('DROP TABLE phone_user');
        $this->addSql('ALTER TABLE "user" ALTER communication_channel DROP NOT NULL');
    }
}
