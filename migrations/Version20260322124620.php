<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322124620 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf(true, 'Duplicate of Version20260320205926 — tables already created.');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE changelog DROP FOREIGN KEY FK_C8422601979B1AD6');
        $this->addSql('ALTER TABLE changelog DROP FOREIGN KEY FK_C842260110BC6D9F');
        $this->addSql('ALTER TABLE employees DROP FOREIGN KEY FK_BA82C300979B1AD6');
        $this->addSql('ALTER TABLE employees DROP FOREIGN KEY FK_BA82C300A76ED395');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY FK_DB021E96979B1AD6');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY FK_DB021E96F624B39D');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY FK_DB021E96E92F8F78');
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D3979B1AD6');
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D3A76ED395');
        $this->addSql('ALTER TABLE overtime_records DROP FOREIGN KEY FK_1EAFB872979B1AD6');
        $this->addSql('ALTER TABLE overtime_records DROP FOREIGN KEY FK_1EAFB8728C03F15C');
        $this->addSql('ALTER TABLE shifts DROP FOREIGN KEY FK_1D1D712F979B1AD6');
        $this->addSql('ALTER TABLE shifts DROP FOREIGN KEY FK_1D1D712F8C03F15C');
        $this->addSql('ALTER TABLE shifts DROP FOREIGN KEY FK_1D1D712FDE12AB56');
        $this->addSql('ALTER TABLE time_entries DROP FOREIGN KEY FK_797F12A3979B1AD6');
        $this->addSql('ALTER TABLE time_entries DROP FOREIGN KEY FK_797F12A38C03F15C');
        $this->addSql('ALTER TABLE time_entries DROP FOREIGN KEY FK_797F12A3D570F2D1');
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E9979B1AD6');
        $this->addSql('DROP TABLE changelog');
        $this->addSql('DROP TABLE companies');
        $this->addSql('DROP TABLE employees');
        $this->addSql('DROP TABLE messages');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE overtime_records');
        $this->addSql('DROP TABLE shifts');
        $this->addSql('DROP TABLE time_entries');
        $this->addSql('DROP TABLE users');
    }
}
