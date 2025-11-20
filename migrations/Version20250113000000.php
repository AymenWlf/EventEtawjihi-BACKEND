<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250113000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add userCode field to User entity';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `user` ADD user_code VARCHAR(50) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_USER_CODE ON `user` (user_code)');
        
        // Générer les codes pour les utilisateurs existants
        $this->addSql('UPDATE `user` SET user_code = CONCAT(\'ET-\', LPAD(id, 4, \'0\')) WHERE user_code IS NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_USER_CODE ON `user`');
        $this->addSql('ALTER TABLE `user` DROP user_code');
    }
}


