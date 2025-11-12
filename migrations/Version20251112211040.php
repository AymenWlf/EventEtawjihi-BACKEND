<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251112211040 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add admin fields to User entity: qrCode, isPresent, lastLoginAt, firstName, lastName, age, telephone';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD qr_code VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_IDENTIFIER_QR_CODE ON `user` (qr_code)');
        $this->addSql('ALTER TABLE `user` ADD is_present TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE `user` ADD last_login_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE `user` ADD first_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD last_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD age INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD telephone VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_IDENTIFIER_QR_CODE ON `user`');
        $this->addSql('ALTER TABLE `user` DROP qr_code');
        $this->addSql('ALTER TABLE `user` DROP is_present');
        $this->addSql('ALTER TABLE `user` DROP last_login_at');
        $this->addSql('ALTER TABLE `user` DROP first_name');
        $this->addSql('ALTER TABLE `user` DROP last_name');
        $this->addSql('ALTER TABLE `user` DROP age');
        $this->addSql('ALTER TABLE `user` DROP telephone');
    }
}
